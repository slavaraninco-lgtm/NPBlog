package service

import (
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"
	"time"

	"npblog/internal/domain"
)

// LoadPostBackgroundsMap загружает все настройки фонов статей из post_backgrounds.json
func (p *PostService) LoadPostBackgroundsMap(activeBlogPath string) map[string]domain.PostBgSettings {
	bgFile := p.storage.GetDataPath(activeBlogPath, "post_backgrounds.json")
	var raw json.RawMessage
	if err := p.storage.SafeReadJSON(bgFile, &raw); err != nil || len(raw) == 0 {
		return make(map[string]domain.PostBgSettings)
	}

	// 1. Попытка десериализации канонического словаря { "1": { ... } }
	var directMap map[string]domain.PostBgSettings
	if err := json.Unmarshal(raw, &directMap); err == nil && len(directMap) > 0 {
		if _, hasPosts := directMap["posts"]; !hasPosts {
			return directMap
		}
	}

	// 2. Обратная совместимость с устаревшей структурой { "posts": { ... } }
	var legacy domain.PostBackgroundConfig
	if err := json.Unmarshal(raw, &legacy); err == nil && legacy.Posts != nil {
		res := make(map[string]domain.PostBgSettings)
		for k, v := range legacy.Posts {
			if v != nil {
				res[k] = domain.PostBgSettings{
					Background:      v.Image,
					BackgroundMode:  v.Size,
					BackgroundScope: "content",
				}
			}
		}
		return res
	}

	return make(map[string]domain.PostBgSettings)
}

// SavePostBackgroundsMap атомарно сохраняет настройки фонов статей
func (p *PostService) SavePostBackgroundsMap(activeBlogPath string, bgMap map[string]domain.PostBgSettings) error {
	bgFile := p.storage.GetDataPath(activeBlogPath, "post_backgrounds.json")
	if len(bgMap) == 0 {
		_ = os.Remove(bgFile)
		return nil
	}
	return p.storage.SafeWriteJSON(bgFile, bgMap)
}

// GetPostBackground возвращает настройки фона конкретной статьи с валидацией наличия файла на диске
func (p *PostService) GetPostBackground(activeBlogPath string, postID int) (*domain.PostBgSettings, error) {
	bgMap := p.LoadPostBackgroundsMap(activeBlogPath)
	idStr := strconv.Itoa(postID)
	settings, exists := bgMap[idStr]
	if !exists {
		return nil, nil
	}

	// Проверяем физическое существование файла фона
	if settings.Background != "" {
		bgFile := filepath.Join(p.storage.GetDataPath(activeBlogPath, "backgrounds"), settings.Background)
		if _, err := os.Stat(bgFile); os.IsNotExist(err) {
			settings.Background = ""
			settings.BackgroundMode = ""
			settings.BackgroundScope = ""
			if !settings.OverlayEnabled {
				delete(bgMap, idStr)
			} else {
				bgMap[idStr] = settings
			}
			_ = p.SavePostBackgroundsMap(activeBlogPath, bgMap)
		}
	}

	if settings.Background == "" && !settings.OverlayEnabled {
		return nil, nil
	}
	return &settings, nil
}

// GetAllPostBackgrounds возвращает настройки всех статей с валидацией файлов
func (p *PostService) GetAllPostBackgrounds(activeBlogPath string) (map[string]domain.PostBgSettings, error) {
	bgMap := p.LoadPostBackgroundsMap(activeBlogPath)
	bgDir := p.storage.GetDataPath(activeBlogPath, "backgrounds")
	changed := false

	for id, settings := range bgMap {
		if settings.Background != "" {
			bgFile := filepath.Join(bgDir, settings.Background)
			if _, err := os.Stat(bgFile); os.IsNotExist(err) {
				settings.Background = ""
				settings.BackgroundMode = ""
				settings.BackgroundScope = ""
				if !settings.OverlayEnabled {
					delete(bgMap, id)
				} else {
					bgMap[id] = settings
				}
				changed = true
			}
		}
	}

	if changed {
		_ = p.SavePostBackgroundsMap(activeBlogPath, bgMap)
	}
	return bgMap, nil
}

