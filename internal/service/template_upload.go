package service

import (
	"archive/zip"
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"os"
	"path"
	"path/filepath"
	"regexp"
	"sort"
	"strings"
	"time"
)

const (
	maxTemplateArchiveEntries = 1000
	maxTemplateExtractedSize  = 100 << 20
)

var templateRequiredPlaceholders = []string{
	"{{TITLE}}",
	"{{POST_ID}}",
	"{{META_TAGS}}",
	"{{CUSTOM_FONTS}}",
	"{{BODY_STYLE}}",
	"{{CONTENT_WRAPPER_START}}",
	"{{DATE}}",
	"{{CONTENT}}",
	"{{CONTENT_WRAPPER_END}}",
}

var templateBlockedExtensions = map[string]bool{
	"php": true, "phtml": true, "php3": true, "php4": true, "php5": true,
	"php7": true, "php8": true, "phps": true, "phar": true, "inc": true,
	"pht": true, "cgi": true, "pl": true, "py": true, "jsp": true,
	"asp": true, "aspx": true, "exe": true, "bat": true, "sh": true,
	"cmd": true, "htaccess": true, "htpasswd": true,
}

var templateAttributeURLPattern = regexp.MustCompile(`(?i)(src|href)=["'](.*?)["']`)
var templateCSSURLPattern = regexp.MustCompile(`(?i)url\(["']?(.*?)["']?\)`)
var templateNameCleaner = regexp.MustCompile(`[^a-zA-Z0-9_-]`)

type templateUploadMeta struct {
	Title       string `json:"title"`
	Description string `json:"description"`
	IsSystem    bool   `json:"is_system"`
	Path        string `json:"path"`
}

