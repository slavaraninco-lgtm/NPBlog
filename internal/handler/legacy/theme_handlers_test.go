package legacy_test

import (
	"bytes"
	"encoding/json"
	"mime/multipart"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"npblog/internal/assets"
	"npblog/internal/handler/legacy"
)

func TestCustomThemeUploadAndDownload(t *testing.T) {
	handler, root := setupTestHandler(t)
	defer os.RemoveAll(root)

	css := ":root { --bg-color: #123456; }"
	var body bytes.Buffer
	writer := multipart.NewWriter(&body)
	part, err := writer.CreateFormFile("themeFile", "theme.css")
	if err != nil {
		t.Fatal(err)
	}
	if _, err := part.Write([]byte(css)); err != nil {
		t.Fatal(err)
	}
	if err := writer.Close(); err != nil {
		t.Fatal(err)
	}

	req := httptest.NewRequest(http.MethodPost, "/upload_custom_theme.php", &body)
	req.Header.Set("Content-Type", writer.FormDataContentType())
	rec := httptest.NewRecorder()
	handler.HandleUploadCustomTheme(rec, req)

	var response map[string]interface{}
	if err := json.Unmarshal(rec.Body.Bytes(), &response); err != nil {
		t.Fatal(err)
	}
	if response["success"] != true || response["cssContent"] != css {
		t.Fatalf("Unexpected upload response: %s", rec.Body.String())
	}
	saved, err := os.ReadFile(filepath.Join(root, "data", "custom_editor_theme.css"))
	if err != nil || string(saved) != css {
		t.Fatalf("Saved theme mismatch: %q, err=%v", string(saved), err)
	}

	standardCSS := "body { color: red; }"
	if err := os.WriteFile(filepath.Join(root, "editor-style.css"), []byte(standardCSS), 0644); err != nil {
		t.Fatal(err)
	}
	downloadRec := httptest.NewRecorder()
	downloadReq := httptest.NewRequest(http.MethodGet, "/download_theme.php", nil)
	legacy.HandleDownloadTheme(assets.NewHybridFS(root, nil)).ServeHTTP(downloadRec, downloadReq)
	if downloadRec.Code != http.StatusOK || downloadRec.Body.String() != standardCSS {
		t.Fatalf("Unexpected download response: %d %q", downloadRec.Code, downloadRec.Body.String())
	}
	if disposition := downloadRec.Header().Get("Content-Disposition"); !strings.Contains(disposition, "editor-style-template.css") {
		t.Fatalf("Missing download filename: %q", disposition)
	}
}

func TestCustomThemeRejectsNonCSSWithoutOverwriting(t *testing.T) {
	handler, root := setupTestHandler(t)
	defer os.RemoveAll(root)

	themePath := filepath.Join(root, "data", "custom_editor_theme.css")
	if err := os.WriteFile(themePath, []byte("original"), 0644); err != nil {
		t.Fatal(err)
	}

	var body bytes.Buffer
	writer := multipart.NewWriter(&body)
	part, err := writer.CreateFormFile("themeFile", "theme.php")
	if err != nil {
		t.Fatal(err)
	}
	_, _ = part.Write([]byte("<?php echo 1; ?>"))
	_ = writer.Close()

	req := httptest.NewRequest(http.MethodPost, "/upload_custom_theme.php", &body)
	req.Header.Set("Content-Type", writer.FormDataContentType())
	rec := httptest.NewRecorder()
	handler.HandleUploadCustomTheme(rec, req)

	var response map[string]interface{}
	_ = json.Unmarshal(rec.Body.Bytes(), &response)
	if response["success"] == true {
		t.Fatalf("Non-CSS upload accepted: %s", rec.Body.String())
	}
	saved, err := os.ReadFile(themePath)
	if err != nil || string(saved) != "original" {
		t.Fatalf("Existing theme changed after rejected upload: %q, err=%v", string(saved), err)
	}
}
