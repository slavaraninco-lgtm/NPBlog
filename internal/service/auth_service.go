package service

import (
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"net"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"golang.org/x/crypto/bcrypt"
	"npblog/internal/config"
	"npblog/internal/domain"
)

type SessionData struct {
	Authenticated  bool
	AuthTime       int64
	CSRFToken      string
	ActiveBlogPath string
	EditorLanguage string
}

type AuthService struct {
	cfg     *config.AppConfig
	storage *StorageService

	sessionsMu sync.RWMutex
	sessions   map[string]*SessionData
}

func NewAuthService(cfg *config.AppConfig, storage *StorageService) *AuthService {
	return &AuthService{
		cfg:      cfg,
		storage:  storage,
		sessions: make(map[string]*SessionData),
	}
}

// GetClientIP извлекает реальный IP-адрес клиента с учетом прокси
func (a *AuthService) GetClientIP(r *http.Request) string {
	if xff := r.Header.Get("X-Forwarded-For"); xff != "" {
		parts := strings.Split(xff, ",")
		if len(parts) > 0 {
			ip := strings.TrimSpace(parts[0])
			if net.ParseIP(ip) != nil {
				return ip
			}
		}
	}
	if xrip := r.Header.Get("X-Real-IP"); xrip != "" {
		if net.ParseIP(xrip) != nil {
			return xrip
		}
	}
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err == nil && host != "" {
		return host
	}
	return r.RemoteAddr
}

// CheckIPWhitelist проверяет белый список IP-адресов
func (a *AuthService) CheckIPWhitelist(clientIP string) (bool, error) {
	settings := a.cfg.GetSettings()
	if !settings.IPWhitelistEnabled {
		return true, nil
	}

	ipsFile := filepath.Join(a.cfg.BaseDir, "allowed_ips.txt")
	content, err := os.ReadFile(ipsFile)
	if err != nil {
		return true, nil // Если файла нет, не блокируем
	}

	lines := strings.Split(string(content), "\n")
	for _, line := range lines {
		line = strings.TrimSpace(line)
		if idx := strings.Index(line, "#"); idx != -1 {
			line = strings.TrimSpace(line[:idx])
		}
		if line == "" {
			continue
		}
		if line == clientIP {
			return true, nil
		}
		// Проверка CIDR диапазонов (например, 192.168.1.0/24)
		if _, ipNet, err := net.ParseCIDR(line); err == nil {
			if parsedIP := net.ParseIP(clientIP); parsedIP != nil && ipNet.Contains(parsedIP) {
				return true, nil
			}
		}
	}

	return false, nil
}

// GetLockoutInfo возвращает состояние блокировки IP
func (a *AuthService) GetLockoutInfo(clientIP, activeBlogPath string) (bool, int64, int) {
	lockoutFile := a.storage.GetDataPath(activeBlogPath, "login_lockouts.json")
	var lockouts map[string]domain.LockoutInfo
	if err := a.storage.SafeReadJSON(lockoutFile, &lockouts); err != nil {
		return false, 0, 0
	}

	h := sha256.Sum256([]byte(clientIP))
	key := hex.EncodeToString(h[:])

	info, exists := lockouts[key]
	if !exists {
		return false, 0, 0
	}

	now := time.Now().Unix()
	if info.LockoutUntil > now {
		return true, info.LockoutUntil - now, info.Attempts
	}
	return false, 0, info.Attempts
}

// RecordFailedLogin фиксирует неудачную попытку входа
func (a *AuthService) RecordFailedLogin(clientIP, activeBlogPath string) (int, int64) {
	lockoutFile := a.storage.GetDataPath(activeBlogPath, "login_lockouts.json")
	var lockouts map[string]domain.LockoutInfo
	_ = a.storage.SafeReadJSON(lockoutFile, &lockouts)
	if lockouts == nil {
		lockouts = make(map[string]domain.LockoutInfo)
	}

	h := sha256.Sum256([]byte(clientIP))
	key := hex.EncodeToString(h[:])

	info := lockouts[key]
	info.Attempts++
	info.IP = clientIP

	var lockoutDuration int64 = 0
	if info.Attempts >= 3 {
		lockoutDuration = 15 * 60
		info.LockoutUntil = time.Now().Unix() + lockoutDuration
	}

	lockouts[key] = info
	_ = a.storage.SafeWriteJSON(lockoutFile, lockouts)

	return info.Attempts, lockoutDuration
}

// ResetLockout сбрасывает блокировку при успешном входе
func (a *AuthService) ResetLockout(clientIP, activeBlogPath string) {
	lockoutFile := a.storage.GetDataPath(activeBlogPath, "login_lockouts.json")
	var lockouts map[string]domain.LockoutInfo
	if err := a.storage.SafeReadJSON(lockoutFile, &lockouts); err == nil && lockouts != nil {
		h := sha256.Sum256([]byte(clientIP))
		key := hex.EncodeToString(h[:])
		if _, exists := lockouts[key]; exists {
			delete(lockouts, key)
			_ = a.storage.SafeWriteJSON(lockoutFile, lockouts)
		}
	}
}

