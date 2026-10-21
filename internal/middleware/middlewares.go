package middleware

import (
	"bufio"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"log"
	"net"
	"net/http"
	"runtime/debug"
	"strings"
	"time"

	"npblog/internal/config"
	"npblog/internal/service"
)

type contextKey string

const (
	BlogContextKey  contextKey = "active_blog_path"
	SessionKey      contextKey = "user_session"
	BearerClaimsKey contextKey = "bearer_auth"
)

type MiddlewareManager struct {
	cfg     *config.AppConfig
	auth    *service.AuthService
	storage *service.StorageService
}

func NewMiddlewareManager(cfg *config.AppConfig, auth *service.AuthService, storage *service.StorageService) *MiddlewareManager {
	return &MiddlewareManager{
		cfg:     cfg,
		auth:    auth,
		storage: storage,
	}
}

type responseWriterRecorder struct {
	http.ResponseWriter
	statusCode   int
	bytesWritten int64
}

func (rw *responseWriterRecorder) WriteHeader(code int) {
	rw.statusCode = code
	rw.ResponseWriter.WriteHeader(code)
}

func (rw *responseWriterRecorder) Write(b []byte) (int, error) {
	if rw.statusCode == 0 {
		rw.statusCode = http.StatusOK
	}
	n, err := rw.ResponseWriter.Write(b)
	rw.bytesWritten += int64(n)
	return n, err
}

func (rw *responseWriterRecorder) Flush() {
	if flusher, ok := rw.ResponseWriter.(http.Flusher); ok {
		flusher.Flush()
	}
}

func (rw *responseWriterRecorder) Hijack() (net.Conn, *bufio.ReadWriter, error) {
	if hijacker, ok := rw.ResponseWriter.(http.Hijacker); ok {
		return hijacker.Hijack()
	}
	return nil, nil, errors.New("hijack not supported")
}

// Logger логгирует входящие HTTP-запросы в стандартный вывод консоли
func (m *MiddlewareManager) Logger(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		rec := &responseWriterRecorder{
			ResponseWriter: w,
			statusCode:     http.StatusOK,
		}

		next.ServeHTTP(rec, r)

		duration := time.Since(start)
		clientIP := m.auth.GetClientIP(r)
		sizeStr := formatBytes(rec.bytesWritten)

		log.Printf("[HTTP] %s | %s %s -> %d %s (%s, %s)",
			clientIP,
			r.Method,
			r.URL.RequestURI(),
			rec.statusCode,
			http.StatusText(rec.statusCode),
			duration.Round(time.Microsecond),
			sizeStr,
		)
	})
}

func formatBytes(b int64) string {
	const unit = 1024
	if b < unit {
		return fmt.Sprintf("%d B", b)
	}
	div, exp := int64(unit), 0
	for n := b / unit; n >= unit; n /= unit {
		div *= unit
		exp++
	}
	return fmt.Sprintf("%.1f %cB", float64(b)/float64(div), "KMGTPE"[exp])
}

// BlogContext извлекает контекст активного блога из заголовков, cookie или query
func (m *MiddlewareManager) BlogContext(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		activeBlog := ""

		if headerBlog := r.Header.Get("X-Blog-Path"); headerBlog != "" {
			activeBlog = headerBlog
		} else if headerBlog := r.Header.Get("X-Blog"); headerBlog != "" {
			activeBlog = headerBlog
		} else if cookie, err := r.Cookie("active_blog_path"); err == nil && cookie.Value != "" {
			activeBlog = cookie.Value
		} else if queryBlog := r.URL.Query().Get("blog"); queryBlog != "" {
			activeBlog = queryBlog
		} else if queryBlog := r.URL.Query().Get("blog_path"); queryBlog != "" {
			activeBlog = queryBlog
		}

		ctx := context.WithValue(r.Context(), BlogContextKey, activeBlog)
		next.ServeHTTP(w, r.WithContext(ctx))
	})
}

// IPFilter проверяет белый список IP-адресов
func (m *MiddlewareManager) IPFilter(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		clientIP := m.auth.GetClientIP(r)
		allowed, _ := m.auth.CheckIPWhitelist(clientIP)

		if !allowed {
			if strings.HasPrefix(r.URL.Path, "/api/") || r.Header.Get("Accept") == "application/json" {
				w.Header().Set("Content-Type", "application/json; charset=utf-8")
				w.WriteHeader(http.StatusForbidden)
				_ = json.NewEncoder(w).Encode(map[string]interface{}{
					"success": false,
					"error":   "ip_blocked",
					"message": "Доступ заблокирован для вашего IP: " + clientIP,
				})
				return
			}

			// Отрисовка HTML страницы блокировки
			w.Header().Set("Content-Type", "text/html; charset=utf-8")
			w.WriteHeader(http.StatusForbidden)
			fmt.Fprintf(w, `<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Доступ заблокирован</title><style>body{background:#121212;color:#f5f5f5;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}.box{background:#1e1e1e;border:1px solid #333;border-radius:12px;padding:32px;text-align:center;max-width:400px;}</style></head><body><div class="box"><h1>🚫 Доступ ограничен</h1><p>Ваш IP-адрес не входит в список разрешенных:</p><p style="color:#ef4444;font-family:monospace;font-weight:bold;">%s</p></div></body></html>`, clientIP)
			return
		}

		next.ServeHTTP(w, r)
	})
}

