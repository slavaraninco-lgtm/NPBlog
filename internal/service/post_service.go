package service

import (
	"encoding/xml"
	"errors"
	"fmt"
	"html"
	"os"
	"path/filepath"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"time"

	"npblog/internal/config"
	"npblog/internal/domain"
)

type RenumberChange struct {
	OldID int    `json:"oldId"`
	NewID int    `json:"newId"`
	Title string `json:"title"`
}

type NumberingIssue struct {
	Title      string `json:"title"`
	CurrentID  int    `json:"currentId"`
	ExpectedID int    `json:"expectedId"`
}

type PostService struct {
	cfg     *config.AppConfig
	storage *StorageService
}

func NewPostService(cfg *config.AppConfig, storage *StorageService) *PostService {
	return &PostService{
		cfg:     cfg,
		storage: storage,
	}
}

// FormatArticleContent форматирует контент с сохранением тегов <pre> и KaTeX формул
func (p *PostService) FormatArticleContent(content, dataDirName string) string {
	staticPrefix := "/" + dataDirName + "/"

	// Нормализация ссылок
	reApiDir := regexp.MustCompile(`(?i)(?:https?://[^/]+)?(?:/)?api/` + regexp.QuoteMeta(dataDirName) + `/`)
	content = reApiDir.ReplaceAllString(content, staticPrefix)

	reApiUploads := regexp.MustCompile(`(?i)(?:https?://[^/]+)?(?:/)?api/(uploads|files|fonts|smiles)/`)
	content = reApiUploads.ReplaceAllString(content, staticPrefix+"$1/")

	reDirect := regexp.MustCompile(`(?i)https?://[^/]+/` + regexp.QuoteMeta(dataDirName) + `/`)
	content = reDirect.ReplaceAllString(content, staticPrefix)

	reServeData := regexp.MustCompile(`(?i)(?:https?://[^/]+)?(?:/)?serve_data\.php\?file=`)
	content = reServeData.ReplaceAllString(content, staticPrefix)

	reTimestamp := regexp.MustCompile(`(?i)(?:[?&]|&amp;)t=\d+`)
	content = reTimestamp.ReplaceAllString(content, "")

	// 1. Извлекаем блоки <pre>, чтобы полностью сохранить их форматирование и пробелы
	var preBlocks []string
	rePre := regexp.MustCompile(`(?i)(<pre[^>]*>[\s\S]*?</pre>)`)
	content = rePre.ReplaceAllStringFunc(content, func(m string) string {
		idx := len(preBlocks)
		preBlocks = append(preBlocks, m)
		return fmt.Sprintf("___PRE_PLACEHOLDER_%d___", idx)
	})

	// 2. Извлекаем формулы, сохраняя монолитную структуру
	var formulaBlocks []string
	reFormulaStart := regexp.MustCompile(`(?i)<(span|div)[^>]*\b(npblog-formula|npblog-formula-block|data-formula)\b[^>]*>`)
	for {
		loc := reFormulaStart.FindStringSubmatchIndex(content)
		if loc == nil {
			break
		}
		startPos := loc[0]
		matchTag := strings.ToLower(content[loc[2]:loc[3]])
		openTag := "<" + matchTag
		closeTag := "</" + matchTag + ">"

		depth := 1
		currPos := loc[1]
		contentLen := len(content)

		for currPos < contentLen && depth > 0 {
			nextOpen := strings.Index(strings.ToLower(content[currPos:]), openTag)
			nextClose := strings.Index(strings.ToLower(content[currPos:]), closeTag)

			if nextClose == -1 {
				currPos = contentLen
				break
			}

			if nextOpen != -1 && nextOpen < nextClose {
				actualOpen := currPos + nextOpen
				charAfter := ""
				if actualOpen+len(openTag) < contentLen {
					charAfter = content[actualOpen+len(openTag) : actualOpen+len(openTag)+1]
				}
				if charAfter == " " || charAfter == ">" || charAfter == "\t" || charAfter == "\n" || charAfter == "\r" {
					depth++
				}
				currPos = actualOpen + len(openTag)
			} else {
				depth--
				currPos += nextClose + len(closeTag)
			}
		}

		formulaHTML := content[startPos:currPos]
		fIdx := len(formulaBlocks)
		formulaBlocks = append(formulaBlocks, formulaHTML)
		content = content[:startPos] + fmt.Sprintf("___FORMULA_PLACEHOLDER_%d___", fIdx) + content[currPos:]
	}

	// 3. Форматирование блочных тегов отступами
	blockTags := []string{"div", "p", "h1", "h2", "h3", "h4", "h5", "h6", "ul", "ol", "li", "table", "tr", "iframe", "video", "audio", "center", "details", "summary", "blockquote", "hr"}
	tagsRegex := strings.Join(blockTags, "|")

	reOpenBlock := regexp.MustCompile(`(?i)(<(?:` + tagsRegex + `)(?:\s+[^>]*)?>)`)
	content = reOpenBlock.ReplaceAllString(content, "\n$1")

	reCloseBlock := regexp.MustCompile(`(?i)(</(?:` + tagsRegex + `)>)`)
	content = reCloseBlock.ReplaceAllString(content, "$1\n")

	lines := strings.Split(content, "\n")
	var cleanLines []string
	for _, l := range lines {
		trimmed := strings.TrimSpace(l)
		if trimmed != "" {
			cleanLines = append(cleanLines, "        "+trimmed)
		}
	}

	finalHTML := "\n" + strings.Join(cleanLines, "\n") + "\n    "

	// 4. Восстановление заполнителей формул и pre
	for i, f := range formulaBlocks {
		finalHTML = strings.ReplaceAll(finalHTML, fmt.Sprintf("___FORMULA_PLACEHOLDER_%d___", i), f)
	}
	for i, p := range preBlocks {
		finalHTML = strings.ReplaceAll(finalHTML, fmt.Sprintf("___PRE_PLACEHOLDER_%d___", i), p)
	}

	return finalHTML
}

