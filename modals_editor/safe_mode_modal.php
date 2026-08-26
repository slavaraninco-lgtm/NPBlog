<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно Safe Mode (Аварийное восстановление)
 * ==============================================================================
 * Полностью автономный модуль Safe Mode с нулевой зависимостью от внешних скриптов/стилей.
 * Активируется автоматически при любых ошибках JS, 404 на скриптах/стилях,
 * фатальных ошибках PHP или отсутствии ключевых компонентов редактора.
 * ==============================================================================
 */
?>
<style id="safe-mode-core-styles">
.safe-mode-backdrop {
    position: fixed !important;
    inset: 0 !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 99999999 !important;
    background: #18191e !important;
    color: #f3f4f6 !important;
    overflow-y: auto !important;
    padding: 24px 16px !important;
    box-sizing: border-box !important;
    display: none;
    align-items: center !important;
    justify-content: center !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
}
body.safe-mode-active {
    overflow: hidden !important;
}
.safe-mode-card {
    width: 100%;
    max-width: 780px;
    background: #23242a;
    border: 2px solid #3c3e48;
    border-radius: 14px;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.85);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
}
.safe-mode-header {
    background: rgba(0, 0, 0, 0.3);
    border-bottom: 1px solid #3c3e48;
    padding: 18px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.safe-mode-badge {
    background: #ef4444;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    letter-spacing: 0.05em;
    display: inline-block;
}
.safe-mode-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 9px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    border: 1px solid transparent;
    text-decoration: none;
    font-family: inherit;
    line-height: 1.3;
}
.safe-mode-btn-primary {
    background: #2563eb;
    color: #ffffff;
    border-color: #3b82f6;
}
.safe-mode-btn-primary:hover {
    background: #1d4ed8;
}
.safe-mode-btn-secondary {
    background: #374151;
    color: #f3f4f6;
    border-color: #4b5563;
}
.safe-mode-btn-secondary:hover {
    background: #4b5563;
}
.safe-mode-btn-ghost {
    background: transparent;
    color: #9ca3af;
    border-color: #4b5563;
}
.safe-mode-btn-ghost:hover {
    background: rgba(255, 255, 255, 0.05);
    color: #f3f4f6;
}
.safe-mode-btn-success {
    background: #059669;
    color: #ffffff;
    border-color: #10b981;
}
.safe-mode-btn-success:hover {
    background: #047857;
}
.safe-mode-dropzone {
    border: 2px dashed #4b5563;
    border-radius: 10px;
    padding: 26px 18px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: rgba(255, 255, 255, 0.02);
}
.safe-mode-dropzone:hover {
    border-color: #60a5fa;
    background: rgba(59, 130, 246, 0.05);
}
</style>

