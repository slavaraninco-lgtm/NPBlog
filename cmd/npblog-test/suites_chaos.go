package main

import (
	"encoding/json"
	"fmt"
	"math/rand"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"sync/atomic"
	"time"
)

// runChaosAndUISuites runs Security/Traversal checks, Parallel Seeded Fuzzing, and Real Browser UI checks.
func runChaosAndUISuites(tr *TestRunner, baseURL, workspaceRoot, canaryPath string, seed int64, fuzzOps, concurrency int, noBrowser bool, browserBin string) {
	// 10. Матрица безопасности и Path Traversal
	tr.Suite("10. Безопасность: Path Traversal, утечки конфигов и границы песочницы", func(s *SuiteContext) {
		c := NewNPClient(baseURL)
		if _, err := c.RefreshCSRF(); err != nil {
			s.Case("CSRF init", func() error { return err })
			return
		}

		vectors := []string{
			"../canary.txt",
			"../../canary.txt",
			"..\\..\\..\\canary.txt",
			"%2e%2e%2fcanary.txt",
			"editor_settings.json",
			"../editor_settings.json",
			"sessions.json",
			"api_tokens.json",
			"allowed_ips.txt",
			"history.json",
			"ftp.json",
			"/etc/passwd",
			`C:\Windows\win.ini`,
			"foo\x00.png",
		}

		s.Case("Блокировка доступа к системным конфигам и выхода за пределы корня через serve_data.php и /{blog}/*", func() error {
			for _, v := range vectors {
				esc := url.QueryEscape(v)
				for _, route := range []string{
					"/serve_data.php?file=" + esc,
					"/serve_data.php/" + esc,
					"/data/" + esc,
				} {
					resp, err := c.RawReq(http.MethodGet, route, nil, nil)
					if err != nil {
						return err
					}
					if resp.Status >= 500 {
						return fmt.Errorf("%s вернул 5xx (%d)", route, resp.Status)
					}
					body := string(resp.Body)
					if strings.Contains(body, "TOP_SECRET_CANARY") || strings.Contains(body, "password_hash") {
						return fmt.Errorf("КРИТИЧНАЯ УТЕЧКА по %s: %s", route, truncateStr(body, 150))
					}
				}
			}
			return nil
		})

		s.Case("Удаление/чтение произвольных файлов вне блога через POST-эндпоинты отклоняется, canary не тронут", func() error {
			for _, v := range vectors {
				endpoints := []struct {
					path string
					body map[string]string
				}{
					{"/delete_draft.php", map[string]string{"filename": v}},
					{"/delete_include.php", map[string]string{"filename": v}},
					{"/delete_document.php", map[string]string{"filename": v}},
					{"/delete_audio.php", map[string]string{"filename": v}},
					{"/delete_video.php", map[string]string{"filename": v}},
					{"/delete_template.php", map[string]string{"name": v}},
					{"/get_include_content.php", map[string]string{"filename": v}},
					{"/get_backup_content.php", map[string]string{"filename": v, "postId": v}},
					{"/create_blog.php", map[string]string{"title": "evil", "folder": v}},
				}
				for _, ep := range endpoints {
					resp, err := c.JSONReq(http.MethodPost, ep.path, ep.body, nil)
					if err != nil {
						return err
					}
					if resp.Status >= 500 {
						return fmt.Errorf("%s (%q) -> HTTP %d", ep.path, v, resp.Status)
					}
				}
			}

			canaryBytes, err := os.ReadFile(canaryPath)
			if err != nil || string(canaryBytes) != "TOP_SECRET_CANARY" {
				return fmt.Errorf("canary-файл вне песочницы был поврежден или удален: %v", err)
			}
			return nil
		})
	})

	// 11. Многопоточный стресс-тест и Seeded Chaos Fuzzer
	tr.Suite(fmt.Sprintf("11. Параллельный хаос-фаззер (seed=%d, ops=%d, workers=%d)", seed, fuzzOps, concurrency), func(s *SuiteContext) {
		s.Case("Случайные предсказуемые и непредсказуемые действия без паник и порчи JSON", func() error {
			if fuzzOps <= 0 {
				return fmt.Errorf("SKIP: fuzz-ops=0")
			}
			if concurrency < 1 {
				concurrency = 1
			}

			var panic500 int64
			var netFails int64
			var opsDone int64
			var errMu sync.Mutex
			var firstErr string

			perWorker := (fuzzOps + concurrency - 1) / concurrency
			var wg sync.WaitGroup

			for w := 0; w < concurrency; w++ {
				workerID := w
				wg.Add(1)
				go func() {
					defer wg.Done()
					rng := rand.New(rand.NewSource(seed + int64(workerID)*7919))
					cli := NewNPClient(baseURL)
					_, _ = cli.RefreshCSRF()

					for i := 0; i < perWorker; i++ {
						if rng.Intn(5) == 0 {
							blogs := []string{"data", "data_two", "/data_two/", `data_two\`, "../../evil", ""}
							cli.BlogHeader = blogs[rng.Intn(len(blogs))]
						}

						var resp *HTTPResp
						var reqErr error
						action := rng.Intn(14)

						switch action {
						case 0: // Создание статьи с рандомным текстом/мусором
							resp, reqErr = cli.JSONReq(http.MethodPost, "/save_post.php", map[string]interface{}{
								"title":   fmt.Sprintf("Fuzz-%d-%d-%s", workerID, i, randWeirdStr(rng)),
								"content": "<p>" + randWeirdStr(rng) + "</p>",
							}, nil)
						case 1: // Сохранение автосейва
							resp, reqErr = cli.JSONReq(http.MethodPost, "/save_autosave.php", map[string]interface{}{
								"id":      fmt.Sprintf("fuzz-as-%d", rng.Intn(8)),
								"title":   randWeirdStr(rng),
								"content": "<div>fuzz</div>",
							}, nil)
						case 2: // Чтение списков и шаблонов
							gets := []string{
								"/api/v1/posts", "/get_all_autosaves.php", "/get_drafts.php",
								"/get_backups.php", "/get_templates.php?details=1",
								"/get_custom_fonts.php", "/get_custom_fonts_css.php", "/get_editor_settings.php",
							}
							resp, reqErr = cli.RawReq(http.MethodGet, gets[rng.Intn(len(gets))], nil, nil)
						case 3: // Полностью битый JSON / неожиданные типы
							weirdBodies := []string{
								`{broken json,,,`,
								``,
								`null`,
								`[1,2,3]`,
								`{"id":"нечисло","title":123456,"content":true}`,
								strings.Repeat(`{"a":`, 150) + `1` + strings.Repeat(`}`, 150),
							}
							eps := []string{"/save_post.php", "/update_post.php", "/save_draft.php", "/save_template.php", "/save_blog_view_settings.php"}
							resp, reqErr = cli.RawReq(http.MethodPost, eps[rng.Intn(len(eps))],
								[]byte(weirdBodies[rng.Intn(len(weirdBodies))]),
								map[string]string{"Content-Type": "application/json"},
							)
						case 4: // Битый multipart с неверным boundary
							resp, reqErr = cli.RawReq(http.MethodPost, "/upload_image.php",
								[]byte("--broken-boundary\r\ngarbage-bytes\x00\xff"),
								map[string]string{"Content-Type": "multipart/form-data; boundary=real-boundary"},
							)
						case 5: // Неожиданный HTTP-метод
							methods := []string{"PATCH", "PUT", "DELETE", "TRACE", "HEAD"}
							resp, reqErr = cli.RawReq(methods[rng.Intn(len(methods))], "/save_post.php", []byte(`{}`), nil)
						case 6: // Чтение/обновление случайного ID статьи
							id := rng.Intn(10) + 1
							resp, reqErr = cli.JSONReq(http.MethodGet, fmt.Sprintf("/get_post_content.php?id=%d", id), nil, nil)
						case 7: // Сохранение/удаление черновика
							if rng.Intn(2) == 0 {
								resp, reqErr = cli.JSONReq(http.MethodPost, "/save_draft.php", map[string]string{
									"title": randWeirdStr(rng), "content": "<p>draft</p>",
								}, nil)
							} else {
								resp, reqErr = cli.JSONReq(http.MethodPost, "/delete_draft.php", map[string]string{
									"filename": fmt.Sprintf("nonexistent-%d.json", rng.Intn(999)),
								}, nil)
							}
						case 8: // Переключение темы / настроек редактора
							themes := []string{"light", "dark", "auto"}
							resp, reqErr = cli.JSONReq(http.MethodPost, "/save_editor_settings.php", map[string]interface{}{
								"active_theme":  themes[rng.Intn(len(themes))],
								"content_width": 700 + rng.Intn(500),
							}, nil)
						case 9: // Перезагрузка шаблонов и проверка целостности
							if rng.Intn(2) == 0 {
								resp, reqErr = cli.RawReq(http.MethodGet, "/check_integrity.php", nil, nil)
							} else {
								resp, reqErr = cli.JSONReq(http.MethodPost, "/regenerate_posts.php", map[string]string{}, nil)
							}
						case 10: // Range запросы с некорректным Range
							ranges := []string{"bytes=999999-1000000", "bytes=-500", "bytes=abc-def", "invalid"}
							resp, reqErr = cli.RawReq(http.MethodGet, "/data/blog/post-1.html", nil, map[string]string{
								"Range": ranges[rng.Intn(len(ranges))],
							})
						case 11: // Вставки (Includes)
							resp, reqErr = cli.JSONReq(http.MethodPost, "/save_include.php", map[string]string{
								"name": fmt.Sprintf("inc_%d", rng.Intn(4)), "content": "<span>" + randWeirdStr(rng) + "</span>",
							}, nil)
						case 12: // Pin toggle на случайном ID
							resp, reqErr = cli.JSONReq(http.MethodPost, "/toggle_pin_post.php", map[string]int{"id": rng.Intn(12) + 1}, nil)
						default: // Быстрый запрос / и статики
							resp, reqErr = cli.RawReq(http.MethodGet, "/", nil, nil)
						}

						atomic.AddInt64(&opsDone, 1)
						if reqErr != nil {
							atomic.AddInt64(&netFails, 1)
							errMu.Lock()
							if firstErr == "" {
								firstErr = fmt.Sprintf("network error: %v", reqErr)
							}
							errMu.Unlock()
							continue
						}
						if resp.Status >= 500 {
							atomic.AddInt64(&panic500, 1)
							errMu.Lock()
							if firstErr == "" {
								firstErr = fmt.Sprintf("HTTP %d: %s", resp.Status, truncateStr(string(resp.Body), 180))
							}
							errMu.Unlock()
						}
					}
				}()
			}
			wg.Wait()

			if panic500 > 0 || netFails > 0 {
				return fmt.Errorf("ops=%d, 5xx=%d, netFails=%d (%s)", opsDone, panic500, netFails, firstErr)
			}

			// Проверяем целостность всех JSON-файлов состояния после многопоточного шторма
			jsonFiles := []string{
				filepath.Join(workspaceRoot, "editor_settings.json"),
				filepath.Join(workspaceRoot, "data", "blog", "posts-meta.json"),
				filepath.Join(workspaceRoot, "data", "blog", "templates", "settings.json"),
				filepath.Join(workspaceRoot, "data_two", "blog", "posts-meta.json"),
			}
			for _, jf := range jsonFiles {
				raw, err := os.ReadFile(jf)
				if err != nil {
					if os.IsNotExist(err) {
						continue
					}
					return err
				}
				var check interface{}
				if err := json.Unmarshal(raw, &check); err != nil {
					return fmt.Errorf("файл %s поврежден после конкурентной нагрузки: %w", jf, err)
				}
			}
			return nil
		})
	})

	// 12. Реальный браузер (Headless Edge / Chrome CDP UI Test)
	tr.Suite("12. Браузерный UI-тест (DOM, темы Light/Dark/AMOLED, модалки и JS без ошибок)", func(s *SuiteContext) {
		if noBrowser {
			s.Case("Запуск headless браузера", func() error {
				return fmt.Errorf("SKIP: отключено флагом -no-browser")
			})
			return
		}
		bin := findChromiumBinary(browserBin)
		if bin == "" {
			s.Case("Поиск Edge/Chrome", func() error {
				return fmt.Errorf("SKIP: браузер Edge/Chrome/Chromium не найден на системе")
			})
			return
		}

		// Перед UI-тестом гарантируем активный блог data, без пароля и с чистой темой
		api := NewNPClient(baseURL)
		_, _ = api.RefreshCSRF()
		_, _ = api.JSONReq(http.MethodPost, "/save_editor_settings.php", map[string]interface{}{
			"active_theme": "dark", "amoled_theme": false, "language": "ru", "custom_theme_enabled": false,
		}, nil)

		cdp, err := launchHeadlessCDP(bin)
		if err != nil {
			s.Case("Подключение к Chrome DevTools Protocol", func() error { return err })
			return
		}
		defer cdp.Close()

		s.Case("Загрузка редактора в браузере без JS-исключений и битых ассетов", func() error {
			if err := cdp.Navigate(baseURL + "/"); err != nil {
				return err
			}
			if err := cdp.WaitFor(`typeof Modal !== 'undefined' && typeof openTemplateManager === 'function' && typeof openCustomFontsModal === 'function'`, 8*time.Second); err != nil {
				return err
			}
			time.Sleep(350 * time.Millisecond)
			jsErrs, netErrs := cdp.TakeErrors()
			if len(jsErrs) > 0 || len(netErrs) > 0 {
				return fmt.Errorf("ошибки при загрузке UI: js=%v net=%v", jsErrs, netErrs)
			}
			return nil
		})

		s.Case("Меню размера шрифта подстраивается по высоте и НЕ имеет полосы прокрутки", func() error {
			if err := cdp.Eval(`document.getElementById('fontSizeBtn').click()`, nil); err != nil {
				return err
			}
			if err := cdp.WaitFor(`document.getElementById('fontSizeWrapMain').classList.contains('is-open')`, 3*time.Second); err != nil {
				return err
			}
			time.Sleep(150 * time.Millisecond)
			var info struct {
				ScrollH   float64 `json:"scrollH"`
				ClientH   float64 `json:"clientH"`
				OverflowY string  `json:"overflowY"`
			}
			err := cdp.Eval(`(() => {
				const inner = document.querySelector('#fontSizeWrapMain .font-size-popover-inner');
				const cs = getComputedStyle(inner);
				return { scrollH: inner.scrollHeight, clientH: inner.clientHeight, overflowY: cs.overflowY };
			})()`, &info)
			if err != nil {
				return err
			}
			if info.ClientH <= 0 || info.ScrollH > info.ClientH+2 {
				return fmt.Errorf("обнаружен скролл/переполнение font-size меню: scrollHeight=%.1f clientHeight=%.1f overflowY=%s", info.ScrollH, info.ClientH, info.OverflowY)
			}
			// Закрываем popover кликом вне его
			_ = cdp.Eval(`document.getElementById('fontSizeBtn').click()`, nil)
			return nil
		})

		s.Case("Менеджер шаблонов открывается, загружает шаблоны, кнопки без смайлов", func() error {
			if err := cdp.Eval(`openTemplateManager()`, nil); err != nil {
				return err
			}
			if err := cdp.WaitFor(`document.querySelectorAll('#templatesGrid > *').length > 0`, 6*time.Second); err != nil {
				return err
			}
			var texts []string
			err := cdp.Eval(`[...document.querySelectorAll('#templateManagerDialog .modal-header-actions button')].map(b => b.textContent.trim())`, &texts)
			if err != nil {
				return err
			}
			joined := strings.Join(texts, " | ")
			if strings.Contains(joined, "ℹ️") || strings.Contains(joined, "📥") || strings.Contains(joined, "\u2139") {
				return fmt.Errorf("в кнопках менеджера шаблонов остались смайлы: %q", joined)
			}
			_ = cdp.Eval(`closeTemplateManager()`, nil)
			return nil
		})

		s.Case("Модалка пользовательских шрифтов: карточки видимы (opacity=1) и контрастны в Light, Dark и AMOLED", func() error {
			if err := cdp.Eval(`openCustomFontsModal()`, nil); err != nil {
				return err
			}
			if err := cdp.WaitFor(`document.querySelectorAll('#customFontsList .custom-font-item').length > 0`, 6*time.Second); err != nil {
				return err
			}
			// Дожидаемся завершения анимации открытия модального окна
			time.Sleep(420 * time.Millisecond)

			themes := []struct {
				name   string
				theme  string
				amoled bool
			}{
				{"light", "light", false},
				{"dark", "dark", false},
				{"amoled", "dark", true},
			}
			for _, th := range themes {
				switchExpr := fmt.Sprintf(`(() => {
					if (typeof applyTheme === 'function') applyTheme('%s');
					else if ('%s' === 'light') document.documentElement.removeAttribute('data-theme');
					else document.documentElement.setAttribute('data-theme', '%s');
					if (%t) document.documentElement.setAttribute('data-amoled', 'true');
					else document.documentElement.removeAttribute('data-amoled');
					return true;
				})()`, th.theme, th.theme, th.theme, th.amoled)
				if err := cdp.Eval(switchExpr, nil); err != nil {
					return err
				}
				// Даем CSS-переходам цвета завершиться перед замером computedStyle
				time.Sleep(350 * time.Millisecond)

				expr := `(() => {
					const item = document.querySelector('#customFontsList .custom-font-item');
					const badge = item ? item.querySelector('.custom-font-badge') : null;
					const body = document.querySelector('#customFontsModal .modal-body');
					const csItem = getComputedStyle(item);
					const csBody = getComputedStyle(body);
					const csBadge = getComputedStyle(badge);
					const rect = item.getBoundingClientRect();
					return {
						opacity: csItem.opacity,
						color: csItem.color,
						bg: csItem.backgroundColor,
						bodyBg: csBody.backgroundColor,
						badgeColor: csBadge.color,
						badgeBg: csBadge.backgroundColor,
						width: rect.width,
						height: rect.height
					};
				})()`
				var res struct {
					Opacity    string  `json:"opacity"`
					Color      string  `json:"color"`
					Bg         string  `json:"bg"`
					BodyBg     string  `json:"bodyBg"`
					BadgeColor string  `json:"badgeColor"`
					BadgeBg    string  `json:"badgeBg"`
					Width      float64 `json:"width"`
					Height     float64 `json:"height"`
				}
				if err := cdp.Eval(expr, &res); err != nil {
					return err
				}
				if res.Opacity != "1" || res.Width < 50 || res.Height < 20 {
					return fmt.Errorf("[%s] карточка шрифта невидима: %+v", th.name, res)
				}
				if res.Color == res.Bg || res.Color == res.BodyBg || res.BadgeColor == res.BadgeBg {
					return fmt.Errorf("[%s] цвет текста сливается с фоном: %+v", th.name, res)
				}
			}
			_ = cdp.Eval(`closeCustomFontsModal()`, nil)
			return nil
		})

		s.Case("Переключение тем/режима Код-Визуально, открытие модалок и сохранение статьи через UI", func() error {
			// Проверка модалки бэкапов и автосейвов + настроек
			err := cdp.Eval(`(async () => {
				if (typeof openGlobalSettings === 'function') openGlobalSettings();
				if (typeof switchGlobalSection === 'function') switchGlobalSection('blogview');
				if (typeof closeGlobalSettings === 'function') closeGlobalSettings();

				if (typeof openAutosaveManager === 'function') openAutosaveManager();
				if (typeof closeAutosaveManager === 'function') closeAutosaveManager();

				if (typeof openBackupManager === 'function') openBackupManager();
				if (typeof closeBackupManager === 'function') closeBackupManager();

				if (typeof setEditorMode === 'function') {
					setEditorMode('code');
					setEditorMode('visual');
				}
				return true;
			})()`, nil)
			if err != nil {
				return err
			}

			// Создаем и сохраняем статью прямо через форму редактора (#title, #contentVisual, #submitButton)
			saveScript := `(() => {
				const titleEl = document.getElementById('title');
				const visualEl = document.getElementById('contentVisual');
				const contentEl = document.getElementById('content');
				const submitBtn = document.getElementById('submitButton');
				if (!titleEl || !visualEl || !submitBtn) throw new Error('missing editor inputs');
				titleEl.value = 'UI Браузерная Статья E2E';
				visualEl.innerHTML = '<p>Текст, введенный реальным браузером через DOM.</p>';
				if (contentEl) contentEl.value = visualEl.innerHTML;
				submitBtn.click();
				return true;
			})()`
			if err := cdp.Eval(saveScript, nil); err != nil {
				return err
			}

			// Проверяем появление статьи на бэкенде
			deadline := time.Now().Add(6 * time.Second)
			found := false
			for time.Now().Before(deadline) {
				resp, reqErr := api.JSONReq(http.MethodGet, "/api/v1/posts", nil, nil)
				if reqErr == nil && strings.Contains(string(resp.Body), "UI Браузерная Статья E2E") {
					found = true
					break
				}
				time.Sleep(120 * time.Millisecond)
			}
			if !found {
				return fmt.Errorf("статья, сохранённая через DOM UI, не появилась в /api/v1/posts")
			}

			jsErrs, netErrs := cdp.TakeErrors()
			if len(jsErrs) > 0 || len(netErrs) > 0 {
				return fmt.Errorf("JS/Network ошибки во время UI-сценариев: js=%v net=%v", jsErrs, netErrs)
			}
			return nil
		})

		s.Case("Увеличение и уменьшение размера шрифта в статье не оставляет лишний вертикальный отступ", func() error {
			var res struct {
				Line1H   float64 `json:"line1H"`
				After32H float64 `json:"after32H"`
				After14H float64 `json:"after14H"`
				Col14H   float64 `json:"col14H"`
				Line2    string  `json:"line2"`
				Line3    string  `json:"line3"`
			}
			script := `(() => {
				const ve = document.getElementById('contentVisual');
				ve.innerHTML = '<p>Первая строка обычного текста</p><p>Вторая строка для проверки шрифта</p><p>Пустая строка</p>';
				ve.focus();

				const p1 = ve.children[0];
				const p2 = ve.children[1];
				const p3 = ve.children[2];

				// 1. Выделение текста на строке 2 -> 32px -> сразу 14px
				const r = document.createRange();
				r.selectNodeContents(p2);
				const sel = window.getSelection();
				sel.removeAllRanges();
				sel.addRange(r);
				VisualEngine.saveSelection();

				setFontSize('32');
				const after32H = p2.getBoundingClientRect().height;
				setFontSize('14');
				const after14H = p2.getBoundingClientRect().height;

				// 2. Схлопнутый курсор на строке 3 -> 32px -> сразу 14px
				const rCol = document.createRange();
				rCol.selectNodeContents(p3);
				rCol.collapse(true);
				sel.removeAllRanges();
				sel.addRange(rCol);
				VisualEngine.saveSelection();
				setFontSize('32');
				setFontSize('14');
				const col14H = p3.getBoundingClientRect().height;

				return {
					line1H: p1.getBoundingClientRect().height,
					after32H,
					after14H,
					col14H,
					line2: p2.innerHTML,
					line3: p3.innerHTML
				};
			})()`
			if err := cdp.Eval(script, &res); err != nil {
				return err
			}
			if res.After32H <= res.Line1H+10 {
				return fmt.Errorf("32px не увеличил высоту строки: line1=%.1f after32=%.1f", res.Line1H, res.After32H)
			}
			if res.After14H > res.Line1H+4 || strings.Contains(res.Line2, "32px") {
				return fmt.Errorf("после уменьшения до 14px остался лишний отступ/обёртка 32px: line1=%.1f after14=%.1f html=%s", res.Line1H, res.After14H, res.Line2)
			}
			if res.Col14H > res.Line1H+4 || strings.Contains(res.Line3, "32px") {
				return fmt.Errorf("при схлопнутом курсоре после 32px->14px остался лишний отступ/32px: line1=%.1f col14=%.1f html=%s", res.Line1H, res.Col14H, res.Line3)
			}
			return nil
		})

		s.Case("Поддержка табуляции (Tab и Shift+Tab) в визуальном и кодовом режимах", func() error {
			var tabRes struct {
				AfterTabHTML    string `json:"afterTabHTML"`
				AfterShiftHTML  string `json:"afterShiftHTML"`
				SavedHTML       string `json:"savedHTML"`
				CodeAfterTab    string `json:"codeAfterTab"`
				CodeAfterShift  string `json:"codeAfterShift"`
			}
			script := `(() => {
				if (typeof setEditorMode === 'function') setEditorMode('visual');
				const ve = document.getElementById('contentVisual');
				ve.innerHTML = '<p>Абзац с табом</p>';
				ve.focus();

				const p = ve.firstElementChild;
				const r = document.createRange();
				r.setStart(p.firstChild, 0);
				r.collapse(true);
				const sel = window.getSelection();
				sel.removeAllRanges();
				sel.addRange(r);
				VisualEngine.saveSelection();

				ve.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true }));
				const afterTabHTML = p.innerHTML;
				const savedHTML = VisualEngine.cleanContentForSave(ve.innerHTML);

				ve.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true, cancelable: true }));
				const afterShiftHTML = p.innerHTML;

				if (typeof setEditorMode === 'function') setEditorMode('code');
				const ta = document.getElementById('content');
				ta.value = '<p>Строка</p>';
				ta.selectionStart = 0;
				ta.selectionEnd = 0;
				ta.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true }));
				const codeAfterTab = ta.value;

				ta.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true, cancelable: true }));
				const codeAfterShift = ta.value;
				if (typeof setEditorMode === 'function') setEditorMode('visual');

				return { afterTabHTML, afterShiftHTML, savedHTML, codeAfterTab, codeAfterShift };
			})()`
			if err := cdp.Eval(script, &tabRes); err != nil {
				return err
			}
			if tabRes.AfterTabHTML != "&nbsp;&nbsp;&nbsp;&nbsp;Абзац с табом" {
				return fmt.Errorf("Tab в визуальном режиме сработал неверно: %q", tabRes.AfterTabHTML)
			}
			if !strings.Contains(tabRes.SavedHTML, "&nbsp;&nbsp;&nbsp;&nbsp;Абзац с табом") {
				return fmt.Errorf("табуляция потерялась при cleanContentForSave: %q", tabRes.SavedHTML)
			}
			if tabRes.AfterShiftHTML != "Абзац с табом" {
				return fmt.Errorf("Shift+Tab в визуальном режиме не убрал отступ: %q", tabRes.AfterShiftHTML)
			}
			if tabRes.CodeAfterTab != "\t<p>Строка</p>" || tabRes.CodeAfterShift != "<p>Строка</p>" {
				return fmt.Errorf("Tab/Shift+Tab в кодовом режиме неверны: tab=%q shift=%q", tabRes.CodeAfterTab, tabRes.CodeAfterShift)
			}
			return nil
		})
	})
}

func randWeirdStr(rng *rand.Rand) string {
	samples := []string{
		"Обычный тест",
		"<b>XSS</b><script>alert(1)</script>",
		"Emoji 🚀🔥✨",
		"Arabic مرحبا / CJK 日本語",
		"Quotes \" ' ` \\ / ; : & ?",
		strings.Repeat("АбВгД ", 40),
		"\t\n   \r",
		"{{CONTENT}} {{TITLE}}",
	}
	return samples[rng.Intn(len(samples))]
}
