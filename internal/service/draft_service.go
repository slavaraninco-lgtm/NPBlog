package service

import (
	"crypto/md5"
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

// SaveAutosave сохраняет снимок текущей редактируемой статьи в autosave_{id}.json
func (d *DraftService) SaveAutosave(data domain.AutosaveData) (*domain.AutosaveData, error) {
	autosaveDir := d.storage.GetAutosavePath("")
	_ = os.MkdirAll(autosaveDir, 0755)

	ts := time.Now().Unix()
	id := data.ID
	if id == "" {
		hash := md5.Sum([]byte(data.Title))
		id = fmt.Sprintf("%x_%d", hash, ts)
	}
	data.ID = id
	data.Timestamp = ts
	data.Date = time.Now().Format("2006-01-02 15:04:05")

	filename := filepath.Join(autosaveDir, fmt.Sprintf("autosave_%s.json", id))
	if err := d.storage.SafeWriteJSON(filename, data); err != nil {
		return nil, err
	}
	return &data, nil
}

// GetAutosave возвращает автосохранение по ID (или последнее, если ID пуст)
func (d *DraftService) GetAutosave(id string) (*domain.AutosaveData, error) {
	autosaveDir := d.storage.GetAutosavePath("")

	cleanID := filepath.Base(id)
	cleanID = strings.TrimSuffix(cleanID, ".json")
	cleanID = strings.TrimPrefix(cleanID, "autosave_")
	cleanID = strings.TrimPrefix(cleanID, "autosave-")

	if cleanID != "" {
		// Try autosave_<cleanID>.json
		path1 := filepath.Join(autosaveDir, fmt.Sprintf("autosave_%s.json", cleanID))
		var data domain.AutosaveData
		if err := d.storage.SafeReadJSON(path1, &data); err == nil {
			if data.ID == "" {
				data.ID = cleanID
			}
			return &data, nil
		}
		// Try autosave-<cleanID>.json
		path2 := filepath.Join(autosaveDir, fmt.Sprintf("autosave-%s.json", cleanID))
		if err := d.storage.SafeReadJSON(path2, &data); err == nil {
			if data.ID == "" {
				data.ID = cleanID
			}
			return &data, nil
		}
	}

	// If cleanID is empty or not found, find the latest autosave
	entries, err := os.ReadDir(autosaveDir)
	if err != nil {
		return nil, fmt.Errorf("autosaves directory not found")
	}

	var newestPath string
	var newestTime int64
	for _, e := range entries {
		if e.IsDir() || !strings.HasSuffix(e.Name(), ".json") {
			continue
		}
		if !strings.HasPrefix(e.Name(), "autosave_") && !strings.HasPrefix(e.Name(), "autosave-") {
			continue
		}
		fi, err := e.Info()
		if err != nil {
			continue
		}
		if fi.ModTime().Unix() > newestTime {
			newestTime = fi.ModTime().Unix()
			newestPath = filepath.Join(autosaveDir, e.Name())
		}
	}

	if newestPath == "" {
		return nil, fmt.Errorf("no autosave found")
	}

	var data domain.AutosaveData
	if err := d.storage.SafeReadJSON(newestPath, &data); err != nil {
		return nil, err
	}
	if data.ID == "" {
		name := filepath.Base(newestPath)
		name = strings.TrimSuffix(name, ".json")
		name = strings.TrimPrefix(name, "autosave_")
		name = strings.TrimPrefix(name, "autosave-")
		data.ID = name
	}
	return &data, nil
}

// GetAllAutosaves возвращает список всех активных автосохранений, отсортированных по времени (новые первые)
func (d *DraftService) GetAllAutosaves() ([]domain.AutosaveData, error) {
	autosaveDir := d.storage.GetAutosavePath("")
	entries, err := os.ReadDir(autosaveDir)
	if err != nil {
		return []domain.AutosaveData{}, nil
	}

	var list []domain.AutosaveData
	for _, e := range entries {
		if e.IsDir() || !strings.HasSuffix(e.Name(), ".json") {
			continue
		}
		if !strings.HasPrefix(e.Name(), "autosave_") && !strings.HasPrefix(e.Name(), "autosave-") {
			continue
		}
		var data domain.AutosaveData
		if err := d.storage.SafeReadJSON(filepath.Join(autosaveDir, e.Name()), &data); err == nil {
			if data.ID == "" {
				name := strings.TrimSuffix(e.Name(), ".json")
				name = strings.TrimPrefix(name, "autosave_")
				name = strings.TrimPrefix(name, "autosave-")
				data.ID = name
			}
			if data.Timestamp == 0 {
				if fi, err := e.Info(); err == nil {
					data.Timestamp = fi.ModTime().Unix()
					data.Date = fi.ModTime().Format("2006-01-02 15:04:05")
				}
			}
			list = append(list, data)
		}
	}

	sort.Slice(list, func(i, j int) bool {
		return list[i].Timestamp > list[j].Timestamp
	})

	return list, nil
}

// DeleteAutosave удаляет автосохранение статьи по ID
func (d *DraftService) DeleteAutosave(id string) error {
	autosaveDir := d.storage.GetAutosavePath("")
	cleanID := filepath.Base(id)
	cleanID = strings.TrimSuffix(cleanID, ".json")
	cleanID = strings.TrimPrefix(cleanID, "autosave_")
	cleanID = strings.TrimPrefix(cleanID, "autosave-")
	if cleanID == "" {
		return nil
	}

	_ = os.Remove(filepath.Join(autosaveDir, fmt.Sprintf("autosave_%s.json", cleanID)))
	_ = os.Remove(filepath.Join(autosaveDir, fmt.Sprintf("autosave-%s.json", cleanID)))
	return nil
}

// DeleteAllAutosaves полностью очищает каталог автосохранений
func (d *DraftService) DeleteAllAutosaves() (int, error) {
	autosaveDir := d.storage.GetAutosavePath("")
	entries, err := os.ReadDir(autosaveDir)
	if err != nil {
		return 0, nil
	}

	deleted := 0
	for _, e := range entries {
		if !e.IsDir() && strings.HasSuffix(e.Name(), ".json") && (strings.HasPrefix(e.Name(), "autosave_") || strings.HasPrefix(e.Name(), "autosave-")) {
			if err := os.Remove(filepath.Join(autosaveDir, e.Name())); err == nil {
				deleted++
			}
		}
	}
	return deleted, nil
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
