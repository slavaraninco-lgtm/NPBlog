package legacy_test

import (
	"encoding/json"
	"golang.org/x/crypto/bcrypt"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"npblog/internal/config"
	"npblog/internal/middleware"
	"npblog/internal/service"
)

func TestPasswordSettingsHashAndVerify(t *testing.T) {
	h, root := setupTestHandler(t)
	t.Cleanup(func() { os.RemoveAll(root) })
	save := func(body string) bool {
		w := httptest.NewRecorder()
		h.HandleSaveEditorSettings(w, httptest.NewRequest("POST", "/save_editor_settings.php", strings.NewReader(body)))
		var result map[string]interface{}
		if err := json.Unmarshal(w.Body.Bytes(), &result); err != nil {
			t.Fatal(err)
		}
		return result["success"] == true
	}
	if !save(`{"password_enabled":true,"new_password":"test-password"}`) {
		t.Fatal("enable failed")
	}
	content, _ := os.ReadFile(filepath.Join(root, "editor_settings.json"))
	var settings map[string]interface{}
	json.Unmarshal(content, &settings)
	if strings.Contains(string(content), "test-password") || bcrypt.CompareHashAndPassword([]byte(settings["password_hash"].(string)), []byte("test-password")) != nil {
		t.Fatal("password not hashed")
	}
	if save(`{"password_enabled":false,"old_password":"wrong"}`) {
		t.Fatal("wrong password accepted")
	}
	if !save(`{"password_enabled":false,"old_password":"test-password"}`) {
		t.Fatal("disable failed")
	}
}

