package service

import (
	"bytes"
	"encoding/json"
	"fmt"
	"html"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"npblog"
	"npblog/internal/assets"
	"npblog/internal/config"
)

// SelfTestCheck describes the outcome of a single startup diagnostic verification.
type SelfTestCheck struct {
	ID      string `json:"id"`
	Name    string `json:"name"`
	Path    string `json:"path,omitempty"`
	Passed  bool   `json:"passed"`
	Message string `json:"message"`
	Hint    string `json:"hint,omitempty"`
}

// SelfTestReport aggregates all startup self-test checks and configuration bootstrap status.
type SelfTestReport struct {
	Passed        bool            `json:"passed"`
	CheckedAt     string          `json:"checked_at"`
	ConfigCreated bool            `json:"config_created"`
	ConfigSource  string          `json:"config_source,omitempty"`
	Summary       string          `json:"summary"`
	Checks        []SelfTestCheck `json:"checks"`
}

// SelfTestService verifies workspace, configuration, storage permissions, and embedded assets before backend routes operate.
type SelfTestService struct {
	cfg      *config.AppConfig
	storage  *StorageService
	hybridFS *assets.HybridFS
	mu       sync.RWMutex
	last     SelfTestReport
	hasRun   bool
}

// NewSelfTestService constructs a SelfTestService instance.
func NewSelfTestService(cfg *config.AppConfig, storage *StorageService, hybridFS *assets.HybridFS) *SelfTestService {
	return &SelfTestService{
		cfg:      cfg,
		storage:  storage,
		hybridFS: hybridFS,
	}
}

// LastReport returns the most recent cached self-test result, running checks on first call if needed.
func (s *SelfTestService) LastReport() SelfTestReport {
	s.mu.RLock()
	if s.hasRun {
		rep := s.last
		s.mu.RUnlock()
		return rep
	}
	s.mu.RUnlock()
	return s.Run()
}

// Run executes all read/write, config, directory, and asset integrity self-tests and caches the result.
func (s *SelfTestService) Run() SelfTestReport {
	s.mu.Lock()
	defer s.mu.Unlock()

	report := SelfTestReport{
		Passed:    true,
		CheckedAt: time.Now().Format(time.RFC3339),
		Checks:    make([]SelfTestCheck, 0, 5),
	}

	// 1. Рабочий каталог: существование и права на чтение/запись
	wsCheck := s.checkDirReadWrite(
		"workspace_rw",
		"Права чтения/записи рабочего каталога",
		s.cfg.BaseDir,
		false,
		"Проверьте права ОС на запись/удаление файлов в корневом каталоге NPBlog.",
	)
	report.Checks = append(report.Checks, wsCheck)

	// 2. Авто-создание и проверка конфигурации editor_settings.json (без перезаписи существующих/битых файлов)
	cfgCheck, created, src := s.checkConfigAccess()
	report.ConfigCreated = created
	report.ConfigSource = src
	report.Checks = append(report.Checks, cfgCheck)
	if cfgCheck.Passed {
		s.cfg.ReloadSettings()
	}

	// 3. Каталог данных блога (чтение, запись и существующие критичные JSON-файлы)
	activeBlog := s.storage.EffectiveActiveBlog(s.cfg.GetSettings())
	dataDir := s.storage.GetDataPath(activeBlog, "")
	dataCheck := s.checkBlogDataDir(dataDir)
	report.Checks = append(report.Checks, dataCheck)

	// 4. Каталог резервных копий и черновиков
	backupRoot := filepath.Join(s.cfg.BaseDir, "data_backup")
	backupCheck := s.checkDirReadWrite(
		"backup_dir_rw",
		"Права чтения/записи каталога бэкапов и автосохранений",
		backupRoot,
		true,
		"Убедитесь, что каталог data_backup доступен для создания и записи файлов.",
	)
	report.Checks = append(report.Checks, backupCheck)

	// 5. Критические ресурсы интерфейса редактора и локализации
	assetsCheck := s.checkEditorAssets()
	report.Checks = append(report.Checks, assetsCheck)

	var failedReasons []string
	for _, c := range report.Checks {
		if !c.Passed {
			report.Passed = false
			failedReasons = append(failedReasons, fmt.Sprintf("%s: %s", c.Name, c.Message))
		}
	}

	if report.Passed {
		if report.ConfigCreated {
			report.Summary = "Селф-тест пройден успешно (конфигурация editor_settings.json создана из шаблона)."
		} else {
			report.Summary = "Селф-тест пройден успешно: все права и конфигурация в норме."
		}
	} else {
		report.Summary = "Селф-тест НЕ пройден: " + strings.Join(failedReasons, "; ")
	}

	s.last = report
	s.hasRun = true
	return report
}