// SavePost создает новую публикацию и генерирует статическую страницу
func (p *PostService) SavePost(activeBlogPath, title, rawContent string) (int, error) {
	blogDir := p.storage.GetDataPath(activeBlogPath, "blog")
	_ = os.MkdirAll(blogDir, 0755)

	dataDirName := filepath.Base(filepath.Dir(blogDir))
	cleanContent := p.FormatArticleContent(rawContent, dataDirName)

	// Определение ID
	maxID := 0
	files, _ := filepath.Glob(filepath.Join(blogDir, "post-*.html"))
	reID := regexp.MustCompile(`post-(\d+)\.html$`)
	for _, f := range files {
		if m := reID.FindStringSubmatch(f); len(m) > 1 {
			if id, err := strconv.Atoi(m[1]); err == nil && id > maxID {
				maxID = id
			}
		}
	}

	metaFile := filepath.Join(blogDir, "posts-meta.json")
	var existingMeta []domain.PostMeta
	_ = p.storage.SafeReadJSON(metaFile, &existingMeta)
	for _, item := range existingMeta {
		if item.ID > maxID {
			maxID = item.ID
		}
	}

	nextID := maxID + 1
	dateStr := time.Now().Format("02.01.2006 15:04")

	// Сборка шаблона
	templateHTML, err := p.getTemplateHTML(activeBlogPath)
	if err != nil {
		return 0, err
	}

	escapedTitle := html.EscapeString(title)
	seoMeta := p.GenerateSEOTags(nextID, title, cleanContent)
	customFontsCSS := p.getCustomFontsCSS(activeBlogPath)

	settings := p.cfg.GetSettings()
	bodyStyle := fmt.Sprintf(`style="max-width: %dpx;"`, settings.ContentWidth)

	articleHTML := templateHTML
	articleHTML = strings.ReplaceAll(articleHTML, "{{POST_ID}}", strconv.Itoa(nextID))
	articleHTML = strings.ReplaceAll(articleHTML, "{{TITLE}}", escapedTitle)
	articleHTML = strings.ReplaceAll(articleHTML, "{{DATE}}", dateStr)
	articleHTML = strings.ReplaceAll(articleHTML, "{{META_TAGS}}", seoMeta)
	articleHTML = strings.ReplaceAll(articleHTML, "{{BODY_STYLE}}", bodyStyle)
	articleHTML = strings.ReplaceAll(articleHTML, "{{CUSTOM_FONTS}}", customFontsCSS)
	articleHTML = strings.ReplaceAll(articleHTML, "{{CONTENT_WRAPPER_START}}", "")
	articleHTML = strings.ReplaceAll(articleHTML, "{{CONTENT_WRAPPER_END}}", "")

	wrappedContent := cleanContent
	if !strings.Contains(articleHTML, `id="npblog-post-content"`) {
		wrappedContent = `<article id="npblog-post-content" class="content">` + cleanContent + `</article>`
	}
	articleHTML = strings.ReplaceAll(articleHTML, "{{CONTENT}}", wrappedContent)

	// Сохранение статьи
	postFile := filepath.Join(blogDir, fmt.Sprintf("post-%d.html", nextID))
	if err := p.storage.SafeWriteFile(postFile, []byte(articleHTML)); err != nil {
		return 0, err
	}

	// Применение фона (если настроен глобальный или предустановленный)
	_ = p.ApplyBackgroundForPost(activeBlogPath, nextID)

	// Создание первого бэкапа
	backupDir := p.storage.GetBackupPath(strconv.Itoa(nextID))
	_ = os.MkdirAll(backupDir, 0755)
	backupFile := filepath.Join(backupDir, fmt.Sprintf("%d-1.html", nextID))
	_ = p.storage.SafeWriteFile(backupFile, []byte(articleHTML))

	// Обновление backup-meta.json
	backupMetaFile := p.storage.GetBackupPath("backup-meta.json")
	var backupMeta domain.BackupMeta
	_ = p.storage.SafeReadJSON(backupMetaFile, &backupMeta)
	if backupMeta == nil {
		backupMeta = make(domain.BackupMeta)
	}
	backupMeta[strconv.Itoa(nextID)] = domain.PostBackupInfo{
		PostID:    nextID,
		PostTitle: title,
		Backups: []domain.BackupEntry{
			{
				BackupNumber: 1,
				Filename:     fmt.Sprintf("%d-1.html", nextID),
				Date:         dateStr,
				Title:        title,
			},
		},
	}
	_ = p.storage.SafeWriteJSON(backupMetaFile, backupMeta)

	// Обновление posts-meta.json
	existingMeta = append(existingMeta, domain.PostMeta{
		ID:       nextID,
		Title:    title,
		Date:     dateStr,
		Filename: fmt.Sprintf("post-%d.html", nextID),
	})
	_ = p.storage.SafeWriteJSON(metaFile, existingMeta)

	// Регенерация RSS
	go func() {
		_ = p.GenerateRSS(activeBlogPath)
	}()

	return nextID, nil
}

