package legacy_test

import (
	"bytes"
	"encoding/json"
	"mime/multipart"
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

func TestPostBackgroundHandlers_EndToEnd(t *testing.T) {
	handler, tmpDir := setupTestHandler(t)
	defer os.RemoveAll(tmpDir)

	blogDir := filepath.Join(tmpDir, "data", "blog")
	_ = os.MkdirAll(blogDir, 0755)

	// Create post 1 HTML
	postHTML := `<!DOCTYPE html><html><head><title>Статья</title></head><body style="max-width: 920px;"><button class="theme-toggle">Тема</button><h1>Статья</h1><div class="date">25.10.2026</div><article id="npblog-post-content" class="content"><p>Тело</p></article><a href="../blog.html" class="back-link">Назад</a></body></html>`
	_ = os.WriteFile(filepath.Join(blogDir, "post-1.html"), []byte(postHTML), 0644)
	meta := `[{"id":1,"title":"Статья","filename":"post-1.html"}]`
	_ = os.WriteFile(filepath.Join(blogDir, "posts-meta.json"), []byte(meta), 0644)

	// 1. Upload background for post 1
	var body bytes.Buffer
	writer := multipart.NewWriter(&body)
	_ = writer.WriteField("postId", "1")
	_ = writer.WriteField("mode", "cover")
	_ = writer.WriteField("scope", "content")
	part, _ := writer.CreateFormFile("background", "wallpaper.png")
	_, _ = part.Write([]byte("fake_image_bytes"))
	_ = writer.Close()

	req := httptest.NewRequest(http.MethodPost, "/upload_background.php", &body)
	req.Header.Set("Content-Type", writer.FormDataContentType())
	w := httptest.NewRecorder()
	handler.HandleUploadBackground(w, req)

	if w.Code != http.StatusOK {
		t.Fatalf("HandleUploadBackground failed: %d, body: %s", w.Code, w.Body.String())
	}

	var uploadResp map[string]interface{}
	_ = json.Unmarshal(w.Body.Bytes(), &uploadResp)
	if uploadResp["success"] != true || uploadResp["filename"] != "bg-1.png" {
		t.Errorf("Unexpected upload response: %v", uploadResp)
	}

	// Verify post-1.html has content-wrapper
	postBytes, _ := os.ReadFile(filepath.Join(blogDir, "post-1.html"))
	if !strings.Contains(string(postBytes), "content-wrapper") {
		t.Errorf("Expected content-wrapper in post-1.html: %s", string(postBytes))
	}

	// 2. Get post backgrounds
	reqGet := httptest.NewRequest(http.MethodGet, "/get_post_backgrounds.php?postId=1", nil)
	wGet := httptest.NewRecorder()
	handler.HandleGetPostBackgrounds(wGet, reqGet)

	var getResp struct {
		Success  bool                   `json:"success"`
		Settings map[string]interface{} `json:"settings"`
	}
	_ = json.Unmarshal(wGet.Body.Bytes(), &getResp)
	if !getResp.Success || getResp.Settings["background"] != "bg-1.png" {
		t.Errorf("Expected settings.background == bg-1.png, got: %v", getResp)
	}

	// 3. Save overlay settings
	overlayPayload := map[string]interface{}{
		"postId":         1,
		"overlayEnabled": true,
		"overlayColor":   "#00ff00",
		"overlayOpacity": 75,
	}
	overlayBytes, _ := json.Marshal(overlayPayload)
	reqOverlay := httptest.NewRequest(http.MethodPost, "/save_overlay_settings.php", bytes.NewReader(overlayBytes))
	reqOverlay.Header.Set("Content-Type", "application/json")
	wOverlay := httptest.NewRecorder()
	handler.HandleSaveOverlaySettings(wOverlay, reqOverlay)

	if wOverlay.Code != http.StatusOK {
		t.Fatalf("HandleSaveOverlaySettings failed: %d", wOverlay.Code)
	}

	postBytes, _ = os.ReadFile(filepath.Join(blogDir, "post-1.html"))
	if !strings.Contains(string(postBytes), "overlay-wrapper") {
		t.Errorf("Expected overlay-wrapper in post-1.html: %s", string(postBytes))
	}

	// 4. Remove background
	removePayload := map[string]interface{}{"postId": 1}
	removeBytes, _ := json.Marshal(removePayload)
	reqRemove := httptest.NewRequest(http.MethodPost, "/remove_background.php", bytes.NewReader(removeBytes))
	reqRemove.Header.Set("Content-Type", "application/json")
	wRemove := httptest.NewRecorder()
	handler.HandleRemoveBackground(wRemove, reqRemove)

	if wRemove.Code != http.StatusOK {
		t.Fatalf("HandleRemoveBackground failed: %d", wRemove.Code)
	}

	postBytes, _ = os.ReadFile(filepath.Join(blogDir, "post-1.html"))
	if strings.Contains(string(postBytes), "content-wrapper") {
		t.Errorf("content-wrapper should be removed from post-1.html: %s", string(postBytes))
	}
	if !strings.Contains(string(postBytes), "overlay-wrapper") {
		t.Errorf("overlay-wrapper should remain in post-1.html: %s", string(postBytes))
	}
}
