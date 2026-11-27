package service

import (
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"runtime"
	"strings"
	"sync"
	"time"

	"npblog/internal/config"
	"npblog/internal/domain"
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

	// Защита от нулевых байт, Windows drive/ADS двоеточий и абсолютных относительных путей
	if strings.ContainsRune(relPath, 0) || strings.ContainsRune(relPath, ':') {
		return "", errors.New("security_violation: недопустимые символы в пути")
	}

	// Нормализация разделителей
	unifiedRel := filepath.FromSlash(strings.ReplaceAll(relPath, "\\", "/"))
	if filepath.IsAbs(unifiedRel) || strings.HasPrefix(unifiedRel, string(filepath.Separator)) {
		return "", errors.New("security_violation: абсолютный путь запрещён")
	}
	target := filepath.Join(cleanBase, unifiedRel)
	target = filepath.Clean(target)

	// Проверяем префикс пути
	rel, err := filepath.Rel(cleanBase, target)
	if err != nil || strings.HasPrefix(rel, "..") || rel == ".." {
		return "", errors.New("security_violation: попытка выхода за пределы разрешенного каталога (Path Traversal)")
	}

	return target, nil
}

func isPlaceholderBlogPath(p string) bool {
	trimmed := strings.TrimSpace(p)
	return trimmed == "" || trimmed == "." || trimmed == "/CHANGE/ME" || trimmed == "CHANGE/ME"
}

// ResolveBlogAbsPath normalizes relative or absolute blog paths against BaseDir for reliable comparison.
func (s *StorageService) ResolveBlogAbsPath(p string) string {
	if isPlaceholderBlogPath(p) {
		return ""
	}
	clean := filepath.Clean(strings.TrimSpace(p))
	if clean == "." || strings.HasPrefix(clean, "..") {
		return ""
	}
	if !filepath.IsAbs(clean) {
		clean = filepath.Join(s.cfg.BaseDir, clean)
	}
	return filepath.Clean(clean)
}

// SameBlogPath compares two blog paths after resolving relative paths and platform case rules.
func (s *StorageService) SameBlogPath(a, b string) bool {
	absA := s.ResolveBlogAbsPath(a)
	absB := s.ResolveBlogAbsPath(b)
	if absA == "" || absB == "" {
		return false
	}
	if runtime.GOOS == "windows" {
		return strings.EqualFold(absA, absB)
	}
	return absA == absB
}

// EffectiveActiveBlog returns the active blog path, guaranteeing it belongs to BlogPaths when BlogPaths is non-empty.
func (s *StorageService) EffectiveActiveBlog(settings domain.EditorSettings) string {
	validPaths := make([]string, 0, len(settings.BlogPaths))
	for _, bp := range settings.BlogPaths {
		if !isPlaceholderBlogPath(bp) {
			validPaths = append(validPaths, strings.TrimSpace(bp))
		}
	}
	if len(validPaths) > 0 {
		if !isPlaceholderBlogPath(settings.ActiveBlogPath) {
			for _, bp := range validPaths {
				if s.SameBlogPath(bp, settings.ActiveBlogPath) {
					return settings.ActiveBlogPath
				}
			}
		}
		// Previously active blog was removed from BlogPaths; fall back to the first configured blog.
		return validPaths[0]
	}
	if !isPlaceholderBlogPath(settings.ActiveBlogPath) {
		return settings.ActiveBlogPath
	}
	return "data"
}

