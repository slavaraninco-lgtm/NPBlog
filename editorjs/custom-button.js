// --- Вставка и редактирование кастомной кнопки со ссылкой ---
let isUpdatingBtnFromRaw = false;
let editingCustomBtnTarget = null;

function openInsertButtonDialog() {
    editingCustomBtnTarget = null;
    const dialogTitle = document.getElementById('customButtonDialogTitle');
    if (dialogTitle) dialogTitle.textContent = window.t ? window.t('modals.btn_title', 'Вставить кнопку со ссылкой') : 'Вставить кнопку со ссылкой';
    const submitBtn = document.getElementById('customButtonSubmitBtn');
    if (submitBtn) submitBtn.innerHTML = '<span>💾</span> <span>' + (window.t ? window.t('modals.btn_submit', 'Вставить кнопку') : 'Вставить кнопку') + '</span>';

    const textInput = document.getElementById('btnTextInput');
    const urlInput = document.getElementById('btnUrlInput');
    const targetInput = document.getElementById('btnTargetInput');
    const defaultText = window.t ? window.t('modals.btn_default_text', 'Перейти на сайт') : 'Перейти на сайт';
    if (textInput) textInput.value = defaultText;
    if (urlInput) urlInput.value = 'https://example.com';
    if (targetInput) targetInput.checked = true;

    applyBtnPreset('editor');

    if (window.Modal) {
        Modal.open('#customButtonDialog');
    } else {
        const dialog = document.getElementById('customButtonDialog');
        if (dialog) {
            dialog.style.display = 'flex';
            dialog.classList.add('show');
        }
    }
    switchBtnTab('gui');
    updateCustomBtnPreview();
}

function openEditCustomButtonDialog(customBtn) {
    if (!customBtn) return;
    editingCustomBtnTarget = customBtn;

    const dialogTitle = document.getElementById('customButtonDialogTitle');
    if (dialogTitle) dialogTitle.textContent = window.t ? window.t('modals.btn_edit_title', 'Редактировать кнопку') : 'Редактировать кнопку';
    const submitBtn = document.getElementById('customButtonSubmitBtn');
    if (submitBtn) submitBtn.innerHTML = '<span>💾</span> <span>' + (window.t ? window.t('modals.btn_save_changes', 'Сохранить изменения') : 'Сохранить изменения') + '</span>';

    const text = customBtn.textContent.trim();
    const url = customBtn.getAttribute('href') || '';
    const target = customBtn.getAttribute('target') === '_blank';
    const styleStr = customBtn.getAttribute('style') || '';

    const textInput = document.getElementById('btnTextInput');
    const urlInput = document.getElementById('btnUrlInput');
    const targetInput = document.getElementById('btnTargetInput');
    if (textInput) textInput.value = text;
    if (urlInput) urlInput.value = url;
    if (targetInput) targetInput.checked = target;

    // Считываем инлайн-стили если они есть
    const bgMatch = styleStr.match(/background\s*:\s*([^;]+)/i);
    if (bgMatch && bgMatch[1]) {
        const bgVal = bgMatch[1].trim();
        const bgText = document.getElementById('btnBgColorText');
        const bgPicker = document.getElementById('btnBgColor');
        if (bgText) bgText.value = bgVal;
        if (bgPicker && bgVal.startsWith('#') && (bgVal.length === 4 || bgVal.length === 7)) {
            bgPicker.value = bgVal;
        }
    }
    const colorMatch = styleStr.match(/color\s*:\s*([^;]+)/i);
    if (colorMatch && colorMatch[1]) {
        const colorVal = colorMatch[1].trim();
        const colorText = document.getElementById('btnTextColorText');
        const colorPicker = document.getElementById('btnTextColor');
        if (colorText) colorText.value = colorVal;
        if (colorPicker && colorVal.startsWith('#') && (colorVal.length === 4 || colorVal.length === 7)) {
            colorPicker.value = colorVal;
        }
    }
    const radiusMatch = styleStr.match(/border-radius\s*:\s*([^;]+)/i);
    if (radiusMatch && radiusMatch[1]) {
        const radiusInput = document.getElementById('btnBorderRadius');
        if (radiusInput) radiusInput.value = radiusMatch[1].trim();
    }
    const paddingMatch = styleStr.match(/padding\s*:\s*([^;]+)/i);
    if (paddingMatch && paddingMatch[1]) {
        const paddingInput = document.getElementById('btnPadding');
        if (paddingInput) paddingInput.value = paddingMatch[1].trim();
    }
    const fontMatch = styleStr.match(/font-size\s*:\s*([^;]+)/i);
    if (fontMatch && fontMatch[1]) {
        const fontInput = document.getElementById('btnFontSize');
        if (fontInput) fontInput.value = fontMatch[1].trim();
    }

    const rawHtmlEl = document.getElementById('btnRawHtml');
    const rawCssEl = document.getElementById('btnRawCss');
    if (rawHtmlEl && rawCssEl) {
        rawHtmlEl.value = customBtn.outerHTML;
        rawCssEl.value = styleStr;
    }

    if (window.Modal) {
        Modal.open('#customButtonDialog');
    } else {
        const dialog = document.getElementById('customButtonDialog');
        if (dialog) {
            dialog.style.display = 'flex';
            dialog.classList.add('show');
        }
    }
    switchBtnTab('gui');
    updateCustomBtnPreview();
}

