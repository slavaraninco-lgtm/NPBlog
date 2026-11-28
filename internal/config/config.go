package config

import (
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io/fs"
	"os"
	"path/filepath"
	"sync"

	"npblog"
	"npblog/internal/domain"
)

type AppConfig struct {
	Host     string
	Port     string
	BaseDir  string
	AssetsDir string
	DevMode  bool
	mu       sync.RWMutex
	Settings domain.EditorSettings
}

func LoadConfig() (*AppConfig, error) {
	hostFlag := flag.String("host", "0.0.0.0", "HTTP host to bind")
	portFlag := flag.String("port", "80", "HTTP port to bind")
	dirFlag := flag.String("dir", ".", "Root directory of NPBlog installation")
	assetsFlag := flag.String("assets-dir", "", "Directory containing web assets (defaults to dir)")
	devFlag := flag.Bool("dev", false, "Enable development mode")

	flag.Parse()

	absDir, err := filepath.Abs(*dirFlag)
	if err != nil {
		absDir = *dirFlag
	}

	assetsDir := *assetsFlag
	if assetsDir == "" {
		assetsDir = absDir
	} else {
		if absAssets, err := filepath.Abs(assetsDir); err == nil {
			assetsDir = absAssets
		}
	}

	cfg := &AppConfig{
		Host:      *hostFlag,
		Port:      *portFlag,
		BaseDir:   absDir,
		AssetsDir: assetsDir,
		DevMode:   *devFlag,
	}

	_, _, _ = EnsureEditorSettings(absDir, npblog.EmbeddedAssets)
	cfg.ReloadSettings()
	return cfg, nil
}

// EnsureEditorSettings creates editor_settings.json from editor_settings.example.json
// ONLY when editor_settings.json does not yet exist on disk. Existing files (including
// corrupt ones) are never overwritten so user configuration is preserved.
func EnsureEditorSettings(baseDir string, fallbackFS fs.FS) (bool, string, error) {
	if baseDir == "" {
		return false, "", errors.New("не указан рабочий каталог конфигурации")
	}
	settingsPath := filepath.Join(baseDir, "editor_settings.json")
	if _, err := os.Stat(settingsPath); err == nil {
		return false, "existing", nil
	} else if !os.IsNotExist(err) {
		return false, "", fmt.Errorf("не удалось проверить editor_settings.json: %w", err)
	}

	var data []byte
	var source string

	diskExample := filepath.Join(baseDir, "editor_settings.example.json")
	if raw, err := os.ReadFile(diskExample); err == nil && isValidSettingsObject(raw) {
		data = raw
		source = diskExample
	}

	if len(data) == 0 && fallbackFS != nil {
		if raw, err := fs.ReadFile(fallbackFS, "editor_settings.example.json"); err == nil && isValidSettingsObject(raw) {
			data = raw
			source = "embedded:editor_settings.example.json"
		}
	}

	if len(data) == 0 {
		return false, "", errors.New("не найден валидный шаблон конфигурации editor_settings.example.json")
	}

	if err := os.MkdirAll(baseDir, 0755); err != nil {
		return false, source, fmt.Errorf("не удалось подготовить каталог для editor_settings.json: %w", err)
	}

	// Atomically write editor_settings.json so concurrent or interrupted starts never leave partial JSON.
	tmpFile, err := os.CreateTemp(baseDir, ".editor_settings_*.tmp")
	if err != nil {
		return false, source, fmt.Errorf("не удалось создать editor_settings.json из %s: %w", source, err)
	}
	tmpName := tmpFile.Name()
	if _, err := tmpFile.Write(data); err != nil {
		_ = tmpFile.Close()
		_ = os.Remove(tmpName)
		return false, source, fmt.Errorf("ошибка записи editor_settings.json: %w", err)
	}
	if err := tmpFile.Close(); err != nil {
		_ = os.Remove(tmpName)
		return false, source, fmt.Errorf("ошибка закрытия editor_settings.json: %w", err)
	}
	_ = os.Chmod(tmpName, 0644)
	if err := os.Rename(tmpName, settingsPath); err != nil {
		_ = os.Remove(tmpName)
		return false, source, fmt.Errorf("ошибка сохранения editor_settings.json: %w", err)
	}

	return true, source, nil
}

func isValidSettingsObject(raw []byte) bool {
	var m map[string]interface{}
	return json.Unmarshal(raw, &m) == nil && m != nil
}

func (c *AppConfig) ReloadSettings() {
	c.mu.Lock()
	defer c.mu.Unlock()

	settingsPath := filepath.Join(c.BaseDir, "editor_settings.json")
	examplePath := filepath.Join(c.BaseDir, "editor_settings.example.json")

	data, err := os.ReadFile(settingsPath)
	if err != nil {
		data, err = os.ReadFile(examplePath)
	}
	if err != nil {
		data, err = fs.ReadFile(npblog.EmbeddedAssets, "editor_settings.example.json")
	}

	var settings domain.EditorSettings
	if err == nil {
		_ = json.Unmarshal(data, &settings)
	}

	if settings.ContentWidth <= 0 {
		settings.ContentWidth = 920
	}
	if settings.AutosaveInterval <= 0 {
		settings.AutosaveInterval = 60
	}
	if settings.Language == "" {
		settings.Language = "ru"
	}
	if settings.ActiveTheme == "" {
		settings.ActiveTheme = "dark"
	}

	c.Settings = settings
}

func (c *AppConfig) GetSettings() domain.EditorSettings {
	c.mu.RLock()
	defer c.mu.RUnlock()
	return c.Settings
}
