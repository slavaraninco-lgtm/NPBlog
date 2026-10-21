package config

import (
	"encoding/json"
	"flag"
	"os"
	"path/filepath"
	"sync"

	"npblog/internal/domain"
)

type AppConfig struct {
	Host       string
	Port       string
	BaseDir    string
	AssetsDir  string
	DevMode    bool
	mu         sync.RWMutex
	Settings   domain.EditorSettings
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

	cfg.ReloadSettings()
	return cfg, nil
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