// GetGlobalBackground возвращает настройки глобального фона из data/global-settings.json
func (p *PostService) GetGlobalBackground(activeBlogPath string) (*domain.GlobalBgSettings, error) {
	settingsFile := p.storage.GetDataPath(activeBlogPath, "global-settings.json")
	var settings domain.GlobalBgSettings
	if err := p.storage.SafeReadJSON(settingsFile, &settings); err != nil {
		return nil, err
	}
	if settings.Background == "" {
		return nil, nil
	}

	// Проверка наличия файла на диске
	bgFile := filepath.Join(p.storage.GetDataPath(activeBlogPath, "backgrounds"), settings.Background)
	if _, err := os.Stat(bgFile); os.IsNotExist(err) {
		return nil, nil
	}

	if settings.BackgroundMode == "" {
		settings.BackgroundMode = "cover"
	}
	if settings.BackgroundScope == "" {
		settings.BackgroundScope = "content"
	}
	return &settings, nil
}

// ApplyBackgroundToHtmlFile модифицирует HTML файл статьи, внедряя или удаляя фон и подложку
func (p *PostService) ApplyBackgroundToHtmlFile(htmlFilePath, bgUrl string, settings *domain.PostBgSettings) error {
	rawBytes, err := os.ReadFile(htmlFilePath)
	if err != nil {
		return err
	}
	html := string(rawBytes)

	// 1. Очистка старых обёрток (.overlay-wrapper и .content-wrapper)
	reOverlayWrapper := regexp.MustCompile(`(?s)<div class="overlay-wrapper" style="[^"]*">\s*([\s\S]*?<a[^>]*class="[^"]*back-link[^"]*"[^>]*>[\s\S]*?</a>)\s*</div>`)
	html = reOverlayWrapper.ReplaceAllString(html, "$1")

	reContentWrapper := regexp.MustCompile(`(?s)<div class="content-wrapper" style="[^"]*">\s*([\s\S]*?<a[^>]*class="[^"]*back-link[^"]*"[^>]*>[\s\S]*?</a>)\s*</div>`)
	html = reContentWrapper.ReplaceAllString(html, "$1")

	// 2. Сброс инлайн-стиля тега <body>
	reBody := regexp.MustCompile(`(?i)<body(\s+style="[^"]*")?\s*>`)
	contentWidth := p.cfg.GetSettings().ContentWidth
	if contentWidth <= 0 {
		contentWidth = 920
	}
	defaultBodyTag := fmt.Sprintf(`<body style="max-width: %dpx;">`, contentWidth)
	html = reBody.ReplaceAllString(html, defaultBodyTag)

	// 3. Применение фона (если настроен)
	if settings != nil && settings.Background != "" && bgUrl != "" {
		mode := settings.BackgroundMode
		if mode != "contain" && mode != "repeat" {
			mode = "cover"
		}
		scope := settings.BackgroundScope
		if scope != "fullpage" {
			scope = "content"
		}

		var backgroundStyle string
		if mode == "repeat" {
			backgroundStyle = fmt.Sprintf("background-image: url('%s'); background-repeat: repeat; background-size: auto;", bgUrl)
		} else if mode == "contain" {
			backgroundStyle = fmt.Sprintf("background-image: url('%s'); background-size: contain; background-position: center; background-repeat: no-repeat;", bgUrl)
		} else { // cover
			backgroundStyle = fmt.Sprintf("background-image: url('%s'); background-size: cover; background-position: center; background-repeat: no-repeat;", bgUrl)
		}

		if scope == "fullpage" {
			backgroundStyle += " background-attachment: fixed;"
			html = reBody.ReplaceAllString(html, fmt.Sprintf(`<body style="%s">`, backgroundStyle))
		} else {
			backgroundStyle += " min-height: 100vh; padding: 40px 60px;"
			reWrapContent := regexp.MustCompile(`(?s)(<button[^>]*class="[^"]*theme-toggle[^"]*"[^>]*>[\s\S]*?</button>\s*)(<h1>[\s\S]*?<a[^>]*class="[^"]*back-link[^"]*"[^>]*>[\s\S]*?</a>)`)
			if reWrapContent.MatchString(html) {
				html = reWrapContent.ReplaceAllString(html, fmt.Sprintf("${1}<div class=\"content-wrapper\" style=\"%s\">${2}</div>", backgroundStyle))
			} else {
				reWrapFallback := regexp.MustCompile(`(?s)(<h1>[\s\S]*?<a[^>]*class="[^"]*back-link[^"]*"[^>]*>[\s\S]*?</a>)`)
				html = reWrapFallback.ReplaceAllString(html, fmt.Sprintf("<div class=\"content-wrapper\" style=\"%s\">${1}</div>", backgroundStyle))
			}
		}
	}

	// 4. Применение подложки (если включена)
	if settings != nil && settings.OverlayEnabled {
		hex := strings.TrimPrefix(settings.OverlayColor, "#")
		if len(hex) == 3 {
			hex = fmt.Sprintf("%c%c%c%c%c%c", hex[0], hex[0], hex[1], hex[1], hex[2], hex[2])
		}
		rVal, gVal, bVal := 255, 255, 255
		if len(hex) == 6 {
			if rgb, err := strconv.ParseUint(hex, 16, 32); err == nil {
				rVal = int((rgb >> 16) & 0xFF)
				gVal = int((rgb >> 8) & 0xFF)
				bVal = int(rgb & 0xFF)
			}
		}
		opacity := settings.OverlayOpacity
		if opacity < 0 {
			opacity = 0
		}
		if opacity > 100 {
			opacity = 100
		}
		alpha := float64(opacity) / 100.0

		overlayStyle := fmt.Sprintf("background: rgba(%d, %d, %d, %.2f); padding: 40px; border-radius: 12px;", rVal, gVal, bVal, alpha)
		reWrapOverlay := regexp.MustCompile(`(?s)(<h1>[\s\S]*?</h1>[\s\S]*?<div class="date">[\s\S]*?</div>[\s\S]*?(?:<div class="content">|<article[^>]*class="[^"]*content[^"]*"[^>]*>)[\s\S]*?</(?:div|article)>[\s\S]*?<a[^>]*class="[^"]*back-link[^"]*"[^>]*>[\s\S]*?</a>)`)
		if reWrapOverlay.MatchString(html) {
			html = reWrapOverlay.ReplaceAllString(html, fmt.Sprintf("<div class=\"overlay-wrapper\" style=\"%s\">${1}</div>", overlayStyle))
		} else {
			reWrapOverlayFallback := regexp.MustCompile(`(?s)(<h1>[\s\S]*?<a[^>]*class="[^"]*back-link[^"]*"[^>]*>[\s\S]*?</a>)`)
			html = reWrapOverlayFallback.ReplaceAllString(html, fmt.Sprintf("<div class=\"overlay-wrapper\" style=\"%s\">${1}</div>", overlayStyle))
		}
	}

	return p.storage.SafeWriteFile(htmlFilePath, []byte(html))
}