<div id="safeModeOverlay" class="safe-mode-backdrop">
    
    <div id="safeModeDialog" class="safe-mode-card">
        
        <!-- Шапка Safe Mode -->
        <div class="safe-mode-header">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 3px; flex-wrap: wrap;">
                        <h3 style="margin: 0; font-size: 19px; font-weight: 700; color: #f9fafb; letter-spacing: -0.01em;" data-i18n="safemode.title">Safe Mode — Режим восстановления</h3>

                    </div>
                    <p style="margin: 0; font-size: 13px; color: #9ca3af;" data-i18n="safemode.subtitle">Редактор переведён в безопасный режим из-за обнаруженной ошибки или отсутствия компонентов</p>
                </div>
            </div>
        </div>

        <!-- Тело Safe Mode -->
        <div style="padding: 24px; overflow-y: auto; max-height: 70vh; color: #e5e7eb; font-size: 13.5px; line-height: 1.6;">
            
            <!-- Блок обнаруженной ошибки -->
            <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); border-radius: 10px; padding: 14px 16px; margin-bottom: 20px; display: flex; gap: 12px; align-items: flex-start;">
                <span style="font-size: 20px; line-height: 1; flex-shrink: 0;">⚠️</span>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 700; font-size: 14px; color: #f87171; margin-bottom: 4px;" data-i18n="safemode.error_detected_title">Обнаружена ошибка / отсутствуют файлы:</div>
                    <div id="safeModeErrorSummary" style="color: #fca5a5; font-family: monospace; font-size: 12.5px; word-break: break-word; white-space: pre-wrap;">Неизвестная ошибка выполнения</div>
                    
                    <details id="safeModeErrorDetailsWrap" style="margin-top: 10px; border-top: 1px dashed rgba(239, 68, 68, 0.3); padding-top: 8px;">
                        <summary style="cursor: pointer; font-size: 12px; color: #9ca3af; user-select: none;" data-i18n="safemode.error_details_toggle">Технические подробности (стек ошибки)</summary>
                        <pre id="safeModeErrorStack" style="margin-top: 8px; padding: 10px; background: rgba(0, 0, 0, 0.4); border-radius: 6px; font-size: 11px; font-family: monospace; color: #d1d5db; overflow-x: auto; white-space: pre-wrap; max-height: 140px;"></pre>
                    </details>
                </div>
            </div>

            <!-- Блок восстановления через архив (ZIP) -->
            <div style="background: rgba(0, 0, 0, 0.2); border: 1px solid #374151; border-radius: 10px; padding: 18px; margin-bottom: 10px;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                    <span style="font-size: 18px;">📦</span>
                    <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #f3f4f6;" data-i18n="safemode.recovery_title">Восстановление и обновление системы</h4>
                </div>
                <p style="margin: 0 0 16px 0; font-size: 12.5px; color: #9ca3af; line-height: 1.5;" data-i18n="safemode.recovery_desc">
                    Загрузите официальный ZIP-архив с обновлением NPBlog, чтобы восстановить отсутствующие или поврежденные файлы ядра редактора. Ваши статьи и личные файлы не пострадают.
                </p>

                <!-- Шаг 1: Выбор / Drag & Drop архива -->
                <div id="safeModeUploadStep">
                    <input type="file" id="safeModeZipInput" accept=".zip" style="display: none;" onchange="handleSafeModeFileSelect(event)">
                    
                    <div id="safeModeDropzone" class="safe-mode-dropzone" onclick="document.getElementById('safeModeZipInput').click()" 
                         ondragover="handleSafeModeDragOver(event)" ondragleave="handleSafeModeDragLeave(event)" ondrop="handleSafeModeDrop(event)">
                        <div style="font-size: 32px; margin-bottom: 8px;">📥</div>
                        <div style="font-size: 14px; font-weight: 600; color: #e5e7eb; margin-bottom: 4px;" data-i18n="safemode.drop_archive_text">Перетащите ZIP-архив сюда или нажмите для выбора</div>
                        <div style="font-size: 11.5px; color: #9ca3af;" data-i18n="safemode.drop_archive_hint">Поддерживаются официальные архивы обновлений NPBlog (.zip)</div>
                    </div>
                </div>

                <!-- Шаг 2: Предпросмотр архива перед восстановлением -->
                <div id="safeModePreviewStep" style="display: none; margin-top: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.35); border-radius: 8px; margin-bottom: 12px;">
                        <div>
                            <span style="font-size: 12px; color: #93c5fd;" data-i18n="safemode.current_version">Текущая версия:</span>
                            <strong id="safeModeCurrentVer" style="margin-left: 4px; color: #ffffff;">—</strong>
                        </div>
                        <div>
                            <span style="font-size: 12px; color: #93c5fd;" data-i18n="safemode.archive_version">Версия в архиве:</span>
                            <strong id="safeModeArchiveVer" style="margin-left: 4px; color: #60a5fa;">—</strong>
                        </div>
                    </div>

                    <div style="margin-bottom: 14px;">
                        <div id="safeModeFilesCountLabel" style="font-size: 12px; font-weight: 600; color: #d1d5db; margin-bottom: 6px;">Файлы для восстановления:</div>
                        <div id="safeModeFilesList" style="max-height: 130px; overflow-y: auto; background: rgba(0,0,0,0.35); border: 1px solid #374151; padding: 8px 12px; border-radius: 6px; font-size: 11px; font-family: monospace; line-height: 1.6; color: #9ca3af;"></div>
                    </div>

                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" onclick="resetSafeModeUploadState()" class="safe-mode-btn safe-mode-btn-ghost">Отмена</button>
                        <button type="button" id="safeModeStartRestoreBtn" onclick="startSafeModeRestoreProcess()" class="safe-mode-btn safe-mode-btn-primary" data-i18n="safemode.start_restore_btn">
                            🚀 Начать восстановление системы
                        </button>
                    </div>
                </div>

                <!-- Шаг 3: Прогресс восстановления -->
                <div id="safeModeProgressStep" style="display: none; margin-top: 16px; text-align: center;">
                    <div id="safeModeStatusText" style="font-size: 13px; font-weight: 600; color: #e5e7eb; margin-bottom: 10px;" data-i18n="safemode.restoring_progress">Восстановление файлов системы...</div>
                    <div style="width: 100%; height: 8px; background: rgba(255, 255, 255, 0.1); border-radius: 999px; overflow: hidden;">
                        <div id="safeModeProgressBar" style="width: 0%; height: 100%; background: #10b981; border-radius: 999px; transition: width 0.3s ease;"></div>
                    </div>
                </div>

                <!-- Шаг 4: Успех восстановления -->
                <div id="safeModeSuccessStep" style="display: none; margin-top: 16px; text-align: center; padding: 16px 0;">
                    <div style="font-size: 38px; margin-bottom: 8px;">🎉</div>
                    <div style="font-size: 16px; font-weight: 700; color: #10b981; margin-bottom: 4px;" data-i18n="safemode.restore_success_title">Система успешно восстановлена!</div>
                    <p style="font-size: 12.5px; color: #9ca3af; margin: 0 0 16px 0;" data-i18n="safemode.restore_success_desc">Все файлы ядра обновлены. Нажмите кнопку ниже для перезагрузки редактора.</p>
                    <button type="button" onclick="window.location.reload(true)" class="safe-mode-btn safe-mode-btn-success" data-i18n="safemode.reload_btn">
                        🔄 Перезагрузить страницу
                    </button>
                </div>

            </div>

        </div>

        <!-- Подвал Safe Mode -->
        <div style="background: rgba(0, 0, 0, 0.25); border-top: 1px solid #3c3e48; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" onclick="window.location.reload(true)" class="safe-mode-btn safe-mode-btn-secondary" data-i18n="safemode.reload_btn">
                    🔄 Перезагрузить
                </button>
                <button type="button" onclick="safeModeClearCacheAndReload()" class="safe-mode-btn safe-mode-btn-secondary" data-i18n="safemode.clear_cache_reload_btn">
                    🧹 Сбросить кэш и перезагрузить
                </button>
            </div>
            
            <div>
                <button type="button" onclick="exitSafeMode()" class="safe-mode-btn safe-mode-btn-ghost" style="font-size: 12px;" data-i18n="safemode.ignore_continue_btn">
                    ⚠️ Попробовать продолжить
                </button>
            </div>
        </div>

    </div>
