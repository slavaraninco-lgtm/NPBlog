package main

import (
	"context"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/signal"
	"path/filepath"
	"strings"
	"syscall"
	"time"

	"github.com/go-chi/chi/v5"
	"npblog"
	"npblog/internal/assets"
	"npblog/internal/config"
	"npblog/internal/handler/legacy"
	"npblog/internal/handler/rest"
	"npblog/internal/handler/web"
	"npblog/internal/middleware"
	"npblog/internal/service"
)

func main() {
	cfg, err := config.LoadConfig()
	if err != nil {
		log.Fatalf("Ошибка загрузки конфигурации: %v", err)
	}

	hybridFS := assets.NewHybridFS(cfg.AssetsDir, npblog.EmbeddedAssets)

	storageService := service.NewStorageService(cfg)
	authService := service.NewAuthService(cfg, storageService)
	postService := service.NewPostService(cfg, storageService)
	draftService := service.NewDraftService(cfg, storageService)
	backupService := service.NewBackupService(cfg, storageService)
	mediaService := service.NewMediaService(cfg, storageService)
	templateService := service.NewTemplateService(cfg, storageService)
	integrityService := service.NewIntegrityService(cfg, storageService)
	ftpService := service.NewFtpService(cfg, storageService)
	formulaService := service.NewFormulaService(cfg)

	mw := middleware.NewMiddlewareManager(cfg, authService, storageService)

	editorHandler := web.NewEditorPageHandler(cfg, authService, storageService, hybridFS)
	legacyHandler := legacy.NewLegacyHandler(
		cfg, authService, storageService, postService, draftService, backupService,
		mediaService, templateService, integrityService, ftpService,
	)
	restHandler := rest.NewRestHandler(
		cfg, authService, storageService, postService, draftService, backupService,
		mediaService, templateService, integrityService, formulaService,
	)

	r := chi.NewRouter()

	r.Use(mw.Recovery)
	r.Use(mw.IPFilter)
	r.Use(mw.BlogContext)
	r.Use(mw.Auth)
	r.Use(mw.CSRF)

	// --- Главная страница редактора ---
	r.Get("/", editorHandler.ServeHTTP)
	r.Get("/index.php", editorHandler.ServeHTTP)

	// --- Двойная маршрутизация: Legacy *.php эндпоинты ---
	r.Post("/save_post.php", legacyHandler.HandleSavePost)
	r.Post("/update_post.php", legacyHandler.HandleUpdatePost)
	r.Post("/delete_post.php", legacyHandler.HandleDeletePost)
	r.Get("/get_post_content.php", legacyHandler.HandleGetPostContent)
	r.Post("/renumber_posts.php", legacyHandler.HandleRenumberPosts)
	r.Post("/toggle_pin_post.php", legacyHandler.HandleTogglePinPost)
	r.Post("/regenerate_posts.php", legacyHandler.HandleRegeneratePosts)

	r.Post("/login.php", legacyHandler.HandleLogin)
	r.Post("/logout.php", legacyHandler.HandleLogout)

	r.Post("/save_draft.php", legacyHandler.HandleSaveDraft)
	r.Get("/get_drafts.php", legacyHandler.HandleGetDrafts)
	r.Post("/delete_draft.php", legacyHandler.HandleDeleteDraft)

	r.Post("/save_autosave.php", legacyHandler.HandleSaveAutosave)
	r.Get("/get_autosave.php", legacyHandler.HandleGetAutosave)
	r.Get("/get_all_autosaves.php", legacyHandler.HandleGetAllAutosaves)
	r.Post("/delete_autosave.php", legacyHandler.HandleDeleteAutosave)
	r.Post("/delete_all_autosaves.php", legacyHandler.HandleDeleteAllAutosaves)

	r.Get("/get_backups.php", legacyHandler.HandleGetBackups)
	r.Get("/get_backup_content.php", legacyHandler.HandleGetBackupContent)
	r.Post("/get_backup_content.php", legacyHandler.HandleGetBackupContent)
	r.Post("/restore_backup.php", legacyHandler.HandleRestoreBackup)
	r.Post("/delete_backup.php", legacyHandler.HandleDeleteBackup)

	r.Post("/upload_image.php", legacyHandler.HandleUploadImage)
	r.Post("/upload_images_grid.php", legacyHandler.HandleUploadImagesGrid)
	r.Post("/save_edited_image.php", legacyHandler.HandleSaveEditedImage)

	r.Get("/serve_data.php", legacyHandler.HandleServeData)
	r.Get("/serve_data.php/*", legacyHandler.HandleServeData)

	r.Get("/get_editor_settings.php", legacyHandler.HandleGetEditorSettings)
	r.Post("/save_editor_settings.php", legacyHandler.HandleSaveEditorSettings)
	r.Post("/save_global_settings.php", legacyHandler.HandleSaveGlobalSettings)
	r.Post("/save_blog_view_settings.php", legacyHandler.HandleSaveBlogViewSettings)

	r.Get("/get_templates.php", legacyHandler.HandleGetTemplates)
	r.Post("/apply_template.php", legacyHandler.HandleApplyTemplate)
	r.Get("/get_includes.php", legacyHandler.HandleGetIncludes)
	r.Post("/save_include.php", legacyHandler.HandleSaveInclude)
	r.Get("/get_include_content.php", legacyHandler.HandleGetIncludeContent)
	r.Post("/delete_include.php", legacyHandler.HandleDeleteInclude)

	r.Post("/create_blog.php", legacyHandler.HandleCreateBlog)
	r.Get("/check_integrity.php", legacyHandler.HandleCheckIntegrity)
	r.Post("/fix_integrity.php", legacyHandler.HandleFixIntegrity)
	r.Get("/get_history.php", legacyHandler.HandleGetHistory)
	r.Post("/save_history.php", legacyHandler.HandleSaveHistory)
	r.Post("/clear_history.php", legacyHandler.HandleClearHistory)

	// Документы, Аудио, Видео
	r.Post("/upload_document.php", legacyHandler.HandleUploadDocument)
	r.Get("/get_documents.php", legacyHandler.HandleGetDocuments)
	r.Post("/delete_document.php", legacyHandler.HandleDeleteDocument)

	r.Post("/upload_audio.php", legacyHandler.HandleUploadAudio)
	r.Get("/get_audio_files.php", legacyHandler.HandleGetAudioFiles)
	r.Post("/delete_audio.php", legacyHandler.HandleDeleteAudio)

	r.Post("/upload_video.php", legacyHandler.HandleUploadVideo)
	r.Get("/get_video_files.php", legacyHandler.HandleGetVideoFiles)
	r.Post("/delete_video.php", legacyHandler.HandleDeleteVideo)

	// Шрифты и Смайлы
	r.Post("/upload_font.php", legacyHandler.HandleUploadFont)
	r.Get("/get_custom_fonts.php", legacyHandler.HandleGetCustomFonts)
	r.Get("/get_custom_fonts_css.php", legacyHandler.HandleGetCustomFontsCSS)

	r.Post("/upload_smiles.php", legacyHandler.HandleUploadSmiles)
	r.Get("/get_smiles.php", legacyHandler.HandleGetSmiles)
	r.Post("/delete_smile_set.php", legacyHandler.HandleDeleteSmileSet)

	// Фоны
	r.Post("/upload_background.php", legacyHandler.HandleUploadBackground)
	r.Post("/upload_blog_background.php", legacyHandler.HandleUploadBlogBackground)
	r.Post("/upload_global_background.php", legacyHandler.HandleUploadGlobalBackground)
	r.Get("/get_post_backgrounds.php", legacyHandler.HandleGetPostBackgrounds)
	r.Post("/update_post_background.php", legacyHandler.HandleUpdatePostBackground)
	r.Post("/remove_background.php", legacyHandler.HandleRemoveBackground)

	// Навигация, настройки и языки
	r.Get("/get_languages.php", legacyHandler.HandleGetLanguages)
	r.Post("/save_blog_nav.php", legacyHandler.HandleSaveBlogNav)
	r.Post("/save_overlay_settings.php", legacyHandler.HandleSaveOverlaySettings)

	// FTP Синхронизация
	r.Get("/ftp.php", legacyHandler.HandleFtp)
	r.Post("/ftp.php", legacyHandler.HandleFtp)
	r.Get("/ftp_upload_stream.php", legacyHandler.HandleFtpUploadStream)
	r.Post("/ftp_upload_stream.php", legacyHandler.HandleFtpUploadStream)

	// --- REST API v1 маршруты ---
	r.Route("/api/v1", func(api chi.Router) {
		api.Post("/auth/login", restHandler.HandleLogin)
		api.Post("/auth/logout", restHandler.HandleLogout)
		api.Get("/auth/me", restHandler.HandleMe)

		api.Get("/blogs", restHandler.HandleListBlogs)

		api.Get("/posts", restHandler.HandleListPosts)
		api.Post("/posts", restHandler.HandleCreatePost)
		api.Get("/posts/{id}", restHandler.HandleGetPost)
		api.Put("/posts/{id}", restHandler.HandleUpdatePost)
		api.Delete("/posts/{id}", restHandler.HandleDeletePost)

		api.Post("/formulas/render", restHandler.HandleRenderFormula)

		api.Get("/system/status", restHandler.HandleSystemStatus)
		api.Get("/system/integrity", restHandler.HandleSystemIntegrity)
	})

	// --- Отдача статических файлов редактора через HybridFS ---
	staticPrefixes := []string{"/editor-style.css", "/editor-img.js", "/assets", "/editorjs", "/modals", "/lang"}
	for _, prefix := range staticPrefixes {
		pfx := prefix
		r.Get(pfx, func(w http.ResponseWriter, req *http.Request) {
			if !hybridFS.ServeHTTP(w, req, req.URL.Path) {
				http.NotFound(w, req)
			}
		})
		r.Get(pfx+"/*", func(w http.ResponseWriter, req *http.Request) {
			if !hybridFS.ServeHTTP(w, req, req.URL.Path) {
				http.NotFound(w, req)
			}
		})
	}

	// --- Отдача статических файлов блога (data/...) ---
	r.Get("/data/*", func(w http.ResponseWriter, req *http.Request) {
		rel := strings.TrimPrefix(req.URL.Path, "/data/")
		safePath, err := storageService.ValidateSafePath(filepath.Join(cfg.BaseDir, "data"), rel)
		if err != nil {
			http.Error(w, "Forbidden", http.StatusForbidden)
			return
		}
		http.ServeFile(w, req, safePath)
	})

	serverAddr := fmt.Sprintf("%s:%s", cfg.Host, cfg.Port)
	srv := &http.Server{
		Addr:         serverAddr,
		Handler:      r,
		ReadTimeout:  30 * time.Second,
		WriteTimeout: 60 * time.Second,
		IdleTimeout:  120 * time.Second,
	}

	go func() {
		log.Printf("🚀 NPBlog Go Сервер запущен на http://%s", serverAddr)
		log.Printf("📁 Рабочая директория: %s", cfg.BaseDir)
		log.Printf("🎨 Каталог ресурсов (Hybrid): %s", cfg.AssetsDir)
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatalf("Критическая ошибка сервера: %v", err)
		}
	}()

	quit := make(chan os.Signal, 1)
	signal.Notify(quit, os.Interrupt, syscall.SIGTERM)
	<-quit

	log.Println("🛑 Остановка сервера NPBlog...")
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	if err := srv.Shutdown(ctx); err != nil {
		log.Fatalf("Ошибка при остановке сервера: %v", err)
	}
	log.Println("✅ Сервер успешно остановлен")
}
