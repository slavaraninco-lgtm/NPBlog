package service

import (
	"os"
	"path/filepath"
	"strings"
	"time"

	"npblog/internal/config"
	"npblog/internal/domain"
)

type IntegrityService struct {
	cfg     *config.AppConfig
	storage *StorageService
}

func NewIntegrityService(cfg *config.AppConfig, storage *StorageService) *IntegrityService {
	return &IntegrityService{
		cfg:     cfg,
		storage: storage,
	}
}

// CheckIntegrity выполняет проверку целостности шаблонов и файлов блога
func (i *IntegrityService) CheckIntegrity(activeBlogPath string) ([]string, bool) {
	var errs []string

	templateFile := i.storage.GetDataPath(activeBlogPath, "blog/template_post.html")
	if bytes, err := os.ReadFile(templateFile); err != nil {
		errs = append(errs, "Файл шаблона не найден: template_post.html")
	} else if !strings.Contains(string(bytes), "Powered by NPBlog") {
		errs = append(errs, "В файле шаблона template_post.html отсутствует надпись 'Powered by NPBlog'")
	}

	blogHtmlFile := i.storage.GetDataPath(activeBlogPath, "blog.html")
	if bytes, err := os.ReadFile(blogHtmlFile); err != nil {
		errs = append(errs, "Файл blog.html не найден")
	} else if !strings.Contains(string(bytes), "Powered by NPBlog") {
		errs = append(errs, "В файле blog.html отсутствует надпись 'Powered by NPBlog'")
	}

	return errs, len(errs) == 0
}

// FixIntegrity восстанавливает обязательные элементы шаблонов
func (i *IntegrityService) FixIntegrity(activeBlogPath string) ([]string, []string) {
	var fixed []string
	var errs []string

	badge := "\n    <div class=\"powered-by\">Powered by NPBlog</div>"

	// 1. Исправление template_post.html
	templateFile := i.storage.GetDataPath(activeBlogPath, "blog/template_post.html")
	if bytes, err := os.ReadFile(templateFile); err == nil {
		content := string(bytes)
		if !strings.Contains(content, "Powered by NPBlog") {
			if strings.Contains(content, "</body>") {
				content = strings.Replace(content, "</body>", badge+"\n</body>", 1)
			} else {
				content += badge
			}
			if err := i.storage.SafeWriteFile(templateFile, []byte(content)); err == nil {
				fixed = append(fixed, "template_post.html")
			} else {
				errs = append(errs, "Не удалось записать изменения в template_post.html")
			}
		}
	}

	// 2. Исправление blog.html
	blogHtmlFile := i.storage.GetDataPath(activeBlogPath, "blog.html")
	if bytes, err := os.ReadFile(blogHtmlFile); err == nil {
		content := string(bytes)
		if !strings.Contains(content, "Powered by NPBlog") {
			if strings.Contains(content, "</body>") {
				content = strings.Replace(content, "</body>", badge+"\n</body>", 1)
			} else {
				content += badge
			}
			if err := i.storage.SafeWriteFile(blogHtmlFile, []byte(content)); err == nil {
				fixed = append(fixed, "blog.html")
			} else {
				errs = append(errs, "Не удалось записать изменения в blog.html")
			}
		}
	}

	return fixed, errs
}

// GetHistory возвращает журнал действий редактора
func (i *IntegrityService) GetHistory() ([]domain.HistoryAction, error) {
	historyFile := filepath.Join(i.cfg.BaseDir, "history.json")
	var history []domain.HistoryAction
	if err := i.storage.SafeReadJSON(historyFile, &history); err != nil {
		return []domain.HistoryAction{}, nil
	}
	return history, nil
}

// SaveHistoryAction добавляет действие в историю
func (i *IntegrityService) SaveHistoryAction(action, details string) error {
	historyFile := filepath.Join(i.cfg.BaseDir, "history.json")
	var history []domain.HistoryAction
	_ = i.storage.SafeReadJSON(historyFile, &history)

	now := time.Now()
	newAction := domain.HistoryAction{
		Action:    action,
		Timestamp: now.Unix(),
		Date:      now.Format("02.01.2006 15:04:05"),
		Details:   details,
	}

	history = append(history, newAction)
	// Ограничиваем историю 100 записями
	if len(history) > 100 {
		history = history[len(history)-100:]
	}

	return i.storage.SafeWriteJSON(historyFile, history)
}

// ClearHistory очищает журнал действий
func (i *IntegrityService) ClearHistory() error {
	historyFile := filepath.Join(i.cfg.BaseDir, "history.json")
	return i.storage.SafeWriteJSON(historyFile, []domain.HistoryAction{})
}
