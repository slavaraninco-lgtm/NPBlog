package rest

import (
	"encoding/json"
	"fmt"
	"html"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"

	"github.com/go-chi/chi/v5"
	"npblog/internal/config"
	"npblog/internal/domain"
	"npblog/internal/middleware"
	"npblog/internal/service"
)

type RestHandler struct {
	cfg       *config.AppConfig
	auth      *service.AuthService
	storage   *service.StorageService
	post      *service.PostService
	draft     *service.DraftService
	backup    *service.BackupService
	media     *service.MediaService
	template  *service.TemplateService
	integrity *service.IntegrityService
	formula   *service.FormulaService
}

func NewRestHandler(
	cfg *config.AppConfig,
	auth *service.AuthService,
	storage *service.StorageService,
	post *service.PostService,
	draft *service.DraftService,
	backup *service.BackupService,
	media *service.MediaService,
	template *service.TemplateService,
	integrity *service.IntegrityService,
	formula *service.FormulaService,
) *RestHandler {
	return &RestHandler{
		cfg:       cfg,
		auth:      auth,
		storage:   storage,
		post:      post,
		draft:     draft,
		backup:    backup,
		media:     media,
		template:  template,
		integrity: integrity,
		formula:   formula,
	}
}

func (h *RestHandler) jsonSuccess(w http.ResponseWriter, status int, message string, data interface{}, meta interface{}) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"status":  status,
		"message": message,
		"data":    data,
		"meta":    meta,
	})
}

func (h *RestHandler) jsonError(w http.ResponseWriter, status int, code, message string, details interface{}) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(map[string]interface{}{
		"success": false,
		"status":  status,
		"error": map[string]interface{}{
			"code":    code,
			"message": message,
			"details": details,
		},
	})
}

// --- Auth Routes ---