// AuthMiddleware проверяет авторизацию (Session или Bearer)
func (m *MiddlewareManager) Auth(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		settings := m.cfg.GetSettings()
		if settings.PasswordHash == "" {
			// Пароль не задан в блоге — разрешаем доступ
			next.ServeHTTP(w, r)
			return
		}

		// Исключения для страницы входа и проверки логина
		path := r.URL.Path
		if path == "/login.php" || path == "/api/v1/auth/login" || path == "/serve_data.php" || strings.HasPrefix(path, "/assets/") || strings.HasPrefix(path, "/lang/") {
			next.ServeHTTP(w, r)
			return
		}

		// 1. Проверка Bearer токена для API
		authHeader := r.Header.Get("Authorization")
		if strings.HasPrefix(authHeader, "Bearer ") {
			token := strings.TrimPrefix(authHeader, "Bearer ")
			if m.auth.VerifyBearerToken(token) {
				ctx := context.WithValue(r.Context(), BearerClaimsKey, token)
				next.ServeHTTP(w, r.WithContext(ctx))
				return
			}
		}

		// Альтернативная проверка токена в query-параметре ?api_token=
		if queryToken := r.URL.Query().Get("api_token"); queryToken != "" {
			if m.auth.VerifyBearerToken(queryToken) {
				ctx := context.WithValue(r.Context(), BearerClaimsKey, queryToken)
				next.ServeHTTP(w, r.WithContext(ctx))
				return
			}
		}

		// 2. Проверка веб-сессии
		session := m.auth.GetSession(r)
		if session != nil && session.Authenticated {
			ctx := context.WithValue(r.Context(), SessionKey, session)
			next.ServeHTTP(w, r.WithContext(ctx))
			return
		}

		// Если это API или Legacy скрипт — возвращаем 401 Unauthorized
		if strings.HasPrefix(path, "/api/") || strings.HasSuffix(path, ".php") {
			w.Header().Set("Content-Type", "application/json; charset=utf-8")
			w.WriteHeader(http.StatusUnauthorized)
			_ = json.NewEncoder(w).Encode(map[string]interface{}{
				"success": false,
				"error":   "unauthorized",
				"message": "Необходима авторизация",
			})
			return
		}

		// Если это запрос главной страницы редактора (GET / или /index.php) — перенаправляем на рендер логина
		next.ServeHTTP(w, r)
	})
}

// CSRF проверяет CSRF токен для POST/PUT/DELETE запросов из веб-редактора
func (m *MiddlewareManager) CSRF(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet || r.Method == http.MethodHead || r.Method == http.MethodOptions {
			next.ServeHTTP(w, r)
			return
		}

		path := r.URL.Path
		if path == "/login.php" || path == "/api/v1/auth/login" || strings.HasPrefix(path, "/api/") {
			next.ServeHTTP(w, r)
			return
		}

		// Для веб-сессий проверяем X-CSRF-Token
		sessVal := r.Context().Value(SessionKey)
		if sessVal != nil {
			sess := sessVal.(*service.SessionData)
			clientToken := r.Header.Get("X-CSRF-Token")
			if clientToken == "" || clientToken != sess.CSRFToken {
				w.Header().Set("Content-Type", "application/json; charset=utf-8")
				w.WriteHeader(http.StatusForbidden)
				_ = json.NewEncoder(w).Encode(map[string]interface{}{
					"success": false,
					"error":   "csrf_error",
					"message": "Неверный или отсутствующий CSRF-токен",
				})
				return
			}
		}

		next.ServeHTTP(w, r)
	})
}

// Recovery перехватывает критические паники и отображает Safe Mode
func (m *MiddlewareManager) Recovery(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		defer func() {
			if rec := recover(); rec != nil {
				stack := string(debug.Stack())
				if strings.HasPrefix(r.URL.Path, "/api/") || r.Header.Get("Accept") == "application/json" {
					w.Header().Set("Content-Type", "application/json; charset=utf-8")
					w.WriteHeader(http.StatusInternalServerError)
					_ = json.NewEncoder(w).Encode(map[string]interface{}{
						"success": false,
						"error":   "server_error",
						"message": fmt.Sprintf("Критическая ошибка сервера: %v", rec),
					})
					return
				}

				w.Header().Set("Content-Type", "text/html; charset=utf-8")
				w.WriteHeader(http.StatusOK)
				fmt.Fprintf(w, `<!DOCTYPE html><html><head><meta charset="utf-8"><title>NPBlog - Safe Mode</title><style>body{margin:0;background:#18191e;color:#f3f4f6;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;}.box{background:#23242a;border:2px solid #ef4444;border-radius:12px;padding:24px;max-width:700px;}pre{background:#000;color:#fca5a5;padding:12px;border-radius:6px;overflow-x:auto;}</style></head><body><div class="box"><h2 style="color:#ef4444;margin-top:0;">🛡️ Safe Mode — Критическая ошибка сервера</h2><p>Обнаружена неисправимая ошибка:</p><pre>%v\n\n%s</pre></div></body></html>`, rec, stack)
			}
		}()
		next.ServeHTTP(w, r)
	})
}

// GetActiveBlogPath извлекает активный путь из контекста
func GetActiveBlogPath(r *http.Request) string {
	if val := r.Context().Value(BlogContextKey); val != nil {
		if str, ok := val.(string); ok {
			return str
		}
	}
	return ""
}
