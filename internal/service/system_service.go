package service

import (
	"archive/zip"
	"context"
	"crypto/md5"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
	"sort"
	"strings"
	"sync"
	"time"

	"npblog/internal/config"
)

type SystemService struct {
	cfg     *config.AppConfig
	storage *StorageService
	mu      sync.RWMutex

	// Прогресс загрузки/подготовки обновлений: token -> progress map
	progressMap sync.Map
}

func NewSystemService(cfg *config.AppConfig, storage *StorageService) *SystemService {
	return &SystemService{
		cfg:     cfg,
		storage: storage,
	}
}

// VersionData структура version.json
type VersionData struct {
	Version     string   `json:"version"`
	Dev         bool     `json:"dev"`
	LastUpdated string   `json:"last_updated"`
	Commit      string   `json:"commit,omitempty"`
	Changelog   []string `json:"changelog,omitempty"`
}

// UpdateCheckResult результат проверки обновлений
type UpdateCheckResult struct {
	Success        bool     `json:"success"`
	HasUpdate      bool     `json:"hasUpdate"`
	CurrentVersion string   `json:"currentVersion"`
	LatestVersion  string   `json:"latestVersion,omitempty"`
	Channel        string   `json:"channel"`
	Repo           string   `json:"repo"`
	Tag            string   `json:"tag,omitempty"`
	Commit         string   `json:"commit,omitempty"`
	DownloadURL    string   `json:"downloadUrl,omitempty"`
	Changelog      []string `json:"changelog,omitempty"`
	Error          string   `json:"error,omitempty"`
}

// GetVersion читает version.json
func (s *SystemService) GetVersion() VersionData {
	vFile := filepath.Join(s.cfg.BaseDir, "version.json")
	var v VersionData
	if err := s.storage.SafeReadJSON(vFile, &v); err != nil {
		v = VersionData{
			Version:     "2.360",
			Dev:         true,
			LastUpdated: time.Now().Format(time.RFC3339),
		}
	}
	return v
}

// GetChangelog возвращает список изменений из version.json
func (s *SystemService) GetChangelog() []string {
	v := s.GetVersion()
	return v.Changelog
}

// CheckAutoUpdate проверяет наличие обновлений на GitHub (Releases или Canary)
func (s *SystemService) CheckAutoUpdate(channel, repo string, force bool, githubToken string) UpdateCheckResult {
	current := s.GetVersion()
	settings := s.cfg.GetSettings()

	if repo == "" {
		repo = settings.UpdateRepo
	}
	if repo == "" {
		repo = "slavaraninco-lgtm/NPBlog"
	}
	if channel == "" {
		channel = settings.UpdateChannel
	}
	if channel != "canary" {
		channel = "stable"
	}
	if githubToken == "" {
		githubToken = settings.UpdateGithubToken
	}

	res := UpdateCheckResult{
		Success:        true,
		CurrentVersion: current.Version,
		Channel:        channel,
		Repo:           repo,
	}

	cacheDir := filepath.Join(s.cfg.BaseDir, "sys_update_tmp")
	_ = os.MkdirAll(cacheDir, 0755)

	cacheKey := fmt.Sprintf("%x", md5.Sum([]byte(channel+"_"+repo)))
	cacheFile := filepath.Join(cacheDir, "cache_"+cacheKey+".json")

	// Проверка кэша (15 минут)
	if !force {
		if fi, err := os.Stat(cacheFile); err == nil && time.Since(fi.ModTime()) < 15*time.Minute {
			var cached UpdateCheckResult
			if err := s.storage.SafeReadJSON(cacheFile, &cached); err == nil {
				cached.CurrentVersion = current.Version
				if channel == "canary" {
					cached.HasUpdate = cached.Commit != "" && cached.Commit != current.Commit
				} else {
					cached.HasUpdate = cached.LatestVersion != "" && cached.LatestVersion != current.Version
				}
				return cached
			}
		}
	}

	client := &http.Client{Timeout: 15 * time.Second}
	var reqURL string

	if channel == "canary" {
		reqURL = fmt.Sprintf("https://api.github.com/repos/%s/commits/dev", repo)
	} else {
		reqURL = fmt.Sprintf("https://api.github.com/repos/%s/releases/latest", repo)
	}

	req, err := http.NewRequestWithContext(context.Background(), "GET", reqURL, nil)
	if err != nil {
		res.Error = err.Error()
		return res
	}
	req.Header.Set("User-Agent", "NPBlog-Go-Updater")
	req.Header.Set("Accept", "application/vnd.github.v3+json")
	if githubToken != "" {
		req.Header.Set("Authorization", "token "+githubToken)
	}

	resp, err := client.Do(req)
	if err != nil {
		res.Error = "Ошибка подключения к GitHub: " + err.Error()
		return res
	}
	defer resp.Body.Close()

	if resp.StatusCode == 404 && channel != "canary" {
		// Fallback: если нет релизов, проверяем ветку main
		res.LatestVersion = current.Version
		res.HasUpdate = false
		return res
	}

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		res.Error = fmt.Sprintf("GitHub API вернул статус: %d", resp.StatusCode)
		return res
	}

	bodyBytes, err := io.ReadAll(resp.Body)
	if err != nil {
		res.Error = "Ошибка чтения ответа GitHub: " + err.Error()
		return res
	}

	if channel == "canary" {
		var commitResp struct {
			SHA    string `json:"sha"`
			Commit struct {
				Message string `json:"message"`
			} `json:"commit"`
		}
		if err := json.Unmarshal(bodyBytes, &commitResp); err == nil {
			res.Commit = commitResp.SHA
			shortSha := commitResp.SHA
			if len(shortSha) > 7 {
				shortSha = shortSha[:7]
			}
			res.LatestVersion = "dev (" + shortSha + ")"
			res.HasUpdate = commitResp.SHA != "" && commitResp.SHA != current.Commit
			res.DownloadURL = fmt.Sprintf("https://github.com/%s/archive/dev.zip", repo)
			if commitResp.Commit.Message != "" {
				res.Changelog = strings.Split(commitResp.Commit.Message, "\n")
			}
		}
	} else {
		var releaseResp struct {
			TagName string `json:"tag_name"`
			Body    string `json:"body"`
			Assets  []struct {
				Name               string `json:"name"`
				BrowserDownloadURL string `json:"browser_download_url"`
			} `json:"assets"`
			ZipballURL string `json:"zipball_url"`
		}
		if err := json.Unmarshal(bodyBytes, &releaseResp); err == nil {
			res.Tag = releaseResp.TagName
			res.LatestVersion = strings.TrimPrefix(releaseResp.TagName, "v")
			res.HasUpdate = res.LatestVersion != "" && res.LatestVersion != current.Version
			res.DownloadURL = releaseResp.ZipballURL
			for _, asset := range releaseResp.Assets {
				if strings.HasSuffix(strings.ToLower(asset.Name), ".zip") {
					res.DownloadURL = asset.BrowserDownloadURL
					break
				}
			}
			if releaseResp.Body != "" {
				res.Changelog = strings.Split(releaseResp.Body, "\n")
			}
		}
	}

	// Кэшируем результат
	_ = s.storage.SafeWriteJSON(cacheFile, res)
	return res
}

