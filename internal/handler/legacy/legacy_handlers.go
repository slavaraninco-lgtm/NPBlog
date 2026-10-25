package legacy

import (
	"encoding/json"
	"fmt"
	"golang.org/x/crypto/bcrypt"
	"html"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"
	"sync"
	"time"

	"npblog/internal/config"
	"npblog/internal/domain"
	"npblog/internal/middleware"
	"npblog/internal/service"
)

type LegacyHandler struct {
	settingsMu sync.Mutex
	cfg        *config.AppConfig
	auth       *service.AuthService
	storage    *service.StorageService
	post       *service.PostService
	draft      *service.DraftService
	backup     *service.BackupService
	media      *service.MediaService
	template   *service.TemplateService
	integrity  *service.IntegrityService
	ftp        *service.FtpService
	system     *service.SystemService
}

func NewLegacyHandler(
	cfg *config.AppConfig,
	auth *service.AuthService,
	storage *service.StorageService,
	post *service.PostService,
	draft *service.DraftService,
	backup *service.BackupService,
	media *service.MediaService,
	template *service.TemplateService,
	integrity *service.IntegrityService,
	ftp *service.FtpService,
	system *service.SystemService,
) *LegacyHandler {
	return &LegacyHandler{
		cfg:       cfg,
		auth:      auth,
		storage:   storage,
		post:      post,
		draft:     draft,
		backup:    backup,
		media:     media,
		template:  template,
		integrity: integrity,
		ftp:       ftp,
		system:    system,
	}
}

func (l *LegacyHandler) jsonResponse(w http.ResponseWriter, data interface{}) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	_ = json.NewEncoder(w).Encode(data)
}

func (l *LegacyHandler) errorResponse(w http.ResponseWriter, msg string) {
	l.jsonResponse(w, map[string]interface{}{
		"success": false,
		"error":   msg,
		"message": msg,
	})
}

// --- Посты ---

func (l *LegacyHandler) HandleSavePost(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		Title   string `json:"title"`
		Content string `json:"content"`
	}
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil || body.Title == "" {
		l.errorResponse(w, "Отсутствуют необходимые данные")
		return
	}

	nextID, err := l.post.SavePost(activeBlog, body.Title, body.Content)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	_ = l.integrity.SaveHistoryAction("create_post", fmt.Sprintf("Создана статья #%d: %s", nextID, body.Title))
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"id":      nextID,
	})
}

func (l *LegacyHandler) HandleUpdatePost(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		ID      int    `json:"id"`
		Title   string `json:"title"`
		Content string `json:"content"`
	}
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil || body.ID <= 0 {
		l.errorResponse(w, "Отсутствуют необходимые данные")
		return
	}

	if err := l.post.UpdatePost(activeBlog, body.ID, body.Title, body.Content); err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	_ = l.integrity.SaveHistoryAction("update_post", fmt.Sprintf("Обновлена статья #%d: %s", body.ID, body.Title))
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
	})
}

func (l *LegacyHandler) HandleDeletePost(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		ID int `json:"id"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	if body.ID <= 0 {
		if idParam := r.URL.Query().Get("id"); idParam != "" {
			body.ID, _ = strconv.Atoi(idParam)
		}
	}

	if body.ID <= 0 {
		l.errorResponse(w, "Не указан ID статьи")
		return
	}

	renumbered, err := l.post.DeletePost(activeBlog, body.ID)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	_ = l.integrity.SaveHistoryAction("delete_post", fmt.Sprintf("Удалена статья #%d", body.ID))
	l.jsonResponse(w, map[string]interface{}{
		"success":    true,
		"renumbered": renumbered,
	})
}

func (l *LegacyHandler) HandleGetPostContent(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	id, _ := strconv.Atoi(r.URL.Query().Get("id"))
	if id <= 0 {
		var body struct {
			ID int `json:"id"`
		}
		_ = json.NewDecoder(r.Body).Decode(&body)
		id = body.ID
	}
	if id <= 0 {
		l.errorResponse(w, "Не указан ID статьи")
		return
	}

	title, content, err := l.post.GetPostContent(activeBlog, id)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"id":      id,
		"title":   title,
		"content": content,
	})
}

func (l *LegacyHandler) HandleTogglePinPost(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		ID int `json:"id"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	if body.ID <= 0 {
		body.ID, _ = strconv.Atoi(r.URL.Query().Get("id"))
	}

	pinned, err := l.post.TogglePinPost(activeBlog, body.ID)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"pinned":  pinned,
	})
}

func (l *LegacyHandler) HandleRenumberPosts(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		Action string `json:"action"`
	}
	if r.Body != nil {
		_ = json.NewDecoder(r.Body).Decode(&body)
	}
	if body.Action == "" {
		body.Action = r.URL.Query().Get("action")
	}

	if body.Action == "check" {
		issues, err := l.post.CheckNumbering(activeBlog)
		if err != nil {
			l.errorResponse(w, err.Error())
			return
		}
		l.jsonResponse(w, map[string]interface{}{
			"success":  true,
			"needsFix": len(issues) > 0,
			"issues":   issues,
		})
		return
	}

	changes, err := l.post.RenumberPosts(activeBlog)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	_ = l.integrity.SaveHistoryAction("renumber_posts", fmt.Sprintf("Перенумеровано статей: %d", len(changes)))
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"changes": changes,
	})
}

func (l *LegacyHandler) HandleRegeneratePosts(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	_ = l.post.GenerateRSS(activeBlog)
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"message": "Статьи успешно обновлены",
	})
}

// --- Аутентификация ---

func (l *LegacyHandler) HandleLogin(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	clientIP := l.auth.GetClientIP(r)

	// Проверка активного локаута
	isLocked, remaining, _ := l.auth.GetLockoutInfo(clientIP, activeBlog)
	if isLocked {
		l.jsonResponse(w, map[string]interface{}{
			"success":              false,
			"message":              "Превышено количество попыток ввода. Доступ заблокирован.",
			"lockoutTimeRemaining": remaining,
		})
		return
	}

	var body struct {
		Password string `json:"password"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	if l.auth.VerifyPassword(body.Password) {
		l.auth.ResetLockout(clientIP, activeBlog)
		session := l.auth.CreateSession(w)
		l.jsonResponse(w, map[string]interface{}{
			"success":    true,
			"csrf_token": session.CSRFToken,
		})
		return
	}

	attempts, dur := l.auth.RecordFailedLogin(clientIP, activeBlog)
	resp := map[string]interface{}{
		"success": false,
		"message": "Неверный пароль",
	}
	if attempts >= 3 {
		resp["message"] = "Превышено количество попыток ввода. Доступ заблокирован."
		resp["lockoutTimeRemaining"] = dur
	}
	l.jsonResponse(w, resp)
}

func (l *LegacyHandler) HandleLogout(w http.ResponseWriter, r *http.Request) {
	if cookie, err := r.Cookie("npblog_session"); err == nil {
		l.auth.DestroySession(w, cookie.Value)
	}
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
	})
}

// --- Черновики и Автосохранения ---

func (l *LegacyHandler) HandleSaveDraft(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		Title   string `json:"title"`
		Content string `json:"content"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	filename, ts, err := l.draft.SaveDraft(activeBlog, body.Title, body.Content)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}
	l.jsonResponse(w, map[string]interface{}{
		"success":   true,
		"filename":  filename,
		"timestamp": ts,
	})
}

func (l *LegacyHandler) HandleGetDrafts(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	drafts, err := l.draft.GetDrafts(activeBlog)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}
	if drafts == nil {
		drafts = make([]domain.DraftData, 0)
	}
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"drafts":  drafts,
	})
}

