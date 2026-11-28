package main

import (
	"flag"
	"fmt"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"time"

	"npblog/internal/app"
	"npblog/internal/config"
)

func main() {
	seedFlag := flag.Int64("seed", time.Now().UnixNano(), "Random seed for reproducible chaos/fuzz scenarios")
	fuzzOpsFlag := flag.Int("fuzz-ops", 180, "Number of randomized chaos operations to execute")
	concurrencyFlag := flag.Int("concurrency", 8, "Number of concurrent workers in stress/fuzz suite")
	noBrowserFlag := flag.Bool("no-browser", false, "Skip headless Edge/Chrome browser UI suite")
	browserFlag := flag.String("browser", "", "Explicit path to Edge/Chrome/Chromium executable")
	filterFlag := flag.String("filter", "", "Run only suites whose title contains this substring")
	reportFlag := flag.String("report", "", "Optional path to save JSON test report")
	keepTmpFlag := flag.Bool("keep-tmp", false, "Keep temporary isolated test workspace after exit")
	noPauseFlag := flag.Bool("no-pause", false, "Do not wait for Enter even when launched from Windows Explorer")
	verboseFlag := flag.Bool("verbose", true, "Print per-case execution timings")
	flag.Parse()

	exitCode := run(*seedFlag, *fuzzOpsFlag, *concurrencyFlag, *noBrowserFlag, *browserFlag, *filterFlag, *reportFlag, *keepTmpFlag, *verboseFlag)
	maybePauseOnExplorerLaunch(*noPauseFlag)
	os.Exit(exitCode)
}

func run(seed int64, fuzzOps, concurrency int, noBrowser bool, browserBin, filter, reportPath string, keepTmp, verbose bool) int {
	// Изолированная временная песочница — реальные data/, editor_settings.json и old_back/ никогда не затрагиваются
	tmpRoot, err := os.MkdirTemp("", "npblog-ultimate-test-*")
	if err != nil {
		fmt.Printf("[FATAL] Не удалось создать временную папку: %v\n", err)
		return 1
	}
	canaryDir, err := os.MkdirTemp("", "npblog-canary-*")
	if err != nil {
		fmt.Printf("[FATAL] Не удалось создать canary папку: %v\n", err)
		_ = os.RemoveAll(tmpRoot)
		return 1
	}

	canaryPath := filepath.Join(canaryDir, "canary.txt")
	_ = os.WriteFile(canaryPath, []byte("TOP_SECRET_CANARY"), 0644)

	if !keepTmp {
		defer func() {
			_ = os.RemoveAll(tmpRoot)
			_ = os.RemoveAll(canaryDir)
		}()
	}

	// Базовая структура каталога блога и стартовых шаблонов в песочнице
	_ = os.MkdirAll(filepath.Join(tmpRoot, "data", "blog", "assets"), 0755)
	_ = os.MkdirAll(filepath.Join(tmpRoot, "data", "blog", "templates", "NPBlog"), 0755)
	_ = os.MkdirAll(filepath.Join(tmpRoot, "data_backup"), 0755)

	starterTpl := `<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>{{TITLE}}</title><meta name="post-id" content="{{POST_ID}}">{{META_TAGS}}{{CUSTOM_FONTS}}<link rel="stylesheet" href="assets/style.css"><style>body{font-family:sans-serif;}</style></head><body{{BODY_STYLE}}>{{CONTENT_WRAPPER_START}}<article><h1>{{TITLE}}</h1><time>{{DATE}}</time><div>{{CONTENT}}</div></article>{{CONTENT_WRAPPER_END}}<script>function loadPostStyles(){}</script></body></html>`
	_ = os.WriteFile(filepath.Join(tmpRoot, "data", "blog", "template_post.html"), []byte(starterTpl), 0644)
	_ = os.WriteFile(filepath.Join(tmpRoot, "data", "blog", "templates", "NPBlog", "main.html"), []byte(starterTpl), 0644)
	_ = os.WriteFile(filepath.Join(tmpRoot, "data", "blog", "assets", "style.css"), []byte("body { margin: 0; font-family: sans-serif; }\n"), 0644)
	_ = os.WriteFile(filepath.Join(tmpRoot, "data", "blog.html"), []byte(`<!DOCTYPE html><html><head><title>Main Test Blog</title></head><body><header><h1>Main Test Blog</h1></header><script>function loadBlogViewSettings(){}</script></body></html>`), 0644)
	_ = os.WriteFile(filepath.Join(tmpRoot, "data", "blog", "posts-meta.json"), []byte("[]"), 0644)

	_ = os.WriteFile(filepath.Join(tmpRoot, "editor_settings.json"), []byte(`{
		"content_width": 920,
		"autosave_interval": 60,
		"language": "ru",
		"active_theme": "dark",
		"active_blog_path": "data",
		"blog_paths": ["data"],
		"blog_titles": {"data": "Main Test Blog"}
	}`), 0644)

	cfg := &config.AppConfig{
		Host:      "127.0.0.1",
		Port:      "0",
		BaseDir:   tmpRoot,
		AssetsDir: tmpRoot, // Все ассеты, стили, JS, i18n берутся из встроенной embedded FS бинарника
	}
	cfg.ReloadSettings()

	srvComp := app.NewServer(cfg, false)
	ts := httptest.NewServer(http.HandlerFunc(srvComp.Router.ServeHTTP))
	defer ts.Close()

	fmt.Println("======================================================================")
	fmt.Println("  NPBlog Ultimate Test Suite (Full Stack + Chaos Fuzzer + Browser UI)")
	fmt.Println("======================================================================")
	fmt.Printf("  Server URL : %s\n", ts.URL)
	fmt.Printf("  Workspace  : %s\n", tmpRoot)
	fmt.Printf("  Seed       : %d\n", seed)
	fmt.Printf("  Fuzz Ops   : %d (workers: %d)\n", fuzzOps, concurrency)
	fmt.Println("======================================================================")

	runner := NewTestRunner(verbose, filter)
	runCoreSuites(runner, ts.URL, tmpRoot)
	runFeatureSuites(runner, ts.URL, tmpRoot)
	runChaosAndUISuites(runner, ts.URL, tmpRoot, canaryPath, seed, fuzzOps, concurrency, noBrowser, browserBin)
	runSelfTestSuite(runner)

	elapsed := time.Since(runner.StartAt).Round(time.Millisecond)
	fmt.Println("\n======================================================================")
	fmt.Printf("  ИТОГ: PASS=%d | FAIL=%d | SKIP=%d | ВСЕГО=%d | Время=%s\n",
		runner.Passed, runner.Failed, runner.Skipped, len(runner.Records), elapsed)
	if keepTmp {
		fmt.Printf("  Временная папка сохранена: %s\n", tmpRoot)
	}
	if reportPath != "" {
		if err := runner.WriteReport(reportPath); err != nil {
			fmt.Printf("  [WARN] Ошибка записи отчета %s: %v\n", reportPath, err)
		} else {
			fmt.Printf("  Отчет сохранен: %s\n", reportPath)
		}
	}
	fmt.Println("======================================================================")

	if runner.Failed > 0 {
		return 1
	}
	return 0
}
