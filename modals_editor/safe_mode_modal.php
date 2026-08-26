<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно Safe Mode (Аварийное восстановление)
 * ==============================================================================
 * Построено на едином фреймворке модальных окон (modals/modal.css, modals/modal.js).
 * Активируется автоматически при критических ошибках JS или отсутствии компонентов.
 * Отображает серое окружение и форму восстановления системы из ZIP-архива.
 * ==============================================================================
 */
?>
<div id="safeModeOverlay" class="safe-mode-backdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999999; background: #1e1e24; color: #f3f4f6; overflow-y: auto; padding: 24px 16px; box-sizing: border-box; align-items: center; justify-content: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    
    <div id="safeModeDialog" class="modal-dialog modal-lg" style="width: 100%; max-width: 780px; background: #25262c; border: 2px solid #3e404b; border-radius: 14px; box-shadow: 0 25px 70px rgba(0, 0, 0, 0.85); overflow: hidden; display: flex; flex-direction: column; animation: modalZoomIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
        
        <!-- Шапка Safe Mode -->
        <div class="modal-header" style="background: rgba(0, 0, 0, 0.25); border-bottom: 1px solid #3e404b; padding: 18px 24px; display: flex; align-items: center; justify-content: space-between;">
            <div class="modal-header-start" style="display: flex; align-items: center; gap: 14px;">
                <span class="modal-icon icon-danger" style="font-size: 26px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.35); border-radius: 12px; color: #ef4444;">🛡️</span>
                <div class="modal-titles">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 3px;">
                        <h3 class="modal-title" style="margin: 0; font-size: 19px; font-weight: 700; color: #f9fafb; letter-spacing: -0.01em;" data-i18n="safemode.title">Safe Mode — Режим восстановления</h3>
                        <span class="modal-badge" style="background: #ef4444; color: #ffffff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 6px; letter-spacing: 0.05em;" data-i18n="safemode.badge">SAFE MODE</span>
                    </div>
                    <p class="modal-subtitle" style="margin: 0; font-size: 13px; color: #9ca3af;" data-i18n="safemode.subtitle">Редактор переведён в безопасный режим из-за обнаруженной ошибки или отсутствия компонентов</p>
                </div>
            </div>
        </div>

        <!-- Тело Safe Mode -->
        <div class="modal-body" style="padding: 24px; overflow-y: auto; max-height: 70vh; color: #e5e7eb; font-size: 13.5px; line-height: 1.6;">
            
            <!-- Блок обнаруженной ошибки -->
            <div class="modal-alert modal-alert-danger" style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); border-radius: 10px; padding: 14px 16px; margin-bottom: 20px; display: flex; gap: 12px; align-items: flex-start;">
                <span style="font-size: 20px; line-height: 1;">⚠️</span>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 700; font-size: 14px; color: #f87171; margin-bottom: 4px;" data-i18n="safemode.error_detected_title">Обнаружена ошибка:</div>
                    <div id="safeModeErrorSummary" style="color: #fca5a5; font-family: monospace; font-size: 12.5px; word-break: break-word;">Неизвестная ошибка выполнения</div>
                    
                    <details id="safeModeErrorDetailsWrap" style="margin-top: 10px; border-top: 1px dashed rgba(239, 68, 68, 0.3); padding-top: 8px;">
                        <summary style="cursor: pointer; font-size: 12px; color: #9ca3af; user-select: none;" data-i18n="safemode.error_details_toggle">Технические подробности (стек ошибки)</summary>
                        <pre id="safeModeErrorStack" style="margin-top: 8px; padding: 10px; background: rgba(0, 0, 0, 0.4); border-radius: 6px; font-size: 11px; font-family: monospace; color: #d1d5db; overflow-x: auto; white-space: pre-wrap; max-height: 140px;"></pre>
                    </details>
                </div>
            </div>

            <!-- Блок восстановления через архив (ZIP) -->
            <div style="background: rgba(0, 0, 0, 0.2); border: 1px solid #374151; border-radius: 10px; padding: 18px; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                    <span style="font-size: 18px;">📦</span>
                    <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #f3f4f6;" data-i18n="safemode.recovery_title">Восстановление и обновление системы</h4>
                </div>
                <p style="margin: 0 0 16px 0; font-size: 12.5px; color: #9ca3af; line-height: 1.5;" data-i18n="safemode.recovery_desc">
                    Загрузите официальный ZIP-архив с обновлением NPBlog, чтобы перезаписать поврежденные файлы ядра и восстановить работоспособность редактора. Ваши статьи и личные файлы не пострадают.
                </p>

                <!-- Шаг 1: Выбор / Drag & Drop архива -->
                <div id="safeModeUploadStep">
                    <input type="file" id="safeModeZipInput" accept=".zip" style="display: none;" onchange="handleSafeModeFileSelect(event)">
                    
                    <div id="safeModeDropzone" onclick="document.getElementById('safeModeZipInput').click()" 
                         ondragover="handleSafeModeDragOver(event)" ondragleave="handleSafeModeDragLeave(event)" ondrop="handleSafeModeDrop(event)"
                         style="border: 2px dashed #4b5563; border-radius: 10px; padding: 24px; text-align: center; cursor: pointer; transition: all 0.2s ease; background: rgba(255, 255, 255, 0.02);">
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
                        <button type="button" onclick="resetSafeModeUploadState()" class="modal-btn modal-btn-ghost" style="color: #9ca3af; border: 1px solid #4b5563; padding: 8px 16px; font-size: 13px;">Отмена</button>
                        <button type="button" id="safeModeStartRestoreBtn" onclick="startSafeModeRestoreProcess()" class="modal-btn modal-btn-primary" style="background: #2563eb; color: #ffffff; padding: 8px 20px; font-weight: 600; font-size: 13px;" data-i18n="safemode.start_restore_btn">
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
                    <button type="button" onclick="window.location.reload(true)" class="modal-btn modal-btn-primary" style="background: #059669; color: #ffffff; padding: 10px 24px; font-weight: 600; font-size: 14px;" data-i18n="safemode.reload_btn">
                        🔄 Перезагрузить страницу
                    </button>
                </div>

            </div>

        </div>

        <!-- Подвал Safe Mode -->
        <div class="modal-footer" style="background: rgba(0, 0, 0, 0.25); border-top: 1px solid #3e404b; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" onclick="window.location.reload(true)" class="modal-btn" style="background: #374151; color: #f3f4f6; border: 1px solid #4b5563; padding: 8px 16px; font-size: 13px;" data-i18n="safemode.reload_btn">
                    🔄 Перезагрузить
                </button>
                <button type="button" onclick="safeModeClearCacheAndReload()" class="modal-btn" style="background: #374151; color: #f3f4f6; border: 1px solid #4b5563; padding: 8px 16px; font-size: 13px;" data-i18n="safemode.clear_cache_reload_btn">
                    🧹 Сбросить кэш и перезагрузить
                </button>
            </div>
            
            <div>
                <button type="button" onclick="exitSafeMode()" class="modal-btn" style="background: transparent; color: #9ca3af; border: 1px dashed #4b5563; padding: 8px 14px; font-size: 12px;" data-i18n="safemode.ignore_continue_btn">
                    ⚠️ Попробовать продолжить
                </button>
            </div>
        </div>

    </div>
