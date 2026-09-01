function loadEditorCustomFonts() {
    fetch('get_custom_fonts.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.fonts.length > 0) {
                let styleTag = document.getElementById('editorCustomFontsStyles');
                if (!styleTag) {
                    styleTag = document.createElement('style');
                    styleTag.id = 'editorCustomFontsStyles';
                    document.head.appendChild(styleTag);
                }

                let fontFaceRules = '';

                data.fonts.forEach(font => {
                    let format = 'truetype';
                    if (font.format === 'woff') format = 'woff';
                    else if (font.format === 'woff2') format = 'woff2';
                    else if (font.format === 'otf') format = 'opentype';

                    fontFaceRules += `
                            @font-face {
                                font-family: '${font.name}';
                                src: url('${font.path}') format('${format}');
                            }
                        `;
                });

                styleTag.textContent = fontFaceRules;
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки шрифтов:', error);
        });
}

// Загружаем шрифты при загрузке страницы
loadEditorCustomFonts();

// Очищаем историю при загрузке страницы
clearHistory();

// Инициализируем историю с пустым состоянием
setTimeout(() => {
    saveToHistory();
    updateUndoRedoButtons();
}, 100);

function setTextColor(color) {
    if (editorMode === 'code') {
        var ta = document.getElementById('content');
        var start = (ta.selectionStart !== undefined && ta.selectionEnd > ta.selectionStart) ? ta.selectionStart : colorInsertStart;
        var end = (ta.selectionEnd !== undefined && ta.selectionEnd > ta.selectionStart) ? ta.selectionEnd : colorInsertEnd;
        var selectedText = ta.value.substring(start, end);
        if (selectedText) {
            var colorSpan = '<span style="color: ' + color + ';">' + selectedText + '</span>';
            ta.value = ta.value.substring(0, start) + colorSpan + ta.value.substring(end);
            ta.selectionStart = start;
            ta.selectionEnd = start + colorSpan.length;
            ta.focus();
            saveToHistory();
        } else {
            var colorSpan = '<span style="color: ' + color + ';"></span>';
            ta.value = ta.value.substring(0, start) + colorSpan + ta.value.substring(start);
            ta.selectionStart = ta.selectionEnd = start + colorSpan.length - 7;
            ta.focus();
            saveToHistory();
        }
    } else {
        if (typeof window.restoreEditorFocus === 'function' && !window.restoreEditorFocus()) return;
        try {
            document.execCommand('styleWithCSS', false, true);
        } catch (e) { }
        document.execCommand('foreColor', false, color);
        var sel = window.getSelection();
        if (sel && sel.rangeCount > 0) {
            savedRange = sel.getRangeAt(0).cloneRange();
        }
        saveToHistory();
    }
}