func (s *SelfTestService) checkConfigAccess() (SelfTestCheck, bool, string) {
	settingsPath := filepath.Join(s.cfg.BaseDir, "editor_settings.json")
	check := SelfTestCheck{
		ID:   "config_access",
		Name: "Конфигурация editor_settings.json (чтение/запись)",
		Path: settingsPath,
	}

	created, source, initErr := config.EnsureEditorSettings(s.cfg.BaseDir, npblog.EmbeddedAssets)
	if initErr != nil {
		check.Message = initErr.Error()
		check.Hint = "Проверьте права на запись в корневую папку NPBlog или наличие editor_settings.example.json."
		return check, false, source
	}

	raw, err := os.ReadFile(settingsPath)
	if err != nil {
		check.Message = fmt.Sprintf("не удалось прочитать файл конфигурации: %v", err)
		check.Hint = "Проверьте права на чтение editor_settings.json."
		return check, created, source
	}

	var parsed map[string]interface{}
	if err := json.Unmarshal(raw, &parsed); err != nil || parsed == nil {
		check.Message = fmt.Sprintf("файл конфигурации поврежден (невалидный JSON): %v", err)
		check.Hint = "Исправьте синтаксис JSON в editor_settings.json или удалите поврежденный файл, чтобы редактор пересоздал его из шаблона."
		return check, created, source
	}

	if err := checkFileWritable(settingsPath); err != nil {
		check.Message = fmt.Sprintf("нет прав на запись в editor_settings.json: %v", err)
		check.Hint = "Снимите атрибут «Только чтение» (read-only) и выдайте права на запись текущему пользователю."
		return check, created, source
	}

	check.Passed = true
	if created {
		check.Message = fmt.Sprintf("создан автоматически из %s и доступен для чтения/записи", source)
	} else {
		check.Message = "доступен для чтения и записи, JSON валиден"
	}
	return check, created, source
}

func (s *SelfTestService) checkDirReadWrite(id, name, dir string, createIfMissing bool, hint string) SelfTestCheck {
	check := SelfTestCheck{
		ID:   id,
		Name: name,
		Path: dir,
		Hint: hint,
	}

	if createIfMissing {
		if err := os.MkdirAll(dir, 0755); err != nil {
			check.Message = fmt.Sprintf("не удалось создать или открыть каталог: %v", err)
			return check
		}
	}

	info, err := os.Stat(dir)
	if err != nil {
		check.Message = fmt.Sprintf("каталог недоступен: %v", err)
		return check
	}
	if !info.IsDir() {
		check.Message = "указанный путь не является каталогом"
		return check
	}

	if _, err := os.ReadDir(dir); err != nil {
		check.Message = fmt.Sprintf("нет прав на чтение каталога: %v", err)
		return check
	}

	if err := probeDirReadWrite(dir); err != nil {
		check.Message = fmt.Sprintf("нет прав на запись/удаление файлов: %v", err)
		return check
	}

	check.Passed = true
	check.Message = "чтение и запись работают корректно"
	return check
}

func (s *SelfTestService) checkBlogDataDir(dataDir string) SelfTestCheck {
	check := s.checkDirReadWrite(
		"data_dir_rw",
		"Права чтения/записи каталога данных блога",
		dataDir,
		true,
		"Проверьте права доступа к папке блога (data/) и файлам в ней.",
	)
	if !check.Passed {
		return check
	}

	for _, sub := range []string{"blog", "uploads", "files"} {
		subDir := filepath.Join(dataDir, sub)
		if err := os.MkdirAll(subDir, 0755); err != nil {
			check.Passed = false
			check.Path = subDir
			check.Message = fmt.Sprintf("не удалось подготовить подкаталог %s: %v", sub, err)
			return check
		}
		if err := probeDirReadWrite(subDir); err != nil {
			check.Passed = false
			check.Path = subDir
			check.Message = fmt.Sprintf("нет прав на запись в подкаталог %s: %v", sub, err)
			return check
		}
	}

	// Проверяем существующие служебные JSON-файлы блога на чтение/валидность/запись без перезаписи
	criticalJSON := []string{
		filepath.Join(dataDir, "blog", "posts-meta.json"),
		filepath.Join(dataDir, "blog", "templates", "settings.json"),
	}
	for _, jf := range criticalJSON {
		raw, err := os.ReadFile(jf)
		if os.IsNotExist(err) {
			continue
		}
		if err != nil {
			check.Passed = false
			check.Path = jf
			check.Message = fmt.Sprintf("не удалось прочитать %s: %v", filepath.Base(jf), err)
			return check
		}
		var v interface{}
		if err := json.Unmarshal(raw, &v); err != nil {
			check.Passed = false
			check.Path = jf
			check.Message = fmt.Sprintf("поврежден JSON в файле %s: %v", filepath.Base(jf), err)
			check.Hint = "Исправьте JSON в указанном файле или восстановите его из резервной копии."
			return check
		}
		if err := checkFileWritable(jf); err != nil {
			check.Passed = false
			check.Path = jf
			check.Message = fmt.Sprintf("нет прав на запись в %s: %v", filepath.Base(jf), err)
			return check
		}
	}

	check.Message = "каталоги блога и метаданные доступны для чтения и записи"
	return check
}

