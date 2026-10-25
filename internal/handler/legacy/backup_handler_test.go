package legacy_test

import (
	"bytes"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"

	"npblog/internal/config"
	"npblog/internal/domain"
	"npblog/internal/handler/legacy"
	"npblog/internal/service"
)

func TestHandleGetBackupContent(t *testing.T) {
	tempDir, err := os.MkdirTemp("", "npblog_backup_handler_test_*")
	if err != nil {
		t.Fatalf("failed to create temp dir: %v", err)
	}
	defer os.RemoveAll(tempDir)

	cfg := &config.AppConfig{
		BaseDir:   tempDir,
		AssetsDir: tempDir,
	}
	cfg.ReloadSettings()

	storage := service.NewStorageService(cfg)
	authSvc := service.NewAuthService(cfg, storage)
	postSvc := service.NewPostService(cfg, storage)
	draftSvc := service.NewDraftService(cfg, storage)
	backupSvc := service.NewBackupService(cfg, storage)
	mediaSvc := service.NewMediaService(cfg, storage)
	tplSvc := service.NewTemplateService(cfg, storage)
	integritySvc := service.NewIntegrityService(cfg, storage)
	ftpSvc := service.NewFtpService(cfg, storage)
	systemSvc := service.NewSystemService(cfg, storage)

	handler := legacy.NewLegacyHandler(
		cfg, authSvc, storage, postSvc, draftSvc, backupSvc, mediaSvc, tplSvc, integritySvc, ftpSvc, systemSvc,
	)

	post1Dir := storage.GetBackupPath("1")
	_ = os.MkdirAll(post1Dir, 0755)
	testHTML := "<article>Резервная копия статьи #1</article>"
	_ = os.WriteFile(filepath.Join(post1Dir, "1-1.html"), []byte(testHTML), 0644)

	meta := domain.BackupMeta{
		"1": domain.PostBackupInfo{
			PostID:    1,
			PostTitle: "Статья 1",
			Backups: []domain.BackupEntry{
				{
					BackupNumber: 1,
					Filename:     "1-1.html",
					Date:         "25.10.2026 01:00",
					Title:        "Статья 1",
				},
			},
		},
	}
	_ = storage.SafeWriteJSON(storage.GetBackupPath("backup-meta.json"), meta)

	// Тест 1: POST запрос с JSON телом { "postId": "1", "filename": "1-1.html" } (как в браузере)
	bodyBytes, _ := json.Marshal(map[string]interface{}{
		"postId":   "1",
		"filename": "1-1.html",
	})
	req := httptest.NewRequest(http.MethodPost, "/get_backup_content.php", bytes.NewReader(bodyBytes))
	req.Header.Set("Content-Type", "application/json")
	rec := httptest.NewRecorder()

	handler.HandleGetBackupContent(rec, req)

	if rec.Code != http.StatusOK {
		t.Fatalf("expected 200, got %d: %s", rec.Code, rec.Body.String())
	}

	var resp map[string]interface{}
	if err := json.Unmarshal(rec.Body.Bytes(), &resp); err != nil {
		t.Fatalf("failed to decode response: %v", err)
	}

	if resp["success"] != true {
		t.Fatalf("expected success true, got %v (error: %v)", resp["success"], resp["error"])
	}
	if resp["content"] != testHTML {
		t.Errorf("content mismatch: got %q, want %q", resp["content"], testHTML)
	}

	// Тест 2: GET запрос с параметрами postId=1&backupNumber=1
	reqGet := httptest.NewRequest(http.MethodGet, "/get_backup_content.php?postId=1&backupNumber=1", nil)
	recGet := httptest.NewRecorder()
	handler.HandleGetBackupContent(recGet, reqGet)

	if recGet.Code != http.StatusOK {
		t.Fatalf("expected 200 for GET, got %d: %s", recGet.Code, recGet.Body.String())
	}
	var respGet map[string]interface{}
	_ = json.Unmarshal(recGet.Body.Bytes(), &respGet)
	if respGet["success"] != true {
		t.Fatalf("expected success true for GET, got %v", respGet["success"])
	}

	// Тест 3: GET /view_backup.php?postId=1&filename=1-1.html&blog=data
	reqView := httptest.NewRequest(http.MethodGet, "/view_backup.php?postId=1&filename=1-1.html&blog=data", nil)
	recView := httptest.NewRecorder()
	handler.HandleViewBackup(recView, reqView)

	if recView.Code != http.StatusOK {
		t.Fatalf("expected 200 for view_backup, got %d: %s", recView.Code, recView.Body.String())
	}
	viewBody := recView.Body.String()
	if !bytes.Contains([]byte(viewBody), []byte("<base href=\"/data/blog/\">")) {
		t.Errorf("expected viewBody to contain base href /data/blog/, got: %s", viewBody)
	}
}
