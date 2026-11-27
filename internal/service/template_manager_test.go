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

func TestTemplateManagerInitializesMissingCatalogWithoutOverwritingCorrupt(t *testing.T) {
	root := t.TempDir()
	cfg := &config.AppConfig{BaseDir: root}
	svc := NewTemplateService(cfg, NewStorageService(cfg))

	// Empty blog folder without settings.json should auto-initialize the default template catalog.
	data, err := svc.GetTemplateManagerData(root)
	if err != nil {
		t.Fatalf("expected missing catalog to auto-initialize, got error: %v", err)
	}
	items := data["templates"].([]map[string]interface{})
	if len(items) != 1 || items[0]["name"] != "main" || data["default"] != "main" {
		t.Fatalf("unexpected initialized templates: %#v", data)
	}
	if _, statErr := os.Stat(filepath.Join(root, "blog", "templates", "NPBlog", "main.html")); statErr != nil {
		t.Fatalf("expected default main.html to be created: %v", statErr)
	}

	// Corrupt settings.json must never be overwritten or repaired silently.
	settingsPath := filepath.Join(root, "blog", "templates", "settings.json")
	corrupt := []byte("{not-valid-json")
	if err := os.WriteFile(settingsPath, corrupt, 0600); err != nil {
		t.Fatal(err)
	}
	if _, err := svc.GetTemplateManagerData(root); err == nil {
		t.Fatal("expected corrupt settings.json to fail")
	}
	after, err := os.ReadFile(settingsPath)
	if err != nil || string(after) != string(corrupt) {
		t.Fatal("corrupt settings.json was modified")
	}
}
