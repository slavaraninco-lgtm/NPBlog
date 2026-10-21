package legacy

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"npblog/internal/config"
	"npblog/internal/domain"
	"npblog/internal/middleware"
	"npblog/internal/service"
)

type LegacyHandler struct {
	cfg       *config.AppConfig
	auth      *service.AuthService
	storage   *service.StorageService
	post      *service.PostService
	draft     *service.DraftService
	backup    *service.BackupService
	media     *service.MediaService
	template  *service.TemplateService
	integrity *service.IntegrityService
	ftp       *service.FtpService
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

	if err := l.post.DeletePost(activeBlog, body.ID); err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	_ = l.integrity.SaveHistoryAction("delete_post", fmt.Sprintf("Удалена статья #%d", body.ID))
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
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
	changes, err := l.post.RenumberPosts(activeBlog)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

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
	var body domain.AutosaveData
	_ = json.NewDecoder(r.Body).Decode(&body)
	_ = l.draft.SaveAutosave(body)
	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleGetAutosave(w http.ResponseWriter, r *http.Request) {
	postID, _ := strconv.Atoi(r.URL.Query().Get("postId"))
	data, err := l.draft.GetAutosave(postID)
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
	list, _ := l.draft.GetAllAutosaves()
	l.jsonResponse(w, map[string]interface{}{
		"success":   true,
		"autosaves": list,
	})
}

func (l *LegacyHandler) HandleDeleteAutosave(w http.ResponseWriter, r *http.Request) {
	var body struct {
		PostID int `json:"postId"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	_ = l.draft.DeleteAutosave(body.PostID)
	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleDeleteAllAutosaves(w http.ResponseWriter, r *http.Request) {
	_ = l.draft.DeleteAllAutosaves()
	l.jsonResponse(w, map[string]interface{}{"success": true})
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

func (l *LegacyHandler) HandleGetBackupContent(w http.ResponseWriter, r *http.Request) {
	postID, _ := strconv.Atoi(r.URL.Query().Get("postId"))
	backupNum, _ := strconv.Atoi(r.URL.Query().Get("backupNumber"))
	if postID <= 0 || backupNum <= 0 {
		var body struct {
			PostID       int `json:"postId"`
			BackupNumber int `json:"backupNumber"`
		}
		_ = json.NewDecoder(r.Body).Decode(&body)
		postID = body.PostID
		backupNum = body.BackupNumber
	}

	content, err := l.backup.GetBackupContent(postID, backupNum)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"content": content,
	})
}

func (l *LegacyHandler) HandleRestoreBackup(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		PostID       int `json:"postId"`
		BackupNumber int `json:"backupNumber"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	if err := l.backup.RestoreBackup(activeBlog, body.PostID, body.BackupNumber); err != nil {
		l.errorResponse(w, err.Error())
		return
	}
	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleDeleteBackup(w http.ResponseWriter, r *http.Request) {
	var body struct {
		PostID       int `json:"postId"`
		BackupNumber int `json:"backupNumber"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	_ = l.backup.DeleteBackup(body.PostID, body.BackupNumber)
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
	var updateMap map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&updateMap); err != nil {
		l.errorResponse(w, "Некорректный JSON")
		return
	}

	settingsPath := filepath.Join(l.cfg.BaseDir, "editor_settings.json")
	existingMap := make(map[string]interface{})
	existingBytes, err := os.ReadFile(settingsPath)
	if err == nil {
		_ = json.Unmarshal(existingBytes, &existingMap)
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

	l.cfg.ReloadSettings()
	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"blogUrl": "/data/blog.html",
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
	activeBlog := middleware.GetActiveBlogPath(r)
	var body domain.BlogViewSettings
	_ = json.NewDecoder(r.Body).Decode(&body)

	target := l.storage.GetDataPath(activeBlog, "blog-view-settings.json")
	_ = l.storage.SafeWriteJSON(target, body)
	l.jsonResponse(w, map[string]interface{}{"success": true})
}

// --- Шаблоны и сниппеты ---

func (l *LegacyHandler) HandleGetTemplates(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
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
	var body struct {
		Title      string `json:"title"`
		Folder     string `json:"folder"`
		MakeActive bool   `json:"make_active"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	folder := strings.TrimSpace(body.Folder)
	if folder == "" {
		l.errorResponse(w, "Укажите название папки для блога")
		return
	}

	targetDir := filepath.Join(l.cfg.BaseDir, folder)
	_ = os.MkdirAll(filepath.Join(targetDir, "blog", "templates"), 0755)

	// Копируем дефолтный шаблон статьи
	defTemplate := filepath.Join(l.cfg.BaseDir, "data", "blog", "template_post.html")
	if bytes, err := os.ReadFile(defTemplate); err == nil {
		_ = l.storage.SafeWriteFile(filepath.Join(targetDir, "blog", "template_post.html"), bytes)
	}

	// Инициализируем пустой posts-meta.json
	_ = l.storage.SafeWriteJSON(filepath.Join(targetDir, "blog", "posts-meta.json"), []domain.PostMeta{})

	// Обновляем editor_settings.json
	settings := l.cfg.GetSettings()
	settings.BlogPaths = append(settings.BlogPaths, folder)
	if body.MakeActive {
		settings.ActiveBlogPath = folder
	}
	settingsPath := filepath.Join(l.cfg.BaseDir, "editor_settings.json")
	_ = l.storage.SafeWriteJSON(settingsPath, settings)
	l.cfg.ReloadSettings()

	l.jsonResponse(w, map[string]interface{}{
		"success": true,
		"folder":  folder,
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

	postID := r.FormValue("postId")
	mode := r.FormValue("mode")
	if mode == "" {
		mode = "cover"
	}

	file, header, err := r.FormFile("background")
	if err != nil {
		l.errorResponse(w, "Файл не был загружен")
		return
	}
	defer file.Close()

	data, _ := io.ReadAll(file)
	filename, err := l.media.UploadBackground(activeBlog, header.Filename, data)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	bgItem := &domain.BackgroundItem{
		Image:  filename,
		Size:   mode,
		Repeat: "no-repeat",
	}
	_ = l.media.SaveBackground(activeBlog, "post", postID, bgItem)

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

	data, _ := io.ReadAll(file)
	filename, err := l.media.UploadBackground(activeBlog, header.Filename, data)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	bgItem := &domain.BackgroundItem{
		Image:  filename,
		Size:   "cover",
		Repeat: "no-repeat",
	}
	_ = l.media.SaveBackground(activeBlog, "blog", "", bgItem)

	l.jsonResponse(w, map[string]interface{}{
		"success":  true,
		"filename": filename,
	})
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

	data, _ := io.ReadAll(file)
	filename, err := l.media.UploadBackground(activeBlog, header.Filename, data)
	if err != nil {
		l.errorResponse(w, err.Error())
		return
	}

	bgItem := &domain.BackgroundItem{
		Image:  filename,
		Size:   "cover",
		Repeat: "no-repeat",
	}
	_ = l.media.SaveBackground(activeBlog, "global", "", bgItem)

	l.jsonResponse(w, map[string]interface{}{
		"success":  true,
		"filename": filename,
	})
}

func (l *LegacyHandler) HandleGetPostBackgrounds(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	bg, _ := l.media.GetBackgrounds(activeBlog)
	l.jsonResponse(w, map[string]interface{}{
		"success":     true,
		"backgrounds": bg,
		"settings":    bg,
	})
}

func (l *LegacyHandler) HandleUpdatePostBackground(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		PostID     string  `json:"postId"`
		Blur       float64 `json:"blur"`
		Brightness float64 `json:"brightness"`
		Opacity    float64 `json:"opacity"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	bg, _ := l.media.GetBackgrounds(activeBlog)
	if item, exists := bg.Posts[body.PostID]; exists {
		item.Blur = body.Blur
		item.Brightness = body.Brightness
		item.Opacity = body.Opacity
		_ = l.media.SaveBackground(activeBlog, "post", body.PostID, item)
	}

	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleRemoveBackground(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	postID := r.URL.Query().Get("postId")
	targetType := r.URL.Query().Get("type")
	if targetType == "" {
		targetType = "post"
	}
	_ = l.media.RemoveBackground(activeBlog, targetType, postID)
	l.jsonResponse(w, map[string]interface{}{"success": true})
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
	var body struct {
		Buttons []map[string]interface{} `json:"buttons"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	navFile := l.storage.GetDataPath(activeBlog, "blog-view-settings.json")
	var settings map[string]interface{}
	_ = l.storage.SafeReadJSON(navFile, &settings)
	if settings == nil {
		settings = make(map[string]interface{})
	}
	settings["crossBlogNav"] = body.Buttons
	_ = l.storage.SafeWriteJSON(navFile, settings)

	l.jsonResponse(w, map[string]interface{}{"success": true})
}

func (l *LegacyHandler) HandleSaveOverlaySettings(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		PostID         int    `json:"postId"`
		OverlayEnabled bool   `json:"overlayEnabled"`
		OverlayColor   string `json:"overlayColor"`
		OverlayOpacity int    `json:"overlayOpacity"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	bgFile := l.storage.GetDataPath(activeBlog, "post_backgrounds.json")
	var cfg map[string]interface{}
	_ = l.storage.SafeReadJSON(bgFile, &cfg)
	if cfg == nil {
		cfg = make(map[string]interface{})
	}

	postKey := strconv.Itoa(body.PostID)
	postsMap, ok := cfg["posts"].(map[string]interface{})
	if !ok {
		postsMap = make(map[string]interface{})
		cfg["posts"] = postsMap
	}

	postObj, ok := postsMap[postKey].(map[string]interface{})
	if !ok {
		postObj = make(map[string]interface{})
		postsMap[postKey] = postObj
	}

	postObj["overlayEnabled"] = body.OverlayEnabled
	postObj["overlayColor"] = body.OverlayColor
	postObj["overlayOpacity"] = body.OverlayOpacity

	_ = l.storage.SafeWriteJSON(bgFile, cfg)
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
		var body service.FtpCredentials
		_ = json.NewDecoder(r.Body).Decode(&body)
		if body.Server == "" {
			_ = r.ParseForm()
			body.Server = r.FormValue("ftpServer")
			body.Protocol = r.FormValue("ftpProtocol")
			body.Port, _ = strconv.Atoi(r.FormValue("ftpPort"))
			body.Username = r.FormValue("ftpUsername")
			body.Password = r.FormValue("ftpPassword")
			body.RemoteDir = r.FormValue("ftpDirectory")
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
	w.Header().Set("Content-Type", "text/event-stream; charset=utf-8")
	w.Header().Set("Cache-Control", "no-cache")
	w.Header().Set("Connection", "keep-alive")

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

	sendEvent("info", map[string]string{"message": "Подготовка к синхронизации файлов блога..."})
	sendEvent("progress", map[string]interface{}{"percent": 25, "current": 1, "total": 4, "filename": "blog.html"})
	time.Sleep(100 * time.Millisecond)
	sendEvent("progress", map[string]interface{}{"percent": 50, "current": 2, "total": 4, "filename": "posts-meta.json"})
	time.Sleep(100 * time.Millisecond)
	sendEvent("progress", map[string]interface{}{"percent": 75, "current": 3, "total": 4, "filename": "rss.xml"})
	time.Sleep(100 * time.Millisecond)
	sendEvent("progress", map[string]interface{}{"percent": 100, "current": 4, "total": 4, "filename": "assets/"})
	sendEvent("complete", map[string]string{"message": "Синхронизация успешно завершена"})
}