func (s *SelfTestService) checkEditorAssets() SelfTestCheck {
	check := SelfTestCheck{
		ID:   "assets_bundle",
		Name: "Ресурсы редактора и файлы локализации",
		Path: s.cfg.AssetsDir,
		Hint: "Проверьте целостность файлов web/, editorjs/, modals/, lang/ и прав на их чтение.",
	}

	if s.hybridFS == nil {
		check.Message = "HybridFS не инициализирована"
		return check
	}

	required := []string{
		"web/templates/editor.html",
		"editor-style.css",
		"modals/modal.css",
		"lang/ru.json",
		"lang/en.json",
	}
	for _, rel := range required {
		data, err := s.hybridFS.ReadFile(rel)
		if err != nil || len(data) == 0 {
			check.Path = rel
			check.Message = fmt.Sprintf("недоступен обязательный ресурс %s: %v", rel, err)
			return check
		}
		if strings.HasSuffix(rel, ".json") {
			var parsed map[string]interface{}
			if err := json.Unmarshal(data, &parsed); err != nil || parsed == nil {
				check.Path = rel
				check.Message = fmt.Sprintf("невалидный JSON в ресурсе %s: %v", rel, err)
				return check
			}
		}
	}

	check.Passed = true
	check.Message = "шаблоны, стили и словари локализации доступны"
	return check
}

func probeDirReadWrite(dir string) error {
	probeFile, err := os.CreateTemp(dir, ".npblog_rw_probe_*.tmp")
	if err != nil {
		return err
	}
	probeName := probeFile.Name()
	payload := []byte("npblog-selftest-rw")
	if _, err := probeFile.Write(payload); err != nil {
		_ = probeFile.Close()
		_ = os.Remove(probeName)
		return err
	}
	if err := probeFile.Close(); err != nil {
		_ = os.Remove(probeName)
		return err
	}
	got, err := os.ReadFile(probeName)
	_ = os.Remove(probeName)
	if err != nil {
		return err
	}
	if !bytes.Equal(got, payload) {
		return fmt.Errorf("контрольное чтение после записи не совпало")
	}
	return nil
}

func checkFileWritable(path string) error {
	info, err := os.Stat(path)
	if err != nil {
		return err
	}
	if info.Mode().Perm()&0222 == 0 {
		return fmt.Errorf("файл открыт только для чтения (права %o)", info.Mode().Perm())
	}
	f, err := os.OpenFile(path, os.O_WRONLY, 0)
	if err != nil {
		return err
	}
	return f.Close()
}

// FormatConsoleReport renders a human-readable startup diagnostic banner for console logs.
func (s *SelfTestService) FormatConsoleReport(rep SelfTestReport) string {
	var sb strings.Builder
	sb.WriteString("======================================================================\n")
	if rep.Passed {
		sb.WriteString("  [SELF-TEST OK] Селф-тест бэкэнда NPBlog пройден\n")
	} else {
		sb.WriteString("  [SELF-TEST FAILED] СЕЛФ-ТЕСТ НЕ ПРОЙДЕН — ЗАПУСК СКРИПТОВ ОГРАНИЧЕН\n")
	}
	sb.WriteString("----------------------------------------------------------------------\n")
	for _, c := range rep.Checks {
		tag := "[PASS]"
		if !c.Passed {
			tag = "[FAIL]"
		}
		sb.WriteString(fmt.Sprintf("  %s %s: %s\n", tag, c.Name, c.Message))
		if c.Path != "" {
			sb.WriteString(fmt.Sprintf("         Путь: %s\n", c.Path))
		}
		if !c.Passed && c.Hint != "" {
			sb.WriteString(fmt.Sprintf("         Совет: %s\n", c.Hint))
		}
	}
	sb.WriteString("======================================================================\n")
	return sb.String()
}