</div>

<script>
/**
 * Safe Mode Standalone Controller
 */
window.isSafeModeActive = false;
window.safeModePendingUpdateToken = null;
window.safeModePendingRootFolder = '';

function getSafeModeCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function enterSafeMode(errorData) {
    window.isSafeModeActive = true;
    
    console.error('NPBlog Safe Mode triggered:', errorData);

    const overlay = document.getElementById('safeModeOverlay');
    if (overlay) {
        overlay.style.display = 'flex';
        document.body.classList.add('safe-mode-active');
    }

    // Заполняем информацию об ошибке
    const summaryEl = document.getElementById('safeModeErrorSummary');
    const stackEl = document.getElementById('safeModeErrorStack');
    
    let summaryText = 'Неизвестная ошибка приложения';
    let stackText = '';

    // Сбор отсутствующих компонентов
    let missingInfo = '';
    if (window._missingComponentsList && window._missingComponentsList.length > 0) {
        missingInfo = 'Отсутствуют следующие файлы/компоненты:\n • ' + window._missingComponentsList.join('\n • ') + '\n\n';
    }

    if (typeof errorData === 'string') {
        summaryText = errorData;
    } else if (errorData && typeof errorData === 'object') {
        if (errorData.message) {
            summaryText = errorData.message;
        }
        if (errorData.filename || errorData.source) {
            summaryText += `\nИсточник: ${errorData.filename || errorData.source}`;
            if (errorData.lineno || errorData.line) {
                summaryText += ` (строка ${errorData.lineno || errorData.line})`;
            }
        }
        if (errorData.stack) {
            stackText = errorData.stack;
        } else if (errorData.error && errorData.error.stack) {
            stackText = errorData.error.stack;
        } else {
            stackText = JSON.stringify(errorData, null, 2);
        }
    }

    if (summaryEl) summaryEl.textContent = (missingInfo ? missingInfo + 'Ошибка: ' : '') + summaryText;
    if (stackEl) stackEl.textContent = stackText || (missingInfo ? missingInfo : 'Стек вызовов недоступен');

    // Применяем переводы если i18n доступен
    if (window.NPBlogI18n && typeof window.NPBlogI18n.applyTranslations === 'function' && overlay) {
        try {
            window.NPBlogI18n.applyTranslations(overlay);
        } catch(e) {}
    }
}