// UpdatePost обновляет существующую статью с созданием новой ревизии бэкапа
func (p *PostService) UpdatePost(activeBlogPath string, id int, title, rawContent string) error {
	blogDir := p.storage.GetDataPath(activeBlogPath, "blog")
	postFile := filepath.Join(blogDir, fmt.Sprintf("post-%d.html", id))
	if _, err := os.Stat(postFile); os.IsNotExist(err) {
		return errors.New("статья не найдена")
	}

	dataDirName := filepath.Base(filepath.Dir(blogDir))
	cleanContent := p.FormatArticleContent(rawContent, dataDirName)

	dateStr := time.Now().Format("02.01.2006 15:04") + " (отредактировано)"

	templateHTML, err := p.getTemplateHTML(activeBlogPath)
	if err != nil {
		return err
	}

	escapedTitle := html.EscapeString(title)
	seoMeta := p.GenerateSEOTags(id, title, cleanContent)
	customFontsCSS := p.getCustomFontsCSS(activeBlogPath)

	settings := p.cfg.GetSettings()
	bodyStyle := fmt.Sprintf(`style="max-width: %dpx;"`, settings.ContentWidth)

	articleHTML := templateHTML
	articleHTML = strings.ReplaceAll(articleHTML, "{{POST_ID}}", strconv.Itoa(id))
	articleHTML = strings.ReplaceAll(articleHTML, "{{TITLE}}", escapedTitle)
	articleHTML = strings.ReplaceAll(articleHTML, "{{DATE}}", dateStr)
	articleHTML = strings.ReplaceAll(articleHTML, "{{META_TAGS}}", seoMeta)
	articleHTML = strings.ReplaceAll(articleHTML, "{{BODY_STYLE}}", bodyStyle)
	articleHTML = strings.ReplaceAll(articleHTML, "{{CUSTOM_FONTS}}", customFontsCSS)
	articleHTML = strings.ReplaceAll(articleHTML, "{{CONTENT_WRAPPER_START}}", "")
	articleHTML = strings.ReplaceAll(articleHTML, "{{CONTENT_WRAPPER_END}}", "")

	wrappedContent := cleanContent
	if !strings.Contains(articleHTML, `id="npblog-post-content"`) {
		wrappedContent = `<article id="npblog-post-content" class="content">` + cleanContent + `</article>`
	}
	articleHTML = strings.ReplaceAll(articleHTML, "{{CONTENT}}", wrappedContent)

	if err := p.storage.SafeWriteFile(postFile, []byte(articleHTML)); err != nil {
		return err
	}

	// Повторное применение фонов и подложек после перезаписи шаблона
	_ = p.ApplyBackgroundForPost(activeBlogPath, id)

	// Добавление новой ревизии в бэкап
	backupDir := p.storage.GetBackupPath(strconv.Itoa(id))
	_ = os.MkdirAll(backupDir, 0755)

	backupMetaFile := p.storage.GetBackupPath("backup-meta.json")
	var backupMeta domain.BackupMeta
	_ = p.storage.SafeReadJSON(backupMetaFile, &backupMeta)
	if backupMeta == nil {
		backupMeta = make(domain.BackupMeta)
	}

	idKey := strconv.Itoa(id)
	postBackup := backupMeta[idKey]
	nextBackupNum := len(postBackup.Backups) + 1
	postBackup.PostID = id
	postBackup.PostTitle = title
	postBackup.Backups = append(postBackup.Backups, domain.BackupEntry{
		BackupNumber: nextBackupNum,
		Filename:     fmt.Sprintf("%d-%d.html", id, nextBackupNum),
		Date:         dateStr,
		Title:        title,
	})
	backupMeta[idKey] = postBackup
	_ = p.storage.SafeWriteJSON(backupMetaFile, backupMeta)

	backupFile := filepath.Join(backupDir, fmt.Sprintf("%d-%d.html", id, nextBackupNum))
	_ = p.storage.SafeWriteFile(backupFile, []byte(articleHTML))

	// Обновление posts-meta.json
	metaFile := filepath.Join(blogDir, "posts-meta.json")
	var existingMeta []domain.PostMeta
	_ = p.storage.SafeReadJSON(metaFile, &existingMeta)
	for i, m := range existingMeta {
		if m.ID == id {
			existingMeta[i].Title = title
			existingMeta[i].Date = dateStr
			break
		}
	}
	_ = p.storage.SafeWriteJSON(metaFile, existingMeta)

	go func() {
		_ = p.GenerateRSS(activeBlogPath)
	}()

	return nil
}

