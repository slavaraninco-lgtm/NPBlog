package service_test

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"npblog/internal/config"
	"npblog/internal/domain"
	"npblog/internal/service"
)

func setupTestPostService(t *testing.T) (*service.PostService, *service.StorageService, string, func()) {
	tmpDir, err := os.MkdirTemp("", "npblog_bg_test_*")
	if err != nil {
		t.Fatalf("Failed to create temp dir: %v", err)
	}

	cfg := &config.AppConfig{
		BaseDir: tmpDir,
	}
	cfg.ReloadSettings()

	storage := service.NewStorageService(cfg)
	postSvc := service.NewPostService(cfg, storage)

	// Create test directories
	blogDir := storage.GetDataPath("data", "blog")
	bgDir := storage.GetDataPath("data", "backgrounds")
	_ = os.MkdirAll(blogDir, 0755)
	_ = os.MkdirAll(bgDir, 0755)

	// Create mock template_post.html
	templateHTML := `<!DOCTYPE html>
<html>
<head><title>{{TITLE}}</title></head>
<body {{BODY_STYLE}}>
    <button class="theme-toggle" onclick="toggleTheme()">Тема</button>
    {{CONTENT_WRAPPER_START}}
    <h1>{{TITLE}}</h1>
    <div class="date">📅 {{DATE}}</div>
    <article id="npblog-post-content" class="content">
{{CONTENT}}
    </article>
    <a href="../blog.html" class="back-link">← Назад к списку статей</a>
    {{CONTENT_WRAPPER_END}}
    <div class="powered-by">Powered by NPBlog</div>
</body>
</html>`
	_ = os.WriteFile(filepath.Join(blogDir, "template_post.html"), []byte(templateHTML), 0644)

	cleanup := func() {
		_ = os.RemoveAll(tmpDir)
	}

	return postSvc, storage, tmpDir, cleanup
}

func TestApplyBackgroundToHtmlFile_ContentAndOverlay(t *testing.T) {
	postSvc, storage, _, cleanup := setupTestPostService(t)
	defer cleanup()

	// 1. Создаем тестовую статью
	postID, err := postSvc.SavePost("data", "Тестовая статья", "<p>Контент статьи</p>")
	if err != nil {
		t.Fatalf("SavePost failed: %v", err)
	}

	postFile := storage.GetDataPath("data", "blog/post-1.html")
	if _, err := os.Stat(postFile); os.IsNotExist(err) {
		t.Fatalf("post-1.html not created")
	}

	// 2. Применяем контентный фон
	bgSettings := &domain.PostBgSettings{
		Background:      "bg-1.png",
		BackgroundMode:  "cover",
		BackgroundScope: "content",
	}
	err = postSvc.ApplyBackgroundToHtmlFile(postFile, "/data/backgrounds/bg-1.png?v=123", bgSettings)
	if err != nil {
		t.Fatalf("ApplyBackgroundToHtmlFile failed: %v", err)
	}

	contentBytes, _ := os.ReadFile(postFile)
	content := string(contentBytes)

	if !strings.Contains(content, `class="content-wrapper"`) {
		t.Errorf("Expected content-wrapper in HTML, got:\n%s", content)
	}
	if !strings.Contains(content, `background-image: url('/data/backgrounds/bg-1.png?v=123')`) {
		t.Errorf("Expected background-image url in content-wrapper, got:\n%s", content)
	}
	if !strings.Contains(content, `<body style="max-width: 920px;">`) {
		t.Errorf("Expected max-width on body when content scope, got:\n%s", content)
	}

	// 3. Включаем подложку
	bgSettings.OverlayEnabled = true
	bgSettings.OverlayColor = "#112233"
	bgSettings.OverlayOpacity = 80

	err = postSvc.ApplyBackgroundToHtmlFile(postFile, "/data/backgrounds/bg-1.png?v=123", bgSettings)
	if err != nil {
		t.Fatalf("ApplyBackgroundToHtmlFile with overlay failed: %v", err)
	}

	contentBytes, _ = os.ReadFile(postFile)
	content = string(contentBytes)

	if !strings.Contains(content, `class="content-wrapper"`) {
		t.Errorf("Expected content-wrapper preserved, got:\n%s", content)
	}
	if !strings.Contains(content, `class="overlay-wrapper"`) {
		t.Errorf("Expected overlay-wrapper inside content, got:\n%s", content)
	}
	if !strings.Contains(content, `background: rgba(17, 34, 51, 0.80)`) {
		t.Errorf("Expected rgba overlay color, got:\n%s", content)
	}

	// 4. Удаляем фон, но оставляем подложку
	overlayOnly := &domain.PostBgSettings{
		OverlayEnabled: true,
		OverlayColor:   "#112233",
		OverlayOpacity: 80,
	}
	err = postSvc.ApplyBackgroundToHtmlFile(postFile, "", overlayOnly)
	if err != nil {
		t.Fatalf("ApplyBackgroundToHtmlFile overlay only failed: %v", err)
	}

	contentBytes, _ = os.ReadFile(postFile)
	content = string(contentBytes)

	if strings.Contains(content, `class="content-wrapper"`) {
		t.Errorf("content-wrapper should be removed when background is unset, got:\n%s", content)
	}
	if !strings.Contains(content, `class="overlay-wrapper"`) {
		t.Errorf("overlay-wrapper should remain, got:\n%s", content)
	}

	// 5. Удаляем всё (nil)
	err = postSvc.ApplyBackgroundToHtmlFile(postFile, "", nil)
	if err != nil {
		t.Fatalf("ApplyBackgroundToHtmlFile clean failed: %v", err)
	}

	contentBytes, _ = os.ReadFile(postFile)
	content = string(contentBytes)

	if strings.Contains(content, `class="content-wrapper"`) || strings.Contains(content, `class="overlay-wrapper"`) {
		t.Errorf("All wrappers should be removed, got:\n%s", content)
	}
	if !strings.Contains(content, `<body style="max-width: 920px;">`) {
		t.Errorf("Body style should be reset to default max-width, got:\n%s", content)
	}

	_ = postID
}

