<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно публикации и загрузки по FTP
 * ==============================================================================
 * Построено на едином фреймворке модальных окон (modals/modal.css, modals/modal.js)
 * Поддерживает потоковую загрузку (SSE), real-time логи, умную синхронизацию,
 * выбор блога и сохранение/сброс учетных данных.
 * ==============================================================================
 */

$savedFtpCredentials = null;
if (file_exists(__DIR__ . '/../ftp.json')) {
    $savedFtpCredentials = json_decode(file_get_contents(__DIR__ . '/../ftp.json'), true);
}
$editorSettingsFile = __DIR__ . '/../editor_settings.json';
$editorSettings = file_exists($editorSettingsFile) ? (json_decode(file_get_contents($editorSettingsFile), true) ?: []) : [];
$ftpBlogPaths = isset($editorSettings['blog_paths']) && is_array($editorSettings['blog_paths']) ? $editorSettings['blog_paths'] : [];
if (empty($ftpBlogPaths)) {
    $ftpBlogPaths = [isset($editorSettings['data_path']) ? $editorSettings['data_path'] : 'data'];
}
$ftpActiveBlog = isset($_SESSION['active_blog_path']) ? $_SESSION['active_blog_path'] : (isset($editorSettings['active_blog_path']) ? $editorSettings['active_blog_path'] : $ftpBlogPaths[0]);
?>
<div id="ftpUploadModal" class="modal-overlay" data-size="lg">
    <div class="modal-dialog modal-lg" style="max-height: 90vh; display: flex; flex-direction: column;">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.ftp_title">Публикация по FTP</h3>
                    <p class="modal-subtitle" data-i18n="modals.ftp_subtitle">Загрузка файлов блога на удаленный FTP/FTPS сервер</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeFtpModal()" data-modal-close title="Закрыть" data-i18n-title="common.close">×</button>
            </div>
        </div>

        <!-- Навигация по вкладкам -->
        <div class="modal-tabs">
            <button type="button" class="modal-tab-btn is-active" data-modal-tab="ftpTabSettings" id="ftpTabBtnSettings" data-i18n="modals.ftp_tab_connection">⚙️ Подключение</button>
            <button type="button" class="modal-tab-btn" data-modal-tab="ftpTabLogs" id="ftpTabBtnLogs" data-i18n="modals.ftp_tab_logs">📊 Прогресс и логи</button>
        </div>

        <!-- Тело окна -->
        <div class="modal-body" style="overflow-y: auto; flex: 1; padding: 20px;">
            <!-- Вкладка 1: Параметры подключения -->
            <div class="modal-tab-pane is-active" id="ftpTabSettings">
                <!-- Блок сохраненных настроек -->
                <div id="ftpSavedInfoBox" class="modal-section-card" style="display: <?= $savedFtpCredentials ? 'block' : 'none' ?>; padding: 12px 16px; margin-bottom: 16px; background: rgba(59, 130, 246, 0.08); border: 1.5px solid rgba(59, 130, 246, 0.3); border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <strong style="font-size: 13px; color: #3b82f6; display: flex; align-items: center; gap: 6px;">
                            <span>📁</span> <span data-i18n="modals.ftp_saved_badge">Сохранённые параметры</span>
                        </strong>
                        <span id="ftpSavedDate" style="font-size: 11px; opacity: 0.7;">
                            <?= htmlspecialchars($savedFtpCredentials['saved_at'] ?? '') ?>
                        </span>
                    </div>
                    <div style="font-size: 12px; line-height: 1.6; opacity: 0.9;">
                        <span id="ftpSavedSummary">
                            <strong>Сервер:</strong> <?= htmlspecialchars($savedFtpCredentials['ftpServer'] ?? '') ?> | 
                            <strong>Пользователь:</strong> <?= htmlspecialchars($savedFtpCredentials['ftpUsername'] ?? '') ?> | 
                            <strong>Каталог:</strong> <?= htmlspecialchars($savedFtpCredentials['ftpDirectory'] ?? '') ?>
                        </span>
                    </div>
                </div>

                <!-- Выбор блога для загрузки -->
                <?php if (count($ftpBlogPaths) > 0): ?>
                <div class="modal-form-group" style="margin-bottom: 16px;">
                    <label class="modal-label" for="ftpModalBlogToUpload" data-i18n="modals.ftp_blog_select_label">Выберите блог для загрузки:</label>
                    <select id="ftpModalBlogToUpload" class="modal-select">
                        <?php foreach ($ftpBlogPaths as $path): ?>
                            <?php $folderName = basename(str_replace('\\', '/', $path)); ?>
                            <option value="<?= htmlspecialchars($path) ?>" <?= $path === $ftpActiveBlog ? 'selected' : '' ?>>
                                <?= htmlspecialchars($folderName) ?> (<?= htmlspecialchars($path) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="modal-help-text" data-i18n="modals.ftp_blog_select_hint">Выбранная папка блога будет загружена целиком на FTP сервер.</div>
                </div>
                <?php endif; ?>

                <!-- Сетка: Сервер и Пользователь -->
                <div class="modal-grid-2" style="margin-bottom: 14px;">
                    <div class="modal-form-group" style="margin-bottom: 0;">
                        <label class="modal-label modal-label-required" for="ftpModalServer" data-i18n="modals.ftp_server_label">FTP Сервер *</label>
                        <input type="text" id="ftpModalServer" class="modal-input" 
                               value="<?= htmlspecialchars($savedFtpCredentials['ftpServer'] ?? '') ?>" 
                               placeholder="ftp.example.com" data-i18n-placeholder="modals.ftp_server_ph" required autocomplete="off">
                    </div>
                    <div class="modal-form-group" style="margin-bottom: 0;">
                        <label class="modal-label modal-label-required" for="ftpModalUsername" data-i18n="modals.ftp_user_label">Имя пользователя *</label>
                        <input type="text" id="ftpModalUsername" class="modal-input" 
                               value="<?= htmlspecialchars($savedFtpCredentials['ftpUsername'] ?? '') ?>" 
                               placeholder="username" data-i18n-placeholder="modals.ftp_user_ph" required autocomplete="off">
                    </div>
                </div>

                <!-- Сетка: Пароль и Корневая директория -->
                <div class="modal-grid-2" style="margin-bottom: 16px;">
                    <div class="modal-form-group" style="margin-bottom: 0;">
                        <label class="modal-label modal-label-required" for="ftpModalPassword" data-i18n="modals.ftp_password_label">Пароль *</label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="password" id="ftpModalPassword" class="modal-input" style="padding-right: 40px;"
                                   placeholder="••••••••" data-i18n-placeholder="modals.ftp_password_ph" required autocomplete="current-password">
                            <button type="button" onclick="toggleFtpModalPassword()" style="position: absolute; right: 8px; background: transparent; border: none; font-size: 16px; cursor: pointer; opacity: 0.6; padding: 4px;" title="Показать/скрыть пароль">👁️</button>
                        </div>
                    </div>
                    <div class="modal-form-group" style="margin-bottom: 0;">
                        <label class="modal-label modal-label-required" for="ftpModalDirectory" data-i18n="modals.ftp_dir_label">Корневая директория сервера *</label>
                        <input type="text" id="ftpModalDirectory" class="modal-input" 
                               value="<?= htmlspecialchars($savedFtpCredentials['ftpDirectory'] ?? '') ?>" 
                               placeholder="/public_html или /" data-i18n-placeholder="modals.ftp_dir_ph" required>
                    </div>
                </div>

                <div class="modal-help-text" style="margin-top: -8px; margin-bottom: 16px;" data-i18n="modals.ftp_dir_hint">
                    Папка блога будет загружена в эту директорию (например, в /public_html/data/)
                </div>

                <!-- Опции и чекбоксы -->
                <div class="modal-section-card" style="padding: 14px; display: flex; flex-direction: column; gap: 10px;">
                    <label class="modal-checkbox-label">
                        <input type="checkbox" id="ftpModalSsl" class="modal-checkbox" <?= !empty($savedFtpCredentials['ftpSsl']) ? 'checked' : '' ?>>
                        <span data-i18n="modals.ftp_opt_ssl">Использовать SSL/TLS (безопасное соединение FTPS)</span>
                    </label>

                    <label class="modal-checkbox-label">
                        <input type="checkbox" id="ftpModalSkipExisting" class="modal-checkbox" <?= (!isset($savedFtpCredentials['ftpSkipExisting']) || !empty($savedFtpCredentials['ftpSkipExisting'])) ? 'checked' : '' ?>>
                        <span data-i18n="modals.ftp_opt_skip">Умная синхронизация (пропускать файлы с одинаковым размером)</span>
                    </label>

                    <label class="modal-checkbox-label">
                        <input type="checkbox" id="ftpModalRemember" class="modal-checkbox" checked>
                        <span data-i18n="modals.ftp_opt_remember">Запомнить настройки FTP (пароль не сохраняется на сервере)</span>
                    </label>
                </div>
            </div>

            <!-- Вкладка 2: Прогресс и логи -->
            <div class="modal-tab-pane" id="ftpTabLogs">
                <!-- Статус -->
                <div id="ftpModalStatusBanner" class="modal-alert modal-alert-info" style="margin-bottom: 16px;">
                    <span class="modal-alert-icon" id="ftpModalStatusIcon">ℹ️</span>
                    <div class="modal-alert-content">
                        <div class="modal-alert-title" id="ftpModalStatusTitle" data-i18n="modals.ftp_status_ready">Готов к передаче файлов</div>
                        <div id="ftpModalStatusSubtext" style="font-size: 12px; opacity: 0.85;">Нажмите кнопку «Начать загрузку» для запуска процесса.</div>
                    </div>
                </div>

                <!-- Прогресс-бар -->
                <div style="margin-bottom: 14px;">
                    <div style="width: 100%; height: 26px; background: rgba(0,0,0,0.08); border: 1px solid var(--border-color); border-radius: 13px; overflow: hidden; position: relative;">
                        <div id="ftpModalProgressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #10b981, #059669); border-radius: 13px; transition: width 0.25s ease;"></div>
                        <span id="ftpModalProgressPercent" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: var(--text-color);">0%</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; margin-top: 6px; opacity: 0.85;">
                        <span id="ftpModalProgressStats">Загружено: 0 / 0</span>
                        <span id="ftpModalCurrentFile" style="max-width: 60%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: monospace;">-</span>
                    </div>
                </div>

                <!-- Логи -->
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label class="modal-label" style="margin-bottom: 0;">Терминал процесса:</label>
                        <button type="button" onclick="clearFtpModalLogs()" class="modal-btn modal-btn-ghost" style="padding: 2px 8px; font-size: 11px;">Очистить логи</button>
                    </div>
                    <div id="ftpModalLogsContainer" style="max-height: 280px; min-height: 180px; overflow-y: auto; background: var(--bg-card, rgba(0,0,0,0.06)); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px; font-family: 'Consolas', 'Courier New', monospace; font-size: 12px; line-height: 1.5; display: flex; flex-direction: column; gap: 4px;">
                        <div style="opacity: 0.5; text-align: center; padding: 20px;">Логи появятся здесь после запуска передачи...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Подвал окна -->
        <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <button type="button" id="ftpModalResetBtn" onclick="resetFtpModalSettings()" class="modal-btn modal-btn-danger" style="display: <?= $savedFtpCredentials ? 'inline-flex' : 'none' ?>;" data-i18n="modals.ftp_btn_reset">🗑️ Сбросить настройки</button>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" class="modal-btn modal-btn-ghost" onclick="closeFtpModal()" data-modal-close data-i18n="common.close">Закрыть</button>
                <button type="button" id="ftpModalSaveBtn" onclick="saveFtpModalSettingsOnly()" class="modal-btn modal-btn-secondary" data-i18n="modals.ftp_btn_save">💾 Сохранить параметры</button>
                <button type="button" id="ftpModalStartBtn" onclick="startFtpModalUpload()" class="modal-btn modal-btn-primary" data-i18n="modals.ftp_btn_start">🚀 Начать загрузку</button>
            </div>
        </div>
    </div>
</div>

<script>
let ftpUploadActive = false;

function openFtpModal() {
    if (window.Modal) {
        Modal.open('#ftpUploadModal');
    } else {
        const modal = document.getElementById('ftpUploadModal');
        if (modal) modal.style.display = 'flex';
    }
}

function closeFtpModal() {
    if (ftpUploadActive) {
        if (!confirm('Процесс загрузки на FTP еще продолжается. Вы уверены, что хотите закрыть окно?')) {
            return;
        }
    }
    if (window.Modal) {
        Modal.close('#ftpUploadModal');
    } else {
        const modal = document.getElementById('ftpUploadModal');
        if (modal) modal.style.display = 'none';
    }
}

function toggleFtpModalPassword() {
    const input = document.getElementById('ftpModalPassword');
    if (input) {
        input.type = input.type === 'password' ? 'text' : 'password';
    }
}

function clearFtpModalLogs() {
    const logs = document.getElementById('ftpModalLogsContainer');
    if (logs) logs.innerHTML = '<div style="opacity: 0.5; text-align: center; padding: 20px;">Логи очищены</div>';
}

function addFtpModalLog(message, level = 'info') {
    const logs = document.getElementById('ftpModalLogsContainer');
    if (!logs) return;
    
    // If placeholder is shown, remove it
    if (logs.querySelector('div[style*="text-align: center"]')) {
        logs.innerHTML = '';
    }

    const entry = document.createElement('div');
    entry.style.padding = '4px 8px';
    entry.style.borderRadius = '4px';
    entry.style.fontSize = '12px';
    entry.style.display = 'flex';
    entry.style.gap = '8px';
    entry.style.alignItems = 'flex-start';
    entry.style.wordBreak = 'break-word';

    const time = new Date().toLocaleTimeString('ru-RU');
    const timeSpan = `<span style="opacity: 0.6; flex-shrink: 0; font-size: 11px;">[${time}]</span>`;

    if (level === 'success') {
        entry.style.background = 'rgba(16, 185, 129, 0.12)';
        entry.style.borderLeft = '3px solid #10b981';
        entry.style.color = '#10b981';
    } else if (level === 'error') {
        entry.style.background = 'rgba(239, 68, 68, 0.12)';
        entry.style.borderLeft = '3px solid #ef4444';
        entry.style.color = '#ef4444';
    } else {
        entry.style.background = 'rgba(59, 130, 246, 0.08)';
        entry.style.borderLeft = '3px solid #3b82f6';
    }

    entry.innerHTML = `${timeSpan} <span>${message}</span>`;
    logs.appendChild(entry);
    logs.scrollTop = logs.scrollHeight;
}

function switchFtpModalTab(tabId) {
    if (window.Modal) {
        const inst = Modal.get('#ftpUploadModal');
        if (inst) {
            inst.switchTab(tabId);
            return;
        }
    }
    // Fallback tab switch
    document.querySelectorAll('#ftpUploadModal .modal-tab-btn').forEach(btn => {
        btn.classList.toggle('is-active', btn.getAttribute('data-modal-tab') === tabId);
    });
    document.querySelectorAll('#ftpUploadModal .modal-tab-pane').forEach(pane => {
        pane.classList.toggle('is-active', pane.id === tabId);
    });
}

function saveFtpModalSettingsOnly() {
    const ftpServer = document.getElementById('ftpModalServer').value.trim();
    const ftpUsername = document.getElementById('ftpModalUsername').value.trim();
    const ftpDirectory = document.getElementById('ftpModalDirectory').value.trim();
    const ftpSsl = document.getElementById('ftpModalSsl').checked ? '1' : '0';
    const ftpSkipExisting = document.getElementById('ftpModalSkipExisting').checked ? '1' : '0';

    if (!ftpServer || !ftpUsername || !ftpDirectory) {
        if (window.showNotification) {
            showNotification(window.t ? window.t('modals.ftp_fill_required', 'Пожалуйста, заполните все обязательные поля') : 'Пожалуйста, заполните все обязательные поля', 'error');
        } else {
            alert('Пожалуйста, заполните все обязательные поля');
        }
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const saveBtn = document.getElementById('ftpModalSaveBtn');
    if (saveBtn) saveBtn.classList.add('is-loading');

    fetch('ftp.php', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken,
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            ftpServer: ftpServer,
            ftpUsername: ftpUsername,
            ftpPassword: 'dummy',
            ftpDirectory: ftpDirectory,
            ftpSsl: ftpSsl,
            ftpSkipExisting: ftpSkipExisting,
            remember: '1',
            saveOnly: '1'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (saveBtn) saveBtn.classList.remove('is-loading');
        if (data.success) {
            const msg = window.t ? window.t('modals.ftp_settings_saved', 'Настройки FTP успешно сохранены!') : 'Настройки FTP успешно сохранены!';
            if (window.showNotification) showNotification(msg, 'success');
            
            // Update saved box
            const box = document.getElementById('ftpSavedInfoBox');
            if (box) {
                box.style.display = 'block';
                const summary = document.getElementById('ftpSavedSummary');
                if (summary) summary.innerHTML = `<strong>Сервер:</strong> ${ftpServer} | <strong>Пользователь:</strong> ${ftpUsername} | <strong>Каталог:</strong> ${ftpDirectory}`;
            }
            const resetBtn = document.getElementById('ftpModalResetBtn');
            if (resetBtn) resetBtn.style.display = 'inline-flex';
        } else {
            if (window.showNotification) showNotification(data.message || 'Ошибка сохранения', 'error');
        }
    })
    .catch(e => {
        if (saveBtn) saveBtn.classList.remove('is-loading');
        if (window.showNotification) showNotification('Ошибка: ' + e.message, 'error');
    });
}

function resetFtpModalSettings() {
    const confirmMsg = window.t ? window.t('modals.ftp_reset_confirm', 'Вы уверены, что хотите сбросить сохранённые настройки FTP?') : 'Вы уверены, что хотите сбросить сохранённые настройки FTP?';
    if (!confirm(confirmMsg)) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const resetBtn = document.getElementById('ftpModalResetBtn');
    if (resetBtn) resetBtn.classList.add('is-loading');

    const formData = new FormData();
    formData.append('action', 'reset');

    fetch('ftp.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (resetBtn) resetBtn.classList.remove('is-loading');
        if (data.success) {
            document.getElementById('ftpModalServer').value = '';
            document.getElementById('ftpModalUsername').value = '';
            document.getElementById('ftpModalPassword').value = '';
            document.getElementById('ftpModalDirectory').value = '';
            document.getElementById('ftpModalSsl').checked = false;
            document.getElementById('ftpModalSkipExisting').checked = true;
            
            const box = document.getElementById('ftpSavedInfoBox');
            if (box) box.style.display = 'none';
            if (resetBtn) resetBtn.style.display = 'none';

            if (window.showNotification) showNotification('Настройки FTP сброшены', 'success');
        }
    })
    .catch(e => {
        if (resetBtn) resetBtn.classList.remove('is-loading');
        if (window.showNotification) showNotification('Ошибка: ' + e.message, 'error');
    });
}