function closeCustomButtonDialog() {
    if (window.Modal) {
        Modal.close('#customButtonDialog');
    } else {
        const dialog = document.getElementById('customButtonDialog');
        if (dialog) {
            dialog.style.display = 'none';
            dialog.classList.remove('show');
        }
    }
    editingCustomBtnTarget = null;
}

function switchBtnTab(tab) {
    const guiContent = document.getElementById('btnTabGuiContent');
    const codeContent = document.getElementById('btnTabCodeContent');
    const guiBtn = document.getElementById('btnTabGui');
    const codeBtn = document.getElementById('btnTabCode');

    if (!guiContent || !codeContent) return;

    if (tab === 'code') {
        guiContent.style.display = 'none';
        codeContent.style.display = 'block';
        if (guiBtn) guiBtn.classList.remove('active');
        if (codeBtn) codeBtn.classList.add('active');
    } else {
        guiContent.style.display = 'block';
        codeContent.style.display = 'none';
        if (guiBtn) guiBtn.classList.add('active');
        if (codeBtn) codeBtn.classList.remove('active');
    }
}

function setBtnBgPreview(mode) {
    const container = document.getElementById('customBtnPreviewContainer');
    if (!container) return;
    if (mode === 'dark') {
        container.style.background = '#0d1117';
    } else if (mode === 'light') {
        container.style.background = '#ffffff';
    } else if (mode === 'grid') {
        container.style.background = 'repeating-conic-gradient(#222 0% 25%, #333 0% 50%) 50% / 16px 16px';
    }
}

function applyBtnPreset(preset) {
    const bgInput = document.getElementById('btnBgColorText');
    const bgColorPicker = document.getElementById('btnBgColor');
    const textInput = document.getElementById('btnTextColorText');
    const textColorPicker = document.getElementById('btnTextColor');
    const radiusInput = document.getElementById('btnBorderRadius');
    const paddingInput = document.getElementById('btnPadding');
    const fontInput = document.getElementById('btnFontSize');

    if (!bgInput || !textInput) return;

    if (preset === 'editor') {
        bgInput.value = 'rgba(15, 22, 36, 0.72)';
        if (bgColorPicker) bgColorPicker.value = '#0f1624';
        textInput.value = '#f3f4f6';
        if (textColorPicker) textColorPicker.value = '#f3f4f6';
        if (radiusInput) radiusInput.value = '12px';
        if (paddingInput) paddingInput.value = '10px 18px';
        if (fontInput) fontInput.value = '14px';
    } else if (preset === 'gradient') {
        bgInput.value = 'linear-gradient(135deg, rgba(129, 140, 248, 0.95) 0%, #ec4899 100%)';
        textInput.value = '#ffffff';
        if (textColorPicker) textColorPicker.value = '#ffffff';
        if (radiusInput) radiusInput.value = '12px';
        if (paddingInput) paddingInput.value = '12px 28px';
        if (fontInput) fontInput.value = '15px';
    } else if (preset === 'success') {
        bgInput.value = '#10b981';
        if (bgColorPicker) bgColorPicker.value = '#10b981';
        textInput.value = '#ffffff';
        if (textColorPicker) textColorPicker.value = '#ffffff';
        if (radiusInput) radiusInput.value = '12px';
        if (paddingInput) paddingInput.value = '12px 24px';
        if (fontInput) fontInput.value = '15px';
    } else if (preset === 'outline') {
        bgInput.value = 'transparent';
        textInput.value = 'rgba(129, 140, 248, 0.95)';
        if (textColorPicker) textColorPicker.value = '#818cf8';
        if (radiusInput) radiusInput.value = '12px';
        if (paddingInput) paddingInput.value = '10px 22px';
        if (fontInput) fontInput.value = '15px';
    } else if (preset === 'neon') {
        bgInput.value = '#0f172a';
        textInput.value = '#38bdf8';
        if (textColorPicker) textColorPicker.value = '#38bdf8';
        if (radiusInput) radiusInput.value = '30px';
        if (paddingInput) paddingInput.value = '12px 26px';
        if (fontInput) fontInput.value = '15px';
    } else if (preset === 'danger') {
        bgInput.value = '#ef4444';
        if (bgColorPicker) bgColorPicker.value = '#ef4444';
        textInput.value = '#ffffff';
        if (textColorPicker) textColorPicker.value = '#ffffff';
        if (radiusInput) radiusInput.value = '12px';
        if (paddingInput) paddingInput.value = '12px 24px';
        if (fontInput) fontInput.value = '15px';
    }
    updateCustomBtnPreview();
}