// UploadTemplate добавляет HTML или ZIP в существующий каталог без замены других шаблонов.
// Возвращает имя шаблона и список отсутствующих обязательных placeholders.
func (t *TemplateService) UploadTemplate(activeBlogPath, originalName string, data []byte) (string, []string, error) {
	t.uploadMu.Lock()
	defer t.uploadMu.Unlock()

	originalName = filepath.Base(strings.ReplaceAll(originalName, "\\", "/"))
	ext := strings.ToLower(filepath.Ext(originalName))
	if ext != ".html" && ext != ".htm" && ext != ".zip" {
		return "", nil, errors.New("разрешено загружать только файлы .html или .zip")
	}
	if len(data) == 0 {
		return "", nil, errors.New("загружен пустой файл")
	}

	templatesDir := t.storage.GetDataPath(activeBlogPath, "blog/templates")
	baseName := strings.TrimSuffix(originalName, ext)
	cleanName := templateNameCleaner.ReplaceAllString(baseName, "")
	if cleanName == "" {
		cleanName = "custom_" + time.Now().Format("20060102150405")
	}
	if strings.EqualFold(cleanName, "main") || strings.EqualFold(cleanName, "NPBlog") {
		cleanName = "theme_" + time.Now().Format("20060102150405")
	}

	finalName := cleanName
	for counter := 1; ; counter++ {
		_, err := os.Stat(filepath.Join(templatesDir, finalName))
		if os.IsNotExist(err) {
			break
		}
		if err != nil {
			return "", nil, errors.New("каталог шаблонов недоступен")
		}
		finalName = fmt.Sprintf("%s_%d", cleanName, counter)
	}
	destDir := filepath.Join(templatesDir, finalName)

	var templateCode []byte
	var templatePath string
	var archive *zip.Reader
	var htmlEntry *zip.File

	if ext == ".zip" {
		var err error
		archive, err = zip.NewReader(bytes.NewReader(data), int64(len(data)))
		if err != nil {
			return "", nil, errors.New("не удалось открыть zip архив")
		}
		if len(archive.File) == 0 || len(archive.File) > maxTemplateArchiveEntries {
			return "", nil, errors.New("архив пуст или содержит слишком много файлов")
		}

		var totalSize uint64
		htmlEntries := make([]*zip.File, 0)
		for _, entry := range archive.File {
			cleanEntryName, err := validateTemplateArchiveEntry(entry)
			if err != nil {
				return "", nil, err
			}
			if entry.FileInfo().IsDir() {
				continue
			}
			if entry.UncompressedSize64 > maxTemplateExtractedSize-totalSize {
				return "", nil, errors.New("распакованный размер архива превышает 100 МБ")
			}
			totalSize += entry.UncompressedSize64
			entryExt := strings.ToLower(path.Ext(cleanEntryName))
			if entryExt == ".html" || entryExt == ".htm" {
				htmlEntries = append(htmlEntries, entry)
			}
		}
		if len(htmlEntries) == 0 {
			return "", nil, errors.New("в архиве не найден файл шаблона .html")
		}
		sort.Slice(htmlEntries, func(i, j int) bool {
			return htmlEntries[i].Name < htmlEntries[j].Name
		})
		htmlEntry = htmlEntries[0]
		templateCode, err = readTemplateArchiveFile(htmlEntry)
		if err != nil {
			return "", nil, errors.New("не удалось прочитать файл шаблона из архива")
		}
		templatePath, _ = validateTemplateArchiveEntry(htmlEntry)
	} else {
		templateCode = data
		templatePath = finalName + ".html"
	}

	missing := validateUploadedTemplateCode(templateCode)
	if len(missing) > 0 {
		return "", missing, fmt.Errorf("в файле шаблона отсутствуют необходимые плейсхолдеры: %s", strings.Join(missing, ", "))
	}

	if err := os.MkdirAll(destDir, 0755); err != nil {
		return "", nil, errors.New("не удалось создать папку шаблона")
	}
	committed := false
	defer func() {
		if !committed {
			_ = os.RemoveAll(destDir)
		}
	}()

	if archive != nil {
		for _, entry := range archive.File {
			cleanEntryName, _ := validateTemplateArchiveEntry(entry)
			targetPath, err := t.storage.ValidateSafePath(destDir, filepath.FromSlash(cleanEntryName))
			if err != nil {
				return "", nil, errors.New("архив содержит недопустимый путь к файлу")
			}
			if entry.FileInfo().IsDir() {
				if err := os.MkdirAll(targetPath, 0755); err != nil {
					return "", nil, errors.New("не удалось распаковать архив")
				}
				continue
			}

			var content []byte
			if entry == htmlEntry {
				dirPart := finalName + "/" + filepath.ToSlash(filepath.Dir(templatePath))
				content = rewriteUploadedTemplatePaths(templateCode, dirPart, templatesDir)
			} else {
				content, err = readTemplateArchiveFile(entry)
				if err != nil {
					return "", nil, errors.New("не удалось распаковать архив")
				}
			}
			if err := t.storage.SafeWriteFile(targetPath, content); err != nil {
				return "", nil, errors.New("не удалось сохранить файлы шаблона")
			}
		}
	} else {
		content := rewriteUploadedTemplatePaths(templateCode, finalName, templatesDir)
		targetPath, err := t.storage.ValidateSafePath(destDir, templatePath)
		if err != nil {
			return "", nil, errors.New("некорректный путь шаблона")
		}
		if err := t.storage.SafeWriteFile(targetPath, content); err != nil {
			return "", nil, errors.New("не удалось сохранить файл шаблона")
		}
	}

	settingsFile := filepath.Join(templatesDir, "settings.json")
	settings := make(map[string]json.RawMessage)
	if err := t.storage.SafeReadJSON(settingsFile, &settings); err != nil {
		if !os.IsNotExist(err) {
			return "", nil, errors.New("настройки шаблонов повреждены; существующий файл не изменён")
		}
	}
	templates := make(map[string]templateUploadMeta)
	if raw, ok := settings["templates"]; ok {
		if err := json.Unmarshal(raw, &templates); err != nil {
			return "", nil, errors.New("каталог шаблонов повреждён; существующий файл не изменён")
		}
	}
	if _, exists := templates[finalName]; exists {
		return "", nil, errors.New("шаблон с таким именем уже есть в каталоге; переименуйте загружаемый файл")
	}
	templates[finalName] = templateUploadMeta{
		Title:       baseName,
		Description: "Пользовательский шаблон, загруженный " + time.Now().Format("02.01.2006 15:04"),
		IsSystem:    false,
		Path:        finalName + "/" + filepath.ToSlash(templatePath),
	}
	rawTemplates, err := json.Marshal(templates)
	if err != nil {
		return "", nil, errors.New("не удалось подготовить настройки шаблонов")
	}
	settings["templates"] = rawTemplates
	if err := t.storage.SafeWriteJSON(settingsFile, settings); err != nil {
		return "", nil, errors.New("не удалось сохранить настройки шаблонов")
	}

	committed = true
	return finalName, nil, nil
}

