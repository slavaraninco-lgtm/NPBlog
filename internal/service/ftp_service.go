package service

import (
	"context"
	"crypto/tls"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"os"
	"path"
	"path/filepath"
	"strings"
	"time"

	"github.com/jlaffaye/ftp"
	"github.com/pkg/sftp"
	"golang.org/x/crypto/ssh"
	"npblog/internal/config"
)

type FtpCredentials struct {
	Server       string `json:"ftpServer"`
	Protocol     string `json:"ftpProtocol"` // ftp | ftps | sftp
	Port         int    `json:"ftpPort"`
	Username     string `json:"ftpUsername"`
	Password     string `json:"ftpPassword,omitempty"`
	RemoteDir    string `json:"ftpDirectory"`
	Ssl          int    `json:"ftpSsl,omitempty"`
	SkipExisting int    `json:"ftpSkipExisting,omitempty"`
	SavedAt      string `json:"saved_at,omitempty"`
}

func (c *FtpCredentials) UnmarshalJSON(data []byte) error {
	type Alias FtpCredentials
	aux := &struct {
		DirAlt string `json:"ftpRemoteDir"`
		*Alias
	}{
		Alias: (*Alias)(c),
	}
	if err := json.Unmarshal(data, &aux); err != nil {
		return err
	}
	if c.RemoteDir == "" && aux.DirAlt != "" {
		c.RemoteDir = aux.DirAlt
	}
	return nil
}

type FtpStreamCallback func(eventType string, data interface{})

type FtpService struct {
	cfg     *config.AppConfig
	storage *StorageService
}

func NewFtpService(cfg *config.AppConfig, storage *StorageService) *FtpService {
	return &FtpService{
		cfg:     cfg,
		storage: storage,
	}
}

// LoadCredentials загружает сохраненные параметры подключения из ftp.json
func (f *FtpService) LoadCredentials() (*FtpCredentials, error) {
	credsFile := filepath.Join(f.cfg.BaseDir, "ftp.json")
	var creds FtpCredentials
	if err := f.storage.SafeReadJSON(credsFile, &creds); err != nil {
		return nil, err
	}
	creds.Password = ""
	return &creds, nil
}

// SaveCredentials сохраняет параметры подключения в ftp.json
func (f *FtpService) SaveCredentials(creds *FtpCredentials) error {
	credsFile := filepath.Join(f.cfg.BaseDir, "ftp.json")
	creds.Password = ""
	creds.SavedAt = time.Now().Format("2006-01-02 15:04:05")
	return f.storage.SafeWriteJSON(credsFile, creds)
}

// ResetCredentials удаляет файл ftp.json
func (f *FtpService) ResetCredentials() error {
	credsFile := filepath.Join(f.cfg.BaseDir, "ftp.json")
	return os.Remove(credsFile)
}

// TestConnection проверяет соединение с удаленным сервером
func (f *FtpService) TestConnection(creds *FtpCredentials) error {
	if creds.Protocol == "sftp" {
		client, err := f.connectSFTP(creds)
		if err != nil {
			return err
		}
		defer client.Close()
		return nil
	}

	client, err := f.connectFTP(creds)
	if err != nil {
		return err
	}
	defer client.Quit()
	return nil
}

func (f *FtpService) connectFTP(creds *FtpCredentials) (*ftp.ServerConn, error) {
	port := creds.Port
	if port <= 0 {
		port = 21
	}
	addr := fmt.Sprintf("%s:%d", creds.Server, port)

	var conn *ftp.ServerConn
	var err error

	if creds.Protocol == "ftps" || creds.Ssl == 1 {
		tlsConfig := &tls.Config{InsecureSkipVerify: true}
		conn, err = ftp.Dial(addr, ftp.DialWithTimeout(15*time.Second), ftp.DialWithTLS(tlsConfig))
	} else {
		conn, err = ftp.Dial(addr, ftp.DialWithTimeout(15*time.Second))
	}

	if err != nil {
		return nil, fmt.Errorf("ошибка подключения к FTP: %w", err)
	}

	if err := conn.Login(creds.Username, creds.Password); err != nil {
		_ = conn.Quit()
		return nil, fmt.Errorf("ошибка авторизации FTP: %w", err)
	}

	return conn, nil
}

