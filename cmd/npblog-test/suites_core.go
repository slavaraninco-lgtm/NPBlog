package main

import (
	"encoding/json"
	"fmt"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"strings"
)

// runCoreSuites covers Static/i18n, Auth/CSRF/Sessions/IPFilter, Posts Lifecycle, and Autosaves & Drafts.
func runCoreSuites(tr *TestRunner, baseURL, workspaceRoot string) {
	// 1. Статика, встраиваемые ресурсы и локализация
	tr.Suite("01. Статика, ассеты и локализация", func(s *SuiteContext) {
		c := NewNPClient(baseURL)

		s.Case("GET / и /index.php отдают HTML редактора без необработанных маркеров", func() error {
			for _, route := range []string{"/", "/index.php"} {
				resp, err := c.RawReq(http.MethodGet, route, nil, nil)
				if err != nil {
					return err
				}
				if resp.Status != 200 {
					return fmt.Errorf("%s -> HTTP %d", route, resp.Status)
				}
				body := string(resp.Body)
				if !strings.Contains(body, "NPBlog") || !strings.Contains(body, "templateManagerDialog") {
					return fmt.Errorf("%s не содержит ожидаемую разметку редактора", route)
				}
				if strings.Contains(body, "{{CSRF_TOKEN}}") || strings.Contains(body, "{{VERSION}}") {
					return fmt.Errorf("%s содержит неподставленные шаблонные плейсхолдеры", route)
				}
			}
			return nil
		})

		s.Case("Ключевые CSS, JS модули и словари доступны (HTTP 200)", func() error {
			paths := []string{
				"/editor-style.css",
				"/editor-img.js",
				"/modals/modal.css",
				"/modals/modal.js",
				"/editorjs/ui.js",
				"/editorjs/formatting.js",
				"/editorjs/backups.js",
				"/editorjs/templates.js",
				"/editorjs/media.js",
				"/editorjs/network.js",
				"/lang/i18n.js",
				"/lang/languages.json",
				"/lang/ru.json",
				"/lang/en.json",
			}
			for _, p := range paths {
				resp, err := c.RawReq(http.MethodGet, p, nil, nil)
				if err != nil {
					return err
				}
				if resp.Status != 200 || len(resp.Body) == 0 {
					return fmt.Errorf("%s -> status=%d len=%d", p, resp.Status, len(resp.Body))
				}
			}
			return nil
		})

		s.Case("Словари ru.json и en.json валидны и имеют 100% паритет ключей", func() error {
			ruResp, err := c.RawReq(http.MethodGet, "/lang/ru.json", nil, nil)
			if err != nil {
				return err
			}
			enResp, err := c.RawReq(http.MethodGet, "/lang/en.json", nil, nil)
			if err != nil {
				return err
			}
			var ruMap, enMap map[string]interface{}
			if err := json.Unmarshal(ruResp.Body, &ruMap); err != nil {
				return fmt.Errorf("ru.json parse error: %w", err)
			}
			if err := json.Unmarshal(enResp.Body, &enMap); err != nil {
				return fmt.Errorf("en.json parse error: %w", err)
			}
			rk := flattenKeys("", ruMap)
			ek := flattenKeys("", enMap)
			for k := range rk {
				if !ek[k] {
					return fmt.Errorf("ключ %q есть в ru.json, но отсутствует в en.json", k)
				}
			}
			for k := range ek {
				if !rk[k] {
					return fmt.Errorf("ключ %q есть в en.json, но отсутствует в ru.json", k)
				}
			}
			return nil
		})
	})

	// 2. Авторизация, CSRF, сессии и IP-фильтр
	tr.Suite("02. Авторизация, CSRF, сессии и IP-фильтр", func(s *SuiteContext) {
		c := NewNPClient(baseURL)
		if _, err := c.RefreshCSRF(); err != nil {
			s.Case("Инициализация CSRF сессии", func() error { return err })
			return
		}

		s.Case("Отправка мутирующего запроса с поддельным CSRF-токеном отклоняется (403)", func() error {
			resp, err := c.JSONReq(http.MethodPost, "/save_post.php", map[string]string{
				"title":   "CSRF Hack Attempt",
				"content": "<p>Test</p>",
			}, map[string]string{"X-CSRF-Token": "forged-invalid-token-12345"})
			if err != nil {
				return err
			}
			if resp.Status != http.StatusForbidden {
				return fmt.Errorf("ожидался HTTP 403 при невалидном CSRF, получен %d (%s)", resp.Status, string(resp.Body))
			}
			return nil
		})

		s.Case("Мутирующий запрос без X-CSRF-Token при активной веб-сессии отклоняется (403)", func() error {
			resp, err := c.JSONReq(http.MethodPost, "/save_post.php", map[string]string{
				"title":   "Missing CSRF",
				"content": "<p>Test</p>",
			}, map[string]string{"X-CSRF-Token": "__DELETE__"})
			if err != nil {
				return err
			}
			if resp.Status != http.StatusForbidden {
				return fmt.Errorf("ожидался HTTP 403 при отсутствии CSRF, получен %d", resp.Status)
			}
			return nil
		})

		s.Case("Установка пароля -> хэширование bcrypt -> проверка блокировки гостей -> логин", func() error {
			resp, err := c.JSONReq(http.MethodPost, "/save_editor_settings.php", map[string]interface{}{
				"password_enabled": true,
				"new_password":     "UltraStrong#2026!",
			}, nil)
			if err != nil {
				return err
			}
			if _, err := resp.MustSuccessJSON(); err != nil {
				return err
			}

			rawCfg, err := os.ReadFile(filepath.Join(workspaceRoot, "editor_settings.json"))
			if err != nil {
				return err
			}
			if strings.Contains(string(rawCfg), "UltraStrong#2026!") {
				return fmt.Errorf("пароль сохранен в открытом виде в editor_settings.json")
			}

			// Анонимный клиент должен получать 401 на API и страницу логина на /
			anon := c.CloneFresh()
			unauthResp, err := anon.JSONReq(http.MethodGet, "/api/v1/posts", nil, nil)
			if err != nil {
				return err
			}
			if unauthResp.Status != http.StatusUnauthorized {
				return fmt.Errorf("анонимный запрос /api/v1/posts вернул %d вместо 401", unauthResp.Status)
			}

			homeResp, err := anon.RawReq(http.MethodGet, "/", nil, nil)
			if err != nil {
				return err
			}
			if !strings.Contains(string(homeResp.Body), "Вход в NPBlog") {
				return fmt.Errorf("для неавторизованного пользователя ожидалась форма входа")
			}

			// Неверный пароль
			badLogin, err := anon.JSONReq(http.MethodPost, "/login.php", map[string]string{"password": "wrong-password"}, nil)
			if err != nil {
				return err
			}
			badMap, _ := badLogin.JSON()
			if badMap["success"] == true {
				return fmt.Errorf("принят неверный пароль")
			}

			// Верный пароль
			goodLogin, err := anon.JSONReq(http.MethodPost, "/login.php", map[string]string{"password": "UltraStrong#2026!"}, nil)
			if err != nil {
				return err
			}
			goodMap, err := goodLogin.MustSuccessJSON()
			if err != nil {
				return err
			}
			tok, _ := goodMap["csrf_token"].(string)
			if tok == "" {
				return fmt.Errorf("логин не вернул csrf_token")
			}
			anon.CSRFToken = tok

			meResp, err := anon.JSONReq(http.MethodGet, "/api/v1/auth/me", nil, nil)
			if err != nil {
				return err
			}
			if meResp.Status != 200 {
				return fmt.Errorf("/api/v1/auth/me после логина вернул %d", meResp.Status)
			}
			return nil
		})

		s.Case("Мульти-сессии: просмотр активных сессий, завершение чужих и выход", func() error {
			sess2 := c.CloneFresh()
			login2, err := sess2.JSONReq(http.MethodPost, "/login.php", map[string]string{"password": "UltraStrong#2026!"}, nil)
			if err != nil {
				return err
			}
			m2, err := login2.MustSuccessJSON()
			if err != nil {
				return err
			}
			sess2.CSRFToken, _ = m2["csrf_token"].(string)

			sess3 := c.CloneFresh()
			login3, err := sess3.JSONReq(http.MethodPost, "/login.php", map[string]string{"password": "UltraStrong#2026!"}, nil)
			if err != nil {
				return err
			}
			m3, err := login3.MustSuccessJSON()
			if err != nil {
				return err
			}
			sess3.CSRFToken, _ = m3["csrf_token"].(string)

			listResp, err := sess2.JSONReq(http.MethodGet, "/get_sessions.php", nil, nil)
			if err != nil {
				return err
			}
			listMap, err := listResp.MustSuccessJSON()
			if err != nil {
				return err
			}
			sessionsArr, _ := listMap["sessions"].([]interface{})
			if len(sessionsArr) < 2 {
				return fmt.Errorf("ожидалось >= 2 активных сессий, получено %d", len(sessionsArr))
			}

			// Завершаем все остальные от имени sess2
			termResp, err := sess2.JSONReq(http.MethodPost, "/terminate_session.php", map[string]interface{}{"all_others": true}, nil)
			if err != nil {
				return err
			}
			if _, err := termResp.MustSuccessJSON(); err != nil {
				return err
			}

			// sess3 должна перестать работать
			check3, err := sess3.JSONReq(http.MethodGet, "/api/v1/posts", nil, nil)
			if err != nil {
				return err
			}
			if check3.Status != http.StatusUnauthorized {
				return fmt.Errorf("завершенная сессия sess3 всё ещё работает (HTTP %d)", check3.Status)
			}

			// Logout sess2
			outResp, err := sess2.JSONReq(http.MethodPost, "/logout.php", map[string]interface{}{}, nil)
			if err != nil {
				return err
			}
			if _, err := outResp.MustSuccessJSON(); err != nil {
				return err
			}
			afterOut, err := sess2.JSONReq(http.MethodGet, "/api/v1/posts", nil, nil)
			if err != nil {
				return err
			}
			if afterOut.Status != http.StatusUnauthorized {
				return fmt.Errorf("сессия после logout осталась активной")
			}

			// Восстанавливаем режим без пароля для следующих сьютов через логин основного клиента
			loginMain, err := c.JSONReq(http.MethodPost, "/login.php", map[string]string{"password": "UltraStrong#2026!"}, nil)
			if err != nil {
				return err
			}
			lm, err := loginMain.MustSuccessJSON()
			if err != nil {
				return err
			}
			c.CSRFToken, _ = lm["csrf_token"].(string)

			// Нельзя отключить пароль с неверным старым паролем
			failDis, err := c.JSONReq(http.MethodPost, "/save_editor_settings.php", map[string]interface{}{
				"password_enabled": false,
				"old_password":     "bad-old-pass",
			}, nil)
			if err != nil {
				return err
			}
			fdMap, _ := failDis.JSON()
			if fdMap["success"] == true {
				return fmt.Errorf("отключение пароля прошло с неверным old_password")
			}

			disResp, err := c.JSONReq(http.MethodPost, "/save_editor_settings.php", map[string]interface{}{
				"password_enabled": false,
				"old_password":     "UltraStrong#2026!",
			}, nil)
			if err != nil {
				return err
			}
			_, err = disResp.MustSuccessJSON()
			return err
		})

		s.Case("IP-белый список allowed_ips.txt блокирует посторонний IP и пускает локальный", func() error {
			ipFile := filepath.Join(workspaceRoot, "allowed_ips.txt")
			defer func() {
				_ = os.Remove(ipFile)
				_, _ = c.JSONReq(http.MethodPost, "/save_editor_settings.php", map[string]interface{}{"ip_whitelist_enabled": false}, nil)
			}()

			enResp, err := c.JSONReq(http.MethodPost, "/save_editor_settings.php", map[string]interface{}{
				"ip_whitelist_enabled": true,
			}, nil)
			if err != nil {
				return err
			}
			if _, err := enResp.MustSuccessJSON(); err != nil {
				return err
			}

			// HandleSaveEditorSettings автоматически добавляет IP вызывающего, поэтому перезаписываем файл после включения
			if err := os.WriteFile(ipFile, []byte("10.250.250.250\n"), 0644); err != nil {
				return err
			}

			blockedResp, err := c.JSONReq(http.MethodGet, "/api/v1/system/status", nil, nil)
			if err != nil {
				return err
			}
			if blockedResp.Status != http.StatusForbidden {
				return fmt.Errorf("IPFilter пропустил неразрешённый localhost: HTTP %d", blockedResp.Status)
			}
			_ = os.WriteFile(ipFile, []byte("127.0.0.1\n::1\n"), 0644)
			okResp, err := c.JSONReq(http.MethodGet, "/api/v1/system/status", nil, nil)
			if err != nil {
				return err
			}
			if okResp.Status != http.StatusOK {
				return fmt.Errorf("при разрешённом 127.0.0.1 ожидался 200, получен %d", okResp.Status)
			}
			return nil
		})
	})

	// 3. Полный цикл статей: CRUD, XSS/Юникод/крупные тела, перенумерация, закреп, RSS, SEO
	tr.Suite("03. Статьи: CRUD, экстремальный контент, перенумерация, RSS", func(s *SuiteContext) {
		c := NewNPClient(baseURL)
		if _, err := c.RefreshCSRF(); err != nil {
			s.Case("CSRF init", func() error { return err })
			return
		}

		var firstPostID int
		s.Case("Создание, чтение, обновление (с XSS/Unicode/RTL/большим HTML) и публикация HTML-файла", func() error {
			extremeTitle := "Статья 🚀 日本語 العربية — <script>alert('xss')</script> & тест"
			largeBlock := strings.Repeat("<p>Абзац с текстом <strong>жирным</strong> и <em>курсивом</em> — проверям нагрузку 12345.</p>\n", 2500)
			content := "<h2>Заголовок секции</h2>" + largeBlock + `<p onclick="alert(1)">Обычный параграф</p><img src="/data/uploads/test.png" alt="test">`

			createResp, err := c.JSONReq(http.MethodPost, "/save_post.php", map[string]interface{}{
				"title":   extremeTitle,
				"content": content,
			}, nil)
			if err != nil {
				return err
			}
			cm, err := createResp.MustSuccessJSON()
			if err != nil {
				return err
			}
			idFloat, _ := cm["id"].(float64)
			firstPostID = int(idFloat)
			if firstPostID <= 0 {
				return fmt.Errorf("некорректный ID созданной статьи: %v", cm["id"])
			}

			// Чтение через legacy и REST
			getResp, err := c.JSONReq(http.MethodGet, fmt.Sprintf("/get_post_content.php?id=%d", firstPostID), nil, nil)
			if err != nil {
				return err
			}
			gm, err := getResp.MustSuccessJSON()
			if err != nil {
				return err
			}
			if gm["title"] != extremeTitle {
				return fmt.Errorf("заголовок не совпал: %v != %q", gm["title"], extremeTitle)
			}

			restGet, err := c.JSONReq(http.MethodGet, fmt.Sprintf("/api/v1/posts/%d", firstPostID), nil, nil)
			if err != nil {
				return err
			}
			if restGet.Status != 200 {
				return fmt.Errorf("REST GET post вернул %d", restGet.Status)
			}

			// Проверка сгенерированного HTML файла статьи по HTTP
			postHTMLResp, err := c.RawReq(http.MethodGet, fmt.Sprintf("/data/blog/post-%d.html", firstPostID), nil, nil)
			if err != nil {
				return err
			}
			if postHTMLResp.Status != 200 {
				return fmt.Errorf("GET /data/blog/post-%d.html -> HTTP %d", firstPostID, postHTMLResp.Status)
			}

			// Обновление через REST PUT + legacy update_post
			updResp, err := c.JSONReq(http.MethodPut, fmt.Sprintf("/api/v1/posts/%d", firstPostID), map[string]interface{}{
				"title":   "Обновленный заголовок v2",
				"content": "<p>Новый контент статьи</p>",
			}, nil)
			if err != nil {
				return err
			}
			if updResp.Status != 200 {
				return fmt.Errorf("PUT /api/v1/posts/%d -> %d (%s)", firstPostID, updResp.Status, string(updResp.Body))
			}

			// Пустой заголовок должен отклоняться при создании
			emptyTitleResp, err := c.JSONReq(http.MethodPost, "/save_post.php", map[string]string{
				"title":   "",
				"content": "<p>Без заголовка</p>",
			}, nil)
			if err != nil {
				return err
			}
			em, _ := emptyTitleResp.JSON()
			if em["success"] == true {
				return fmt.Errorf("сервер разрешил создать статью с пустым заголовком")
			}
			return nil
		})

		s.Case("Закрепление, перенумерация, удаление, предпросмотр, регенерация и RSS", func() error {
			// Создаем еще 2 статьи, удаляем первую, чтобы проверить дыры и renumber
			r2, err := c.JSONReq(http.MethodPost, "/save_post.php", map[string]string{"title": "Вторая статья", "content": "<p>2</p>"}, nil)
			if err != nil {
				return err
			}
			m2, err := r2.MustSuccessJSON()
			if err != nil {
				return err
			}
			id2 := int(m2["id"].(float64))

			r3, err := c.JSONReq(http.MethodPost, "/save_post.php", map[string]string{"title": "Третья статья", "content": "<p>3</p>"}, nil)
			if err != nil {
				return err
			}
			if _, err := r3.MustSuccessJSON(); err != nil {
				return err
			}

			// Pin / Unpin
			pinResp, err := c.JSONReq(http.MethodPost, "/toggle_pin_post.php", map[string]int{"id": id2}, nil)
			if err != nil {
				return err
			}
			pm, err := pinResp.MustSuccessJSON()
			if err != nil || pm["pinned"] != true {
				return fmt.Errorf("закрепление не сработало: %v", pinResp.Body)
			}

			// Delete firstPostID -> удаляем и проверяем check / renumber
			delResp, err := c.JSONReq(http.MethodPost, "/delete_post.php", map[string]int{"id": firstPostID}, nil)
			if err != nil {
				return err
			}
			if _, err := delResp.MustSuccessJSON(); err != nil {
				return err
			}

			chkResp, err := c.JSONReq(http.MethodPost, "/renumber_posts.php", map[string]string{"action": "check"}, nil)
			if err != nil {
				return err
			}
			if _, err := chkResp.MustSuccessJSON(); err != nil {
				return err
			}

			renResp, err := c.JSONReq(http.MethodPost, "/renumber_posts.php", map[string]string{}, nil)
			if err != nil {
				return err
			}
			if _, err := renResp.MustSuccessJSON(); err != nil {
				return err
			}

			// Включаем RSS и проверяем генерацию feed.xml
			rssSet, err := c.JSONReq(http.MethodPost, "/save_editor_settings.php", map[string]interface{}{
				"rss_enabled":     true,
				"rss_base_url":    "https://example.test",
				"rss_title":       "NPBlog Test Feed",
				"rss_description": "Automated feed test",
			}, nil)
			if err != nil {
				return err
			}
			if _, err := rssSet.MustSuccessJSON(); err != nil {
				return err
			}
			if _, err := os.Stat(filepath.Join(workspaceRoot, "data", "feed.xml")); err != nil {
				return fmt.Errorf("feed.xml не сгенерирован: %w", err)
			}

			// Регенерация постов, SEO и предпросмотр
			if resp, err := c.JSONReq(http.MethodPost, "/regenerate_posts.php", map[string]string{}, nil); err != nil {
				return err
			} else if _, err := resp.MustSuccessJSON(); err != nil {
				return err
			}
			if resp, err := c.JSONReq(http.MethodPost, "/regenerate_seo.php", map[string]string{}, nil); err != nil {
				return err
			} else if _, err := resp.MustSuccessJSON(); err != nil {
				return err
			}
			if resp, err := c.JSONReq(http.MethodPost, "/save_post_preview.php", map[string]string{"action": "check", "postId": "1"}, nil); err != nil {
				return err
			} else if _, err := resp.MustSuccessJSON(); err != nil {
				return err
			}
			return nil
		})
	})

	// 4. Автосохранения и Черновики
	tr.Suite("04. Автосохранения и Черновики", func(s *SuiteContext) {
		c := NewNPClient(baseURL)
		if _, err := c.RefreshCSRF(); err != nil {
			s.Case("CSRF init", func() error { return err })
			return
		}

		s.Case("Полный цикл автосохранений (legacy + REST) и граничные случаи", func() error {
			// Пустое автосохранение должно отклоняться
			emptyAS, err := c.JSONReq(http.MethodPost, "/save_autosave.php", map[string]interface{}{
				"id": "empty-as", "title": "", "content": "",
			}, nil)
			if err != nil {
				return err
			}
			em, _ := emptyAS.JSON()
			if em["success"] == true {
				return fmt.Errorf("сервер принял пустое автосохранение")
			}

			// Валидное автосохранение (JSON и multipart)
			as1, err := c.JSONReq(http.MethodPost, "/save_autosave.php", map[string]interface{}{
				"id": "as-custom-1", "postId": 0, "title": "Автосейв 1", "content": "<p>Несохраненный текст</p>",
			}, nil)
			if err != nil {
				return err
			}
			if _, err := as1.MustSuccessJSON(); err != nil {
				return err
			}

			as2, err := c.MultipartReq("/save_autosave.php", map[string]string{
				"id": "as-custom-2", "postId": "7", "title": "Автосейв 2", "content": "<p>Второй текст</p>",
			}, nil, nil)
			if err != nil {
				return err
			}
			if _, err := as2.MustSuccessJSON(); err != nil {
				return err
			}

			// Чтение конкретного и списка
			get1, err := c.JSONReq(http.MethodGet, "/get_autosave.php?id=as-custom-1", nil, nil)
			if err != nil {
				return err
			}
			if _, err := get1.MustSuccessJSON(); err != nil {
				return err
			}

			allResp, err := c.JSONReq(http.MethodGet, "/get_all_autosaves.php", nil, nil)
			if err != nil {
				return err
			}
			allMap, err := allResp.MustSuccessJSON()
			if err != nil {
				return err
			}
			arr, _ := allMap["autosaves"].([]interface{})
			if len(arr) < 2 {
				return fmt.Errorf("ожидалось >= 2 автосохранений, найдено %d", len(arr))
			}

			// Настройки интервала автосохранения
			setResp, err := c.JSONReq(http.MethodPost, "/save_autosave_settings.php", map[string]interface{}{
				"enabled": true, "interval": 30,
			}, nil)
			if err != nil {
				return err
			}
			if _, err := setResp.MustSuccessJSON(); err != nil {
				return err
			}

			// Удаление одного, затем всех
			if d1, err := c.JSONReq(http.MethodPost, "/delete_autosave.php", map[string]string{"id": "as-custom-1"}, nil); err != nil {
				return err
			} else if _, err := d1.MustSuccessJSON(); err != nil {
				return err
			}

			if da, err := c.JSONReq(http.MethodPost, "/delete_all_autosaves.php", map[string]string{}, nil); err != nil {
				return err
			} else if _, err := da.MustSuccessJSON(); err != nil {
				return err
			}
			return nil
		})

		s.Case("Полный цикл черновиков (сохранение, спецсимволы в имени, список, удаление)", func() error {
			drResp, err := c.JSONReq(http.MethodPost, "/save_draft.php", map[string]string{
				"title":   "Черновик №1 / \\ : * ? \" <> тест",
				"content": "<p>Контент черновика с эмодзи 🔥</p>",
			}, nil)
			if err != nil {
				return err
			}
			dm, err := drResp.MustSuccessJSON()
			if err != nil {
				return err
			}
			fname, _ := dm["filename"].(string)
			if fname == "" {
				return fmt.Errorf("пустой filename черновика")
			}

			listResp, err := c.JSONReq(http.MethodGet, "/get_drafts.php", nil, nil)
			if err != nil {
				return err
			}
			lm, err := listResp.MustSuccessJSON()
			if err != nil {
				return err
			}
			drafts, _ := lm["drafts"].([]interface{})
			if len(drafts) == 0 {
				return fmt.Errorf("сохраненный черновик не найден в /get_drafts.php")
			}

			// Удаляем через REST DELETE /api/v1/drafts/{filename}
			delResp, err := c.JSONReq(http.MethodDelete, "/api/v1/drafts/"+url.PathEscape(fname), nil, nil)
			if err != nil {
				return err
			}
			if delResp.Status != 200 {
				return fmt.Errorf("DELETE draft вернул %d: %s", delResp.Status, string(delResp.Body))
			}
			return nil
		})
	})
}

func flattenKeys(prefix string, m map[string]interface{}) map[string]bool {
	out := make(map[string]bool)
	for k, v := range m {
		key := k
		if prefix != "" {
			key = prefix + "." + k
		}
		if sub, ok := v.(map[string]interface{}); ok {
			for sk := range flattenKeys(key, sub) {
				out[sk] = true
			}
		} else {
			out[key] = true
		}
	}
	return out
}