</div>

<script>
/**
 * Safe Mode Controller
 */
window.isSafeModeActive = false;
window.safeModePendingUpdateToken = null;
window.safeModePendingRootFolder = '';

function enterSafeMode(errorData) {
    if (window.isSafeModeActive) return;
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

    if (summaryEl) summaryEl.textContent = summaryText;
    if (stackEl) stackEl.textContent = stackText || 'Стек вызовов недоступен';

    // Применяем переводы если i18n доступен
    if (window.NPBlogI18n && typeof window.NPBlogI18n.applyTranslations === 'function') {
        window.NPBlogI18n.applyTranslations(overlay);
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
        const response = await fetch('update_system.php?action=preview', {
            method: 'POST',
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
        const response = await fetch('update_system.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
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

// Проверка целостности ключевых компонентов интерфейса
function checkEditorComponentsHealth() {
    if (window.isSafeModeActive) return true;

    const criticalElements = [
        { id: 'contentVisual', name: 'Область редактора (#contentVisual)' },
        { id: 'blogForm', name: 'Форма статьи (#blogForm)' },
        { id: 'title', name: 'Поле заголовка (#title)' }
    ];

    for (let i = 0; i < criticalElements.length; i++) {
        const item = criticalElements[i];
        if (!document.getElementById(item.id)) {
            const missingMsg = (window.t ? window.t('safemode.missing_component_msg', `Отсутствует или не загрузился критический компонент: ${item.name}`, { component: item.name }) : `Отсутствует или не загрузился критический компонент: ${item.name}`);
            enterSafeMode({
                message: missingMsg,
                filename: 'index.php',
                stack: `Health check error: Critical DOM element #${item.id} not found in document.`
            });
            return false;
        }
    }

    if (!window.NPBlogI18n) {
        enterSafeMode({
            message: 'Отсутствует или повреждён модуль локализации (window.NPBlogI18n)',
            filename: 'lang/i18n.js',
            stack: 'Health check error: window.NPBlogI18n is undefined.'
        });
        return false;
    }

    return true;
}

// Проверяем, произошла ли ошибка до загрузки разметки Safe Mode
if (window._pendingSafeModeError) {
    setTimeout(() => {
        enterSafeMode(window._pendingSafeModeError);
    }, 50);
}

// Запускаем проверку компонентов после завершения загрузки страницы
window.addEventListener('load', function() {
    setTimeout(checkEditorComponentsHealth, 400);
});
</script>