(function initColorPalette() {
    var presetColors = ['#000000', '#333333', '#666666', '#999999', '#cccccc', '#ffffff', '#ff0000', '#ff6600', '#ff9900', '#ffcc00', '#99cc00', '#00cc00', '#00cccc', '#0066ff', '#0000ff', '#6600cc', '#9900cc', '#cc0099', '#ff0066', '#8b4513', '#a0522d', '#cd853f', '#deb887', '#ff69b4', '#ffc0cb', '#add8e6', '#98fb98', '#f0e68c', '#ffd700', '#ff6347'];
    function fillGrid(gridId) {
        var grid = document.getElementById(gridId);
        if (!grid) return;
        grid.innerHTML = '';
        presetColors.forEach(function (hex) {
            var swatch = document.createElement('span');
            swatch.className = 'color-swatch';
            swatch.style.background = hex;
            swatch.title = hex;
            swatch.setAttribute('data-color', hex);
            grid.appendChild(swatch);
        });
    }
    fillGrid('colorPaletteGridMain');

    function openColorPicker(wrap) {
        document.querySelectorAll('.color-picker-wrap.is-open').forEach(function (w) { if (w !== wrap) w.classList.remove('is-open'); });
        wrap.classList.add('is-open');
    }
    function toggleColorPicker(wrap) {
        var isOpen = wrap.classList.contains('is-open');
        document.querySelectorAll('.color-picker-wrap.is-open').forEach(function (w) { w.classList.remove('is-open'); });
        if (!isOpen) {
            wrap.classList.add('is-open');
        }
    }
    function closeAllColorPickers() {
        var tutOverlay = document.getElementById('tutorialOverlay');
        if (tutOverlay && tutOverlay.classList.contains('show')) return;
        document.querySelectorAll('.color-picker-wrap.is-open').forEach(function (w) { w.classList.remove('is-open'); });
    }
    function applyColorAndClose(hex, wrap) {
        setTextColor(hex);
        wrap.classList.remove('is-open');
        var preview = wrap.querySelector('.color-preview');
        if (preview) preview.style.background = hex;
    }

    // Функция для меню "Прочее"
    window.toggleMoreMenu = function () {
        const wrap = document.getElementById('moreMenuWrap');
        if (!wrap) return;

        const isOpen = wrap.classList.contains('is-open');

        // Закрываем другие открытые меню
        document.querySelectorAll('.color-picker-wrap.is-open, .font-size-picker-wrap.is-open, .font-family-picker-wrap.is-open').forEach(function (w) {
            w.classList.remove('is-open');
        });

        if (!isOpen) {
            wrap.classList.add('is-open');
        } else {
            wrap.classList.remove('is-open');
            // Закрываем подменю
            document.querySelectorAll('.more-menu-item.has-submenu').forEach(function (item) {
                item.classList.remove('submenu-open');
            });
        }
    };

    ['colorPickerWrapMain'].forEach(function (id) {
        var wrap = document.getElementById(id);
        if (!wrap) return;
        var btn = wrap.querySelector('.color-picker-btn');
        var popover = wrap.querySelector('.color-palette-popover');
        var customInput = wrap.querySelector('input[type="color"]');
        if (btn) {
            btn.addEventListener('mousedown', function (e) {
                if (editorMode === 'code') {
                    var ta = document.getElementById('content');
                    colorInsertStart = ta.selectionStart;
                    colorInsertEnd = ta.selectionEnd;
                } else {
                    saveSelection();
                }
            });
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                toggleColorPicker(wrap);
            });
        }
        if (popover) {
            popover.addEventListener('mousedown', function (e) {
                if (e.target.tagName !== 'INPUT') {
                    e.preventDefault();
                }
            });
            popover.addEventListener('click', function (e) {
                e.stopPropagation();
                var swatch = e.target.closest('.color-swatch');
                if (swatch && swatch.dataset.color) applyColorAndClose(swatch.dataset.color, wrap);
            });
        }
        if (customInput) {
            ['change', 'input'].forEach(function (evtType) {
                customInput.addEventListener(evtType, function () {
                    applyColorAndClose(this.value, wrap);
                });
            });
        }
    });
    document.addEventListener('click', closeAllColorPickers);
})();

function applyCustomFontSize(wrapId) {
    var wrap = document.getElementById(wrapId);
    var input = wrap.querySelector('.font-size-custom input[type="number"]');
    var size = input && input.value ? parseInt(input.value, 10) : 0;
    if (size >= 8 && size <= 72) {
        var sizeStr = size + 'px';
        setFontSize(String(size));

        // Обновляем текст кнопки
        const sizeBtn = document.getElementById('fontSizeBtn');
        if (sizeBtn) {
            sizeBtn.textContent = sizeStr;
        }

        input.value = '';
        wrap.classList.remove('is-open');
    } else {
        showNotification('Введите размер от 8 до 72', 'warning');
    }
}
function applyCustomFontFamily(wrapId) {
    var wrap = document.getElementById(wrapId);
    var input = wrap.querySelector('.font-family-custom input[type="text"]');
    var font = input && input.value ? input.value.trim() : '';
    if (font) {
        setFontFamily(font);

        // Обновляем текст кнопки
        const fontBtn = document.getElementById('fontFamilyBtn');
        if (fontBtn) {
            fontBtn.textContent = font;
            fontBtn.style.fontFamily = font;
        }

        input.value = '';
        wrap.classList.remove('is-open');
    } else {
        showNotification('Введите название шрифта', 'warning');
    }
}

