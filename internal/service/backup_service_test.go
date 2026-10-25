package service_test

import (
	"os"
	"path/filepath"
	"testing"

	"npblog/internal/config"
	"npblog/internal/domain"
	"npblog/internal/service"
)

func TestBackupService_GetBackupFileContent(t *testing.T) {
	tempDir, err := os.MkdirTemp("", "npblog_backup_test_*")
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
	backupSvc := service.NewBackupService(cfg, storage)

	// Создаём тестовую директорию бэкапов для статьи 1
	post1Dir := storage.GetBackupPath("1")
	if err := os.MkdirAll(post1Dir, 0755); err != nil {
		t.Fatalf("failed to create backup dir: %v", err)
	}

	testContent := "<h1>Тестовый бэкап статьи</h1>"
	backupFile := filepath.Join(post1Dir, "1-1.html")
	if err := os.WriteFile(backupFile, []byte(testContent), 0644); err != nil {
		t.Fatalf("failed to write backup file: %v", err)
	}

	// Сохраняем backup-meta.json
	meta := domain.BackupMeta{
		"1": domain.PostBackupInfo{
			PostID:    1,
			PostTitle: "Тест статьи",
			Backups: []domain.BackupEntry{
				{
					BackupNumber: 1,
					Filename:     "1-1.html",
					Date:         "25.10.2026 01:00",
					Title:        "Тест статьи",
				},
			},
		},
	}
	_ = storage.SafeWriteJSON(storage.GetBackupPath("backup-meta.json"), meta)

	// 1. Поиск только по filename и postId как строке ("1", "1-1.html", 0) - как отправляет браузер
	content, err := backupSvc.GetBackupFileContent("1", "1-1.html", 0)
	if err != nil {
		t.Fatalf("failed to get backup by postId and filename: %v", err)
	}
	if content != testContent {
		t.Errorf("content mismatch: got %q, want %q", content, testContent)
	}

	// 2. Поиск по backupNumber ("1", "", 1)
	content, err = backupSvc.GetBackupFileContent("1", "", 1)
	if err != nil {
		t.Fatalf("failed to get backup by postId and number: %v", err)
	}
	if content != testContent {
		t.Errorf("content mismatch: got %q, want %q", content, testContent)
	}

	// 3. Вызов устаревшего GetBackupContent(1, 1)
	content, err = backupSvc.GetBackupContent(1, 1)
	if err != nil {
		t.Fatalf("failed to get backup via legacy GetBackupContent: %v", err)
	}
	if content != testContent {
		t.Errorf("content mismatch: got %q, want %q", content, testContent)
	}

	// 4. Поиск только по filename без postId ("", "1-1.html", 0)
	content, err = backupSvc.GetBackupFileContent("", "1-1.html", 0)
	if err != nil {
		t.Fatalf("failed to get backup by filename only: %v", err)
	}
	if content != testContent {
		t.Errorf("content mismatch: got %q, want %q", content, testContent)
	}

	// 5. Несуществующий бэкап
	_, err = backupSvc.GetBackupFileContent("1", "1-999.html", 999)
	if err == nil {
		t.Errorf("expected error for non-existent backup, got nil")
	}
}
