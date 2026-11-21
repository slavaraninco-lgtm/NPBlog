package legacy

import (
	"bytes"
	"fmt"
	"io"
	"net/http"
	"path/filepath"
	"strings"
	"time"

	"npblog/internal/assets"
)

const maxCustomThemeSize = 2 << 20

func (l *LegacyHandler) HandleUploadCustomTheme(w http.ResponseWriter, r *http.Request) {
	r.Body = http.MaxBytesReader(w, r.Body, maxCustomThemeSize)
	if err := r.ParseMultipartForm(maxCustomThemeSize); err != nil {
		l.errorResponse(w, "Ошибка чтения формы или размер файла превышает 2 МБ")
		return
	}

	file, header, err := r.FormFile("themeFile")
	if err != nil {
		l.errorResponse(w, "CSS-файл не был передан")
		return
	}
	defer file.Close()

	if strings.ToLower(filepath.Ext(header.Filename)) != ".css" {
		l.errorResponse(w, "Допускаются только файлы CSS")
		return
	}

	contentType := strings.ToLower(strings.TrimSpace(strings.Split(header.Header.Get("Content-Type"), ";")[0]))
	if contentType != "" && contentType != "text/css" && contentType != "text/plain" && contentType != "application/octet-stream" {
		l.errorResponse(w, "Недопустимый тип файла темы")
		return
	}

	data, err := io.ReadAll(io.LimitReader(file, maxCustomThemeSize+1))
	if err != nil || len(data) == 0 || len(data) > maxCustomThemeSize {
		l.errorResponse(w, "Не удалось прочитать CSS-файл или превышен размер 2 МБ")
		return
	}
	if bytes.IndexByte(data, 0) >= 0 {
		l.errorResponse(w, "Файл не является текстовым CSS")
		return
	}
	if detected := http.DetectContentType(data); !strings.HasPrefix(detected, "text/plain") && !strings.HasPrefix(detected, "text/css") {
		l.errorResponse(w, "Недопустимое содержимое файла темы")
		return
	}

	dataDir := filepath.Join(l.cfg.BaseDir, "data")
	themePath, err := l.storage.ValidateSafePath(dataDir, "custom_editor_theme.css")
	if err != nil {
		l.errorResponse(w, "Недопустимый путь для сохранения темы")
		return
	}
	if err := l.storage.SafeWriteFile(themePath, data); err != nil {
		l.errorResponse(w, "Не удалось сохранить CSS-тему")
		return
	}

	l.jsonResponse(w, map[string]interface{}{
		"success":    true,
		"message":    "Кастомная тема загружена",
		"cssContent": string(data),
		"themeUrl":   fmt.Sprintf("data/custom_editor_theme.css?v=%d", time.Now().Unix()),
	})
}

func HandleDownloadTheme(hybridFS *assets.HybridFS) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "text/css; charset=utf-8")
		w.Header().Set("Content-Disposition", `attachment; filename="editor-style-template.css"`)
		if !hybridFS.ServeHTTP(w, r, "editor-style.css") {
			http.NotFound(w, r)
		}
	}
}