(function initFontSizeAndFamilyPopovers() {
    function closeAllFontPopovers() {
        var tutOverlay = document.getElementById('tutorialOverlay');
        if (tutOverlay && tutOverlay.classList.contains('show')) return;
        document.querySelectorAll('.font-size-picker-wrap.is-open, .font-family-picker-wrap.is-open').forEach(function (w) { w.classList.remove('is-open'); });
    }
    function toggleWrap(wrap) {
        var isOpen = wrap.classList.contains('is-open');
        document.querySelectorAll('.font-size-picker-wrap.is-open, .font-family-picker-wrap.is-open').forEach(function (w) { w.classList.remove('is-open'); });
        if (!isOpen) {
            wrap.classList.add('is-open');
        }
    }
    function openWrap(wrap, closeOthers) {
        if (closeOthers) {
            document.querySelectorAll('.font-size-picker-wrap.is-open, .font-family-picker-wrap.is-open').forEach(function (w) { if (w !== wrap) w.classList.remove('is-open'); });
        }
        wrap.classList.add('is-open');
    }

    // Закрытие при клике вне меню
    document.addEventListener('click', function (e) {
        var tutOverlay = document.getElementById('tutorialOverlay');
        if (tutOverlay && tutOverlay.classList.contains('show')) return;
        if (!e.target.closest('.more-menu-wrap')) {
            const moreMenu = document.getElementById('moreMenuWrap');
            if (moreMenu) {
                moreMenu.classList.remove('is-open');
                // Закрываем подменю
                document.querySelectorAll('.more-menu-item.has-submenu').forEach(function (item) {
                    item.classList.remove('submenu-open');
                });
            }
        }
    });

    ['fontSizeWrapMain'].forEach(function (id) {
        var wrap = document.getElementById(id);
        if (!wrap) return;
        var btn = wrap.querySelector('.font-size-picker-btn');
        var popover = wrap.querySelector('.font-size-popover-inner');
        if (btn) {
            btn.addEventListener('mousedown', function () {
                if (editorMode === 'code') {
                    var ta = document.getElementById('content');
                    colorInsertStart = ta.selectionStart;
                    colorInsertEnd = ta.selectionEnd;
                } else {
                    saveSelection();
                }
            });
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                toggleWrap(wrap);
            });
        }
        if (popover) {
            popover.addEventListener('mousedown', function (e) {
                if (e.target.tagName !== 'INPUT') {
                    e.preventDefault();
                }
            });
            popover.addEventListener('click', function (e) {
                e.stopPropagation();
                var item = e.target.closest('.font-size-item[data-size]');
                if (item) {
                    var sizeValue = item.getAttribute('data-size');
                    setFontSize(sizeValue);

                    // Обновляем текст кнопки
                    const sizeBtn = document.getElementById('fontSizeBtn');
                    if (sizeBtn) {
                        sizeBtn.textContent = sizeValue + 'px';
                    }

                    wrap.classList.remove('is-open');
                }
            });
        }
    });
    ['fontFamilyWrapMain'].forEach(function (id) {
        var wrap = document.getElementById(id);
        if (!wrap) return;
        var btn = wrap.querySelector('.font-family-picker-btn');
        var popover = wrap.querySelector('.font-family-popover-inner');
        if (btn) {
            btn.addEventListener('mousedown', function () {
                if (editorMode === 'code') {
                    var ta = document.getElementById('content');
                    colorInsertStart = ta.selectionStart;
                    colorInsertEnd = ta.selectionEnd;
                } else {
                    saveSelection();
                }
            });
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                toggleWrap(wrap);
            });
        }
        if (popover) {
            popover.addEventListener('mousedown', function (e) {
                if (e.target.tagName !== 'INPUT') {
                    e.preventDefault();
                }
            });
            popover.addEventListener('click', function (e) {
                e.stopPropagation();
                var item = e.target.closest('.font-family-item[data-font]');
                if (item) {
                    var fontName = item.getAttribute('data-font');
                    setFontFamily(fontName);

                    // Обновляем текст кнопки
                    const fontBtn = document.getElementById('fontFamilyBtn');
                    if (fontBtn) {
                        fontBtn.textContent = fontName;
                        fontBtn.style.fontFamily = fontName;
                    }

                    wrap.classList.remove('is-open');
                }
            });
        }
    });
    document.addEventListener('click', closeAllFontPopovers);
})();