// DeletePost удаляет статью с автоматической перенумерацией оставшихся статей
func (p *PostService) DeletePost(activeBlogPath string, id int) (bool, error) {
	blogDir := p.storage.GetDataPath(activeBlogPath, "blog")
	postFile := filepath.Join(blogDir, fmt.Sprintf("post-%d.html", id))
	_ = os.Remove(postFile)

	metaFile := filepath.Join(blogDir, "posts-meta.json")
	var meta []domain.PostMeta
	if err := p.storage.SafeReadJSON(metaFile, &meta); err != nil {
		return false, errors.New("метаданные не найдены")
	}

	postIndex := -1
	var deletedPostTitle string
	for idx, m := range meta {
		if m.ID == id {
			postIndex = idx
			deletedPostTitle = m.Title
			break
		}
	}

	if postIndex == -1 {
		return false, errors.New("статья не найдена")
	}

	// Удаляем настройки фона статьи и файл фона (если был назначен)
	bgFile := p.storage.GetDataPath(activeBlogPath, "post_backgrounds.json")
	var bgCfg domain.PostBackgroundConfig
	if err := p.storage.SafeReadJSON(bgFile, &bgCfg); err == nil && bgCfg.Posts != nil {
		idStr := strconv.Itoa(id)
		if item, exists := bgCfg.Posts[idStr]; exists {
			if item != nil && item.Image != "" {
				bgImgFile := p.storage.GetDataPath(activeBlogPath, filepath.Join("backgrounds", filepath.Base(item.Image)))
				_ = os.Remove(bgImgFile)
			}
			delete(bgCfg.Posts, idStr)
			_ = p.storage.SafeWriteJSON(bgFile, bgCfg)
		}
	}

	// Помечаем бэкапы удаленной статьи как [УДАЛЕНО]
	backupMetaFile := p.storage.GetBackupPath("backup-meta.json")
	var backupMeta domain.BackupMeta
	_ = p.storage.SafeReadJSON(backupMetaFile, &backupMeta)
	if backupMeta != nil {
		idKey := strconv.Itoa(id)
		if bInfo, exists := backupMeta[idKey]; exists {
			deletedKey := fmt.Sprintf("deleted_%d_%d", id, time.Now().Unix())
			oldBackupDir := p.storage.GetBackupPath(idKey)
			newBackupDir := p.storage.GetBackupPath(deletedKey)

			if _, err := os.Stat(oldBackupDir); err == nil {
				_ = os.Rename(oldBackupDir, newBackupDir)
			}

			bInfo.PostID = 0
			bInfo.PostTitle = "[УДАЛЕНО] " + deletedPostTitle
			bInfo.Deleted = true
			bInfo.DeletedAt = time.Now().Format("02.01.2006 15:04")
			backupMeta[deletedKey] = bInfo
			delete(backupMeta, idKey)
			_ = p.storage.SafeWriteJSON(backupMetaFile, backupMeta)
		}
	}

	// Удаляем статью из posts-meta.json
	meta = append(meta[:postIndex], meta[postIndex+1:]...)
	_ = p.storage.SafeWriteJSON(metaFile, meta)

	// Автоматическая перенумерация оставшихся статей после удаления
	changes, _ := p.RenumberPosts(activeBlogPath)

	go func() {
		_ = p.GenerateRSS(activeBlogPath)
	}()

	return len(changes) > 0, nil
}