function startFtpModalUpload() {
    const ftpServer = document.getElementById('ftpModalServer').value.trim();
    const ftpUsername = document.getElementById('ftpModalUsername').value.trim();
    const ftpPassword = document.getElementById('ftpModalPassword').value;
    const ftpDirectory = document.getElementById('ftpModalDirectory').value.trim();
    const ftpSsl = document.getElementById('ftpModalSsl').checked ? '1' : '0';
    const ftpSkipExisting = document.getElementById('ftpModalSkipExisting').checked ? '1' : '0';
    const remember = document.getElementById('ftpModalRemember').checked;
    const blogToUpload = document.getElementById('ftpModalBlogToUpload') ? document.getElementById('ftpModalBlogToUpload').value : '';

    if (!ftpServer || !ftpUsername || !ftpPassword || !ftpDirectory) {
        const msg = window.t ? window.t('modals.ftp_fill_required', 'Пожалуйста, заполните все обязательные поля (Сервер, Пользователь, Пароль, Директория)') : 'Пожалуйста, заполните все обязательные поля (Сервер, Пользователь, Пароль, Директория)';
        if (window.showNotification) {
            showNotification(msg, 'error');
        } else {
            alert(msg);
        }
        return;
    }

    const startBtn = document.getElementById('ftpModalStartBtn');
    const saveBtn = document.getElementById('ftpModalSaveBtn');
    const resetBtn = document.getElementById('ftpModalResetBtn');
    const progressBar = document.getElementById('ftpModalProgressBar');
    const progressPercent = document.getElementById('ftpModalProgressPercent');
    const progressStats = document.getElementById('ftpModalProgressStats');
    const currentFileSpan = document.getElementById('ftpModalCurrentFile');
    const statusTitle = document.getElementById('ftpModalStatusTitle');
    const statusSubtext = document.getElementById('ftpModalStatusSubtext');
    const statusBanner = document.getElementById('ftpModalStatusBanner');
    const statusIcon = document.getElementById('ftpModalStatusIcon');

    // Save credentials if remember is checked
    if (remember) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch('ftp.php', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken,
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                ftpServer: ftpServer,
                ftpUsername: ftpUsername,
                ftpPassword: 'dummy',
                ftpDirectory: ftpDirectory,
                ftpSsl: ftpSsl,
                ftpSkipExisting: ftpSkipExisting,
                remember: '1',
                saveOnly: '1'
            })
        });
    }

    // Switch to logs tab
    switchFtpModalTab('ftpTabLogs');

    // UI state
    ftpUploadActive = true;
    if (startBtn) {
        startBtn.disabled = true;
        startBtn.classList.add('is-loading');
        startBtn.textContent = '⏳ Передача...';
    }
    if (saveBtn) saveBtn.disabled = true;
    if (resetBtn) resetBtn.disabled = true;

    progressBar.style.width = '0%';
    progressPercent.textContent = '0%';
    progressStats.textContent = 'Загружено: 0 / 0';
    currentFileSpan.textContent = 'Подключение...';

    statusBanner.className = 'modal-alert modal-alert-info';
    statusIcon.textContent = '⏳';
    statusTitle.textContent = window.t ? window.t('modals.ftp_status_connecting', 'Подключение к FTP серверу...') : 'Подключение к FTP серверу...';
    statusSubtext.textContent = `Сервер: ${ftpServer} (${ftpUsername})`;

    clearFtpModalLogs();
    addFtpModalLog(`Инициализация передачи на ${ftpServer}...`, 'info');

    // Prepare formData
    const formData = new URLSearchParams();
    formData.append('ftpServer', ftpServer);
    formData.append('ftpUsername', ftpUsername);
    formData.append('ftpPassword', ftpPassword);
    formData.append('ftpDirectory', ftpDirectory);
    formData.append('ftpSsl', ftpSsl);
    formData.append('ftpSkipExisting', ftpSkipExisting);
    formData.append('blogToUpload', blogToUpload);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    fetch('ftp_upload_stream.php', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken
        },
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        const reader = response.body.getReader();
        const decoder = new TextDecoder();

        function readStream() {
            reader.read().then(({ done, value }) => {
                if (done) {
                    finishUploadUI(true);
                    return;
                }

                const chunk = decoder.decode(value, { stream: true });
                const lines = chunk.split('\n');

                lines.forEach(line => {
                    if (line.startsWith('data: ')) {
                        try {
                            const event = JSON.parse(line.substring(6));

                            if (event.type === 'log') {
                                addFtpModalLog(event.data.message, event.data.level);
                                if (event.data.message.includes('→')) {
                                    currentFileSpan.textContent = event.data.message.replace(/^[^\s]+\s*/, '');
                                }
                            } else if (event.type === 'progress') {
                                const percent = event.data.percent;
                                progressBar.style.width = percent + '%';
                                progressPercent.textContent = percent + '%';
                                progressStats.textContent = `Загружено: ${event.data.current} / ${event.data.total}`;
                                statusTitle.textContent = (window.t ? window.t('modals.ftp_status_uploading', 'Передача файлов...') : 'Передача файлов...') + ` (${percent}%)`;
                            } else if (event.type === 'complete') {
                                if (event.data.failed === 0) {
                                    statusBanner.className = 'modal-alert modal-alert-success';
                                    statusIcon.textContent = '✅';
                                    statusTitle.textContent = window.t ? window.t('modals.ftp_status_complete', 'Загрузка успешно завершена!') : 'Загрузка успешно завершена!';
                                    statusSubtext.textContent = event.data.message;
                                    if (window.showNotification) showNotification(event.data.message, 'success');
                                } else {
                                    statusBanner.className = 'modal-alert modal-alert-danger';
                                    statusIcon.textContent = '⚠️';
                                    statusTitle.textContent = 'Завершено с ошибками';
                                    statusSubtext.textContent = event.data.message;
                                    if (window.showNotification) showNotification(event.data.message, 'error');
                                }
                                finishUploadUI(false);
                            } else if (event.type === 'error') {
                                statusBanner.className = 'modal-alert modal-alert-danger';
                                statusIcon.textContent = '🛑';
                                statusTitle.textContent = 'Ошибка передачи';
                                statusSubtext.textContent = event.data.message;
                                addFtpModalLog('Ошибка: ' + event.data.message, 'error');
                                if (window.showNotification) showNotification('Ошибка FTP: ' + event.data.message, 'error');
                                finishUploadUI(false);
                            }
                        } catch (e) {
                            console.error('SSE JSON parse error:', e);
                        }
                    }
                });

                readStream();
            }).catch(err => {
                addFtpModalLog('Ошибка потока: ' + err.message, 'error');
                finishUploadUI(false);
            });
        }

        readStream();
    })
    .catch(error => {
        statusBanner.className = 'modal-alert modal-alert-danger';
        statusIcon.textContent = '🛑';
        statusTitle.textContent = 'Сбой подключения';
        statusSubtext.textContent = error.message;
        addFtpModalLog('Ошибка: ' + error.message, 'error');
        if (window.showNotification) showNotification('Ошибка: ' + error.message, 'error');
        finishUploadUI(false);
    });

    function finishUploadUI(success) {
        ftpUploadActive = false;
        if (startBtn) {
            startBtn.disabled = false;
            startBtn.classList.remove('is-loading');
            startBtn.textContent = window.t ? window.t('modals.ftp_btn_start', '🚀 Начать загрузку') : '🚀 Начать загрузку';
        }
        if (saveBtn) saveBtn.disabled = false;
        if (resetBtn) resetBtn.disabled = false;
    }
}
</script>