func (h *RestHandler) HandleLogin(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	clientIP := h.auth.GetClientIP(r)

	isLocked, remaining, _ := h.auth.GetLockoutInfo(clientIP, activeBlog)
	if isLocked {
		h.jsonError(w, http.StatusTooManyRequests, "lockout_active", "Превышен лимит попыток входа", map[string]interface{}{
			"lockout_time_remaining": remaining,
		})
		return
	}

	var body struct {
		Password   string `json:"password"`
		DeviceName string `json:"device_name"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	if !h.auth.VerifyPassword(body.Password) {
		attempts, lockoutDur := h.auth.RecordFailedLogin(clientIP, activeBlog)
		details := map[string]interface{}{
			"attempts": attempts,
		}
		if lockoutDur > 0 {
			details["lockout_time_remaining"] = lockoutDur
		}
		h.jsonError(w, http.StatusUnauthorized, "invalid_credentials", "Неверный пароль", details)
		return
	}

	h.auth.ResetLockout(clientIP, activeBlog)

	if body.DeviceName == "" {
		body.DeviceName = "REST Client"
	}
	token, err := h.auth.CreateApiToken(body.DeviceName)
	if err != nil {
		h.jsonError(w, http.StatusInternalServerError, "server_error", "Ошибка генерации токена", nil)
		return
	}

	h.jsonSuccess(w, http.StatusOK, "Авторизация успешна", token, nil)
}

func (h *RestHandler) HandleLogout(w http.ResponseWriter, r *http.Request) {
	h.jsonSuccess(w, http.StatusOK, "Токен успешно отозван", nil, nil)
}

func (h *RestHandler) HandleMe(w http.ResponseWriter, r *http.Request) {
	h.jsonSuccess(w, http.StatusOK, "Информация о текущей сессии", map[string]interface{}{
		"authenticated": true,
		"active_blog":   middleware.GetActiveBlogPath(r),
	}, nil)
}

// --- Posts Routes ---

func (h *RestHandler) HandleListPosts(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	metaFile := h.storage.GetDataPath(activeBlog, "blog/posts-meta.json")
	var posts []domain.PostMeta
	_ = h.storage.SafeReadJSON(metaFile, &posts)

	h.jsonSuccess(w, http.StatusOK, "Список статей получен", posts, map[string]interface{}{
		"total": len(posts),
	})
}

func (h *RestHandler) HandleGetPost(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	idStr := chi.URLParam(r, "id")
	id, _ := strconv.Atoi(idStr)

	title, content, err := h.post.GetPostContent(activeBlog, id)
	if err != nil {
		h.jsonError(w, http.StatusNotFound, "post_not_found", "Статья не найдена", nil)
		return
	}

	h.jsonSuccess(w, http.StatusOK, "Статья получена", map[string]interface{}{
		"id":      id,
		"title":   title,
		"content": content,
	}, nil)
}

func (h *RestHandler) HandleCreatePost(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		Title   string `json:"title"`
		Content string `json:"content"`
	}
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil || body.Title == "" {
		h.jsonError(w, http.StatusUnprocessableEntity, "validation_error", "Заголовок обязателен для заполнения", nil)
		return
	}

	nextID, err := h.post.SavePost(activeBlog, body.Title, body.Content)
	if err != nil {
		h.jsonError(w, http.StatusInternalServerError, "server_error", err.Error(), nil)
		return
	}

	h.jsonSuccess(w, http.StatusCreated, "Статья успешно создана", map[string]interface{}{
		"id":    nextID,
		"title": body.Title,
	}, nil)
}

func (h *RestHandler) HandleUpdatePost(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	idStr := chi.URLParam(r, "id")
	id, _ := strconv.Atoi(idStr)

	var body struct {
		Title   string `json:"title"`
		Content string `json:"content"`
	}
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil || body.Title == "" {
		h.jsonError(w, http.StatusUnprocessableEntity, "validation_error", "Заголовок обязателен", nil)
		return
	}

	if err := h.post.UpdatePost(activeBlog, id, body.Title, body.Content); err != nil {
		h.jsonError(w, http.StatusNotFound, "post_not_found", err.Error(), nil)
		return
	}

	h.jsonSuccess(w, http.StatusOK, "Статья успешно обновлена", map[string]interface{}{
		"id":    id,
		"title": body.Title,
	}, nil)
}

func (h *RestHandler) HandleDeletePost(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	idStr := chi.URLParam(r, "id")
	id, _ := strconv.Atoi(idStr)

	if err := h.post.DeletePost(activeBlog, id); err != nil {
		h.jsonError(w, http.StatusInternalServerError, "server_error", err.Error(), nil)
		return
	}

	h.jsonSuccess(w, http.StatusOK, "Статья успешно удалена", nil, nil)
}

// --- Formulas Route (KaTeX) ---

func (h *RestHandler) HandleRenderFormula(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Tex         string `json:"tex"`
		DisplayMode bool   `json:"displayMode"`
	}
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil || body.Tex == "" {
		h.jsonError(w, http.StatusBadRequest, "invalid_request", "Не передан LaTeX текст формулы", nil)
		return
	}

	res, err := h.formula.RenderKaTeX(r.Context(), body.Tex, body.DisplayMode)
	if err != nil {
		h.jsonError(w, http.StatusInternalServerError, "katex_render_error", err.Error(), nil)
		return
	}

	if !res.Success {
		h.jsonError(w, http.StatusUnprocessableEntity, "katex_syntax_error", res.Error, nil)
		return
	}

	h.jsonSuccess(w, http.StatusOK, "Формула успешно отрисована", map[string]interface{}{
		"html": res.HTML,
	}, nil)
}

// --- System Status & Integrity ---

func (h *RestHandler) HandleSystemStatus(w http.ResponseWriter, r *http.Request) {
	h.jsonSuccess(w, http.StatusOK, "Система активна", map[string]interface{}{
		"server":  "NPBlog Go Engine",
		"version": "1.0.0",
		"status":  "healthy",
	}, nil)
}

func (h *RestHandler) HandleSystemIntegrity(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	errs, ok := h.integrity.CheckIntegrity(activeBlog)

	h.jsonSuccess(w, http.StatusOK, "Проверка целостности завершена", map[string]interface{}{
		"is_healthy": ok,
		"errors":     errs,
	}, nil)
}

// --- Blogs Routes ---

func (h *RestHandler) HandleListBlogs(w http.ResponseWriter, r *http.Request) {
	settings := h.cfg.GetSettings()
	h.jsonSuccess(w, http.StatusOK, "Список блогов получен", map[string]interface{}{
		"blogs":       settings.BlogPaths,
		"active_blog": settings.ActiveBlogPath,
	}, nil)
}

// --- Autosaves Routes ---

func (h *RestHandler) HandleListAutosaves(w http.ResponseWriter, r *http.Request) {
	list, err := h.draft.GetAllAutosaves()
	if err != nil || list == nil {
		list = []domain.AutosaveData{}
	}
	h.jsonSuccess(w, http.StatusOK, "Список автосохранений получен", map[string]interface{}{
		"autosaves": list,
	}, map[string]interface{}{
		"total": len(list),
	})
}

func (h *RestHandler) HandleGetAutosave(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")
	data, err := h.draft.GetAutosave(id)
	if err != nil {
		h.jsonError(w, http.StatusNotFound, "autosave_not_found", "Автосохранение не найдено", nil)
		return
	}
	h.jsonSuccess(w, http.StatusOK, "Автосохранение получено", map[string]interface{}{
		"autosave": data,
	}, nil)
}

func (h *RestHandler) HandleCreateAutosave(w http.ResponseWriter, r *http.Request) {
	var body struct {
		ID      string `json:"id"`
		Title   string `json:"title"`
		Content string `json:"content"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	if body.Title == "" && body.Content == "" {
		h.jsonError(w, http.StatusUnprocessableEntity, "validation_error", "Нет данных для сохранения", nil)
		return
	}

	saved, err := h.draft.SaveAutosave(domain.AutosaveData{
		ID:      body.ID,
		Title:   body.Title,
		Content: body.Content,
	})
	if err != nil {
		h.jsonError(w, http.StatusInternalServerError, "server_error", "Ошибка сохранения автосохранения", nil)
		return
	}

	h.jsonSuccess(w, http.StatusCreated, "Автосохранение выполнено", map[string]interface{}{
		"id":        saved.ID,
		"timestamp": saved.Timestamp,
		"date":      saved.Date,
	}, nil)
}

func (h *RestHandler) HandleDeleteAutosave(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")
	_ = h.draft.DeleteAutosave(id)
	h.jsonSuccess(w, http.StatusOK, "Автосохранение удалено", nil, nil)
}

func (h *RestHandler) HandleDeleteAllAutosaves(w http.ResponseWriter, r *http.Request) {
	deleted, _ := h.draft.DeleteAllAutosaves()
	h.jsonSuccess(w, http.StatusOK, "Все автосохранения удалены", map[string]interface{}{
		"deleted": deleted,
	}, nil)
}

// --- Drafts Routes ---

func (h *RestHandler) HandleListDrafts(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	drafts, err := h.draft.GetDrafts(activeBlog)
	if err != nil || drafts == nil {
		drafts = []domain.DraftData{}
	}
	h.jsonSuccess(w, http.StatusOK, "Список черновиков получен", map[string]interface{}{
		"drafts": drafts,
	}, map[string]interface{}{
		"total": len(drafts),
	})
}

func (h *RestHandler) HandleCreateDraft(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		Title   string `json:"title"`
		Content string `json:"content"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	filename, ts, err := h.draft.SaveDraft(activeBlog, body.Title, body.Content)
	if err != nil {
		h.jsonError(w, http.StatusInternalServerError, "server_error", "Ошибка сохранения черновика", nil)
		return
	}

	h.jsonSuccess(w, http.StatusCreated, "Черновик успешно сохранен", map[string]interface{}{
		"filename":  filename,
		"timestamp": ts,
	}, nil)
}

func (h *RestHandler) HandleDeleteDraft(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	filename := chi.URLParam(r, "filename")
	if filename == "" {
		filename = r.URL.Query().Get("filename")
	}

	_ = h.draft.DeleteDraft(activeBlog, filename)
	h.jsonSuccess(w, http.StatusOK, "Черновик успешно удален", nil, nil)
}

// --- Includes Routes ---

func (h *RestHandler) HandleListIncludes(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	items, err := h.template.GetIncludes(activeBlog)
	if err != nil || items == nil {
		items = make([]domain.IncludeMetaItem, 0)
	}
	h.jsonSuccess(w, http.StatusOK, "Список includes получен", map[string]interface{}{
		"includes": items,
		"files":    items,
	}, map[string]interface{}{
		"total": len(items),
	})
}

func (h *RestHandler) HandleCreateInclude(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		Name    string `json:"name"`
		Content string `json:"content"`
	}
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil || strings.TrimSpace(body.Name) == "" {
		h.jsonError(w, http.StatusBadRequest, "invalid_request", "Имя include обязательно", nil)
		return
	}

	filename, displayName, err := h.template.SaveInclude(activeBlog, body.Name, body.Content)
	if err != nil {
		h.jsonError(w, http.StatusInternalServerError, "save_error", err.Error(), nil)
		return
	}

	h.jsonSuccess(w, http.StatusCreated, "Include успешно сохранен", map[string]interface{}{
		"filename":    filename,
		"displayName": displayName,
	}, nil)
}

func (h *RestHandler) HandleGetIncludeContent(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	filename := chi.URLParam(r, "filename")
	if filename == "" {
		filename = chi.URLParam(r, "name")
	}
	if filename == "" {
		h.jsonError(w, http.StatusBadRequest, "invalid_request", "Имя файла не указано", nil)
		return
	}

	content, err := h.template.GetIncludeContent(activeBlog, filename)
	if err != nil {
		h.jsonError(w, http.StatusNotFound, "include_not_found", "Include не найден", nil)
		return
	}

	h.jsonSuccess(w, http.StatusOK, "Содержимое include получено", map[string]interface{}{
		"filename": filename,
		"content":  content,
	}, nil)
}

func (h *RestHandler) HandleDeleteInclude(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	filename := chi.URLParam(r, "filename")
	if filename == "" {
		filename = chi.URLParam(r, "name")
	}
	if filename == "" {
		h.jsonError(w, http.StatusBadRequest, "invalid_request", "Имя файла не указано", nil)
		return
	}

	if err := h.template.DeleteInclude(activeBlog, filename); err != nil {
		h.jsonError(w, http.StatusInternalServerError, "delete_error", err.Error(), nil)
		return
	}

	h.jsonSuccess(w, http.StatusOK, "Include успешно удален", nil, nil)
}

// --- Settings Routes ---

func (h *RestHandler) HandleGetEditorSettings(w http.ResponseWriter, r *http.Request) {
	settingsPath := filepath.Join(h.cfg.BaseDir, "editor_settings.json")
	var settings map[string]interface{}
	_ = h.storage.SafeReadJSON(settingsPath, &settings)
	if settings == nil {
		settings = make(map[string]interface{})
	}
	h.jsonSuccess(w, http.StatusOK, "Настройки редактора получены", map[string]interface{}{
		"settings": settings,
	}, nil)
}

func (h *RestHandler) HandleUpdateEditorSettings(w http.ResponseWriter, r *http.Request) {
	var updateMap map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&updateMap); err != nil {
		h.jsonError(w, http.StatusBadRequest, "invalid_json", "Некорректный JSON", nil)
		return
	}

	settingsPath := filepath.Join(h.cfg.BaseDir, "editor_settings.json")
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

	if err := h.storage.SafeWriteJSON(settingsPath, existingMap); err != nil {
		h.jsonError(w, http.StatusInternalServerError, "server_error", "Ошибка сохранения настроек", nil)
		return
	}

	if blogTitle, ok := updateMap["blog_title"].(string); ok && strings.TrimSpace(blogTitle) != "" {
		activeBlog := middleware.GetActiveBlogPath(r)
		bvsPath := h.storage.GetDataPath(activeBlog, "blog-view-settings.json")
		var bvs map[string]interface{}
		_ = h.storage.SafeReadJSON(bvsPath, &bvs)
		if bvs == nil {
			bvs = make(map[string]interface{})
		}
		cleanTitle := strings.TrimSpace(blogTitle)
		bvs["title"] = cleanTitle
		_ = h.storage.SafeWriteJSON(bvsPath, bvs)

		blogHTML := h.storage.GetDataPath(activeBlog, "blog.html")
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

	h.cfg.ReloadSettings()
	h.jsonSuccess(w, http.StatusOK, "Настройки редактора обновлены", map[string]interface{}{
		"settings": existingMap,
		"blogUrl":  "/data/blog.html",
	}, nil)
}

func (h *RestHandler) HandleGetGlobalSettings(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	target := h.storage.GetDataPath(activeBlog, "global-settings.json")
	var settings domain.GlobalSettings
	_ = h.storage.SafeReadJSON(target, &settings)
	h.jsonSuccess(w, http.StatusOK, "Глобальные настройки получены", map[string]interface{}{
		"settings": settings,
	}, nil)
}

func (h *RestHandler) HandleUpdateGlobalSettings(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body domain.GlobalSettings
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		h.jsonError(w, http.StatusBadRequest, "invalid_json", "Некорректный JSON", nil)
		return
	}

	target := h.storage.GetDataPath(activeBlog, "global-settings.json")
	if err := h.storage.SafeWriteJSON(target, body); err != nil {
		h.jsonError(w, http.StatusInternalServerError, "server_error", "Ошибка записи глобальных настроек", nil)
		return
	}

	h.jsonSuccess(w, http.StatusOK, "Глобальные настройки сохранены", map[string]interface{}{
		"settings": body,
	}, nil)
}

func (h *RestHandler) HandleGetBlogViewSettings(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	target := h.storage.GetDataPath(activeBlog, "blog-view-settings.json")
	var settings map[string]interface{}
	_ = h.storage.SafeReadJSON(target, &settings)
	if settings == nil {
		settings = make(map[string]interface{})
	}
	h.jsonSuccess(w, http.StatusOK, "Настройки вида блога получены", map[string]interface{}{
		"settings": settings,
	}, nil)
}

func (h *RestHandler) HandleUpdateBlogViewSettings(w http.ResponseWriter, r *http.Request) {
	var data map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&data); err != nil {
		h.jsonError(w, http.StatusBadRequest, "invalid_json", "Некорректный JSON", nil)
		return
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
		target := h.storage.GetDataPath(blogDir, "blog-view-settings.json")
		var settings map[string]interface{}
		_ = h.storage.SafeReadJSON(target, &settings)
		if settings == nil {
			settings = make(map[string]interface{})
		}
		for k, v := range updates {
			settings[k] = v
		}
		if err := h.storage.SafeWriteJSON(target, settings); err != nil {
			return false
		}
		if newTitle, hasTitle := updates["title"].(string); hasTitle && newTitle != "" {
			blogHTML := h.storage.GetDataPath(blogDir, "blog.html")
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
			h.jsonError(w, http.StatusBadRequest, "validation_error", "Нет данных для сохранения", nil)
			return
		}
		updates := make(map[string]interface{})
		if layout != "" {
			updates["layout"] = layout
		}
		if title != "" && applyTitleAll {
			updates["title"] = title
		}

		blogPaths := h.cfg.GetSettings().BlogPaths
		if len(blogPaths) == 0 {
			dp := h.cfg.GetSettings().ActiveBlogPath
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

		h.jsonSuccess(w, http.StatusOK, "Настройки вида блога применены для всех блогов", map[string]interface{}{
			"updated_count": successCount,
			"details":       details,
		}, nil)
		return
	}

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
		h.jsonError(w, http.StatusBadRequest, "validation_error", "Нет данных для сохранения", nil)
		return
	}

	if !updateFile(activeBlog, updates) {
		h.jsonError(w, http.StatusInternalServerError, "server_error", "Не удалось сохранить настройки вида блога", nil)
		return
	}

	h.jsonSuccess(w, http.StatusOK, "Настройки вида блога сохранены", map[string]interface{}{
		"settings": updates,
	}, nil)
}

func (h *RestHandler) HandleGetBlogNav(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	blogHtmlPath := h.storage.GetDataPath(activeBlog, "blog.html")
	isStandard := false
	if bytes, err := os.ReadFile(blogHtmlPath); err == nil {
		content := string(bytes)
		isStandard = strings.Contains(content, "function loadBlogViewSettings()") && strings.Contains(content, "<header>")
	}

	navFile := h.storage.GetDataPath(activeBlog, "blog-view-settings.json")
	var settings map[string]interface{}
	_ = h.storage.SafeReadJSON(navFile, &settings)
	var buttons interface{} = []interface{}{}
	if settings != nil && settings["crossBlogNav"] != nil {
		buttons = settings["crossBlogNav"]
	}

	h.jsonSuccess(w, http.StatusOK, "Настройки навигации получены", map[string]interface{}{
		"is_standard": isStandard,
		"buttons":     buttons,
	}, nil)
}

func (h *RestHandler) HandleUpdateBlogNav(w http.ResponseWriter, r *http.Request) {
	activeBlog := middleware.GetActiveBlogPath(r)
	var body struct {
		Action   string                   `json:"action"`
		Buttons  []map[string]interface{} `json:"buttons"`
		ApplyAll bool                     `json:"apply_all"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	sanitized := make([]map[string]interface{}, 0, len(body.Buttons))
	for _, b := range body.Buttons {
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

	if body.Action == "apply_all" || body.ApplyAll {
		blogPaths := h.cfg.GetSettings().BlogPaths
		if len(blogPaths) == 0 {
			dp := h.cfg.GetSettings().ActiveBlogPath
			if dp == "" {
				dp = "data"
			}
			blogPaths = []string{dp}
		}
		successCount := 0
		for _, p := range blogPaths {
			t := h.storage.GetDataPath(p, "blog-view-settings.json")
			var s map[string]interface{}
			_ = h.storage.SafeReadJSON(t, &s)
			if s == nil {
				s = make(map[string]interface{})
			}
			s["crossBlogNav"] = sanitized
			if err := h.storage.SafeWriteJSON(t, s); err == nil {
				successCount++
			}
		}
		h.jsonSuccess(w, http.StatusOK, "Кнопки навигации применены для всех блогов", map[string]interface{}{
			"updated_count": successCount,
		}, nil)
		return
	}

	navFile := h.storage.GetDataPath(activeBlog, "blog-view-settings.json")
	var settings map[string]interface{}
	_ = h.storage.SafeReadJSON(navFile, &settings)
	if settings == nil {
		settings = make(map[string]interface{})
	}
	settings["crossBlogNav"] = sanitized
	_ = h.storage.SafeWriteJSON(navFile, settings)

	h.jsonSuccess(w, http.StatusOK, "Кнопки навигации сохранены", map[string]interface{}{
		"buttons": sanitized,
	}, nil)
}
