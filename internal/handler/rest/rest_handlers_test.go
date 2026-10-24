package rest_test

import (
	"bytes"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"

	"github.com/go-chi/chi/v5"

	"npblog/internal/config"
	"npblog/internal/handler/rest"
	"npblog/internal/service"
)

func setupTestRouter(t *testing.T) (http.Handler, string) {
	tmpDir, err := os.MkdirTemp("", "npblog_rest_test_*")
	if err != nil {
		t.Fatalf("Failed to create temp dir: %v", err)
	}

	dataDir := filepath.Join(tmpDir, "data")
	_ = os.MkdirAll(dataDir, 0755)

	// Create mock blog.html
	blogHTML := `<!DOCTYPE html><html><head><title>Блог - Список статей</title></head><body><header><h1>Блог</h1></header></body></html>`
	_ = os.WriteFile(filepath.Join(dataDir, "blog.html"), []byte(blogHTML), 0644)

	// Create mock blog-view-settings.json
	initialSettings := `{"title":"Блог","layout":"list"}`
	_ = os.WriteFile(filepath.Join(dataDir, "blog-view-settings.json"), []byte(initialSettings), 0644)

	// Create mock editor_settings.json
	editorSettings := `{"blog_paths":["data"],"active_blog_path":"data"}`
	_ = os.WriteFile(filepath.Join(tmpDir, "editor_settings.json"), []byte(editorSettings), 0644)

	cfg := &config.AppConfig{
		BaseDir:   tmpDir,
		AssetsDir: tmpDir,
	}
	cfg.ReloadSettings()

	storage := service.NewStorageService(cfg)
	auth := service.NewAuthService(cfg, storage)
	post := service.NewPostService(cfg, storage)
	draft := service.NewDraftService(cfg, storage)
	backup := service.NewBackupService(cfg, storage)
	media := service.NewMediaService(cfg, storage)
	tmpl := service.NewTemplateService(cfg, storage)
	integrity := service.NewIntegrityService(cfg, storage)
	formula := service.NewFormulaService(cfg)
	system := service.NewSystemService(cfg, storage)

	restHandler := rest.NewRestHandler(cfg, auth, storage, post, draft, backup, media, tmpl, integrity, formula, system)

	r := chi.NewRouter()
	r.Route("/api/v1", func(api chi.Router) {
		api.Route("/autosaves", func(sub chi.Router) {
			sub.Get("/", restHandler.HandleListAutosaves)
			sub.Post("/", restHandler.HandleCreateAutosave)
			sub.Delete("/", restHandler.HandleDeleteAllAutosaves)
			sub.Get("/{id}", restHandler.HandleGetAutosave)
			sub.Delete("/{id}", restHandler.HandleDeleteAutosave)
		})

		api.Route("/drafts", func(sub chi.Router) {
			sub.Get("/", restHandler.HandleListDrafts)
			sub.Post("/", restHandler.HandleCreateDraft)
			sub.Delete("/{filename}", restHandler.HandleDeleteDraft)
		})

		api.Route("/includes", func(sub chi.Router) {
			sub.Get("/", restHandler.HandleListIncludes)
			sub.Post("/", restHandler.HandleCreateInclude)
			sub.Get("/{filename}", restHandler.HandleGetIncludeContent)
			sub.Delete("/{filename}", restHandler.HandleDeleteInclude)
		})

		api.Route("/settings", func(sub chi.Router) {
			sub.Get("/editor", restHandler.HandleGetEditorSettings)
			sub.Put("/editor", restHandler.HandleUpdateEditorSettings)
			sub.Get("/global", restHandler.HandleGetGlobalSettings)
			sub.Put("/global", restHandler.HandleUpdateGlobalSettings)
			sub.Get("/blog-view", restHandler.HandleGetBlogViewSettings)
			sub.Put("/blog-view", restHandler.HandleUpdateBlogViewSettings)
			sub.Get("/blog-nav", restHandler.HandleGetBlogNav)
			sub.Put("/blog-nav", restHandler.HandleUpdateBlogNav)
		})
	})

	return r, tmpDir
}

