package service

import (
	"errors"
	"os"
	"path/filepath"
	"regexp"
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

// GetIncludes возвращает список сниппетов из includes/includes-meta.json
func (t *TemplateService) GetIncludes(activeBlogPath string) ([]domain.IncludeMetaItem, error) {
	metaFile := filepath.Join(t.cfg.BaseDir, "includes", "includes-meta.json")
	var items []domain.IncludeMetaItem
	if err := t.storage.SafeReadJSON(metaFile, &items); err != nil {
		return []domain.IncludeMetaItem{}, nil
	}
	return items, nil
}

// SaveInclude сохраняет сниппет в includes/{name}.html и обновляет includes-meta.json
func (t *TemplateService) SaveInclude(activeBlogPath, name, title, description, content string) error {
	cleanName := regexp.MustCompile(`[^a-zA-Z0-9_\-]`).ReplaceAllString(name, "")
	if cleanName == "" {
		return errors.New("недопустимое имя сниппета")
	}

	includesDir := filepath.Join(t.cfg.BaseDir, "includes")
	_ = os.MkdirAll(includesDir, 0755)

	targetFile := filepath.Join(includesDir, cleanName+".html")
	if err := t.storage.SafeWriteFile(targetFile, []byte(content)); err != nil {
		return err
	}

	metaFile := filepath.Join(includesDir, "includes-meta.json")
	var items []domain.IncludeMetaItem
	_ = t.storage.SafeReadJSON(metaFile, &items)

	found := false
	nowDate := time.Now().Format("02.01.2006 15:04")
	for i, it := range items {
		if it.Name == cleanName {
			items[i].Title = title
			items[i].Description = description
			items[i].Date = nowDate
			items[i].Size = int64(len(content))
			found = true
			break
		}
	}

	if !found {
		items = append(items, domain.IncludeMetaItem{
			Name:        cleanName,
			Title:       title,
			Description: description,
			Date:        nowDate,
			Size:        int64(len(content)),
		})
	}

	return t.storage.SafeWriteJSON(metaFile, items)
}

// GetIncludeContent возвращает содержимое файла сниппета
func (t *TemplateService) GetIncludeContent(activeBlogPath, name string) (string, error) {
	cleanName := regexp.MustCompile(`[^a-zA-Z0-9_\-]`).ReplaceAllString(name, "")
	targetFile := filepath.Join(t.cfg.BaseDir, "includes", cleanName+".html")
	bytes, err := os.ReadFile(targetFile)
	if err != nil {
		return "", errors.New("сниппет не найден")
	}
	return string(bytes), nil
}

// DeleteInclude удаляет сниппет и запись из метаданных
func (t *TemplateService) DeleteInclude(activeBlogPath, name string) error {
	cleanName := regexp.MustCompile(`[^a-zA-Z0-9_\-]`).ReplaceAllString(name, "")
	targetFile := filepath.Join(t.cfg.BaseDir, "includes", cleanName+".html")
	_ = os.Remove(targetFile)

	metaFile := filepath.Join(t.cfg.BaseDir, "includes", "includes-meta.json")
	var items []domain.IncludeMetaItem
	_ = t.storage.SafeReadJSON(metaFile, &items)

	var newItems []domain.IncludeMetaItem
	for _, it := range items {
		if it.Name != cleanName {
			newItems = append(newItems, it)
		}
	}
	return t.storage.SafeWriteJSON(metaFile, newItems)
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
