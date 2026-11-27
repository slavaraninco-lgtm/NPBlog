package service

import (
	"encoding/json"
	"errors"
	"os"
	"path/filepath"
	"strconv"
	"strings"
)

type templateCatalogData struct {
	Raw           map[string]json.RawMessage
	Default       string
	PostTemplates map[string]string
	Templates     map[string]templateUploadMeta
}

func (t *TemplateService) readTemplateCatalog(activeBlogPath string) (*templateCatalogData, string, error) {
	dir := t.storage.GetDataPath(activeBlogPath, "blog/templates")
	settingsFile := filepath.Join(dir, "settings.json")
	catalog := &templateCatalogData{Raw: make(map[string]json.RawMessage)}
	if err := t.storage.SafeReadJSON(settingsFile, &catalog.Raw); err != nil {
		if os.IsNotExist(err) {
			if initErr := t.ensureTemplateCatalogLocked(activeBlogPath, dir); initErr == nil {
				err = t.storage.SafeReadJSON(settingsFile, &catalog.Raw)
			}
		}
		if err != nil {
			return nil, "", errors.New("настройки шаблонов не найдены или повреждены")
		}
	}
	if raw, ok := catalog.Raw["default"]; ok {
		_ = json.Unmarshal(raw, &catalog.Default)
	}
	if catalog.Default == "" {
		catalog.Default = "main"
	}
	if raw, ok := catalog.Raw["post_templates"]; ok {
		if err := json.Unmarshal(raw, &catalog.PostTemplates); err != nil {
			return nil, "", errors.New("назначения шаблонов повреждены")
		}
	}
	if catalog.PostTemplates == nil {
		catalog.PostTemplates = make(map[string]string)
	}
	if raw, ok := catalog.Raw["templates"]; ok {
		if err := json.Unmarshal(raw, &catalog.Templates); err != nil {
			return nil, "", errors.New("каталог шаблонов повреждён")
		}
	}
	if catalog.Templates == nil {
		catalog.Templates = make(map[string]templateUploadMeta)
	}
	return catalog, dir, nil
}

func (t *TemplateService) writeTemplateCatalog(settingsFile string, catalog *templateCatalogData) error {
	var err error
	catalog.Raw["default"], err = json.Marshal(catalog.Default)
	if err != nil {
		return err
	}
	catalog.Raw["post_templates"], err = json.Marshal(catalog.PostTemplates)
	if err != nil {
		return err
	}
	catalog.Raw["templates"], err = json.Marshal(catalog.Templates)
	if err != nil {
		return err
	}
	return t.storage.SafeWriteJSON(settingsFile, catalog.Raw)
}

func templateCatalogPath(name string, meta templateUploadMeta) string {
	if meta.Path != "" {
		return meta.Path
	}
	if name == "main" {
		return "NPBlog/main.html"
	}
	return name + ".html"
}

func (t *TemplateService) templateCode(catalog *templateCatalogData, templatesDir, name string) (string, string, []byte, error) {
	meta, ok := catalog.Templates[name]
	if !ok {
		return "", "", nil, errors.New("шаблон не найден в каталоге")
	}
	rel := templateCatalogPath(name, meta)
	templateFile, err := t.storage.ValidateSafePath(templatesDir, rel)
	if err != nil {
		return "", "", nil, errors.New("некорректный путь шаблона")
	}
	resolved, err := filepath.EvalSymlinks(templateFile)
	if err != nil {
		return "", "", nil, errors.New("файл шаблона не найден")
	}
	root, err := filepath.EvalSymlinks(templatesDir)
	if err != nil {
		return "", "", nil, errors.New("каталог шаблонов недоступен")
	}
	relative, err := filepath.Rel(root, resolved)
	if err != nil || relative == ".." || strings.HasPrefix(relative, ".."+string(filepath.Separator)) {
		return "", "", nil, errors.New("некорректный путь шаблона")
	}
	code, err := os.ReadFile(resolved)
	if err != nil {
		return "", "", nil, errors.New("не удалось прочитать шаблон")
	}
	return resolved, rel, code, nil
}

// SaveTemplate сохраняет код и метаданные существующего шаблона без изменения остального каталога.
func (t *TemplateService) SaveTemplate(activeBlogPath, name, title, description string, code []byte) ([]string, error) {
	t.uploadMu.Lock()
	defer t.uploadMu.Unlock()

	if name == "" || strings.TrimSpace(title) == "" {
		return nil, errors.New("неверное имя или пустое название шаблона")
	}
	if missing := validateUploadedTemplateCode(code); len(missing) > 0 {
		return missing, errors.New("в шаблоне отсутствуют необходимые плейсхолдеры")
	}
	catalog, templatesDir, err := t.readTemplateCatalog(activeBlogPath)
	if err != nil {
		return nil, err
	}
	templateFile, rel, originalCode, err := t.templateCode(catalog, templatesDir, name)
	if err != nil {
		return nil, err
	}
	dirPart := filepath.ToSlash(filepath.Dir(rel))
	code = rewriteUploadedTemplatePaths(code, dirPart, templatesDir)
	if err := t.storage.SafeWriteFile(templateFile, code); err != nil {
		return nil, errors.New("не удалось сохранить файл шаблона")
	}

	meta := catalog.Templates[name]
	meta.Title = title
	meta.Description = description
	meta.Path = rel
	catalog.Templates[name] = meta
	if err := t.writeTemplateCatalog(filepath.Join(templatesDir, "settings.json"), catalog); err != nil {
		_ = t.storage.SafeWriteFile(templateFile, originalCode)
		return nil, errors.New("не удалось сохранить настройки шаблонов")
	}
	return nil, nil
}