func TestRestAutosaves(t *testing.T) {
	router, tmpDir := setupTestRouter(t)
	defer os.RemoveAll(tmpDir)

	// 1. Create autosave via POST /api/v1/autosaves
	payload := map[string]interface{}{
		"title":   "Тестовая автосохраненная статья",
		"content": "<p>Контент автосохранения</p>",
	}
	body, _ := json.Marshal(payload)
	req := httptest.NewRequest(http.MethodPost, "/api/v1/autosaves", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	w := httptest.NewRecorder()
	router.ServeHTTP(w, req)

	if w.Code != http.StatusOK && w.Code != http.StatusCreated {
		t.Fatalf("Expected 200/201, got %d: %s", w.Code, w.Body.String())
	}

	var createResp struct {
		Success bool `json:"success"`
		Data    struct {
			ID string `json:"id"`
		} `json:"data"`
	}
	if err := json.Unmarshal(w.Body.Bytes(), &createResp); err != nil {
		t.Fatalf("Failed to parse JSON: %v", err)
	}
	if !createResp.Success || createResp.Data.ID == "" {
		t.Fatalf("Expected success with valid ID, got: %+v", createResp)
	}
	autosaveID := createResp.Data.ID

	// 2. List autosaves via GET /api/v1/autosaves
	reqList := httptest.NewRequest(http.MethodGet, "/api/v1/autosaves", nil)
	wList := httptest.NewRecorder()
	router.ServeHTTP(wList, reqList)

	if wList.Code != http.StatusOK {
		t.Fatalf("Expected 200 on list, got %d: %s", wList.Code, wList.Body.String())
	}

	var listResp struct {
		Success bool `json:"success"`
		Data    struct {
			Autosaves []map[string]interface{} `json:"autosaves"`
		} `json:"data"`
	}
	if err := json.Unmarshal(wList.Body.Bytes(), &listResp); err != nil {
		t.Fatalf("Failed to parse list JSON: %v", err)
	}
	if len(listResp.Data.Autosaves) != 1 {
		t.Fatalf("Expected 1 autosave, got %d", len(listResp.Data.Autosaves))
	}
	if listResp.Data.Autosaves[0]["title"] != "Тестовая автосохраненная статья" {
		t.Errorf("Unexpected title: %v", listResp.Data.Autosaves[0]["title"])
	}

	// 3. Get single autosave via GET /api/v1/autosaves/{id}
	reqGet := httptest.NewRequest(http.MethodGet, "/api/v1/autosaves/"+autosaveID, nil)
	wGet := httptest.NewRecorder()
	router.ServeHTTP(wGet, reqGet)

	if wGet.Code != http.StatusOK {
		t.Fatalf("Expected 200 on get, got %d: %s", wGet.Code, wGet.Body.String())
	}

	// 4. Delete single autosave via DELETE /api/v1/autosaves/{id}
	reqDel := httptest.NewRequest(http.MethodDelete, "/api/v1/autosaves/"+autosaveID, nil)
	wDel := httptest.NewRecorder()
	router.ServeHTTP(wDel, reqDel)

	if wDel.Code != http.StatusOK {
		t.Fatalf("Expected 200 on delete, got %d: %s", wDel.Code, wDel.Body.String())
	}

	// 5. Verify list is empty
	wList2 := httptest.NewRecorder()
	router.ServeHTTP(wList2, httptest.NewRequest(http.MethodGet, "/api/v1/autosaves", nil))
	var listResp2 struct {
		Success bool `json:"success"`
		Data    struct {
			Autosaves []map[string]interface{} `json:"autosaves"`
		} `json:"data"`
	}
	_ = json.Unmarshal(wList2.Body.Bytes(), &listResp2)
	if len(listResp2.Data.Autosaves) != 0 {
		t.Fatalf("Expected 0 autosaves, got %d", len(listResp2.Data.Autosaves))
	}
}

func TestRestDrafts(t *testing.T) {
	router, tmpDir := setupTestRouter(t)
	defer os.RemoveAll(tmpDir)

	// 1. Create a draft via POST /api/v1/drafts
	payload := map[string]interface{}{
		"title":   "Статья черновика",
		"content": "Содержимое черновика",
	}
	body, _ := json.Marshal(payload)
	req := httptest.NewRequest(http.MethodPost, "/api/v1/drafts", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	w := httptest.NewRecorder()
	router.ServeHTTP(w, req)

	if w.Code != http.StatusOK && w.Code != http.StatusCreated {
		t.Fatalf("Expected 200/201, got %d: %s", w.Code, w.Body.String())
	}

	// 2. List drafts via GET /api/v1/drafts
	reqList := httptest.NewRequest(http.MethodGet, "/api/v1/drafts", nil)
	wList := httptest.NewRecorder()
	router.ServeHTTP(wList, reqList)

	if wList.Code != http.StatusOK {
		t.Fatalf("Expected 200 on list drafts, got %d: %s", wList.Code, wList.Body.String())
	}

	var listResp struct {
		Success bool `json:"success"`
		Data    struct {
			Drafts []map[string]interface{} `json:"drafts"`
		} `json:"data"`
	}
	_ = json.Unmarshal(wList.Body.Bytes(), &listResp)
	if len(listResp.Data.Drafts) != 1 {
		t.Fatalf("Expected 1 draft, got %d", len(listResp.Data.Drafts))
	}

	filename, ok := listResp.Data.Drafts[0]["filename"].(string)
	if !ok || filename == "" {
		t.Fatalf("Expected draft filename, got %+v", listResp.Data.Drafts[0])
	}

	// 3. Delete draft via DELETE /api/v1/drafts/{filename}
	reqDel := httptest.NewRequest(http.MethodDelete, "/api/v1/drafts/"+filename, nil)
	wDel := httptest.NewRecorder()
	router.ServeHTTP(wDel, reqDel)

	if wDel.Code != http.StatusOK {
		t.Fatalf("Expected 200 on delete draft, got %d: %s", wDel.Code, wDel.Body.String())
	}
}

func TestRestBlogViewSettings(t *testing.T) {
	router, tmpDir := setupTestRouter(t)
	defer os.RemoveAll(tmpDir)

	// 1. Get current blog view settings
	reqGet := httptest.NewRequest(http.MethodGet, "/api/v1/settings/blog-view", nil)
	wGet := httptest.NewRecorder()
	router.ServeHTTP(wGet, reqGet)

	if wGet.Code != http.StatusOK {
		t.Fatalf("Expected 200, got %d: %s", wGet.Code, wGet.Body.String())
	}

	var getResp struct {
		Success bool `json:"success"`
		Data    struct {
			Settings map[string]interface{} `json:"settings"`
		} `json:"data"`
	}
	_ = json.Unmarshal(wGet.Body.Bytes(), &getResp)
	if getResp.Data.Settings["title"] != "Блог" {
		t.Errorf("Expected title 'Блог', got %v", getResp.Data.Settings["title"])
	}

	// 2. Update blog view settings (Title & Grid layout)
	updatePayload := map[string]interface{}{
		"action": "save",
		"title":  "Мой Классный Блог",
		"layout": "grid",
	}
	body, _ := json.Marshal(updatePayload)
	reqPut := httptest.NewRequest(http.MethodPut, "/api/v1/settings/blog-view", bytes.NewReader(body))
	reqPut.Header.Set("Content-Type", "application/json")
	wPut := httptest.NewRecorder()
	router.ServeHTTP(wPut, reqPut)

	if wPut.Code != http.StatusOK {
		t.Fatalf("Expected 200 on put, got %d: %s", wPut.Code, wPut.Body.String())
	}

	// 3. Verify file on disk
	content, err := os.ReadFile(filepath.Join(tmpDir, "data", "blog.html"))
	if err != nil {
		t.Fatalf("Failed to read blog.html: %v", err)
	}
	htmlStr := string(content)
	if !bytes.Contains([]byte(htmlStr), []byte("<title>Мой Классный Блог - Список статей</title>")) {
		t.Errorf("blog.html does not contain updated title: %s", htmlStr)
	}
	if !bytes.Contains([]byte(htmlStr), []byte("<h1>Мой Классный Блог</h1>")) {
		t.Errorf("blog.html does not contain updated h1: %s", htmlStr)
	}
}

func TestRestEditorSettings(t *testing.T) {
	router, tmpDir := setupTestRouter(t)
	defer os.RemoveAll(tmpDir)

	// 1. Get editor settings
	reqGet := httptest.NewRequest(http.MethodGet, "/api/v1/settings/editor", nil)
	wGet := httptest.NewRecorder()
	router.ServeHTTP(wGet, reqGet)

	if wGet.Code != http.StatusOK {
		t.Fatalf("Expected 200 on get editor settings, got %d: %s", wGet.Code, wGet.Body.String())
	}

	// 2. Update editor settings
	updatePayload := map[string]interface{}{
		"autosave_interval": 30,
		"theme":             "dark",
	}
	body, _ := json.Marshal(updatePayload)
	reqPut := httptest.NewRequest(http.MethodPut, "/api/v1/settings/editor", bytes.NewReader(body))
	reqPut.Header.Set("Content-Type", "application/json")
	wPut := httptest.NewRecorder()
	router.ServeHTTP(wPut, reqPut)

	if wPut.Code != http.StatusOK {
		t.Fatalf("Expected 200 on update editor settings, got %d: %s", wPut.Code, wPut.Body.String())
	}
}

func TestRestIncludes(t *testing.T) {
	router, tmpDir := setupTestRouter(t)
	defer os.RemoveAll(tmpDir)

	// 1. Create include
	createPayload := map[string]interface{}{
		"name":    "Мой Сниппет",
		"content": "<div>Сниппет контент</div>",
	}
	body, _ := json.Marshal(createPayload)
	reqPost := httptest.NewRequest(http.MethodPost, "/api/v1/includes", bytes.NewReader(body))
	reqPost.Header.Set("Content-Type", "application/json")
	wPost := httptest.NewRecorder()
	router.ServeHTTP(wPost, reqPost)

	if wPost.Code != http.StatusCreated && wPost.Code != http.StatusOK {
		t.Fatalf("Expected 201/200 on create include, got %d: %s", wPost.Code, wPost.Body.String())
	}

	var postResp struct {
		Success bool `json:"success"`
		Data    struct {
			Filename    string `json:"filename"`
			DisplayName string `json:"displayName"`
		} `json:"data"`
	}
	if err := json.Unmarshal(wPost.Body.Bytes(), &postResp); err != nil {
		t.Fatalf("Failed to parse create include response: %v", err)
	}
	filename := postResp.Data.Filename
	if filename == "" {
		t.Fatalf("Expected filename, got empty")
	}

	// 2. List includes
	reqList := httptest.NewRequest(http.MethodGet, "/api/v1/includes", nil)
	wList := httptest.NewRecorder()
	router.ServeHTTP(wList, reqList)

	if wList.Code != http.StatusOK {
		t.Fatalf("Expected 200 on list includes, got %d: %s", wList.Code, wList.Body.String())
	}

	var listResp struct {
		Success bool `json:"success"`
		Data    struct {
			Includes []map[string]interface{} `json:"includes"`
		} `json:"data"`
	}
	_ = json.Unmarshal(wList.Body.Bytes(), &listResp)
	if len(listResp.Data.Includes) != 1 {
		t.Fatalf("Expected 1 include, got %d", len(listResp.Data.Includes))
	}

	// 3. Get include content
	reqGet := httptest.NewRequest(http.MethodGet, "/api/v1/includes/"+filename, nil)
	wGet := httptest.NewRecorder()
	router.ServeHTTP(wGet, reqGet)

	if wGet.Code != http.StatusOK {
		t.Fatalf("Expected 200 on get include content, got %d: %s", wGet.Code, wGet.Body.String())
	}

	var getResp struct {
		Success bool `json:"success"`
		Data    struct {
			Content string `json:"content"`
		} `json:"data"`
	}
	_ = json.Unmarshal(wGet.Body.Bytes(), &getResp)
	if getResp.Data.Content != "<div>Сниппет контент</div>" {
		t.Errorf("Unexpected content: %s", getResp.Data.Content)
	}

	// 4. Delete include
	reqDel := httptest.NewRequest(http.MethodDelete, "/api/v1/includes/"+filename, nil)
	wDel := httptest.NewRecorder()
	router.ServeHTTP(wDel, reqDel)

	if wDel.Code != http.StatusOK {
		t.Fatalf("Expected 200 on delete include, got %d: %s", wDel.Code, wDel.Body.String())
	}
}
