package service

import (
	"crypto/rand"
	"crypto/sha256"
	"crypto/subtle"
	"encoding/hex"
	"fmt"
	"net"
	"net/http"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"sync"
	"time"

	"golang.org/x/crypto/bcrypt"
	"npblog/internal/config"
	"npblog/internal/domain"
)

type SessionData struct {
	ID             string `json:"id"`
	Authenticated  bool   `json:"authenticated"`
	AuthTime       int64  `json:"auth_time"`
	CSRFToken      string `json:"csrf_token"`
	ActiveBlogPath string `json:"active_blog_path"`
	EditorLanguage string `json:"editor_language"`
	IP             string `json:"ip"`
	UserAgent      string `json:"user_agent"`
	Device         string `json:"device"`
	Browser        string `json:"browser"`
	CreatedAt      int64  `json:"created_at"`
	LastActivity   int64  `json:"last_activity"`
}

type AuthService struct {
	cfg     *config.AppConfig
	storage *StorageService

	sessionsMu sync.RWMutex
	sessions   map[string]*SessionData
}

func NewAuthService(cfg *config.AppConfig, storage *StorageService) *AuthService {
	a := &AuthService{
		cfg:      cfg,
		storage:  storage,
		sessions: make(map[string]*SessionData),
	}
	a.loadSessionsFromDisk()
	return a
}

// GetClientIP извлекает реальный IP-адрес клиента с защитой от спуфинга заголовков
func (a *AuthService) GetClientIP(r *http.Request) string {
	remoteIP, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil || remoteIP == "" {
		remoteIP = r.RemoteAddr
	}

	// Доверяем X-Forwarded-For и X-Real-IP только если непосредственный пир является локальным (обратный прокси Nginx/Apache)
	parsedRemote := net.ParseIP(remoteIP)
	if parsedRemote != nil && parsedRemote.IsLoopback() {
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
			ip := strings.TrimSpace(xrip)
			if net.ParseIP(ip) != nil {
				return ip
			}
		}
	}

	return remoteIP
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

// AddAllowedIP добавляет IP в allowed_ips.txt, если его там ещё нет
func (a *AuthService) AddAllowedIP(clientIP string) error {
	clientIP = strings.TrimSpace(clientIP)
	if clientIP == "" {
		return nil
	}

	ipsFile := filepath.Join(a.cfg.BaseDir, "allowed_ips.txt")
	content, err := os.ReadFile(ipsFile)
	if err == nil {
		lines := strings.Split(string(content), "\n")
		for _, line := range lines {
			line = strings.TrimSpace(line)
			if idx := strings.Index(line, "#"); idx != -1 {
				line = strings.TrimSpace(line[:idx])
			}
			if line == clientIP {
				return nil // Уже в списке
			}
		}
	}

	newEntry := ""
	if len(content) > 0 && !strings.HasSuffix(string(content), "\n") {
		newEntry = "\n"
	}
	newEntry += fmt.Sprintf("%s # Auto-added on enable\n", clientIP)

	f, err := os.OpenFile(ipsFile, os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0644)
	if err != nil {
		return err
	}
	defer f.Close()

	_, err = f.WriteString(newEntry)
	return err
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
	return a.VerifyHash(settings.PasswordHash, password)
}

// VerifyHash проверяет пароль с заданным bcrypt хэшем
func (a *AuthService) VerifyHash(hash, password string) bool {
	if hash == "" {
		return true
	}

	normHash := hash
	if strings.HasPrefix(normHash, "$2y$") {
		normHash = "$2a$" + normHash[4:]
	}

	err := bcrypt.CompareHashAndPassword([]byte(normHash), []byte(password))
	return err == nil
}

// CreateSession создает новую сессию для пользователя
func (a *AuthService) CreateSession(w http.ResponseWriter, r *http.Request) *SessionData {
	sessID := a.generateRandomToken(32)
	csrfToken := a.generateRandomToken(32)

	now := time.Now().Unix()
	var clientIP, userAgent, device, browser string
	if r != nil {
		clientIP = a.GetClientIP(r)
		userAgent = r.UserAgent()
		device, browser = parseUserAgent(userAgent)
	}

	sess := &SessionData{
		ID:             sessID,
		Authenticated:  true,
		AuthTime:       now,
		CreatedAt:      now,
		LastActivity:   now,
		CSRFToken:      csrfToken,
		ActiveBlogPath: a.cfg.GetSettings().ActiveBlogPath,
		EditorLanguage: a.cfg.GetSettings().Language,
		IP:             clientIP,
		UserAgent:      userAgent,
		Device:         device,
		Browser:        browser,
	}

	a.sessionsMu.Lock()
	a.sessions[sessID] = sess
	a.sessionsMu.Unlock()

	go a.saveSessionsToDisk()

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

	a.sessionsMu.Lock()
	sess, exists := a.sessions[cookie.Value]
	if !exists {
		a.sessionsMu.Unlock()
		return nil
	}

	now := time.Now().Unix()
	// Проверяем срок действия сессии (24 часа)
	if now-sess.AuthTime > 86400 {
		delete(a.sessions, cookie.Value)
		a.sessionsMu.Unlock()
		go a.saveSessionsToDisk()
		return nil
	}

	var shouldSave bool
	// Обновляем время последней активности (с задержкой записи на диск)
	if now-sess.LastActivity >= 60 {
		sess.LastActivity = now
		if sess.IP == "" && r != nil {
			sess.IP = a.GetClientIP(r)
		}
		if sess.UserAgent == "" && r != nil {
			sess.UserAgent = r.UserAgent()
			sess.Device, sess.Browser = parseUserAgent(sess.UserAgent)
		}
		shouldSave = true
	} else {
		sess.LastActivity = now
	}
	a.sessionsMu.Unlock()

	if shouldSave {
		go a.saveSessionsToDisk()
	}

	return sess
}