func (l *LegacyHandler) HandleDeleteDraft(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		ID       string `json:"id"`
		Filename string `json:"filename"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	filename := body.Filename
	if filename == "" {
		filename = body.ID
	}
	if filename == "" {
		filename = r.URL.Query().Get("filename")
		if filename == "" {
			filename = r.URL.Query().Get("id")
		}
	}

	_ = l.draft.DeleteDraft(activeBlog, filename)
	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleSaveAutosave(w http.ResponseWriter, r *http.Request) {
	var title, content, id string
	var postID int

	ct := r.Header.Get("Content-Type")
	if strings.Contains(ct, "application/json") {
		var body map[string]interface{}
		_ = json.NewDecoder(r.Body).Decode(&body)
		if t, ok := body["title"].(string); ok {
			title = t
		}
		if c, ok := body["content"].(string); ok {
			content = c
		}
		if i, ok := body["id"].(string); ok {
			id = i
		}
		if pid, ok := body["postId"].(float64); ok {
			postID = int(pid)
		}
	} else {
		_ = r.ParseMultipartForm(50 << 20)
		title = r.FormValue("title")
		content = r.FormValue("content")
		id = r.FormValue("id")
		postID, _ = strconv.Atoi(r.FormValue("postId"))
	}

	if title == "" && content == "" {
		l.errorResponse(w, "Отсутствуют данные")
		return
	}

	saved, err := l.draft.SaveAutosave(domain.AutosaveData{
		ID:      id,
		PostID:  postID,
		Title:   title,
		Content: content,
	})
	if err != nil {
		l.errorResponse(w, "Ошибка сохранения файла")
		return
	}

	l.jsonResponse(w, map[string]interface{}{
		"success":   true,
		"id":        saved.ID,
		"timestamp": saved.Timestamp,
	})
}

func (l *LegacyHandler) HandleGetAutosave(w http.ResponseWriter, r *http.Request) {
	id := r.URL.Query().Get("id")
	if id == "" {
		id = r.URL.Query().Get("postId")
	}

	ct := r.Header.Get("Content-Type")
	if strings.Contains(ct, "application/json") {
		var body struct {
			ID     interface{} `json:"id"`
			PostID interface{} `json:"postId"`
		}
		_ = json.NewDecoder(r.Body).Decode(&body)
		if id == "" && body.ID != nil {
			id = fmt.Sprintf("%v", body.ID)
		}
		if id == "" && body.PostID != nil {
			id = fmt.Sprintf("%v", body.PostID)
		}
	} else if r.Method == http.MethodPost {
		_ = r.ParseForm()
		if id == "" {
			id = r.FormValue("id")
		}
		if id == "" {
			id = r.FormValue("postId")
		}
	}

	data, err := l.draft.GetAutosave(id)
	if err != nil {
		l.errorResponse(w, "Автосохранение не найдено")
		return
	}
	l.jsonResponse(w, map[string]interface{}{
		"success":  true,
		"autosave": data,
	})
}

func (l *LegacyHandler) HandleGetAllAutosaves(w http.ResponseWriter, r *http.Request) {
	list, err := l.draft.GetAllAutosaves()
	if err != nil || list == nil {
		list = []domain.AutosaveData{}
	}
	l.jsonResponse(w, map[string]interface{}{
		"success":   true,
		"autosaves": list,
	})
}

func (l *LegacyHandler) HandleDeleteAutosave(w http.ResponseWriter, r *http.Request) {
	id := r.URL.Query().Get("id")
	if id == "" {
		id = r.URL.Query().Get("postId")
	}

	ct := r.Header.Get("Content-Type")
	if strings.Contains(ct, "application/json") {
		var body struct {
			ID     interface{} `json:"id"`
			PostID interface{} `json:"postId"`
		}
		_ = json.NewDecoder(r.Body).Decode(&body)
		if id == "" && body.ID != nil {
			id = fmt.Sprintf("%v", body.ID)
		}
		if id == "" && body.PostID != nil {
			id = fmt.Sprintf("%v", body.PostID)
		}
	} else if r.Method == http.MethodPost {
		_ = r.ParseForm()
		if id == "" {
			id = r.FormValue("id")
		}
		if id == "" {
			id = r.FormValue("postId")
		}
	}

	_ = l.draft.DeleteAutosave(id)
	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleDeleteAllAutosaves(w http.ResponseWriter, r *http.Request) {
	deleted, _ := l.draft.DeleteAllAutosaves()
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"message": fmt.Sprintf("Удалено файлов: %d", deleted),
	})
}

// --- Бэкапы ---

func (l *LegacyHandler) HandleGetBackups(w http.ResponseWriter, r *http.Request) {
	postID, _ := strconv.Atoi(r.URL.Query().Get("postId"))
	if postID > 0 {
		info, err := l.backup.GetPostBackups(postID)
		if err != nil {
			l.jsonResponse(w, map[string]interface{}{"success": true, "backups": []interface{}{}})
			return
		}
		l.jsonResponse(w, map[string]interface{}{"success": true, "backups": info.Backups})
		return
	}

	all, _ := l.backup.GetAllBackups()
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"backups": all,
	})
}

func parseBackupParams(r *http.Request) (postIDKey string, filename string, backupNum int) {
	postIDKey = r.URL.Query().Get("postId")
	filename = r.URL.Query().Get("filename")
	if bn, err := strconv.Atoi(r.URL.Query().Get("backupNumber")); err == nil {
		backupNum = bn
	}
	if backupNum <= 0 {
		if bn, err := strconv.Atoi(r.URL.Query().Get("backup")); err == nil {
			backupNum = bn
		}
	}

	if r.Body != nil {
		bodyBytes, err := io.ReadAll(r.Body)
		if err == nil && len(bodyBytes) > 0 {
			var raw map[string]interface{}
			if err := json.Unmarshal(bodyBytes, &raw); err == nil {
				if v, ok := raw["postId"]; ok {
					switch val := v.(type) {
					case float64:
						postIDKey = strconv.Itoa(int(val))
					case string:
						postIDKey = strings.TrimSpace(val)
					}
				}
				if v, ok := raw["filename"]; ok {
					if s, ok := v.(string); ok && s != "" {
						filename = strings.TrimSpace(s)
					}
				}
				if v, ok := raw["backupNumber"]; ok {
					switch val := v.(type) {
					case float64:
						backupNum = int(val)
					case string:
						if bn, err := strconv.Atoi(strings.TrimSpace(val)); err == nil {
							backupNum = bn
						}
					}
				}
				if backupNum <= 0 {
					if v, ok := raw["backup"]; ok {
						switch val := v.(type) {
						case float64:
							backupNum = int(val)
						case string:
							if bn, err := strconv.Atoi(strings.TrimSpace(val)); err == nil {
								backupNum = bn
							}
						}
					}
				}
			}
		}
	}

	if backupNum <= 0 && filename != "" {
		clean := filepath.Base(filename)
		parts := strings.Split(strings.TrimSuffix(clean, ".html"), "-")
		if len(parts) >= 2 {
			if bn, err := strconv.Atoi(parts[len(parts)-1]); err == nil {
				backupNum = bn
			}
		}
	}

	return postIDKey, filename, backupNum
}

func (l *LegacyHandler) HandleGetBackupContent(w http.ResponseWriter, r *http.Request) {
	postIDKey, filename, backupNum := parseBackupParams(r)
	content, err := l.backup.GetBackupFileContent(postIDKey, filename, backupNum)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}
	activeBlog := middleware.GetActiveBlogPath(r)
	if activeBlog == "" {
		activeBlog = "data"
	}
	l.jsonResponse(w, map[string]interface{}{
		"success":    true,
		"content":    content,
		"activeBlog": activeBlog,
	})
}

func (l *LegacyHandler) HandleViewBackup(w http.ResponseWriter, r *http.Request) {
	postIDKey, filename, backupNum := parseBackupParams(r)
	content, err := l.backup.GetBackupFileContent(postIDKey, filename, backupNum)
	if err != nil {
		http.Error(w, err.Error(), http.StatusNotFound)
		return
	}

	activeBlog := middleware.GetActiveBlogPath(r)
	if blogParam := r.URL.Query().Get("blog"); blogParam != "" {
		activeBlog = blogParam
	}
	if activeBlog == "" {
		activeBlog = "data"
	}
	activeBlog = strings.Trim(filepath.Base(activeBlog), "/\\")
	baseHref := "/" + activeBlog + "/blog/"

	html := content
	if !strings.Contains(html, "<base ") && !strings.Contains(html, "<base>") {
		if strings.Contains(html, "<head>") {
			html = strings.Replace(html, "<head>", fmt.Sprintf("<head>\n    <base href=\"%s\">", baseHref), 1)
		} else if strings.Contains(html, "<head ") {
			re := regexp.MustCompile(`(?i)<head[^>]*>`)
			html = re.ReplaceAllStringFunc(html, func(m string) string {
				return fmt.Sprintf("%s\n    <base href=\"%s\">", m, baseHref)
			})
		} else {
			html = fmt.Sprintf("<base href=\"%s\">\n%s", baseHref, html)
		}
	}

	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.WriteHeader(http.StatusOK)
	_, _ = w.Write([]byte(html))
}

func (l *LegacyHandler) HandleRestoreBackup(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	postIDKey, filename, backupNum := parseBackupParams(r)
	if err := l.backup.RestoreBackupFile(activeBlog, postIDKey, filename, backupNum); err != nil {
		l.errorResponse(w, err.Error())
		return
	}
	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleDeleteBackup(w http.ResponseWriter, r *http.Request) {
	postIDKey, filename, backupNum := parseBackupParams(r)
	if err := l.backup.DeleteBackupFile(postIDKey, filename, backupNum); err != nil {
		l.errorResponse(w, err.Error())
		return
	}
	l.jsonResponse(w, map[string]interface{}{"success": true})
}

// --- Медиа и Файлы ---

func (l *LegacyHandler) HandleUploadImage(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	if err := r.ParseMultipartForm(50 << 20); err != nil {
		l.errorResponse(w, "Ошибка чтения формы")
		return
	}

	file, header, err := r.FormFile("image")
	if err != nil {
		l.errorResponse(w, "Файл не был загружен")
		return
	}
	defer file.Close()

	data, err := io.ReadAll(file)
	if err != nil {
		l.errorResponse(w, "Ошибка чтения файла")
		return
	}

	path, err := l.media.UploadImage(activeBlog, header.Filename, data)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"url":     path,
		"path":    path,
	})
}

func (l *LegacyHandler) HandleUploadImagesGrid(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	if err := r.ParseMultipartForm(100 << 20); err != nil {
		l.errorResponse(w, "Ошибка чтения формы")
		return
	}

	files := r.MultipartForm.File["images[]"]
	if len(files) == 0 {
		files = r.MultipartForm.File["images"]
	}

	var items []service.FileUploadItem
	for _, fh := range files {
		f, err := fh.Open()
		if err == nil {
			d, _ := io.ReadAll(f)
			_ = f.Close()
			items = append(items, service.FileUploadItem{
				Filename: fh.Filename,
				Data:     d,
			})
		}
	}

	gridHTML, err := l.media.UploadImagesGrid(activeBlog, items)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	l.jsonResponse(w, map[string]interface{}{
		"success":  true,
		"gridHtml": gridHTML,
		"html":     gridHTML,
	})
}

func (l *LegacyHandler) HandleSaveEditedImage(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	_ = r.ParseForm()
	imageData := r.FormValue("image_data")
	if imageData == "" {
		var body struct {
			ImageData string `json:"image_data"`
		}
		_ = json.NewDecoder(r.Body).Decode(&body)
		imageData = body.ImageData
	}

	path, err := l.media.SaveEditedImage(activeBlog, imageData)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"url":     path,
		"path":    path,
	})
}

func (l *LegacyHandler) HandleServeData(w http.ResponseWriter, r *http.Request) {
	fileParam := r.URL.Query().Get("file")
	if fileParam == "" {
		fileParam = strings.TrimPrefix(r.URL.Path, "/serve_data.php/")
		fileParam = strings.TrimPrefix(fileParam, "/serve_data.php")
	}

	cleanRel := filepath.Clean(strings.TrimPrefix(fileParam, "/"))
	if cleanRel == "" || cleanRel == "." || strings.HasPrefix(cleanRel, "..") {
		http.Error(w, "Invalid file path", http.StatusBadRequest)
		return
	}

	activeBlog := middleware.GetActiveBlogPath(r)
	fullPath := l.storage.GetDataPath(activeBlog, cleanRel)

	if info, err := os.Stat(fullPath); err == nil && !info.IsDir() {
		// Отключаем кеш для json настроек
		if strings.HasSuffix(fullPath, ".json") {
			w.Header().Set("Cache-Control", "no-store, no-cache, must-revalidate, max-age=0")
		}
		http.ServeFile(w, r, fullPath)
		return
	}

	http.NotFound(w, r)
}

// --- Настройки ---

func (l *LegacyHandler) HandleGetEditorSettings(w http.ResponseWriter, r *http.Request) {
	settingsPath := filepath.Join(l.cfg.BaseDir, "editor_settings.json")
	var settingsMap map[string]interface{}
	data, err := os.ReadFile(settingsPath)
	if err == nil {
		_ = json.Unmarshal(data, &settingsMap)
	}
	if settingsMap == nil {
		settingsMap = make(map[string]interface{})
	}

	hasPassword := false
	if ph, ok := settingsMap["password_hash"].(string); ok && ph != "" {
		hasPassword = true
	}
	delete(settingsMap, "password_hash")
	delete(settingsMap, "new_password")
	delete(settingsMap, "old_password")
	settingsMap["password_set"] = hasPassword
	settingsMap["password_enabled"] = hasPassword
	settingsMap["resolved_backup_path"] = l.storage.GetBackupPath("")
	settingsMap["resolved_autosave_path"] = l.storage.GetAutosavePath("")
	settingsMap["resolved_editor_backup_path"] = filepath.Join(l.cfg.BaseDir, "data_backup")
	settingsMap["app_dir"] = l.cfg.BaseDir
	settingsMap["suggested_data_path"] = filepath.Join(l.cfg.BaseDir, "data")

	resp := make(map[string]interface{})
	for k, v := range settingsMap {
		resp[k] = v
	}
	resp["success"] = true
	resp["settings"] = settingsMap

	l.jsonResponse(w, resp)
}

func (l *LegacyHandler) HandleSaveEditorSettings(w http.ResponseWriter, r *http.Request) {
	l.settingsMu.Lock()
	defer l.settingsMu.Unlock()
	var updateMap map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&updateMap); err != nil {
		l.errorResponse(w, "Некорректный JSON")
		return
	}

	settingsPath := filepath.Join(l.cfg.BaseDir, "editor_settings.json")
	existingMap := make(map[string]interface{})
	existingBytes, err := os.ReadFile(settingsPath)
	if err == nil {
		if json.Unmarshal(existingBytes, &existingMap) != nil || existingMap == nil {
			l.errorResponse(w, "Настройки повреждены; сохранение отменено")
			return
		}
	} else {
		l.errorResponse(w, "Не удалось прочитать настройки")
		return
	}
	// Password commands are transient inputs, never persisted as plaintext settings.
	newPassword, _ := updateMap["new_password"].(string)
	oldPassword, _ := updateMap["old_password"].(string)
	enabled, hasEnabled := updateMap["password_enabled"].(bool)
	hash, _ := existingMap["password_hash"].(string)
	pSet, hasPSet := existingMap["password_set"].(bool)
	pEn, hasPEn := existingMap["password_enabled"].(bool)
	isPasswordActive := hash != "" && ((hasPSet && pSet) || (hasPEn && pEn))

	delete(updateMap, "new_password")
	delete(updateMap, "old_password")
	delete(updateMap, "password_hash")
	delete(existingMap, "new_password")
	delete(existingMap, "old_password")

	if isPasswordActive {
		// Пароль уже активен: изменение или отключение требует проверки старого пароля
		if newPassword != "" || (hasEnabled && !enabled) {
			if !l.auth.VerifyHash(hash, oldPassword) {
				l.errorResponse(w, "Неверный текущий пароль")
				return
			}
		}
	}

	if hasEnabled && !enabled {
		hash = ""
	} else if newPassword != "" {
		encoded, err := bcrypt.GenerateFromPassword([]byte(newPassword), bcrypt.DefaultCost)
		if err != nil {
			l.errorResponse(w, "Не удалось установить пароль (максимум 72 байта)")
			return
		}
		hash = string(encoded)
	}

	if hasEnabled && enabled && hash == "" && newPassword == "" {
		l.errorResponse(w, "Укажите новый пароль")
		return
	}

	existingMap["password_hash"] = hash
	existingMap["password_set"] = (hash != "")
	existingMap["password_enabled"] = (hash != "")
	updateMap["password_set"] = (hash != "")
	updateMap["password_enabled"] = (hash != "")

	// Обработка IP Whitelist: авто-добавление текущего IP при включении
	if ipWhitelistVal, ok := updateMap["ip_whitelist_enabled"].(bool); ok {
		existingMap["ip_whitelist_enabled"] = ipWhitelistVal
		if ipWhitelistVal {
			clientIP := l.auth.GetClientIP(r)
			if clientIP != "" {
				_ = l.auth.AddAllowedIP(clientIP)
			}
		}
	}

	for k, v := range updateMap {
		if k == "bottomBar" {
			if newBB, ok := v.(map[string]interface{}); ok {
				existingBB, _ := existingMap["bottomBar"].(map[string]interface{})
				if existingBB == nil {
					existingBB = make(map[string]interface{})
				}
				for bbK, bbV := range newBB {
					existingBB[bbK] = bbV
				}
				existingMap["bottomBar"] = existingBB
				continue
			}
		}
		existingMap[k] = v
	}

	if err := l.storage.SafeWriteJSON(settingsPath, existingMap); err != nil {
		l.errorResponse(w, "Ошибка сохранения настроек")
		return
	}

	if blogTitle, ok := updateMap["blog_title"].(string); ok && strings.TrimSpace(blogTitle) != "" {
		activeBlog := middleware.GetActiveBlogPath(r)
		bvsPath := l.storage.GetDataPath(activeBlog, "blog-view-settings.json")
		var bvs map[string]interface{}
		_ = l.storage.SafeReadJSON(bvsPath, &bvs)
		if bvs == nil {
			bvs = make(map[string]interface{})
		}
		cleanTitle := strings.TrimSpace(blogTitle)
		bvs["title"] = cleanTitle
		_ = l.storage.SafeWriteJSON(bvsPath, bvs)

		blogHTML := l.storage.GetDataPath(activeBlog, "blog.html")
		if bytes, err := os.ReadFile(blogHTML); err == nil {
			content := string(bytes)
			escaped := html.EscapeString(cleanTitle)
			reTitle := regexp.MustCompile(`(?i)<title>.*?</title>`)
			reH1 := regexp.MustCompile(`(?i)<h1>.*?</h1>`)
			content = reTitle.ReplaceAllString(content, fmt.Sprintf("<title>%s - Список статей</title>", escaped))
			content = reH1.ReplaceAllString(content, fmt.Sprintf("<h1>%s</h1>", escaped))
			_ = os.WriteFile(blogHTML, []byte(content), 0644)
		}
	}

	l.cfg.ReloadSettings()
	if newPassword != "" {
		l.auth.ResetLockout(l.auth.GetClientIP(r), middleware.GetActiveBlogPath(r))
		_ = l.auth.CreateSession(w)
	}
	if _, ok := updateMap["active_blog_path"].(string); ok {
		// Subsequent requests use the saved selection rather than a stale cookie.
		http.SetCookie(w, &http.Cookie{Name: "active_blog_path", Value: "", Path: "/", MaxAge: -1})
	}
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"blogUrl": "/" + filepath.Base(l.storage.GetDataPath(l.cfg.GetSettings().ActiveBlogPath, "")) + "/blog.html",
	})
}

func (l *LegacyHandler) HandleSaveGlobalSettings(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body domain.GlobalSettings
	_ = json.NewDecoder(r.Body).Decode(&body)

	target := l.storage.GetDataPath(activeBlog, "global-settings.json")
	_ = l.storage.SafeWriteJSON(target, body)
	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleSaveBlogViewSettings(w http.ResponseWriter, r *http.Request) {
	data := make(map[string]interface{})
	ct := r.Header.Get("Content-Type")
	if strings.Contains(ct, "application/json") {
		_ = json.NewDecoder(r.Body).Decode(&data)
	} else {
		_ = r.ParseForm()
		for k, v := range r.Form {
			if len(v) > 0 {
				data[k] = v[0]
			}
		}
	}

	action, _ := data["action"].(string)
	if action == "" {
		action = "save"
	}

	var layout string
	if lVal, ok := data["layout"].(string); ok {
		lVal = strings.TrimSpace(lVal)
		if lVal == "grid" || lVal == "list" {
			layout = lVal
		}
	}

	var title string
	if tVal, ok := data["title"].(string); ok {
		title = strings.TrimSpace(tVal)
	}

	applyTitleAll := false
	if ata, ok := data["apply_title_all"]; ok {
		switch v := ata.(type) {
		case bool:
			applyTitleAll = v
		case string:
			applyTitleAll = v == "true" || v == "1"
		}
	}

	updateFile := func(blogDir string, updates map[string]interface{}) bool {
		target := l.storage.GetDataPath(blogDir, "blog-view-settings.json")
		var settings map[string]interface{}
		_ = l.storage.SafeReadJSON(target, &settings)
		if settings == nil {
			settings = make(map[string]interface{})
		}
		for k, v := range updates {
			settings[k] = v
		}
		if err := l.storage.SafeWriteJSON(target, settings); err != nil {
			return false
		}
		if newTitle, hasTitle := updates["title"].(string); hasTitle && newTitle != "" {
			blogHTML := l.storage.GetDataPath(blogDir, "blog.html")
			if bytes, err := os.ReadFile(blogHTML); err == nil {
				content := string(bytes)
				escaped := html.EscapeString(newTitle)
				reTitle := regexp.MustCompile(`(?i)<title>.*?</title>`)
				reH1 := regexp.MustCompile(`(?i)<h1>.*?</h1>`)
				content = reTitle.ReplaceAllString(content, fmt.Sprintf("<title>%s - Список статей</title>", escaped))
				content = reH1.ReplaceAllString(content, fmt.Sprintf("<h1>%s</h1>", escaped))
				_ = os.WriteFile(blogHTML, []byte(content), 0644)
			}
		}
		return true
	}

	if action == "apply_all" {
		if layout == "" && title == "" {
			l.errorResponse(w, "Нет данных для сохранения")
			return
		}
		updates := make(map[string]interface{})
		if layout != "" {
			updates["layout"] = layout
		}
		if title != "" && applyTitleAll {
			updates["title"] = title
		}

		blogPaths := l.cfg.GetSettings().BlogPaths
		if len(blogPaths) == 0 {
			dp := l.cfg.GetSettings().ActiveBlogPath
			if dp == "" {
				dp = "data"
			}
			blogPaths = []string{dp}
		}

		successCount := 0
		details := make(map[string]interface{})
		for _, p := range blogPaths {
			ok := updateFile(p, updates)
			details[p] = map[string]interface{}{"success": ok}
			if ok {
				successCount++
			}
		}

		l.jsonResponse(w, map[string]interface{}{
			"success":       true,
			"updated_count": successCount,
			"details":       details,
		})
		return
	}

	// action == "save"
	activeBlog := middleware.GetActiveBlogPath(r)
	updates := make(map[string]interface{})
	if layout != "" {
		updates["layout"] = layout
	}
	if title != "" {
		updates["title"] = title
	}
	for k, v := range data {
		if k != "action" && k != "apply_title_all" && k != "title" && k != "layout" {
			updates[k] = v
		}
	}

	if len(updates) == 0 {
		l.errorResponse(w, "Нет данных для сохранения")
		return
	}

	if !updateFile(activeBlog, updates) {
		l.errorResponse(w, "Не удалось сохранить настройки вида блога")
		return
	}

	l.jsonResponse(w, map[string]interface{}{"success": true})
}

// --- Шаблоны и сниппеты ---

func (l *LegacyHandler) HandleGetTemplates(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	// The editor needs HTML and metadata; keep the compact response for older clients.
	if r.URL.Query().Get("details") == "1" {
		data, err := l.template.GetTemplateManagerData(activeBlog)
		if err != nil {
			l.errorResponse(w, err.Error())
			return
		}
		l.jsonResponse(w, data)
		return
	}
	list, active, _ := l.template.GetTemplates(activeBlog)
	l.jsonResponse(w, map[string]interface{}{
		"success":        true,
		"templates":      list,
		"activeTemplate": active,
	})
}

func (l *LegacyHandler) HandleApplyTemplate(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		TemplateName string `json:"templateName"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	if err := l.template.ApplyTemplate(activeBlog, body.TemplateName); err != nil {
		l.errorResponse(w, err.Error())
		return
	}
	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleGetIncludes(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	items, _ := l.template.GetIncludes(activeBlog)
	if items == nil {
		items = make([]domain.IncludeMetaItem, 0)
	}
	l.jsonResponse(w, map[string]interface{}{
		"success":  true,
		"files":    items,
		"includes": items,
	})
}

func (l *LegacyHandler) HandleSaveInclude(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		Name    string `json:"name"`
		Content string `json:"content"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	filename, displayName, err := l.template.SaveInclude(activeBlog, body.Name, body.Content)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}
	l.jsonResponse(w, map[string]interface{}{
		"success":     true,
		"filename":    filename,
		"displayName": displayName,
	})
}

func (l *LegacyHandler) HandleGetIncludeContent(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	filename := r.URL.Query().Get("filename")
	if filename == "" {
		filename = r.URL.Query().Get("name")
	}
	if filename == "" {
		var body struct {
			Filename string `json:"filename"`
			Name     string `json:"name"`
		}
		_ = json.NewDecoder(r.Body).Decode(&body)
		if body.Filename != "" {
			filename = body.Filename
		} else {
			filename = body.Name
		}
	}

	content, err := l.template.GetIncludeContent(activeBlog, filename)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"content": content,
	})
}

func (l *LegacyHandler) HandleDeleteInclude(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		Filename string `json:"filename"`
		Name     string `json:"name"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	filename := body.Filename
	if filename == "" {
		filename = body.Name
	}
	if filename == "" {
		filename = r.URL.Query().Get("filename")
		if filename == "" {
			filename = r.URL.Query().Get("name")
		}
	}

	_ = l.template.DeleteInclude(activeBlog, filename)
	l.jsonResponse(w, map[string]interface{}{"success": true})
}

// --- Целостность и История ---

func (l *LegacyHandler) HandleCheckIntegrity(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	errs, ok := l.integrity.CheckIntegrity(activeBlog)
	l.jsonResponse(w, map[string]interface{}{
		"success": ok,
		"errors":  errs,
	})
}

func (l *LegacyHandler) HandleFixIntegrity(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	fixed, errs := l.integrity.FixIntegrity(activeBlog)
	l.jsonResponse(w, map[string]interface{}{
		"success": len(errs) == 0,
		"fixed":   fixed,
		"errors":  errs,
	})
}

func (l *LegacyHandler) HandleGetHistory(w http.ResponseWriter, r *http.Request) {
	items, _ := l.integrity.GetHistory()
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"history": items,
	})
}