func TestCreateBlogAndServeVideo(t *testing.T) {
	h, root := setupTestHandler(t)
	t.Cleanup(func() { os.RemoveAll(root) })
	for _, rel := range []string{"blog/assets/style.css", "blog/templates/NPBlog/main.html", "blog/template_post.html"} {
		dest := filepath.Join(root, "data", filepath.FromSlash(rel))
		if err := os.MkdirAll(filepath.Dir(dest), 0755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(dest, []byte("template"), 0600); err != nil {
			t.Fatal(err)
		}
	}
	w := httptest.NewRecorder()
	h.HandleCreateBlog(w, httptest.NewRequest("POST", "/create_blog.php", strings.NewReader(`{"title":"New","folder":"data_new","make_active":true}`)))
	var result map[string]interface{}
	json.Unmarshal(w.Body.Bytes(), &result)
	if result["success"] != true || result["path"] != "data_new" || result["title"] != "New" || result["make_active"] != true {
		t.Fatalf("bad response: %s", w.Body.String())
	}
	video := filepath.Join(root, "data_new", "files", "videos", "sample.mp4")
	os.MkdirAll(filepath.Dir(video), 0755)
	os.WriteFile(video, []byte("0123456789"), 0600)
	req := httptest.NewRequest("GET", "/data_new/files/videos/sample.mp4", nil)
	req.Header.Set("Range", "bytes=0-3")
	w = httptest.NewRecorder()
	h.HandleBlogFile(w, req)
	if w.Code != 206 || w.Body.String() != "0123" || !strings.HasPrefix(w.Header().Get("Content-Type"), "video/mp4") {
		t.Fatalf("video response: %d %v", w.Code, w.Header())
	}
	w = httptest.NewRecorder()
	h.HandleCreateBlog(w, httptest.NewRequest("POST", "/create_blog.php", strings.NewReader(`{"folder":"data_new"}`)))
	json.Unmarshal(w.Body.Bytes(), &result)
	if result["success"] == true {
		t.Fatal("existing directory accepted")
	}
	if _, err := os.Stat(video); err != nil {
		t.Fatal("existing data lost")
	}
}

func TestEnablingRSSGeneratesFeedImmediately(t *testing.T) {
	h, root := setupTestHandler(t)
	defer os.RemoveAll(root)

	blogDir := filepath.Join(root, "data", "blog")
	if err := os.MkdirAll(blogDir, 0755); err != nil {
		t.Fatal(err)
	}
	posts := `[{"id":1,"title":"Существующая статья","date":"01.11.2026 10:00","filename":"post-1.html"}]`
	if err := os.WriteFile(filepath.Join(blogDir, "posts-meta.json"), []byte(posts), 0644); err != nil {
		t.Fatal(err)
	}

	body := `{"rss_enabled":true,"rss_base_url":"https://example.com","rss_title":"RSS после включения","rss_description":"Описание"}`
	req := httptest.NewRequest("POST", "/save_editor_settings.php", strings.NewReader(body))
	rec := httptest.NewRecorder()
	h.HandleSaveEditorSettings(rec, req)

	var response map[string]interface{}
	if err := json.Unmarshal(rec.Body.Bytes(), &response); err != nil {
		t.Fatal(err)
	}
	if response["success"] != true {
		t.Fatalf("RSS settings were not saved: %s", rec.Body.String())
	}
	feed, err := os.ReadFile(filepath.Join(root, "data", "feed.xml"))
	if err != nil {
		t.Fatalf("feed.xml was not generated after enabling RSS: %v", err)
	}
	if !strings.Contains(string(feed), "Существующая статья") || !strings.Contains(string(feed), "RSS после включения") {
		t.Fatalf("Generated feed does not contain existing post or new settings: %s", string(feed))
	}
}

func TestMultiBlogPathReconciliationAndCustomFonts(t *testing.T) {
	h, root := setupTestHandler(t)
	t.Cleanup(func() { os.RemoveAll(root) })

	// Create custom font in primary blog (data) and another font in secondary blog (data_alt)
	dataFontsDir := filepath.Join(root, "data", "fonts")
	altFontsDir := filepath.Join(root, "data_alt", "fonts")
	if err := os.MkdirAll(dataFontsDir, 0755); err != nil {
		t.Fatal(err)
	}
	if err := os.MkdirAll(altFontsDir, 0755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(dataFontsDir, "MainFont.ttf"), []byte("ttf"), 0644); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(altFontsDir, "AltFont.woff2"), []byte("woff2"), 0644); err != nil {
		t.Fatal(err)
	}

	// Simulate orphaned active_blog_path pointing to removed "data2" while blog_paths only keeps abs "data"
	absData := filepath.Join(root, "data")
	absData2 := filepath.Join(root, "data2")
	orphaned := map[string]interface{}{
		"blog_paths":       []string{absData},
		"active_blog_path": absData2,
	}
	rawOrphaned, _ := json.Marshal(orphaned)
	if err := os.WriteFile(filepath.Join(root, "editor_settings.json"), rawOrphaned, 0644); err != nil {
		t.Fatal(err)
	}

	cfg := &config.AppConfig{BaseDir: root, AssetsDir: root}
	cfg.ReloadSettings()
	storage := service.NewStorageService(cfg)
	auth := service.NewAuthService(cfg, storage)
	mw := middleware.NewMiddlewareManager(cfg, auth, storage)

	// 1. Even with stale cookie pointing to removed data2, BlogContext must resolve to effective active blog (data)
	req := httptest.NewRequest(http.MethodGet, "/get_custom_fonts.php", nil)
	req.AddCookie(&http.Cookie{Name: "active_blog_path", Value: absData2})
	rec := httptest.NewRecorder()
	mw.BlogContext(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		h.HandleGetCustomFonts(w, r)
	})).ServeHTTP(rec, req)

	var fontsResp struct {
		Success bool `json:"success"`
		Fonts   []struct {
			Name string `json:"name"`
		} `json:"fonts"`
	}
	if err := json.Unmarshal(rec.Body.Bytes(), &fontsResp); err != nil {
		t.Fatal(err)
	}
	if !fontsResp.Success || len(fontsResp.Fonts) != 1 || fontsResp.Fonts[0].Name != "MainFont" {
		t.Fatalf("expected MainFont from data after orphaned active_blog_path fallback, got: %s", rec.Body.String())
	}

	// 2. Saving blog_paths via HandleSaveEditorSettings reconciles orphaned active_blog_path to blog_paths[0]
	saveReq := httptest.NewRequest(http.MethodPost, "/save_editor_settings.php", strings.NewReader(`{"blog_paths":[`+jsonQuote(absData)+`,"data_alt"]}`))
	saveRec := httptest.NewRecorder()
	h.HandleSaveEditorSettings(saveRec, saveReq)

	var saveResp map[string]interface{}
	if err := json.Unmarshal(saveRec.Body.Bytes(), &saveResp); err != nil {
		t.Fatal(err)
	}
	if saveResp["success"] != true || !storage.SameBlogPath(saveResp["active_blog_path"].(string), absData) {
		t.Fatalf("expected active_blog_path reconciled to %s, got: %s", absData, saveRec.Body.String())
	}

	// 3. Relative header "data_alt" matches and HandleGetCustomFontsCSS generates /data_alt/fonts/... URL (not hardcoded data/)
	cfg.ReloadSettings()
	cssReq := httptest.NewRequest(http.MethodGet, "/get_custom_fonts_css.php", nil)
	cssReq.Header.Set("X-Blog-Path", "data_alt")
	cssRec := httptest.NewRecorder()
	mw.BlogContext(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		h.HandleGetCustomFontsCSS(w, r)
	})).ServeHTTP(cssRec, cssReq)

	cssBody := cssRec.Body.String()
	if !strings.Contains(cssBody, "AltFont") || !strings.Contains(cssBody, "/data_alt/fonts/AltFont.woff2") {
		t.Fatalf("expected /data_alt/fonts/AltFont.woff2 in custom fonts CSS, got: %s", cssBody)
	}
}

func jsonQuote(s string) string {
	b, _ := json.Marshal(s)
	return string(b)
}
