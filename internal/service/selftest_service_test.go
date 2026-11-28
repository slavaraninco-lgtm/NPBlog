package service_test

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"npblog"
	"npblog/internal/assets"
	"npblog/internal/config"
	"npblog/internal/service"
)

func TestAutoCreateEditorSettingsAndSelfTest(t *testing.T) {
	tmpRoot := t.TempDir()

	// 1. Отсутствуют оба файла на диске -> создание из встроенного editor_settings.example.json
	cfg := &config.AppConfig{BaseDir: tmpRoot, AssetsDir: tmpRoot}
	storage := service.NewStorageService(cfg)
	hfs := assets.NewHybridFS(tmpRoot, npblog.EmbeddedAssets)
	st := service.NewSelfTestService(cfg, storage, hfs)

	rep := st.Run()
	if !rep.Passed || !rep.ConfigCreated {
		t.Fatalf("expected initial self-test pass & ConfigCreated=true, got: %+v", rep)
	}
	settingsPath := filepath.Join(tmpRoot, "editor_settings.json")
	if _, err := os.Stat(settingsPath); err != nil {
		t.Fatalf("editor_settings.json was not created: %v", err)
	}

	// 2. Поврежденный editor_settings.json НЕ перезаписывается, селф-тест падает с причиной
	corruptPayload := []byte("{broken json!!!")
	if err := os.WriteFile(settingsPath, corruptPayload, 0644); err != nil {
		t.Fatal(err)
	}
	repCorrupt := st.Run()
	if repCorrupt.Passed {
		t.Fatalf("expected self-test failure on corrupt editor_settings.json")
	}
	afterCorrupt, _ := os.ReadFile(settingsPath)
	if string(afterCorrupt) != string(corruptPayload) {
		t.Fatalf("corrupt editor_settings.json was overwritten: %q", string(afterCorrupt))
	}
	if !strings.Contains(st.FormatConsoleReport(repCorrupt), "СЕЛФ-ТЕСТ НЕ ПРОЙДЕН") {
		t.Fatalf("console report missing failure header")
	}
	if !strings.Contains(st.RenderBrowserErrorHTML(repCorrupt), "Селф-тест бэкэнда NPBlog не пройден") {
		t.Fatalf("browser HTML missing failure title")
	}

	// 3. Read-only editor_settings.json -> селф-тест фиксирует отсутствие прав на запись
	if err := os.WriteFile(settingsPath, []byte(`{"language":"ru"}`), 0644); err != nil {
		t.Fatal(err)
	}
	if err := os.Chmod(settingsPath, 0444); err != nil {
		t.Fatal(err)
	}
	defer func() { _ = os.Chmod(settingsPath, 0644) }()

	repRO := st.Run()
	if repRO.Passed {
		t.Fatalf("expected self-test failure when editor_settings.json is read-only")
	}
	if !strings.Contains(strings.ToLower(repRO.Summary), "запис") {
		t.Fatalf("expected write permission explanation, got: %s", repRO.Summary)
	}

	// 4. Возврат прав -> селф-тест восстанавливается
	if err := os.Chmod(settingsPath, 0644); err != nil {
		t.Fatal(err)
	}
	repRecovered := st.Run()
	if !repRecovered.Passed {
		t.Fatalf("expected self-test recovery after restoring permissions, got: %s", repRecovered.Summary)
	}
}
