package service_test

import (
	"context"
	"os"
	"path/filepath"
	"testing"

	"npblog/internal/config"
	"npblog/internal/service"
)

func TestFtpService_Credentials(t *testing.T) {
	tempDir, err := os.MkdirTemp("", "npblog_ftp_test_*")
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
	ftpSvc := service.NewFtpService(cfg, storage)

	// 1. Изначально файл отсутствует
	creds, err := ftpSvc.LoadCredentials()
	if err == nil && creds != nil {
		t.Fatalf("expected nil creds initially, got %+v", creds)
	}

	// 2. Сохраняем учетные данные
	saveCreds := &service.FtpCredentials{
		Server:       "ftp.example.com",
		Protocol:     "sftp",
		Port:         22,
		Username:     "testuser",
		Password:     "secret123", // должен быть очищен при сохранении
		RemoteDir:    "/var/www/blog",
		Ssl:          0,
		SkipExisting: 1,
	}

	if err := ftpSvc.SaveCredentials(saveCreds); err != nil {
		t.Fatalf("SaveCredentials failed: %v", err)
	}

	// Проверяем, что пароль не был записан на диск
	loaded, err := ftpSvc.LoadCredentials()
	if err != nil {
		t.Fatalf("LoadCredentials failed: %v", err)
	}
	if loaded.Server != "ftp.example.com" {
		t.Errorf("Server mismatch: expected ftp.example.com, got %s", loaded.Server)
	}
	if loaded.Protocol != "sftp" {
		t.Errorf("Protocol mismatch: expected sftp, got %s", loaded.Protocol)
	}
	if loaded.Port != 22 {
		t.Errorf("Port mismatch: expected 22, got %d", loaded.Port)
	}
	if loaded.Username != "testuser" {
		t.Errorf("Username mismatch: expected testuser, got %s", loaded.Username)
	}
	if loaded.Password != "" {
		t.Errorf("Password should be empty when loaded, got %s", loaded.Password)
	}
	if loaded.RemoteDir != "/var/www/blog" {
		t.Errorf("RemoteDir mismatch: expected /var/www/blog, got %s", loaded.RemoteDir)
	}
	if loaded.SavedAt == "" {
		t.Errorf("SavedAt should be set")
	}

	// 3. Сброс настроек
	if err := ftpSvc.ResetCredentials(); err != nil {
		t.Fatalf("ResetCredentials failed: %v", err)
	}
	credsAfterReset, err := ftpSvc.LoadCredentials()
	if err == nil && credsAfterReset != nil {
		t.Fatalf("expected nil creds after reset, got %+v", credsAfterReset)
	}
}

func TestFtpService_UploadStream_MissingDir(t *testing.T) {
	tempDir, err := os.MkdirTemp("", "npblog_ftp_stream_test_*")
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
	ftpSvc := service.NewFtpService(cfg, storage)

	var events []string
	callback := func(evtType string, data interface{}) {
		events = append(events, evtType)
	}

	creds := &service.FtpCredentials{
		Server:    "127.0.0.1",
		Protocol:  "ftp",
		Port:      21,
		Username:  "user",
		Password:  "pass",
		RemoteDir: "/remote",
	}

	// Попытка синхронизации несуществующей папки блога
	err = ftpSvc.UploadBlogStream(context.Background(), creds, "nonexistent_dir", callback)
	if err == nil {
		t.Fatalf("expected error for missing blog dir, got nil")
	}
	if len(events) == 0 || events[0] != "error" {
		t.Errorf("expected 'error' event to be emitted, got %+v", events)
	}
}

func TestFtpService_UploadStream_ConnectionFailure(t *testing.T) {
	tempDir, err := os.MkdirTemp("", "npblog_ftp_conn_test_*")
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
	ftpSvc := service.NewFtpService(cfg, storage)

	// Создаем тестовую папку блога с файлом
	blogDir := storage.GetDataPath("", "")
	_ = os.MkdirAll(blogDir, 0755)
	_ = os.WriteFile(filepath.Join(blogDir, "blog.html"), []byte("<h1>Blog</h1>"), 0644)

	var events []string
	callback := func(evtType string, data interface{}) {
		events = append(events, evtType)
	}

	// Недоступный порт
	creds := &service.FtpCredentials{
		Server:    "127.0.0.1",
		Protocol:  "ftp",
		Port:      65432,
		Username:  "user",
		Password:  "pass",
		RemoteDir: "/remote",
	}

	err = ftpSvc.UploadBlogStream(context.Background(), creds, "", callback)
	if err == nil {
		t.Fatalf("expected connection failure error, got nil")
	}

	// Должен быть лог о подключении и событие об ошибке
	hasLog := false
	hasError := false
	for _, ev := range events {
		if ev == "log" {
			hasLog = true
		}
		if ev == "error" {
			hasError = true
		}
	}

	if !hasLog {
		t.Errorf("expected 'log' event before connection")
	}
	if !hasError {
		t.Errorf("expected 'error' event on connection failure")
	}
}