// DestroySession удаляет сессию
func (a *AuthService) DestroySession(w http.ResponseWriter, sessID string) {
	a.sessionsMu.Lock()
	delete(a.sessions, sessID)
	a.sessionsMu.Unlock()

	go a.saveSessionsToDisk()

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

// GetActiveSessions возвращает список всех активных сессий с пометкой текущей
func (a *AuthService) GetActiveSessions(currentCookie string) []domain.SessionItem {
	a.sessionsMu.RLock()
	defer a.sessionsMu.RUnlock()

	now := time.Now().Unix()
	var items []domain.SessionItem
	for id, s := range a.sessions {
		if s == nil || now-s.AuthTime > 86400 {
			continue
		}
		isCurrent := (id == currentCookie)
		items = append(items, domain.SessionItem{
			ID:           id,
			IP:           s.IP,
			UserAgent:    s.UserAgent,
			Device:       s.Device,
			Browser:      s.Browser,
			CreatedAt:    s.CreatedAt,
			LastActivity: s.LastActivity,
			IsCurrent:    isCurrent,
		})
	}

	sort.Slice(items, func(i, j int) bool {
		if items[i].IsCurrent != items[j].IsCurrent {
			return items[i].IsCurrent // текущая сессия всегда сверху
		}
		return items[i].LastActivity > items[j].LastActivity
	})

	return items
}

// TerminateSession завершает сессию по ID
func (a *AuthService) TerminateSession(targetID string) bool {
	a.sessionsMu.Lock()
	_, exists := a.sessions[targetID]
	if exists {
		delete(a.sessions, targetID)
	}
	a.sessionsMu.Unlock()

	if exists {
		go a.saveSessionsToDisk()
	}
	return exists
}

// TerminateOtherSessions завершает все остальные сессии кроме текущей
func (a *AuthService) TerminateOtherSessions(currentCookie string) int {
	a.sessionsMu.Lock()
	count := 0
	for id := range a.sessions {
		if id != currentCookie {
			delete(a.sessions, id)
			count++
		}
	}
	a.sessionsMu.Unlock()

	if count > 0 {
		go a.saveSessionsToDisk()
	}
	return count
}

func (a *AuthService) loadSessionsFromDisk() {
	sessFile := filepath.Join(a.cfg.BaseDir, "data", "sessions.json")
	var loaded map[string]*SessionData
	if err := a.storage.SafeReadJSON(sessFile, &loaded); err != nil || loaded == nil {
		return
	}

	now := time.Now().Unix()
	a.sessionsMu.Lock()
	defer a.sessionsMu.Unlock()
	for k, v := range loaded {
		if v != nil && (now-v.AuthTime <= 86400) {
			if v.ID == "" {
				v.ID = k
			}
			if v.CreatedAt == 0 {
				v.CreatedAt = v.AuthTime
			}
			if v.LastActivity == 0 {
				v.LastActivity = v.AuthTime
			}
			if v.Device == "" || v.Browser == "" {
				v.Device, v.Browser = parseUserAgent(v.UserAgent)
			}
			a.sessions[k] = v
		}
	}
}

func (a *AuthService) saveSessionsToDisk() {
	a.sessionsMu.RLock()
	copyMap := make(map[string]*SessionData, len(a.sessions))
	now := time.Now().Unix()
	for k, v := range a.sessions {
		if v != nil && (now-v.AuthTime <= 86400) {
			copyMap[k] = v
		}
	}
	a.sessionsMu.RUnlock()

	sessFile := filepath.Join(a.cfg.BaseDir, "data", "sessions.json")
	_ = a.storage.SafeWriteJSON(sessFile, copyMap)
}

func parseUserAgent(ua string) (string, string) {
	if strings.TrimSpace(ua) == "" {
		return "Компьютер", "Браузер"
	}

	device := "Компьютер"
	switch {
	case strings.Contains(ua, "Windows NT 10.0") || strings.Contains(ua, "Windows NT 11.0"):
		device = "Windows"
	case strings.Contains(ua, "Windows"):
		device = "Windows"
	case strings.Contains(ua, "iPhone"):
		device = "iPhone"
	case strings.Contains(ua, "iPad"):
		device = "iPad"
	case strings.Contains(ua, "Macintosh") || strings.Contains(ua, "Mac OS X"):
		device = "macOS"
	case strings.Contains(ua, "Android"):
		device = "Android"
	case strings.Contains(ua, "Linux"):
		device = "Linux"
	}

	browser := "Браузер"
	switch {
	case strings.Contains(ua, "YaBrowser/"):
		browser = "Яндекс Браузер"
		if v := extractBrowserVersion(ua, "YaBrowser/"); v != "" {
			browser += " " + v
		}
	case strings.Contains(ua, "Edg/"):
		browser = "Microsoft Edge"
		if v := extractBrowserVersion(ua, "Edg/"); v != "" {
			browser += " " + v
		}
	case strings.Contains(ua, "OPR/") || strings.Contains(ua, "Opera/"):
		browser = "Opera"
		prefix := "OPR/"
		if strings.Contains(ua, "Opera/") {
			prefix = "Opera/"
		}
		if v := extractBrowserVersion(ua, prefix); v != "" {
			browser += " " + v
		}
	case strings.Contains(ua, "Firefox/"):
		browser = "Firefox"
		if v := extractBrowserVersion(ua, "Firefox/"); v != "" {
			browser += " " + v
		}
	case strings.Contains(ua, "Chrome/") || strings.Contains(ua, "CriOS/"):
		browser = "Chrome"
		prefix := "Chrome/"
		if strings.Contains(ua, "CriOS/") {
			prefix = "CriOS/"
		}
		if v := extractBrowserVersion(ua, prefix); v != "" {
			browser += " " + v
		}
	case strings.Contains(ua, "Safari/"):
		browser = "Safari"
		if strings.Contains(ua, "Version/") {
			if v := extractBrowserVersion(ua, "Version/"); v != "" {
				browser += " " + v
			}
		}
	}

	return device, browser
}

func extractBrowserVersion(ua, prefix string) string {
	idx := strings.Index(ua, prefix)
	if idx == -1 {
		return ""
	}
	sub := ua[idx+len(prefix):]
	end := strings.IndexAny(sub, " .;/()")
	if end != -1 {
		return sub[:end]
	}
	if len(sub) > 4 {
		return sub[:4]
	}
	return sub
}

// VerifyBearerToken проверяет Bearer API токен из api_tokens.json по криптографическому хэшу
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
	h := sha256.Sum256([]byte(token))
	tokenHash := hex.EncodeToString(h[:])

	needsMigration := false
	for i, t := range tokens {
		matched := false
		// 1. Проверка по SHA-256 хэшу токена
		if subtle.ConstantTimeCompare([]byte(t.Token), []byte(tokenHash)) == 1 {
			matched = true
		} else if subtle.ConstantTimeCompare([]byte(t.Token), []byte(token)) == 1 {
			// 2. Обратная совместимость: миграция ранее сохраненного открытого токена в хэш
			matched = true
			tokens[i].Token = tokenHash
			needsMigration = true
		}

		if matched {
			if t.ExpiresAt > 0 && t.ExpiresAt < now {
				return false
			}
			tokens[i].LastUsedAt = now
			if needsMigration {
				_ = a.storage.SafeWriteJSON(tokensFile, tokens)
			} else {
				go func() {
					_ = a.storage.SafeWriteJSON(tokensFile, tokens)
				}()
			}
			return true
		}
	}
	return false
}

