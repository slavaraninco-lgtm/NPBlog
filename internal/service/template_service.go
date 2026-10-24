package service

import (
	"errors"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"time"

	"npblog/internal/config"
	"npblog/internal/domain"
)

type TemplateService struct {
	cfg     *config.AppConfig
	storage *StorageService
}

func NewTemplateService(cfg *config.AppConfig, storage *StorageService) *TemplateService {
	return &TemplateService{
		cfg:     cfg,
		storage: storage,
	}
}

// Transliterate кириллицы в латиницу для безопасных имен файлов
func transliterateIncludeName(text string) string {
	translitMap := map[rune]string{
		'а': "a", 'б': "b", 'в': "v", 'г': "g", 'д': "d",
		'е': "e", 'ё': "yo", 'ж': "zh", 'з': "z", 'и': "i",
		'й': "y", 'к': "k", 'л': "l", 'м': "m", 'н': "n",
		'о': "o", 'п': "p", 'р': "r", 'с': "s", 'т': "t",
		'у': "u", 'ф': "f", 'х': "h", 'ц': "ts", 'ч': "ch",
		'ш': "sh", 'щ': "sch", 'ъ': "", 'ы': "y", 'ь': "",
		'э': "e", 'ю': "yu", 'я': "ya",
		'А': "A", 'Б': "B", 'В': "V", 'Г': "G", 'Д': "D",
		'Е': "E", 'Ё': "Yo", 'Ж': "Zh", 'З': "Z", 'И': "I",
		'Й': "Y", 'К': "K", 'Л': "L", 'М': "M", 'Н': "N",
		'О': "O", 'П': "P", 'Р': "R", 'С': "S", 'Т': "T",
		'У': "U", 'Ф': "F", 'Х': "H", 'Ц': "Ts", 'Ч': "Ch",
		'Ш': "Sh", 'Щ': "Sch", 'Ъ': "", 'Ы': "Y", 'Ь': "",
		'Э': "E", 'Ю': "Yu", 'Я': "Ya",
	}

	var sb strings.Builder
	for _, r := range text {
		if val, ok := translitMap[r]; ok {
			sb.WriteString(val)
		} else if r == ' ' {
			sb.WriteRune('_')
		} else if (r >= 'a' && r <= 'z') || (r >= 'A' && r <= 'Z') || (r >= '0' && r <= '9') || r == '_' || r == '-' {
			sb.WriteRune(r)
		}
	}
	res := strings.Trim(sb.String(), "_-")
	if res == "" {
		res = "include_" + time.Now().Format("20060102150405")
	}
	return res
}

// GetIncludes возвращает список сниппетов из includes/ и includes-meta.json
func (t *TemplateService) GetIncludes(activeBlogPath string) ([]domain.IncludeMetaItem, error) {
	includesDir := filepath.Join(t.cfg.BaseDir, "includes")
	items := make([]domain.IncludeMetaItem, 0)

	if _, err := os.Stat(includesDir); os.IsNotExist(err) {
		return items, nil
	}

	metaFile := filepath.Join(includesDir, "includes-meta.json")
	meta := make(map[string]string)
	_ = t.storage.SafeReadJSON(metaFile, &meta)

	entries, err := os.ReadDir(includesDir)
	if err != nil {
		return items, nil
	}

	for _, e := range entries {
		if e.IsDir() || e.Name() == "includes-meta.json" {
			continue
		}
		ext := strings.ToLower(filepath.Ext(e.Name()))
		if ext != ".txt" && ext != ".html" {
			continue
		}

		displayName := meta[e.Name()]
		if displayName == "" {
			displayName = strings.TrimSuffix(e.Name(), ext)
		}

		var size int64
		var modDate string
		if info, err := e.Info(); err == nil {
			size = info.Size()
			modDate = info.ModTime().Format("02.01.2006 15:04")
		}

		items = append(items, domain.IncludeMetaItem{
			Name:        e.Name(),
			DisplayName: displayName,
			Title:       displayName,
			Date:        modDate,
			Size:        size,
		})
	}

	sort.Slice(items, func(i, j int) bool {
		return strings.ToLower(items[i].DisplayName) < strings.ToLower(items[j].DisplayName)
	})

	return items, nil
}