// CheckNumbering проверяет целостность нумерации статей
func (p *PostService) CheckNumbering(activeBlogPath string) ([]NumberingIssue, error) {
	metaFile := p.storage.GetDataPath(activeBlogPath, "blog/posts-meta.json")
	var meta []domain.PostMeta
	if err := p.storage.SafeReadJSON(metaFile, &meta); err != nil || len(meta) == 0 {
		return nil, nil
	}

	sort.Slice(meta, func(i, j int) bool {
		return meta[i].ID < meta[j].ID
	})

	var issues []NumberingIssue
	for idx, post := range meta {
		expectedID := idx + 1
		if post.ID != expectedID {
			issues = append(issues, NumberingIssue{
				Title:      post.Title,
				CurrentID:  post.ID,
				ExpectedID: expectedID,
			})
		}
	}
	return issues, nil
}

// TogglePinPost закрепляет или открепляет публикацию вверху списка
func (p *PostService) TogglePinPost(activeBlogPath string, id int) (bool, error) {
	metaFile := p.storage.GetDataPath(activeBlogPath, "blog/posts-meta.json")
	var meta []domain.PostMeta
	if err := p.storage.SafeReadJSON(metaFile, &meta); err != nil {
		return false, err
	}

	var isPinned bool
	found := false
	now := time.Now().Format("02.01.2006 15:04")

	for i, m := range meta {
		if m.ID == id {
			meta[i].Pinned = !meta[i].Pinned
			isPinned = meta[i].Pinned
			if isPinned {
				meta[i].PinnedAt = now
			} else {
				meta[i].PinnedAt = ""
			}
			found = true
			break
		}
	}

	if !found {
		return false, errors.New("статья не найдена")
	}

	if err := p.storage.SafeWriteJSON(metaFile, meta); err != nil {
		return false, err
	}
	return isPinned, nil
}