func (f *FtpService) connectSFTP(creds *FtpCredentials) (*sftp.Client, error) {
	port := creds.Port
	if port <= 0 {
		port = 22
	}

	sshConfig := &ssh.ClientConfig{
		User: creds.Username,
		Auth: []ssh.AuthMethod{
			ssh.Password(creds.Password),
		},
		HostKeyCallback: ssh.InsecureIgnoreHostKey(),
		Timeout:         15 * time.Second,
	}

	addr := fmt.Sprintf("%s:%d", creds.Server, port)
	conn, err := ssh.Dial("tcp", addr, sshConfig)
	if err != nil {
		return nil, fmt.Errorf("ошибка подключения к SSH: %w", err)
	}

	client, err := sftp.NewClient(conn)
	if err != nil {
		_ = conn.Close()
		return nil, fmt.Errorf("ошибка инициализации SFTP: %w", err)
	}

	return client, nil
}

func (f *FtpService) ftpMkdirAll(conn *ftp.ServerConn, remoteDir string) error {
	remoteDir = path.Clean(strings.ReplaceAll(remoteDir, "\\", "/"))
	if remoteDir == "/" || remoteDir == "." || remoteDir == "" {
		return nil
	}
	isAbs := strings.HasPrefix(remoteDir, "/")
	parts := strings.Split(strings.Trim(remoteDir, "/"), "/")
	cur := ""
	for _, part := range parts {
		if part == "" {
			continue
		}
		if cur == "" {
			if isAbs {
				cur = "/" + part
			} else {
				cur = part
			}
		} else {
			cur += "/" + part
		}
		_ = conn.MakeDir(cur)
	}
	return nil
}

type fileUploadItem struct {
	localPath  string
	relPath    string
	remotePath string
	remoteDir  string
	size       int64
}