// Проверяет ZIP entry до извлечения: "../secret.php" и "C:/secret" отклоняются.
func validateTemplateArchiveEntry(entry *zip.File) (string, error) {
	normalized := strings.ReplaceAll(entry.Name, "\\", "/")
	if normalized == "" || strings.ContainsRune(normalized, '\x00') || strings.HasPrefix(normalized, "/") || strings.Contains(normalized, ":") {
		return "", errors.New("архив содержит недопустимые пути к файлам (Zip Slip обнаружен)")
	}
	cleanName := path.Clean(normalized)
	if cleanName == "." || cleanName == ".." || strings.HasPrefix(cleanName, "../") {
		return "", errors.New("архив содержит недопустимые пути к файлам (Zip Slip обнаружен)")
	}
	if entry.Mode()&os.ModeSymlink != 0 {
		return "", errors.New("архив содержит запрещённые символические ссылки")
	}
	if !entry.FileInfo().IsDir() {
		ext := strings.ToLower(strings.TrimPrefix(path.Ext(cleanName), "."))
		if templateBlockedExtensions[ext] {
			return "", fmt.Errorf("архив содержит запрещенные исполняемые файлы (.%s)", ext)
		}
	}
	return cleanName, nil
}

func readTemplateArchiveFile(entry *zip.File) ([]byte, error) {
	reader, err := entry.Open()
	if err != nil {
		return nil, err
	}
	defer reader.Close()

	data, err := io.ReadAll(io.LimitReader(reader, int64(entry.UncompressedSize64)+1))
	if err != nil {
		return nil, err
	}
	if uint64(len(data)) != entry.UncompressedSize64 {
		return nil, errors.New("размер файла в архиве не совпадает с метаданными")
	}
	return data, nil
}

func validateUploadedTemplateCode(code []byte) []string {
	text := string(code)
	missing := make([]string, 0)
	for _, placeholder := range templateRequiredPlaceholders {
		if !strings.Contains(text, placeholder) {
			missing = append(missing, placeholder)
		}
	}
	return missing
}

// Переносит относительные ресурсы рядом с HTML: src="style.css" -> src="templates/theme/style.css".
func rewriteUploadedTemplatePaths(code []byte, dirPart, templatesDir string) []byte {
	if dirPart == "" || dirPart == "." || dirPart == "NPBlog" {
		return code
	}
	rewrite := func(url string) string {
		return rewriteUploadedTemplateURL(url, dirPart, templatesDir)
	}
	html := templateAttributeURLPattern.ReplaceAllStringFunc(string(code), func(attribute string) string {
		matches := templateAttributeURLPattern.FindStringSubmatch(attribute)
		return matches[1] + `="` + rewrite(matches[2]) + `"`
	})
	html = templateCSSURLPattern.ReplaceAllStringFunc(html, func(value string) string {
		matches := templateCSSURLPattern.FindStringSubmatch(value)
		return `url("` + rewrite(matches[1]) + `")`
	})
	return []byte(html)
}

func rewriteUploadedTemplateURL(url, dirPart, templatesDir string) string {
	lowerURL := strings.ToLower(url)
	if url == "" || strings.HasPrefix(lowerURL, "http:") || strings.HasPrefix(lowerURL, "https:") ||
		strings.HasPrefix(url, "//") || strings.HasPrefix(url, "#") || strings.HasPrefix(url, "{{") ||
		strings.HasPrefix(lowerURL, "data:") || strings.HasPrefix(lowerURL, "mailto:") ||
		strings.HasPrefix(lowerURL, "tel:") || strings.HasPrefix(lowerURL, "javascript:") {
		return url
	}
	if strings.HasPrefix(url, "/") {
		if !strings.HasPrefix(lowerURL, "/templates/") {
			return url
		}
		url = url[1:]
		lowerURL = lowerURL[1:]
	}
	url = strings.TrimPrefix(url, "./")
	lowerURL = strings.TrimPrefix(lowerURL, "./")

	if strings.HasPrefix(lowerURL, "templates/") {
		url = url[len("templates/"):]
		if slash := strings.Index(url, "/"); slash >= 0 {
			nextSegment := url[:slash]
			targetDir := filepath.Join(templatesDir, filepath.FromSlash(dirPart), nextSegment)
			if info, err := os.Stat(targetDir); err != nil || !info.IsDir() {
				url = url[slash+1:]
			}
		}
	}
	if strings.HasPrefix(strings.ToLower(url), "blog/") {
		url = url[len("blog/"):]
	}
	if strings.HasPrefix(strings.ToLower(url), strings.ToLower(dirPart)+"/") {
		url = url[len(dirPart)+1:]
	}
	return "templates/" + dirPart + "/" + url
}
