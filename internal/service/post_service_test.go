package service_test

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"npblog/internal/config"
	"npblog/internal/domain"
	"npblog/internal/service"
)

func TestPostService_DeleteAndRenumber(t *testing.T) {
	tempDir, err := os.MkdirTemp("", "npblog_post_test_*")
	if err != nil {
		t.Fatalf("failed to create temp dir: %v", err)
	}
	defer os.RemoveAll(tempDir)

	cfg := &config.AppConfig{
		BaseDir:   tempDir,
		AssetsDir: tempDir,
	}
	cfg.ReloadSettings()

	storage := service.NewStorageService(cfg)
	postSvc := service.NewPostService(cfg, storage)

	blogDir := storage.GetDataPath("", "blog")
	if err := os.MkdirAll(blogDir, 0755); err != nil {
		t.Fatalf("failed to create blog dir: %v", err)
	}

	backupDir := storage.GetBackupPath("")
	if err := os.MkdirAll(backupDir, 0755); err != nil {
		t.Fatalf("failed to create backup dir: %v", err)
	}

	// Создаем 4 тестовых статьи: 1, 2, 3, 4
	var meta []domain.PostMeta
	backupMeta := domain.BackupMeta{}

	for i := 1; i <= 4; i++ {
		title := fmt.Sprintf("Статья номер %d", i)
		meta = append(meta, domain.PostMeta{
			ID:       i,
			Title:    title,
			Date:     "25.10.2026",
			Filename: fmt.Sprintf("post-%d.html", i),
		})

		postHtml := fmt.Sprintf(`<!DOCTYPE html><html><head><meta name="post-id" content="%d"><title>%s</title></head><body>Контент %d</body></html>`, i, title, i)
		_ = os.WriteFile(filepath.Join(blogDir, fmt.Sprintf("post-%d.html", i)), []byte(postHtml), 0644)

		postBDir := storage.GetBackupPath(fmt.Sprintf("%d", i))
		_ = os.MkdirAll(postBDir, 0755)
		_ = os.WriteFile(filepath.Join(postBDir, fmt.Sprintf("%d-1.html", i)), []byte(postHtml), 0644)

		backupMeta[fmt.Sprintf("%d", i)] = domain.PostBackupInfo{
			PostID:    i,
			PostTitle: title,
			Backups: []domain.BackupEntry{
				{
					BackupNumber: 1,
					Filename:     fmt.Sprintf("%d-1.html", i),
					Date:         "25.10.2026 12:00",
					Title:        title,
				},
			},
		}
	}

	_ = storage.SafeWriteJSON(filepath.Join(blogDir, "posts-meta.json"), meta)
	_ = storage.SafeWriteJSON(filepath.Join(backupDir, "backup-meta.json"), backupMeta)

	// Проверяем начальное состояние
	issues, err := postSvc.CheckNumbering("")
	if err != nil {
		t.Fatalf("CheckNumbering failed: %v", err)
	}
	if len(issues) > 0 {
		t.Fatalf("Expected 0 issues initially, got %d", len(issues))
	}

	// Удаляем первые 3 статьи по очереди: 1, 1, 1 (после удаления первой, статья 2 становится 1)
	// Или удалим 1, затем оставшуюся 1, затем еще раз 1
	renumbered, err := postSvc.DeletePost("", 1)
	if err != nil {
		t.Fatalf("DeletePost(1) failed: %v", err)
	}
	if !renumbered {
		t.Errorf("Expected renumbered=true on first delete")
	}

	// После удаления статьи 1, должны остаться 3 статьи с ID 1, 2, 3
	// Бывшая статья 4 теперь должна быть статьей 3
	var metaAfter1 []domain.PostMeta
	_ = storage.SafeReadJSON(filepath.Join(blogDir, "posts-meta.json"), &metaAfter1)
	if len(metaAfter1) != 3 {
		t.Fatalf("Expected 3 posts, got %d", len(metaAfter1))
	}
	if metaAfter1[2].Title != "Статья номер 4" || metaAfter1[2].ID != 3 {
		t.Errorf("Expected post 4 to become ID 3, got ID=%d, Title=%s", metaAfter1[2].ID, metaAfter1[2].Title)
	}

	// Удаляем еще две статьи
	_, err = postSvc.DeletePost("", 1)
	if err != nil {
		t.Fatalf("DeletePost(1) [second] failed: %v", err)
	}
	_, err = postSvc.DeletePost("", 1)
	if err != nil {
		t.Fatalf("DeletePost(1) [third] failed: %v", err)
	}

	// Теперь должна остаться только ОДНА статья, и она должна иметь ID=1 и название "Статья номер 4"
	var finalMeta []domain.PostMeta
	_ = storage.SafeReadJSON(filepath.Join(blogDir, "posts-meta.json"), &finalMeta)
	if len(finalMeta) != 1 {
		t.Fatalf("Expected 1 post, got %d", len(finalMeta))
	}
	if finalMeta[0].ID != 1 || finalMeta[0].Title != "Статья номер 4" {
		t.Errorf("Expected remaining post to be ID 1 'Статья номер 4', got ID=%d, Title=%s", finalMeta[0].ID, finalMeta[0].Title)
	}

	// Проверяем, что файл post-1.html существует и содержит <meta name="post-id" content="1">
	post1File := filepath.Join(blogDir, "post-1.html")
	contentBytes, err := os.ReadFile(post1File)
	if err != nil {
		t.Fatalf("post-1.html missing: %v", err)
	}
	if !strings.Contains(string(contentBytes), `content="1"`) {
		t.Errorf("post-1.html does not contain content=\"1\": %s", string(contentBytes))
	}

	// Проверяем бэкапы: папка 1 должна содержать 1-1.html
	backupFile1 := filepath.Join(backupDir, "1", "1-1.html")
	if _, err := os.Stat(backupFile1); err != nil {
		t.Errorf("backup 1/1-1.html should exist: %v", err)
	}

	// В backup-meta.json ключ "1" должен указывать на "Статья номер 4"
	var finalBackupMeta domain.BackupMeta
	_ = storage.SafeReadJSON(filepath.Join(backupDir, "backup-meta.json"), &finalBackupMeta)
	info, exists := finalBackupMeta["1"]
	if !exists {
		t.Errorf("finalBackupMeta missing key '1'")
	} else if info.PostTitle != "Статья номер 4" {
		t.Errorf("finalBackupMeta['1'] title mismatch: got %s", info.PostTitle)
	}

	// Проверяем, что архивированные копии удаленных статей сохранились
	hasDeletedBackups := false
	for k, v := range finalBackupMeta {
		if strings.HasPrefix(k, "deleted_") && v.Deleted {
			hasDeletedBackups = true
			break
		}
	}
	if !hasDeletedBackups {
		t.Errorf("expected archived deleted backups in backup-meta.json")
	}

	// Проверка отсутствия рассинхронизации нумерации
	issuesAfter, err := postSvc.CheckNumbering("")
	if err != nil {
		t.Fatalf("CheckNumbering after deletion failed: %v", err)
	}
	if len(issuesAfter) != 0 {
		t.Errorf("Expected 0 numbering issues, got %d: %+v", len(issuesAfter), issuesAfter)
	}
}
