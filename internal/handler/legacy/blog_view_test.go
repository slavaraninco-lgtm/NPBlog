package legacy_test

import (
	"bytes"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"net/url"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"npblog/internal/config"
	"npblog/internal/handler/legacy"
	"npblog/internal/service"
)

func setupTestHandler(t *testing.T) (*legacy.LegacyHandler, string) {
	tmpDir, err := os.MkdirTemp("", "npblog_test_*")
	if err != nil {
		t.Fatalf("Failed to create temp dir: %v", err)
	}

	dataDir := filepath.Join(tmpDir, "data")
	_ = os.MkdirAll(dataDir, 0755)

	// Create mock blog.html
	blogHTML := `<!DOCTYPE html><html><head><title>Блог - Список статей</title></head><body><header><h1>Блог</h1></header><script>function loadBlogViewSettings(){}</script></body></html>`
	_ = os.WriteFile(filepath.Join(dataDir, "blog.html"), []byte(blogHTML), 0644)

	// Create mock blog-view-settings.json
	initialSettings := `{"title":"Блог","layout":"list"}`
	_ = os.WriteFile(filepath.Join(dataDir, "blog-view-settings.json"), []byte(initialSettings), 0644)

	// Create mock editor_settings.json
	editorSettings := `{"blog_paths":["data"],"active_blog_path":"data"}`
	_ = os.WriteFile(filepath.Join(tmpDir, "editor_settings.json"), []byte(editorSettings), 0644)

	cfg := &config.AppConfig{
		BaseDir:   tmpDir,
		AssetsDir: tmpDir,
	}
	cfg.ReloadSettings()

	storage := service.NewStorageService(cfg)
	auth := service.NewAuthService(cfg, storage)
	post := service.NewPostService(cfg, storage)
	draft := service.NewDraftService(cfg, storage)
	backup := service.NewBackupService(cfg, storage)
	media := service.NewMediaService(cfg, storage)
	tmpl := service.NewTemplateService(cfg, storage)
	integrity := service.NewIntegrityService(cfg, storage)
	ftp := service.NewFtpService(cfg, storage)
	system := service.NewSystemService(cfg, storage)

	handler := legacy.NewLegacyHandler(cfg, auth, storage, post, draft, backup, media, tmpl, integrity, ftp, system)
	return handler, tmpDir
}

func TestHandleSaveBlogViewSettings(t *testing.T) {
	handler, tmpDir := setupTestHandler(t)
	defer os.RemoveAll(tmpDir)

	// 1. Save new title and layout "grid"
	payload := map[string]interface{}{
		"action": "save",
		"title":  "Тестовый Заголовок",
		"layout": "grid",
	}
	bodyBytes, _ := json.Marshal(payload)
	req := httptest.NewRequest(http.MethodPost, "/save_blog_view_settings.php", bytes.NewReader(bodyBytes))
	req.Header.Set("Content-Type", "application/json")
	w := httptest.NewRecorder()

	handler.HandleSaveBlogViewSettings(w, req)
	if w.Code != http.StatusOK {
		t.Fatalf("Expected 200 OK, got %d", w.Code)
	}

	var resp map[string]interface{}
	_ = json.Unmarshal(w.Body.Bytes(), &resp)
	if resp["success"] != true {
		t.Fatalf("Expected success: true, got %v", resp)
	}

	// Verify data/blog-view-settings.json on disk
	settingsPath := filepath.Join(tmpDir, "data", "blog-view-settings.json")
	bytesOnDisk, err := os.ReadFile(settingsPath)
	if err != nil {
		t.Fatalf("Failed to read settings: %v", err)
	}

	var savedSettings map[string]interface{}
	_ = json.Unmarshal(bytesOnDisk, &savedSettings)
	if savedSettings["title"] != "Тестовый Заголовок" {
		t.Errorf("Expected title 'Тестовый Заголовок', got '%v'", savedSettings["title"])
	}
	if savedSettings["layout"] != "grid" {
		t.Errorf("Expected layout 'grid', got '%v'", savedSettings["layout"])
	}

	// Verify blog.html static title updated
	blogHTMLBytes, _ := os.ReadFile(filepath.Join(tmpDir, "data", "blog.html"))
	blogHTML := string(blogHTMLBytes)
	if !strings.Contains(blogHTML, "<title>Тестовый Заголовок - Список статей</title>") {
		t.Errorf("blog.html <title> was not updated: %s", blogHTML)
	}
	if !strings.Contains(blogHTML, "<h1>Тестовый Заголовок</h1>") {
		t.Errorf("blog.html <h1> was not updated: %s", blogHTML)
	}

	// 2. Change layout only (preserves title)
	payload2 := map[string]interface{}{
		"action": "save",
		"layout": "list",
	}
	bodyBytes2, _ := json.Marshal(payload2)
	req2 := httptest.NewRequest(http.MethodPost, "/save_blog_view_settings.php", bytes.NewReader(bodyBytes2))
	req2.Header.Set("Content-Type", "application/json")
	w2 := httptest.NewRecorder()

	handler.HandleSaveBlogViewSettings(w2, req2)
	bytesOnDisk2, _ := os.ReadFile(settingsPath)
	var savedSettings2 map[string]interface{}
	_ = json.Unmarshal(bytesOnDisk2, &savedSettings2)
	if savedSettings2["layout"] != "list" {
		t.Errorf("Expected layout 'list', got '%v'", savedSettings2["layout"])
	}
	if savedSettings2["title"] != "Тестовый Заголовок" {
		t.Errorf("Title should have been preserved, got '%v'", savedSettings2["title"])
	}

	// 3. Test apply_all
	payload3 := map[string]interface{}{
		"action": "apply_all",
		"layout": "grid",
	}
	bodyBytes3, _ := json.Marshal(payload3)
	req3 := httptest.NewRequest(http.MethodPost, "/save_blog_view_settings.php", bytes.NewReader(bodyBytes3))
	req3.Header.Set("Content-Type", "application/json")
	w3 := httptest.NewRecorder()

	handler.HandleSaveBlogViewSettings(w3, req3)
	var resp3 map[string]interface{}
	_ = json.Unmarshal(w3.Body.Bytes(), &resp3)
	if resp3["success"] != true || resp3["updated_count"].(float64) < 1 {
		t.Errorf("Expected apply_all success and updated_count >= 1, got %v", resp3)
	}
}

