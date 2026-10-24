package service

import (
	"os"
	"path/filepath"
	"testing"

	"npblog/internal/config"
)

func TestTemplateManagerReadsExistingCatalog(t *testing.T) {
	root := t.TempDir()
	dir := filepath.Join(root, "blog", "templates")
	if err := os.MkdirAll(filepath.Join(dir, "NPBlog"), 0755); err != nil {
		t.Fatal(err)
	}
	catalog := []byte(`{"default":"main","post_templates":{"1":"main"},"templates":{"main":{"title":"Standard","is_system":true,"path":"NPBlog/main.html"}}}`)
	if err := os.WriteFile(filepath.Join(dir, "settings.json"), catalog, 0600); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(dir, "NPBlog", "main.html"), []byte("<h1>{{TITLE}}</h1>{{CONTENT}}"), 0600); err != nil {
		t.Fatal(err)
	}
	cfg := &config.AppConfig{BaseDir: root}
	svc := NewTemplateService(cfg, NewStorageService(cfg))
	data, err := svc.GetTemplateManagerData(root)
	if err != nil {
		t.Fatal(err)
	}
	items := data["templates"].([]map[string]interface{})
	if len(items) != 1 || items[0]["code"] != "<h1>{{TITLE}}</h1>{{CONTENT}}" || data["default"] != "main" {
		t.Fatalf("unexpected response: %#v", data)
	}
	after, err := os.ReadFile(filepath.Join(dir, "settings.json"))
	if err != nil || string(after) != string(catalog) {
		t.Fatal("catalog changed while reading")
	}
	if err := os.WriteFile(filepath.Join(dir, "settings.json"), []byte(`{"templates":{"bad":{"path":"../outside.html"}}}`), 0600); err != nil {
		t.Fatal(err)
	}
	if _, err := svc.GetTemplateManagerData(root); err == nil {
		t.Fatal("unsafe path accepted")
	}
}
