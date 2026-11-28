package service

import (
	"archive/zip"
	"bytes"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"runtime"
	"strings"
	"testing"

	"npblog/internal/config"
	"npblog/internal/domain"
)

func TestIsNewerVersion(t *testing.T) {
	tests := []struct {
		name    string
		latest  string
		current string
		want    bool
	}{
		{name: "newer patch", latest: "2.361", current: "2.360", want: true},
		{name: "older release", latest: "2.359", current: "2.360", want: false},
		{name: "same version", latest: "2.360", current: "2.360", want: false},
		{name: "numeric segments", latest: "2.100", current: "2.99", want: true},
		{name: "newer major", latest: "3.0", current: "2.360", want: true},
		{name: "release tag prefix", latest: "v2.361", current: "2.360", want: true},
		{name: "equivalent zero padding", latest: "2.360.0", current: "2.360", want: false},
		{name: "missing latest", latest: "", current: "2.360", want: false},
		{name: "invalid latest", latest: "development", current: "2.360", want: false},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := isNewerVersion(tt.latest, tt.current); got != tt.want {
				t.Fatalf("isNewerVersion(%q, %q) = %v, want %v", tt.latest, tt.current, got, tt.want)
			}
		})
	}
}

func buildTestUpdateZip(t *testing.T, entries map[string]string) []byte {
	t.Helper()
	var buf bytes.Buffer
	zw := zip.NewWriter(&buf)
	for name, content := range entries {
		fw, err := zw.Create(name)
		if err != nil {
			t.Fatalf("zip create %q: %v", name, err)
		}
		if _, err := fw.Write([]byte(content)); err != nil {
			t.Fatalf("zip write %q: %v", name, err)
		}
	}
	if err := zw.Close(); err != nil {
		t.Fatalf("zip close: %v", err)
	}
	return buf.Bytes()
}

func newTestSystemEnv(t *testing.T) (string, *SystemService) {
	t.Helper()
	tmpDir := t.TempDir()

	// Пользовательские данные и конфигурация, которые не должны затираться при обновлении
	mustWrite := func(rel, content string) {
		full := filepath.Join(tmpDir, filepath.FromSlash(rel))
		if err := os.MkdirAll(filepath.Dir(full), 0755); err != nil {
			t.Fatalf("mkdir %s: %v", full, err)
		}
		if err := os.WriteFile(full, []byte(content), 0644); err != nil {
			t.Fatalf("write %s: %v", full, err)
		}
	}

	mustWrite("version.json", `{"version":"2.361","dev":true,"channel":"go","last_updated":"2026-11-28","commit":"1111111aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","changelog":["init"]}`)
	mustWrite("editor_settings.json", `{"blogPaths":["data","data_custom"],"updateChannel":"go"}`)
	mustWrite("sessions.json", `{"secret-session":"token-value"}`)
	mustWrite("data/blog/posts/post1.json", `{"id":"post1","title":"User Post"}`)
	mustWrite("data_custom/blog/posts/post2.json", `{"id":"post2","title":"Alt Blog Post"}`)
	mustWrite("old_back/index.php", `<?php echo 'legacy';`)
	mustWrite("web/templates/editor.html", `<html>old-ui</html>`)
	mustWrite("npblog.exe", `OLD_WIN_BIN`)
	mustWrite("npblog", `OLD_LINUX_BIN`)
	mustWrite("dist/npblog.exe", `OLD_DIST_WIN_BIN`)
	mustWrite("dist/npblog-linux-amd64", `OLD_DIST_LINUX_BIN`)

	cfg := &config.AppConfig{
		BaseDir:   tmpDir,
		AssetsDir: tmpDir,
		Settings: domain.EditorSettings{
			UpdateChannel: "go",
			UpdateRepo:    "owner/repo",
			BlogPaths:     []string{"data", "data_custom"},
		},
	}
	storage := NewStorageService(cfg)
	return tmpDir, NewSystemService(cfg, storage)
}