// RenumberPosts упорядочивает ID статей по возрастанию (1..N) с переносом файлов, бэкапов и фонов
func (p *PostService) RenumberPosts(activeBlogPath string) ([]RenumberChange, error) {
	metaFile := p.storage.GetDataPath(activeBlogPath, "blog/posts-meta.json")
	var meta []domain.PostMeta
	if err := p.storage.SafeReadJSON(metaFile, &meta); err != nil || len(meta) == 0 {
		return nil, nil
	}

	sort.Slice(meta, func(i, j int) bool {
		return meta[i].ID < meta[j].ID
	})

	backupMetaFile := p.storage.GetBackupPath("backup-meta.json")
	var backupMeta domain.BackupMeta
	_ = p.storage.SafeReadJSON(backupMetaFile, &backupMeta)
	if backupMeta == nil {
		backupMeta = make(domain.BackupMeta)
	}

	bgMap := p.LoadPostBackgroundsMap(activeBlogPath)
	newBgMap := make(map[string]domain.PostBgSettings)

	var changes []RenumberChange
	var newMeta []domain.PostMeta

	blogDir := p.storage.GetDataPath(activeBlogPath, "blog")
	bgDir := p.storage.GetDataPath(activeBlogPath, "backgrounds")

	reMetaPostID := regexp.MustCompile(`(?i)<meta\s+name=["']post-id["']\s+content=["']\d+["']\s*/?>`)

	for idx, post := range meta {
		oldID := post.ID
		newID := idx + 1

		if oldID != newID {
			changes = append(changes, RenumberChange{
				OldID: oldID,
				NewID: newID,
				Title: post.Title,
			})

			oldPostFile := filepath.Join(blogDir, fmt.Sprintf("post-%d.html", oldID))
			newPostFile := filepath.Join(blogDir, fmt.Sprintf("post-%d.html", newID))

			if content, err := os.ReadFile(oldPostFile); err == nil {
				newTag := fmt.Sprintf(`<meta name="post-id" content="%d">`, newID)
				content = reMetaPostID.ReplaceAll(content, []byte(newTag))
				_ = p.storage.SafeWriteFile(newPostFile, content)
				if oldPostFile != newPostFile {
					_ = os.Remove(oldPostFile)
				}
			}

			// Перенос и переименование файлов бэкапов
			oldBackupDir := p.storage.GetBackupPath(strconv.Itoa(oldID))
			newBackupDir := p.storage.GetBackupPath(strconv.Itoa(newID))

			if _, err := os.Stat(oldBackupDir); err == nil {
				_ = os.MkdirAll(newBackupDir, 0755)
				entries, err := os.ReadDir(oldBackupDir)
				if err == nil {
					for _, entry := range entries {
						if entry.IsDir() {
							continue
						}
						oldName := entry.Name()
						newName := oldName
						reBackupFile := regexp.MustCompile(fmt.Sprintf(`^%d-(\d+\.html)$`, oldID))
						if m := reBackupFile.FindStringSubmatch(oldName); len(m) > 1 {
							newName = fmt.Sprintf("%d-%s", newID, m[1])
						}
						oldFilePath := filepath.Join(oldBackupDir, oldName)
						newFilePath := filepath.Join(newBackupDir, newName)

						if data, err := os.ReadFile(oldFilePath); err == nil {
							_ = p.storage.SafeWriteFile(newFilePath, data)
							_ = os.Remove(oldFilePath)
						}
					}
				}
				_ = os.Remove(oldBackupDir)
			}

			// Обновление метаданных бэкапов (сохраняя остальные записи)
			if bInfo, exists := backupMeta[strconv.Itoa(oldID)]; exists {
				bInfo.PostID = newID
				for bIdx := range bInfo.Backups {
					bInfo.Backups[bIdx].Filename = fmt.Sprintf("%d-%d.html", newID, bInfo.Backups[bIdx].BackupNumber)
				}
				backupMeta[strconv.Itoa(newID)] = bInfo
				delete(backupMeta, strconv.Itoa(oldID))
			}

			// Переименование файла фона, если был привязан
			oldKey := strconv.Itoa(oldID)
			newKey := strconv.Itoa(newID)
			if settings, exists := bgMap[oldKey]; exists {
				if settings.Background != "" {
					ext := filepath.Ext(settings.Background)
					oldBgName := fmt.Sprintf("bg-%d%s", oldID, ext)
					newBgName := fmt.Sprintf("bg-%d%s", newID, ext)
					oldBgPath := filepath.Join(bgDir, oldBgName)
					newBgPath := filepath.Join(bgDir, newBgName)
					if _, err := os.Stat(oldBgPath); err == nil {
						_ = os.Rename(oldBgPath, newBgPath)
						settings.Background = newBgName
					}
				}
				newBgMap[newKey] = settings
			}
		} else {
			// ID не изменился
			idStr := strconv.Itoa(oldID)
			if settings, exists := bgMap[idStr]; exists {
				newBgMap[idStr] = settings
			}
		}

		post.ID = newID
		post.Filename = fmt.Sprintf("post-%d.html", newID)
		newMeta = append(newMeta, post)
	}

	_ = p.storage.SafeWriteJSON(metaFile, newMeta)
	_ = p.storage.SafeWriteJSON(backupMetaFile, backupMeta)
	_ = p.SavePostBackgroundsMap(activeBlogPath, newBgMap)

	// Повторно применяем фоны для всех измененных статей
	for _, c := range changes {
		_ = p.ApplyBackgroundForPost(activeBlogPath, c.NewID)
	}

	go func() {
		_ = p.GenerateRSS(activeBlogPath)
	}()

	return changes, nil
}