// ApplyBackgroundForPost обновляет HTML файл статьи, вычисляя каскад индивидуального и глобального фонов
func (p *PostService) ApplyBackgroundForPost(activeBlogPath string, postID int) error {
	blogDir := p.storage.GetDataPath(activeBlogPath, "blog")
	metaFile := filepath.Join(blogDir, "posts-meta.json")

	filename := fmt.Sprintf("post-%d.html", postID)
	var meta []domain.PostMeta
	if err := p.storage.SafeReadJSON(metaFile, &meta); err == nil {
		for _, m := range meta {
			if m.ID == postID && m.Filename != "" {
				filename = m.Filename
				break
			}
		}
	}

	htmlFilePath := filepath.Join(blogDir, filename)
	if _, err := os.Stat(htmlFilePath); os.IsNotExist(err) {
		return errors.New("HTML файл статьи не найден")
	}

	postBg, _ := p.GetPostBackground(activeBlogPath, postID)
	globalBg, _ := p.GetGlobalBackground(activeBlogPath)

	var effectiveSettings *domain.PostBgSettings
	var bgUrl string

	if postBg != nil && postBg.Background != "" {
		// Индивидуальный фон статьи
		effectiveSettings = postBg
		bgFilePath := filepath.Join(p.storage.GetDataPath(activeBlogPath, "backgrounds"), postBg.Background)
		ver := time.Now().Unix()
		if fi, err := os.Stat(bgFilePath); err == nil {
			ver = fi.ModTime().Unix()
		}
		bgUrl = fmt.Sprintf("%s?v=%d", p.storage.GetDataURL(activeBlogPath, "backgrounds/"+postBg.Background), ver)
	} else if globalBg != nil && globalBg.Background != "" {
		// Глобальный фон блога
		combined := domain.PostBgSettings{
			Background:      globalBg.Background,
			BackgroundMode:  globalBg.BackgroundMode,
			BackgroundScope: globalBg.BackgroundScope,
		}
		if postBg != nil {
			combined.OverlayEnabled = postBg.OverlayEnabled
			combined.OverlayColor = postBg.OverlayColor
			combined.OverlayOpacity = postBg.OverlayOpacity
		}
		effectiveSettings = &combined
		bgFilePath := filepath.Join(p.storage.GetDataPath(activeBlogPath, "backgrounds"), globalBg.Background)
		ver := time.Now().Unix()
		if fi, err := os.Stat(bgFilePath); err == nil {
			ver = fi.ModTime().Unix()
		}
		bgUrl = fmt.Sprintf("%s?v=%d", p.storage.GetDataURL(activeBlogPath, "backgrounds/"+globalBg.Background), ver)
	} else if postBg != nil && postBg.OverlayEnabled {
		// Фона нет, но включена подложка
		effectiveSettings = postBg
	}

	return p.ApplyBackgroundToHtmlFile(htmlFilePath, bgUrl, effectiveSettings)
}