func TestGoChannelCommitCheckAndSafeApplyUpdate(t *testing.T) {
	tmpDir, sysSvc := newTestSystemEnv(t)

	newCommit := "2222222bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"
	prevCommit := "1111111aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"

	archiveBytes := buildTestUpdateZip(t, map[string]string{
		"NPBlog-go/version.json":             `{"version":"2.362","dev":true,"changelog":["Go branch build"]}`,
		"NPBlog-go/web/templates/editor.html": `<html>new-go-ui</html>`,
		"NPBlog-go/npblog.exe":               `NEW_WIN_BIN`,
		"NPBlog-go/npblog":                   `NEW_LINUX_BIN`,
		"NPBlog-go/dist/npblog.exe":          `NEW_DIST_WIN_BIN`,
		"NPBlog-go/dist/npblog-linux-amd64":  `NEW_DIST_LINUX_BIN`,
		// Попытки перезаписи пользовательских данных должны игнорироваться
		"NPBlog-go/editor_settings.json":          `{"hacked":true}`,
		"NPBlog-go/data/blog/posts/post1.json":    `{"title":"OVERWRITTEN"}`,
		"NPBlog-go/data_custom/blog/posts/p.json": `{"title":"OVERWRITTEN_ALT"}`,
		"NPBlog-go/old_back/index.php":            `OVERWRITTEN_OLD_BACK`,
		"NPBlog-go/sessions.json":                 `{}`,
	})

	commitsPayload := []map[string]interface{}{
		{
			"sha": newCommit,
			"commit": map[string]interface{}{
				"message": "feat: add go channel safe updater",
				"committer": map[string]interface{}{
					"date": "2026-11-28T15:00:00Z",
				},
			},
		},
		{
			"sha": prevCommit,
			"commit": map[string]interface{}{
				"message": "chore: base commit",
				"committer": map[string]interface{}{
					"date": "2026-11-27T15:00:00Z",
				},
			},
		},
	}

	var requestedBranch string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch {
		case r.URL.Path == "/repos/owner/repo/commits":
			requestedBranch = r.URL.Query().Get("sha")
			w.Header().Set("Content-Type", "application/json")
			_ = json.NewEncoder(w).Encode(commitsPayload)
		case r.URL.Path == "/owner/repo/archive/refs/heads/go.zip":
			w.Header().Set("Content-Type", "application/zip")
			_, _ = w.Write(archiveBytes)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	sysSvc.SetGitHubURLs(srv.URL, srv.URL)

	// 1. Проверка канала "go" — ожидается новое обновление по коммиту
	checkRes := sysSvc.CheckAutoUpdate("go", "owner/repo", true, "")
	if !checkRes.Success {
		t.Fatalf("CheckAutoUpdate(go) failed: %s", checkRes.Error)
	}
	if requestedBranch != "go" {
		t.Fatalf("expected sha=go query param, got %q", requestedBranch)
	}
	if !checkRes.HasUpdate || checkRes.Commit != newCommit {
		t.Fatalf("unexpected CheckAutoUpdate result: %+v", checkRes)
	}
	if !strings.Contains(checkRes.ArchiveURL, "/owner/repo/archive/refs/heads/go.zip") {
		t.Fatalf("unexpected go archive URL: %q", checkRes.ArchiveURL)
	}
	if len(checkRes.Changelog) == 0 || !strings.Contains(checkRes.Changelog[0], "[2222222]") {
		t.Fatalf("expected commit changelog entry, got: %#v", checkRes.Changelog)
	}

	// 2. Подготовка и применение обновления
	meta, err := sysSvc.PrepareAutoUpdate(UpdatePrepareRequest{
		Channel:    "go",
		Repo:       "owner/repo",
		ArchiveURL: checkRes.ArchiveURL,
		Commit:     checkRes.Commit,
		Changelog:  checkRes.Changelog,
	})
	if err != nil {
		t.Fatalf("PrepareAutoUpdate failed: %v", err)
	}
	if meta.TotalFiles == 0 || !meta.HasBinaryUpdate {
		t.Fatalf("unexpected StagedUpdateMeta: %+v", meta)
	}

	applyRes, err := sysSvc.ApplyUpdate(meta.Token)
	if err != nil {
		t.Fatalf("ApplyUpdate failed: %v", err)
	}
	if !applyRes.Success || !applyRes.RestartRequired || applyRes.BackupFile == "" {
		t.Fatalf("unexpected ApplyUpdate result: %+v", applyRes)
	}

	// Проверка создания системного бэкапа
	backupPath := filepath.Join(tmpDir, "editor_backup", applyRes.BackupFile)
	if _, err := os.Stat(backupPath); err != nil {
		t.Fatalf("expected system backup file %s: %v", backupPath, err)
	}

	// Проверка обновления системных файлов и бинарников
	if got, _ := os.ReadFile(filepath.Join(tmpDir, "web", "templates", "editor.html")); string(got) != "<html>new-go-ui</html>" {
		t.Fatalf("editor.html not updated, got %q", string(got))
	}
	if runtime.GOOS == "windows" {
		if got, _ := os.ReadFile(filepath.Join(tmpDir, "npblog.exe")); string(got) != "NEW_WIN_BIN" {
			t.Fatalf("root npblog.exe not updated, got %q", string(got))
		}
	} else {
		if got, _ := os.ReadFile(filepath.Join(tmpDir, "npblog")); string(got) != "NEW_LINUX_BIN" {
			t.Fatalf("root npblog not updated, got %q", string(got))
		}
	}

	// Проверка сохранности пользовательских данных и конфигов
	assertContent := func(rel, want string) {
		t.Helper()
		b, err := os.ReadFile(filepath.Join(tmpDir, filepath.FromSlash(rel)))
		if err != nil {
			t.Fatalf("missing protected file %s: %v", rel, err)
		}
		if string(b) != want {
			t.Fatalf("protected file %s was mutated: got %q, want %q", rel, string(b), want)
		}
	}
	assertContent("editor_settings.json", `{"blogPaths":["data","data_custom"],"updateChannel":"go"}`)
	assertContent("sessions.json", `{"secret-session":"token-value"}`)
	assertContent("data/blog/posts/post1.json", `{"id":"post1","title":"User Post"}`)
	assertContent("data_custom/blog/posts/post2.json", `{"id":"post2","title":"Alt Blog Post"}`)
	assertContent("old_back/index.php", `<?php echo 'legacy';`)

	// Проверка version.json (сохранён коммит и канал "go")
	vRaw, err := os.ReadFile(filepath.Join(tmpDir, "version.json"))
	if err != nil {
		t.Fatalf("read version.json: %v", err)
	}
	var vd VersionData
	if err := json.Unmarshal(vRaw, &vd); err != nil {
		t.Fatalf("unmarshal version.json: %v", err)
	}
	if vd.Commit != newCommit || vd.Channel != "go" {
		t.Fatalf("unexpected version.json after update: %+v", vd)
	}

	// 3. Повторная проверка по каналу "go": коммит уже совпадает -> HasUpdate == false
	recheck := sysSvc.CheckAutoUpdate("go", "owner/repo", true, "")
	if !recheck.Success {
		t.Fatalf("recheck failed: %s", recheck.Error)
	}
	if recheck.HasUpdate {
		t.Fatalf("expected HasUpdate=false when commit matches, got: %+v", recheck)
	}
}

func TestPreviewUploadedUpdateZipRejectsZipSlip(t *testing.T) {
	_, sysSvc := newTestSystemEnv(t)

	evilZip := buildTestUpdateZip(t, map[string]string{
		"../evil_escape.php": "<?php phpinfo();",
		"version.json":       `{"version":"9.9.9"}`,
	})

	if _, err := sysSvc.PreviewUploadedUpdateZip(bytes.NewReader(evilZip), int64(len(evilZip))); err == nil {
		t.Fatal("expected Zip-Slip archive to be rejected by PreviewUploadedUpdateZip")
	}
}
