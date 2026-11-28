package main

import (
	"archive/zip"
	"bytes"
	"encoding/base64"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"strings"
)

// runFeatureSuites covers Backups/History, Templates/Includes, Media/Fonts/Smiles/Backgrounds/Themes, Multi-Blog, and Settings/Integrity.
func runFeatureSuites(tr *TestRunner, baseURL, workspaceRoot string) {
	// 5. Бэкапы и История действий
	tr.Suite("05. Бэкапы, восстановление и история", func(s *SuiteContext) {
		c := NewNPClient(baseURL)
		if _, err := c.RefreshCSRF(); err != nil {
			s.Case("CSRF init", func() error { return err })
			return
		}

		s.Case("Автосоздание бэкапа при изменении статьи -> просмотр -> восстановление -> удаление", func() error {
			cr, err := c.JSONReq(http.MethodPost, "/save_post.php", map[string]string{
				"title":   "Статья для бэкапа",
				"content": "<p>Исходная версия v1</p>",
			}, nil)
			if err != nil {
				return err
			}
			cm, err := cr.MustSuccessJSON()
			if err != nil {
				return err
			}
			postID := int(cm["id"].(float64))

			ur, err := c.JSONReq(http.MethodPost, "/update_post.php", map[string]interface{}{
				"id":      postID,
				"title":   "Статья для бэкапа v2",
				"content": "<p>Измененная версия v2</p>",
			}, nil)
			if err != nil {
				return err
			}
			if _, err := ur.MustSuccessJSON(); err != nil {
				return err
			}

			// Читаем список бэкапов по postId и общий список
			bList, err := c.JSONReq(http.MethodGet, fmt.Sprintf("/get_backups.php?postId=%d", postID), nil, nil)
			if err != nil {
				return err
			}
			bm, err := bList.MustSuccessJSON()
			if err != nil {
				return err
			}
			backups, _ := bm["backups"].([]interface{})
			if len(backups) == 0 {
				return fmt.Errorf("бэкап не был создан после update_post")
			}

			allB, err := c.JSONReq(http.MethodGet, "/get_backups.php", nil, nil)
			if err != nil {
				return err
			}
			if _, err := allB.MustSuccessJSON(); err != nil {
				return err
			}

			// view_backup.php должен инжектить <base href="...">
			viewResp, err := c.RawReq(http.MethodGet, fmt.Sprintf("/view_backup.php?postId=%d&backup=1", postID), nil, nil)
			if err != nil {
				return err
			}
			if viewResp.Status != 200 || !strings.Contains(string(viewResp.Body), "<base href=") {
				return fmt.Errorf("view_backup.php вернул %d или без <base href>", viewResp.Status)
			}

			// get_backup_content.php
			bcResp, err := c.JSONReq(http.MethodPost, "/get_backup_content.php", map[string]interface{}{
				"postId": postID, "backupNumber": 1,
			}, nil)
			if err != nil {
				return err
			}
			bcm, err := bcResp.MustSuccessJSON()
			if err != nil {
				return err
			}
			if contentStr, _ := bcm["content"].(string); !strings.Contains(contentStr, "Исходная версия v1") {
				return fmt.Errorf("содержимое бэкапа не соответствует v1")
			}

			// restore_backup.php
			restResp, err := c.JSONReq(http.MethodPost, "/restore_backup.php", map[string]interface{}{
				"postId": postID, "backupNumber": 1,
			}, nil)
			if err != nil {
				return err
			}
			if _, err := restResp.MustSuccessJSON(); err != nil {
				return err
			}

			afterRestore, err := c.JSONReq(http.MethodGet, fmt.Sprintf("/get_post_content.php?id=%d", postID), nil, nil)
			if err != nil {
				return err
			}
			arm, err := afterRestore.MustSuccessJSON()
			if err != nil {
				return err
			}
			if cStr, _ := arm["content"].(string); !strings.Contains(cStr, "Исходная версия v1") {
				return fmt.Errorf("восстановление бэкапа не вернуло текст v1: %q", cStr)
			}

			// delete_backup.php
			delB, err := c.JSONReq(http.MethodPost, "/delete_backup.php", map[string]interface{}{
				"postId": postID, "backupNumber": 1,
			}, nil)
			if err != nil {
				return err
			}
			_, err = delB.MustSuccessJSON()
			return err
		})

		s.Case("История действий: сохранение, получение, очистка", func() error {
			if r, err := c.JSONReq(http.MethodPost, "/save_history.php", map[string]string{"action": "custom_test", "details": "Запись теста"}, nil); err != nil {
				return err
			} else if _, err := r.MustSuccessJSON(); err != nil {
				return err
			}
			if r, err := c.JSONReq(http.MethodGet, "/get_history.php", nil, nil); err != nil {
				return err
			} else if _, err := r.MustSuccessJSON(); err != nil {
				return err
			}
			if r, err := c.JSONReq(http.MethodPost, "/clear_history.php", map[string]string{}, nil); err != nil {
				return err
			} else if _, err := r.MustSuccessJSON(); err != nil {
				return err
			}
			return nil
		})
	})

	// 6. Шаблоны (HTML/ZIP + ZipSlip) и Вставки (Includes)
	tr.Suite("06. Шаблоны (HTML/ZIP/ZipSlip) и Вставки", func(s *SuiteContext) {
		c := NewNPClient(baseURL)
		if _, err := c.RefreshCSRF(); err != nil {
			s.Case("CSRF init", func() error { return err })
			return
		}

		validTpl := `<!DOCTYPE html><html><head><title>{{TITLE}}</title><meta name="post-id" content="{{POST_ID}}">{{META_TAGS}}{{CUSTOM_FONTS}}<style>.page{max-width:800px;}</style></head><body{{BODY_STYLE}}>{{CONTENT_WRAPPER_START}}<h1>{{TITLE}}</h1><time>{{DATE}}</time><article class="content">{{CONTENT}}</article>{{CONTENT_WRAPPER_END}}<script>console.log('ok');</script></body></html>`

		s.Case("Автоинициализация шаблонов, details=1 и валидация обязательных плейсхолдеров", func() error {
			r, err := c.JSONReq(http.MethodGet, "/get_templates.php?details=1", nil, nil)
			if err != nil {
				return err
			}
			if _, err := r.MustSuccessJSON(); err != nil {
				return err
			}

			// Шаблон без {{CONTENT}} обязан отклоняться
			badTplResp, err := c.JSONReq(http.MethodPost, "/save_template.php", map[string]string{
				"name": "broken_tpl", "title": "Broken", "description": "bad",
				"code": "<html><body><h1>{{TITLE}}</h1></body></html>",
			}, nil)
			if err != nil {
				return err
			}
			bm, _ := badTplResp.JSON()
			if bm["success"] == true {
				return fmt.Errorf("сервер сохранил неполный шаблон без обязательных плейсхолдеров")
			}

			// Загружаем новый шаблон через upload_template.php, затем редактируем его через save_template.php
			upHTML, err := c.MultipartReq("/upload_template.php", nil, []UploadFileItem{{
				Field: "template_file", Filename: "custom_pro.html", Data: []byte(validTpl),
			}}, nil)
			if err != nil {
				return err
			}
			upm, err := upHTML.MustSuccessJSON()
			if err != nil {
				return err
			}
			tplName, _ := upm["name"].(string)
			if tplName == "" {
				tplName = "custom_pro"
			}

			// Валидное обновление существующего шаблона
			okTplResp, err := c.JSONReq(http.MethodPost, "/save_template.php", map[string]string{
				"name": tplName, "title": "Custom Pro", "description": "Valid test template",
				"code": validTpl,
			}, nil)
			if err != nil {
				return err
			}
			if _, err := okTplResp.MustSuccessJSON(); err != nil {
				return err
			}

			// Применение шаблона и удаление
			apResp, err := c.JSONReq(http.MethodPost, "/apply_template.php", map[string]string{
				"template_name": tplName, "mode": "default",
			}, nil)
			if err != nil {
				return err
			}
			if _, err := apResp.MustSuccessJSON(); err != nil {
				return err
			}

			// Возвращаем дефолт на main (или NPBlog) и удаляем кастомный
			_, _ = c.JSONReq(http.MethodPost, "/apply_template.php", map[string]string{"template_name": "main", "mode": "default"}, nil)
			delResp, err := c.JSONReq(http.MethodPost, "/delete_template.php", map[string]string{"name": tplName}, nil)
			if err != nil {
				return err
			}
			_, err = delResp.MustSuccessJSON()
			return err
		})

		s.Case("Загрузка валидного ZIP-шаблона и блокировка Zip-Slip traversal в архиве", func() error {
			// Валидный ZIP с main.html
			var buf bytes.Buffer
			zw := zip.NewWriter(&buf)
			f, _ := zw.Create("zipped_tpl/main.html")
			_, _ = f.Write([]byte(validTpl))
			_ = zw.Close()

			upOk, err := c.MultipartReq("/upload_template.php", nil, []UploadFileItem{{
				Field: "template_file", Filename: "zipped_tpl.zip", Data: buf.Bytes(),
			}}, nil)
			if err != nil {
				return err
			}
			if _, err := upOk.MustSuccessJSON(); err != nil {
				return err
			}

			// Вредоносный Zip-Slip ZIP
			var evilBuf bytes.Buffer
			ezw := zip.NewWriter(&evilBuf)
			ef, _ := ezw.Create("../../zipslip_pwn.html")
			_, _ = ef.Write([]byte(validTpl))
			_ = ezw.Close()

			upEvil, err := c.MultipartReq("/upload_template.php", nil, []UploadFileItem{{
				Field: "template_file", Filename: "evil_zipslip.zip", Data: evilBuf.Bytes(),
			}}, nil)
			if err != nil {
				return err
			}
			em, _ := upEvil.JSON()
			if em["success"] == true {
				return fmt.Errorf("сервер принял ZIP с Zip-Slip путём ../../")
			}
			if _, statErr := os.Stat(filepath.Join(workspaceRoot, "zipslip_pwn.html")); statErr == nil {
				return fmt.Errorf("КРИТИЧНО: Zip-Slip создал файл за пределами каталога шаблонов!")
			}
			return nil
		})

		s.Case("CRUD вставок (Includes) через legacy и REST", func() error {
			cr, err := c.JSONReq(http.MethodPost, "/save_include.php", map[string]string{
				"name": "promo_banner", "content": "<div class=\"banner\">Promo {{VAR}}</div>",
			}, nil)
			if err != nil {
				return err
			}
			cm, err := cr.MustSuccessJSON()
			if err != nil {
				return err
			}
			fname, _ := cm["filename"].(string)
			if fname == "" {
				fname = "promo_banner.html"
			}

			if r, err := c.JSONReq(http.MethodGet, "/get_includes.php", nil, nil); err != nil {
				return err
			} else if _, err := r.MustSuccessJSON(); err != nil {
				return err
			}

			getC, err := c.JSONReq(http.MethodGet, "/get_include_content.php?filename="+fname, nil, nil)
			if err != nil {
				return err
			}
			gm, err := getC.MustSuccessJSON()
			if err != nil {
				return err
			}
			if sContent, _ := gm["content"].(string); !strings.Contains(sContent, "Promo") {
				return fmt.Errorf("неверное содержимое вставки: %q", sContent)
			}

			if d, err := c.JSONReq(http.MethodPost, "/delete_include.php", map[string]string{"filename": fname}, nil); err != nil {
				return err
			} else if _, err := d.MustSuccessJSON(); err != nil {
				return err
			}
			return nil
		})
	})

	// 7. Медиа, Шрифты, Смайлы, Фоны, Кастомная тема и Формулы
	tr.Suite("07. Медиа, шрифты, смайлы, фоны, темы и формулы", func(s *SuiteContext) {
		c := NewNPClient(baseURL)
		if _, err := c.RefreshCSRF(); err != nil {
			s.Case("CSRF init", func() error { return err })
			return
		}

		pngBytes, _ := base64.StdEncoding.DecodeString("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=")

		s.Case("Изображения, SVG XSS-санитайзер, сетка и base64 редактор изображений", func() error {
			// Валидный PNG
			upImg, err := c.MultipartReq("/upload_image.php", nil, []UploadFileItem{{
				Field: "image", Filename: "pixel.png", Data: pngBytes,
			}}, nil)
			if err != nil {
				return err
			}
			if _, err := upImg.MustSuccessJSON(); err != nil {
				return err
			}

			// Запрещенное расширение .php
			badExt, err := c.MultipartReq("/upload_image.php", nil, []UploadFileItem{{
				Field: "image", Filename: "shell.php", Data: []byte("<?php echo 1; ?>"),
			}}, nil)
			if err != nil {
				return err
			}
			bm, _ := badExt.JSON()
			if bm["success"] == true {
				return fmt.Errorf("загружен .php как изображение")
			}

			// SVG со <script>
			evilSVG, err := c.MultipartReq("/upload_image.php", nil, []UploadFileItem{{
				Field: "image", Filename: "xss.svg", Data: []byte(`<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>`),
			}}, nil)
			if err != nil {
				return err
			}
			sm, _ := evilSVG.JSON()
			if sm["success"] == true {
				return fmt.Errorf("загружен SVG с <script>")
			}

			// Сетка
			gridResp, err := c.MultipartReq("/upload_images_grid.php", nil, []UploadFileItem{
				{Field: "images[]", Filename: "g1.png", Data: pngBytes},
				{Field: "images[]", Filename: "g2.png", Data: pngBytes},
			}, nil)
			if err != nil {
				return err
			}
			if _, err := gridResp.MustSuccessJSON(); err != nil {
				return err
			}

			// Base64 edited image
			editResp, err := c.JSONReq(http.MethodPost, "/save_edited_image.php", map[string]string{
				"image_data": "data:image/png;base64," + base64.StdEncoding.EncodeToString(pngBytes),
			}, nil)
			if err != nil {
				return err
			}
			_, err = editResp.MustSuccessJSON()
			return err
		})

		s.Case("Документы, Аудио, Видео + Range 206 стриминг", func() error {
			// Document
			docUp, err := c.MultipartReq("/upload_document.php", nil, []UploadFileItem{{
				Field: "document", Filename: "spec.txt", Data: []byte("NPBlog document test"),
			}}, nil)
			if err != nil {
				return err
			}
			if _, err := docUp.MustSuccessJSON(); err != nil {
				return err
			}
			docsResp, err := c.JSONReq(http.MethodGet, "/get_documents.php", nil, nil)
			if err != nil {
				return err
			}
			if _, err := docsResp.MustSuccessJSON(); err != nil {
				return err
			}

			// Audio
			audUp, err := c.MultipartReq("/upload_audio.php", nil, []UploadFileItem{{
				Field: "audio", Filename: "tone.wav", Data: []byte("RIFF\x24\x00\x00\x00WAVEfmt "),
			}}, nil)
			if err != nil {
				return err
			}
			if _, err := audUp.MustSuccessJSON(); err != nil {
				return err
			}

			// Video + Range 206
			vidUp, err := c.MultipartReq("/upload_video.php", nil, []UploadFileItem{{
				Field: "video", Filename: "clip.mp4", Data: []byte("0123456789ABCDEF"),
			}}, nil)
			if err != nil {
				return err
			}
			vm, err := vidUp.MustSuccessJSON()
			if err != nil {
				return err
			}
			vidURL, _ := vm["url"].(string)
			if vidURL == "" {
				vidURL, _ = vm["path"].(string)
			}
			if vidURL == "" {
				return fmt.Errorf("upload_video не вернул URL")
			}

			rangeResp, err := c.RawReq(http.MethodGet, vidURL, nil, map[string]string{"Range": "bytes=0-3"})
			if err != nil {
				return err
			}
			if rangeResp.Status != http.StatusPartialContent || string(rangeResp.Body) != "0123" {
				return fmt.Errorf("ожидался Range 206 '0123', получено %d %q", rangeResp.Status, string(rangeResp.Body))
			}
			return nil
		})

		s.Case("Пользовательские шрифты + CSS @font-face и Смайлы", func() error {
			// Загружаем .ttf заголовок (0x00010000 sfnt)
			ttfHeader := []byte{0x00, 0x01, 0x00, 0x00, 0x00, 0x02, 0x00, 0x10, 0x00, 0x00, 0x00, 0x00}
			fUp, err := c.MultipartReq("/upload_font.php", nil, []UploadFileItem{{
				Field: "font", Filename: "TestDisplayFont.ttf", Data: ttfHeader,
			}}, nil)
			if err != nil {
				return err
			}
			if _, err := fUp.MustSuccessJSON(); err != nil {
				return err
			}

			fList, err := c.JSONReq(http.MethodGet, "/get_custom_fonts.php", nil, nil)
			if err != nil {
				return err
			}
			fm, err := fList.MustSuccessJSON()
			if err != nil {
				return err
			}
			fontsArr, _ := fm["fonts"].([]interface{})
			if len(fontsArr) == 0 {
				return fmt.Errorf("загруженный шрифт не найден в get_custom_fonts.php")
			}

			cssResp, err := c.RawReq(http.MethodGet, "/get_custom_fonts_css.php", nil, nil)
			if err != nil {
				return err
			}
			if cssResp.Status != 200 || !strings.Contains(string(cssResp.Body), "TestDisplayFont") {
				return fmt.Errorf("get_custom_fonts_css.php не содержит @font-face: %s", string(cssResp.Body))
			}

			// Загрузка и удаление смайлов
			smUp, err := c.MultipartReq("/upload_smiles.php", map[string]string{"setName": "pack_test"}, []UploadFileItem{{
				Field: "smiles[]", Filename: "smile1.png", Data: pngBytes,
			}}, nil)
			if err != nil {
				return err
			}
			if _, err := smUp.MustSuccessJSON(); err != nil {
				return err
			}
			if delSm, err := c.JSONReq(http.MethodPost, "/delete_smile_set.php", map[string]string{"setName": "pack_test"}, nil); err != nil {
				return err
			} else if _, err := delSm.MustSuccessJSON(); err != nil {
				return err
			}
			return nil
		})

		s.Case("Фоны (глобальный/блога/статьи), Кастомная тема CSS и Формулы", func() error {
			bgGlobal, err := c.MultipartReq("/upload_global_background.php", map[string]string{"mode": "tiled", "scope": "all"}, []UploadFileItem{{
				Field: "background", Filename: "bg.png", Data: pngBytes,
			}}, nil)
			if err != nil {
				return err
			}
			if _, err := bgGlobal.MustSuccessJSON(); err != nil {
				return err
			}
			if rmG, err := c.JSONReq(http.MethodPost, "/remove_global_background.php", map[string]string{}, nil); err != nil {
				return err
			} else if _, err := rmG.MustSuccessJSON(); err != nil {
				return err
			}

			// Загрузка валидной кастомной CSS-темы и отклонение бинарного файла с .css расширением
			goodTheme, err := c.MultipartReq("/upload_custom_theme.php", nil, []UploadFileItem{{
				Field: "themeFile", Filename: "custom.css", Data: []byte(":root { --primary-color: #2563eb; }\nbody { color: var(--text-color); }\n"),
			}}, nil)
			if err != nil {
				return err
			}
			if _, err := goodTheme.MustSuccessJSON(); err != nil {
				return err
			}

			badBinTheme, err := c.MultipartReq("/upload_custom_theme.php", nil, []UploadFileItem{{
				Field: "themeFile", Filename: "fake.css", Data: []byte{0x00, 0x01, 0x02, 0xFF, 0xFE},
			}}, nil)
			if err != nil {
				return err
			}
			btm, _ := badBinTheme.JSON()
			if btm["success"] == true {
				return fmt.Errorf("сервер принял бинарный файл вместо CSS темы")
			}

			dlTheme, err := c.RawReq(http.MethodGet, "/download_theme.php", nil, nil)
			if err != nil {
				return err
			}
			if dlTheme.Status != 200 || !strings.Contains(string(dlTheme.Body), "--bg-color") {
				return fmt.Errorf("download_theme.php не вернул шаблон стилей")
			}

			// Формулы REST API (проверка валидации пустого запроса + вызова рендерера)
			badF, err := c.JSONReq(http.MethodPost, "/api/v1/formulas/render", map[string]string{
				"tex": "",
			}, nil)
			if err != nil {
				return err
			}
			if badF.Status != http.StatusBadRequest {
				return fmt.Errorf("/api/v1/formulas/render при пустом tex вернул %d вместо 400", badF.Status)
			}
			fResp, err := c.JSONReq(http.MethodPost, "/api/v1/formulas/render", map[string]interface{}{
				"tex": `E = mc^2`, "displayMode": false,
			}, nil)
			if err != nil {
				return err
			}
			if fResp.Status != http.StatusOK && fResp.Status != http.StatusUnprocessableEntity && fResp.Status != http.StatusInternalServerError {
				return fmt.Errorf("/api/v1/formulas/render вернул неожиданный HTTP %d", fResp.Status)
			}
			return nil
		})
	})

	// 8. Система нескольких блогов и изоляция
	tr.Suite("08. Мультиблог: создание, переключение, изоляция данных", func(s *SuiteContext) {
		c := NewNPClient(baseURL)
		if _, err := c.RefreshCSRF(); err != nil {
			s.Case("CSRF init", func() error { return err })
			return
		}

		s.Case("Создание второго блога -> изоляция статей и шрифтов между data и data_two", func() error {
			cr, err := c.JSONReq(http.MethodPost, "/create_blog.php", map[string]interface{}{
				"title": "Second Blog", "folder": "data_two", "make_active": false,
			}, nil)
			if err != nil {
				return err
			}
			cm, err := cr.MustSuccessJSON()
			if err != nil {
				return err
			}
			if cm["path"] != "data_two" {
				return fmt.Errorf("неожиданный путь блога: %v", cm["path"])
			}

			// Повторное создание с той же папкой должно отклоняться
			dup, err := c.JSONReq(http.MethodPost, "/create_blog.php", map[string]interface{}{
				"title": "Dup", "folder": "data_two",
			}, nil)
			if err != nil {
				return err
			}
			dm, _ := dup.JSON()
			if dm["success"] == true {
				return fmt.Errorf("повторное создание блога в существующую папку прошло")
			}

			// Загружаем шрифт только во второй блог через X-Blog-Path со слэшами и бэкслешами
			c2 := c.CloneFresh()
			c2.BlogHeader = "/data_two\\"
			_, _ = c2.RefreshCSRF()

			ttf2 := []byte{0x00, 0x01, 0x00, 0x00, 0x00, 0x01}
			if r, err := c2.MultipartReq("/upload_font.php", nil, []UploadFileItem{{
				Field: "font", Filename: "OnlyInBlogTwo.ttf", Data: ttf2,
			}}, nil); err != nil {
				return err
			} else if _, err := r.MustSuccessJSON(); err != nil {
				return err
			}

			css2, err := c2.RawReq(http.MethodGet, "/get_custom_fonts_css.php", nil, nil)
			if err != nil {
				return err
			}
			if !strings.Contains(string(css2.Body), "/data_two/fonts/OnlyInBlogTwo.ttf") {
				return fmt.Errorf("get_custom_fonts_css в блоге 2 вернул неверный URL: %s", string(css2.Body))
			}

			// Проверяем, что в основном блоге data шрифт OnlyInBlogTwo отсутствует
			css1, err := c.RawReq(http.MethodGet, "/get_custom_fonts_css.php", nil, nil)
			if err != nil {
				return err
			}
			if strings.Contains(string(css1.Body), "OnlyInBlogTwo") {
				return fmt.Errorf("утечка шрифта из блога data_two в блог data!")
			}

			// Статья в data_two не попадает в data
			p2, err := c2.JSONReq(http.MethodPost, "/save_post.php", map[string]string{
				"title": "Изолированная статья Blog2", "content": "<p>Blog Two Body</p>",
			}, nil)
			if err != nil {
				return err
			}
			p2m, err := p2.MustSuccessJSON()
			if err != nil {
				return err
			}
			p2ID := int(p2m["id"].(float64))

			p2HTML, err := c.RawReq(http.MethodGet, fmt.Sprintf("/data_two/blog/post-%d.html", p2ID), nil, nil)
			if err != nil {
				return err
			}
			if p2HTML.Status != 200 || !strings.Contains(string(p2HTML.Body), "Blog Two Body") {
				return fmt.Errorf("статья второго блога недоступна по /data_two/blog/post-%d.html (HTTP %d)", p2ID, p2HTML.Status)
			}
			return nil
		})
	})

	// 9. Настройки, Целостность, Восстановление и Системный экспорт
	tr.Suite("09. Настройки, целостность и системное обслуживание", func(s *SuiteContext) {
		c := NewNPClient(baseURL)
		if _, err := c.RefreshCSRF(); err != nil {
			s.Case("CSRF init", func() error { return err })
			return
		}

		s.Case("Чтение/сохранение настроек вида блога, навигации, оверлея и языков", func() error {
			if r, err := c.JSONReq(http.MethodGet, "/get_languages.php", nil, nil); err != nil {
				return err
			} else if _, err := r.MustSuccessJSON(); err != nil {
				return err
			}
			if r, err := c.JSONReq(http.MethodGet, "/get_editor_settings.php", nil, nil); err != nil {
				return err
			} else if _, err := r.MustSuccessJSON(); err != nil {
				return err
			}
			if r, err := c.JSONReq(http.MethodPost, "/save_blog_view_settings.php", map[string]interface{}{
				"action": "save", "layout": "grid", "title": "Мой Тестовый Блог",
			}, nil); err != nil {
				return err
			} else if _, err := r.MustSuccessJSON(); err != nil {
				return err
			}
			if r, err := c.JSONReq(http.MethodPost, "/save_blog_nav.php", map[string]interface{}{
				"action": "save",
				"buttons": []map[string]string{
					{"text": "Главная", "url": "https://example.org"},
					{"text": "XSS", "url": "javascript:alert(1)"},
				},
			}, nil); err != nil {
				return err
			} else if _, err := r.MustSuccessJSON(); err != nil {
				return err
			}
			return nil
		})

		s.Case("Повреждение метаданных блога -> check_integrity -> fix_integrity -> экспорт системы", func() error {
			// Удаляем settings.json шаблонов, чтобы проверить автопочинку целостности
			tplSettings := filepath.Join(workspaceRoot, "data", "blog", "templates", "settings.json")
			_ = os.Remove(tplSettings)

			if r, err := c.JSONReq(http.MethodGet, "/check_integrity.php", nil, nil); err != nil {
				return err
			} else if r.Status != 200 {
				return fmt.Errorf("check_integrity вернул %d", r.Status)
			}

			fixResp, err := c.JSONReq(http.MethodPost, "/fix_integrity.php", map[string]string{}, nil)
			if err != nil {
				return err
			}
			if fixResp.Status != 200 {
				return fmt.Errorf("fix_integrity вернул %d", fixResp.Status)
			}

			verResp, err := c.JSONReq(http.MethodGet, "/api/v1/system/version", nil, nil)
			if err != nil {
				return err
			}
			if verResp.Status != 200 {
				return fmt.Errorf("/api/v1/system/version вернул %d", verResp.Status)
			}

			// Проверяем legacy get_version + возврат канала обновлений
			legacyVerResp, err := c.JSONReq(http.MethodGet, "/update_system.php?action=get_version", nil, nil)
			if err != nil {
				return err
			}
			if _, err := legacyVerResp.MustSuccessJSON(); err != nil {
				return fmt.Errorf("/update_system.php?action=get_version: %w", err)
			}

			// Проверяем сохранение и чтение канала обновлений "go"
			origSettings, err := c.JSONReq(http.MethodGet, "/get_editor_settings.php", nil, nil)
			if err != nil {
				return err
			}
			origChannel := "stable"
			if origJSON, jErr := origSettings.JSON(); jErr == nil {
				if sObj, ok := origJSON["settings"].(map[string]interface{}); ok {
					if ch, ok := sObj["update_channel"].(string); ok && ch != "" {
						origChannel = ch
					}
				}
			}
			saveGoResp, err := c.JSONReq(http.MethodPost, "/save_editor_settings.php", map[string]interface{}{
				"update_channel": "go",
			}, nil)
			if err != nil {
				return err
			}
			if _, err := saveGoResp.MustSuccessJSON(); err != nil {
				return fmt.Errorf("сохранение update_channel=go не удалось: %w", err)
			}
			verAfterGo, err := c.JSONReq(http.MethodGet, "/update_system.php?action=get_version", nil, nil)
			if err != nil {
				return err
			}
			verAfterJSON, err := verAfterGo.MustSuccessJSON()
			if err != nil {
				return fmt.Errorf("get_version после сохранения go: %w", err)
			}
			if ch, _ := verAfterJSON["configured_channel"].(string); ch != "go" {
				return fmt.Errorf("ожидался configured_channel='go' в get_version, получено: %#v", verAfterJSON["configured_channel"])
			}
			// Безопасность: отклоняем сторонние (не GitHub) URL архивов в prepare_auto_update
			badPrep, err := c.JSONReq(http.MethodPost, "/api/v1/system/updates/prepare", map[string]interface{}{
				"channel":    "go",
				"repo":       "slavaraninco-lgtm/NPBlog",
				"archiveUrl": "https://evil.example.com/malicious.zip",
			}, nil)
			if err == nil && badPrep.Status == 200 {
				if j, jErr := badPrep.JSON(); jErr == nil && j["success"] == true {
					return fmt.Errorf("prepare_auto_update принял недопустимый URL архива")
				}
			}
			_, _ = c.JSONReq(http.MethodPost, "/save_editor_settings.php", map[string]interface{}{
				"update_channel": origChannel,
			}, nil)

			expResp, err := c.RawReq(http.MethodGet, "/api/v1/system/updates/export", nil, nil)
			if err != nil {
				return err
			}
			if expResp.Status != 200 || len(expResp.Body) < 100 {
				return fmt.Errorf("/api/v1/system/updates/export вернул %d (len=%d)", expResp.Status, len(expResp.Body))
			}
			// Проверка валидности отданного ZIP
			if _, err := zip.NewReader(bytes.NewReader(expResp.Body), int64(len(expResp.Body))); err != nil {
				return fmt.Errorf("экспортированный системный архив невалиден: %w", err)
			}
			return nil
		})
	})
}