// SaveInclude сохраняет сниппет в includes/{name}.txt и обновляет includes-meta.json
func (t *TemplateService) SaveInclude(activeBlogPath, name, content string) (string, string, error) {
	if strings.TrimSpace(name) == "" {
		return "", "", errors.New("не указано имя сниппета")
	}

	includesDir := filepath.Join(t.cfg.BaseDir, "includes")
	_ = os.MkdirAll(includesDir, 0755)

	safeBase := transliterateIncludeName(name)
	filename := safeBase + ".txt"
	targetFile := filepath.Join(includesDir, filename)

	if err := t.storage.SafeWriteFile(targetFile, []byte(content)); err != nil {
		return "", "", err
	}

	metaFile := filepath.Join(includesDir, "includes-meta.json")
	meta := make(map[string]string)
	_ = t.storage.SafeReadJSON(metaFile, &meta)

	meta[filename] = name
	_ = t.storage.SafeWriteJSON(metaFile, meta)

	return filename, name, nil
}

// GetIncludeContent возвращает содержимое файла сниппета
func (t *TemplateService) GetIncludeContent(activeBlogPath, filename string) (string, error) {
	safeFilename := filepath.Base(filename)
	if safeFilename == "" || safeFilename == "." || safeFilename == "includes-meta.json" {
		return "", errors.New("некорректное имя файла")
	}

	includesDir := filepath.Join(t.cfg.BaseDir, "includes")
	targetFile := filepath.Join(includesDir, safeFilename)

	bytes, err := os.ReadFile(targetFile)
	if err != nil {
		// Попробуем с расширением .txt
		if !strings.HasSuffix(safeFilename, ".txt") {
			if bytes2, err2 := os.ReadFile(targetFile + ".txt"); err2 == nil {
				return string(bytes2), nil
			}
		}
		return "", errors.New("файл не найден")
	}
	return string(bytes), nil
}

// DeleteInclude удаляет сниппет и запись из метаданных
func (t *TemplateService) DeleteInclude(activeBlogPath, filename string) error {
	safeFilename := filepath.Base(filename)
	if safeFilename == "" || safeFilename == "." || safeFilename == "includes-meta.json" {
		return errors.New("недопустимый файл")
	}

	includesDir := filepath.Join(t.cfg.BaseDir, "includes")
	targetFile := filepath.Join(includesDir, safeFilename)
	_ = os.Remove(targetFile)

	metaFile := filepath.Join(includesDir, "includes-meta.json")
	meta := make(map[string]string)
	if err := t.storage.SafeReadJSON(metaFile, &meta); err == nil {
		delete(meta, safeFilename)
		_ = t.storage.SafeWriteJSON(metaFile, meta)
	}

	return nil
}