// SetDefaultTemplate назначает шаблон по умолчанию и возвращает его код для пересборки статей.
func (t *TemplateService) SetDefaultTemplate(activeBlogPath, name string) ([]byte, error) {
	t.uploadMu.Lock()
	defer t.uploadMu.Unlock()

	catalog, templatesDir, err := t.readTemplateCatalog(activeBlogPath)
	if err != nil {
		return nil, err
	}
	_, _, code, err := t.templateCode(catalog, templatesDir, name)
	if err != nil {
		return nil, err
	}
	catalog.Default = name
	catalog.PostTemplates = make(map[string]string)
	catalog.Raw["activeTemplate"], _ = json.Marshal(name)
	if err := t.writeTemplateCatalog(filepath.Join(templatesDir, "settings.json"), catalog); err != nil {
		return nil, errors.New("не удалось сохранить настройки шаблонов")
	}
	return code, nil
}

// AssignTemplateToPost связывает статью с шаблоном и возвращает его код.
func (t *TemplateService) AssignTemplateToPost(activeBlogPath, name string, postID int) ([]byte, error) {
	t.uploadMu.Lock()
	defer t.uploadMu.Unlock()

	if postID <= 0 {
		return nil, errors.New("не указан ID статьи")
	}
	catalog, templatesDir, err := t.readTemplateCatalog(activeBlogPath)
	if err != nil {
		return nil, err
	}
	_, _, code, err := t.templateCode(catalog, templatesDir, name)
	if err != nil {
		return nil, err
	}
	catalog.PostTemplates[strconv.Itoa(postID)] = name
	if err := t.writeTemplateCatalog(filepath.Join(templatesDir, "settings.json"), catalog); err != nil {
		return nil, errors.New("не удалось сохранить настройки шаблонов")
	}
	return code, nil
}

// DeleteTemplate удаляет пользовательский шаблон и его назначения статьям.
func (t *TemplateService) DeleteTemplate(activeBlogPath, name string) error {
	t.uploadMu.Lock()
	defer t.uploadMu.Unlock()

	catalog, templatesDir, err := t.readTemplateCatalog(activeBlogPath)
	if err != nil {
		return err
	}
	meta, ok := catalog.Templates[name]
	if !ok {
		return errors.New("шаблон не найден")
	}
	if name == "main" || meta.IsSystem {
		return errors.New("нельзя удалить главный системный шаблон")
	}
	if catalog.Default == name {
		return errors.New("нельзя удалить шаблон, который установлен по умолчанию")
	}
	rel := templateCatalogPath(name, meta)
	delete(catalog.Templates, name)
	for postID, templateName := range catalog.PostTemplates {
		if templateName == name {
			delete(catalog.PostTemplates, postID)
		}
	}
	if err := t.writeTemplateCatalog(filepath.Join(templatesDir, "settings.json"), catalog); err != nil {
		return errors.New("не удалось сохранить настройки шаблонов")
	}
	return t.removeTemplateFiles(templatesDir, rel)
}

// DeleteCustomTemplates удаляет только не системные шаблоны и восстанавливает main по умолчанию.
func (t *TemplateService) DeleteCustomTemplates(activeBlogPath string) (int, error) {
	t.uploadMu.Lock()
	defer t.uploadMu.Unlock()

	catalog, templatesDir, err := t.readTemplateCatalog(activeBlogPath)
	if err != nil {
		return 0, err
	}
	paths := make([]string, 0)
	for name, meta := range catalog.Templates {
		if name == "main" || meta.IsSystem {
			continue
		}
		paths = append(paths, templateCatalogPath(name, meta))
		delete(catalog.Templates, name)
	}
	for postID, name := range catalog.PostTemplates {
		if _, exists := catalog.Templates[name]; !exists {
			delete(catalog.PostTemplates, postID)
		}
	}
	catalog.Default = "main"
	catalog.Raw["activeTemplate"], _ = json.Marshal("main")
	if err := t.writeTemplateCatalog(filepath.Join(templatesDir, "settings.json"), catalog); err != nil {
		return 0, errors.New("не удалось сохранить настройки шаблонов")
	}
	for _, rel := range paths {
		if err := t.removeTemplateFiles(templatesDir, rel); err != nil {
			return len(paths), err
		}
	}
	return len(paths), nil
}

func (t *TemplateService) removeTemplateFiles(templatesDir, rel string) error {
	templateFile, err := t.storage.ValidateSafePath(templatesDir, rel)
	if err != nil {
		return errors.New("некорректный путь шаблона")
	}
	resolved, err := filepath.EvalSymlinks(templateFile)
	if os.IsNotExist(err) {
		return nil
	}
	if err != nil {
		return errors.New("не удалось проверить путь шаблона")
	}
	root, err := filepath.EvalSymlinks(templatesDir)
	if err != nil {
		return errors.New("каталог шаблонов недоступен")
	}
	relative, err := filepath.Rel(root, resolved)
	if err != nil || relative == ".." || strings.HasPrefix(relative, ".."+string(filepath.Separator)) {
		return errors.New("некорректный путь шаблона")
	}
	target := filepath.Dir(resolved)
	if target == root {
		target = resolved
	}
	if err := os.RemoveAll(target); err != nil {
		return errors.New("запись удалена из каталога, но не все файлы шаблона удалены")
	}
	return nil
}
