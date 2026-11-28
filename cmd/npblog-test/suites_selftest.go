package main

import (
	"fmt"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"

	"npblog/internal/app"
	"npblog/internal/config"
)

// runSelfTestSuite verifies automatic creation of editor_settings.json from editor_settings.example.json
// and validates startup self-test console/browser/API reporting and backend blocking on failures.
func runSelfTestSuite(tr *TestRunner) {
	tr.Suite("13. Конфигурация из example и Startup Self-Test", func(s *SuiteContext) {
		s.Case("Чистый запуск без editor_settings.json: автосоздание из example и сохранение настроек", func() error {
			freshDir, err := os.MkdirTemp("", "npblog-fresh-cfg-*")
			if err != nil {
				return err
			}
			defer os.RemoveAll(freshDir)

			// Копируем тестовый editor_settings.example.json на диск (без editor_settings.json)
			exampleJSON := `{
				"language": "ru",
				"initial_setup_completed": false,
				"active_blog_path": "data",
				"blog_paths": ["data"],
				"content_width": 920,
				"autosave_interval": 60,
				"active_theme": "dark"
			}`
			if err := os.WriteFile(filepath.Join(freshDir, "editor_settings.example.json"), []byte(exampleJSON), 0644); err != nil {
				return err
			}

			cfg := &config.AppConfig{Host: "127.0.0.1", Port: "0", BaseDir: freshDir, AssetsDir: freshDir}
			_, _, initErr := config.EnsureEditorSettings(freshDir, nil)
			if initErr != nil {
				return initErr
			}
			cfg.ReloadSettings()

			srvComp := app.NewServer(cfg, false)
			if !srvComp.SelfTestReport.Passed {
				return fmt.Errorf("селф-тест не прошёл на свежей папке: %s", srvComp.SelfTestReport.Summary)
			}
			ts := httptest.NewServer(srvComp.Router)
			defer ts.Close()

			cli := NewNPClient(ts.URL)
			if _, err := cli.RefreshCSRF(); err != nil {
				return err
			}

			// Чтение и сохранение настроек работают сразу без "Не удалось прочитать настройки"
			getResp, err := cli.JSONReq(http.MethodGet, "/get_editor_settings.php", nil, nil)
			if err != nil {
				return err
			}
			if _, err := getResp.MustSuccessJSON(); err != nil {
				return err
			}
			saveResp, err := cli.JSONReq(http.MethodPost, "/save_editor_settings.php", map[string]interface{}{
				"initial_setup_completed": true,
				"active_theme":            "light",
			}, nil)
			if err != nil {
				return err
			}
			if _, err := saveResp.MustSuccessJSON(); err != nil {
				return fmt.Errorf("сохранение настроек не сработало: %w", err)
			}

			// Удаляем editor_settings.json + example и проверяем автосоздание из встроенного шаблона (standalone бинарник)
			_ = os.Remove(filepath.Join(freshDir, "editor_settings.json"))
			_ = os.Remove(filepath.Join(freshDir, "editor_settings.example.json"))
			repEmbedded := srvComp.SelfTest.Run()
			if !repEmbedded.Passed || !repEmbedded.ConfigCreated {
				return fmt.Errorf("ожидалось автосоздание из встроенного шаблона: %+v", repEmbedded)
			}
			return nil
		})

		s.Case("Повреждённый конфиг и нехватка прав: консоль + браузер 503 + блокировка бэкэнда + восстановление", func() error {
			badDir, err := os.MkdirTemp("", "npblog-bad-cfg-*")
			if err != nil {
				return err
			}
			defer func() {
				_ = os.Chmod(filepath.Join(badDir, "editor_settings.json"), 0644)
				_ = os.RemoveAll(badDir)
			}()

			settingsPath := filepath.Join(badDir, "editor_settings.json")
			corruptBytes := `{broken json`
			if err := os.WriteFile(settingsPath, []byte(corruptBytes), 0644); err != nil {
				return err
			}

			cfg := &config.AppConfig{Host: "127.0.0.1", Port: "0", BaseDir: badDir, AssetsDir: badDir}
			cfg.ReloadSettings()
			srvComp := app.NewServer(cfg, false)
			if srvComp.SelfTestReport.Passed {
				return fmt.Errorf("селф-тест должен был провалиться на битом JSON")
			}

			consoleTxt := srvComp.SelfTest.FormatConsoleReport(srvComp.SelfTestReport)
			if !strings.Contains(consoleTxt, "СЕЛФ-ТЕСТ НЕ ПРОЙДЕН") || !strings.Contains(consoleTxt, "editor_settings.json") {
				return fmt.Errorf("консольный отчет не содержит причину провала: %s", consoleTxt)
			}

			// Битый файл пользователя НЕ должен быть перезаписан
			afterDisk, _ := os.ReadFile(settingsPath)
			if string(afterDisk) != corruptBytes {
				return fmt.Errorf("поврежденный файл настроек был перезаписан")
			}

			ts := httptest.NewServer(srvComp.Router)
			defer ts.Close()
			cli := NewNPClient(ts.URL)

			// 1. В браузере GET / возвращает 503 HTML с причиной провала селф-теста
			homeResp, err := cli.RawReq(http.MethodGet, "/", nil, nil)
			if err != nil {
				return err
			}
			if homeResp.Status != http.StatusServiceUnavailable || !strings.Contains(string(homeResp.Body), "Селф-тест бэкэнда NPBlog не пройден") {
				return fmt.Errorf("GET / при ошибке селф-теста вернул %d: %s", homeResp.Status, truncateStr(string(homeResp.Body), 200))
			}

			// 2. Скрипты бэкэнда заблокированы (503 self_test_failed)
			apiResp, err := cli.JSONReq(http.MethodPost, "/save_post.php", map[string]string{"title": "Test", "content": "x"}, nil)
			if err != nil {
				return err
			}
			if apiResp.Status != http.StatusServiceUnavailable || !strings.Contains(string(apiResp.Body), "self_test_failed") {
				return fmt.Errorf("бэкэнд-скрипт не был заблокирован: %d %s", apiResp.Status, string(apiResp.Body))
			}

			// 3. Делаем конфиг валидным, но read-only (нет прав на запись)
			if err := os.WriteFile(settingsPath, []byte(`{"language":"ru","active_blog_path":"data","blog_paths":["data"]}`), 0644); err != nil {
				return err
			}
			if err := os.Chmod(settingsPath, 0444); err != nil {
				return err
			}
			roResp, err := cli.RawReq(http.MethodGet, "/", nil, nil)
			if err != nil {
				return err
			}
			if roResp.Status != http.StatusServiceUnavailable || !strings.Contains(strings.ToLower(string(roResp.Body)), "запис") {
				return fmt.Errorf("селф-тест не сообщил об отсутствии прав на запись: HTTP %d", roResp.Status)
			}

			// 4. Возвращаем права на запись -> перезагрузка страницы / восстанавливает работу редактора
			if err := os.Chmod(settingsPath, 0644); err != nil {
				return err
			}
			okResp, err := cli.RawReq(http.MethodGet, "/", nil, nil)
			if err != nil {
				return err
			}
			if okResp.Status != http.StatusOK || !strings.Contains(string(okResp.Body), "NPBlog") {
				return fmt.Errorf("редактор не восстановился после устранения ошибки (HTTP %d)", okResp.Status)
			}
			return nil
		})
	})
}