// UploadPostBackground загружает фон для отдельной статьи и применяет его
func (p *PostService) UploadPostBackground(activeBlogPath string, postID int, origFilename string, data []byte, mode, scope string) (string, error) {
	ext := strings.ToLower(filepath.Ext(origFilename))
	if ext != ".jpg" && ext != ".jpeg" && ext != ".png" && ext != ".gif" && ext != ".webp" {
		return "", errors.New("Недопустимый тип файла (разрешены jpg, jpeg, png, gif, webp)")
	}

	if mode != "contain" && mode != "repeat" {
		mode = "cover"
	}
	if scope != "fullpage" {
		scope = "content"
	}

	bgDir := p.storage.GetDataPath(activeBlogPath, "backgrounds")
	_ = os.MkdirAll(bgDir, 0755)

	// Удаляем старые файлы фона этой статьи
	pattern := filepath.Join(bgDir, fmt.Sprintf("bg-%d.*", postID))
	if matches, err := filepath.Glob(pattern); err == nil {
		for _, m := range matches {
			_ = os.Remove(m)
		}
	}

	filename := fmt.Sprintf("bg-%d%s", postID, ext)
	targetPath := filepath.Join(bgDir, filename)
	if err := p.storage.SafeWriteFile(targetPath, data); err != nil {
		return "", err
	}

	// Сохраняем настройки в post_backgrounds.json (сохраняя настройки подложки)
	bgMap := p.LoadPostBackgroundsMap(activeBlogPath)
	idStr := strconv.Itoa(postID)
	curSettings := bgMap[idStr]
	curSettings.Background = filename
	curSettings.BackgroundMode = mode
	curSettings.BackgroundScope = scope
	bgMap[idStr] = curSettings

	if err := p.SavePostBackgroundsMap(activeBlogPath, bgMap); err != nil {
		return "", err
	}

	// Применяем изменения к HTML файлу статьи
	if err := p.ApplyBackgroundForPost(activeBlogPath, postID); err != nil {
		return filename, err
	}

	return filename, nil
}

// SaveOverlaySettings сохраняет настройки подложки для отдельной статьи и обновляет HTML
func (p *PostService) SaveOverlaySettings(activeBlogPath string, postID int, enabled bool, color string, opacity int) error {
	bgMap := p.LoadPostBackgroundsMap(activeBlogPath)
	idStr := strconv.Itoa(postID)
	curSettings := bgMap[idStr]

	curSettings.OverlayEnabled = enabled
	if color == "" {
		color = "#ffffff"
	}
	curSettings.OverlayColor = color
	if opacity <= 0 {
		opacity = 90
	}
	curSettings.OverlayOpacity = opacity
	bgMap[idStr] = curSettings

	if err := p.SavePostBackgroundsMap(activeBlogPath, bgMap); err != nil {
		return err
	}

	return p.ApplyBackgroundForPost(activeBlogPath, postID)
}

// RemovePostBackground удаляет файл фона статьи и возвращает true если был применен глобальный фон
func (p *PostService) RemovePostBackground(activeBlogPath string, postID int) (bool, error) {
	bgDir := p.storage.GetDataPath(activeBlogPath, "backgrounds")
	pattern := filepath.Join(bgDir, fmt.Sprintf("bg-%d.*", postID))
	if matches, err := filepath.Glob(pattern); err == nil {
		for _, m := range matches {
			_ = os.Remove(m)
		}
	}

	bgMap := p.LoadPostBackgroundsMap(activeBlogPath)
	idStr := strconv.Itoa(postID)
	if settings, exists := bgMap[idStr]; exists {
		settings.Background = ""
		settings.BackgroundMode = ""
		settings.BackgroundScope = ""
		if !settings.OverlayEnabled {
			delete(bgMap, idStr)
		} else {
			bgMap[idStr] = settings
		}
		_ = p.SavePostBackgroundsMap(activeBlogPath, bgMap)
	}

	_ = p.ApplyBackgroundForPost(activeBlogPath, postID)

	globalBg, _ := p.GetGlobalBackground(activeBlogPath)
	hasGlobal := (globalBg != nil && globalBg.Background != "")
	return hasGlobal, nil
}