// Функции для работы со шрифтом
function setFontFamily(font) {
    if (!font) return;
    if (editorMode === 'code') {
        var ta = document.getElementById('content');
        if (!ta) return;
        var start = (ta.selectionStart !== undefined && ta.selectionEnd > ta.selectionStart) ? ta.selectionStart : colorInsertStart;
        var end = (ta.selectionEnd !== undefined && ta.selectionEnd > ta.selectionStart) ? ta.selectionEnd : colorInsertEnd;
        var selectedText = ta.value.substring(start, end);
        if (selectedText) {
            // Применяем к выделенному тексту
            var fontSpan = '<span style="font-family: \'' + font.replace(/'/g, "\\'") + '\';">' + selectedText + '</span>';
            ta.value = ta.value.substring(0, start) + fontSpan + ta.value.substring(end);
            ta.selectionStart = start;
            ta.selectionEnd = start + fontSpan.length;
            ta.focus();
        } else {
            // Вставляем span для последующего текста
            var fontSpan = '<span style="font-family: \'' + font.replace(/'/g, "\\'") + '\';"></span>';
            ta.value = ta.value.substring(0, start) + fontSpan + ta.value.substring(start);
            // Ставим курсор перед закрывающим тегом
            ta.selectionStart = ta.selectionEnd = start + fontSpan.length - 7;
            ta.focus();
        }
        saveToHistory();
    } else {
        if (typeof window.restoreEditorFocus === 'function' && !window.restoreEditorFocus()) return;

        try {
            document.execCommand('styleWithCSS', false, true);
        } catch (e) { }

        // Применяем шрифт через execCommand
        document.execCommand('fontName', false, font);

        var sel = window.getSelection();
        if (sel && sel.rangeCount > 0) {
            savedRange = sel.getRangeAt(0).cloneRange();
        }
        if (typeof updateActiveButtons === 'function') updateActiveButtons();
        saveToHistory();
    }
}

function closeFontFamilyDialog() {
    document.getElementById('fontFamilyDialog').style.display = 'none';
    document.getElementById('customFontFamily').value = '';
}

function setCustomFontFamily() {
    const font = document.getElementById('customFontFamily').value.trim();
    if (font) {
        setFontFamily(font);
        closeFontFamilyDialog();
    } else {
        showNotification('Пожалуйста, введите название шрифта', 'warning');
    }
}

// ——— Работа с якорями и содержанием ———
function addAnchor() {
    if (editorMode !== 'visual') {
        showNotification('Якоря можно добавлять только в визуальном режиме', 'warning');
        return;
    }

    if (typeof window.restoreEditorFocus === 'function' && !window.restoreEditorFocus()) return;
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) {
        showNotification('Пожалуйста, выделите текст для якоря', 'warning');
        return;
    }

    const range = sel.getRangeAt(0);

    // Проверяем, находится ли выделение уже внутри существующего якоря
    let anchorSpan = null;
    let startNode = range.startContainer;
    if (startNode.nodeType === Node.TEXT_NODE) {
        startNode = startNode.parentNode;
    }
    anchorSpan = startNode.closest('span[data-npblog-anchor="true"]');

    if (!anchorSpan) {
        let endNode = range.endContainer;
        if (endNode.nodeType === Node.TEXT_NODE) {
            endNode = endNode.parentNode;
        }
        anchorSpan = endNode.closest('span[data-npblog-anchor="true"]');
    }

    // Если якорь найден, убираем его (unwrap)
    if (anchorSpan) {
        const parent = anchorSpan.parentNode;
        if (parent) {
            const hasOnlyIcon = anchorSpan.innerText.trim() === '⚓' || anchorSpan.textContent.trim() === '⚓';
            if (hasOnlyIcon) {
                parent.removeChild(anchorSpan);
            } else {
                const fragment = document.createDocumentFragment();
                while (anchorSpan.firstChild) {
                    fragment.appendChild(anchorSpan.firstChild);
                }
                parent.replaceChild(fragment, anchorSpan);
            }
            saveToHistory();
            showNotification('Якорь удален', 'info');
            return;
        }
    }

    // Автоматически определяем следующий числовой ID
    const ve = document.getElementById('contentVisual');
    if (!ve) return;

    let nextId = 1;
    while (ve.querySelector('[id="' + nextId + '"]')) {
        nextId++;
    }
    const anchorId = String(nextId);

    const span = document.createElement('span');
    span.id = anchorId;
    span.setAttribute('data-npblog-anchor', 'true');

    if (range.collapsed) {
        span.innerHTML = '⚓'; // Если текст не выделен, вставляем иконку
        range.insertNode(span);
    } else {
        try {
            const contents = range.extractContents();
            span.appendChild(contents);
            range.insertNode(span);
        } catch (e) {
            console.error("Ошибка при создании якоря:", e);
            showNotification('Не удалось создать якорь в этом месте', 'error');
            return;
        }
    }

    // Выделяем добавленный якорь
    const newRange = document.createRange();
    newRange.selectNodeContents(span);
    sel.removeAllRanges();
    sel.addRange(newRange);

    saveToHistory();
    showNotification(`Якорь #${anchorId} успешно добавлен`, 'success');
}

function toggleTocSubmenu(event) {
    event.stopPropagation();

    const button = event.currentTarget;
    const isOpen = button.classList.contains('submenu-open');

    // Закрываем другие подменю
    document.querySelectorAll('.more-menu-item.has-submenu').forEach(btn => {
        if (btn !== button) {
            btn.classList.remove('submenu-open');
        }
    });

    if (!isOpen) {
        button.classList.add('submenu-open');
        loadTocList();
    } else {
        button.classList.remove('submenu-open');
    }
}

function loadTocList() {
    const submenu = document.getElementById('tocSubmenu');
    if (!submenu) return;

    const ve = document.getElementById('contentVisual');
    if (!ve) return;

    // Ищем все элементы с ID
    const anchors = ve.querySelectorAll('[id]');

    if (anchors.length === 0) {
        submenu.innerHTML = '<div class="more-submenu-empty">' + (window.t ? window.t('more_menu.no_anchors', 'Нет якорей в статье') : 'Нет якорей в статье') + '</div>';
        return;
    }

    let html = '';
    anchors.forEach(el => {
        const id = el.id;
        if (!id) return;

        let text = el.innerText.trim();
        // Убираем иконку якоря ⚓ из текста пункта меню, если она там есть
        if (text.startsWith('⚓')) {
            text = text.substring(1).trim();
        }

        if (!text) {
            text = `#${id}`;
        } else {
            if (text.length > 25) {
                text = text.substring(0, 22) + '...';
            }
            text = `${text} (#${id})`;
        }

        html += `
        <div class="toc-menu-item-row">
            <button type="button" class="more-submenu-item" onclick="insertAnchorLink('${escapeHtmlJS(id)}')">${escapeHtml(text)}</button>
            <button type="button" class="toc-delete-btn" onclick="removeAnchorById('${escapeHtmlJS(id)}', event)">×</button>
        </div>`;
    });

    submenu.innerHTML = html;
}

function removeAnchorById(id, event) {
    if (event) {
        event.stopPropagation();
    }

    if (editorMode !== 'visual') {
        showNotification('Якоря можно удалять только в визуальном режиме', 'warning');
        return;
    }

    const ve = document.getElementById('contentVisual');
    if (!ve) return;

    const anchorSpan = ve.querySelector('[id="' + id + '"]');
    if (anchorSpan) {
        const parent = anchorSpan.parentNode;
        if (parent) {
            const hasOnlyIcon = anchorSpan.innerText.trim() === '⚓' || anchorSpan.textContent.trim() === '⚓';
            if (hasOnlyIcon) {
                parent.removeChild(anchorSpan);
            } else {
                const fragment = document.createDocumentFragment();
                while (anchorSpan.firstChild) {
                    fragment.appendChild(anchorSpan.firstChild);
                }
                parent.replaceChild(fragment, anchorSpan);
            }
            saveToHistory();
            showNotification(`Якорь #${id} удален`, 'info');
            loadTocList(); // Обновляем список сразу
        }
    }
}

async function insertAnchorLink(id) {
    if (editorMode !== 'visual') {
        showNotification('Ссылки на якоря можно вставлять только в визуальном режиме', 'warning');
        return;
    }

    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return;
    const range = sel.getRangeAt(0);

    let text = sel.toString().trim();
    if (!text) {
        const ve = document.getElementById('contentVisual');
        const anchorEl = ve ? ve.querySelector('[id="' + id + '"]') : null;
        let anchorText = "";
        if (anchorEl) {
            anchorText = anchorEl.innerText.trim();
            if (anchorText.startsWith('⚓')) {
                anchorText = anchorText.substring(1).trim();
            }
        }

        if (!anchorText) {
            anchorText = "Перейти к разделу";
        }

        text = await showPrompt("Введите текст для ссылки-якоря:", anchorText, "Ссылка-якорь");
        if (text === null) return; // Отмена
        if (!text) text = anchorText;
    }

    const link = document.createElement('a');
    link.href = '#' + id;
    link.innerText = text;

    range.deleteContents();
    range.insertNode(link);

    // Ставим курсор после вставленной ссылки
    const newRange = document.createRange();
    newRange.setStartAfter(link);
    newRange.collapse(true);
    sel.removeAllRanges();
    sel.addRange(newRange);

    saveToHistory();

    // Закрываем выпадающие меню
    const moreMenu = document.getElementById('moreMenuWrap');
    if (moreMenu) moreMenu.classList.remove('is-open');
    document.querySelectorAll('.more-menu-item.has-submenu').forEach(btn => {
        btn.classList.remove('submenu-open');
    });

    showNotification('Ссылка на якорь вставлена', 'success');
}

// Экспортируем в window для inline-событий
window.addAnchor = addAnchor;
window.toggleTocSubmenu = toggleTocSubmenu;
window.loadTocList = loadTocList;
window.insertAnchorLink = insertAnchorLink;
window.removeAnchorById = removeAnchorById;

window.amoledThemeEnabled = false;
function updateAmoledState() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (window.amoledThemeEnabled && isDark) {
        document.documentElement.setAttribute('data-amoled', 'true');
    } else {
        document.documentElement.removeAttribute('data-amoled');
    }
}
window.updateAmoledState = updateAmoledState;