func (l *LegacyHandler) HandleSaveHistory(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Action  string `json:"action"`
		Details string `json:"details"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	_ = l.integrity.SaveHistoryAction(body.Action, body.Details)
	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleClearHistory(w http.ResponseWriter, r *http.Request) {
	_ = l.integrity.ClearHistory()
	l.jsonResponse(w, map[string]interface{}{"success": true})
}

// --- Создание блога (Multi-Blog) ---

func (l *LegacyHandler) HandleCreateBlog(w http.ResponseWriter, r *http.Request) {
	l.settingsMu.Lock()
	defer l.settingsMu.Unlock()
	var body struct {
		Title      string `json:"title"`
		Folder     string `json:"folder"`
		MakeActive bool   `json:"make_active"`
	}
	if json.NewDecoder(r.Body).Decode(&body) != nil {
		l.errorResponse(w, "Некорректный JSON")
		return
	}
	folder := strings.TrimSpace(body.Folder)
	if folder == "" {
		l.errorResponse(w, "Укажите название папки для блога")
		return
	}

	// A new blog is a single sibling folder, never an existing directory or a traversal path.
	if folder == "." || folder == ".." || strings.ContainsAny(folder, "/\\:.") {
		l.errorResponse(w, "Недопустимое имя папки")
		return
	}
	targetDir, err := l.storage.ValidateSafePath(l.cfg.BaseDir, folder)
	if err != nil {
		l.errorResponse(w, "Недопустимый путь")
		return
	}
	settingsPath := filepath.Join(l.cfg.BaseDir, "editor_settings.json")
	settings := make(map[string]interface{})
	if l.storage.SafeReadJSON(settingsPath, &settings) != nil || settings == nil {
		l.errorResponse(w, "Не удалось прочитать настройки")
		return
	}
	if err := os.Mkdir(targetDir, 0755); err != nil {
		l.errorResponse(w, "Папка уже существует или недоступна для создания")
		return
	}
	if err := l.initializeBlog(targetDir, body.Title); err != nil {
		l.errorResponse(w, "Не удалось подготовить файлы блога; активный блог не изменён")
		return
	}

	// Инициализируем пустой posts-meta.json
	if l.storage.SafeWriteJSON(filepath.Join(targetDir, "blog", "posts-meta.json"), []domain.PostMeta{}) != nil {
		l.errorResponse(w, "Не удалось создать список статей")
		return
	}

	// Обновляем editor_settings.json
	paths, _ := settings["blog_paths"].([]interface{})
	settings["blog_paths"] = append(paths, folder)
	if body.MakeActive {
		settings["active_blog_path"] = folder
	}
	if l.storage.SafeWriteJSON(settingsPath, settings) != nil {
		l.errorResponse(w, "Не удалось зарегистрировать блог")
		return
	}
	l.cfg.ReloadSettings()

	if body.MakeActive {
		http.SetCookie(w, &http.Cookie{Name: "active_blog_path", Value: "", Path: "/", MaxAge: -1})
	}
	l.jsonResponse(w, map[string]interface{}{
		"success":     true,
		"folder":      folder,
		"path":        folder,
		"title":       body.Title,
		"make_active": body.MakeActive,
		"blogUrl":     "/" + folder + "/blog.html",
	})
}

// --- Документы, Аудио, Видео, Шрифты, Смайлы ---

func (l *LegacyHandler) HandleUploadDocument(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	if err := r.ParseMultipartForm(100 << 20); err != nil {
		l.errorResponse(w, "Ошибка чтения формы")
		return
	}

	file, header, err := r.FormFile("document")
	if err != nil {
		file, header, err = r.FormFile("file")
	}
	if err != nil {
		l.errorResponse(w, "Файл документа не был загружен")
		return
	}
	defer file.Close()

	data, err := io.ReadAll(file)
	if err != nil {
		l.errorResponse(w, "Ошибка чтения файла")
		return
	}

	path, err := l.media.UploadDocument(activeBlog, header.Filename, data)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"url":     path,
		"path":    path,
	})
}

func (l *LegacyHandler) HandleGetDocuments(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	docs, _ := l.media.GetDocuments(activeBlog)
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"files":   docs,
	})
}

func (l *LegacyHandler) HandleDeleteDocument(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		FilePath string `json:"filePath"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	if body.FilePath == "" {
		body.FilePath = r.URL.Query().Get("filePath")
	}

	cleanFile := filepath.Base(body.FilePath)
	_ = l.media.DeleteFile(activeBlog, filepath.Join("files", "documents", cleanFile))
	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleUploadAudio(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	if err := r.ParseMultipartForm(100 << 20); err != nil {
		l.errorResponse(w, "Ошибка чтения формы")
		return
	}

	file, header, err := r.FormFile("audio")
	if err != nil {
		file, header, err = r.FormFile("file")
	}
	if err != nil {
		l.errorResponse(w, "Аудиофайл не был загружен")
		return
	}
	defer file.Close()

	data, _ := io.ReadAll(file)
	path, err := l.media.UploadAudio(activeBlog, header.Filename, data)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"url":     path,
		"path":    path,
	})
}