function updateCustomBtnPreview() {
    if (isUpdatingBtnFromRaw) return;

    const textInput = document.getElementById('btnTextInput');
    const urlInput = document.getElementById('btnUrlInput');
    const targetInput = document.getElementById('btnTargetInput');
    const bgInput = document.getElementById('btnBgColorText');
    const textColInput = document.getElementById('btnTextColorText');
    const radiusInput = document.getElementById('btnBorderRadius');
    const paddingInput = document.getElementById('btnPadding');
    const fontInput = document.getElementById('btnFontSize');

    if (!textInput || !urlInput) return;

    const text = textInput.value || (window.t ? window.t('modals.btn_text_fallback', 'Текст кнопки') : 'Текст кнопки');
    const url = urlInput.value || '#';
    const targetBlank = targetInput ? targetInput.checked : true;

    const bgColor = bgInput ? (bgInput.value || 'rgba(15, 22, 36, 0.72)') : 'rgba(15, 22, 36, 0.72)';
    const textColor = textColInput ? (textColInput.value || '#f3f4f6') : '#f3f4f6';
    const radius = radiusInput ? (radiusInput.value || '12px') : '12px';
    const padding = paddingInput ? (paddingInput.value || '10px 18px') : '10px 18px';
    const fontSize = fontInput ? (fontInput.value || '14px') : '14px';

    const previewContainer = document.getElementById('customBtnPreviewContainer');
    if (!previewContainer) return;

    let cssRules = [];
    cssRules.push(`display: inline-block`);
    cssRules.push(`padding: ${padding}`);
    cssRules.push(`background: ${bgColor}`);
    cssRules.push(`color: ${textColor}`);
    cssRules.push(`font-size: ${fontSize}`);
    cssRules.push(`text-decoration: none`);
    cssRules.push(`border-radius: ${radius}`);
    cssRules.push(`transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1)`);

    if (bgColor.includes('15, 22, 36') || bgColor === '#0f1624') {
        cssRules.push(`font-weight: 500`);
        cssRules.push(`border: 1px solid rgba(255, 255, 255, 0.18)`);
        cssRules.push(`box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2), inset 0 1px 1px rgba(255, 255, 255, 0.2)`);
        cssRules.push(`backdrop-filter: blur(16px) saturate(190%)`);
        cssRules.push(`-webkit-backdrop-filter: blur(16px) saturate(190%)`);
    } else {
        cssRules.push(`font-weight: 600`);
        if (bgColor.includes('129, 140, 248') || bgColor === '#818cf8' || bgColor === '#6366f1') {
            cssRules.push(`border: 1px solid rgba(255, 255, 255, 0.4)`);
            cssRules.push(`box-shadow: 0 0 24px rgba(129, 140, 248, 0.5), 0 4px 14px rgba(0, 0, 0, 0.25)`);
        } else if (bgColor === 'transparent') {
            cssRules.push(`border: 2px solid ${textColor}`);
            cssRules.push(`box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15)`);
        } else if (bgColor === '#0f172a') {
            cssRules.push(`border: 1px solid #38bdf8`);
            cssRules.push(`box-shadow: 0 0 16px rgba(56, 189, 248, 0.4)`);
        } else {
            cssRules.push(`border: 1px solid rgba(255, 255, 255, 0.25)`);
            cssRules.push(`box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2)`);
        }
    }

    const inlineStyle = cssRules.join('; ');
    const targetAttr = targetBlank ? ' target="_blank" rel="noopener noreferrer"' : '';
    const generatedHtml = `<a href="${url}"${targetAttr} class="custom-blog-btn" style="${inlineStyle}" contenteditable="false" onclick="event.preventDefault()">${text}</a>`;

    previewContainer.innerHTML = generatedHtml;

    const rawHtmlEl = document.getElementById('btnRawHtml');
    const rawCssEl = document.getElementById('btnRawCss');
    if (rawHtmlEl && rawCssEl) {
        const cleanHtml = `<a href="${url}"${targetAttr} class="custom-blog-btn" style="${inlineStyle}" contenteditable="false">${text}</a>`;
        rawHtmlEl.value = cleanHtml;
        rawCssEl.value = inlineStyle;
    }
}

