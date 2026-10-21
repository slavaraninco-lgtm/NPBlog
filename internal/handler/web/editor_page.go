package web

import (
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
	"strings"

	"npblog/internal/assets"
	"npblog/internal/config"
	"npblog/internal/middleware"
	"npblog/internal/service"
)

type EditorPageHandler struct {
	cfg      *config.AppConfig
	auth     *service.AuthService
	storage  *service.StorageService
	hybridFS *assets.HybridFS
}

func NewEditorPageHandler(cfg *config.AppConfig, auth *service.AuthService, storage *service.StorageService, hfs *assets.HybridFS) *EditorPageHandler {
	return &EditorPageHandler{
		cfg:      cfg,
		auth:     auth,
		storage:  storage,
		hybridFS: hfs,
	}
}

// ServeHTTP обрабатывает GET / и GET /index.php
func (h *EditorPageHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	settings := h.cfg.GetSettings()
	clientIP := h.auth.GetClientIP(r)

	// Проверяем авторизацию, если задан хэш пароля
	if settings.PasswordHash != "" {
		session := h.auth.GetSession(r)
		if session == nil || !session.Authenticated {
			h.renderLoginPage(w, clientIP)
			return
		}
	}

	// Пользователь авторизован — рендерим полноценный интерфейс редактора
	h.renderEditor(w, r)
}

func (h *EditorPageHandler) renderLoginPage(w http.ResponseWriter, clientIP string) {
	settings := h.cfg.GetSettings()
	activeBlog := settings.ActiveBlogPath
	isLocked, remainingLockout, _ := h.auth.GetLockoutInfo(clientIP, activeBlog)

	lockoutMsg := ""
	if isLocked {
		mins := (remainingLockout + 59) / 60
		lockoutMsg = fmt.Sprintf("Превышено количество попыток ввода. Доступ заблокирован на %d мин.", mins)
	}

	amoledAttr := ""
	if settings.AmoledTheme {
		amoledAttr = `data-amoled="true"`
	}

	lockoutStyle := "none"
	if isLocked {
		lockoutStyle = "block"
	}
	disabledAttr := ""
	if isLocked {
		disabledAttr = "disabled"
	}

	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	htmlTpl := `<!DOCTYPE html>
<html lang="ru" {{AMOLED_ATTR}}>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в NPBlog</title>
    <script>
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
    <style>
        :root { --bg-color: #ffffff; --text-color: #333333; --border-color: #000000; --shadow-color: rgba(0, 0, 0, 0.08); --danger-color: #d32f2f; --input-focus-shadow: rgba(0, 0, 0, 0.1); }
        [data-theme="dark"] { --bg-color: #121212; --text-color: #f5f5f5; --border-color: #ffffff; --shadow-color: rgba(0, 0, 0, 0.5); --danger-color: #f44336; --input-focus-shadow: rgba(255, 255, 255, 0.25); }
        html[data-amoled="true"] { --bg-color: #000000; }
        html[data-theme="dark"][data-amoled="true"] .login-dialog { border-color: #222222; box-shadow: 0 12px 32px rgba(0, 0, 0, 0.8); }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; transition: background-color 0.3s, color 0.3s; }
        .login-dialog { background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 12px; padding: 32px; width: 100%; max-width: 360px; box-shadow: 0 12px 32px var(--shadow-color); animation: dialogIn 0.28s cubic-bezier(0.34, 1.2, 0.64, 1) forwards; }
        @keyframes dialogIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .login-header { text-align: center; margin-bottom: 24px; }
        .login-title { font-size: 24px; font-weight: 700; margin-bottom: 4px; letter-spacing: -0.02em; }
        .login-subtitle { font-size: 13px; opacity: 0.6; }
        .form-group { width: 100%; margin-bottom: 20px; }
        .form-label { display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
        .input-wrapper { position: relative; width: 100%; }
        .form-input { width: 100%; padding: 10px 40px 10px 12px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-color); font-size: 14px; outline: none; }
        .toggle-password { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-color); cursor: pointer; font-size: 16px; opacity: 0.5; }
        .submit-btn { width: 100%; height: 38px; background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
        .submit-btn:hover:not(:disabled) { background: var(--text-color); color: var(--bg-color); }
        .submit-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .error-message { color: var(--danger-color); font-size: 13px; margin-top: 12px; text-align: center; display: none; }
        .lockout-info { padding: 12px; background: rgba(211, 47, 47, 0.08); border: 1px solid var(--danger-color); border-radius: 8px; color: var(--danger-color); font-size: 13px; margin-bottom: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="login-dialog">
        <div class="login-header">
            <div class="login-title">NPBlog</div>
            <div class="login-subtitle">Панель управления редактором</div>
        </div>
        <div class="lockout-info" id="lockoutInfo" style="display: {{LOCKOUT_STYLE}};">{{LOCKOUT_MSG}}</div>
        <form id="loginForm" onsubmit="handleLogin(event)">
            <div class="form-group">
                <label class="form-label" for="password">Пароль доступа</label>
                <div class="input-wrapper">
                    <input type="password" id="password" class="form-input" required placeholder="Введите ваш пароль" {{DISABLED_ATTR}} autocomplete="current-password">
                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility()">👁️</button>
                </div>
            </div>
            <button type="submit" id="submitBtn" class="submit-btn" {{DISABLED_ATTR}}>Войти</button>
            <div class="error-message" id="errorMessage"></div>
        </form>
    </div>
    <script>
        let lockoutTimeRemaining = {{REMAINING_LOCKOUT}};
        if (lockoutTimeRemaining > 0) startCountdown();

        function togglePasswordVisibility() {
            const inp = document.getElementById('password');
            inp.type = inp.type === 'password' ? 'text' : 'password';
        }
        async function handleLogin(e) {
            e.preventDefault();
            const password = document.getElementById('password').value;
            const errDiv = document.getElementById('errorMessage');
            const btn = document.getElementById('submitBtn');
            errDiv.style.display = 'none';
            btn.disabled = true;

            try {
                const res = await fetch('login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ password })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    errDiv.textContent = data.message || 'Ошибка входа';
                    errDiv.style.display = 'block';
                    if (data.lockoutTimeRemaining) {
                        lockoutTimeRemaining = data.lockoutTimeRemaining;
                        document.getElementById('password').disabled = true;
                        document.getElementById('lockoutInfo').style.display = 'block';
                        updateLockoutText();
                        startCountdown();
                    } else {
                        btn.disabled = false;
                    }
                }
            } catch (err) {
                errDiv.textContent = 'Ошибка сети';
                errDiv.style.display = 'block';
                btn.disabled = false;
            }
        }
        function startCountdown() {
            const iv = setInterval(() => {
                lockoutTimeRemaining--;
                if (lockoutTimeRemaining <= 0) {
                    clearInterval(iv);
                    document.getElementById('password').disabled = false;
                    document.getElementById('submitBtn').disabled = false;
                    document.getElementById('lockoutInfo').style.display = 'none';
                } else {
                    updateLockoutText();
                }
            }, 1000);
        }
        function updateLockoutText() {
            const m = Math.ceil(lockoutTimeRemaining / 60);
            document.getElementById('lockoutInfo').innerText = "Превышено количество попыток. Заблокировано на " + m + " мин.";
        }
    </script>
</body>
</html>`

	htmlTpl = strings.ReplaceAll(htmlTpl, "{{AMOLED_ATTR}}", amoledAttr)
	htmlTpl = strings.ReplaceAll(htmlTpl, "{{LOCKOUT_STYLE}}", lockoutStyle)
	htmlTpl = strings.ReplaceAll(htmlTpl, "{{LOCKOUT_MSG}}", lockoutMsg)
	htmlTpl = strings.ReplaceAll(htmlTpl, "{{DISABLED_ATTR}}", disabledAttr)
	htmlTpl = strings.ReplaceAll(htmlTpl, "{{REMAINING_LOCKOUT}}", fmt.Sprintf("%d", remainingLockout))

	_, _ = w.Write([]byte(htmlTpl))
}

