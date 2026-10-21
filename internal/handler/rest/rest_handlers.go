package rest

import (
	"encoding/json"
	"net/http"
	"strconv"

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