// CreateApiToken создает новый токен для мобильного клиента и сохраняет его SHA-256 хэш
func (a *AuthService) CreateApiToken(deviceName string) (*domain.ApiToken, error) {
	tokensFile := filepath.Join(a.cfg.BaseDir, "api_tokens.json")
	var tokens []domain.ApiToken
	_ = a.storage.SafeReadJSON(tokensFile, &tokens)

	tokenVal := "npb_" + a.generateRandomToken(30)
	now := time.Now().Unix()

	h := sha256.Sum256([]byte(tokenVal))
	tokenHash := hex.EncodeToString(h[:])

	storedToken := domain.ApiToken{
		Token:      tokenHash,
		DeviceName: deviceName,
		CreatedAt:  now,
		LastUsedAt: now,
		ExpiresAt:  now + (30 * 86400), // 30 дней
	}

	tokens = append(tokens, storedToken)
	if err := a.storage.SafeWriteJSON(tokensFile, tokens); err != nil {
		return nil, err
	}

	// Возвращаем клиенту открытый токен для отображения/сохранения на устройстве
	clientToken := storedToken
	clientToken.Token = tokenVal
	return &clientToken, nil
}

func (a *AuthService) generateRandomToken(bytesLen int) string {
	b := make([]byte, bytesLen)
	_, _ = rand.Read(b)
	return hex.EncodeToString(b)
}