// GetTemplateManagerData reads the existing PHP-era catalog without migrating or writing user data.
func (t *TemplateService) GetTemplateManagerData(activeBlogPath string) (map[string]interface{}, error) {
	dir := t.storage.GetDataPath(activeBlogPath, "blog/templates")
	var settings struct {
		Default       string            `json:"default"`
		PostTemplates map[string]string `json:"post_templates"`
		Templates     map[string]struct {
			Title       string `json:"title"`
			Description string `json:"description"`
			IsSystem    bool   `json:"is_system"`
			Path        string `json:"path"`
		} `json:"templates"`
	}
	if err := t.storage.SafeReadJSON(filepath.Join(dir, "settings.json"), &settings); err != nil {
		return nil, errors.New("настройки шаблонов не найдены или повреждены")
	}
	names := make([]string, 0, len(settings.Templates))
	for name := range settings.Templates {
		names = append(names, name)
	}
	sort.Strings(names)
	items := make([]map[string]interface{}, 0, len(names))
	for _, name := range names {
		meta := settings.Templates[name]
		rel := meta.Path
		if rel == "" {
			rel = name + ".html"
			if name == "main" {
				rel = "NPBlog/main.html"
			}
		}
		path, err := t.storage.ValidateSafePath(dir, rel)
		if err != nil {
			return nil, errors.New("некорректный путь шаблона")
		}
		// Resolve symlinks before reading so a catalog entry cannot escape the template directory.
		resolved, err := filepath.EvalSymlinks(path)
		if err != nil {
			return nil, errors.New("файл шаблона не найден")
		}
		root, err := filepath.EvalSymlinks(dir)
		if err != nil {
			return nil, errors.New("каталог шаблонов недоступен")
		}
		relative, err := filepath.Rel(root, resolved)
		if err != nil || relative == ".." || strings.HasPrefix(relative, ".."+string(filepath.Separator)) {
			return nil, errors.New("некорректный путь шаблона")
		}
		code, err := os.ReadFile(resolved)
		if err != nil {
			return nil, errors.New("не удалось прочитать шаблон")
		}
		items = append(items, map[string]interface{}{
			"name": name, "title": meta.Title, "description": meta.Description,
			"is_system": meta.IsSystem, "code": string(code),
		})
	}
	posts := make([]domain.PostMeta, 0)
	if err := t.storage.SafeReadJSON(t.storage.GetDataPath(activeBlogPath, "blog/posts-meta.json"), &posts); err != nil && !os.IsNotExist(err) {
		return nil, errors.New("не удалось прочитать список статей")
	}
	if posts == nil {
		posts = make([]domain.PostMeta, 0)
	}
	if settings.Default == "" {
		settings.Default = "main"
	}
	if settings.PostTemplates == nil {
		settings.PostTemplates = make(map[string]string)
	}
	return map[string]interface{}{
		"success": true, "templates": items, "posts": posts,
		"default": settings.Default, "post_templates": settings.PostTemplates,
	}, nil
}

// GetTemplates возвращает список доступных шаблонов и активный шаблон
func (t *TemplateService) GetTemplates(activeBlogPath string) ([]string, string, error) {
	templatesDir := t.storage.GetDataPath(activeBlogPath, "blog/templates")
	entries, err := os.ReadDir(templatesDir)
	if err != nil {
		return []string{"NPBlog"}, "NPBlog", nil
	}

	var list []string
	for _, e := range entries {
		if e.IsDir() {
			list = append(list, e.Name())
		}
	}
	if len(list) == 0 {
		list = append(list, "NPBlog")
	}

	settingsFile := filepath.Join(templatesDir, "settings.json")
	type TemplateSettings struct {
		ActiveTemplate string `json:"activeTemplate"`
	}
	var s TemplateSettings
	_ = t.storage.SafeReadJSON(settingsFile, &s)
	if s.ActiveTemplate == "" {
		s.ActiveTemplate = "NPBlog"
	}

	return list, s.ActiveTemplate, nil
}

// ApplyTemplate переключает активный шаблон блога
func (t *TemplateService) ApplyTemplate(activeBlogPath, templateName string) error {
	templatesDir := t.storage.GetDataPath(activeBlogPath, "blog/templates")
	targetTemplateDir := filepath.Join(templatesDir, templateName)

	if _, err := os.Stat(targetTemplateDir); os.IsNotExist(err) {
		return errors.New("папка шаблона не найдена")
	}

	// Копируем template_post.html из шаблона в blog/
	srcPost := filepath.Join(targetTemplateDir, "template_post.html")
	dstPost := t.storage.GetDataPath(activeBlogPath, "blog/template_post.html")
	if bytes, err := os.ReadFile(srcPost); err == nil {
		_ = t.storage.SafeWriteFile(dstPost, bytes)
	}

	// Копируем blog.html
	srcBlog := filepath.Join(targetTemplateDir, "blog.html")
	dstBlog := t.storage.GetDataPath(activeBlogPath, "blog.html")
	if bytes, err := os.ReadFile(srcBlog); err == nil {
		_ = t.storage.SafeWriteFile(dstBlog, bytes)
	}

	// Обновляем settings.json
	settingsFile := filepath.Join(templatesDir, "settings.json")
	return t.storage.SafeWriteJSON(settingsFile, map[string]string{
		"activeTemplate": templateName,
	})
}