// UploadBlogStream выполняет потоковую синхронизацию файлов блога по FTP/FTPS/SFTP с SSE-уведомлениями
func (f *FtpService) UploadBlogStream(ctx context.Context, creds *FtpCredentials, blogToUpload string, callback FtpStreamCallback) error {
	protocol := strings.ToLower(strings.TrimSpace(creds.Protocol))
	if protocol == "" {
		protocol = "ftp"
	}
	port := creds.Port
	if port <= 0 {
		if protocol == "sftp" {
			port = 22
		} else {
			port = 21
		}
	}
	creds.Protocol = protocol
	creds.Port = port

	localDataDir := f.storage.GetDataPath(blogToUpload, "")
	fi, err := os.Stat(localDataDir)
	if err != nil || !fi.IsDir() {
		msg := fmt.Sprintf("Папка блога не найдена: %s", filepath.Base(localDataDir))
		callback("error", map[string]interface{}{"message": msg})
		return errors.New(msg)
	}
	remoteFolderName := filepath.Base(localDataDir)

	remoteBase := "/" + strings.Trim(strings.TrimSpace(creds.RemoteDir), "/")
	if remoteBase == "/" {
		remoteBase = ""
	}
	remoteBlogDir := remoteBase + "/" + remoteFolderName

	var items []fileUploadItem
	var totalBytes int64

	walkErr := filepath.Walk(localDataDir, func(p string, info os.FileInfo, err error) error {
		if err != nil || info == nil {
			return nil
		}
		name := strings.ToLower(info.Name())
		if info.IsDir() {
			if name == ".git" || name == "node_modules" || name == ".svn" {
				return filepath.SkipDir
			}
			return nil
		}
		if name == ".ds_store" || name == "thumbs.db" || name == ".gitignore" {
			return nil
		}

		subRel, relErr := filepath.Rel(localDataDir, p)
		if relErr != nil {
			return nil
		}
		subSlash := filepath.ToSlash(subRel)
		relPath := remoteFolderName + "/" + subSlash
		remotePath := remoteBlogDir + "/" + subSlash
		remoteDir := path.Dir(remotePath)

		items = append(items, fileUploadItem{
			localPath:  p,
			relPath:    relPath,
			remotePath: remotePath,
			remoteDir:  remoteDir,
			size:       info.Size(),
		})
		totalBytes += info.Size()
		return nil
	})

	if walkErr != nil {
		callback("error", map[string]interface{}{"message": "Ошибка сканирования файлов блога: " + walkErr.Error()})
		return walkErr
	}

	totalFiles := len(items)
	if totalFiles == 0 {
		callback("error", map[string]interface{}{"message": "В выбранной папке блога нет файлов для загрузки"})
		return errors.New("no files to upload")
	}

	protoLabel := "FTP"
	if creds.Protocol == "sftp" {
		protoLabel = "SFTP"
	} else if creds.Ssl == 1 || creds.Protocol == "ftps" {
		protoLabel = "FTPS"
	}

	callback("log", map[string]interface{}{
		"message": fmt.Sprintf("Подключение к %s серверу %s:%d...", protoLabel, creds.Server, creds.Port),
		"level":   "info",
	})

	var sftpClient *sftp.Client
	var ftpConn *ftp.ServerConn

	if creds.Protocol == "sftp" {
		sftpClient, err = f.connectSFTP(creds)
		if err != nil {
			callback("error", map[string]interface{}{
				"message": "Не удалось подключиться к SFTP серверу: " + err.Error(),
			})
			return err
		}
		defer sftpClient.Close()
	} else {
		ftpConn, err = f.connectFTP(creds)
		if err != nil {
			callback("error", map[string]interface{}{
				"message": "Не удалось подключиться или авторизоваться на FTP сервере: " + err.Error(),
			})
			return err
		}
		defer ftpConn.Quit()
	}

	callback("log", map[string]interface{}{
		"message": fmt.Sprintf("Успешное подключение и авторизация на %s!", protoLabel),
		"level":   "success",
	})

	callback("progress", map[string]interface{}{
		"total":         totalFiles,
		"current":       0,
		"percent":       0,
		"currentFile":   "Подготовка к передаче...",
		"uploadedBytes": int64(0),
		"totalBytes":    totalBytes,
	})

	callback("log", map[string]interface{}{
		"message": fmt.Sprintf("Найдено файлов для анализа/загрузки: %d (%s)", totalFiles, formatBytes(totalBytes)),
		"level":   "info",
	})

	uploadedCount := 0
	failedCount := 0
	uploadedBytes := int64(0)
	createdDirs := make(map[string]bool)

	for _, item := range items {
		select {
		case <-ctx.Done():
			callback("log", map[string]interface{}{
				"message": "⚠️ Передача прервана пользователем",
				"level":   "warning",
			})
			return ctx.Err()
		default:
		}

		currentPercent := 0
		if totalFiles > 0 {
			currentPercent = int(float64(uploadedCount) / float64(totalFiles) * 100)
		}

		callback("file_start", map[string]interface{}{
			"total":         totalFiles,
			"current":       uploadedCount + 1,
			"percent":       currentPercent,
			"file":          item.relPath,
			"size":          item.size,
			"sizeFormatted": formatBytes(item.size),
		})

		// Умная синхронизация: пропускать файлы с совпадающим размером
		if creds.SkipExisting == 1 {
			matched := false
			if creds.Protocol == "sftp" {
				if rfi, statErr := sftpClient.Stat(item.remotePath); statErr == nil {
					if rfi.Size() == item.size {
						matched = true
					}
				}
			} else {
				if rSize, sizeErr := ftpConn.FileSize(item.remotePath); sizeErr == nil {
					if rSize == item.size {
						matched = true
					}
				}
			}

			if matched {
				uploadedCount++
				uploadedBytes += item.size
				pct := int(float64(uploadedCount) / float64(totalFiles) * 100)
				callback("progress", map[string]interface{}{
					"total":         totalFiles,
					"current":       uploadedCount,
					"percent":       pct,
					"currentFile":   item.relPath,
					"action":        "skip",
					"uploadedBytes": uploadedBytes,
					"totalBytes":    totalBytes,
				})
				callback("log", map[string]interface{}{
					"message": fmt.Sprintf("⚡ Пропущен (размер совпадает): %s", item.relPath),
					"level":   "info",
					"file":    item.relPath,
				})
				time.Sleep(10 * time.Millisecond)
				continue
			}
		}

		callback("log", map[string]interface{}{
			"message": fmt.Sprintf("Загрузка: %s (%s)", item.relPath, formatBytes(item.size)),
			"level":   "info",
			"file":    item.relPath,
		})

		if !createdDirs[item.remoteDir] {
			if creds.Protocol == "sftp" {
				_ = sftpClient.MkdirAll(item.remoteDir)
			} else {
				_ = f.ftpMkdirAll(ftpConn, item.remoteDir)
			}
			createdDirs[item.remoteDir] = true
		}

		var uploadErr error
		for attempt := 1; attempt <= 2; attempt++ {
			lf, openErr := os.Open(item.localPath)
			if openErr != nil {
				uploadErr = openErr
				break
			}

			if creds.Protocol == "sftp" {
				rf, createErr := sftpClient.Create(item.remotePath)
				if createErr != nil {
					uploadErr = createErr
				} else {
					_, uploadErr = io.Copy(rf, lf)
					_ = rf.Close()
				}
			} else {
				uploadErr = ftpConn.Stor(item.remotePath, lf)
			}
			_ = lf.Close()

			if uploadErr == nil {
				break
			}

			if attempt < 2 {
				callback("log", map[string]interface{}{
					"message": fmt.Sprintf("⚠️ Ошибка загрузки %s (%v). Повторная попытка...", item.relPath, uploadErr),
					"level":   "warning",
				})
				time.Sleep(300 * time.Millisecond)
			}
		}

		if uploadErr == nil {
			uploadedCount++
			uploadedBytes += item.size
			pct := int(float64(uploadedCount) / float64(totalFiles) * 100)
			callback("progress", map[string]interface{}{
				"total":         totalFiles,
				"current":       uploadedCount,
				"percent":       pct,
				"currentFile":   item.relPath,
				"action":        "upload",
				"uploadedBytes": uploadedBytes,
				"totalBytes":    totalBytes,
			})
			callback("log", map[string]interface{}{
				"message": fmt.Sprintf("✓ Загружен: %s", item.relPath),
				"level":   "success",
				"file":    item.relPath,
			})
		} else {
			failedCount++
			pct := int(float64(uploadedCount) / float64(totalFiles) * 100)
			callback("progress", map[string]interface{}{
				"total":         totalFiles,
				"current":       uploadedCount,
				"percent":       pct,
				"currentFile":   item.relPath,
				"action":        "error",
				"uploadedBytes": uploadedBytes,
				"totalBytes":    totalBytes,
			})
			callback("log", map[string]interface{}{
				"message": fmt.Sprintf("✗ Ошибка загрузки: %s (%v)", item.relPath, uploadErr),
				"level":   "error",
				"file":    item.relPath,
			})
		}

		time.Sleep(15 * time.Millisecond)
	}

	if failedCount == 0 {
		callback("complete", map[string]interface{}{
			"uploaded": uploadedCount,
			"failed":   failedCount,
			"total":    totalFiles,
			"message":  fmt.Sprintf("🎉 Загрузка успешно завершена! Обработано файлов: %d", uploadedCount),
		})
	} else {
		callback("complete", map[string]interface{}{
			"uploaded": uploadedCount,
			"failed":   failedCount,
			"total":    totalFiles,
			"message":  fmt.Sprintf("Завершено с ошибками. Загружено/пропущено: %d, ошибок: %d", uploadedCount, failedCount),
		})
	}

	return nil
}

func formatBytes(bytes int64) string {
	if bytes < 1024 {
		return fmt.Sprintf("%d B", bytes)
	}
	units := []string{"KB", "MB", "GB", "TB"}
	div := float64(1024)
	val := float64(bytes) / div
	uIdx := 0
	for val >= 1024 && uIdx < len(units)-1 {
		val /= 1024
		uIdx++
	}
	return fmt.Sprintf("%.1f %s", val, units[uIdx])
}
