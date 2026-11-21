package service

import (
	"archive/zip"
	"bytes"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"npblog/internal/config"
)

func validUploadTemplate() []byte {
	return []byte(`<!doctype html><html><head><title>{{TITLE}}</title>{{META_TAGS}}{{CUSTOM_FONTS}}<link href="style.css" rel="stylesheet"></head><body {{BODY_STYLE}}><meta content="{{POST_ID}}">{{CONTENT_WRAPPER_START}}<time>{{DATE}}</time><main>{{CONTENT}}</main>{{CONTENT_WRAPPER_END}}</body></html>`)
}

func makeTemplateZip(t *testing.T, files map[string]string) []byte {
	t.Helper()
	var buffer bytes.Buffer
	writer := zip.NewWriter(&buffer)
	for name, content := range files {
		entry, err := writer.Create(name)
		if err != nil {
			t.Fatal(err)
		}
		if _, err := entry.Write([]byte(content)); err != nil {
			t.Fatal(err)
		}
	}
	if err := writer.Close(); err != nil {
		t.Fatal(err)
	}
	return buffer.Bytes()
}

func TestUploadTemplateHTMLAndZip(t *testing.T) {
	root := t.TempDir()
	cfg := &config.AppConfig{BaseDir: root}
	svc := NewTemplateService(cfg, NewStorageService(cfg))

	name, missing, err := svc.UploadTemplate(root, "My theme.html", validUploadTemplate())
	if err != nil || len(missing) != 0 || name != "Mytheme" {
		t.Fatalf("HTML upload failed: name=%q missing=%v err=%v", name, missing, err)
	}
	htmlPath := filepath.Join(root, "blog", "templates", "Mytheme", "Mytheme.html")
	html, err := os.ReadFile(htmlPath)
	if err != nil {
		t.Fatal(err)
	}
	if !strings.Contains(string(html), `href="templates/Mytheme/style.css"`) {
		t.Fatalf("relative resource was not rewritten: %s", html)
	}

	secondName, _, err := svc.UploadTemplate(root, "My theme.html", validUploadTemplate())
	if err != nil || secondName != "Mytheme_1" {
		t.Fatalf("duplicate name was not made unique: %q, %v", secondName, err)
	}

	archive := makeTemplateZip(t, map[string]string{
		"nested/index.html": string(validUploadTemplate()),
		"nested/style.css":  "body { background: url(background.png) }",
		"nested/image.png":  "image",
	})
	zipName, _, err := svc.UploadTemplate(root, "bundle.zip", archive)
	if err != nil || zipName != "bundle" {
		t.Fatalf("ZIP upload failed: name=%q err=%v", zipName, err)
	}
	zipHTML, err := os.ReadFile(filepath.Join(root, "blog", "templates", "bundle", "nested", "index.html"))
	if err != nil {
		t.Fatal(err)
	}
	if !strings.Contains(string(zipHTML), `href="templates/bundle/nested/style.css"`) {
		t.Fatalf("ZIP resource was not rewritten: %s", zipHTML)
	}
	if _, err := os.Stat(filepath.Join(root, "blog", "templates", "bundle", "nested", "image.png")); err != nil {
		t.Fatalf("ZIP asset was not extracted: %v", err)
	}

	data, err := svc.GetTemplateManagerData(root)
	if err != nil {
		t.Fatal(err)
	}
	items := data["templates"].([]map[string]interface{})
	if len(items) != 3 {
		t.Fatalf("expected three uploaded templates, got %d", len(items))
	}
}

func TestUploadTemplateRejectsUnsafeOrInvalidInput(t *testing.T) {
	root := t.TempDir()
	cfg := &config.AppConfig{BaseDir: root}
	svc := NewTemplateService(cfg, NewStorageService(cfg))

	_, missing, err := svc.UploadTemplate(root, "missing.html", []byte("<h1>{{TITLE}}</h1>"))
	if err == nil || len(missing) == 0 {
		t.Fatal("template without required placeholders was accepted")
	}
	if _, statErr := os.Stat(filepath.Join(root, "blog", "templates", "missing")); !os.IsNotExist(statErr) {
		t.Fatal("invalid template left a directory behind")
	}

	zipSlip := makeTemplateZip(t, map[string]string{
		"index.html":      string(validUploadTemplate()),
		"../outside.html": "unsafe",
	})
	if _, _, err := svc.UploadTemplate(root, "unsafe.zip", zipSlip); err == nil {
		t.Fatal("Zip Slip entry was accepted")
	}
	if _, err := os.Stat(filepath.Join(root, "blog", "outside.html")); !os.IsNotExist(err) {
		t.Fatal("archive escaped the template directory")
	}

	executable := makeTemplateZip(t, map[string]string{
		"index.html": string(validUploadTemplate()),
		"shell.php":  "unsafe",
	})
	if _, _, err := svc.UploadTemplate(root, "executable.zip", executable); err == nil {
		t.Fatal("executable archive entry was accepted")
	}
	if _, _, err := svc.UploadTemplate(root, "document.txt", validUploadTemplate()); err == nil {
		t.Fatal("unsupported extension was accepted")
	}
}