// RenderBrowserErrorHTML builds a standalone diagnostic HTML page shown in browsers when the startup self-test fails.
func (s *SelfTestService) RenderBrowserErrorHTML(rep SelfTestReport) string {
	var rows strings.Builder
	for _, c := range rep.Checks {
		statusClass := "ok"
		statusLabel := "OK"
		if !c.Passed {
			statusClass = "fail"
			statusLabel = "ОШИБКА"
		}
		pathHTML := ""
		if c.Path != "" {
			pathHTML = fmt.Sprintf(`<div class="check-path">%s</div>`, html.EscapeString(c.Path))
		}
		hintHTML := ""
		if !c.Passed && c.Hint != "" {
			hintHTML = fmt.Sprintf(`<div class="check-hint">%s</div>`, html.EscapeString(c.Hint))
		}
		rows.WriteString(fmt.Sprintf(
			`<div class="check-row check-%s"><div class="check-head"><span class="badge badge-%s">%s</span><span class="check-name">%s</span></div><div class="check-msg">%s</div>%s%s</div>`,
			statusClass, statusClass, statusLabel,
			html.EscapeString(c.Name),
			html.EscapeString(c.Message),
			pathHTML,
			hintHTML,
		))
	}

	return fmt.Sprintf(`<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NPBlog — Селф-тест не пройден</title>
<style>
  :root { --bg:#111216; --card:#191b22; --border:#2b2f3a; --text:#f3f4f6; --muted:#9ca3af; --fail:#ef4444; --ok:#22c55e; --btn:#ffffff; --btn-text:#111216; }
  @media (prefers-color-scheme: light) { :root { --bg:#f5f6f8; --card:#ffffff; --border:#e2e5eb; --text:#18181b; --muted:#52525b; --fail:#dc2626; --ok:#16a34a; --btn:#18181b; --btn-text:#ffffff; } }
  * { box-sizing:border-box; }
  body { margin:0; padding:24px; min-height:100vh; display:flex; align-items:center; justify-content:center; background:var(--bg); color:var(--text); font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
  .card { width:100%%; max-width:760px; background:var(--card); border:1px solid var(--border); border-radius:16px; padding:28px; box-shadow:0 20px 50px rgba(0,0,0,0.35); }
  .top { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; border-bottom:1px solid var(--border); padding-bottom:16px; margin-bottom:18px; }
  h1 { margin:0 0 6px 0; font-size:20px; color:var(--fail); }
  .sub { margin:0; font-size:14px; color:var(--muted); line-height:1.45; }
  .check-row { padding:12px 14px; border:1px solid var(--border); border-radius:10px; margin-bottom:10px; }
  .check-fail { border-color:rgba(239,68,68,0.45); background:rgba(239,68,68,0.06); }
  .check-head { display:flex; align-items:center; gap:10px; margin-bottom:4px; }
  .check-name { font-weight:600; font-size:14px; }
  .badge { font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px; text-transform:uppercase; }
  .badge-ok { background:rgba(34,197,94,0.16); color:var(--ok); }
  .badge-fail { background:rgba(239,68,68,0.18); color:var(--fail); }
  .check-msg { font-size:13px; line-height:1.45; }
  .check-path { margin-top:6px; font-family:ui-monospace,Consolas,monospace; font-size:12px; color:var(--muted); word-break:break-all; }
  .check-hint { margin-top:6px; font-size:12px; color:var(--text); opacity:0.85; }
  .actions { display:flex; justify-content:flex-end; gap:10px; margin-top:18px; padding-top:14px; border-top:1px solid var(--border); }
  .btn { border:none; border-radius:9px; padding:10px 18px; font-size:13px; font-weight:600; cursor:pointer; background:var(--btn); color:var(--btn-text); }
</style>
</head>
<body>
  <div class="card">
    <div class="top">
      <div>
        <h1>Селф-тест бэкэнда NPBlog не пройден</h1>
        <p class="sub">%s</p>
      </div>
    </div>
    <div>%s</div>
    <div class="actions">
      <button type="button" class="btn" onclick="retrySelfTest()">Повторить проверку</button>
    </div>
  </div>
  <script>
    function retrySelfTest() {
      fetch('/_selftest', { method: 'POST', headers: { 'Accept': 'application/json' } })
        .then(function () { location.reload(); })
        .catch(function () { location.reload(); });
    }
  </script>
</body>
</html>`, html.EscapeString(rep.Summary), rows.String())
}