// ReconcileBlogSettings keeps active_blog_path valid inside blog_paths whenever settings are saved.
func (s *StorageService) ReconcileBlogSettings(existingMap, updateMap map[string]interface{}) (bool, string) {
	var validPaths []string
	if rawList, ok := existingMap["blog_paths"].([]interface{}); ok {
		for _, item := range rawList {
			if str, ok := item.(string); ok && !isPlaceholderBlogPath(str) {
				validPaths = append(validPaths, strings.TrimSpace(str))
			}
		}
	} else if strList, ok := existingMap["blog_paths"].([]string); ok {
		for _, str := range strList {
			if !isPlaceholderBlogPath(str) {
				validPaths = append(validPaths, strings.TrimSpace(str))
			}
		}
	}

	curActive, _ := existingMap["active_blog_path"].(string)
	_, activeExplicitlyUpdated := updateMap["active_blog_path"].(string)
	activeChanged := activeExplicitlyUpdated

	if len(validPaths) > 0 {
		matched := ""
		if !isPlaceholderBlogPath(curActive) {
			for _, bp := range validPaths {
				if s.SameBlogPath(bp, curActive) {
					matched = curActive
					break
				}
			}
		}
		if matched == "" {
			existingMap["active_blog_path"] = validPaths[0]
			curActive = validPaths[0]
			activeChanged = true
		}
	} else if isPlaceholderBlogPath(curActive) {
		existingMap["active_blog_path"] = "data"
		curActive = "data"
	}

	return activeChanged, curActive
}

// GetDataPath возвращает абсолютный путь к активному каталогу данных блога
func (s *StorageService) GetDataPath(activeBlogPath, subpath string) string {
	settings := s.cfg.GetSettings()
	targetPath := activeBlogPath

	if isPlaceholderBlogPath(targetPath) {
		targetPath = s.EffectiveActiveBlog(settings)
	}

	cleanRel := filepath.Clean(targetPath)
	if cleanRel == "." || cleanRel == "" || strings.HasPrefix(cleanRel, "..") {
		targetPath = filepath.Join(s.cfg.BaseDir, "data")
	} else if !filepath.IsAbs(cleanRel) {
		targetPath = filepath.Join(s.cfg.BaseDir, cleanRel)
	} else {
		// Если передан абсолютный путь, проверяем, что он находится внутри BaseDir или входит в settings.BlogPaths
		isAllowed := false
		if rel, err := filepath.Rel(s.cfg.BaseDir, cleanRel); err == nil && !strings.HasPrefix(rel, "..") {
			isAllowed = true
		}
		for _, bp := range settings.BlogPaths {
			if s.SameBlogPath(bp, cleanRel) {
				isAllowed = true
				break
			}
		}
		if !isAllowed && s.SameBlogPath(settings.ActiveBlogPath, cleanRel) {
			isAllowed = true
		}
		if !isAllowed {
			targetPath = filepath.Join(s.cfg.BaseDir, "data")
		} else {
			targetPath = cleanRel
		}
	}

	_ = os.MkdirAll(targetPath, 0755)

	if subpath != "" {
		safe, err := s.ValidateSafePath(targetPath, subpath)
		if err == nil {
			return safe
		}
		// Защита: при попытке Path Traversal возвращаем безопасный целевой каталог
		return targetPath
	}
	return targetPath
}

// GetDataURL возвращает URL-путь для клиентских запросов (например, "/data/backgrounds/bg-1.png")
func (s *StorageService) GetDataURL(activeBlogPath, subpath string) string {
	target := activeBlogPath
	settings := s.cfg.GetSettings()
	if isPlaceholderBlogPath(target) {
		target = s.EffectiveActiveBlog(settings)
	}
	if filepath.IsAbs(target) {
		if rel, err := filepath.Rel(s.cfg.BaseDir, target); err == nil && rel != "." && rel != ".." && !strings.HasPrefix(rel, "..") {
			target = rel
		} else {
			// Для внешних путей за пределами BaseDir используем имя папки, как в HandleBlogFile
			target = filepath.Base(filepath.Clean(target))
		}
	}
	target = filepath.ToSlash(target)
	target = strings.Trim(target, "/")
	if target == "" || target == "." {
		target = "data"
	}
	sub := filepath.ToSlash(subpath)
	sub = strings.Trim(sub, "/")
	if sub != "" {
		return "/" + target + "/" + sub
	}
	return "/" + target
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
		// Защита: при попытке Path Traversal не возвращаем небезопасный путь
		return backupPath
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
		// Защита: при попытке Path Traversal не возвращаем небезопасный путь
		return autosavePath
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