// SetUpdateProgress сохраняет прогресс
func (s *SystemService) SetUpdateProgress(token string, percent int, message string) {
	s.progressMap.Store(token, map[string]interface{}{
		"percent": percent,
		"message": message,
		"time":    time.Now().Unix(),
	})
}

// GetUpdateProgress возвращает сохранённый прогресс
func (s *SystemService) GetUpdateProgress(token string) map[string]interface{} {
	if val, ok := s.progressMap.Load(token); ok {
		return val.(map[string]interface{})
	}
	return map[string]interface{}{
		"percent": 0,
		"message": "Ожидание...",
	}
}

// ExportPackage упаковывает систему в ZIP архив (исключая данные пользователя data/ и секреты)
func (s *SystemService) ExportPackage(w http.ResponseWriter) error {
	curr := s.GetVersion()
	cleanVer := regexp.MustCompile(`[^a-zA-Z0-9._-]`).ReplaceAllString(curr.Version, "_")
	zipFilename := fmt.Sprintf("npblog_update_v%s.zip", cleanVer)

	w.Header().Set("Content-Type", "application/zip")
	w.Header().Set("Content-Disposition", fmt.Sprintf("attachment; filename=\"%s\"", zipFilename))
	w.Header().Set("Pragma", "no-cache")
	w.Header().Set("Expires", "0")

	zw := zip.NewWriter(w)
	defer zw.Close()

	baseDir := s.cfg.BaseDir
	return filepath.Walk(baseDir, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return nil
		}
		rel, err := filepath.Rel(baseDir, path)
		if err != nil {
			return nil
		}
		rel = filepath.ToSlash(rel)
		if rel == "." {
			return nil
		}

		lower := strings.ToLower(rel)
		// Пропускаем служебные и пользовательские папки
		if info.IsDir() {
			if strings.HasPrefix(lower, ".git") ||
				strings.HasPrefix(lower, ".github") ||
				strings.HasPrefix(lower, ".vscode") ||
				strings.HasPrefix(lower, ".idea") ||
				strings.HasPrefix(lower, "node_modules") ||
				strings.HasPrefix(lower, "sys_update_tmp") ||
				strings.HasPrefix(lower, "rollback_tmp") ||
				strings.HasPrefix(lower, "editor_backup") ||
				strings.HasPrefix(lower, "data_backup") ||
				strings.HasPrefix(lower, "autosave") ||
				strings.HasPrefix(lower, "old_back") ||
				strings.HasPrefix(lower, "dist") ||
				strings.HasPrefix(lower, "data") {
				return filepath.SkipDir
			}
			return nil
		}

		// Пропускаем отдельные защищенные файлы
		if strings.HasSuffix(lower, ".zip") ||
			lower == "editor_settings.json" ||
			lower == "allowed_ips.txt" ||
			lower == "api_tokens.json" ||
			strings.HasPrefix(lower, "data/") {
			return nil
		}

		header, err := zip.FileInfoHeader(info)
		if err != nil {
			return nil
		}
		header.Name = rel
		header.Method = zip.Deflate

		writer, err := zw.CreateHeader(header)
		if err != nil {
			return nil
		}

		file, err := os.Open(path)
		if err != nil {
			return nil
		}
		defer file.Close()

		_, _ = io.Copy(writer, file)
		return nil
	})
}