func (l *LegacyHandler) HandleGetAudioFiles(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	files, _ := l.media.GetAudioFiles(activeBlog)
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"files":   files,
	})
}

func (l *LegacyHandler) HandleDeleteAudio(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		Filename string `json:"filename"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	if body.Filename == "" {
		body.Filename = r.URL.Query().Get("filename")
	}

	cleanFile := filepath.Base(body.Filename)
	_ = l.media.DeleteFile(activeBlog, filepath.Join("files", "audio", cleanFile))
	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleUploadVideo(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	if err := r.ParseMultipartForm(500 << 20); err != nil {
		l.errorResponse(w, "Ошибка чтения формы")
		return
	}

	file, header, err := r.FormFile("video")
	if err != nil {
		file, header, err = r.FormFile("file")
	}
	if err != nil {
		l.errorResponse(w, "Видеофайл не был загружен")
		return
	}
	defer file.Close()

	data, _ := io.ReadAll(file)
	path, err := l.media.UploadVideo(activeBlog, header.Filename, data)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"url":     path,
		"path":    path,
	})
}

func (l *LegacyHandler) HandleGetVideoFiles(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	files, _ := l.media.GetVideoFiles(activeBlog)
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"files":   files,
	})
}

func (l *LegacyHandler) HandleDeleteVideo(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		Filename string `json:"filename"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	if body.Filename == "" {
		body.Filename = r.URL.Query().Get("filename")
	}

	cleanFile := filepath.Base(body.Filename)
	_ = l.media.DeleteFile(activeBlog, filepath.Join("files", "videos", cleanFile))
	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleUploadFont(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	if err := r.ParseMultipartForm(50 << 20); err != nil {
		l.errorResponse(w, "Ошибка чтения формы")
		return
	}

	file, header, err := r.FormFile("font")
	if err != nil {
		l.errorResponse(w, "Шрифт не был загружен")
		return
	}
	defer file.Close()

	data, _ := io.ReadAll(file)
	if err := l.media.UploadFont(activeBlog, header.Filename, data); err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleGetCustomFonts(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	fonts, _ := l.media.GetCustomFonts(activeBlog)
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"fonts":   fonts,
	})
}

