package service

import (
	"fmt"
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

// SaveDraft сохраняет черновик статьи в data/drafts/{timestamp}.json
func (d *DraftService) SaveDraft(activeBlogPath, title, content string) (string, int64, error) {
	draftsDir := d.storage.GetDataPath(activeBlogPath, "drafts")
	_ = os.MkdirAll(draftsDir, 0755)

	ts := time.Now().Unix()
	filename := fmt.Sprintf("%d.json", ts)
	filePath := filepath.Join(draftsDir, filename)

	draft := domain.DraftData{
		Filename:  filename,
		Title:     title,
		Content:   content,
		Timestamp: ts,
		Date:      time.Now().Format("2006-01-02 15:04:05"),
	}

	if err := d.storage.SafeWriteJSON(filePath, draft); err != nil {
		return "", 0, err
	}
	return filename, ts, nil
}

// GetDrafts возвращает все сохраненные черновики, отсортированные от новых к старым
func (d *DraftService) GetDrafts(activeBlogPath string) ([]domain.DraftData, error) {
	drafts := make([]domain.DraftData, 0)
	seen := make(map[string]bool)

	dirs := []string{
		d.storage.GetDataPath(activeBlogPath, "drafts"),
		filepath.Join(d.cfg.BaseDir, "draft"),
	}

	for _, dir := range dirs {
		entries, err := os.ReadDir(dir)
		if err != nil {
			continue
		}

		for _, e := range entries {
			if e.IsDir() || !strings.HasSuffix(e.Name(), ".json") {
				continue
			}
			if seen[e.Name()] {
				continue
			}
			seen[e.Name()] = true

			var draft domain.DraftData
			if err := d.storage.SafeReadJSON(filepath.Join(dir, e.Name()), &draft); err == nil {
				draft.Filename = e.Name()
				if draft.Timestamp == 0 {
					if info, err := e.Info(); err == nil {
						draft.Timestamp = info.ModTime().Unix()
					}
				}
				drafts = append(drafts, draft)
			}
		}
	}

	sort.Slice(drafts, func(i, j int) bool {
		return drafts[i].Timestamp > drafts[j].Timestamp
	})

	return drafts, nil
}

// DeleteDraft удаляет черновик по имени файла
func (d *DraftService) DeleteDraft(activeBlogPath, filename string) error {
	safeFilename := filepath.Base(filename)
	_ = os.Remove(filepath.Join(d.storage.GetDataPath(activeBlogPath, "drafts"), safeFilename))
	_ = os.Remove(filepath.Join(d.cfg.BaseDir, "draft", safeFilename))
	return nil
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
