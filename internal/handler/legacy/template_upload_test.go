package legacy

import (
	"bytes"
	"encoding/json"
	"mime/multipart"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"

	"npblog/internal/config"
	"npblog/internal/service"
)

func TestHandleUploadTemplate(t *testing.T) {
	root := t.TempDir()
	cfg := &config.AppConfig{BaseDir: root}
	storage := service.NewStorageService(cfg)
	handler := &LegacyHandler{template: service.NewTemplateService(cfg, storage)}

	var body bytes.Buffer
	writer := multipart.NewWriter(&body)
	part, err := writer.CreateFormFile("template_file", "endpoint.html")
	if err != nil {
		t.Fatal(err)
	}
	template := `<title>{{TITLE}}</title>{{POST_ID}}{{META_TAGS}}{{CUSTOM_FONTS}}{{BODY_STYLE}}{{CONTENT_WRAPPER_START}}{{DATE}}{{CONTENT}}{{CONTENT_WRAPPER_END}}`
	if _, err := part.Write([]byte(template)); err != nil {
		t.Fatal(err)
	}
	if err := writer.Close(); err != nil {
		t.Fatal(err)
	}

	req := httptest.NewRequest(http.MethodPost, "/upload_template.php", &body)
	req.Header.Set("Content-Type", writer.FormDataContentType())
	recorder := httptest.NewRecorder()
	handler.HandleUploadTemplate(recorder, req)

	if recorder.Code != http.StatusOK {
		t.Fatalf("unexpected status %d: %s", recorder.Code, recorder.Body.String())
	}
	var response struct {
		Success bool   `json:"success"`
		Name    string `json:"name"`
		Error   string `json:"error"`
	}
	if err := json.Unmarshal(recorder.Body.Bytes(), &response); err != nil {
		t.Fatalf("response is not JSON: %v", err)
	}
	if !response.Success || response.Name != "endpoint" || response.Error != "" {
		t.Fatalf("unexpected response: %+v", response)
	}
	if _, err := os.Stat(filepath.Join(root, "data", "blog", "templates", "endpoint", "endpoint.html")); err != nil {
		t.Fatalf("uploaded template is missing: %v", err)
	}
}