function syncFromRawCode() {
    isUpdatingBtnFromRaw = true;
    const rawHtmlEl = document.getElementById('btnRawHtml');
    const previewContainer = document.getElementById('customBtnPreviewContainer');
    if (rawHtmlEl && previewContainer) {
        previewContainer.innerHTML = rawHtmlEl.value;
        const btn = previewContainer.querySelector('a, button');
        if (btn) {
            btn.onclick = (e) => e.preventDefault();
        }
    }
    isUpdatingBtnFromRaw = false;
}

function insertCustomButtonToEditor() {
    const rawHtmlEl = document.getElementById('btnRawHtml');
    let btnHtml = '';

    if (rawHtmlEl && rawHtmlEl.value.trim() !== '') {
        btnHtml = rawHtmlEl.value.trim();
    } else {
        updateCustomBtnPreview();
        btnHtml = rawHtmlEl ? rawHtmlEl.value.trim() : '';
    }

    if (!btnHtml) {
        if (typeof showNotification === 'function') {
            showNotification(window.t ? window.t('notifications.custom_btn_enter_text_url', 'Пожалуйста, введите текст и ссылку для кнопки') : 'Пожалуйста, введите текст и ссылку для кнопки', 'warning');
        }
        return;
    }

    if (editingCustomBtnTarget) {
        // Редактирование существующей кнопки
        const temp = document.createElement('div');
        temp.innerHTML = btnHtml;
        const newBtn = temp.firstElementChild;
        if (newBtn && editingCustomBtnTarget.parentNode) {
            editingCustomBtnTarget.parentNode.replaceChild(newBtn, editingCustomBtnTarget);
        } else {
            editingCustomBtnTarget.outerHTML = btnHtml;
        }
        editingCustomBtnTarget = null;
        if (typeof hideGlobalMediaOverlay === 'function') {
            hideGlobalMediaOverlay();
        }
        if (typeof showNotification === 'function') {
            showNotification(window.t ? window.t('notifications.custom_btn_updated', 'Кнопка со ссылкой успешно обновлена!') : 'Кнопка со ссылкой успешно обновлена!', 'success');
        }
    } else {
        // Вставка новой кнопки с оберткой медиа-элементов
        const wrappedHtml = wrapMediaWithControls(btnHtml, 'button');

        if (typeof editorMode !== 'undefined' && editorMode === 'code') {
            const ta = document.getElementById('content');
            if (ta) {
                const cursorPos = ta.selectionStart;
                ta.value = ta.value.substring(0, cursorPos) + '\n' + btnHtml + '\n' + ta.value.substring(cursorPos);
            }
        } else {
            if (typeof insertImageBlockAtCaret === 'function') {
                insertImageBlockAtCaret(wrappedHtml);
            } else if (typeof insertHtmlAtCaret === 'function') {
                insertHtmlAtCaret(wrappedHtml);
            } else {
                const ve = document.getElementById('contentVisual');
                if (ve) ve.insertAdjacentHTML('beforeend', wrappedHtml);
            }
        }
        if (typeof showNotification === 'function') {
            showNotification(window.t ? window.t('notifications.custom_btn_inserted', 'Кнопка со ссылкой успешно вставлена!') : 'Кнопка со ссылкой успешно вставлена!', 'success');
        }
    }

    if (typeof saveToHistory === 'function') {
        saveToHistory();
    }

    closeCustomButtonDialog();
}

window.openInsertButtonDialog = openInsertButtonDialog;
window.openEditCustomButtonDialog = openEditCustomButtonDialog;
window.closeCustomButtonDialog = closeCustomButtonDialog;
window.switchBtnTab = switchBtnTab;
window.setBtnBgPreview = setBtnBgPreview;
window.applyBtnPreset = applyBtnPreset;
window.updateCustomBtnPreview = updateCustomBtnPreview;
window.syncFromRawCode = syncFromRawCode;
window.insertCustomButtonToEditor = insertCustomButtonToEditor;

// Expose key dialogue actions to window explicitly
window.addLink = addLink;
window.closeLinkDialog = closeLinkDialog;
window.insertLinkFromDialog = insertLinkFromDialog;
window.showImageUpload = showImageUpload;
window.showMediaDialog = showMediaDialog;
window.closeMediaDialog = closeMediaDialog;
window.insertMedia = insertMedia;
window.openFileUploadDialog = openFileUploadDialog;