function exitSafeMode() {
    window.isSafeModeActive = false;
    const overlay = document.getElementById('safeModeOverlay');
    if (overlay) overlay.style.display = 'none';
    document.body.classList.remove('safe-mode-active');
}

function safeModeClearCacheAndReload() {
    try {
        localStorage.clear();
        sessionStorage.clear();
    } catch(e) {}
    window.location.reload(true);
}

function handleSafeModeDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    const dz = document.getElementById('safeModeDropzone');
    if (dz) dz.style.borderColor = '#3b82f6';
}

function handleSafeModeDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    const dz = document.getElementById('safeModeDropzone');
    if (dz) dz.style.borderColor = '#4b5563';
}

function handleSafeModeDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    const dz = document.getElementById('safeModeDropzone');
    if (dz) dz.style.borderColor = '#4b5563';
    
    const files = e.dataTransfer.files;
    if (files && files.length > 0) {
        processSafeModeZipFile(files[0]);
    }
}

function handleSafeModeFileSelect(e) {
    const files = e.target.files;
    if (files && files.length > 0) {
        processSafeModeZipFile(files[0]);
    }
}

async function processSafeModeZipFile(file) {
    if (!file.name.toLowerCase().endsWith('.zip')) {
        alert('Пожалуйста, выберите файл с расширением .zip');
        return;
    }

    const uploadStep = document.getElementById('safeModeUploadStep');
    const previewStep = document.getElementById('safeModePreviewStep');
    const progressStep = document.getElementById('safeModeProgressStep');
    const statusText = document.getElementById('safeModeStatusText');
    const progressBar = document.getElementById('safeModeProgressBar');

    if (uploadStep) uploadStep.style.display = 'none';
    if (previewStep) previewStep.style.display = 'none';
    if (progressStep) progressStep.style.display = 'block';
    if (statusText) statusText.textContent = (window.t ? window.t('safemode.analyzing_archive', 'Анализ архива...') : 'Анализ архива...');
    if (progressBar) progressBar.style.width = '30%';

    const formData = new FormData();
    formData.append('updateFile', file);

    try {
        const csrf = getSafeModeCsrfToken();
        const headers = {};
        if (csrf) headers['X-CSRF-Token'] = csrf;

        const response = await fetch('update_system.php?action=preview', {
            method: 'POST',
            headers: headers,
            body: formData
        });

        const data = await response.json();
        if (progressStep) progressStep.style.display = 'none';

        if (data.success) {
            window.safeModePendingUpdateToken = data.token;
            window.safeModePendingRootFolder = data.rootFolder || '';

            document.getElementById('safeModeCurrentVer').textContent = data.currentVersion || '—';
            document.getElementById('safeModeArchiveVer').textContent = data.newVersion || '—';

            const countLabel = document.getElementById('safeModeFilesCountLabel');
            if (countLabel) {
                const count = (data.files && data.files.length) ? data.files.length : 0;
                countLabel.textContent = (window.t ? window.t('safemode.files_to_restore', `Файлы, которые будут восстановлены/обновлены (${count}):`, { count }) : `Файлы, которые будут восстановлены/обновлены (${count}):`);
            }

            const filesList = document.getElementById('safeModeFilesList');
            if (filesList) {
                if (data.files && data.files.length > 0) {
                    filesList.innerHTML = data.files.map(f => `<div>📄 ${escapeHtmlSafeMode(f)}</div>`).join('');
                } else {
                    filesList.innerHTML = '<div>Нет файлов для замены</div>';
                }
            }

            if (previewStep) previewStep.style.display = 'block';
        } else {
            alert('Ошибка анализа архива: ' + (data.error || 'Неверный формат'));
            resetSafeModeUploadState();
        }
    } catch (err) {
        console.error('Ошибка анализа архива в Safe Mode:', err);
        alert('Сетевая ошибка при анализе архива');
        resetSafeModeUploadState();
    }
}

function resetSafeModeUploadState() {
    window.safeModePendingUpdateToken = null;
    window.safeModePendingRootFolder = '';

    const uploadStep = document.getElementById('safeModeUploadStep');
    const previewStep = document.getElementById('safeModePreviewStep');
    const progressStep = document.getElementById('safeModeProgressStep');
    const successStep = document.getElementById('safeModeSuccessStep');
    const zipInput = document.getElementById('safeModeZipInput');

    if (uploadStep) uploadStep.style.display = 'block';
    if (previewStep) previewStep.style.display = 'none';
    if (progressStep) progressStep.style.display = 'none';
    if (successStep) successStep.style.display = 'none';
    if (zipInput) zipInput.value = '';
}