// VerifyPassword проверяет пароль с хэшем из editor_settings.json
func (a *AuthService) VerifyPassword(password string) bool {
	settings := a.cfg.GetSettings()
	hash := settings.PasswordHash
	if hash == "" {
		return true
	}

	// Нормализация bcrypt префикса для совместимости PHP $2y$ -> Go $2a$
	normHash := hash
	if strings.HasPrefix(normHash, "$2y$") {
		normHash = "$2a$" + normHash[4:]
	}

	err := bcrypt.CompareHashAndPassword([]byte(normHash), []byte(password))
	return err == nil
}

// CreateSession создает новую сессию для пользователя
func (a *AuthService) CreateSession(w http.ResponseWriter) *SessionData {
	sessID := a.generateRandomToken(32)
	csrfToken := a.generateRandomToken(32)

	sess := &SessionData{
		Authenticated:  true,
		AuthTime:       time.Now().Unix(),
		CSRFToken:      csrfToken,
		ActiveBlogPath: a.cfg.GetSettings().ActiveBlogPath,
		EditorLanguage: a.cfg.GetSettings().Language,
	}

	a.sessionsMu.Lock()
	a.sessions[sessID] = sess
	a.sessionsMu.Unlock()

	http.SetCookie(w, &http.Cookie{
		Name:     "npblog_session",
		Value:    sessID,
		Path:     "/",
		MaxAge:   86400, // 24 часа
		HttpOnly: true,
		SameSite: http.SameSiteLaxMode,
	})

	return sess
}

// GetSession возвращает существующую сессию по Cookie
func (a *AuthService) GetSession(r *http.Request) *SessionData {
	cookie, err := r.Cookie("npblog_session")
	if err != nil || cookie.Value == "" {
		return nil
	}

	a.sessionsMu.RLock()
	sess, exists := a.sessions[cookie.Value]
	a.sessionsMu.RUnlock()

	if !exists {
		return nil
	}

	// Проверяем срок действия сессии (24 часа)
	if time.Now().Unix()-sess.AuthTime > 86400 {
		a.DestroySession(nil, cookie.Value)
		return nil
	}

	return sess
}

// DestroySession удаляет сессию
func (a *AuthService) DestroySession(w http.ResponseWriter, sessID string) {
	a.sessionsMu.Lock()
	delete(a.sessions, sessID)
	a.sessionsMu.Unlock()

	if w != nil {
		http.SetCookie(w, &http.Cookie{
			Name:     "npblog_session",
			Value:    "",
			Path:     "/",
			MaxAge:   -1,
			HttpOnly: true,
		})
	}
}

// VerifyBearerToken проверяет Bearer API токен из api_tokens.json
func (a *AuthService) VerifyBearerToken(token string) bool {
	if token == "" {
		return false
	}
	tokensFile := filepath.Join(a.cfg.BaseDir, "api_tokens.json")
	var tokens []domain.ApiToken
	if err := a.storage.SafeReadJSON(tokensFile, &tokens); err != nil {
		return false
	}

	now := time.Now().Unix()
	for i, t := range tokens {
		if t.Token == token {
			if t.ExpiresAt > 0 && t.ExpiresAt < now {
				return false
			}
			// Продлеваем срок активности
			tokens[i].LastUsedAt = now
			go func() {
				_ = a.storage.SafeWriteJSON(tokensFile, tokens)
			}()
			return true
		}
	}
	return false
}

// CreateApiToken создает новый токен для мобильного клиента
func (a *AuthService) CreateApiToken(deviceName string) (*domain.ApiToken, error) {
	tokensFile := filepath.Join(a.cfg.BaseDir, "api_tokens.json")
	var tokens []domain.ApiToken
	_ = a.storage.SafeReadJSON(tokensFile, &tokens)

	tokenVal := "npb_" + a.generateRandomToken(30)
	now := time.Now().Unix()
	newToken := domain.ApiToken{
		Token:      tokenVal,
		DeviceName: deviceName,
		CreatedAt:  now,
		LastUsedAt: now,
		ExpiresAt:  now + (30 * 86400), // 30 дней
	}

	tokens = append(tokens, newToken)
	if err := a.storage.SafeWriteJSON(tokensFile, tokens); err != nil {
		return nil, err
	}
	return &newToken, nil
}

func (a *AuthService) generateRandomToken(bytesLen int) string {
	b := make([]byte, bytesLen)
	_, _ = rand.Read(b)
	return hex.EncodeToString(b)
}
