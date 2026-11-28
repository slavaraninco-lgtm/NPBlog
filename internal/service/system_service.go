package service

import (
	"archive/zip"
	"context"
	"crypto/md5"
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"regexp"
	"runtime"
	"sort"
	"strconv"
	"strings"
	"sync"
	"time"

	"npblog/internal/config"
)

const (
	maxUpdateZipBytes     int64 = 200 << 20 // 200 MB
	maxUpdateExtractBytes int64 = 350 << 20 // 350 MB
	maxUpdateEntries            = 8000
)

var repoSlugRegex = regexp.MustCompile(`^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$`)

type SystemService struct {
	cfg     *config.AppConfig
	storage *StorageService
	mu      sync.RWMutex

	// Прогресс загрузки/подготовки обновлений: token -> progress map
	progressMap sync.Map

	// Подменяемые базовые URL для изолированного тестирования без выхода в интернет
	apiBaseURL     string
	archiveBaseURL string
}

func NewSystemService(cfg *config.AppConfig, storage *StorageService) *SystemService {
	return &SystemService{
		cfg:            cfg,
		storage:        storage,
		apiBaseURL:     "https://api.github.com",
		archiveBaseURL: "https://github.com",
	}
}

// SetGitHubURLs overrides GitHub API and archive base URLs (intended for local test servers).
func (s *SystemService) SetGitHubURLs(apiBase, archiveBase string) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if apiBase != "" {
		s.apiBaseURL = strings.TrimRight(apiBase, "/")
	}
	if archiveBase != "" {
		s.archiveBaseURL = strings.TrimRight(archiveBase, "/")
	}
}

func (s *SystemService) getGitHubURLs() (string, string) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	apiBase := s.apiBaseURL
	if apiBase == "" {
		apiBase = "https://api.github.com"
	}
	archBase := s.archiveBaseURL
	if archBase == "" {
		archBase = "https://github.com"
	}
	return apiBase, archBase
}

// VersionData структура version.json
type VersionData struct {
	Version     string   `json:"version"`
	Dev         bool     `json:"dev"`
	Channel     string   `json:"channel,omitempty"`
	LastUpdated string   `json:"last_updated"`
	Commit      string   `json:"commit,omitempty"`
	Changelog   []string `json:"changelog,omitempty"`
}

// UpdateCheckResult результат проверки обновлений
type UpdateCheckResult struct {
	Success        bool     `json:"success"`
	HasUpdate      bool     `json:"hasUpdate"`
	CurrentVersion string   `json:"currentVersion"`
	CurrentCommit  string   `json:"currentCommit,omitempty"`
	LatestVersion  string   `json:"latestVersion,omitempty"`
	Channel        string   `json:"channel"`
	Branch         string   `json:"branch,omitempty"`
	Repo           string   `json:"repo"`
	Tag            string   `json:"tag,omitempty"`
	Commit         string   `json:"commit,omitempty"`
	DownloadURL    string   `json:"downloadUrl,omitempty"`
	ArchiveURL     string   `json:"archiveUrl,omitempty"`
	Changelog      []string `json:"changelog,omitempty"`
	Error          string   `json:"error,omitempty"`
}

// UpdatePrepareRequest параметры подготовки обновления с GitHub
type UpdatePrepareRequest struct {
	Channel    string   `json:"channel"`
	Repo       string   `json:"repo"`
	Tag        string   `json:"tag"`
	Commit     string   `json:"commit"`
	ArchiveURL string   `json:"archiveUrl"`
	Changelog  []string `json:"changelog"`
	Token      string   `json:"token"`
}

// StagedUpdateMeta метаданные распакованного обновления в sys_update_tmp/<token>/meta.json
type StagedUpdateMeta struct {
	Token            string   `json:"token"`
	Channel          string   `json:"channel"`
	Repo             string   `json:"repo"`
	Tag              string   `json:"tag,omitempty"`
	Commit           string   `json:"commit,omitempty"`
	NewVersion       string   `json:"newVersion,omitempty"`
	RootFolder       string   `json:"rootFolder"`
	Changelog        []string `json:"changelog,omitempty"`
	TotalFiles       int      `json:"totalFiles"`
	Files            []string `json:"files,omitempty"`
	SkippedProtected []string `json:"skippedProtected,omitempty"`
	HasBinaryUpdate  bool     `json:"hasBinaryUpdate"`
	CreatedAt        int64    `json:"createdAt"`
}

// UpdateApplyResult результат безопасного применения обновления
type UpdateApplyResult struct {
	Success           bool     `json:"success"`
	Message           string   `json:"message"`
	BackupFile        string   `json:"backupFile,omitempty"`
	UpdatedFiles      int      `json:"updatedFiles"`
	UpdatedExecutables []string `json:"updatedExecutables,omitempty"`
	RestartRequired   bool     `json:"restartRequired"`
	NewVersion        string   `json:"newVersion,omitempty"`
	NewCommit         string   `json:"newCommit,omitempty"`
	Error             string   `json:"error,omitempty"`
}

func normalizeUpdateChannel(channel string) string {
	switch strings.ToLower(strings.TrimSpace(channel)) {
	case "canary", "dev":
		return "canary"
	case "go":
		return "go"
	default:
		return "stable"
	}
}

func channelBranch(channel string) string {
	switch channel {
	case "canary":
		return "dev"
	case "go":
		return "go"
	default:
		return ""
	}
}

