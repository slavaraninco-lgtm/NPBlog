package legacy

import (
	"bytes"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"npblog/internal/config"
	"npblog/internal/domain"
	"npblog/internal/service"
)

func writeTemplateActionFixture(t *testing.T, root string) {
	t.Helper()
	templatesDir := filepath.Join(root, "data", "blog", "templates")
	mainTemplate := `<html><head>{{META_TAGS}}{{CUSTOM_FONTS}}</head><body {{BODY_STYLE}}><article id="npblog-post-content">MAIN {{TITLE}} {{DATE}} {{POST_ID}} {{CONTENT_WRAPPER_START}}{{CONTENT}}{{CONTENT_WRAPPER_END}}</article></body></html>`
	customTemplate := strings.Replace(mainTemplate, "MAIN", "CUSTOM", 1)
	alternateTemplate := strings.Replace(mainTemplate, "MAIN", "ALTERNATE", 1)
	for name, code := range map[string]string{"NPBlog/main.html": mainTemplate, "custom/custom.html": customTemplate, "alternate/alternate.html": alternateTemplate} {
		path := filepath.Join(templatesDir, filepath.FromSlash(name))
		if err := os.MkdirAll(filepath.Dir(path), 0755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(code), 0600); err != nil {
			t.Fatal(err)
		}
	}
	settings := `{
		"default":"main",
		"post_templates":{"1":"custom"},
		"custom_option":"preserve",
		"templates":{
			"main":{"title":"Main","is_system":true,"path":"NPBlog/main.html"},
			"custom":{"title":"Custom","is_system":false,"path":"custom/custom.html"},
			"alternate":{"title":"Alternate","is_system":false,"path":"alternate/alternate.html"}
		}
	}`
	if err := os.WriteFile(filepath.Join(templatesDir, "settings.json"), []byte(settings), 0600); err != nil {
		t.Fatal(err)
	}
	blogDir := filepath.Join(root, "data", "blog")
	meta := []domain.PostMeta{{ID: 1, Title: "Article", Date: "01.02.2026 10:00", Filename: "post-1.html"}}
	metaBytes, _ := json.Marshal(meta)
	if err := os.WriteFile(filepath.Join(blogDir, "posts-meta.json"), metaBytes, 0600); err != nil {
		t.Fatal(err)
	}
	original := `<html><body><article id="npblog-post-content"><p>Keep this text</p><div>Nested block</div></article></body></html>`
	if err := os.WriteFile(filepath.Join(blogDir, "post-1.html"), []byte(original), 0600); err != nil {
		t.Fatal(err)
	}
}

