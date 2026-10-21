package service

import (
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"npblog/internal/config"
)

type StorageService struct {
	cfg     *config.AppConfig
	fileMu  sync.Map // ключ: нормализованный путь, значение: *sync.RWMutex
	writeMu sync.Mutex
}

func NewStorageService(cfg *config.AppConfig) *StorageService {
	return &StorageService{
		cfg: cfg,
	}
}

func (s *StorageService) getFileMutex(path string) *sync.RWMutex {
	norm := filepath.Clean(path)
	val, _ := s.fileMu.LoadOrStore(norm, &sync.RWMutex{})
	return val.(*sync.RWMutex)
}

// ValidateSafePath строго проверяет, чтобы целевой путь не выходил за пределы baseDir
func (s *StorageService) ValidateSafePath(baseDir, relPath string) (string, error) {
	cleanBase, err := filepath.Abs(baseDir)
	if err != nil {
		cleanBase = filepath.Clean(baseDir)
	}

	// Нормализация разделителей
	unifiedRel := filepath.FromSlash(strings.ReplaceAll(relPath, "\\", "/"))
	target := filepath.Join(cleanBase, unifiedRel)
	target = filepath.Clean(target)

	// Проверяем префикс пути
	rel, err := filepath.Rel(cleanBase, target)
	if err != nil || strings.HasPrefix(rel, "..") || rel == ".." {
		return "", errors.New("security_violation: попытка выхода за пределы разрешенного каталога (Path Traversal)")
	}

	return target, nil
}

// GetDataPath возвращает абсолютный путь к активному каталогу данных блога
func (s *StorageService) GetDataPath(activeBlogPath, subpath string) string {
	settings := s.cfg.GetSettings()
	targetPath := activeBlogPath

	if targetPath == "" || targetPath == "/CHANGE/ME" || targetPath == "CHANGE/ME" {
		if settings.ActiveBlogPath != "" && settings.ActiveBlogPath != "/CHANGE/ME" {
			targetPath = settings.ActiveBlogPath
		} else if len(settings.BlogPaths) > 0 && settings.BlogPaths[0] != "/CHANGE/ME" && settings.BlogPaths[0] != "" {
			targetPath = settings.BlogPaths[0]
		} else {
			targetPath = filepath.Join(s.cfg.BaseDir, "data")
		}
	}

	if !filepath.IsAbs(targetPath) {
		targetPath = filepath.Join(s.cfg.BaseDir, targetPath)
	}

	_ = os.MkdirAll(targetPath, 0755)

	if subpath != "" {
		safe, err := s.ValidateSafePath(targetPath, subpath)
		if err == nil {
			return safe
		}
		return filepath.Join(targetPath, subpath)
	}
	return targetPath
}

// GetBackupPath возвращает абсолютный путь к каталогу резервных копий
func (s *StorageService) GetBackupPath(subpath string) string {
	settings := s.cfg.GetSettings()
	backupPath := settings.BackupPath
	if backupPath == "" {
		backupPath = filepath.Join(s.cfg.BaseDir, "data_backup")
	} else if !filepath.IsAbs(backupPath) {
		backupPath = filepath.Join(s.cfg.BaseDir, backupPath)
	}

	_ = os.MkdirAll(backupPath, 0755)

	if subpath != "" {
		safe, err := s.ValidateSafePath(backupPath, subpath)
		if err == nil {
			return safe
		}
		return filepath.Join(backupPath, subpath)
	}
	return backupPath
}

// GetAutosavePath возвращает абсолютный путь к каталогу автосохранений
func (s *StorageService) GetAutosavePath(subpath string) string {
	settings := s.cfg.GetSettings()
	autosavePath := settings.AutosavePath
	if autosavePath == "" {
		autosavePath = filepath.Join(s.cfg.BaseDir, "autosave")
	} else if !filepath.IsAbs(autosavePath) {
		autosavePath = filepath.Join(s.cfg.BaseDir, autosavePath)
	}

	_ = os.MkdirAll(autosavePath, 0755)

	if subpath != "" {
		safe, err := s.ValidateSafePath(autosavePath, subpath)
		if err == nil {
			return safe
		}
		return filepath.Join(autosavePath, subpath)
	}
	return autosavePath
}

// SafeWriteJSON атомарно и безопасно сохраняет JSON-структуру в файл
func (s *StorageService) SafeWriteJSON(filePath string, data interface{}) error {
	mu := s.getFileMutex(filePath)
	mu.Lock()
	defer mu.Unlock()

	dir := filepath.Dir(filePath)
	if err := os.MkdirAll(dir, 0755); err != nil {
		return err
	}

	bytes, err := json.MarshalIndent(data, "", "    ")
	if err != nil {
		return err
	}

	tmpFile := fmt.Sprintf("%s.tmp.%d", filePath, time.Now().UnixNano())
	if err := os.WriteFile(tmpFile, bytes, 0666); err != nil {
		return err
	}

	if err := os.Rename(tmpFile, filePath); err != nil {
		// Fallback для некоторых редких файловых блокировок Windows
		_ = os.Remove(filePath)
		if err2 := os.Rename(tmpFile, filePath); err2 != nil {
			_ = os.WriteFile(filePath, bytes, 0666)
			_ = os.Remove(tmpFile)
		}
	}
	return nil
}

// SafeReadJSON читает и десериализует JSON с блокировкой чтения
func (s *StorageService) SafeReadJSON(filePath string, out interface{}) error {
	mu := s.getFileMutex(filePath)
	mu.RLock()
	defer mu.RUnlock()

	data, err := os.ReadFile(filePath)
	if err != nil {
		return err
	}
	return json.Unmarshal(data, out)
}

// SafeWriteFile атомарно записывает бинарный или текстовый файл
func (s *StorageService) SafeWriteFile(filePath string, data []byte) error {
	mu := s.getFileMutex(filePath)
	mu.Lock()
	defer mu.Unlock()

	dir := filepath.Dir(filePath)
	if err := os.MkdirAll(dir, 0755); err != nil {
		return err
	}

	tmpFile := fmt.Sprintf("%s.tmp.%d", filePath, time.Now().UnixNano())
	if err := os.WriteFile(tmpFile, data, 0666); err != nil {
		return err
	}

	if err := os.Rename(tmpFile, filePath); err != nil {
		_ = os.Remove(filePath)
		if err2 := os.Rename(tmpFile, filePath); err2 != nil {
			_ = os.WriteFile(filePath, data, 0666)
			_ = os.Remove(tmpFile)
		}
	}
	return nil
}

// IsDirectoryWritable проверяет доступность каталога для записи
func (s *StorageService) IsDirectoryWritable(dir string) bool {
	if err := os.MkdirAll(dir, 0755); err != nil {
		return false
	}
	testFile := filepath.Join(dir, fmt.Sprintf(".test_write_%d", time.Now().UnixNano()))
	if err := os.WriteFile(testFile, []byte("1"), 0666); err != nil {
		return false
	}
	_ = os.Remove(testFile)
	return true
}