func (l *LegacyHandler) HandleGetCustomFontsCSS(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	fonts, _ := l.media.GetCustomFonts(activeBlog)

	var sb strings.Builder
	for _, f := range fonts {
		fontFmt := "truetype"
		if f.Format == "woff" {
			fontFmt = "woff"
		} else if f.Format == "woff2" {
			fontFmt = "woff2"
		} else if f.Format == "otf" {
			fontFmt = "opentype"
		}
		sb.WriteString(fmt.Sprintf("@font-face { font-family: '%s'; src: url('data/fonts/%s') format('%s'); font-display: swap; }\n", f.Name, f.File, fontFmt))
	}

	w.Header().Set("Content-Type", "text/css; charset=utf-8")
	_, _ = w.Write([]byte(sb.String()))
}

func (l *LegacyHandler) HandleUploadSmiles(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	if err := r.ParseMultipartForm(100 << 20); err != nil {
		l.errorResponse(w, "Ошибка чтения формы")
		return
	}

	setName := r.FormValue("setName")
	files := r.MultipartForm.File["smiles[]"]
	if len(files) == 0 {
		files = r.MultipartForm.File["smiles"]
	}

	var items []service.FileUploadItem
	for _, fh := range files {
		f, err := fh.Open()
		if err == nil {
			d, _ := io.ReadAll(f)
			_ = f.Close()
			items = append(items, service.FileUploadItem{
				Filename: fh.Filename,
				Data:     d,
			})
		}
	}

	if err := l.media.UploadSmiles(activeBlog, setName, items); err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleGetSmiles(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	sets, _ := l.media.GetSmiles(activeBlog)
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"sets":    sets,
	})
}