func TestHandleSaveBlogNav(t *testing.T) {
	handler, tmpDir := setupTestHandler(t)
	defer os.RemoveAll(tmpDir)

	// 1. Test check action via form-urlencoded
	form := url.Values{}
	form.Set("action", "check")
	req := httptest.NewRequest(http.MethodPost, "/save_blog_nav.php", strings.NewReader(form.Encode()))
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	w := httptest.NewRecorder()

	handler.HandleSaveBlogNav(w, req)
	var checkResp map[string]interface{}
	_ = json.Unmarshal(w.Body.Bytes(), &checkResp)
	if checkResp["is_standard"] != true {
		t.Errorf("Expected is_standard: true, got %v", checkResp)
	}

	// 2. Test save navigation buttons
	savePayload := map[string]interface{}{
		"action": "save",
		"buttons": []map[string]interface{}{
			{"text": "Главный блог", "url": "/data/blog.html"},
		},
	}
	bodyBytes, _ := json.Marshal(savePayload)
	req2 := httptest.NewRequest(http.MethodPost, "/save_blog_nav.php", bytes.NewReader(bodyBytes))
	req2.Header.Set("Content-Type", "application/json")
	w2 := httptest.NewRecorder()

	handler.HandleSaveBlogNav(w2, req2)
	var saveResp map[string]interface{}
	_ = json.Unmarshal(w2.Body.Bytes(), &saveResp)
	if saveResp["success"] != true {
		t.Errorf("Expected success: true, got %v", saveResp)
	}

	// Verify on disk
	settingsPath := filepath.Join(tmpDir, "data", "blog-view-settings.json")
	bytesOnDisk, _ := os.ReadFile(settingsPath)
	var savedSettings map[string]interface{}
	_ = json.Unmarshal(bytesOnDisk, &savedSettings)
	navButtons, ok := savedSettings["crossBlogNav"].([]interface{})
	if !ok || len(navButtons) != 1 {
		t.Fatalf("Expected 1 nav button saved, got %v", savedSettings["crossBlogNav"])
	}
}

func TestHandleSaveEditorSettingsBlogTitle(t *testing.T) {
	handler, tmpDir := setupTestHandler(t)
	defer os.RemoveAll(tmpDir)

	payload := map[string]interface{}{
		"blog_title": "Заголовок из Общих Настроек",
	}
	bodyBytes, _ := json.Marshal(payload)
	req := httptest.NewRequest(http.MethodPost, "/save_editor_settings.php", bytes.NewReader(bodyBytes))
	req.Header.Set("Content-Type", "application/json")
	w := httptest.NewRecorder()

	handler.HandleSaveEditorSettings(w, req)
	if w.Code != http.StatusOK {
		t.Fatalf("Expected 200 OK, got %d", w.Code)
	}

	settingsPath := filepath.Join(tmpDir, "data", "blog-view-settings.json")
	bytesOnDisk, _ := os.ReadFile(settingsPath)
	var savedSettings map[string]interface{}
	_ = json.Unmarshal(bytesOnDisk, &savedSettings)
	if savedSettings["title"] != "Заголовок из Общих Настроек" {
		t.Errorf("Expected title 'Заголовок из Общих Настроек', got '%v'", savedSettings["title"])
	}
}

func TestHandleBlogBackground(t *testing.T) {
	handler, tmpDir := setupTestHandler(t)
	defer os.RemoveAll(tmpDir)

	// Remove blog background
	req := httptest.NewRequest(http.MethodPost, "/remove_blog_background.php", nil)
	w := httptest.NewRecorder()
	handler.HandleRemoveBlogBackground(w, req)
	if w.Code != http.StatusOK {
		t.Fatalf("Expected 200 OK, got %d", w.Code)
	}

	settingsPath := filepath.Join(tmpDir, "data", "blog-view-settings.json")
	bytesOnDisk, _ := os.ReadFile(settingsPath)
	var savedSettings map[string]interface{}
	_ = json.Unmarshal(bytesOnDisk, &savedSettings)
	if _, exists := savedSettings["background"]; exists {
		t.Errorf("Expected background to be removed")
	}
}