async function startSafeModeRestoreProcess() {
    if (!window.safeModePendingUpdateToken) return;

    const previewStep = document.getElementById('safeModePreviewStep');
    const progressStep = document.getElementById('safeModeProgressStep');
    const successStep = document.getElementById('safeModeSuccessStep');
    const statusText = document.getElementById('safeModeStatusText');
    const progressBar = document.getElementById('safeModeProgressBar');

    if (previewStep) previewStep.style.display = 'none';
    if (progressStep) progressStep.style.display = 'block';
    if (statusText) statusText.textContent = (window.t ? window.t('safemode.restoring_progress', 'Восстановление файлов системы...') : 'Восстановление файлов системы...');
    if (progressBar) progressBar.style.width = '60%';

    try {
        const csrf = getSafeModeCsrfToken();
        const headers = { 'Content-Type': 'application/json' };
        if (csrf) headers['X-CSRF-Token'] = csrf;

        const response = await fetch('update_system.php?action=update', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                token: window.safeModePendingUpdateToken,
                rootFolder: window.safeModePendingRootFolder
            })
        });

        const data = await response.json();

        if (data.success) {
            if (progressBar) progressBar.style.width = '100%';
            setTimeout(() => {
                if (progressStep) progressStep.style.display = 'none';
                if (successStep) successStep.style.display = 'block';
            }, 500);
        } else {
            alert('Ошибка восстановления: ' + (data.error || 'Не удалось применить файлы'));
            resetSafeModeUploadState();
        }
    } catch (err) {
        console.error('Ошибка процесса восстановления:', err);
        alert('Сетевая ошибка при восстановлении системы');
        resetSafeModeUploadState();
    }
}

function escapeHtmlSafeMode(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Проверка целостности ключевых компонентов интерфейса и скриптов
function checkEditorComponentsHealth() {
    if (window.isSafeModeActive) return true;

    // 1. Проверка отсутствующих компонентов
    if (window._missingComponentsList && window._missingComponentsList.length > 0) {
        enterSafeMode({
            message: 'Отсутствуют необходимые компоненты редактора: ' + window._missingComponentsList.join(', '),
            filename: window._missingComponentsList[0],
            stack: 'Missing components list:\n' + window._missingComponentsList.join('\n')
        });
        return false;
    }

    // 2. Проверка DOM-элементов
    const criticalElements = [
        { id: 'contentVisual', name: 'Область редактора (#contentVisual)' },
        { id: 'blogForm', name: 'Форма статьи (#blogForm)' },
        { id: 'title', name: 'Поле заголовка (#title)' }
    ];

    for (let i = 0; i < criticalElements.length; i++) {
        const item = criticalElements[i];
        if (!document.getElementById(item.id)) {
            enterSafeMode({
                message: `Отсутствует критический DOM-компонент: ${item.name}`,
                filename: 'index.php',
                stack: `Health check error: Critical DOM element #${item.id} not found.`
            });
            return false;
        }
    }

    // 3. Проверка загрузки основного функционала редактора
    if (typeof setMode !== 'function' || typeof showNotification !== 'function') {
        enterSafeMode({
            message: 'Не загружен или поврежден основной скрипт редактора (editor-main.js)',
            filename: 'editor-main.js',
            stack: 'Health check error: Core editor functions (setMode, showNotification) are undefined.'
        });
        return false;
    }

    // 4. Проверка модуля локализации
    if (!window.NPBlogI18n) {
        enterSafeMode({
            message: 'Отсутствует модуль локализации (lang/i18n.js)',
            filename: 'lang/i18n.js',
            stack: 'Health check error: window.NPBlogI18n is undefined.'
        });
        return false;
    }

    return true;
}

// Если ошибки были пойманы до загрузки разметки Safe Mode
if (window._safeModeErrors && window._safeModeErrors.length > 0) {
    enterSafeMode(window._safeModeErrors[0]);
}

// Запуск проверки компонентов при загрузке документа
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(checkEditorComponentsHealth, 300);
    });
} else {
    setTimeout(checkEditorComponentsHealth, 300);
}
window.addEventListener('load', function() {
    setTimeout(checkEditorComponentsHealth, 500);
});
</script>