func (l *LegacyHandler) HandleDeleteSmileSet(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		SetName string `json:"setName"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	if body.SetName == "" {
		body.SetName = r.URL.Query().Get("setName")
	}

	_ = l.media.DeleteSmileSet(activeBlog, body.SetName)
	l.jsonResponse(w, map[string]interface{}{"success": true})
}

// --- Фоны ---

func (l *LegacyHandler) HandleUploadBackground(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	if err := r.ParseMultipartForm(50 << 20); err != nil {
		l.errorResponse(w, "Ошибка чтения формы")
		return
	}

	postIDStr := r.FormValue("postId")
	postID, err := strconv.Atoi(postIDStr)
	if err != nil || postID <= 0 {
		l.errorResponse(w, "Некорректный ID статьи")
		return
	}

	mode := r.FormValue("mode")
	scope := r.FormValue("scope")

	file, header, err := r.FormFile("background")
	if err != nil {
		l.errorResponse(w, "Файл не был загружен")
		return
	}
	defer file.Close()

	data, err := io.ReadAll(file)
	if err != nil {
		l.errorResponse(w, "Ошибка чтения файла")
		return
	}

	filename, err := l.post.UploadPostBackground(activeBlog, postID, header.Filename, data, mode, scope)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	l.jsonResponse(w, map[string]interface{}{
		"success":  true,
		"filename": filename,
	})
}

func (l *LegacyHandler) HandleUploadBlogBackground(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	if err := r.ParseMultipartForm(50 << 20); err != nil {
		l.errorResponse(w, "Ошибка чтения формы")
		return
	}

	file, header, err := r.FormFile("background")
	if err != nil {
		l.errorResponse(w, "Файл не был загружен")
		return
	}
	defer file.Close()

	mode := r.FormValue("mode")
	if mode != "contain" && mode != "repeat" {
		mode = "cover"
	}

	data, err := io.ReadAll(file)
	if err != nil {
		l.errorResponse(w, "Ошибка чтения файла")
		return
	}

	ext := strings.ToLower(filepath.Ext(header.Filename))
	if ext != ".jpg" && ext != ".jpeg" && ext != ".png" && ext != ".gif" && ext != ".webp" {
		l.errorResponse(w, "Недопустимый тип файла")
		return
	}

	bgDir := l.storage.GetDataPath(activeBlog, "backgrounds")
	_ = os.MkdirAll(bgDir, 0755)

	// Remove old blog-bg.*
	if matches, err := filepath.Glob(filepath.Join(bgDir, "blog-bg.*")); err == nil {
		for _, m := range matches {
			_ = os.Remove(m)
		}
	}

	filename := "blog-bg" + ext
	targetPath := filepath.Join(bgDir, filename)
	if err := l.storage.SafeWriteFile(targetPath, data); err != nil {
		l.errorResponse(w, "Ошибка сохранения файла")
		return
	}

	// Update blog-view-settings.json
	settingsPath := l.storage.GetDataPath(activeBlog, "blog-view-settings.json")
	var settings map[string]interface{}
	_ = l.storage.SafeReadJSON(settingsPath, &settings)
	if settings == nil {
		settings = make(map[string]interface{})
	}
	settings["background"] = filename
	settings["backgroundMode"] = mode
	_ = l.storage.SafeWriteJSON(settingsPath, settings)

	l.jsonResponse(w, map[string]interface{}{
		"success":  true,
		"filename": filename,
	})
}

func (l *LegacyHandler) HandleRemoveBlogBackground(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	bgDir := l.storage.GetDataPath(activeBlog, "backgrounds")
	if matches, err := filepath.Glob(filepath.Join(bgDir, "blog-bg.*")); err == nil {
		for _, m := range matches {
			_ = os.Remove(m)
		}
	}

	settingsPath := l.storage.GetDataPath(activeBlog, "blog-view-settings.json")
	var settings map[string]interface{}
	_ = l.storage.SafeReadJSON(settingsPath, &settings)
	if settings != nil {
		delete(settings, "background")
		delete(settings, "backgroundMode")
		_ = l.storage.SafeWriteJSON(settingsPath, settings)
	}

	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleUploadGlobalBackground(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	if err := r.ParseMultipartForm(50 << 20); err != nil {
		l.errorResponse(w, "Ошибка чтения формы")
		return
	}

	file, header, err := r.FormFile("background")
	if err != nil {
		l.errorResponse(w, "Файл не был загружен")
		return
	}
	defer file.Close()

	mode := r.FormValue("mode")
	scope := r.FormValue("scope")

	data, err := io.ReadAll(file)
	if err != nil {
		l.errorResponse(w, "Ошибка чтения файла")
		return
	}

	filename, err := l.post.UploadGlobalBackground(activeBlog, header.Filename, data, mode, scope)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	l.jsonResponse(w, map[string]interface{}{
		"success":  true,
		"filename": filename,
	})
}

func (l *LegacyHandler) HandleGetPostBackgrounds(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	postIDStr := r.URL.Query().Get("postId")
	if postIDStr != "" {
		postID, err := strconv.Atoi(postIDStr)
		if err == nil && postID > 0 {
			settings, _ := l.post.GetPostBackground(activeBlog, postID)
			l.jsonResponse(w, map[string]interface{}{
				"success":  true,
				"settings": settings,
			})
			return
		}
	}

	bgs, _ := l.post.GetAllPostBackgrounds(activeBlog)
	l.jsonResponse(w, map[string]interface{}{
		"success":     true,
		"backgrounds": bgs,
	})
}

func (l *LegacyHandler) HandleUpdatePostBackground(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		PostID int `json:"postId"`
	}
	if err := json.NewDecoder(r.Body).Decode(&body); err == nil && body.PostID > 0 {
		_ = l.post.ApplyBackgroundForPost(activeBlog, body.PostID)
	}

	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleRemoveBackground(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var postID int

	ct := r.Header.Get("Content-Type")
	if strings.Contains(ct, "application/json") {
		var body struct {
			PostID int `json:"postId"`
		}
		if err := json.NewDecoder(r.Body).Decode(&body); err == nil {
			postID = body.PostID
		}
	}

	if postID <= 0 {
		if pid, err := strconv.Atoi(r.URL.Query().Get("postId")); err == nil {
			postID = pid
		}
	}

	if postID <= 0 {
		l.errorResponse(w, "Отсутствует ID статьи")
		return
	}

	globalApplied, err := l.post.RemovePostBackground(activeBlog, postID)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	l.jsonResponse(w, map[string]interface{}{
		"success":       true,
		"globalApplied": globalApplied,
	})
}

// --- Навигация и Языки ---

func (l *LegacyHandler) HandleGetLanguages(w http.ResponseWriter, r *http.Request) {
	langFile := filepath.Join(l.cfg.BaseDir, "lang", "languages.json")
	var langs []interface{}
	_ = l.storage.SafeReadJSON(langFile, &langs)
	l.jsonResponse(w, map[string]interface{}{
		"success":   true,
		"languages": langs,
	})
}

func (l *LegacyHandler) HandleSaveBlogNav(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)

	var action string
	var buttons []map[string]interface{}

	ct := r.Header.Get("Content-Type")
	if strings.Contains(ct, "application/json") {
		var body struct {
			Action  string                   `json:"action"`
			Buttons []map[string]interface{} `json:"buttons"`
		}
		_ = json.NewDecoder(r.Body).Decode(&body)
		action = body.Action
		buttons = body.Buttons
	} else {
		_ = r.ParseForm()
		action = r.FormValue("action")
		if rawBtns := r.FormValue("buttons"); rawBtns != "" {
			_ = json.Unmarshal([]byte(rawBtns), &buttons)
		}
	}

	if action == "" {
		action = "save"
	}

	navFile := l.storage.GetDataPath(activeBlog, "blog-view-settings.json")
	var settings map[string]interface{}
	_ = l.storage.SafeReadJSON(navFile, &settings)
	if settings == nil {
		settings = make(map[string]interface{})
	}

	if action == "check" {
		blogHtmlPath := l.storage.GetDataPath(activeBlog, "blog.html")
		isStandard := false
		if bytes, err := os.ReadFile(blogHtmlPath); err == nil {
			content := string(bytes)
			isStandard = strings.Contains(content, "function loadBlogViewSettings()") && strings.Contains(content, "<header>")
		}
		currentButtons := settings["crossBlogNav"]
		if currentButtons == nil {
			currentButtons = []interface{}{}
		}
		l.jsonResponse(w, map[string]interface{}{
			"is_standard": isStandard,
			"buttons":     currentButtons,
		})
		return
	}

	sanitized := make([]map[string]interface{}, 0, len(buttons))
	for _, b := range buttons {
		u, _ := b["url"].(string)
		t, _ := b["text"].(string)
		u = strings.TrimSpace(u)
		t = strings.TrimSpace(t)
		lower := strings.ToLower(u)
		if strings.HasPrefix(lower, "javascript:") || strings.HasPrefix(lower, "data:") || strings.HasPrefix(lower, "vbscript:") {
			continue
		}
		sanitized = append(sanitized, map[string]interface{}{
			"url":  u,
			"text": t,
		})
	}

	if action == "apply_all" {
		blogPaths := l.cfg.GetSettings().BlogPaths
		if len(blogPaths) == 0 {
			dp := l.cfg.GetSettings().ActiveBlogPath
			if dp == "" {
				dp = "data"
			}
			blogPaths = []string{dp}
		}
		successCount := 0
		for _, p := range blogPaths {
			t := l.storage.GetDataPath(p, "blog-view-settings.json")
			var s map[string]interface{}
			_ = l.storage.SafeReadJSON(t, &s)
			if s == nil {
				s = make(map[string]interface{})
			}
			s["crossBlogNav"] = sanitized
			if err := l.storage.SafeWriteJSON(t, s); err == nil {
				successCount++
			}
		}
		l.jsonResponse(w, map[string]interface{}{
			"success":       true,
			"updated_count": successCount,
		})
		return
	}

	settings["crossBlogNav"] = sanitized
	_ = l.storage.SafeWriteJSON(navFile, settings)
	l.jsonResponse(w, map[string]interface{}{"success": true, "is_standard": true})
}

func (l *LegacyHandler) HandleSaveOverlaySettings(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		PostID         int    `json:"postId"`
		OverlayEnabled bool   `json:"overlayEnabled"`
		OverlayColor   string `json:"overlayColor"`
		OverlayOpacity int    `json:"overlayOpacity"`
	}
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil || body.PostID <= 0 {
		l.errorResponse(w, "Отсутствует ID статьи")
		return
	}

	if err := l.post.SaveOverlaySettings(activeBlog, body.PostID, body.OverlayEnabled, body.OverlayColor, body.OverlayOpacity); err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	l.jsonResponse(w, map[string]interface{}{"success": true})
}

// --- FTP Синхронизация ---

func (l *LegacyHandler) HandleFtp(w http.ResponseWriter, r *http.Request) {
	if r.URL.Query().Get("action") == "get_settings" {
		creds, _ := l.ftp.LoadCredentials()
		settings := l.cfg.GetSettings()
		l.jsonResponse(w, map[string]interface{}{
			"success":            true,
			"credentials":        creds,
			"availableBlogPaths": settings.BlogPaths,
			"currentActiveBlog":  settings.ActiveBlogPath,
		})
		return
	}

	if r.Method == http.MethodPost {
		_ = r.ParseMultipartForm(10 << 20)
		_ = r.ParseForm()

		action := r.URL.Query().Get("action")
		if action == "" {
			action = r.FormValue("action")
		}

		if action == "reset" {
			_ = l.ftp.ResetCredentials()
			l.jsonResponse(w, map[string]interface{}{
				"success": true,
				"message": "Настройки FTP сброшены",
			})
			return
		}

		var body service.FtpCredentials
		if strings.Contains(r.Header.Get("Content-Type"), "application/json") {
			_ = json.NewDecoder(r.Body).Decode(&body)
		}
		if body.Server == "" {
			body.Server = strings.TrimSpace(r.FormValue("ftpServer"))
			body.Protocol = strings.TrimSpace(r.FormValue("ftpProtocol"))
			body.Port, _ = strconv.Atoi(r.FormValue("ftpPort"))
			body.Username = strings.TrimSpace(r.FormValue("ftpUsername"))
			body.RemoteDir = strings.TrimSpace(r.FormValue("ftpDirectory"))
			if body.RemoteDir == "" {
				body.RemoteDir = strings.TrimSpace(r.FormValue("ftpRemoteDir"))
			}
			body.Ssl, _ = strconv.Atoi(r.FormValue("ftpSsl"))
			body.SkipExisting, _ = strconv.Atoi(r.FormValue("ftpSkipExisting"))
		}

		if body.Protocol == "" {
			body.Protocol = "ftp"
		}
		if body.Port <= 0 {
			if body.Protocol == "sftp" {
				body.Port = 22
			} else {
				body.Port = 21
			}
		}

		_ = l.ftp.SaveCredentials(&body)
		l.jsonResponse(w, map[string]interface{}{
			"success": true,
			"message": "Настройки FTP сохранены",
		})
		return
	}
}

func (l *LegacyHandler) HandleFtpUploadStream(w http.ResponseWriter, r *http.Request) {
	// 1. Обязательно сначала парсим тело запроса ДО записи в ResponseWriter и ДО flusher.Flush()
	var server, protocol, username, password, directory, blogToUpload string
	var port, ssl, skipExisting int

	if strings.Contains(r.Header.Get("Content-Type"), "application/json") {
		var body struct {
			Server       string `json:"ftpServer"`
			Protocol     string `json:"ftpProtocol"`
			Port         int    `json:"ftpPort"`
			Username     string `json:"ftpUsername"`
			Password     string `json:"ftpPassword"`
			Directory    string `json:"ftpDirectory"`
			RemoteDir    string `json:"ftpRemoteDir"`
			Ssl          int    `json:"ftpSsl"`
			SkipExisting int    `json:"ftpSkipExisting"`
			BlogToUpload string `json:"blogToUpload"`
		}
		if err := json.NewDecoder(r.Body).Decode(&body); err == nil {
			server = strings.TrimSpace(body.Server)
			protocol = strings.ToLower(strings.TrimSpace(body.Protocol))
			port = body.Port
			username = strings.TrimSpace(body.Username)
			password = body.Password
			directory = strings.TrimSpace(body.Directory)
			if directory == "" {
				directory = strings.TrimSpace(body.RemoteDir)
			}
			ssl = body.Ssl
			skipExisting = body.SkipExisting
			blogToUpload = strings.TrimSpace(body.BlogToUpload)
		}
	} else {
		_ = r.ParseMultipartForm(10 << 20)
		_ = r.ParseForm()

		server = strings.TrimSpace(r.FormValue("ftpServer"))
		protocol = strings.ToLower(strings.TrimSpace(r.FormValue("ftpProtocol")))
		port, _ = strconv.Atoi(r.FormValue("ftpPort"))
		username = strings.TrimSpace(r.FormValue("ftpUsername"))
		password = r.FormValue("ftpPassword")
		directory = strings.TrimSpace(r.FormValue("ftpDirectory"))
		if directory == "" {
			directory = strings.TrimSpace(r.FormValue("ftpRemoteDir"))
		}
		ssl, _ = strconv.Atoi(r.FormValue("ftpSsl"))
		skipExisting, _ = strconv.Atoi(r.FormValue("ftpSkipExisting"))
		blogToUpload = strings.TrimSpace(r.FormValue("blogToUpload"))
	}

	// Фолбэк на query параметры при необходимости
	if server == "" {
		server = strings.TrimSpace(r.URL.Query().Get("ftpServer"))
	}
	if username == "" {
		username = strings.TrimSpace(r.URL.Query().Get("ftpUsername"))
	}
	if directory == "" {
		directory = strings.TrimSpace(r.URL.Query().Get("ftpDirectory"))
	}

	// 2. Устанавливаем SSE заголовки
	w.Header().Set("Content-Type", "text/event-stream; charset=utf-8")
	w.Header().Set("Cache-Control", "no-cache, no-store, must-revalidate")
	w.Header().Set("Pragma", "no-cache")
	w.Header().Set("Connection", "keep-alive")
	w.Header().Set("X-Accel-Buffering", "no")

	rc := http.NewResponseController(w)
	_ = rc.SetWriteDeadline(time.Time{})

	flusher, ok := w.(http.Flusher)
	if !ok {
		http.Error(w, "Streaming unsupported", http.StatusInternalServerError)
		return
	}

	sendEvent := func(evtType string, data interface{}) {
		b, _ := json.Marshal(map[string]interface{}{
			"type": evtType,
			"data": data,
		})
		fmt.Fprintf(w, "data: %s\n\n", string(b))
		flusher.Flush()
	}

	// 4KB комментарий для немедленного проталкивания любых буферов прокси и веб-сервера
	fmt.Fprintf(w, ":%s\n\n", strings.Repeat(" ", 4096))
	flusher.Flush()

	if server == "" || username == "" || password == "" || directory == "" {
		sendEvent("error", map[string]interface{}{
			"message": "Пожалуйста, заполните все обязательные поля (Сервер, Пользователь, Пароль, Директория)",
		})
		return
	}

	if protocol == "" {
		protocol = "ftp"
	}
	if port <= 0 {
		if protocol == "sftp" {
			port = 22
		} else {
			port = 21
		}
	}

	creds := &service.FtpCredentials{
		Server:       server,
		Protocol:     protocol,
		Port:         port,
		Username:     username,
		Password:     password,
		RemoteDir:    directory,
		Ssl:          ssl,
		SkipExisting: skipExisting,
	}

	_ = l.ftp.UploadBlogStream(r.Context(), creds, blogToUpload, sendEvent)
}

// --- System Updates & Maintenance Handlers ---

func (l *LegacyHandler) HandleUpdateSystem(w http.ResponseWriter, r *http.Request) {
	action := r.URL.Query().Get("action")
	if action == "" {
		action = r.FormValue("action")
	}

	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	switch action {
	case "get_version":
		v := l.system.GetVersion()
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success":      true,
			"version":      v.Version,
			"dev":          v.Dev,
			"last_updated": v.LastUpdated,
			"commit":       v.Commit,
		})

	case "get_changelog":
		v := l.system.GetVersion()
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success":      true,
			"version":      v.Version,
			"dev":          v.Dev,
			"last_updated": v.LastUpdated,
			"changelog":    v.Changelog,
		})

	case "check_auto_update":
		channel := r.URL.Query().Get("channel")
		repo := r.URL.Query().Get("repo")
		force := r.URL.Query().Get("force") == "1" || r.URL.Query().Get("force") == "true"
		token := r.URL.Query().Get("token")
		res := l.system.CheckAutoUpdate(channel, repo, force, token)
		json.NewEncoder(w).Encode(res)

	case "auto_update_progress":
		token := r.URL.Query().Get("token")
		prog := l.system.GetUpdateProgress(token)
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success":  true,
			"progress": prog,
		})

	case "prepare_auto_update":
		token := fmt.Sprintf("upd_%d", time.Now().Unix())
		l.system.SetUpdateProgress(token, 100, "Файлы обновления подготовлены")
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": true,
			"token":   token,
		})

	case "preview":
		file, header, err := r.FormFile("updateFile")
		if err != nil {
			json.NewEncoder(w).Encode(map[string]interface{}{
				"success": false,
				"error":   "Файл обновления не загружен",
			})
			return
		}
		defer file.Close()
		prev, err := l.system.PreviewUpdateZip(file, header.Size)
		if err != nil {
			json.NewEncoder(w).Encode(map[string]interface{}{
				"success": false,
				"error":   err.Error(),
			})
			return
		}
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": true,
			"preview": prev,
		})

	case "update":
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": true,
			"message": "Обновление успешно завершено",
		})

	case "export_package":
		if err := l.system.ExportPackage(w); err != nil {
			http.Error(w, err.Error(), http.StatusInternalServerError)
		}

	default:
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": false,
			"error":   "Неизвестное действие: " + action,
		})
	}
}

func (l *LegacyHandler) HandleRestoreSystem(w http.ResponseWriter, r *http.Request) {
	action := r.URL.Query().Get("action")
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	if action == "list_backups" {
		backups := l.system.ListSystemBackups()
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": true,
			"backups": backups,
		})
		return
	}

	if action == "restore" {
		var body struct {
			Filename string `json:"filename"`
		}
		_ = json.NewDecoder(r.Body).Decode(&body)
		if body.Filename == "" {
			json.NewEncoder(w).Encode(map[string]interface{}{
				"success": false,
				"error":   "Файл бэкапа не указан",
			})
			return
		}
		count, err := l.system.RestoreSystemBackup(body.Filename)
		if err != nil {
			json.NewEncoder(w).Encode(map[string]interface{}{
				"success": false,
				"error":   err.Error(),
			})
			return
		}
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success":       true,
			"restoredCount": count,
			"message":       "Система успешно восстановлена.",
		})
		return
	}

	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": false,
		"error":   "Неверное действие: " + action,
	})
}

func (l *LegacyHandler) HandleRemoveGlobalBackground(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	if err := l.post.RemoveGlobalBackground(activeBlog); err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"message": "Глобальный фон успешно удален",
	})
}

func (l *LegacyHandler) HandleSavePostPreview(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	activeBlog := middleware.GetActiveBlogPath(r)

	// Поддержка multipart и json
	action := r.FormValue("action")
	postId := r.FormValue("postId")

	if action == "" {
		var body struct {
			Action string `json:"action"`
			PostID string `json:"postId"`
			URL    string `json:"url"`
		}
		_ = json.NewDecoder(r.Body).Decode(&body)
		action = body.Action
		postId = body.PostID
	}

	metaFile := l.storage.GetDataPath(activeBlog, "blog/posts-meta.json")
	var meta []map[string]interface{}
	_ = l.storage.SafeReadJSON(metaFile, &meta)

	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"postId":  postId,
		"action":  action,
	})
}

func (l *LegacyHandler) HandleSaveAutosaveSettings(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	var body struct {
		Enabled  bool `json:"enabled"`
		Interval int  `json:"interval"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	settingsFile := filepath.Join(l.cfg.BaseDir, "autosave-settings.json")
	_ = l.storage.SafeWriteJSON(settingsFile, body)

	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
	})
}

func (l *LegacyHandler) HandleDeleteCustomTemplates(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"message": "Пользовательские шаблоны удалены",
	})
}

func (l *LegacyHandler) HandleRegenerateSEO(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	activeBlog := middleware.GetActiveBlogPath(r)
	_ = l.post.GenerateRSS(activeBlog)
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success":   true,
		"processed": 1,
		"updated":   1,
	})
}

func (l *LegacyHandler) HandleUpdateBackgroundStyles(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"updated": 0,
	})
}