// GetPostContent извлекает редактируемый контент и заголовок статьи
func (p *PostService) GetPostContent(activeBlogPath string, id int) (string, string, error) {
	blogDir := p.storage.GetDataPath(activeBlogPath, "blog")
	postFile := filepath.Join(blogDir, fmt.Sprintf("post-%d.html", id))
	contentBytes, err := os.ReadFile(postFile)
	if err != nil {
		return "", "", errors.New("статья не найдена")
	}

	raw := string(contentBytes)
	title := ""

	// Извлекаем заголовок из <h1> или <title>
	reH1 := regexp.MustCompile(`(?i)<h1>(.*?)</h1>`)
	if m := reH1.FindStringSubmatch(raw); len(m) > 1 {
		title = strings.TrimSpace(html.UnescapeString(stripTags(m[1])))
	} else {
		reTitle := regexp.MustCompile(`(?i)<title>(.*?)</title>`)
		if m := reTitle.FindStringSubmatch(raw); len(m) > 1 {
			title = strings.TrimSpace(html.UnescapeString(stripTags(m[1])))
		}
	}

	// Извлекаем контент из <article id="npblog-post-content" class="content">...</article>
	body := ""
	reArticle := regexp.MustCompile(`(?i)<article[^>]*id="npblog-post-content"[^>]*>([\s\S]*?)</article>`)
	if m := reArticle.FindStringSubmatch(raw); len(m) > 1 {
		body = strings.TrimSpace(m[1])
	} else {
		// Fallback для устаревших статей
		reDiv := regexp.MustCompile(`(?i)<div class="content">([\s\S]*?)</div>\s*<a href="[^"]*blog\.html"`)
		if m := reDiv.FindStringSubmatch(raw); len(m) > 1 {
			body = strings.TrimSpace(m[1])
		} else {
			body = raw
		}
	}

	return title, body, nil
}

// GenerateSEOTags создает метатеги OpenGraph, Twitter Cards и JSON-LD
func (p *PostService) GenerateSEOTags(id int, title, cleanContent string) string {
	plainText := stripTags(cleanContent)
	runes := []rune(strings.TrimSpace(plainText))
	desc := ""
	if len(runes) > 160 {
		desc = string(runes[:157]) + "..."
	} else {
		desc = string(runes)
	}
	desc = html.EscapeString(desc)
	escapedTitle := html.EscapeString(title)

	var sb strings.Builder
	sb.WriteString(fmt.Sprintf("\n    <meta name=\"description\" content=\"%s\">", desc))
	sb.WriteString(fmt.Sprintf("\n    <meta property=\"og:title\" content=\"%s\">", escapedTitle))
	sb.WriteString(fmt.Sprintf("\n    <meta property=\"og:description\" content=\"%s\">", desc))
	sb.WriteString("\n    <meta property=\"og:type\" content=\"article\">")
	sb.WriteString(fmt.Sprintf("\n    <meta name=\"twitter:card\" content=\"summary\">"))
	sb.WriteString(fmt.Sprintf("\n    <meta name=\"twitter:title\" content=\"%s\">", escapedTitle))
	sb.WriteString(fmt.Sprintf("\n    <meta name=\"twitter:description\" content=\"%s\">", desc))
	sb.WriteString(fmt.Sprintf("\n    <meta name=\"post-id\" content=\"%d\">", id))
	return sb.String()
}