func callTemplateJSON(t *testing.T, handler func(http.ResponseWriter, *http.Request), path string, payload interface{}) map[string]interface{} {
	t.Helper()
	body, _ := json.Marshal(payload)
	req := httptest.NewRequest(http.MethodPost, path, bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	recorder := httptest.NewRecorder()
	handler(recorder, req)
	if recorder.Code != http.StatusOK {
		t.Fatalf("%s returned %d: %s", path, recorder.Code, recorder.Body.String())
	}
	var response map[string]interface{}
	if err := json.Unmarshal(recorder.Body.Bytes(), &response); err != nil {
		t.Fatalf("%s returned non-JSON response: %v", path, err)
	}
	return response
}

func TestTemplateSaveApplyAndDeleteEndpoints(t *testing.T) {
	root := t.TempDir()
	writeTemplateActionFixture(t, root)
	cfg := &config.AppConfig{BaseDir: root}
	storage := service.NewStorageService(cfg)
	templates := service.NewTemplateService(cfg, storage)
	posts := service.NewPostService(cfg, storage)
	handler := &LegacyHandler{storage: storage, template: templates, post: posts}

	validCode := `<title>{{TITLE}}</title>{{POST_ID}}{{META_TAGS}}{{CUSTOM_FONTS}}{{BODY_STYLE}}{{CONTENT_WRAPPER_START}}{{DATE}}<div>CUSTOM-SAVED</div><article id="npblog-post-content">{{CONTENT}}</article>{{CONTENT_WRAPPER_END}}`
	response := callTemplateJSON(t, handler.HandleSaveTemplate, "/save_template.php", map[string]interface{}{
		"name": "custom", "title": "Changed", "description": "Updated", "code": validCode,
	})
	if response["success"] != true {
		t.Fatalf("save failed: %#v", response)
	}

	response = callTemplateJSON(t, handler.HandleApplyTemplate, "/apply_template.php", map[string]interface{}{
		"template_name": "custom", "mode": "default",
	})
	if response["success"] != true {
		t.Fatalf("default apply failed: %#v", response)
	}
	postPath := filepath.Join(root, "data", "blog", "post-1.html")
	postHTML, err := os.ReadFile(postPath)
	if err != nil {
		t.Fatal(err)
	}
	if !strings.Contains(string(postHTML), "CUSTOM-SAVED") || !strings.Contains(string(postHTML), "Keep this text") || !strings.Contains(string(postHTML), "Nested block") {
		t.Fatalf("article was not regenerated correctly: %s", postHTML)
	}
	if !strings.Contains(string(postHTML), "01.02.2026 10:00") {
		t.Fatal("article date changed while applying template")
	}

	settingsPath := filepath.Join(root, "data", "blog", "templates", "settings.json")
	settingsBytes, _ := os.ReadFile(settingsPath)
	var settings map[string]interface{}
	_ = json.Unmarshal(settingsBytes, &settings)
	if settings["default"] != "custom" || settings["activeTemplate"] != "custom" || settings["custom_option"] != "preserve" {
		t.Fatalf("catalog settings were not preserved: %s", settingsBytes)
	}
	if len(settings["templates"].(map[string]interface{})) != 3 {
		t.Fatal("applying a template removed catalog entries")
	}

	response = callTemplateJSON(t, handler.HandleApplyTemplate, "/apply_template.php", map[string]interface{}{
		"template_name": "main", "mode": "post", "post_id": 1,
	})
	if response["success"] != true {
		t.Fatalf("post apply failed: %#v", response)
	}
	postHTML, _ = os.ReadFile(postPath)
	if !strings.Contains(string(postHTML), "MAIN") || strings.Contains(string(postHTML), "CUSTOM-SAVED") {
		t.Fatalf("individual template was not applied: %s", postHTML)
	}
	settingsBytes, _ = os.ReadFile(settingsPath)
	_ = json.Unmarshal(settingsBytes, &settings)
	if settings["post_templates"].(map[string]interface{})["1"] != "main" {
		t.Fatal("individual template assignment was not saved")
	}

	if _, err := templates.SetDefaultTemplate("data", "main"); err != nil {
		t.Fatal(err)
	}
	response = callTemplateJSON(t, handler.HandleDeleteTemplate, "/delete_template.php", map[string]string{"name": "custom"})
	if response["success"] != true {
		t.Fatalf("delete failed: %#v", response)
	}
	if _, err := os.Stat(filepath.Join(root, "data", "blog", "templates", "custom")); !os.IsNotExist(err) {
		t.Fatal("custom template files were not deleted")
	}

	response = callTemplateJSON(t, handler.HandleDeleteCustomTemplates, "/delete_custom_templates.php", map[string]string{})
	if response["success"] != true || response["deleted"] != float64(1) {
		t.Fatalf("bulk delete failed: %#v", response)
	}
	if _, err := os.Stat(filepath.Join(root, "data", "blog", "templates", "alternate")); !os.IsNotExist(err) {
		t.Fatal("alternate template files were not deleted")
	}
	if _, err := os.Stat(filepath.Join(root, "data", "blog", "templates", "NPBlog", "main.html")); err != nil {
		t.Fatal("system template was deleted")
	}
	settingsBytes, _ = os.ReadFile(settingsPath)
	_ = json.Unmarshal(settingsBytes, &settings)
	if settings["default"] != "main" || settings["custom_option"] != "preserve" {
		t.Fatal("bulk delete did not restore the safe default or preserve settings")
	}
}