func TestApplyBackgroundToHtmlFile_Fullpage(t *testing.T) {
	postSvc, storage, _, cleanup := setupTestPostService(t)
	defer cleanup()

	_, err := postSvc.SavePost("data", "Статья fullpage", "<p>Контент</p>")
	if err != nil {
		t.Fatalf("SavePost failed: %v", err)
	}

	postFile := storage.GetDataPath("data", "blog/post-1.html")

	bgSettings := &domain.PostBgSettings{
		Background:      "bg-1.jpg",
		BackgroundMode:  "cover",
		BackgroundScope: "fullpage",
	}

	err = postSvc.ApplyBackgroundToHtmlFile(postFile, "/data/backgrounds/bg-1.jpg", bgSettings)
	if err != nil {
		t.Fatalf("ApplyBackgroundToHtmlFile failed: %v", err)
	}

	contentBytes, _ := os.ReadFile(postFile)
	content := string(contentBytes)

	if strings.Contains(content, `class="content-wrapper"`) {
		t.Errorf("Fullpage should not have content-wrapper, got:\n%s", content)
	}
	if !strings.Contains(content, `<body style="background-image: url('/data/backgrounds/bg-1.jpg')`) {
		t.Errorf("Expected background on body tag, got:\n%s", content)
	}
	if !strings.Contains(content, `background-attachment: fixed;`) {
		t.Errorf("Expected background-attachment fixed on body, got:\n%s", content)
	}
}

func TestUploadAndRemoveBackground_Cascade(t *testing.T) {
	postSvc, storage, _, cleanup := setupTestPostService(t)
	defer cleanup()

	// 1. Создаем статью 1
	postID, err := postSvc.SavePost("data", "Первая статья", "<p>Текст</p>")
	if err != nil {
		t.Fatalf("SavePost failed: %v", err)
	}

	// 2. Загружаем глобальный фон
	globalData := []byte("fake_image_global")
	_, err = postSvc.UploadGlobalBackground("data", "global.png", globalData, "cover", "content")
	if err != nil {
		t.Fatalf("UploadGlobalBackground failed: %v", err)
	}

	postFile := storage.GetDataPath("data", "blog/post-1.html")
	contentBytes, _ := os.ReadFile(postFile)
	content := string(contentBytes)

	if !strings.Contains(content, "global-bg.png") {
		t.Errorf("Post 1 should receive global background, got:\n%s", content)
	}

	// 3. Загружаем индивидуальный фон для статьи 1
	postData := []byte("fake_image_post")
	uploadedFilename, err := postSvc.UploadPostBackground("data", postID, "myphoto.jpg", postData, "contain", "content")
	if err != nil {
		t.Fatalf("UploadPostBackground failed: %v", err)
	}

	if uploadedFilename != "bg-1.jpg" {
		t.Errorf("Expected filename bg-1.jpg, got %s", uploadedFilename)
	}

	contentBytes, _ = os.ReadFile(postFile)
	content = string(contentBytes)

	if !strings.Contains(content, "bg-1.jpg") {
		t.Errorf("Post 1 should have individual bg-1.jpg, got:\n%s", content)
	}

	// 4. Удаляем индивидуальный фон - должен вернуться глобальный фон
	globalApplied, err := postSvc.RemovePostBackground("data", postID)
	if err != nil {
		t.Fatalf("RemovePostBackground failed: %v", err)
	}
	if !globalApplied {
		t.Errorf("Expected globalApplied to be true")
	}

	contentBytes, _ = os.ReadFile(postFile)
	content = string(contentBytes)

	if !strings.Contains(content, "global-bg.png") {
		t.Errorf("Post 1 should fall back to global-bg.png after individual bg removed, got:\n%s", content)
	}

	// 5. Удаляем глобальный фон - статья должна остаться без фона
	err = postSvc.RemoveGlobalBackground("data")
	if err != nil {
		t.Fatalf("RemoveGlobalBackground failed: %v", err)
	}

	contentBytes, _ = os.ReadFile(postFile)
	content = string(contentBytes)

	if strings.Contains(content, `class="content-wrapper"`) || strings.Contains(content, "global-bg.png") {
		t.Errorf("Post 1 should have no background wrapper after global bg removed, got:\n%s", content)
	}
}