func (h *EditorPageHandler) renderEditor(w http.ResponseWriter, r *http.Request) {
	// 1. Пытаемся прочитать предкомпилированный шаблон web/templates/editor.html
	rawBytes, err := h.hybridFS.ReadFile("web/templates/editor.html")
	if err != nil {
		rawBytes, err = h.hybridFS.ReadFile("index.php")
	}
	if err != nil {
		http.Error(w, "Не удалось загрузить шаблон редактора", http.StatusInternalServerError)
		return
	}

	content := string(rawBytes)

	// Получаем текущую сессию
	session := h.auth.GetSession(r)
	csrfToken := ""
	if session != nil {
		csrfToken = session.CSRFToken
	} else {
		// Создаем сессию для генерации CSRF токена
		s := h.auth.CreateSession(w)
		csrfToken = s.CSRFToken
	}

	settings := h.cfg.GetSettings()

	// Загружаем доступные языки из lang/languages.json
	langBytes, _ := h.hybridFS.ReadFile("lang/languages.json")
	if len(langBytes) == 0 {
		langBytes = []byte("[]")
	}

	// Читаем версию из version.json
	version := "2.287"
	isDev := false
	if vBytes, err := h.hybridFS.ReadFile("version.json"); err == nil {
		var vMap map[string]interface{}
		if json.Unmarshal(vBytes, &vMap) == nil {
			if vStr, ok := vMap["version"].(string); ok {
				version = vStr
			}
			if dVal, ok := vMap["dev"].(bool); ok {
				isDev = dVal
			}
		}
	}

	amoledAttr := ""
	if settings.AmoledTheme {
		amoledAttr = ` data-amoled="true"`
	}
	disableAnimAttr := ""
	if settings.DisableAnimations {
		disableAnimAttr = ` data-disable-animations="true"`
	}

	activeBlog := middleware.GetActiveBlogPath(r)
	dataDirName := filepath.Base(h.storage.GetDataPath(activeBlog, ""))
	defaultDataPath := h.storage.GetDataPath(activeBlog, "")

	hasPassword := settings.PasswordHash != ""

	// Подстановка чистых плейсхолдеров
	content = strings.ReplaceAll(content, "{{CSRF_TOKEN}}", csrfToken)
	content = strings.ReplaceAll(content, "{{AVAILABLE_LANGUAGES}}", string(langBytes))
	content = strings.ReplaceAll(content, "{{CURRENT_LANGUAGE}}", settings.Language)
	content = strings.ReplaceAll(content, "{{DISABLE_ANIMATIONS}}", fmt.Sprintf("%t", settings.DisableAnimations))
	content = strings.ReplaceAll(content, "{{ACTIVE_THEME}}", settings.ActiveTheme)
	content = strings.ReplaceAll(content, "{{DATA_URL_PREFIX}}", fmt.Sprintf("/%s/", dataDirName))
	content = strings.ReplaceAll(content, "{{IS_DEV_BUILD}}", fmt.Sprintf("%t", isDev))
	content = strings.ReplaceAll(content, "{{VERSION}}", version)
	content = strings.ReplaceAll(content, "{{SETUP_DEFAULT_DATA_PATH}}", fmt.Sprintf("%q", defaultDataPath))
	content = strings.ReplaceAll(content, "{{SETUP_HAS_PASSWORD}}", fmt.Sprintf("%t", hasPassword))
	content = strings.ReplaceAll(content, "{{AMOLED_ATTR}}", amoledAttr)
	content = strings.ReplaceAll(content, "{{DISABLE_ANIMATIONS_ATTR}}", disableAnimAttr)

	// Замена динамических тегов, если читался сырой index.php
	reCSRF := regexp.MustCompile(`(?i)<meta name="csrf-token" content="<\?php[^>]*">`)
	content = reCSRF.ReplaceAllString(content, fmt.Sprintf(`<meta name="csrf-token" content="%s">`, csrfToken))

	reLangs := regexp.MustCompile(`(?i)window\.NPBLOG_AVAILABLE_LANGUAGES\s*=\s*<\?php[\s\S]*?\?>;?`)
	content = reLangs.ReplaceAllString(content, fmt.Sprintf(`window.NPBLOG_AVAILABLE_LANGUAGES = %s;`, string(langBytes)))

	reLang := regexp.MustCompile(`(?i)window\.NPBLOG_LANG\s*=\s*'<\?php[\s\S]*?\?>';?`)
	content = reLang.ReplaceAllString(content, fmt.Sprintf(`window.NPBLOG_LANG = '%s';`, settings.Language))

	reAnim := regexp.MustCompile(`(?i)window\.disableAnimations\s*=\s*<\?php[\s\S]*?\?>;?`)
	content = reAnim.ReplaceAllString(content, fmt.Sprintf(`window.disableAnimations = %t;`, settings.DisableAnimations))

	reTheme := regexp.MustCompile(`(?i)localStorage\.getItem\('theme'\)\s*\|\|\s*'<\?php[\s\S]*?\?>'`)
	content = reTheme.ReplaceAllString(content, fmt.Sprintf(`localStorage.getItem('theme') || '%s'`, settings.ActiveTheme))

	reDataPrefix := regexp.MustCompile(`(?i)const DATA_URL_PREFIX\s*=\s*'<\?php[\s\S]*?\?>';?`)
	content = reDataPrefix.ReplaceAllString(content, fmt.Sprintf(`const DATA_URL_PREFIX = '/%s/';`, dataDirName))

	reDev := regexp.MustCompile(`(?i)window\.isDevBuild\s*=\s*<\?php[\s\S]*?\?>;?`)
	content = reDev.ReplaceAllString(content, fmt.Sprintf(`window.isDevBuild = %t;`, isDev))

	// Замена safe_include_editor_modal
	reModal := regexp.MustCompile(`<\?php\s+(?:safe_include_editor_modal|require_once\s+__DIR__\s*\.\s*'\/modals_editor\/)\(?['"]([^'"]+)['"]\)?;\s*\?>`)
	content = reModal.ReplaceAllStringFunc(content, func(m string) string {
		sub := reModal.FindStringSubmatch(m)
		if len(sub) > 1 {
			filename := sub[1]
			modalBytes, err := h.hybridFS.ReadFile(filepath.Join("modals_editor", filename))
			if err == nil {
				modalHTML := string(modalBytes)
				modalHTML = regexp.MustCompile(`^<\?php[\s\S]*?\?>`).ReplaceAllString(modalHTML, "")
				return modalHTML
			}
		}
		return ""
	})

	// Очистка версионных хэшей в стилях и скриптах (?v=...)
	nowTs := fmt.Sprintf("?v=%d", os.Getpid())
	content = regexp.MustCompile(`\?v=<\?php[\s\S]*?\?>`).ReplaceAllString(content, nowTs)

	// Гарантированное удаление любых оставшихся PHP тегов
	content = regexp.MustCompile(`<\?php[\s\S]*?\?>`).ReplaceAllString(content, "")
	content = regexp.MustCompile(`<\?=[^>]*\?>`).ReplaceAllString(content, "")
	content = regexp.MustCompile(`\?>;`).ReplaceAllString(content, ";")

	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	_, _ = w.Write([]byte(content))
}
