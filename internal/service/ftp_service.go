package service

import (
	"crypto/tls"
	"fmt"
	"os"
	"path/filepath"
	"time"

	"github.com/jlaffaye/ftp"
	"github.com/pkg/sftp"
	"golang.org/x/crypto/ssh"
	"npblog/internal/config"
)

type FtpCredentials struct {
	Server    string `json:"ftpServer"`
	Protocol  string `json:"ftpProtocol"` // ftp | ftps | sftp
	Port      int    `json:"ftpPort"`
	Username  string `json:"ftpUsername"`
	Password  string `json:"ftpPassword"`
	RemoteDir string `json:"ftpRemoteDir"`
	SavedAt   string `json:"saved_at,omitempty"`
}

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
	return &creds, nil
}

// SaveCredentials сохраняет параметры подключения в ftp.json
func (f *FtpService) SaveCredentials(creds *FtpCredentials) error {
	credsFile := filepath.Join(f.cfg.BaseDir, "ftp.json")
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
	addr := fmt.Sprintf("%s:%d", creds.Server, creds.Port)
	if creds.Port <= 0 {
		addr = fmt.Sprintf("%s:21", creds.Server)
	}

	var conn *ftp.ServerConn
	var err error

	if creds.Protocol == "ftps" {
		tlsConfig := &tls.Config{InsecureSkipVerify: true}
		conn, err = ftp.Dial(addr, ftp.DialWithTimeout(10*time.Second), ftp.DialWithTLS(tlsConfig))
	} else {
		conn, err = ftp.Dial(addr, ftp.DialWithTimeout(10*time.Second))
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
		Timeout:         10 * time.Second,
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