// SystemBackupItem информация о бэкапе редактора
type SystemBackupItem struct {
	Filename string `json:"filename"`
	Size     int64  `json:"size"`
	Time     int64  `json:"time"`
}

// ListSystemBackups возвращает список бэкапов из editor_backup/
func (s *SystemService) ListSystemBackups() []SystemBackupItem {
	dir := filepath.Join(s.cfg.BaseDir, "editor_backup")
	var result []SystemBackupItem

	entries, err := os.ReadDir(dir)
	if err != nil {
		return []SystemBackupItem{}
	}

	for _, e := range entries {
		if !e.IsDir() && strings.HasSuffix(strings.ToLower(e.Name()), ".zip") {
			fi, err := e.Info()
			if err == nil {
				result = append(result, SystemBackupItem{
					Filename: e.Name(),
					Size:     fi.Size(),
					Time:     fi.ModTime().Unix(),
				})
			}
		}
	}

	sort.Slice(result, func(i, j int) bool {
		return result[i].Time > result[j].Time
	})

	return result
}

// RestoreSystemBackup восстанавливает систему из архива editor_backup/
func (s *SystemService) RestoreSystemBackup(filename string) (int, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	cleanName := filepath.Base(filename)
	backupPath := filepath.Join(s.cfg.BaseDir, "editor_backup", cleanName)

	r, err := zip.OpenReader(backupPath)
	if err != nil {
		return 0, fmt.Errorf("не удалось открыть архив бэкапа: %w", err)
	}
	defer r.Close()

	restoredCount := 0
	baseDir := s.cfg.BaseDir

	for _, f := range r.File {
		if f.FileInfo().IsDir() {
			continue
		}
		targetPath := filepath.Join(baseDir, f.Name)
		// Проверяем Path Traversal
		rel, err := filepath.Rel(baseDir, targetPath)
		if err != nil || strings.HasPrefix(rel, "..") {
			continue
		}

		// Защита пользовательских папок
		relLower := strings.ToLower(filepath.ToSlash(rel))
		if strings.HasPrefix(relLower, "data/") || relLower == "editor_settings.json" || relLower == "allowed_ips.txt" {
			continue
		}

		_ = os.MkdirAll(filepath.Dir(targetPath), 0755)

		rc, err := f.Open()
		if err != nil {
			continue
		}

		out, err := os.OpenFile(targetPath, os.O_WRONLY|os.O_CREATE|os.O_TRUNC, f.Mode())
		if err != nil {
			rc.Close()
			continue
		}

		_, copyErr := io.Copy(out, rc)
		out.Close()
		rc.Close()

		if copyErr == nil {
			restoredCount++
		}
	}

	return restoredCount, nil
}

// PreviewUpdateZip инспектирует загруженный ZIP-архив обновления
func (s *SystemService) PreviewUpdateZip(r io.ReaderAt, size int64) (map[string]interface{}, error) {
	zr, err := zip.NewReader(r, size)
	if err != nil {
		return nil, fmt.Errorf("некорректный ZIP архив: %w", err)
	}

	var files []string
	var newVersion string

	for _, f := range zr.File {
		if !f.FileInfo().IsDir() {
			files = append(files, f.Name)
			if f.Name == "version.json" || strings.HasSuffix(f.Name, "/version.json") {
				rc, err := f.Open()
				if err == nil {
					var v VersionData
					_ = json.NewDecoder(rc).Decode(&v)
					rc.Close()
					if v.Version != "" {
						newVersion = v.Version
					}
				}
			}
		}
	}

	curr := s.GetVersion()
	return map[string]interface{}{
		"totalFiles":     len(files),
		"files":          files,
		"currentVersion": curr.Version,
		"newVersion":     newVersion,
	}, nil
}