func isCommitChannel(channel string) bool {
	return channel == "canary" || channel == "go"
}

// GetVersion читает version.json
func (s *SystemService) GetVersion() VersionData {
	vFile := filepath.Join(s.cfg.BaseDir, "version.json")
	var v VersionData
	if err := s.storage.SafeReadJSON(vFile, &v); err != nil {
		v = VersionData{
			Version:     "2.361",
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

func isNewerVersion(latest, current string) bool {
	parse := func(version string) ([]int, bool) {
		version = strings.TrimPrefix(strings.TrimSpace(version), "v")
		if version == "" {
			return nil, false
		}

		parts := strings.Split(version, ".")
		numbers := make([]int, len(parts))
		for i, part := range parts {
			number, err := strconv.Atoi(part)
			if err != nil {
				return nil, false
			}
			numbers[i] = number
		}
		return numbers, true
	}

	latestParts, latestOK := parse(latest)
	currentParts, currentOK := parse(current)
	if !latestOK || !currentOK {
		return false
	}

	for i := 0; i < len(latestParts) || i < len(currentParts); i++ {
		latestPart, currentPart := 0, 0
		if i < len(latestParts) {
			latestPart = latestParts[i]
		}
		if i < len(currentParts) {
			currentPart = currentParts[i]
		}
		if latestPart != currentPart {
			return latestPart > currentPart
		}
	}
	return false
}

// CheckAutoUpdate проверяет наличие обновлений на GitHub (Stable Releases, Canary dev-коммиты или Go-ветка по коммитам)
func (s *SystemService) CheckAutoUpdate(channel, repo string, force bool, githubToken string) UpdateCheckResult {
	current := s.GetVersion()
	settings := s.cfg.GetSettings()

	if repo == "" {
		repo = settings.UpdateRepo
	}
	if repo == "" {
		repo = "slavaraninco-lgtm/NPBlog"
	}
	repo = strings.TrimSpace(repo)

	if channel == "" {
		channel = settings.UpdateChannel
	}
	channel = normalizeUpdateChannel(channel)
	branch := channelBranch(channel)

	if githubToken == "" {
		githubToken = settings.UpdateGithubToken
	}

	res := UpdateCheckResult{
		Success:        true,
		CurrentVersion: current.Version,
		CurrentCommit:  current.Commit,
		Channel:        channel,
		Branch:         branch,
		Repo:           repo,
	}

	if !repoSlugRegex.MatchString(repo) {
		res.Success = false
		res.Error = "Некорректный формат репозитория GitHub (ожидается owner/repo)"
		return res
	}

	cacheDir := filepath.Join(s.cfg.BaseDir, "sys_update_tmp")
	_ = os.MkdirAll(cacheDir, 0755)

	cacheKey := fmt.Sprintf("%x", md5.Sum([]byte(channel+"_"+repo)))
	cacheFile := filepath.Join(cacheDir, "cache_"+cacheKey+".json")

	// Проверка кэша (15 минут)
	if !force {
		if fi, err := os.Stat(cacheFile); err == nil && time.Since(fi.ModTime()) < 15*time.Minute {
			var cached UpdateCheckResult
			if err := s.storage.SafeReadJSON(cacheFile, &cached); err == nil && cached.Success {
				cached.CurrentVersion = current.Version
				cached.CurrentCommit = current.Commit
				if isCommitChannel(channel) {
					cached.HasUpdate = cached.Commit != "" && cached.Commit != current.Commit
				} else {
					cached.HasUpdate = isNewerVersion(cached.LatestVersion, current.Version)
				}
				return cached
			}
		}
	}

	apiBase, archBase := s.getGitHubURLs()
	client := s.newGitHubHTTPClient(15 * time.Second)
	var reqURL string

	if isCommitChannel(channel) {
		reqURL = fmt.Sprintf("%s/repos/%s/commits?sha=%s&per_page=10", apiBase, repo, url.QueryEscape(branch))
	} else {
		reqURL = fmt.Sprintf("%s/repos/%s/releases/latest", apiBase, repo)
	}

	req, err := http.NewRequestWithContext(context.Background(), "GET", reqURL, nil)
	if err != nil {
		res.Success = false
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
		res.Success = false
		res.Error = "Ошибка подключения к GitHub: " + err.Error()
		return res
	}
	defer resp.Body.Close()

	if resp.StatusCode == 404 && !isCommitChannel(channel) {
		res.LatestVersion = current.Version
		res.HasUpdate = false
		return res
	}

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		res.Success = false
		if resp.StatusCode == 404 && branch != "" {
			res.Error = fmt.Sprintf("Ветка '%s' не найдена в репозитории %s (HTTP 404)", branch, repo)
		} else {
			res.Error = fmt.Sprintf("GitHub API вернул статус: %d", resp.StatusCode)
		}
		return res
	}

	bodyBytes, err := io.ReadAll(io.LimitReader(resp.Body, 4<<20))
	if err != nil {
		res.Success = false
		res.Error = "Ошибка чтения ответа GitHub: " + err.Error()
		return res
	}

	if isCommitChannel(channel) {
		type ghCommitItem struct {
			SHA    string `json:"sha"`
			Commit struct {
				Message string `json:"message"`
			} `json:"commit"`
		}
		var commits []ghCommitItem
		if err := json.Unmarshal(bodyBytes, &commits); err != nil || len(commits) == 0 {
			// Fallback на одиночный объект /commits/<branch> если вернулся не массив
			var single ghCommitItem
			if errSingle := json.Unmarshal(bodyBytes, &single); errSingle != nil || single.SHA == "" {
				res.Success = false
				res.Error = fmt.Sprintf("Не удалось разобрать коммиты ветки '%s'", branch)
				return res
			}
			commits = []ghCommitItem{single}
		}

		head := commits[0]
		res.Commit = head.SHA
		shortSha := head.SHA
		if len(shortSha) > 7 {
			shortSha = shortSha[:7]
		}
		res.LatestVersion = fmt.Sprintf("%s (%s)", branch, shortSha)
		res.HasUpdate = head.SHA != "" && head.SHA != current.Commit
		archiveURL := fmt.Sprintf("%s/%s/archive/refs/heads/%s.zip", archBase, repo, branch)
		res.DownloadURL = archiveURL
		res.ArchiveURL = archiveURL

		var cl []string
		for _, c := range commits {
			if current.Commit != "" && c.SHA == current.Commit {
				break
			}
			firstLine := strings.TrimSpace(strings.Split(c.Commit.Message, "\n")[0])
			cShort := c.SHA
			if len(cShort) > 7 {
				cShort = cShort[:7]
			}
			if firstLine != "" {
				cl = append(cl, fmt.Sprintf("[%s] %s", cShort, firstLine))
			}
		}
		if len(cl) == 0 && head.Commit.Message != "" {
			for _, line := range strings.Split(head.Commit.Message, "\n") {
				if trimmed := strings.TrimSpace(line); trimmed != "" {
					cl = append(cl, trimmed)
				}
			}
		}
		res.Changelog = cl
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
		if err := json.Unmarshal(bodyBytes, &releaseResp); err != nil {
			res.Success = false
			res.Error = "Ошибка разбора ответа релиза GitHub"
			return res
		}
		res.Tag = releaseResp.TagName
		res.LatestVersion = strings.TrimPrefix(releaseResp.TagName, "v")
		res.HasUpdate = isNewerVersion(res.LatestVersion, current.Version)
		archiveURL := releaseResp.ZipballURL
		for _, asset := range releaseResp.Assets {
			if strings.HasSuffix(strings.ToLower(asset.Name), ".zip") {
				archiveURL = asset.BrowserDownloadURL
				break
			}
		}
		res.DownloadURL = archiveURL
		res.ArchiveURL = archiveURL
		if releaseResp.Body != "" {
			res.Changelog = strings.Split(releaseResp.Body, "\n")
		}
	}

	// Кэшируем только успешный ответ
	_ = s.storage.SafeWriteJSON(cacheFile, res)
	return res
}

func (s *SystemService) newGitHubHTTPClient(timeout time.Duration) *http.Client {
	return &http.Client{
		Timeout: timeout,
		CheckRedirect: func(req *http.Request, via []*http.Request) error {
			if len(via) >= 10 {
				return errors.New("слишком много перенаправлений при загрузке обновления")
			}
			if !s.isAllowedArchiveURL(req.URL.String()) {
				return fmt.Errorf("запрещённый адрес перенаправления: %s", req.URL.Host)
			}
			// Не передаём токен GitHub сторонним CDN-хостам (release-assets/codeload)
			if len(via) > 0 && req.URL.Host != via[0].URL.Host {
				req.Header.Del("Authorization")
			}
			return nil
		},
	}
}

func (s *SystemService) isAllowedArchiveURL(rawURL string) bool {
	u, err := url.Parse(rawURL)
	if err != nil {
		return false
	}
	apiBase, archBase := s.getGitHubURLs()
	for _, base := range []string{apiBase, archBase} {
		if bu, err := url.Parse(base); err == nil && bu.Host != "" && strings.EqualFold(u.Host, bu.Host) && u.Scheme == bu.Scheme {
			return true
		}
	}
	if u.Scheme != "https" {
		return false
	}
	host := strings.ToLower(u.Hostname())
	allowedHosts := map[string]bool{
		"github.com":                           true,
		"api.github.com":                       true,
		"codeload.github.com":                  true,
		"objects.githubusercontent.com":        true,
		"release-assets.githubusercontent.com": true,
		"github-releases.githubusercontent.com": true,
	}
	return allowedHosts[host]
}

// PrepareAutoUpdate downloads, verifies, and stages an update ZIP archive from GitHub without mutating user data.
func (s *SystemService) PrepareAutoUpdate(reqData UpdatePrepareRequest) (*StagedUpdateMeta, error) {
	settings := s.cfg.GetSettings()
	channel := normalizeUpdateChannel(reqData.Channel)
	branch := channelBranch(channel)

	repo := strings.TrimSpace(reqData.Repo)
	if repo == "" {
		repo = strings.TrimSpace(settings.UpdateRepo)
	}
	if repo == "" {
		repo = "slavaraninco-lgtm/NPBlog"
	}
	if !repoSlugRegex.MatchString(repo) {
		return nil, errors.New("некорректный репозиторий обновления")
	}

	archiveURL := strings.TrimSpace(reqData.ArchiveURL)
	_, archBase := s.getGitHubURLs()
	if archiveURL == "" {
		if isCommitChannel(channel) {
			archiveURL = fmt.Sprintf("%s/%s/archive/refs/heads/%s.zip", archBase, repo, branch)
		} else if reqData.Tag != "" {
			archiveURL = fmt.Sprintf("%s/%s/archive/refs/tags/%s.zip", archBase, repo, url.PathEscape(reqData.Tag))
		}
	}
	if !s.isAllowedArchiveURL(archiveURL) {
		return nil, errors.New("недопустимый URL архива обновления")
	}

	token := generateUpdateToken()
	s.SetUpdateProgress(token, 12, "Подключение к серверу обновлений...")

	githubToken := strings.TrimSpace(reqData.Token)
	if githubToken == "" {
		githubToken = strings.TrimSpace(settings.UpdateGithubToken)
	}

	client := s.newGitHubHTTPClient(120 * time.Second)
	httpReq, err := http.NewRequestWithContext(context.Background(), http.MethodGet, archiveURL, nil)
	if err != nil {
		return nil, err
	}
	httpReq.Header.Set("User-Agent", "NPBlog-Go-Updater")
	if githubToken != "" {
		httpReq.Header.Set("Authorization", "token "+githubToken)
	}

	s.SetUpdateProgress(token, 28, "Скачивание архива обновления...")
	resp, err := client.Do(httpReq)
	if err != nil {
		return nil, fmt.Errorf("ошибка загрузки архива: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return nil, fmt.Errorf("не удалось скачать архив (HTTP %d)", resp.StatusCode)
	}

	stageDir := s.stageDir(token)
	_ = os.RemoveAll(stageDir)
	if err := os.MkdirAll(stageDir, 0755); err != nil {
		return nil, fmt.Errorf("не удалось создать временную директорию обновления: %w", err)
	}

	zipPath := filepath.Join(stageDir, "package.zip")
	outFile, err := os.OpenFile(zipPath, os.O_CREATE|os.O_WRONLY|os.O_TRUNC, 0600)
	if err != nil {
		_ = os.RemoveAll(stageDir)
		return nil, err
	}

	written, copyErr := io.Copy(outFile, io.LimitReader(resp.Body, maxUpdateZipBytes+1))
	_ = outFile.Close()
	if copyErr != nil {
		_ = os.RemoveAll(stageDir)
		return nil, fmt.Errorf("ошибка сохранения архива обновления: %w", copyErr)
	}
	if written > maxUpdateZipBytes {
		_ = os.RemoveAll(stageDir)
		return nil, errors.New("размер архива обновления превышает лимит 200 МБ")
	}

	s.SetUpdateProgress(token, 58, "Проверка целостности и распаковка архива...")
	meta, err := s.stageZipFile(token, zipPath, channel, repo, reqData.Tag, reqData.Commit, reqData.Changelog)
	if err != nil {
		_ = os.RemoveAll(stageDir)
		return nil, err
	}

	s.SetUpdateProgress(token, 72, "Файлы обновления подготовлены")
	return meta, nil
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

func generateUpdateToken() string {
	b := make([]byte, 6)
	_, _ = rand.Read(b)
	return fmt.Sprintf("upd_%d_%s", time.Now().Unix(), hex.EncodeToString(b))
}

func (s *SystemService) stageDir(token string) string {
	clean := regexp.MustCompile(`[^a-zA-Z0-9_-]`).ReplaceAllString(token, "")
	if clean == "" {
		clean = "invalid"
	}
	return filepath.Join(s.cfg.BaseDir, "sys_update_tmp", clean)
}

func (s *SystemService) protectedBlogPrefixes() []string {
	prefixes := []string{"data", "data_backup", "editor_backup", "sys_update_tmp", "rollback_tmp", "autosave", "backups", "uploads", "files", "old_back"}
	seen := make(map[string]bool)
	var res []string
	add := func(p string) {
		p = strings.ToLower(strings.Trim(strings.TrimSpace(strings.ReplaceAll(p, "\\", "/")), "/"))
		if p != "" && p != "." && !seen[p] {
			seen[p] = true
			res = append(res, p)
		}
	}
	for _, d := range prefixes {
		add(d)
	}
	settings := s.cfg.GetSettings()
	for _, bp := range settings.BlogPaths {
		if abs := s.storage.ResolveBlogAbsPath(bp); abs != "" {
			if rel, err := filepath.Rel(s.cfg.BaseDir, abs); err == nil && !strings.HasPrefix(rel, "..") {
				add(rel)
			}
		}
	}
	if settings.ActiveBlogPath != "" {
		if abs := s.storage.ResolveBlogAbsPath(settings.ActiveBlogPath); abs != "" {
			if rel, err := filepath.Rel(s.cfg.BaseDir, abs); err == nil && !strings.HasPrefix(rel, "..") {
				add(rel)
			}
		}
	}
	return res
}

// isProtectedRelPath protects user blogs, uploads, backups, settings, credentials, and VCS metadata from overwrite/export.
func (s *SystemService) isProtectedRelPath(relSlash string) bool {
	lower := strings.ToLower(strings.TrimPrefix(filepath.ToSlash(relSlash), "./"))
	if lower == "" || lower == "." {
		return true
	}

	if strings.HasPrefix(lower, ".git/") || lower == ".git" ||
		strings.HasPrefix(lower, ".github/") ||
		strings.HasPrefix(lower, ".vscode/") ||
		strings.HasPrefix(lower, ".idea/") ||
		strings.HasPrefix(lower, "data_") {
		return true
	}

	for _, prefix := range s.protectedBlogPrefixes() {
		if lower == prefix || strings.HasPrefix(lower, prefix+"/") {
			return true
		}
	}

	protectedExact := map[string]bool{
		"editor_settings.json":  true,
		"allowed_ips.txt":       true,
		"api_tokens.json":       true,
		"ftp.json":              true,
		"sessions.json":         true,
		"history.json":          true,
		"login_lockouts.json":   true,
		"autosave-settings.json": true,
		".env":                  true,
	}
	return protectedExact[lower]
}

// PreviewUploadedUpdateZip validates, extracts, and stages an uploaded local .zip update archive.
func (s *SystemService) PreviewUploadedUpdateZip(r io.ReaderAt, size int64) (map[string]interface{}, error) {
	if size <= 0 || size > maxUpdateZipBytes {
		return nil, errors.New("некорректный размер ZIP-архива (максимум 200 МБ)")
	}

	token := generateUpdateToken()
	stageDir := s.stageDir(token)
	_ = os.RemoveAll(stageDir)
	if err := os.MkdirAll(stageDir, 0755); err != nil {
		return nil, err
	}

	zipPath := filepath.Join(stageDir, "package.zip")
	outFile, err := os.OpenFile(zipPath, os.O_CREATE|os.O_WRONLY|os.O_TRUNC, 0600)
	if err != nil {
		_ = os.RemoveAll(stageDir)
		return nil, err
	}
	if _, err := io.Copy(outFile, io.NewSectionReader(r, 0, size)); err != nil {
		_ = outFile.Close()
		_ = os.RemoveAll(stageDir)
		return nil, err
	}
	_ = outFile.Close()

	meta, err := s.stageZipFile(token, zipPath, "manual", "", "", "", nil)
	if err != nil {
		_ = os.RemoveAll(stageDir)
		return nil, err
	}

	curr := s.GetVersion()
	return map[string]interface{}{
		"token":            meta.Token,
		"rootFolder":       meta.RootFolder,
		"totalFiles":       meta.TotalFiles,
		"files":            meta.Files,
		"skippedProtected": meta.SkippedProtected,
		"hasBinaryUpdate":  meta.HasBinaryUpdate,
		"currentVersion":   curr.Version,
		"newVersion":       meta.NewVersion,
	}, nil
}

func (s *SystemService) stageZipFile(token, zipPath, channel, repo, tag, commit string, changelog []string) (*StagedUpdateMeta, error) {
	zr, err := zip.OpenReader(zipPath)
	if err != nil {
		return nil, fmt.Errorf("некорректный ZIP-архив: %w", err)
	}
	defer zr.Close()

	if len(zr.File) > maxUpdateEntries {
		return nil, errors.New("в архиве слишком много элементов")
	}

	// Detect GitHub-style single top-level wrapper folder (e.g. "NPBlog-go/")
	rootFolder := ""
	commonTop := ""
	allHaveTop := true
	hasEntries := false
	for _, f := range zr.File {
		name := strings.TrimPrefix(strings.ReplaceAll(f.Name, "\\", "/"), "./")
		if name == "" || name == "." {
			continue
		}
		hasEntries = true
		slashIdx := strings.IndexByte(name, '/')
		if slashIdx <= 0 {
			allHaveTop = false
			break
		}
		top := name[:slashIdx]
		if commonTop == "" {
			commonTop = top
		} else if commonTop != top {
			allHaveTop = false
			break
		}
	}
	if hasEntries && allHaveTop && commonTop != "" && !strings.Contains(commonTop, "..") {
		rootFolder = commonTop
	}

	extractDir := filepath.Join(s.stageDir(token), "extracted")
	if err := os.MkdirAll(extractDir, 0755); err != nil {
		return nil, err
	}

	var totalExtracted int64
	var filesList []string
	var skippedList []string
	var newVersion string
	var archiveChangelog []string
	hasBinaryUpdate := false

	for _, f := range zr.File {
		if f.Mode()&os.ModeSymlink != 0 {
			return nil, errors.New("символические ссылки в архиве обновления запрещены")
		}
		rawName := strings.ReplaceAll(f.Name, "\\", "/")
		if strings.ContainsRune(rawName, 0) || strings.Contains(rawName, ":") {
			return nil, fmt.Errorf("недопустимое имя файла в архиве: %s", f.Name)
		}
		if rootFolder != "" && strings.HasPrefix(rawName, rootFolder+"/") {
			rawName = strings.TrimPrefix(rawName, rootFolder+"/")
		}
		cleanRel := filepath.ToSlash(filepath.Clean(strings.TrimPrefix(rawName, "/")))
		if cleanRel == "." || cleanRel == "" {
			continue
		}
		if strings.HasPrefix(cleanRel, "../") || cleanRel == ".." || filepath.IsAbs(cleanRel) {
			return nil, fmt.Errorf("обнаружена попытка выхода за пределы каталога (Zip-Slip): %s", f.Name)
		}
		if f.FileInfo().IsDir() {
			continue
		}

		if s.isProtectedRelPath(cleanRel) {
			skippedList = append(skippedList, cleanRel)
			continue
		}

		destPath := filepath.Join(extractDir, filepath.FromSlash(cleanRel))
		relCheck, relErr := filepath.Rel(extractDir, destPath)
		if relErr != nil || strings.HasPrefix(relCheck, "..") {
			return nil, fmt.Errorf("небезопасный путь распаковки: %s", f.Name)
		}
		if err := os.MkdirAll(filepath.Dir(destPath), 0755); err != nil {
			return nil, err
		}

		rc, err := f.Open()
		if err != nil {
			return nil, fmt.Errorf("ошибка чтения файла из архива (%s): %w", cleanRel, err)
		}
		out, err := os.OpenFile(destPath, os.O_CREATE|os.O_WRONLY|os.O_TRUNC, 0644)
		if err != nil {
			_ = rc.Close()
			return nil, err
		}

		n, copyErr := io.Copy(out, io.LimitReader(rc, maxUpdateExtractBytes-totalExtracted+1))
		_ = out.Close()
		_ = rc.Close()
		if copyErr != nil {
			return nil, copyErr
		}
		totalExtracted += n
		if totalExtracted > maxUpdateExtractBytes {
			return nil, errors.New("превышен максимальный размер распакованного обновления")
		}

		filesList = append(filesList, cleanRel)

		lower := strings.ToLower(cleanRel)
		if lower == "npblog.exe" || lower == "npblog" || strings.HasPrefix(lower, "dist/npblog") {
			hasBinaryUpdate = true
		}
		if lower == "version.json" {
			if vBytes, rErr := os.ReadFile(destPath); rErr == nil {
				var v VersionData
				if json.Unmarshal(vBytes, &v) == nil {
					if v.Version != "" {
						newVersion = v.Version
					}
					if len(v.Changelog) > 0 {
						archiveChangelog = v.Changelog
					}
				}
			}
		}
	}

	if len(filesList) == 0 {
		return nil, errors.New("в архиве не найдено системных файлов для обновления")
	}

	if len(changelog) == 0 && len(archiveChangelog) > 0 {
		changelog = archiveChangelog
	}
	if newVersion == "" && commit != "" && channel != "manual" {
		short := commit
		if len(short) > 7 {
			short = short[:7]
		}
		br := channelBranch(channel)
		if br != "" {
			newVersion = fmt.Sprintf("%s (%s)", br, short)
		} else {
			newVersion = short
		}
	}

	meta := &StagedUpdateMeta{
		Token:            token,
		Channel:          channel,
		Repo:             repo,
		Tag:              tag,
		Commit:           commit,
		NewVersion:       newVersion,
		RootFolder:       rootFolder,
		Changelog:        changelog,
		TotalFiles:       len(filesList),
		Files:            filesList,
		SkippedProtected: skippedList,
		HasBinaryUpdate:  hasBinaryUpdate,
		CreatedAt:        time.Now().Unix(),
	}
	if err := s.storage.SafeWriteJSON(filepath.Join(s.stageDir(token), "meta.json"), meta); err != nil {
		return nil, err
	}
	return meta, nil
}

// CreateSystemBackup writes a pre-update backup ZIP of current system files into editor_backup/.
func (s *SystemService) CreateSystemBackup() (string, error) {
	backupDir := filepath.Join(s.cfg.BaseDir, "editor_backup")
	if err := os.MkdirAll(backupDir, 0755); err != nil {
		return "", err
	}

	name := fmt.Sprintf("sys_backup_%s.zip", time.Now().Format("20060102_150405"))
	targetZip := filepath.Join(backupDir, name)
	tmpZip := targetZip + ".tmp"

	f, err := os.OpenFile(tmpZip, os.O_CREATE|os.O_WRONLY|os.O_TRUNC, 0644)
	if err != nil {
		return "", err
	}
	zw := zip.NewWriter(f)

	baseDir := s.cfg.BaseDir
	walkErr := filepath.Walk(baseDir, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return nil
		}
		rel, err := filepath.Rel(baseDir, path)
		if err != nil {
			return nil
		}
		relSlash := filepath.ToSlash(rel)
		if relSlash == "." {
			return nil
		}

		lower := strings.ToLower(relSlash)
		if info.IsDir() {
			if s.isProtectedRelPath(relSlash) || lower == "node_modules" || lower == "dist" {
				return filepath.SkipDir
			}
			return nil
		}
		if strings.HasSuffix(lower, ".zip") || strings.HasSuffix(lower, ".old") || strings.HasSuffix(lower, ".new") || s.isProtectedRelPath(relSlash) {
			return nil
		}

		hdr, err := zip.FileInfoHeader(info)
		if err != nil {
			return nil
		}
		hdr.Name = relSlash
		hdr.Method = zip.Deflate
		w, err := zw.CreateHeader(hdr)
		if err != nil {
			return err
		}
		src, err := os.Open(path)
		if err != nil {
			return nil
		}
		_, _ = io.Copy(w, src)
		_ = src.Close()
		return nil
	})

	_ = zw.Close()
	_ = f.Close()
	if walkErr != nil {
		_ = os.Remove(tmpZip)
		return "", walkErr
	}
	if err := os.Rename(tmpZip, targetZip); err != nil {
		_ = os.Remove(tmpZip)
		return "", err
	}
	return name, nil
}

// ApplyUpdate creates a pre-update system backup, applies staged system files while protecting user data,
// atomically swaps root executables, and records updated version/commit metadata.
func (s *SystemService) ApplyUpdate(token string) (*UpdateApplyResult, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	if strings.TrimSpace(token) == "" {
		return nil, errors.New("не передан токен обновления")
	}

	stageDir := s.stageDir(token)
	var meta StagedUpdateMeta
	if err := s.storage.SafeReadJSON(filepath.Join(stageDir, "meta.json"), &meta); err != nil {
		return nil, errors.New("подготовленное обновление не найдено или устарело")
	}

	extractDir := filepath.Join(stageDir, "extracted")
	if _, err := os.Stat(extractDir); err != nil {
		return nil, errors.New("распакованные файлы обновления отсутствуют")
	}

	s.SetUpdateProgress(token, 78, "Создание резервной копии системы...")
	backupFile, err := s.CreateSystemBackup()
	if err != nil {
		return nil, fmt.Errorf("не удалось создать бэкап перед обновлением: %w", err)
	}

	s.SetUpdateProgress(token, 86, "Безопасная установка системных файлов...")
	updatedFiles := 0
	var rootBins []string

	// Откладываем замену корневых исполняемых файлов до завершения копирования системных компонентов
	var deferredBinaries []struct {
		rel  string
		src  string
		dest string
	}

	for _, relSlash := range meta.Files {
		if s.isProtectedRelPath(relSlash) {
			continue
		}
		srcPath := filepath.Join(extractDir, filepath.FromSlash(relSlash))
		destPath, safeErr := s.storage.ValidateSafePath(s.cfg.BaseDir, relSlash)
		if safeErr != nil {
			continue
		}

		if isExecutableRelPath(relSlash) {
			deferredBinaries = append(deferredBinaries, struct {
				rel  string
				src  string
				dest string
			}{rel: relSlash, src: srcPath, dest: destPath})
			continue
		}

		raw, readErr := os.ReadFile(srcPath)
		if readErr != nil {
			return nil, fmt.Errorf("ошибка чтения файла обновления %s: %w", relSlash, readErr)
		}
		if writeErr := s.storage.SafeWriteFile(destPath, raw); writeErr != nil {
			return nil, fmt.Errorf("ошибка обновления файла %s: %w", relSlash, writeErr)
		}
		updatedFiles++
	}

	s.SetUpdateProgress(token, 94, "Обновление корневых исполняемых файлов...")
	updatedBinSet := make(map[string]bool)
	for _, b := range deferredBinaries {
		raw, readErr := os.ReadFile(b.src)
		if readErr != nil || len(raw) == 0 {
			return nil, fmt.Errorf("ошибка чтения исполняемого файла %s", b.rel)
		}
		if err := atomicReplaceBinary(b.dest, raw); err != nil {
			return nil, fmt.Errorf("ошибка обновления исполняемого файла %s: %w", b.rel, err)
		}
		updatedBinSet[b.dest] = true
		rootBins = append(rootBins, b.rel)
		updatedFiles++
	}

	// Синхронизируем корневой npblog.exe / npblog из dist/, если в пакете лежал только dist/ бинарник для текущей ОС
	if syncedRel, err := s.syncCurrentOSRootBinary(extractDir, updatedBinSet); err != nil {
		return nil, err
	} else if syncedRel != "" {
		rootBins = append(rootBins, syncedRel)
	}

	// Сохраняем актуальную версию и SHA коммита в version.json
	v := s.GetVersion()
	if meta.NewVersion != "" && !isCommitChannel(meta.Channel) {
		v.Version = strings.TrimPrefix(meta.NewVersion, "v")
	}
	if meta.Commit != "" {
		v.Commit = meta.Commit
	}
	if meta.Channel != "" && meta.Channel != "manual" {
		v.Channel = meta.Channel
		v.Dev = isCommitChannel(meta.Channel)
	}
	if len(meta.Changelog) > 0 {
		v.Changelog = meta.Changelog
	}
	v.LastUpdated = time.Now().UTC().Format(time.RFC3339)
	_ = s.storage.SafeWriteJSON(filepath.Join(s.cfg.BaseDir, "version.json"), v)

	// Очищаем кэш проверки обновлений и временную папку
	s.clearUpdateCache()
	_ = os.RemoveAll(stageDir)
	s.cfg.ReloadSettings()

	s.SetUpdateProgress(token, 100, "Обновление успешно завершено")

	return &UpdateApplyResult{
		Success:            true,
		Message:            "Система безопасно обновлена. Пользовательские данные сохранены.",
		BackupFile:         backupFile,
		UpdatedFiles:       updatedFiles,
		UpdatedExecutables: rootBins,
		RestartRequired:    len(rootBins) > 0,
		NewVersion:         v.Version,
		NewCommit:          v.Commit,
	}, nil
}

func isExecutableRelPath(relSlash string) bool {
	lower := strings.ToLower(relSlash)
	return lower == "npblog.exe" || lower == "npblog" ||
		lower == "npblog-test.exe" ||
		strings.HasPrefix(lower, "dist/npblog")
}

func (s *SystemService) syncCurrentOSRootBinary(extractDir string, alreadyUpdated map[string]bool) (string, error) {
	var distCandidates []string
	rootName := "npblog"
	if runtime.GOOS == "windows" {
		rootName = "npblog.exe"
		distCandidates = []string{"npblog.exe", "dist/npblog.exe"}
	} else if runtime.GOARCH == "arm64" {
		distCandidates = []string{"npblog", "dist/npblog-linux-arm64", "dist/npblog-linux-amd64"}
	} else {
		distCandidates = []string{"npblog", "dist/npblog-linux-amd64"}
	}

	var matchedBytes []byte
	for _, cand := range distCandidates {
		p := filepath.Join(extractDir, filepath.FromSlash(cand))
		if b, err := os.ReadFile(p); err == nil && len(b) > 0 {
			matchedBytes = b
			break
		}
	}
	if len(matchedBytes) == 0 {
		return "", nil
	}

	rootTarget := filepath.Join(s.cfg.BaseDir, rootName)
	updatedRoot := false
	if !alreadyUpdated[rootTarget] {
		if err := atomicReplaceBinary(rootTarget, matchedBytes); err != nil {
			return "", fmt.Errorf("ошибка синхронизации корневого %s: %w", rootName, err)
		}
		updatedRoot = true
	}

	// Если текущий запущенный exe лежит по нестандартному пути в том же BaseDir, обновим и его
	if curExe, err := os.Executable(); err == nil && curExe != "" {
		if !alreadyUpdated[curExe] && !samePathCI(curExe, rootTarget) {
			rel, relErr := filepath.Rel(s.cfg.BaseDir, curExe)
			if relErr == nil && !strings.HasPrefix(rel, "..") {
				_ = atomicReplaceBinary(curExe, matchedBytes)
			}
		}
	}

	if updatedRoot {
		return rootName, nil
	}
	return "", nil
}

func samePathCI(a, b string) bool {
	ca := filepath.Clean(a)
	cb := filepath.Clean(b)
	if runtime.GOOS == "windows" {
		return strings.EqualFold(ca, cb)
	}
	return ca == cb
}

// atomicReplaceBinary replaces an executable even while running on Windows/Linux using .new/.old swaps with rollback.
func atomicReplaceBinary(targetPath string, raw []byte) error {
	dir := filepath.Dir(targetPath)
	if err := os.MkdirAll(dir, 0755); err != nil {
		return err
	}

	newPath := targetPath + ".new"
	if err := os.WriteFile(newPath, raw, 0755); err != nil {
		return err
	}

	if _, err := os.Stat(targetPath); os.IsNotExist(err) {
		if err := os.Rename(newPath, targetPath); err != nil {
			_ = os.Remove(newPath)
			return err
		}
		return nil
	}

	oldPath := fmt.Sprintf("%s.old.%d", targetPath, time.Now().UnixNano())
	if err := os.Rename(targetPath, oldPath); err != nil {
		_ = os.Remove(newPath)
		return err
	}

	if err := os.Rename(newPath, targetPath); err != nil {
		// Немедленный откат прежнего бинарника
		_ = os.Rename(oldPath, targetPath)
		_ = os.Remove(newPath)
		return err
	}

	// На Linux удалится сразу; на Windows заблокированный .old уберет CleanupOldBinaries при следующем старте
	_ = os.Remove(oldPath)
	return nil
}

// CleanupOldBinaries removes leftover .old.* and .new staging artifacts from prior live binary updates.
func CleanupOldBinaries(baseDir string) {
	if baseDir == "" {
		return
	}
	for _, dir := range []string{baseDir, filepath.Join(baseDir, "dist")} {
		entries, err := os.ReadDir(dir)
		if err != nil {
			continue
		}
		for _, e := range entries {
			if e.IsDir() {
				continue
			}
			name := e.Name()
			if strings.Contains(name, ".old.") || strings.HasSuffix(name, ".old") || strings.HasSuffix(name, ".new") {
				_ = os.Remove(filepath.Join(dir, name))
			}
		}
	}
}

func (s *SystemService) clearUpdateCache() {
	cacheDir := filepath.Join(s.cfg.BaseDir, "sys_update_tmp")
	entries, err := os.ReadDir(cacheDir)
	if err != nil {
		return
	}
	for _, e := range entries {
		if !e.IsDir() && strings.HasPrefix(e.Name(), "cache_") && strings.HasSuffix(e.Name(), ".json") {
			_ = os.Remove(filepath.Join(cacheDir, e.Name()))
		}
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
		if info.IsDir() {
			if s.isProtectedRelPath(rel) || strings.HasPrefix(lower, "node_modules") || strings.HasPrefix(lower, "dist") {
				return filepath.SkipDir
			}
			return nil
		}

		if strings.HasSuffix(lower, ".zip") || strings.HasSuffix(lower, ".old") || strings.HasSuffix(lower, ".new") || s.isProtectedRelPath(rel) {
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
		if f.FileInfo().IsDir() || f.Mode()&os.ModeSymlink != 0 {
			continue
		}
		relSlash := filepath.ToSlash(filepath.Clean(f.Name))
		targetPath, err := s.storage.ValidateSafePath(baseDir, relSlash)
		if err != nil {
			continue
		}

		// Защита пользовательских данных и исходников/исполняемых при откате из старого системного бэкапа
		relLower := strings.ToLower(relSlash)
		if s.isProtectedRelPath(relSlash) ||
			strings.HasPrefix(relLower, "cmd/") ||
			strings.HasPrefix(relLower, "internal/") ||
			strings.HasPrefix(relLower, "api/") ||
			strings.HasSuffix(relLower, ".exe") ||
			strings.HasSuffix(relLower, ".go") ||
			relLower == "go.mod" ||
			relLower == "go.sum" {
			continue
		}

		rc, err := f.Open()
		if err != nil {
			continue
		}
		raw, readErr := io.ReadAll(rc)
		_ = rc.Close()
		if readErr != nil {
			continue
		}
		if s.storage.SafeWriteFile(targetPath, raw) == nil {
			restoredCount++
		}
	}

	return restoredCount, nil
}

// PreviewUpdateZip сохраняет backward compatibility wrapper для анализа ZIP (и подготавливает staging токен).
func (s *SystemService) PreviewUpdateZip(r io.ReaderAt, size int64) (map[string]interface{}, error) {
	return s.PreviewUploadedUpdateZip(r, size)
}