// UploadGlobalBackground сохраняет глобальный фон и применяет его ко всем статьям
func (p *PostService) UploadGlobalBackground(activeBlogPath, origFilename string, data []byte, mode, scope string) (string, error) {
	ext := strings.ToLower(filepath.Ext(origFilename))
	if ext != ".jpg" && ext != ".jpeg" && ext != ".png" && ext != ".gif" && ext != ".webp" {
		return "", errors.New("Недопустимый тип файла")
	}

	if mode != "contain" && mode != "repeat" {
		mode = "cover"
	}
	if scope != "fullpage" {
		scope = "content"
	}

	bgDir := p.storage.GetDataPath(activeBlogPath, "backgrounds")
	_ = os.MkdirAll(bgDir, 0755)

	// Удаляем старый global-bg.*
	if matches, err := filepath.Glob(filepath.Join(bgDir, "global-bg.*")); err == nil {
		for _, m := range matches {
			_ = os.Remove(m)
		}
	}

	filename := "global-bg" + ext
	targetPath := filepath.Join(bgDir, filename)
	if err := p.storage.SafeWriteFile(targetPath, data); err != nil {
		return "", err
	}

	// Обновляем global-settings.json с сохранением других полей
	settingsFile := p.storage.GetDataPath(activeBlogPath, "global-settings.json")
	var settings map[string]interface{}
	_ = p.storage.SafeReadJSON(settingsFile, &settings)
	if settings == nil {
		settings = make(map[string]interface{})
	}
	settings["background"] = filename
	settings["backgroundMode"] = mode
	settings["backgroundScope"] = scope
	if err := p.storage.SafeWriteJSON(settingsFile, settings); err != nil {
		return "", err
	}

	// Удаляем файлы всех индивидуальных фонов и очищаем их в post_backgrounds.json (сохраняя подложки)
	if matches, err := filepath.Glob(filepath.Join(bgDir, "bg-*.*")); err == nil {
		for _, m := range matches {
			_ = os.Remove(m)
		}
	}

	bgMap := p.LoadPostBackgroundsMap(activeBlogPath)
	newBgMap := make(map[string]domain.PostBgSettings)
	for id, s := range bgMap {
		s.Background = ""
		s.BackgroundMode = ""
		s.BackgroundScope = ""
		if s.OverlayEnabled {
			newBgMap[id] = s
		}
	}
	_ = p.SavePostBackgroundsMap(activeBlogPath, newBgMap)

	// Применяем глобальный фон ко всем статьям
	p.reapplyAllPostBackgrounds(activeBlogPath)

	return filename, nil
}

// RemoveGlobalBackground удаляет глобальный фон и обновляет все статьи
func (p *PostService) RemoveGlobalBackground(activeBlogPath string) error {
	bgDir := p.storage.GetDataPath(activeBlogPath, "backgrounds")
	if matches, err := filepath.Glob(filepath.Join(bgDir, "global-bg.*")); err == nil {
		for _, m := range matches {
			_ = os.Remove(m)
		}
	}

	settingsFile := p.storage.GetDataPath(activeBlogPath, "global-settings.json")
	var settings map[string]interface{}
	if err := p.storage.SafeReadJSON(settingsFile, &settings); err == nil && settings != nil {
		delete(settings, "background")
		delete(settings, "backgroundMode")
		delete(settings, "backgroundScope")
		if len(settings) == 0 {
			_ = os.Remove(settingsFile)
		} else {
			_ = p.storage.SafeWriteJSON(settingsFile, settings)
		}
	}

	p.reapplyAllPostBackgrounds(activeBlogPath)
	return nil
}

// reapplyAllPostBackgrounds вспомогательно вызывает ApplyBackgroundForPost для всех опубликованных статей
func (p *PostService) reapplyAllPostBackgrounds(activeBlogPath string) {
	blogDir := p.storage.GetDataPath(activeBlogPath, "blog")
	metaFile := filepath.Join(blogDir, "posts-meta.json")
	var meta []domain.PostMeta
	if err := p.storage.SafeReadJSON(metaFile, &meta); err == nil {
		for _, m := range meta {
			_ = p.ApplyBackgroundForPost(activeBlogPath, m.ID)
		}
	}
}