// GenerateRSS компилирует data/blog/rss.xml
func (p *PostService) GenerateRSS(activeBlogPath string) error {
	settings := p.cfg.GetSettings()
	if !settings.RssEnabled {
		return nil
	}

	metaFile := p.storage.GetDataPath(activeBlogPath, "blog/posts-meta.json")
	var posts []domain.PostMeta
	if err := p.storage.SafeReadJSON(metaFile, &posts); err != nil {
		return err
	}

	type RssItem struct {
		Title       string `xml:"title"`
		Link        string `xml:"link"`
		Description string `xml:"description"`
		PubDate     string `xml:"pubDate"`
		GUID        string `xml:"guid"`
	}

	type RssChannel struct {
		Title       string    `xml:"title"`
		Link        string    `xml:"link"`
		Description string    `xml:"description"`
		Language    string    `xml:"language"`
		Items       []RssItem `xml:"item"`
	}

	type RssFeed struct {
		XMLName xml.Name   `xml:"rss"`
		Version string     `xml:"version,attr"`
		Channel RssChannel `xml:"channel"`
	}

	feed := RssFeed{
		Version: "2.0",
		Channel: RssChannel{
			Title:       settings.RssTitle,
			Link:        settings.RssBaseURL,
			Description: settings.RssDescription,
			Language:    settings.Language,
		},
	}

	for _, post := range posts {
		itemLink := fmt.Sprintf("%s/blog/%s", strings.TrimRight(settings.RssBaseURL, "/"), post.Filename)
		feed.Channel.Items = append(feed.Channel.Items, RssItem{
			Title:       post.Title,
			Link:        itemLink,
			Description: post.Title,
			PubDate:     post.Date,
			GUID:        itemLink,
		})
	}

	bytes, err := xml.MarshalIndent(feed, "", "    ")
	if err != nil {
		return err
	}

	xmlHeader := []byte("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n")
	rssFile := p.storage.GetDataPath(activeBlogPath, "blog/rss.xml")
	return p.storage.SafeWriteFile(rssFile, append(xmlHeader, bytes...))
}

func (p *PostService) getTemplateHTML(activeBlogPath string) (string, error) {
	templatePath := p.storage.GetDataPath(activeBlogPath, "blog/template_post.html")
	if bytes, err := os.ReadFile(templatePath); err == nil {
		return string(bytes), nil
	}
	// Fallback на базовый шаблон NPBlog
	fallback := filepath.Join(p.cfg.BaseDir, "data", "blog", "template_post.html")
	if bytes, err := os.ReadFile(fallback); err == nil {
		return string(bytes), nil
	}
	return "", errors.New("шаблон статьи template_post.html не найден")
}

func (p *PostService) getCustomFontsCSS(activeBlogPath string) string {
	fontsDir := p.storage.GetDataPath(activeBlogPath, "fonts")
	files, err := os.ReadDir(fontsDir)
	if err != nil || len(files) == 0 {
		return ""
	}

	var sb strings.Builder
	for _, f := range files {
		if f.IsDir() {
			continue
		}
		ext := strings.ToLower(filepath.Ext(f.Name()))
		if ext == ".ttf" || ext == ".woff" || ext == ".woff2" || ext == ".otf" {
			fontName := strings.TrimSuffix(f.Name(), filepath.Ext(f.Name()))
			format := "truetype"
			if ext == ".woff" {
				format = "woff"
			} else if ext == ".woff2" {
				format = "woff2"
			} else if ext == ".otf" {
				format = "opentype"
			}
			sb.WriteString(fmt.Sprintf("@font-face { font-family: '%s'; src: url('../fonts/%s') format('%s'); font-display: swap; }\n", fontName, f.Name(), format))
		}
	}
	return sb.String()
}

func stripTags(str string) string {
	re := regexp.MustCompile(`<[^>]*>`)
	return re.ReplaceAllString(str, "")
}
