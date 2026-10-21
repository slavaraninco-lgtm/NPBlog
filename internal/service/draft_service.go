package service

import (
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"
	"time"

	"npblog/internal/config"
	"npblog/internal/domain"
)

type DraftService struct {
	cfg     *config.AppConfig
	storage *StorageService
}

func NewDraftService(cfg *config.AppConfig, storage *StorageService) *DraftService {
	return &DraftService{
		cfg:     cfg,
		storage: storage,
	}
}

// SaveDraft сохраняет черновик статьи в data/drafts/draft-{id}.json
func (d *DraftService) SaveDraft(activeBlogPath, id, title, content string) error {
	draftsDir := d.storage.GetDataPath(activeBlogPath, "drafts")
	_ = os.MkdirAll(draftsDir, 0755)

	if id == "" {
		id = fmt.Sprintf("%d", time.Now().UnixNano())
	}

	cleanID := regexp.MustCompile(`[^a-zA-Z0-9_\-]`).ReplaceAllString(id, "")
	filename := filepath.Join(draftsDir, fmt.Sprintf("draft-%s.json", cleanID))

	draft := domain.DraftData{
		ID:      cleanID,
		Title:   title,
		Content: content,
		SavedAt: time.Now(),
	}

	return d.storage.SafeWriteJSON(filename, draft)
}

// GetDrafts возвращает все сохраненные черновики
func (d *DraftService) GetDrafts(activeBlogPath string) ([]domain.DraftData, error) {
	draftsDir := d.storage.GetDataPath(activeBlogPath, "drafts")
	entries, err := os.ReadDir(draftsDir)
	if err != nil {
		return []domain.DraftData{}, nil
	}

	var drafts []domain.DraftData
	for _, e := range entries {
		if e.IsDir() || !strings.HasPrefix(e.Name(), "draft-") || !strings.HasSuffix(e.Name(), ".json") {
			continue
		}
		var draft domain.DraftData
		if err := d.storage.SafeReadJSON(filepath.Join(draftsDir, e.Name()), &draft); err == nil {
			drafts = append(drafts, draft)
		}
	}
	return drafts, nil
}

// DeleteDraft удаляет черновик по ID или имени файла
func (d *DraftService) DeleteDraft(activeBlogPath, id string) error {
	draftsDir := d.storage.GetDataPath(activeBlogPath, "drafts")
	cleanID := strings.TrimPrefix(id, "draft-")
	cleanID = strings.TrimSuffix(cleanID, ".json")
	cleanID = regexp.MustCompile(`[^a-zA-Z0-9_\-]`).ReplaceAllString(cleanID, "")

	filename := filepath.Join(draftsDir, fmt.Sprintf("draft-%s.json", cleanID))
	return os.Remove(filename)
}

// SaveAutosave сохраняет снимок текущей редактируемой статьи
func (d *DraftService) SaveAutosave(data domain.AutosaveData) error {
	autosaveDir := d.storage.GetAutosavePath("")
	_ = os.MkdirAll(autosaveDir, 0755)

	filename := filepath.Join(autosaveDir, fmt.Sprintf("autosave-%d.json", data.PostID))
	data.Timestamp = time.Now().Unix()
	data.Date = time.Now().Format("02.01.2006 15:04:05")

	return d.storage.SafeWriteJSON(filename, data)
}

// GetAutosave возвращает автосохранение для конкретной статьи
func (d *DraftService) GetAutosave(postID int) (*domain.AutosaveData, error) {
	autosaveDir := d.storage.GetAutosavePath("")
	filename := filepath.Join(autosaveDir, fmt.Sprintf("autosave-%d.json", postID))

	var data domain.AutosaveData
	if err := d.storage.SafeReadJSON(filename, &data); err != nil {
		return nil, err
	}
	return &data, nil
}

// GetAllAutosaves возвращает список всех активных автосохранений
func (d *DraftService) GetAllAutosaves() ([]domain.AutosaveData, error) {
	autosaveDir := d.storage.GetAutosavePath("")
	entries, err := os.ReadDir(autosaveDir)
	if err != nil {
		return []domain.AutosaveData{}, nil
	}

	var list []domain.AutosaveData
	for _, e := range entries {
		if e.IsDir() || !strings.HasPrefix(e.Name(), "autosave-") || !strings.HasSuffix(e.Name(), ".json") {
			continue
		}
		var data domain.AutosaveData
		if err := d.storage.SafeReadJSON(filepath.Join(autosaveDir, e.Name()), &data); err == nil {
			list = append(list, data)
		}
	}
	return list, nil
}

// DeleteAutosave удаляет автосохранение статьи
func (d *DraftService) DeleteAutosave(postID int) error {
	autosaveDir := d.storage.GetAutosavePath("")
	filename := filepath.Join(autosaveDir, fmt.Sprintf("autosave-%d.json", postID))
	return os.Remove(filename)
}

// DeleteAllAutosaves полностью очищает каталог автосохранений
func (d *DraftService) DeleteAllAutosaves() error {
	autosaveDir := d.storage.GetAutosavePath("")
	entries, err := os.ReadDir(autosaveDir)
	if err != nil {
		return nil
	}

	for _, e := range entries {
		if !e.IsDir() && strings.HasPrefix(e.Name(), "autosave-") {
			_ = os.Remove(filepath.Join(autosaveDir, e.Name()))
		}
	}
	return nil
}

func parsePostIDFromFilename(name string) int {
	re := regexp.MustCompile(`autosave-(\d+)\.json`)
	m := re.FindStringSubmatch(name)
	if len(m) > 1 {
		id, _ := strconv.Atoi(m[1])
		return id
	}
	return 0
}
