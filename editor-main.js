// ——— Система уведомлений ———
    function showNotification(message, type = 'info', title = '') {
        const container = document.getElementById('notificationContainer');
        if (!container) return;
        
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        
        const titles = {
            success: title || 'Успешно',
            error: title || 'Ошибка',
            warning: title || 'Внимание',
            info: title || 'Информация'
        };
        
        notification.innerHTML = `
            <div class="notification-icon">${icons[type] || icons.info}</div>
            <div class="notification-content">
                <div class="notification-title">${titles[type]}</div>
                <div class="notification-message">${message}</div>
            </div>
            <button class="notification-close" onclick="closeNotification(this)">×</button>
        `;
        
        container.appendChild(notification);
        
        // Анимация появления
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Автоматическое скрытие через 5 секунд
        setTimeout(() => {
            closeNotification(notification.querySelector('.notification-close'));
        }, 5000);
    }
    
    function closeNotification(btn) {
        const notification = btn.closest('.notification');
        if (!notification) return;
        
        notification.classList.remove('show');
        notification.classList.add('hide');
        
        setTimeout(() => {
            notification.remove();
        }, 400);
    }

    let currentEditId = null;
    let editorMode = 'visual'; // 'visual' | 'code'
    let savedRange = null;
    
    // Флаги состояния и защита от потери данных
    let isEditorDirty = false;
    let localDraftSaveTimeout = null;
    let isSavingArticle = false;
    
    // Система истории изменений
    const MAX_HISTORY_STATES = 50;
    let historyStack = [];
    let historyIndex = -1;
    let isRestoringHistory = false;
    let historySaveTimeout = null;
    let lastActionType = null;
    let lastActionTime = 0;
    let cursorMoved = false;

    function getLocalDraftStorageKey() {
        return currentEditId ? `npblog_draft_post_${currentEditId}` : 'npblog_draft_new_post';
    }

    function markEditorDirty() {
        isEditorDirty = true;
        scheduleLocalDraftSave();
    }

    function scheduleLocalDraftSave() {
        clearTimeout(localDraftSaveTimeout);
        localDraftSaveTimeout = setTimeout(() => {
            saveLocalDraftNow();
        }, 1200);
    }

    function saveLocalDraftNow() {
        try {
            const titleInput = document.getElementById('title');
            const ve = document.getElementById('contentVisual');
            const ta = document.getElementById('content');
            if (!titleInput || (!ve && !ta)) return;
            
            const title = titleInput.value.trim();
            const content = editorMode === 'visual' ? (ve ? ve.innerHTML : '') : (ta ? ta.value : '');
            
            const hasText = title.length > 0 || (content.length > 0 && content !== '<br>' && content !== '<div><br></div>');
            if (!hasText) {
                localStorage.removeItem(getLocalDraftStorageKey());
                return;
            }
            
            const draft = {
                title: title,
                content: content,
                mode: editorMode,
                currentEditId: currentEditId,
                timestamp: Date.now()
            };
            localStorage.setItem(getLocalDraftStorageKey(), JSON.stringify(draft));
        } catch (e) {
            console.warn('Не удалось сохранить локальный черновик:', e);
        }
    }

    function clearLocalDraft() {
        isEditorDirty = false;
        try {
            localStorage.removeItem(getLocalDraftStorageKey());
            localStorage.removeItem('npblog_draft_new_post');
        } catch (e) {}
    }

    function checkLocalDraftOnStartup() {
        try {
            if (currentEditId) return;
            const raw = localStorage.getItem('npblog_draft_new_post');
            if (!raw) return;
            const draft = JSON.parse(raw);
            if (!draft || (!draft.title && !draft.content)) return;
            
            const title = document.getElementById('title')?.value?.trim();
            const ve = document.getElementById('contentVisual');
            const ta = document.getElementById('content');
            const currentContent = editorMode === 'visual' ? (ve?.innerHTML?.trim() || '') : (ta?.value?.trim() || '');
            
            if (!title && (!currentContent || currentContent === '<br>' || currentContent === '<div><br></div>')) {
                const dateObj = new Date(draft.timestamp);
                const timeStr = dateObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const dateStr = dateObj.toLocaleDateString();
                showDraftRestoreToast(draft, `${dateStr} ${timeStr}`);
            }
        } catch (e) {
            console.error('Ошибка проверки локального черновика:', e);
        }
    }

    function showDraftRestoreToast(draft, timeFormatted) {
        const container = document.getElementById('notificationContainer');
        if (!container) return;
        
        const toast = document.createElement('div');
        toast.className = 'notification info draft-restore-toast';
        toast.style.cssText = 'display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border-left: 4px solid #2196F3; max-width: 480px; box-shadow: 0 4px 16px rgba(0,0,0,0.3);';
        
        const textSpan = document.createElement('span');
        textSpan.style.flex = '1';
        const displayTitle = draft.title ? (draft.title.length > 30 ? draft.title.substring(0, 30) + '...' : draft.title) : 'Без названия';
        textSpan.innerHTML = `📝 Несохранённый черновик (${timeFormatted}): <b>${escapeHtml(displayTitle)}</b>`;
        
        const actionsDiv = document.createElement('div');
        actionsDiv.style.display = 'flex';
        actionsDiv.style.gap = '8px';
        actionsDiv.style.alignItems = 'center';
        
        const restoreBtn = document.createElement('button');
        restoreBtn.type = 'button';
        restoreBtn.textContent = 'Восстановить';
        restoreBtn.style.cssText = 'background: #2196F3; color: white; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: bold;';
        restoreBtn.onclick = () => {
            if (draft.title) document.getElementById('title').value = draft.title;
            const ve = document.getElementById('contentVisual');
            const ta = document.getElementById('content');
            if (draft.mode === 'code') {
                setMode('code');
                if (ta) ta.value = draft.content || '';
            } else {
                setMode('visual');
                if (ve) {
                    ve.innerHTML = draft.content || '';
                    wrapExistingEditorImages();
                }
            }
            markEditorDirty();
            showNotification('Локальный черновик успешно восстановлен!', 'success');
            toast.remove();
        };
        
        const dismissBtn = document.createElement('button');
        dismissBtn.type = 'button';
        dismissBtn.textContent = '×';
        dismissBtn.style.cssText = 'background: transparent; border: none; color: inherit; cursor: pointer; font-size: 18px; line-height: 1; padding: 0 4px;';
        dismissBtn.onclick = () => {
            localStorage.removeItem('npblog_draft_new_post');
            toast.remove();
        };
        
        actionsDiv.appendChild(restoreBtn);
        actionsDiv.appendChild(dismissBtn);
        toast.appendChild(textSpan);
        toast.appendChild(actionsDiv);
        container.appendChild(toast);
    }

    window.addEventListener('beforeunload', function(e) {
        if (isEditorDirty && typeof hasEditorContent === 'function' && hasEditorContent()) {
            e.preventDefault();
            e.returnValue = 'У вас есть несохраненные изменения в статье. Вы уверены, что хотите покинуть страницу?';
            return e.returnValue;
        }
    });
    
    // Загружаем пользовательские шрифты при инициализации редактора
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
    
    let linkInsertStart = 0;
    let linkInsertEnd = 0;
    let colorInsertStart = 0;
    let colorInsertEnd = 0;

    function saveSelection() {
        const ve = document.getElementById('contentVisual');
        if (!ve || (document.activeElement !== ve && !ve.contains(document.activeElement))) return;
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        const range = sel.getRangeAt(0);
        if (ve.contains(range.commonAncestorContainer)) {
            savedRange = range.cloneRange();
        }
    }

    // Стабильная логика тулбара: не даём кнопкам забирать фокус у редактора.
    // Это сохраняет каретку/выделение и делает execCommand предсказуемым.
    (function initToolbarFocusGuard() {
        var bar = document.getElementById('formatBarRow');
        if (!bar) return;
        bar.addEventListener('mousedown', function(e) {
            var btn = e.target.closest('button');
            if (!btn) return;
            // Не ломаем клики внутри поповеров/диалогов
            if (e.target.closest('.font-size-popover, .font-family-popover, .color-palette-popover')) return;
            e.preventDefault();
            if (editorMode === 'visual') {
                var ve = document.getElementById('contentVisual');
                if (ve) ve.focus();
            } else {
                var ta = document.getElementById('content');
                if (ta) ta.focus();
            }
        }, true);
    })();

    // Надёжно обновляем savedRange при наборе/кликах внутри редактора (пробел/Enter/мышь и т.п.)
    (function initVisualSelectionTracking() {
        var ve = document.getElementById('contentVisual');
        if (!ve) return;
        ['mouseup','keyup','input','click','touchend','compositionend'].forEach(function(evt) {
            ve.addEventListener(evt, function() {
                if (editorMode === 'visual') saveSelection();
            }, true);
        });
    })();

    function insertHtmlAtCursor(html) {
        var ve = document.getElementById('contentVisual');
        if (ve) ve.focus();
        
        // Restore selection if we have one
        if (savedRange) {
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(savedRange);
        }
        
        let sel = window.getSelection();
        if (sel && sel.rangeCount > 0) {
            let range = sel.getRangeAt(0);
            range.deleteContents();
            
            let el = document.createElement("div");
            el.innerHTML = html;
            let frag = document.createDocumentFragment(), node, lastNode;
            while ( (node = el.firstChild) ) {
                lastNode = frag.appendChild(node);
            }
            range.insertNode(frag);
            
            if (lastNode) {
                range = range.cloneRange();
                range.setStartAfter(lastNode);
                range.collapse(true);
                sel.removeAllRanges();
                sel.addRange(range);
                saveSelection();
            }
            saveToHistory();
        }
    }

    function formatHTML(html) {
        if (!html) return '';
        
        // 1. Выделяем блоки <pre>, чтобы сохранить их внутреннее форматирование/пробелы
        let preBlocks = [];
        let formatted = html.replace(/<pre[^>]*>[\s\S]*?<\/pre>/gi, function(match) {
            preBlocks.push(match);
            return '___PRE_PLACEHOLDER_' + (preBlocks.length - 1) + '___';
        });
        
        const blockTags = [
            'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 
            'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 
            'blockquote', 'section', 'article', 'header', 'footer', 'hr'
        ];
        
        // Очищаем от старых переносов строк
        formatted = formatted.replace(/\r/g, '');
        
        // Удаляем лишние пробелы вокруг блочных тегов, чтобы сбросить структуру
        blockTags.forEach(tag => {
            const closeRegex = new RegExp('\\s*</' + tag + '>\\s*', 'gi');
            formatted = formatted.replace(closeRegex, '</' + tag + '>');
            
            const openRegex = new RegExp('\\s*<(' + tag + ')((\\s+[^>]*?>)|>)\\s*', 'gi');
            formatted = formatted.replace(openRegex, '<$1$2');
        });
        
        // Вставляем переносы строк перед и после блочных тегов
        blockTags.forEach(tag => {
            const openRegex = new RegExp('<(' + tag + ')((\\s+[^>]*?>)|>)', 'gi');
            formatted = formatted.replace(openRegex, '\n<$1$2');
            
            const closeRegex = new RegExp('</(' + tag + ')>', 'gi');
            formatted = formatted.replace(closeRegex, '</$1>\n');
        });
        
        // Форматируем одиночные теги (hr, br)
        formatted = formatted.replace(/<hr(\s+[^>]*?>| >|>)/gi, '\n<hr$1\n');
        formatted = formatted.replace(/<br(\s*\/)?>/gi, '<br$1>\n');
        
        // Разбиваем на строки и вычисляем вложенность
        let lines = formatted.split('\n');
        let pad = 0;
        let result = [];
        
        for (let i = 0; i < lines.length; i++) {
            let line = lines[i].trim();
            if (!line) continue;
            
            let startsWithClosing = false;
            for (let j = 0; j < blockTags.length; j++) {
                if (line.toLowerCase().startsWith('</' + blockTags[j])) {
                    startsWithClosing = true;
                    break;
                }
            }
            
            let startsWithOpening = false;
            if (!startsWithClosing) {
                for (let j = 0; j < blockTags.length; j++) {
                    let tag = blockTags[j];
                    let reg = new RegExp('^<' + tag + '(\\s+|>)', 'i');
                    if (reg.test(line)) {
                        let hasClose = new RegExp('</' + tag + '>$', 'i').test(line);
                        if (!hasClose && tag !== 'hr') {
                            startsWithOpening = true;
                        }
                        break;
                    }
                }
            }
            
            if (startsWithClosing) {
                pad = Math.max(0, pad - 1);
            }
            
            result.push('    '.repeat(pad) + line);
            
            if (startsWithOpening) {
                pad++;
            }
        }
        
        let finalHtml = result.join('\n');
        
        // Восстанавливаем сохраненные блоки <pre>
        for (let i = 0; i < preBlocks.length; i++) {
            finalHtml = finalHtml.replace('___PRE_PLACEHOLDER_' + i + '___', preBlocks[i]);
        }
        
        return finalHtml.trim();
    }

    function cleanContentForSave(html) {
        // Создаем временный контейнер для очистки HTML
        var temp = document.createElement('div');
        temp.innerHTML = html;
        
        // Удаляем все элементы интерфейса редактора
        var elementsToRemove = temp.querySelectorAll(
            '.image-toolbar, ' +
            '.image-align-dropdown, ' +
            '.image-size-indicator, ' +
            '.image-resize-handle, ' +
            '.blog-image-overlay, ' +
            '.column-resizer'
        );
        elementsToRemove.forEach(function(el) {
            el.parentNode.removeChild(el);
        });
        
        // Удаляем атрибуты data-image-id, data-media-id, data-media-type
        var wraps = temp.querySelectorAll('[data-image-id], [data-media-id], [data-media-type]');
        wraps.forEach(function(el) {
            el.removeAttribute('data-image-id');
            el.removeAttribute('data-media-id');
            el.removeAttribute('data-media-type');
        });
        
        // Очистка таблиц: удаляем атрибуты редактирования и состояния ресайзера
        var tables = temp.querySelectorAll('table[data-resizers-added]');
        tables.forEach(function(table) {
            table.removeAttribute('data-resizers-added');
        });
        
        var editableCells = temp.querySelectorAll('[contenteditable]');
        editableCells.forEach(function(el) {
            el.removeAttribute('contenteditable');
        });
        
        // Удаляем классы selected
        var selected = temp.querySelectorAll('.selected');
        selected.forEach(function(el) {
            el.classList.remove('selected');
        });
        
        // Убираем служебные ZWS (\u200B) и форматируем HTML
        let cleanedHtml = temp.innerHTML.replace(/\u200B/g, '');
        cleanedHtml = cleanedHtml.replace(/(?:[?&]|&amp;)t=\d+/g, '');
        return formatHTML(cleanedHtml);
    }

    function setMode(mode) {
        editorMode = mode;
        const ta = document.getElementById('content');
        const ve = document.getElementById('contentVisual');
        const visualBtn = document.getElementById('modeVisualBtn');
        const codeBtn = document.getElementById('modeCodeBtn');
        
        if (mode === 'visual') {
            ve.contentEditable = 'true';
            if (window.enableMarkdown) {
                ve.innerHTML = parseMarkdownToHtml(ta.value);
            } else {
                // sync from code -> visual
                if (ta.style.display !== 'none') {
                    ve.innerHTML = ta.value;
                    wrapExistingEditorImages();
                    addColumnResizers(); // Добавляем ручки изменения размера столбцов
                }
            }
            ve.style.display = '';
            ta.style.display = 'none';
            visualBtn.classList.add('active');
            codeBtn.classList.remove('active');
        } else {
            hideGlobalMediaOverlay();
            ve.contentEditable = 'true';
            if (window.enableMarkdown) {
                if (ve.style.display !== 'none') {
                    ta.value = convertHtmlToMarkdown(ve.innerHTML);
                }
            } else {
                // sync from visual -> code - очищаем от элементов интерфейса
                if (ve.style.display !== 'none') {
                    ta.value = cleanContentForSave(ve.innerHTML);
                }
            }
            ta.style.display = '';
            ve.style.display = 'none';
            codeBtn.classList.add('active');
            visualBtn.classList.remove('active');
        }
    }

    const toggleState = { b: false, i: false, u: false, s: false };

    function setBtnActive(id, active) {
        const btn = document.getElementById(id);
        if (!btn) return;
        if (active) btn.classList.add('active'); else btn.classList.remove('active');
    }

    function updateActiveButtons() {
        if (editorMode !== 'visual') return;
        const ve = document.getElementById('contentVisual');
        const sel = window.getSelection();
        // Не подсвечиваем кнопки, если выделение/каретка не в поле статьи
        if (!ve || !sel || sel.rangeCount === 0) {
            ['btn-bold','btn-italic','btn-underline','btn-strike','btn-sup','btn-sub','btn-h2'].forEach(function(id){ setBtnActive(id, false); });
            return;
        }
        const r = sel.getRangeAt(0);
        if (!ve.contains(r.commonAncestorContainer)) {
            ['btn-bold','btn-italic','btn-underline','btn-strike','btn-sup','btn-sub','btn-h2'].forEach(function(id){ setBtnActive(id, false); });
            return;
        }
        
        const node = r.startContainer;
        
        const isBold = !!isFormatApplied(node, 'B') || !!isFormatApplied(node, 'STRONG');
        const isItalic = !!isFormatApplied(node, 'I') || !!isFormatApplied(node, 'EM');
        const isUnderline = !!isFormatApplied(node, 'U');
        const isStrike = !!isFormatApplied(node, 'S') || !!isFormatApplied(node, 'STRIKE') || !!isFormatApplied(node, 'DEL');
        const isSup = !!isFormatApplied(node, 'SUP');
        const isSub = !!isFormatApplied(node, 'SUB');
        const isH2 = !!isFormatApplied(node, 'H2');

        // верхняя панель
        setBtnActive('btn-bold', isBold);
        setBtnActive('btn-italic', isItalic);
        setBtnActive('btn-underline', isUnderline);
        setBtnActive('btn-strike', isStrike);
        setBtnActive('btn-sup', isSup);
        setBtnActive('btn-sub', isSub);
        setBtnActive('btn-h2', isH2);

        // Находим текущий примененный шрифт и размер
        let fontName = '';
        let fontSize = '';
        
        let checkNode = node;
        while (checkNode && checkNode !== ve) {
            if (checkNode.nodeType === Node.ELEMENT_NODE) {
                if (!fontName && checkNode.style.fontFamily) {
                    fontName = checkNode.style.fontFamily.split(',')[0].replace(/['"]/g, '').trim();
                }
                if (!fontSize && checkNode.style.fontSize) {
                    fontSize = checkNode.style.fontSize;
                }
            }
            checkNode = checkNode.parentNode;
        }
        
        if (!fontName) fontName = 'Arial';
        if (!fontSize) fontSize = '14px';
        
        const fontBtn = document.getElementById('fontFamilyBtn');
        if (fontBtn) {
            fontBtn.textContent = fontName;
            fontBtn.style.fontFamily = fontName;
        }
        
        const sizeBtn = document.getElementById('fontSizeBtn');
        if (sizeBtn) {
            sizeBtn.textContent = fontSize;
        }
    }

    // Теги форматирования, которые нужно «покидать» при выключении режима
    var FORMAT_TAGS = {
        bold: ['B','STRONG'],
        italic: ['I','EM'],
        underline: ['U'],
        strikeThrough: ['S','STRIKE','DEL'],
        superscript: ['SUP'],
        subscript: ['SUB']
    };

    /**
     * При выключении inline-формата на collapsed каретке:
     *  - Если форматирующий тег пуст / содержит только <br> (новая строка после Enter)
     *    → полностью убираем обёртку (unwrap), каретка остаётся на той же строке.
     *  - Иначе (текст + пробел) → вставляем ZWS после тега и ставим туда каретку.
     */
    function escapeFormatNode(cmd, ve) {
        var tags = FORMAT_TAGS[cmd];
        if (!tags) return;
        var sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        var range = sel.getRangeAt(0);
        if (!range.collapsed) return;

        // Ищем ближайший форматирующий предок
        var node = range.startContainer;
        if (node.nodeType === Node.TEXT_NODE) node = node.parentNode;
        var formatEl = null;
        while (node && node !== ve) {
            if (node.nodeType === Node.ELEMENT_NODE && tags.indexOf(node.tagName) !== -1) {
                formatEl = node;
                break;
            }
            node = node.parentNode;
        }
        if (!formatEl) return;

        // Проверяем, пустой ли тег (только пробелы/ZWS и/или <br>)
        var text = formatEl.textContent.replace(/[\u200B\s]/g, '');
        var isEmpty = text.length === 0;

        if (isEmpty) {
            // Unwrap: заменяем <b><br></b> на просто <br>
            var parent = formatEl.parentNode;
            var br = formatEl.querySelector('br');
            if (!br) br = document.createElement('br');
            parent.insertBefore(br, formatEl);
            parent.removeChild(formatEl);
            // Ставим каретку перед <br> (на эту строку)
            var newRange = document.createRange();
            newRange.setStartBefore(br);
            newRange.collapse(true);
            sel.removeAllRanges();
            sel.addRange(newRange);
        } else {
            // Вставляем ZWS после тега и ставим туда каретку
            var zws = document.createTextNode('\u200B');
            formatEl.parentNode.insertBefore(zws, formatEl.nextSibling);
            var newRange = document.createRange();
            newRange.setStart(zws, 1);
            newRange.collapse(true);
            sel.removeAllRanges();
            sel.addRange(newRange);
        }
    }

    function isFormatApplied(node, tag) {
        const tagName = tag.toUpperCase();
        let current = node;
        while (current && current.id !== 'contentVisual') {
            if (current.nodeType === Node.ELEMENT_NODE && current.tagName.toUpperCase() === tagName) {
                return current;
            }
            current = current.parentNode;
        }
        return null;
    }

    function toggleInlineFormat(tag) {
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        
        let range = sel.getRangeAt(0);
        const existingNode = isFormatApplied(range.commonAncestorContainer, tag);
        
        if (existingNode) {
            const parent = existingNode.parentNode;
            while (existingNode.firstChild) {
                parent.insertBefore(existingNode.firstChild, existingNode);
            }
            parent.removeChild(existingNode);
        } else {
            const el = document.createElement(tag);
            if (range.collapsed) {
                el.innerHTML = '\u200B';
                range.insertNode(el);
                range.selectNodeContents(el);
                range.collapse(false);
                sel.removeAllRanges();
                sel.addRange(range);
            } else {
                try {
                    const contents = range.extractContents();
                    el.appendChild(contents);
                    range.insertNode(el);
                    sel.removeAllRanges();
                    const newRange = document.createRange();
                    newRange.selectNodeContents(el);
                    sel.addRange(newRange);
                } catch(e) {
                    console.error("Selection crosses block boundaries", e);
                }
            }
        }
    }

    function toggleBlockFormat(tag) {
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        const range = sel.getRangeAt(0);
        
        let node = range.startContainer;
        let blockNode = null;
        const blockTags = ['P', 'H1', 'H2', 'H3', 'DIV'];
        while (node && node.id !== 'contentVisual') {
            if (node.nodeType === 1 && blockTags.includes(node.tagName.toUpperCase())) {
                blockNode = node;
                break;
            }
            node = node.parentNode;
        }
        
        if (blockNode) {
            const targetTag = tag.toUpperCase();
            if (blockNode.tagName.toUpperCase() === targetTag) {
                const p = document.createElement('p');
                p.innerHTML = blockNode.innerHTML;
                blockNode.parentNode.replaceChild(p, blockNode);
                
                // Перемещаем каретку внутрь нового абзаца
                const newRange = document.createRange();
                newRange.selectNodeContents(p);
                newRange.collapse(false);
                sel.removeAllRanges();
                sel.addRange(newRange);
            } else {
                const h = document.createElement(tag);
                h.innerHTML = blockNode.innerHTML;
                blockNode.parentNode.replaceChild(h, blockNode);
                
                // Перемещаем каретку внутрь нового заголовка
                const newRange = document.createRange();
                newRange.selectNodeContents(h);
                newRange.collapse(false);
                sel.removeAllRanges();
                sel.addRange(newRange);
            }
        } else {
            const block = document.createElement(tag);
            if (!range.collapsed) {
                try {
                    const contents = range.extractContents();
                    block.appendChild(contents);
                    range.insertNode(block);
                    
                    // Выделяем содержимое нового блока
                    const newRange = document.createRange();
                    newRange.selectNodeContents(block);
                    sel.removeAllRanges();
                    sel.addRange(newRange);
                } catch(e) {
                    console.error("Extract contents failed", e);
                }
            } else {
                block.innerHTML = '<br>';
                range.insertNode(block);
                
                // Ставим каретку внутрь созданного блока перед <br>
                const newRange = document.createRange();
                newRange.setStart(block, 0);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
            }
        }
    }

    function formatText(tag) {
        const ta = document.getElementById('content');
        const ve = document.getElementById('contentVisual');
        
        if (window.enableMarkdown && editorMode === 'code') {
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            const selectedText = ta.value.substring(start, end);
            const beforeText = ta.value.substring(0, start);
            const afterText = ta.value.substring(end);
            
            let formattedText = selectedText;
            let newCursorStart = start;
            let newCursorEnd = end;
            
            if (tag === 'b') {
                formattedText = `**${selectedText}**`;
                newCursorStart += 2;
                newCursorEnd += 2;
            } else if (tag === 'i') {
                formattedText = `*${selectedText}*`;
                newCursorStart += 1;
                newCursorEnd += 1;
            } else if (tag === 's') {
                formattedText = `~~${selectedText}~~`;
                newCursorStart += 2;
                newCursorEnd += 2;
            } else if (tag === 'h2') {
                formattedText = `\n## ${selectedText}\n`;
                newCursorStart += 4;
                newCursorEnd += 4;
            }
            
            ta.value = beforeText + formattedText + afterText;
            ta.setSelectionRange(newCursorStart, newCursorEnd);
            saveToHistory();
            return;
        }
        
        if (editorMode === 'code') {
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            const selectedText = ta.value.substring(start, end);
            const beforeText = ta.value.substring(0, start);
            const afterText = ta.value.substring(end);
            const formattedText = tag === 'h2' ? `<${tag}>${selectedText}</${tag}>\n` : `<${tag}>${selectedText}</${tag}>`;
            ta.value = beforeText + formattedText + afterText;
            ta.setSelectionRange(start + tag.length + 2, start + tag.length + 2 + selectedText.length);
            saveToHistory();
        } else {
            if (ve) ve.focus();
            if (tag === 'h2') {
                toggleBlockFormat('h2');
            } else {
                toggleInlineFormat(tag);
            }
            saveSelection();
            updateActiveButtons();
            saveToHistory();
        }
    }

    function alignText(side) {
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            const selectedText = ta.value.substring(start, end);
            const before = ta.value.substring(0, start);
            const after = ta.value.substring(end);
            const html = `<div style="text-align: ${side};">${selectedText || '&nbsp;'}</div>`;
            ta.value = before + html + after;
        } else {
            const ve = document.getElementById('contentVisual');
            if (ve) ve.focus();
            
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;
            const range = sel.getRangeAt(0);
            
            let node = range.startContainer;
            let blockNode = null;
            const blockTags = ['P', 'H1', 'H2', 'H3', 'DIV'];
            while (node && node.id !== 'contentVisual') {
                if (node.nodeType === 1 && blockTags.includes(node.tagName.toUpperCase())) {
                    blockNode = node;
                    break;
                }
                node = node.parentNode;
            }
            
            if (blockNode) {
                blockNode.style.textAlign = side;
            } else {
                const div = document.createElement('div');
                div.style.textAlign = side;
                if (!range.collapsed) {
                    try {
                        const contents = range.extractContents();
                        div.appendChild(contents);
                        range.insertNode(div);
                    } catch(e) {
                        console.error("Extract failed", e);
                    }
                } else {
                    div.innerHTML = '<br>';
                    range.insertNode(div);
                }
            }
            
            saveSelection();
            saveToHistory();
        }
    }

    // Кастомный обработчик Enter для стабильной структуры абзацев (разделение на блоки <p>)
    document.getElementById('contentVisual').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.defaultPrevented) {
            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) return;
            const node = sel.anchorNode;
            
            // Если мы внутри списка или преформатированного текста, пусть браузер обрабатывает сам
            let inListOrPre = false;
            let curr = node;
            while(curr && curr.id !== 'contentVisual') {
                if(curr.tagName === 'LI' || curr.tagName === 'PRE') { 
                    inListOrPre = true; 
                    break; 
                }
                curr = curr.parentNode;
            }
            if (inListOrPre) return; 

            e.preventDefault();
            
            const range = sel.getRangeAt(0);
            
            // Находим ближайший блочный элемент (P, H1-H6, DIV, etc.)
            let blockNode = range.startContainer;
            const blockTags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'DIV', 'BLOCKQUOTE'];
            while (blockNode && blockNode.id !== 'contentVisual') {
                if (blockNode.nodeType === 1 && blockTags.includes(blockNode.tagName.toUpperCase())) {
                    break;
                }
                blockNode = blockNode.parentNode;
            }
            
            // Если мы не нашли блочный элемент, обернем текущее содержимое в <p>
            if (!blockNode || blockNode.id === 'contentVisual') {
                document.execCommand('formatBlock', false, 'p');
                
                // Переполучим blockNode
                blockNode = sel.anchorNode;
                while (blockNode && blockNode.id !== 'contentVisual') {
                    if (blockNode.nodeType === 1 && blockTags.includes(blockNode.tagName.toUpperCase())) {
                        break;
                    }
                    blockNode = blockNode.parentNode;
                }
            }
            
            if (blockNode && blockNode.id !== 'contentVisual') {
                range.deleteContents();
                
                // Разделяем блок
                const afterRange = document.createRange();
                afterRange.setStart(range.endContainer, range.endOffset);
                afterRange.setEndAfter(blockNode.lastChild || blockNode);
                
                let afterFragment;
                try {
                    afterFragment = afterRange.extractContents();
                } catch(err) {
                    afterFragment = document.createDocumentFragment();
                }
                
                // Создаем новый абзац <p>
                const newP = document.createElement('p');
                
                // Наполняем его
                if (afterFragment.childNodes.length === 0 || (afterFragment.childNodes.length === 1 && afterFragment.textContent === '')) {
                    newP.innerHTML = '<br>';
                } else {
                    newP.appendChild(afterFragment);
                }
                
                // Вставляем новый абзац после текущего блока
                blockNode.parentNode.insertBefore(newP, blockNode.nextSibling);
                
                // Ставим каретку в начало нового абзаца
                const newRange = document.createRange();
                newRange.setStart(newP, 0);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
                
                // Очищаем старый блок, если он пуст
                if (blockNode.textContent.trim() === '' && !blockNode.querySelector('img, video, audio, iframe')) {
                    blockNode.innerHTML = '<br>';
                }
            } else {
                // В крайнем случае вставляем <p><br></p> в позицию каретки
                const newP = document.createElement('p');
                newP.innerHTML = '<br>';
                range.insertNode(newP);
                const newRange = document.createRange();
                newRange.setStart(newP, 0);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
            }
            
            saveSelection();
            saveToHistory();
        }
    });

    // Получить текущие начальное и конечное смещения выделения в виде символьных индексов относительно container
    function getSelectionOffsets(container) {
        if (!container) return { start: 0, end: 0 };
        const sel = window.getSelection();
        if (!sel || !sel.rangeCount) return { start: 0, end: 0 };
        const range = sel.getRangeAt(0);
        
        if (!container.contains(range.commonAncestorContainer)) {
            return { start: 0, end: 0 };
        }
        
        let start = 0;
        let end = 0;
        
        const preNavigator = document.createNodeIterator(container, NodeFilter.SHOW_TEXT);
        let currentNode;
        while ((currentNode = preNavigator.nextNode())) {
            if (currentNode === range.startContainer) {
                start += range.startOffset;
            } else if (range.startContainer !== currentNode) {
                if (currentNode.compareDocumentPosition(range.startContainer) & Node.DOCUMENT_POSITION_PRECEDING) {
                    // startContainer is after currentNode
                } else {
                    start += currentNode.length;
                }
            }
            
            if (currentNode === range.endContainer) {
                end += range.endOffset;
            } else if (range.endContainer !== currentNode) {
                if (currentNode.compareDocumentPosition(range.endContainer) & Node.DOCUMENT_POSITION_PRECEDING) {
                    // endContainer is after currentNode
                } else {
                    end += currentNode.length;
                }
            }
        }
        
        return { start, end };
    }

    // Восстановить выделение по символьным смещениям относительно container
    function setSelectionOffsets(container, start, end) {
        if (!container) return;
        const sel = window.getSelection();
        if (!sel) return;
        sel.removeAllRanges();
        
        const range = document.createRange();
        let charIndex = 0;
        let startNode = null;
        let startOffset = 0;
        let endNode = null;
        let endOffset = 0;
        
        const preNavigator = document.createNodeIterator(container, NodeFilter.SHOW_TEXT);
        let currentNode;
        
        while ((currentNode = preNavigator.nextNode())) {
            const nodeLength = currentNode.length;
            
            if (!startNode && charIndex + nodeLength >= start) {
                startNode = currentNode;
                startOffset = start - charIndex;
            }
            
            if (!endNode && charIndex + nodeLength >= end) {
                endNode = currentNode;
                endOffset = end - charIndex;
            }
            
            charIndex += nodeLength;
        }
        
        if (!startNode) {
            startNode = container;
            startOffset = 0;
        }
        if (!endNode) {
            endNode = container;
            endOffset = 0;
        }
        
        try {
            range.setStart(startNode, startOffset);
            range.setEnd(endNode, endOffset);
            sel.addRange(range);
        } catch (e) {
            console.error('Error restoring selection offsets:', e);
        }
    }

    // Функции для работы с историей изменений
    function saveToHistory(force = true) {
        if (isRestoringHistory) return;
        
        const ve = document.getElementById('contentVisual');
        const ta = document.getElementById('content');
        if (!ve || !ta) return;
        
        const currentState = {
            visual: ve.innerHTML,
            code: ta.value,
            mode: editorMode,
            visualSelection: getSelectionOffsets(ve),
            codeSelection: { start: ta.selectionStart, end: ta.selectionEnd }
        };
        
        if (force) {
            lastActionType = 'formatting';
            cursorMoved = false;
            // Удаляем все состояния после текущего индекса
            historyStack = historyStack.slice(0, historyIndex + 1);
            historyStack.push(currentState);
            historyIndex++;
            
            while (historyStack.length > MAX_HISTORY_STATES) {
                historyStack.shift();
                historyIndex = Math.max(0, historyIndex - 1);
            }
            markEditorDirty();
        }
        
        updateUndoRedoButtons();
        
        // Сохраняем в файл с задержкой
        clearTimeout(historySaveTimeout);
        historySaveTimeout = setTimeout(() => {
            saveHistoryToFile();
        }, 1000);
    }

    function saveHistoryToFile() {
        fetch('save_history.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                history: historyStack,
                index: historyIndex
            })
        }).catch(error => {
            console.error('Ошибка сохранения истории:', error);
        });
    }

    function loadHistoryFromFile() {
        fetch('get_history.php?t=' + Date.now())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    historyStack = data.history || [];
                    historyIndex = data.index ?? -1;
                    updateUndoRedoButtons();
                }
            })
            .catch(error => {
                console.error('Ошибка загрузки истории:', error);
            });
    }

    function undoEdit() {
        if (historyIndex <= 0) return;
        
        historyIndex--;
        restoreHistoryState(historyStack[historyIndex]);
        updateUndoRedoButtons();
        saveHistoryToFile();
    }

    function redoEdit() {
        if (historyIndex >= historyStack.length - 1) return;
        
        historyIndex++;
        restoreHistoryState(historyStack[historyIndex]);
        updateUndoRedoButtons();
        saveHistoryToFile();
    }

    function restoreHistoryState(state) {
        isRestoringHistory = true;
        
        const ve = document.getElementById('contentVisual');
        const ta = document.getElementById('content');
        
        ve.innerHTML = state.visual;
        ta.value = state.code;
        
        // Восстанавливаем обработчики для изображений и других элементов
        addColumnResizers();
        
        // Восстанавливаем выделение
        if (state.mode === 'visual') {
            ve.focus();
            if (state.visualSelection) {
                setSelectionOffsets(ve, state.visualSelection.start, state.visualSelection.end);
            }
        } else {
            ta.focus();
            if (state.codeSelection) {
                ta.setSelectionRange(state.codeSelection.start, state.codeSelection.end);
            }
        }
        
        isRestoringHistory = false;
    }

    function updateUndoRedoButtons() {
        const undoBtn = document.getElementById('undoBtn');
        const redoBtn = document.getElementById('redoBtn');
        
        if (undoBtn) {
            undoBtn.disabled = historyIndex <= 0;
            undoBtn.style.opacity = historyIndex <= 0 ? '0.4' : '1';
            undoBtn.style.cursor = historyIndex <= 0 ? 'not-allowed' : 'pointer';
        }
        
        if (redoBtn) {
            redoBtn.disabled = historyIndex >= historyStack.length - 1;
            redoBtn.style.opacity = historyIndex >= historyStack.length - 1 ? '0.4' : '1';
            redoBtn.style.cursor = historyIndex >= historyStack.length - 1 ? 'not-allowed' : 'pointer';
        }
    }

    function clearHistory() {
        historyStack = [];
        historyIndex = -1;
        updateUndoRedoButtons();
        
        // Очищаем файл истории
        fetch('clear_history.php', {
            method: 'POST'
        }).catch(error => {
            console.error('Ошибка очистки истории:', error);
        });
    }

    function insertHtmlAtCaret(html) {
        const ve = document.getElementById('contentVisual');
        ve.focus();
        const sel = window.getSelection();
        let range = null;
        if (savedRange && ve.contains(savedRange.commonAncestorContainer)) {
            range = savedRange;
        } else if (sel && sel.rangeCount > 0) {
            range = sel.getRangeAt(0);
        }
        if (!range) {
            ve.insertAdjacentHTML('beforeend', html);
            return;
        }
        range.deleteContents();
        const temp = document.createElement('div');
        temp.innerHTML = html;
        const frag = document.createDocumentFragment();
        let node, lastNode;
        while ((node = temp.firstChild)) {
            lastNode = frag.appendChild(node);
        }
        range.insertNode(frag);
        if (lastNode) {
            range.setStartAfter(lastNode);
            range.collapse(true);
            const s = window.getSelection();
            if (s) {
                s.removeAllRanges();
                s.addRange(range);
            }
            savedRange = range.cloneRange();
        }
    }

    /** Вставка блока с изображением(ями) и пустой строки после; курсор ставится в пустой блок, чтобы текст не привязывался к картинке */
    function insertImageBlockAtCaret(html) {
        const ve = document.getElementById('contentVisual');
        ve.focus();
        const sel = window.getSelection();
        let range = null;
        if (savedRange && ve.contains(savedRange.commonAncestorContainer)) {
            range = savedRange;
        } else if (sel && sel.rangeCount > 0) {
            range = sel.getRangeAt(0);
        }
        var emptyDiv = document.createElement('div');
        emptyDiv.innerHTML = '<br>';
        if (!range) {
            ve.insertAdjacentHTML('beforeend', html);
            ve.appendChild(emptyDiv);
            range = document.createRange();
            range.setStart(emptyDiv, 0);
            range.collapse(true);
            if (sel) {
                sel.removeAllRanges();
                sel.addRange(range);
            }
            savedRange = range.cloneRange();
            return;
        }
        range.deleteContents();
        var temp = document.createElement('div');
        temp.innerHTML = html;
        var frag = document.createDocumentFragment();
        var node, lastNode;
        while ((node = temp.firstChild)) {
            lastNode = frag.appendChild(node);
        }
        range.insertNode(frag);
        if (lastNode) {
            var parent = lastNode.parentNode;
            parent.insertBefore(emptyDiv, lastNode.nextSibling);
            range.setStart(emptyDiv, 0);
            range.collapse(true);
            if (sel) {
                sel.removeAllRanges();
                sel.addRange(range);
            }
            savedRange = range.cloneRange();
        }
    }

    function insertList() {
        const listTemplate = "\n<ul>\n  <li>Пункт 1</li>\n  <li>Пункт 2</li>\n  <li>Пункт 3</li>\n</ul>\n";
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const cursorPos = ta.selectionStart;
            ta.value = ta.value.substring(0, cursorPos) + listTemplate + ta.value.substring(cursorPos);
            ta.focus();
        } else {
            insertHtmlAtCaret(listTemplate);
        }
        saveToHistory();
    }

    function openTableDialog() {
        document.getElementById('tableDialog').style.display = 'block';
        document.getElementById('tableRows').focus();
    }

    function closeTableDialog() {
        document.getElementById('tableDialog').style.display = 'none';
        document.getElementById('tableRows').value = '3';
        document.getElementById('tableCols').value = '3';
    }

    function addTableRow() {
        if (!window.contextMenuTableRow) return;
        
        const row = window.contextMenuTableRow;
        const table = row.closest('table');
        if (!table) return;
        
        const colCount = row.querySelectorAll('td, th').length;
        const newRow = document.createElement('tr');
        
        for (let i = 0; i < colCount; i++) {
            const cell = document.createElement('td');
            cell.innerHTML = '<br>';
            cell.contentEditable = 'true';
            newRow.appendChild(cell);
        }
        
        // Вставляем новую строку после текущей
        if (row.parentNode.tagName === 'THEAD') {
            // Если это строка заголовка, добавляем в tbody
            const tbody = table.querySelector('tbody');
            if (tbody && tbody.firstChild) {
                tbody.insertBefore(newRow, tbody.firstChild);
            } else if (tbody) {
                tbody.appendChild(newRow);
            }
        } else {
            row.parentNode.insertBefore(newRow, row.nextSibling);
        }
        
        saveToHistory();
        showNotification('Строка добавлена', 'success');
    }

    function deleteTableRow() {
        if (!window.contextMenuTableRow) return;
        
        const row = window.contextMenuTableRow;
        const table = row.closest('table');
        if (!table) return;
        
        // Проверяем, не является ли это единственной строкой в tbody
        const tbody = table.querySelector('tbody');
        if (tbody && tbody.querySelectorAll('tr').length === 1 && row.parentNode === tbody) {
            showNotification('Нельзя удалить последнюю строку таблицы', 'warning');
            return;
        }
        
        // Не даем удалить строку заголовка, если она единственная в thead
        if (row.parentNode.tagName === 'THEAD') {
            showNotification('Нельзя удалить строку заголовка', 'warning');
            return;
        }
        
        row.parentNode.removeChild(row);
        saveToHistory();
        showNotification('Строка удалена', 'success');
    }

    function addTableColumn() {
        if (!window.contextMenuTableCell) return;
        
        const cell = window.contextMenuTableCell;
        const table = cell.closest('table');
        if (!table) return;
        
        // Определяем индекс текущего столбца
        const row = cell.closest('tr');
        const cells = Array.from(row.querySelectorAll('td, th'));
        const colIndex = cells.indexOf(cell);
        
        // Добавляем ячейку в заголовок
        const thead = table.querySelector('thead');
        if (thead) {
            const headerRow = thead.querySelector('tr');
            if (headerRow) {
                const headerCells = headerRow.querySelectorAll('th');
                const newHeader = document.createElement('th');
                newHeader.innerHTML = '<br>';
                newHeader.contentEditable = 'true';
                
                if (colIndex + 1 < headerCells.length) {
                    headerRow.insertBefore(newHeader, headerCells[colIndex + 1]);
                } else {
                    headerRow.appendChild(newHeader);
                }
            }
        }
        
        // Добавляем ячейки во все строки tbody
        const tbody = table.querySelector('tbody');
        if (tbody) {
            const rows = tbody.querySelectorAll('tr');
            rows.forEach(function(bodyRow) {
                const bodyCells = bodyRow.querySelectorAll('td');
                const newCell = document.createElement('td');
                newCell.innerHTML = '<br>';
                newCell.contentEditable = 'true';
                
                if (colIndex + 1 < bodyCells.length) {
                    bodyRow.insertBefore(newCell, bodyCells[colIndex + 1]);
                } else {
                    bodyRow.appendChild(newCell);
                }
            });
        }
        
        // Обновляем ресайзеры
        addColumnResizers();
        saveToHistory();
        showNotification('Столбец добавлен', 'success');
    }

    function deleteTableColumn() {
        if (!window.contextMenuTableCell) return;
        
        const cell = window.contextMenuTableCell;
        const table = cell.closest('table');
        if (!table) return;
        
        // Определяем индекс текущего столбца
        const row = cell.closest('tr');
        const cells = Array.from(row.querySelectorAll('td, th'));
        const colIndex = cells.indexOf(cell);
        
        // Проверяем, не единственный ли это столбец
        if (cells.length === 1) {
            showNotification('Нельзя удалить единственный столбец', 'warning');
            return;
        }
        
        // Удаляем ячейку из заголовка
        const thead = table.querySelector('thead');
        if (thead) {
            const headerRow = thead.querySelector('tr');
            if (headerRow) {
                const headerCells = headerRow.querySelectorAll('th');
                if (headerCells[colIndex]) {
                    headerCells[colIndex].parentNode.removeChild(headerCells[colIndex]);
                }
            }
        }
        
        // Удаляем ячейки из всех строк tbody
        const tbody = table.querySelector('tbody');
        if (tbody) {
            const rows = tbody.querySelectorAll('tr');
            rows.forEach(function(bodyRow) {
                const bodyCells = bodyRow.querySelectorAll('td');
                if (bodyCells[colIndex]) {
                    bodyCells[colIndex].parentNode.removeChild(bodyCells[colIndex]);
                }
            });
        }
        
        // Обновляем ресайзеры
        addColumnResizers();
        saveToHistory();
        showNotification('Столбец удален', 'success');
    }

    function deleteTable() {
        if (!window.contextMenuTableCell && !window.contextMenuTableRow) return;
        
        const cell = window.contextMenuTableCell || window.contextMenuTableRow.querySelector('td, th');
        if (!cell) return;
        
        const table = cell.closest('table');
        if (!table) return;
        
        // Удаляем таблицу
        table.parentNode.removeChild(table);
        saveToHistory();
        showNotification('Таблица удалена', 'success');
    }

    function openCellColorDialog() {
        if (!window.contextMenuTableCell) return;
        document.getElementById('cellColorDialog').style.display = 'block';
    }

    function closeCellColorDialog() {
        document.getElementById('cellColorDialog').style.display = 'none';
    }

    function setCellColor(color) {
        if (!window.contextMenuTableCell) return;
        
        const cell = window.contextMenuTableCell;
        
        if (color) {
            cell.style.backgroundColor = color;
            cell.style.color = '#000000'; // Устанавливаем черный цвет текста
        } else {
            cell.style.backgroundColor = '';
            cell.style.color = ''; // Сбрасываем цвет текста
        }
        
        saveToHistory();
        closeCellColorDialog();
        showNotification('Цвет ячейки изменен', 'success');
    }

    function insertTable() {
        const rows = parseInt(document.getElementById('tableRows').value);
        const cols = parseInt(document.getElementById('tableCols').value);
        
        if (!rows || rows < 1 || rows > 20) {
            showNotification('Введите количество строк от 1 до 20', 'warning');
            return;
        }
        
        if (!cols || cols < 1 || cols > 7) {
            showNotification('Введите количество столбцов от 1 до 7', 'warning');
            return;
        }
        
        if (window.enableMarkdown && editorMode === 'code') {
            let mdTable = '\n';
            mdTable += '| ' + Array.from({length: cols}, (_, i) => `Заголовок ${i + 1}`).join(' | ') + ' |\n';
            mdTable += '| ' + Array.from({length: cols}, () => '---').join(' | ') + ' |\n';
            for (let i = 0; i < rows; i++) {
                mdTable += '| ' + Array.from({length: cols}, () => ' ').join(' | ') + ' |\n';
            }
            mdTable += '\n';

            const ta = document.getElementById('content');
            const cursorPos = ta.selectionStart;
            ta.value = ta.value.substring(0, cursorPos) + mdTable + ta.value.substring(cursorPos);
            ta.focus();
            saveToHistory();
            closeTableDialog();
            showNotification('Таблица добавлена', 'success');
            return;
        }
        
        let tableHtml = '<table><thead><tr>';
        
        // Создаем заголовки
        for (let i = 0; i < cols; i++) {
            tableHtml += `<th>Заголовок ${i + 1}</th>`;
        }
        tableHtml += '</tr></thead><tbody>';
        
        // Создаем строки с пустыми ячейками
        for (let i = 0; i < rows; i++) {
            tableHtml += '<tr>';
            for (let j = 0; j < cols; j++) {
                tableHtml += '<td><br></td>';
            }
            tableHtml += '</tr>';
        }
        
        tableHtml += '</tbody></table>';
        
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const cursorPos = ta.selectionStart;
            ta.value = ta.value.substring(0, cursorPos) + tableHtml + '\n' + ta.value.substring(cursorPos);
            ta.focus();
        } else {
            insertTableAtCaret(tableHtml);
        }
        
        saveToHistory();
        closeTableDialog();
        showNotification('Таблица добавлена', 'success');
    }

    // Функция для вставки таблицы в визуальном редакторе
    function insertTableAtCaret(tableHtml) {
        const ve = document.getElementById('contentVisual');
        ve.focus();
        const sel = window.getSelection();
        let range = null;
        
        if (savedRange && ve.contains(savedRange.commonAncestorContainer)) {
            range = savedRange;
        } else if (sel && sel.rangeCount > 0) {
            range = sel.getRangeAt(0);
        }
        
        // Создаем пустой блок для курсора после таблицы
        const emptyDiv = document.createElement('div');
        emptyDiv.innerHTML = '<br>';
        
        if (!range) {
            ve.insertAdjacentHTML('beforeend', tableHtml);
            ve.appendChild(emptyDiv);
            range = document.createRange();
            range.setStart(emptyDiv, 0);
            range.collapse(true);
            if (sel) {
                sel.removeAllRanges();
                sel.addRange(range);
            }
            savedRange = range.cloneRange();
        } else {
            range.deleteContents();
            
            // Создаем временный контейнер для парсинга HTML
            const temp = document.createElement('div');
            temp.innerHTML = tableHtml;
            
            const frag = document.createDocumentFragment();
            let node, lastNode;
            while ((node = temp.firstChild)) {
                lastNode = frag.appendChild(node);
            }
            
            range.insertNode(frag);
            
            if (lastNode) {
                const parent = lastNode.parentNode;
                parent.insertBefore(emptyDiv, lastNode.nextSibling);
                range.setStart(emptyDiv, 0);
                range.collapse(true);
                if (sel) {
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
                savedRange = range.cloneRange();
            }
        }
        
        // Добавляем ручки изменения размера после небольшой задержки
        setTimeout(() => {
            addColumnResizers();
        }, 100);
    }

    // Функция для добавления ручек изменения размера столбцов
    function addColumnResizers() {
        const ve = document.getElementById('contentVisual');
        if (!ve) return;
        
        const tables = ve.querySelectorAll('table');
        tables.forEach(table => {
            // Проверяем, не добавлены ли уже ручки
            if (table.dataset.resizersAdded) return;
            table.dataset.resizersAdded = 'true';
            
            const headerCells = table.querySelectorAll('thead th');
            
            // Устанавливаем начальную ширину в процентах
            const colWidth = 100 / headerCells.length;
            headerCells.forEach(th => {
                th.style.width = colWidth + '%';
            });
            
            headerCells.forEach((th, index) => {
                // Не добавляем ручку к последнему столбцу
                if (index === headerCells.length - 1) return;
                
                const resizer = document.createElement('div');
                resizer.className = 'column-resizer';
                resizer.contentEditable = 'false';
                th.appendChild(resizer);
                
                let startX, startWidthPercent, nextStartWidthPercent, tableWidth;
                
                resizer.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    resizer.classList.add('resizing');
                    startX = e.pageX;
                    tableWidth = table.offsetWidth;
                    
                    // Получаем текущую ширину в процентах
                    startWidthPercent = (th.offsetWidth / tableWidth) * 100;
                    
                    const nextTh = headerCells[index + 1];
                    nextStartWidthPercent = nextTh ? (nextTh.offsetWidth / tableWidth) * 100 : 0;
                    
                    document.addEventListener('mousemove', onMouseMove);
                    document.addEventListener('mouseup', onMouseUp);
                });
                
                function onMouseMove(e) {
                    const diff = e.pageX - startX;
                    const diffPercent = (diff / tableWidth) * 100;
                    
                    const newWidthPercent = startWidthPercent + diffPercent;
                    const newNextWidthPercent = nextStartWidthPercent - diffPercent;
                    
                    // Минимальная ширина 5%
                    if (newWidthPercent > 5 && newNextWidthPercent > 5) {
                        th.style.width = newWidthPercent + '%';
                        const nextTh = headerCells[index + 1];
                        if (nextTh) {
                            nextTh.style.width = newNextWidthPercent + '%';
                        }
                        
                        // Применяем ширину ко всем ячейкам в столбце
                        const rows = table.querySelectorAll('tbody tr');
                        rows.forEach(row => {
                            const cells = row.querySelectorAll('td');
                            if (cells[index]) {
                                cells[index].style.width = newWidthPercent + '%';
                            }
                            if (cells[index + 1]) {
                                cells[index + 1].style.width = newNextWidthPercent + '%';
                            }
                        });
                    }
                }
                
                function onMouseUp() {
                    resizer.classList.remove('resizing');
                    document.removeEventListener('mousemove', onMouseMove);
                    document.removeEventListener('mouseup', onMouseUp);
                }
            });
        });
    }

    // Вызываем функцию при загрузке контента в визуальный редактор
    function initTableResizers() {
        const ve = document.getElementById('contentVisual');
        if (!ve) return;
        
        // Добавляем ручки к существующим таблицам
        addColumnResizers();
        
        // Наблюдаем за изменениями в редакторе
        const observer = new MutationObserver(() => {
            addColumnResizers();
        });
        
        observer.observe(ve, {
            childList: true,
            subtree: true
        });
    }

    // Инициализируем при загрузке страницы
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTableResizers);
    } else {
        initTableResizers();
    }

    function addLink() {
        var urlInput = document.getElementById('linkUrl');
        var textInput = document.getElementById('linkText');
        urlInput.value = 'https://';
        if (editorMode === 'code') {
            var ta = document.getElementById('content');
            linkInsertStart = ta.selectionStart;
            linkInsertEnd = ta.selectionEnd;
            textInput.value = ta.value.substring(linkInsertStart, linkInsertEnd).trim();
        } else {
            textInput.value = document.getSelection().toString().trim();
        }
        document.getElementById('linkDialog').style.display = 'block';
        urlInput.focus();
        if (navigator.clipboard && navigator.clipboard.readText) {
            navigator.clipboard.readText().then(function(text) {
                if (text && (text = text.trim())) {
                    if (!/^https?:\/\//i.test(text)) text = 'https://' + text.replace(/^\/+/, '');
                    urlInput.value = text;
                }
            }).catch(function() {});
        }
    }

    function closeLinkDialog() {
        document.getElementById('linkDialog').style.display = 'none';
        document.getElementById('linkUrl').value = '';
        document.getElementById('linkText').value = '';
    }

    function insertLinkFromDialog() {
        var url = document.getElementById('linkUrl').value.trim();
        if (!url) {
            showNotification('Введите URL ссылки', 'warning');
            return;
        }
        var linkText = document.getElementById('linkText').value.trim();
        
        if (window.enableMarkdown && editorMode === 'code') {
            var ta = document.getElementById('content');
            var start = linkInsertStart;
            var end = linkInsertEnd;
            var selectedText = ta.value.substring(start, end);
            var text = linkText || selectedText || 'ссылка';
            var link = '[' + text + '](' + url + ')';
            ta.value = ta.value.substring(0, start) + link + ta.value.substring(end);
            ta.focus();
        } else if (editorMode === 'code') {
            var ta = document.getElementById('content');
            var start = linkInsertStart;
            var end = linkInsertEnd;
            var selectedText = ta.value.substring(start, end);
            var text = linkText || selectedText || 'ссылка';
            var link = '<a href="' + url + '">' + text + '</a>';
            ta.value = ta.value.substring(0, start) + link + ta.value.substring(end);
            ta.focus();
        } else {
            var text = linkText || (savedRange ? savedRange.toString() : '') || 'ссылка';
            var html = '<a href="' + url + '">' + text + '</a>';
            insertHtmlAtCaret(html);
        }
        saveToHistory();
        closeLinkDialog();
    }

    // Функции для работы с изображениями
    let selectedImageFiles = [];
    let isImageDragDropInitialized = false;

    function initImageDragDrop() {
        if (isImageDragDropInitialized) return;
        const dropzone = document.getElementById('imageDropzone');
        if (!dropzone) return;
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => {
                dropzone.classList.add('drag-over');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => {
                dropzone.classList.remove('drag-over');
            }, false);
        });
        
        dropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                Array.from(files).forEach(file => {
                    if (file.type.startsWith('image/')) {
                        selectedImageFiles.push(file);
                    }
                });
                renderImagePreviews();
            }
        }, false);
        
        isImageDragDropInitialized = true;
    }

    function renderImagePreviews() {
        const previewContainer = document.getElementById('imageFilesPreview');
        const dropzoneText = document.getElementById('imageDropzoneText');
        if (!previewContainer) return;
        
        if (selectedImageFiles.length === 0) {
            previewContainer.style.display = 'none';
            previewContainer.innerHTML = '';
            if (dropzoneText) {
                dropzoneText.textContent = 'Выберите изображения или перетащите их сюда';
            }
            return;
        }
        
        previewContainer.style.display = 'grid';
        previewContainer.innerHTML = '';
        if (dropzoneText) {
            dropzoneText.textContent = `Выбрано изображений: ${selectedImageFiles.length}`;
        }
        
        selectedImageFiles.forEach((file, index) => {
            const reader = new FileReader();
            const thumbnail = document.createElement('div');
            thumbnail.className = 'image-preview-thumbnail';
            
            const img = document.createElement('img');
            thumbnail.appendChild(img);
            
            const deleteBtn = document.createElement('div');
            deleteBtn.className = 'image-preview-thumbnail-delete';
            deleteBtn.innerHTML = '×';
            deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                selectedImageFiles.splice(index, 1);
                renderImagePreviews();
            });
            thumbnail.appendChild(deleteBtn);
            previewContainer.appendChild(thumbnail);
            
            reader.onload = function(e) {
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    function handleImageFileSelect(input) {
        if (input.files && input.files.length > 0) {
            const MAX_IMAGE_SIZE = 25 * 1024 * 1024; // 25 MB
            Array.from(input.files).forEach(file => {
                if (!file.type.startsWith('image/')) {
                    showNotification(`Файл "${file.name}" не является изображением`, 'warning');
                    return;
                }
                if (file.size > MAX_IMAGE_SIZE) {
                    showNotification(`Файл "${file.name}" слишком большой (${(file.size / 1024 / 1024).toFixed(1)} МБ). Максимум 25 МБ.`, 'error');
                    return;
                }
                selectedImageFiles.push(file);
            });
            renderImagePreviews();
            checkInsertGalleryVisibility();
        }
        input.value = '';
    }

    // Expose handleImageFileSelect to window
    window.handleImageFileSelect = handleImageFileSelect;

    function showImageUpload() {
    document.getElementById('imageUploadDialog').style.display = 'block';
    initImageDragDrop();
}

let gridTileFiles = {};

document.addEventListener('DOMContentLoaded', function() {
    const gridLayoutSelect = document.getElementById('gridLayout');
    if (gridLayoutSelect) {
        gridLayoutSelect.addEventListener('change', renderGridPreview);
    }
    
    // Remember state of "Remove rounded corners" checkbox
    const noRadiusChk = document.getElementById('noBorderRadius');
    if (noRadiusChk) {
        noRadiusChk.checked = localStorage.getItem('noBorderRadius') === 'true';
        noRadiusChk.addEventListener('change', function() {
            localStorage.setItem('noBorderRadius', this.checked ? 'true' : 'false');
        });
    }

    function sanitizePastedHtml(html) {
        if (!html || typeof html !== 'string') return '';
        const temp = document.createElement('div');
        temp.innerHTML = html;
        
        // Удаляем опасные и недопустимые теги
        const forbidden = temp.querySelectorAll('script, style, link, meta, base, xml, object, embed, applet, iframe, form, input, button, select, textarea, o\\:p');
        forbidden.forEach(el => el.remove());
        
        // Удаляем HTML-комментарии (например от Word <!--[if ...]-->)
        const removeComments = function(element) {
            for (let i = element.childNodes.length - 1; i >= 0; i--) {
                const child = element.childNodes[i];
                if (child.nodeType === Node.COMMENT_NODE) {
                    element.removeChild(child);
                } else if (child.nodeType === Node.ELEMENT_NODE) {
                    removeComments(child);
                }
            }
        };
        removeComments(temp);
        
        // Очищаем атрибуты и инлайн-стили
        const allNodes = temp.querySelectorAll('*');
        allNodes.forEach(node => {
            // Удаляем обработчики событий и служебные id
            Array.from(node.attributes).forEach(attr => {
                const name = attr.name.toLowerCase();
                if (name.startsWith('on') || name === 'id' || name.startsWith('data-')) {
                    node.removeAttribute(attr.name);
                }
                // Очищаем классы от стилей Word/сторонних библиотек
                if (name === 'class') {
                    const safeClasses = Array.from(node.classList).filter(c => c.startsWith('blog-') || c === 'content' || c === 'table-container');
                    if (safeClasses.length) {
                        node.className = safeClasses.join(' ');
                    } else {
                        node.removeAttribute('class');
                    }
                }
            });
            
            // Очищаем деструктивные инлайн-стили
            if (node.hasAttribute('style')) {
                const styleStr = node.getAttribute('style') || '';
                const cleanRules = styleStr.split(';')
                    .map(r => r.trim())
                    .filter(r => {
                        if (!r) return false;
                        const lower = r.toLowerCase();
                        if (lower.startsWith('mso-') || lower.startsWith('font-family') || lower.startsWith('line-height') || lower.startsWith('margin-') || lower.startsWith('padding-')) return false;
                        if (lower.includes('background') && (lower.includes('#fff') || lower.includes('#000') || lower.includes('white') || lower.includes('black') || lower.includes('transparent') || lower.includes('rgb(255') || lower.includes('rgb(0'))) return false;
                        return true;
                    });
                if (cleanRules.length) {
                    node.setAttribute('style', cleanRules.join('; '));
                } else {
                    node.removeAttribute('style');
                }
            }
            
            // Разворачиваем устаревшие теги <font>
            if (node.tagName === 'FONT') {
                const parent = node.parentNode;
                while (node.firstChild) {
                    parent.insertBefore(node.firstChild, node);
                }
                parent.removeChild(node);
            }
        });
        
        return temp.innerHTML.trim();
    }

    // Support inserting images and clean rich text from clipboard (Ctrl+V / Paste)
    const handlePaste = function(e) {
        const clipboardData = e.clipboardData || e.originalEvent?.clipboardData;
        if (!clipboardData) return;
        
        const items = clipboardData.items;
        let imageItem = null;
        if (items) {
            for (const item of items) {
                if (item.type.indexOf('image') !== -1) {
                    imageItem = item;
                    break;
                }
            }
        }
        
        if (imageItem) {
            e.preventDefault();
            const file = imageItem.getAsFile();
            if (!file) return;
            
            showNotification('Загрузка изображения из буфера обмена...', 'info');
            
            const noBorderRadius = localStorage.getItem('noBorderRadius') === 'true';
            
            const formData = new FormData();
            // Explicitly set filename with proper extension to ensure backend validation passes
            const extension = file.type === 'image/jpeg' || file.type === 'image/jpg' ? 'jpg' : 'png';
            formData.append('image', file, `clipboard-${Date.now()}.${extension}`);
            
            fetch('upload_image.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    insertImage(data.url, '100', '', '%', '', '', noBorderRadius);
                    showNotification('Изображение успешно вставлено из буфера обмена', 'success');
                    markEditorDirty();
                } else {
                    showNotification('Ошибка при загрузке изображения: ' + data.error, 'error');
                }
            })
            .catch(() => {
                showNotification('Ошибка при загрузке изображения из буфера обмена', 'error');
            });
            return;
        }

        // Smart Paste Sanitization for Visual Mode
        if (editorMode === 'visual') {
            const html = clipboardData.getData('text/html');
            if (html && (html.includes('MsoNormal') || html.includes('style=') || html.includes('<!--[if') || html.includes('<font') || html.includes('<span'))) {
                e.preventDefault();
                const cleanHtml = sanitizePastedHtml(html);
                if (cleanHtml) {
                    document.execCommand('insertHTML', false, cleanHtml);
                } else {
                    const text = clipboardData.getData('text/plain');
                    if (text) document.execCommand('insertText', false, text);
                }
                markEditorDirty();
                saveToHistory();
            }
        }
    };

    const visualEditor = document.getElementById('contentVisual');
    if (visualEditor) {
        visualEditor.addEventListener('paste', handlePaste);
    }
    const codeEditor = document.getElementById('content');
    if (codeEditor) {
        codeEditor.addEventListener('paste', handlePaste);
    }
});

function renderGridPreview() {
    const gridLayout = document.getElementById('gridLayout').value;
    const previewContainer = document.getElementById('imageGridPreviewContainer');
    const fileUploadContainer = document.getElementById('fileUploadContainer');
    const imageSource = document.querySelector('input[name="imageSource"]:checked').value;
    
    // Clear old files
    gridTileFiles = {};
    
    if (!gridLayout || imageSource !== 'file') {
        previewContainer.style.display = 'none';
        previewContainer.innerHTML = '';
        if (imageSource === 'file') {
            fileUploadContainer.style.display = 'block';
        }
        return;
    }
    
    // Hide standard file upload input
    fileUploadContainer.style.display = 'none';
    previewContainer.style.display = 'grid';
    
    const [cols, rows] = gridLayout.split('x').map(Number);
    previewContainer.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;
    
    let html = '';
    const totalTiles = cols * rows;
    for (let i = 0; i < totalTiles; i++) {
        html += `
            <div class="grid-preview-tile" onclick="triggerTileUpload(${i})">
                <div class="grid-preview-tile-badge">${i + 1}</div>
                <div class="grid-preview-tile-content" id="tile-content-${i}">
                    <span class="grid-preview-tile-icon">➕</span>
                    <span>Плитка ${i + 1}</span>
                </div>
                <img id="tile-img-${i}" class="grid-preview-tile-img">
                <div id="tile-delete-${i}" class="grid-preview-tile-delete" onclick="clearTileImage(event, ${i})">×</div>
                <input type="file" id="tile-file-input-${i}" accept="image/*" style="display: none;" onchange="handleTileFileChange(event, ${i})">
            </div>
        `;
    }
    previewContainer.innerHTML = html;
}

window.triggerTileUpload = function(index) {
    const input = document.getElementById(`tile-file-input-${index}`);
    if (input) input.click();
};

window.clearTileImage = function(e, index) {
    e.stopPropagation();
    delete gridTileFiles[index];
    
    const img = document.getElementById(`tile-img-${index}`);
    const content = document.getElementById(`tile-content-${index}`);
    const delBtn = document.getElementById(`tile-delete-${index}`);
    const input = document.getElementById(`tile-file-input-${index}`);
    
    if (img) img.style.display = 'none';
    if (content) content.style.display = 'flex';
    if (delBtn) delBtn.style.display = 'none';
    if (input) input.value = '';
};

window.handleTileFileChange = function(e, index) {
    const file = e.target.files[0];
    if (!file) return;
    
    gridTileFiles[index] = file;
    
    const reader = new FileReader();
    reader.onload = function(evt) {
        const img = document.getElementById(`tile-img-${index}`);
        const content = document.getElementById(`tile-content-${index}`);
        const delBtn = document.getElementById(`tile-delete-${index}`);
        
        if (img) {
            img.src = evt.target.result;
            img.style.display = 'block';
        }
        if (content) {
            content.style.display = 'none';
        }
        if (delBtn) {
            delBtn.style.display = 'flex';
        }
    };
    reader.readAsDataURL(file);
};

document.querySelectorAll('input[name="imageSource"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const isFile = this.value === 'file';
        const gridLayout = document.getElementById('gridLayout').value;
        
        if (isFile && gridLayout) {
            document.getElementById('fileUploadContainer').style.display = 'none';
            document.getElementById('imageGridPreviewContainer').style.display = 'grid';
        } else {
            document.getElementById('fileUploadContainer').style.display = isFile ? 'block' : 'none';
            document.getElementById('imageGridPreviewContainer').style.display = 'none';
        }
        
        document.getElementById('urlContainer').style.display = 
            this.value === 'url' ? 'block' : 'none';
        
        // Обновляем видимость checkbox при переключении источника
        checkInsertGalleryVisibility();
    });
});

// Обработчик изменения URL для проверки множественности
const imageUrlInput = document.getElementById('imageUrl');
if (imageUrlInput) {
    imageUrlInput.addEventListener('input', checkInsertGalleryVisibility);
}

function checkInsertGalleryVisibility() {
    const insertGalleryContainer = document.getElementById('insertGalleryContainer');
    if (!insertGalleryContainer) return;
    
    const imageSource = document.querySelector('input[name="imageSource"]:checked')?.value;
    
    if (imageSource === 'file') {
        // Для файлов проверяем количество выбранных файлов
        if (selectedImageFiles.length > 1) {
            insertGalleryContainer.style.display = 'flex';
        } else {
            insertGalleryContainer.style.display = 'none';
        }
    } else if (imageSource === 'url') {
        // Для URL проверяем количество введённых адресов
        const urlInput = document.getElementById('imageUrl').value.trim();
        const urls = urlInput.split(/[\n,]+/).map(s => s.trim()).filter(Boolean);
        if (urls.length > 1) {
            insertGalleryContainer.style.display = 'flex';
        } else {
            insertGalleryContainer.style.display = 'none';
        }
    } else {
        insertGalleryContainer.style.display = 'none';
    }
}
function processImage() {
    const imageSource = document.querySelector('input[name="imageSource"]:checked').value;
    const gridLayout = document.getElementById('gridLayout').value;
    const sizeSelect = document.getElementById('imageSize');
    const sizeValue = sizeSelect.value;
    const insertGallery = document.getElementById('insertGallery')?.checked || false;

    let width, widthUnit = 'px';
    if (sizeValue === 'custom') {
        width = document.getElementById('customWidth').value;
        widthUnit = document.getElementById('widthUnit').value;
        if (widthUnit === 'px' && width && !isNaN(width)) {
            const maxLimit = window.editorContentWidth || 920;
            if (parseInt(width) > maxLimit) {
                width = maxLimit;
            }
        }
    } else {
        const sizes = {
            small: { width: 300 },
            medium: { width: 500 },
            large: { width: 800 }
        };
        width = sizes[sizeValue].width;
        
        const maxLimit = window.editorContentWidth || 920;
        if (width > maxLimit) {
            width = maxLimit;
        }
    }

    const caption = document.getElementById('imageCaption').value.trim();
    const noBorderRadius = document.getElementById('noBorderRadius')?.checked || false;

    if (imageSource === 'url') {
        const urlInput = document.getElementById('imageUrl').value.trim();
        if (!urlInput) {
            showNotification('Введите URL изображения (можно несколько — каждое с новой строки или через запятую)', 'warning');
            return;
        }
        const urls = urlInput.split(/[\n,]+/).map(function(s) { return s.trim(); }).filter(Boolean);
        if (urls.length === 1) {
            insertImage(urls[0], width, '', widthUnit, '', caption, noBorderRadius);
        } else {
            if (insertGallery) {
                insertImagesAsGallery(urls, caption, width, widthUnit, noBorderRadius);
            } else {
                insertImagesInGrid(urls, gridLayout, caption, width, widthUnit, noBorderRadius);
            }
            closeImageDialog();
        }
        return;
    }

    let hasFiles = false;
    const formData = new FormData();

    if (gridLayout) {
        // Использование интерактивных плиток визуальной сетки!
        const [cols, rows] = gridLayout.split('x').map(Number);
        const totalTiles = cols * rows;
        for (let i = 0; i < totalTiles; i++) {
            if (gridTileFiles[i]) {
                formData.append('image[]', gridTileFiles[i]);
                hasFiles = true;
            }
        }
    } else {
        // Стандартная одиночная или множественная загрузка файлов
        if (selectedImageFiles.length) {
            selectedImageFiles.forEach(file => {
                formData.append('image[]', file);
            });
            hasFiles = true;
        }
    }

    if (!hasFiles) {
        showNotification('Выберите хотя бы одно изображение для загрузки', 'warning');
        return;
    }

    formData.append('width', width);
    formData.append('widthUnit', widthUnit);
    formData.append('gridLayout', gridLayout);

    fetch('upload_images_grid.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.urls) {
            if (data.urls.length === 1 && !data.gridLayout) {
                insertImage(data.urls[0], width, '', widthUnit, '', caption, noBorderRadius);
            } else {
                if (insertGallery && data.urls.length > 1) {
                    insertImagesAsGallery(data.urls, caption, width, widthUnit, noBorderRadius);
                } else {
                    insertImagesInGrid(data.urls, data.gridLayout, caption, width, widthUnit, noBorderRadius);
                }
            }
        } else {
            showNotification('Ошибка при загрузке изображений: ' + data.error, 'error');
        }
    })
    .catch(() => {
        showNotification('Ошибка сети при загрузке изображений', 'error');
    });

    closeImageDialog();
}

function insertImagesInGrid(urls, layout, caption = '', width = '', widthUnit = 'px', noBorderRadius = false) {
    let html = '';
    const radiusStyle = noBorderRadius ? 'border-radius: 0px !important;' : 'border-radius: 8px;';
    const classAttr = `class="blog-image${noBorderRadius ? ' no-radius' : ''}"`;
    if (layout) {
        const [cols] = layout.split('x').map(Number);
        const className = `grid-container grid-${layout}`;
        
        html += `<div class="${className}" style="display: grid; grid-template-columns: repeat(${cols}, 1fr); gap: 10px;">`;
        urls.forEach(url => {
            html += wrapImageWithHint(`<img src="${url}" style="width: 100%; height: auto; display: block; margin: 0; ${radiusStyle}" ${classAttr}>`);
        });
        html += `</div>`;
        if (caption) {
            html += `<div style="text-align: center; margin-top: 8px;"><span class="caption" style="display: block; font-style: italic; font-size: 13px; opacity: 0.7;">${caption}</span></div>`;
        }
    } else {
        const imgStyle = width ? ` style="width: ${width}${widthUnit}; max-width: 100%; height: auto; display: block; margin: 0; ${radiusStyle}"` : ` style="max-width: 100%; height: auto; display: block; margin: 0; ${radiusStyle}"`;
        urls.forEach(url => {
            html += wrapImageWithHint(`<img src="${url}"${imgStyle} ${classAttr}>`, caption);
        });
    }

    if (editorMode === 'code') {
        const ta = document.getElementById('content');
        const cursorPos = ta.selectionStart;
        ta.value = ta.value.substring(0, cursorPos) + html + '\n' + ta.value.substring(cursorPos);
    } else {
        insertImageBlockAtCaret(html);
    }

    closeImageDialog();
}

function insertImagesAsGallery(urls, caption = '', width = '', widthUnit = 'px', noBorderRadius = false) {
    if (!urls || urls.length === 0) return;
    
    const galleryId = 'gallery-' + Date.now();
    const radiusStyle = noBorderRadius ? 'border-radius: 0px !important;' : 'border-radius: 8px;';
    const widthStyle = width ? `width: ${width}${widthUnit}; max-width: 100%;` : 'max-width: 100%;';
    
    let galleryHtml = `<div class="image-gallery" id="${galleryId}" style="position: relative; ${widthStyle} margin: 0;">`;
    
    urls.forEach((url, index) => {
        const displayStyle = index === 0 ? 'display: block;' : 'display: none;';
        galleryHtml += `<img src="${url}" style="width: 100%; height: auto; ${displayStyle} margin: 0; ${radiusStyle}" class="blog-image${noBorderRadius ? ' no-radius' : ''}" data-gallery="${galleryId}" data-index="${index}">`;
    });
    
    if (urls.length > 1) {
        galleryHtml += `
            <button type="button" class="gallery-nav gallery-prev" onclick="event.stopPropagation(); window.navigateGallery('${galleryId}', -1);" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.6); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 20px; cursor: pointer; z-index: 10; display: flex; align-items: center; justify-content: center; user-select: none;">‹</button>
            <button type="button" class="gallery-nav gallery-next" onclick="event.stopPropagation(); window.navigateGallery('${galleryId}', 1);" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.6); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 20px; cursor: pointer; z-index: 10; display: flex; align-items: center; justify-content: center; user-select: none;">›</button>
            <div class="gallery-indicator" style="position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.6); color: white; padding: 5px 12px; border-radius: 12px; font-size: 12px; z-index: 10; user-select: none; pointer-events: none;">1 / ${urls.length}</div>
        `;
    }
    
    galleryHtml += `</div>`;
    
    // Оборачиваем галерею в blog-image-wrap для поддержки тулбара и изменения размера
    const wrappedHtml = wrapImageWithHint(galleryHtml, caption);
    
    if (editorMode === 'code') {
        const ta = document.getElementById('content');
        const cursorPos = ta.selectionStart;
        ta.value = ta.value.substring(0, cursorPos) + wrappedHtml + '\n' + ta.value.substring(cursorPos);
    } else {
        insertImageBlockAtCaret(wrappedHtml);
    }
    
    closeImageDialog();
}

function uploadImage(file, width, height, widthUnit, heightUnit, caption) {
    const formData = new FormData();
    formData.append('image', file);
    formData.append('width', width);
    formData.append('height', height || '');
    formData.append('widthUnit', widthUnit);
    formData.append('heightUnit', heightUnit || '');
    formData.append('caption', caption || '');

    fetch('upload_image.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            insertImage(data.url, width, height, widthUnit, heightUnit, caption);
        } else {
            showNotification('Ошибка при загрузке изображения: ' + data.error, 'error');
        }
    })
    .catch(error => {
        showNotification('Ошибка при загрузке изображения', 'error');
    });
}

function wrapImageWithHint(imgHtml, caption = '') {
    const uniqueId = 'img-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    const captionHtml = caption ? `<span class="caption" style="display: block; text-align: center; margin-top: 8px; font-style: italic; font-size: 13px; opacity: 0.7;">${caption}</span>` : '';
    return '<div class="blog-image-align-wrap" style="text-align:left; display: block; margin: 14px 0; width: 100%; clear: both; position: relative;" data-image-id="' + uniqueId + '">' +
        '<div class="blog-image-wrap" style="position: relative; display: inline-block; max-width: 100%; vertical-align: top; text-align: center;">' + imgHtml + captionHtml + '</div></div>';
}

function wrapMediaWithControls(mediaHtml, type = 'video') {
    const uniqueId = type + '-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    return '<div class="blog-image-align-wrap" style="text-align:left; display: block; margin: 14px 0; width: 100%; clear: both; position: relative;" data-image-id="' + uniqueId + '">' +
        '<div class="blog-image-wrap" style="position: relative; display: inline-block; max-width: 100%; vertical-align: top; text-align: center;" data-media-type="' + type + '">' + mediaHtml + '</div></div>';
}

function wrapExistingEditorImages() {
    var ve = document.getElementById('contentVisual');
    if (!ve || ve.style.display === 'none') return;
    
    // Сначала удаляем все старые элементы управления, если они вдруг есть
    var legacyElements = ve.querySelectorAll('.image-toolbar, .image-align-dropdown, .image-size-indicator, .image-resize-handle, .blog-image-overlay');
    legacyElements.forEach(function(el) {
        el.parentNode.removeChild(el);
    });
    
    // Обрабатываем галереи отдельно
    var galleries = ve.querySelectorAll('.image-gallery');
    galleries.forEach(function(gallery) {
        var wrap = gallery.closest('.blog-image-wrap');
        var alignWrap = gallery.closest('.blog-image-align-wrap');
        
        // Если галерея не обёрнута, оборачиваем её
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.className = 'blog-image-wrap';
            wrap.setAttribute('data-media-type', 'gallery');
            gallery.parentNode.insertBefore(wrap, gallery);
            wrap.appendChild(gallery);
        } else {
            wrap.setAttribute('data-media-type', 'gallery');
        }
        
        wrap.style.position = 'relative';
        wrap.style.display = 'inline-block';
        wrap.style.maxWidth = '100%';
        wrap.style.verticalAlign = 'top';
        wrap.style.textAlign = 'center';
        
        if (!alignWrap) {
            alignWrap = document.createElement('div');
            alignWrap.className = 'blog-image-align-wrap';
            alignWrap.style.textAlign = 'left';
            alignWrap.setAttribute('data-image-id', 'gallery-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9));
            wrap.parentNode.insertBefore(alignWrap, wrap);
            alignWrap.appendChild(wrap);
        }
        
        alignWrap.style.display = 'block';
        alignWrap.style.margin = '14px 0';
        alignWrap.style.width = '100%';
        alignWrap.style.clear = 'both';
        alignWrap.style.position = 'relative';
    });
    
    var imgs = ve.querySelectorAll('img.blog-image, img[src], video, audio, iframe, pre.code-block, a.custom-blog-btn');
    for (var i = 0; i < imgs.length; i++) {
        var img = imgs[i];
        
        // Пропускаем изображения внутри галерей
        if (img.closest('.image-gallery')) {
            continue;
        }
        
        // Пропускаем, если элемент является частью каких-то других управляющих структур
        if (img.closest('.image-toolbar') || img.closest('.image-align-dropdown') || img.closest('.editor-context-menu')) {
            continue;
        }
        
        var isImg = img.tagName.toLowerCase() === 'img';
        var type = img.classList.contains('custom-blog-btn') ? 'button' : img.tagName.toLowerCase();
        
        var wrap = img.closest && img.closest('.blog-image-wrap');
        var alignWrap = img.closest && img.closest('.blog-image-align-wrap');
        
        // 1. Если нет wrap, создаем его
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.className = 'blog-image-wrap';
            if (type !== 'img') {
                wrap.setAttribute('data-media-type', type);
            }
            img.parentNode.insertBefore(wrap, img);
            wrap.appendChild(img);
        } else {
            // Если wrap есть, но нет типа медиа
            if (type !== 'img' && !wrap.hasAttribute('data-media-type')) {
                wrap.setAttribute('data-media-type', type);
            }
        }
        wrap.style.position = 'relative';
        wrap.style.display = 'inline-block';
        wrap.style.maxWidth = '100%';
        wrap.style.verticalAlign = 'top';
        wrap.style.textAlign = 'center';
        
        // 2. Если нет alignWrap, создаем его
        if (!alignWrap) {
            alignWrap = document.createElement('div');
            alignWrap.className = 'blog-image-align-wrap';
            alignWrap.style.textAlign = 'left';
            alignWrap.setAttribute('data-image-id', type + '-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9));
            wrap.parentNode.insertBefore(alignWrap, wrap);
            alignWrap.appendChild(wrap);
        }
        alignWrap.style.display = 'block';
        alignWrap.style.margin = '14px 0';
        alignWrap.style.width = '100%';
        alignWrap.style.clear = 'both';
        alignWrap.style.position = 'relative';

        if (type === 'img') {
            img.style.display = 'block';
            img.style.maxWidth = '100%';
            if (!img.style.height || img.style.height === 'initial') {
                img.style.height = 'auto';
            }
            img.style.margin = '0';
            if (img.classList.contains('no-radius')) {
                img.style.borderRadius = '0px';
            } else {
                img.style.borderRadius = '8px';
            }
        } else if (type === 'video') {
            img.style.display = 'block';
            img.style.maxWidth = '100%';
            img.style.height = 'auto';
        }

        if (type === 'pre' || type === 'button') {
            img.setAttribute('contenteditable', 'false');
        }
    }
}

var activeTarget = null; // Текущий активный медиа-блок .blog-image-wrap
var isResizingMedia = false;
var startX, startY, startWidth, startHeight;
var currentHandle = null;

function showGlobalMediaOverlay(mediaWrap) {
    if (editorMode !== 'visual') return;
    
    activeTarget = mediaWrap;
    
    var overlay = document.getElementById('editorGlobalMediaOverlay');
    if (!overlay) {
        initGlobalMediaOverlayDOM();
        overlay = document.getElementById('editorGlobalMediaOverlay');
    }
    
    overlay.style.display = 'block';
    updateOverlayPosition();
    
    var innerMedia = mediaWrap.querySelector('img, video, audio, iframe, .blog-file-button, .blog-ascii-art, pre.code-block, a.custom-blog-btn, .image-gallery');
    var isImg = innerMedia && innerMedia.tagName.toLowerCase() === 'img';
    var isFile = innerMedia && innerMedia.classList.contains('blog-file-button');
    var isAscii = innerMedia && innerMedia.classList.contains('blog-ascii-art');
    var isCode = innerMedia && innerMedia.tagName.toLowerCase() === 'pre';
    var isBtn = innerMedia && innerMedia.classList.contains('custom-blog-btn');
    var isGallery = innerMedia && innerMedia.classList.contains('image-gallery');
    var isGrid = activeTarget.closest('.grid-container') !== null;
    
    var editBtn = overlay.querySelector('.image-toolbar-btn[data-action="edit"]');
    var resizeBtn = overlay.querySelector('.image-toolbar-btn[data-action="resize"]');
    var sizeIndicator = overlay.querySelector('.image-size-indicator');
    var resizeHandles = overlay.querySelectorAll('.image-resize-handle');
    
    // Для галереи показываем только редактирование первого изображения
    if (editBtn) editBtn.style.display = (isImg || isAscii || isCode || isBtn || isGallery) ? 'flex' : 'none';
    if (resizeBtn) resizeBtn.style.display = (isFile || isAscii || isCode || isGrid || isBtn) ? 'none' : 'flex';
    if (sizeIndicator) sizeIndicator.style.display = (isFile || isAscii || isCode || isGrid || isBtn) ? 'none' : 'block';
    resizeHandles.forEach(h => h.style.display = (isFile || isAscii || isCode || isGrid || isBtn) ? 'none' : 'block');
    
    var alignWrap = mediaWrap.closest('.blog-image-align-wrap');
    var align = alignWrap ? (alignWrap.style.textAlign || 'left') : 'left';
    overlay.querySelectorAll('.image-align-option').forEach(function(opt) {
        if (opt.getAttribute('data-align') === align) {
            opt.classList.add('active');
        } else {
            opt.classList.remove('active');
        }
    });
    
    var dropdown = overlay.querySelector('.image-align-dropdown');
    if (dropdown) dropdown.style.display = 'none';
    var alignBtn = overlay.querySelector('.image-toolbar-btn[data-action="align"]');
    if (alignBtn) alignBtn.classList.remove('active');
}

function hideGlobalMediaOverlay() {
    var overlay = document.getElementById('editorGlobalMediaOverlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
    activeTarget = null;
}

function updateOverlayPosition() {
    var overlay = document.getElementById('editorGlobalMediaOverlay');
    if (!overlay || overlay.style.display === 'none' || !activeTarget) return;
    
    var rect = activeTarget.getBoundingClientRect();
    
    if (rect.width === 0 || rect.height === 0 || !document.body.contains(activeTarget)) {
        hideGlobalMediaOverlay();
        return;
    }
    
    overlay.style.left = (rect.left + window.scrollX) + 'px';
    overlay.style.top = (rect.top + window.scrollY) + 'px';
    overlay.style.width = rect.width + 'px';
    overlay.style.height = rect.height + 'px';
    
    var innerMedia = activeTarget.querySelector('img, video, audio, iframe, .blog-file-button, .blog-ascii-art, .image-gallery');
    var sizeIndicator = overlay.querySelector('.image-size-indicator');
    
    if (innerMedia && sizeIndicator) {
        // Для галереи берём размер самой галереи, а не первого изображения
        var isGallery = innerMedia.classList && innerMedia.classList.contains('image-gallery');
        var w, h;
        
        if (isGallery) {
            w = innerMedia.offsetWidth;
            h = innerMedia.offsetHeight;
        } else {
            w = innerMedia.offsetWidth;
            h = innerMedia.offsetHeight;
        }
        
        if (innerMedia.classList.contains('blog-file-button') || innerMedia.classList.contains('blog-ascii-art')) {
            sizeIndicator.style.display = 'none';
        } else if (w && h) {
            sizeIndicator.textContent = w + ' × ' + h + ' px';
            sizeIndicator.style.display = 'block';
        } else if (w) {
            sizeIndicator.textContent = w + ' px';
            sizeIndicator.style.display = 'block';
        } else {
            sizeIndicator.style.display = 'none';
        }
    }
}

function showImageResizeDialog(img) {
    var isGallery = img.classList && img.classList.contains('image-gallery');
    var currentWidth = img.offsetWidth || (img.naturalWidth || img.videoWidth || 0);
    var isAudio = img.tagName.toLowerCase() === 'audio';
    var isVideo = img.tagName.toLowerCase() === 'video';
    var label = isGallery ? 'галереи' : (isAudio ? 'плеера аудио' : (isVideo ? 'плеера видео' : 'изображения'));
    
    var newWidth = prompt('Введите новую ширину ' + label + ' (в пикселях):', currentWidth);
    if (newWidth && !isNaN(newWidth) && newWidth > 0) {
        newWidth = parseInt(newWidth);
        const maxLimit = window.editorContentWidth || 920;
        if (newWidth > maxLimit) {
            newWidth = maxLimit;
        }
        
        if (isGallery) {
            // Для галереи изменяем размер самой галереи
            img.style.width = newWidth + 'px';
            img.style.maxWidth = '100%';
            
            // И обновляем размер всех изображений внутри
            var galleryImages = img.querySelectorAll('img');
            galleryImages.forEach(function(image) {
                image.style.width = '100%';
                image.style.height = 'auto';
            });
        } else {
            img.style.width = newWidth + 'px';
            if (isAudio) {
                img.style.height = '';
            } else {
                img.style.height = 'auto';
            }
        }
        updateOverlayPosition();
    }
}

function initGlobalMediaOverlayDOM() {
    if (document.getElementById('editorGlobalMediaOverlay')) return;
    
    var overlay = document.createElement('div');
    overlay.id = 'editorGlobalMediaOverlay';
    overlay.className = 'editor-global-media-overlay';
    overlay.style.cssText = 'display: none; position: absolute; pointer-events: none; z-index: 990;';
    
    overlay.innerHTML = 
        '<div class="media-overlay-outline" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; box-shadow: 0 0 0 2px var(--primary-color, #4CAF50); pointer-events: none; border-radius: 8px;"></div>' +
        '<div class="image-toolbar" style="position: absolute; top: 8px; right: 8px; display: flex; gap: 4px; background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(8px); padding: 6px; border-radius: 10px; z-index: 10; pointer-events: auto;">' +
        '    <button type="button" class="image-toolbar-btn" data-action="align" title="Выравнивание">⚏</button>' +
        '    <button type="button" class="image-toolbar-btn" data-action="resize" title="Изменить размер">⇲</button>' +
        '    <button type="button" class="image-toolbar-btn" data-action="edit" title="Редактировать">✏️</button>' +
        '    <button type="button" class="image-toolbar-btn" data-action="delete" title="Удалить">🗑</button>' +
        '</div>' +
        '<div class="image-align-dropdown" style="position: absolute; top: 48px; right: 8px; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(8px); border-radius: 10px; padding: 6px; display: none; flex-direction: column; gap: 4px; min-width: 180px; box-shadow: 0 8px 24px rgba(0,0,0,0.3); z-index: 20; pointer-events: auto;">' +
        '    <button type="button" class="image-align-option" data-align="left"><span>◄</span> По левому краю</button>' +
        '    <button type="button" class="image-align-option" data-align="center"><span>≡</span> По центру</button>' +
        '    <button type="button" class="image-align-option" data-align="right"><span>►</span> По правому краю</button>' +
        '</div>' +
        '<div class="image-size-indicator" style="position: absolute; bottom: 8px; left: 8px; background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(8px); color: #fff; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-family: monospace; pointer-events: none;"></div>' +
        '<div class="image-resize-handle bottom-right" style="position: absolute; width: 12px; height: 12px; background: var(--primary-color, #4CAF50); border: 2px solid #fff; border-radius: 50%; cursor: nwse-resize; z-index: 11; bottom: -6px; right: -6px; pointer-events: auto;"></div>' +
        '<div class="image-resize-handle bottom-left" style="position: absolute; width: 12px; height: 12px; background: var(--primary-color, #4CAF50); border: 2px solid #fff; border-radius: 50%; cursor: nesw-resize; z-index: 11; bottom: -6px; left: -6px; pointer-events: auto;"></div>';
        
    document.body.appendChild(overlay);
    
    overlay.addEventListener('mousedown', function(e) {
        var handle = e.target.closest('.image-resize-handle');
        if (!handle) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        isResizingMedia = true;
        currentHandle = handle;
        
        var innerMedia = activeTarget ? activeTarget.querySelector('img, video, audio, iframe') : null;
        if (!innerMedia) return;
        
        startX = e.clientX;
        startY = e.clientY;
        startWidth = innerMedia.offsetWidth;
        startHeight = innerMedia.offsetHeight;
        
        overlay.classList.add('selected');
        document.body.style.cursor = handle.classList.contains('bottom-right') ? 'nwse-resize' : 'nesw-resize';
    });
    
    overlay.addEventListener('click', function(e) {
        var toolbarBtn = e.target.closest('.image-toolbar-btn');
        if (toolbarBtn) {
            e.preventDefault();
            e.stopPropagation();
            
            var action = toolbarBtn.getAttribute('data-action');
            var dropdown = overlay.querySelector('.image-align-dropdown');
            
            if (action === 'align') {
                if (dropdown) {
                    var isOpen = dropdown.style.display === 'flex';
                    dropdown.style.display = isOpen ? 'none' : 'flex';
                    if (!isOpen) {
                        toolbarBtn.classList.add('active');
                    } else {
                        toolbarBtn.classList.remove('active');
                    }
                }
            } else if (action === 'resize') {
                var innerMedia = activeTarget ? activeTarget.querySelector('img, video, audio, iframe, .image-gallery') : null;
                if (innerMedia) {
                    showImageResizeDialog(innerMedia);
                }
            } else if (action === 'edit') {
                var gallery = activeTarget ? activeTarget.querySelector('.image-gallery') : null;
                var img = null;
                
                if (gallery) {
                    // Для галереи редактируем первое видимое изображение
                    img = gallery.querySelector('img[style*="display: block"], img:not([style*="display: none"])');
                    if (!img) {
                        // Если нет видимого, берём первое
                        img = gallery.querySelector('img');
                    }
                } else {
                    img = activeTarget ? activeTarget.querySelector('img') : null;
                }
                
                var ascii = activeTarget ? activeTarget.querySelector('.blog-ascii-wrap') : null;
                var codeBlock = activeTarget ? activeTarget.querySelector('pre.code-block') : null;
                var customBtn = activeTarget ? activeTarget.querySelector('a.custom-blog-btn') : null;
                
                if (img) {
                    openImageEditorModal(img);
                } else if (ascii) {
                    openAsciiDrawer(ascii);
                } else if (codeBlock) {
                    openEditCodeBlockDialog(codeBlock);
                } else if (customBtn) {
                    openEditCustomButtonDialog(customBtn);
                }
            } else if (action === 'delete') {
                var innerMedia = activeTarget ? activeTarget.querySelector('img, video, audio, iframe, .blog-file-button, .blog-ascii-art, pre.code-block, a.custom-blog-btn, .image-gallery') : null;
                var isImg = innerMedia && innerMedia.tagName.toLowerCase() === 'img';
                var isVideo = innerMedia && innerMedia.tagName.toLowerCase() === 'video';
                var isIframe = innerMedia && innerMedia.tagName.toLowerCase() === 'iframe';
                var isFile = innerMedia && innerMedia.classList.contains('blog-file-button');
                var isAscii = innerMedia && innerMedia.classList.contains('blog-ascii-art');
                var isCode = innerMedia && innerMedia.tagName.toLowerCase() === 'pre';
                var isCustomBtn = innerMedia && innerMedia.classList.contains('custom-blog-btn');
                var isGallery = innerMedia && innerMedia.classList.contains('image-gallery');
                var label = isGallery ? 'галерею' : (isImg ? 'изображение' : (isVideo || isIframe ? 'видео' : (isFile ? 'файл' : (isAscii ? 'ASCII-арт' : (isCode ? 'блок кода' : (isCustomBtn ? 'кнопку со ссылкой' : 'аудио'))))));
                
                var targetToDelete = activeTarget;
                
                showConfirm('Удалить это ' + label + '?').then(result => {
                    if (!result) return;
                    if (targetToDelete) {
                        var alignWrap = targetToDelete.closest('.blog-image-align-wrap');
                        if (alignWrap) {
                            alignWrap.parentNode.removeChild(alignWrap);
                        } else {
                            targetToDelete.parentNode.removeChild(targetToDelete);
                        }
                        hideGlobalMediaOverlay();
                        saveToHistory();
                    }
                });
            }
            return;
        }
        
        var alignOption = e.target.closest('.image-align-option');
        if (alignOption) {
            e.preventDefault();
            e.stopPropagation();
            
            var align = alignOption.getAttribute('data-align');
            var alignWrap = activeTarget ? activeTarget.closest('.blog-image-align-wrap') : null;
            if (alignWrap) {
                alignWrap.style.textAlign = align;
                
                overlay.querySelectorAll('.image-align-option').forEach(function(opt) {
                    opt.classList.remove('active');
                });
                alignOption.classList.add('active');
                
                var dropdown = overlay.querySelector('.image-align-dropdown');
                if (dropdown) dropdown.style.display = 'none';
                var alignBtn = overlay.querySelector('.image-toolbar-btn[data-action="align"]');
                if (alignBtn) alignBtn.classList.remove('active');
                
                updateOverlayPosition();
            }
        }
    });
}

function initImageAlignmentHandlers() {
    var ve = document.getElementById('contentVisual');
    if (!ve) return;
    
    initGlobalMediaOverlayDOM();
    
    ve.addEventListener('mouseover', function(e) {
        if (editorMode !== 'visual' || isResizingMedia) return;
        var mediaWrap = e.target.closest('.blog-image-wrap');
        if (!mediaWrap) {
            var fileBtn = e.target.closest('.blog-file-button');
            if (fileBtn) {
                // Если это старая структура файла без .blog-image-wrap,
                // используем родительский div или саму кнопку как цель
                mediaWrap = fileBtn.closest('div[style*="display: block"]') || fileBtn;
            }
        }
        if (mediaWrap) {
            showGlobalMediaOverlay(mediaWrap);
        }
    });
    
    ve.addEventListener('click', function(e) {
        if (editorMode !== 'visual') return;
        
        // Предотвращаем переход по ссылкам и скачивание файлов при редактировании
        const clickedLink = e.target.closest('a');
        if (clickedLink) {
            e.preventDefault();
        }
        
        var mediaWrap = e.target.closest('.blog-image-wrap');
        if (!mediaWrap) {
            var fileBtn = e.target.closest('.blog-file-button');
            if (fileBtn) {
                mediaWrap = fileBtn.closest('div[style*="display: block"]') || fileBtn;
            }
        }
        if (mediaWrap) {
            showGlobalMediaOverlay(mediaWrap);
        }
    });
}

document.addEventListener('mousemove', function(e) {
    if (isResizingMedia && activeTarget) {
        var innerMedia = activeTarget.querySelector('img, video, audio, iframe, .image-gallery');
        if (!innerMedia) return;
        
        e.preventDefault();
        
        var deltaX = e.clientX - startX;
        var deltaY = e.clientY - startY;
        
        if (currentHandle.classList.contains('bottom-left')) {
            deltaX = -deltaX;
        }
        
        var isGallery = innerMedia.classList && innerMedia.classList.contains('image-gallery');
        var isAudio = innerMedia.tagName.toLowerCase() === 'audio';
        var isIframe = innerMedia.tagName.toLowerCase() === 'iframe';
        var isVideo = innerMedia.tagName.toLowerCase() === 'video';
        var newWidth = startWidth + deltaX;
        
        const maxLimit = window.editorContentWidth || 920;
        if (newWidth > maxLimit) {
            newWidth = maxLimit;
        }
        
        if (newWidth > 50 && newWidth <= maxLimit) {
            if (isGallery) {
                // Для галереи изменяем размер самой галереи
                innerMedia.style.width = newWidth + 'px';
                innerMedia.style.maxWidth = '100%';
                
                // И обновляем размер всех изображений внутри
                var galleryImages = innerMedia.querySelectorAll('img');
                galleryImages.forEach(function(img) {
                    img.style.width = '100%';
                    img.style.height = 'auto';
                });
            } else {
                innerMedia.style.width = newWidth + 'px';
                if (isAudio) {
                    innerMedia.style.height = '';
                } else if (isIframe || isVideo) {
                    var aspectRatio = startHeight / startWidth;
                    var newHeight = newWidth * aspectRatio;
                    innerMedia.style.height = newHeight + 'px';
                } else {
                    innerMedia.style.height = 'auto';
                }
            }
            updateOverlayPosition();
        }
    } else {
        if (editorMode !== 'visual') return;
        
        var overlay = document.getElementById('editorGlobalMediaOverlay');
        if (!overlay || overlay.style.display === 'none') return;
        
        var target = e.target;
        var insideActive = activeTarget && activeTarget.contains(target);
        var insideOverlay = overlay.contains(target);
        
        if (!insideActive && !insideOverlay) {
            var newMediaWrap = target.closest('.blog-image-wrap');
            if (!newMediaWrap) {
                var fileBtn = target.closest('.blog-file-button');
                if (fileBtn) {
                    newMediaWrap = fileBtn.closest('div[style*="display: block"]') || fileBtn;
                }
            }
            if (newMediaWrap) {
                showGlobalMediaOverlay(newMediaWrap);
            } else {
                var dropdown = overlay.querySelector('.image-align-dropdown');
                if (dropdown && dropdown.style.display === 'flex') {
                    return;
                }
                hideGlobalMediaOverlay();
            }
        }
    }
});

document.addEventListener('mouseup', function(e) {
    if (isResizingMedia) {
        isResizingMedia = false;
        document.body.style.cursor = '';
        var overlay = document.getElementById('editorGlobalMediaOverlay');
        if (overlay) overlay.classList.remove('selected');
        currentHandle = null;
    }
});

document.addEventListener('click', function(e) {
    if (editorMode !== 'visual') return;
    
    var overlay = document.getElementById('editorGlobalMediaOverlay');
    if (!overlay || overlay.style.display === 'none') return;
    
    var target = e.target;
    var insideActive = activeTarget && activeTarget.contains(target);
    var insideOverlay = overlay.contains(target);
    
    if (!insideActive && !insideOverlay) {
        hideGlobalMediaOverlay();
    }
});

window.addEventListener('scroll', updateOverlayPosition, { capture: true, passive: true });
window.addEventListener('resize', updateOverlayPosition);

initImageAlignmentHandlers();

(function preventEnterInsideImageBlock() {
    var ve = document.getElementById('contentVisual');
    if (!ve) return;
    ve.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter' || editorMode !== 'visual') return;
        var sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        var node = sel.anchorNode;
        if (!node || !ve.contains(node)) return;
        var alignWrap = node.nodeType === Node.ELEMENT_NODE ? node.closest('.blog-image-align-wrap') : (node.parentElement && node.parentElement.closest('.blog-image-align-wrap'));
        if (!alignWrap) return;
        e.preventDefault();
        var emptyDiv = document.createElement('div');
        emptyDiv.innerHTML = '<br>';
        var next = alignWrap.nextSibling;
        var parent = alignWrap.parentNode;
        if (next) parent.insertBefore(emptyDiv, next);
        else parent.appendChild(emptyDiv);
        var range = document.createRange();
        range.setStart(emptyDiv, 0);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
        if (typeof savedRange !== 'undefined') savedRange = range.cloneRange();
    });
})();

(function initEditorContextMenu() {
    var menu = document.getElementById('editorContextMenu');
    var contentVisual = document.getElementById('contentVisual');
    var contentTa = document.getElementById('content');
    var contextMenuImageTarget = null;
    if (!menu || !contentVisual) return;

    function hideMenu() {
        menu.classList.remove('is-open');
        contextMenuImageTarget = null;
    }
    function showMenu(x, y) {
        menu.style.left = x + 'px';
        menu.style.top = y + 'px';
        menu.classList.add('is-open');
        requestAnimationFrame(function() {
            var rect = menu.getBoundingClientRect();
            var w = window.innerWidth;
            var h = window.innerHeight;
            var left = parseFloat(menu.style.left);
            var top = parseFloat(menu.style.top);
            if (left + rect.width > w - 8) left = w - rect.width - 8;
            if (top + rect.height > h - 8) top = h - rect.height - 8;
            if (left < 8) left = 8;
            if (top < 8) top = 8;
            menu.style.left = left + 'px';
            menu.style.top = top + 'px';
        });
    }

    function onContextMenu(e) {
        var inEditor = e.target === contentVisual || contentVisual.contains(e.target) ||
                       e.target === contentTa || contentTa.contains(e.target);
        if (!inEditor) return;
        e.preventDefault();
        e.stopPropagation();
        contextMenuImageTarget = null;
        
        // Скрываем кнопки таблицы по умолчанию
        var tableItems = menu.querySelectorAll('.table-context-item, .table-context-sep');
        tableItems.forEach(function(item) {
            item.style.display = 'none';
        });
        
        // Проверяем, находимся ли в таблице
        var tableRow = null;
        var tableCell = null;
        if (editorMode === 'visual' && contentVisual.contains(e.target)) {
            tableCell = e.target.closest('td, th');
            tableRow = e.target.closest('tr');
            if (tableRow && tableCell) {
                // Показываем кнопки таблицы
                tableItems.forEach(function(item) {
                    item.style.display = '';
                });
                // Сохраняем ссылку на строку и ячейку
                window.contextMenuTableRow = tableRow;
                window.contextMenuTableCell = tableCell;
            }
            
            var alignWrap = e.target.closest && e.target.closest('.blog-image-align-wrap');
            var imgWrap = e.target.closest && e.target.closest('.blog-image-wrap');
            var img = e.target.tagName === 'IMG' ? e.target : null;
            if (alignWrap) contextMenuImageTarget = alignWrap;
            else if (imgWrap) contextMenuImageTarget = imgWrap;
            else if (img && img.parentNode) contextMenuImageTarget = img.parentNode;
        }
        saveSelection();
        if (editorMode === 'code' && contentTa) {
            colorInsertStart = contentTa.selectionStart;
            colorInsertEnd = contentTa.selectionEnd;
        }
        showMenu(e.clientX, e.clientY);
    }

    contentVisual.addEventListener('contextmenu', onContextMenu);
    if (contentTa) contentTa.addEventListener('contextmenu', onContextMenu);

    // Обработчики для истории изменений с умной группировкой (как в MS Word)
    function handleInputHistory(inputType, data) {
        if (isRestoringHistory) return;
        const now = Date.now();
        let actionType = 'typing';
        
        if (inputType && inputType.startsWith('delete')) {
            actionType = 'deleting';
        } else if (inputType === 'insertFromPaste') {
            actionType = 'paste';
        }
        
        let startNewGroup = false;
        if (lastActionType !== actionType) {
            startNewGroup = true;
        } else if (cursorMoved) {
            startNewGroup = true;
            cursorMoved = false;
        } else if (now - lastActionTime > 1200) {
            startNewGroup = true;
        } else if (actionType === 'typing') {
            if (!data || data === ' ' || data === '\n' || data === '\r' || /[.,!?;:()\[\]{}"'+\-*/=<>#@$%^&~`|\\-]/.test(data)) {
                startNewGroup = true;
            }
        } else if (actionType === 'paste') {
            startNewGroup = true;
        }
        
        lastActionType = actionType;
        lastActionTime = now;
        
        const ve = document.getElementById('contentVisual');
        const ta = document.getElementById('content');
        if (!ve || !ta) return;
        
        const currentState = {
            visual: ve.innerHTML,
            code: ta.value,
            mode: editorMode,
            visualSelection: getSelectionOffsets(ve),
            codeSelection: { start: ta.selectionStart, end: ta.selectionEnd }
        };
        
        if (startNewGroup || historyIndex === -1) {
            historyStack = historyStack.slice(0, historyIndex + 1);
            historyStack.push(currentState);
            historyIndex++;
            
            while (historyStack.length > MAX_HISTORY_STATES) {
                historyStack.shift();
                historyIndex = Math.max(0, historyIndex - 1);
            }
        } else {
            historyStack[historyIndex] = currentState;
        }
        
        markEditorDirty();
        updateUndoRedoButtons();
        
        clearTimeout(historySaveTimeout);
        historySaveTimeout = setTimeout(() => {
            saveHistoryToFile();
        }, 1000);
    }

    const onCursorMove = function(e) {
        if (e.type === 'keyup') {
            const key = e.key;
            if (key !== 'ArrowLeft' && key !== 'ArrowRight' && key !== 'ArrowUp' && key !== 'ArrowDown' && 
                key !== 'Home' && key !== 'End' && key !== 'PageUp' && key !== 'PageDown') {
                return;
            }
        }
        cursorMoved = true;
    };
    
    contentVisual.addEventListener('keyup', onCursorMove);
    contentVisual.addEventListener('click', onCursorMove);
    if (contentTa) {
        contentTa.addEventListener('keyup', onCursorMove);
        contentTa.addEventListener('click', onCursorMove);
    }

    contentVisual.addEventListener('input', function(e) {
        const inputType = e.inputType || 'insertText';
        const data = e.data || '';
        handleInputHistory(inputType, data);
    });
    
    if (contentTa) {
        contentTa.addEventListener('input', function(e) {
            const inputType = e.inputType || 'insertText';
            const data = e.data || '';
            handleInputHistory(inputType, data);
        });
    }

    const onUndoRedoShortcut = function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
            e.preventDefault();
            if (e.shiftKey) {
                redoEdit();
            } else {
                undoEdit();
            }
        } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') {
            e.preventDefault();
            redoEdit();
        }
    };

    contentVisual.addEventListener('keydown', onUndoRedoShortcut);
    if (contentTa) {
        contentTa.addEventListener('keydown', onUndoRedoShortcut);
    }

    // Обработчик для обеспечения возможности редактирования после spoiler блоков
    contentVisual.addEventListener('click', function(e) {
        // Проверяем, кликнули ли мы на spoiler блок или рядом с ним
        const ve = document.getElementById('contentVisual');
        const spoilers = ve.querySelectorAll('.spoiler-block');
        
        spoilers.forEach(function(spoiler) {
            // Проверяем, есть ли после spoiler следующий элемент
            if (!spoiler.nextSibling || (spoiler.nextSibling.nodeType === Node.TEXT_NODE && spoiler.nextSibling.textContent.trim() === '')) {
                // Если нет следующего элемента или это пустой текстовый узел, создаем div
                const emptyDiv = document.createElement('div');
                emptyDiv.innerHTML = '<br>';
                if (spoiler.nextSibling) {
                    spoiler.parentNode.insertBefore(emptyDiv, spoiler.nextSibling);
                } else {
                    spoiler.parentNode.appendChild(emptyDiv);
                }
            }
        });
    });

    // Обработчик для клавиш - создаем пустой блок при нажатии Enter в конце spoiler
    contentVisual.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const sel = window.getSelection();
            if (sel && sel.rangeCount > 0) {
                const range = sel.getRangeAt(0);
                let node = range.startContainer;
                
                // Ищем родительский spoiler-block
                while (node && node !== contentVisual) {
                    if (node.classList && node.classList.contains('spoiler-block')) {
                        // Проверяем, находимся ли мы в конце spoiler
                        const spoilerContent = node.querySelector('.spoiler-content');
                        if (spoilerContent && spoilerContent.contains(range.startContainer)) {
                            // Проверяем, есть ли после spoiler элемент
                            if (!node.nextSibling || (node.nextSibling.nodeType === Node.TEXT_NODE && node.nextSibling.textContent.trim() === '')) {
                                e.preventDefault();
                                const emptyDiv = document.createElement('div');
                                emptyDiv.innerHTML = '<br>';
                                node.parentNode.insertBefore(emptyDiv, node.nextSibling);
                                
                                // Устанавливаем курсор в новый блок
                                const newRange = document.createRange();
                                newRange.setStart(emptyDiv, 0);
                                newRange.collapse(true);
                                sel.removeAllRanges();
                                sel.addRange(newRange);
                                return;
                            }
                        }
                        break;
                    }
                    node = node.parentNode;
                }
            }
        }
    });

    menu.addEventListener('click', function(e) {
        var item = e.target.closest('.editor-context-item');
        if (!item || !item.dataset.cmd) return;
        e.preventDefault();
        e.stopPropagation();
        var cmd = item.dataset.cmd;
        if (cmd === 'paste' || cmd === 'copy' || cmd === 'cut' || cmd === 'delete') {
            if (cmd === 'delete' && contextMenuImageTarget && contextMenuImageTarget.parentNode) {
                contextMenuImageTarget.parentNode.removeChild(contextMenuImageTarget);
                contextMenuImageTarget = null;
            } else if (editorMode === 'visual') {
                contentVisual.focus();
                document.execCommand(cmd, false, null);
            } else {
                if (cmd === 'copy') document.execCommand('copy');
                if (cmd === 'cut') document.execCommand('cut');
                if (cmd === 'paste') document.execCommand('paste');
                if (cmd === 'delete' && contentTa) {
                    var start = colorInsertStart;
                    var end = colorInsertEnd;
                    contentTa.value = contentTa.value.substring(0, start) + contentTa.value.substring(end);
                    contentTa.focus();
                }
            }
        } else if (cmd === 'link') {
            addLink();
        } else if (cmd === 'image') {
            showImageUpload();
        } else if (cmd === 'list') {
            insertList();
        } else if (cmd === 'addRow') {
            addTableRow();
        } else if (cmd === 'deleteRow') {
            deleteTableRow();
        } else if (cmd === 'addColumn') {
            addTableColumn();
        } else if (cmd === 'deleteColumn') {
            deleteTableColumn();
        } else if (cmd === 'colorCell') {
            openCellColorDialog();
        } else if (cmd === 'deleteTable') {
            deleteTable();
        }
        hideMenu();
    });

    document.addEventListener('click', hideMenu);
    document.addEventListener('contextmenu', function(e) {
        if (!menu.contains(e.target)) hideMenu();
    });
})();

function insertImage(url, width, height, widthUnit, heightUnit, caption = '', noBorderRadius = false) {
    if (window.enableMarkdown && editorMode === 'code') {
        const mdImg = `![${caption || 'Изображение'}](${url})`;
        const ta = document.getElementById('content');
        const cursorPos = ta.selectionStart;
        ta.value = ta.value.substring(0, cursorPos) + mdImg + ta.value.substring(cursorPos);
        saveToHistory();
        closeImageDialog();
        return;
    }
    const radiusStyle = noBorderRadius ? 'border-radius: 0px !important;' : 'border-radius: 8px;';
    const imgStyle = `width: ${width}${widthUnit}; max-width: 100%; height: auto; display: block; margin: 0; ` + 
                    (height ? `height: ${height}${heightUnit};` : '') + radiusStyle;
    const classAttr = `blog-image${noBorderRadius ? ' no-radius' : ''}`;
    const imgTag = wrapImageWithHint(`<img src="${url}" style="${imgStyle}" class="${classAttr}">`, caption);
    
    if (editorMode === 'code') {
        const ta = document.getElementById('content');
        const cursorPos = ta.selectionStart;
        ta.value = ta.value.substring(0, cursorPos) + imgTag + '\n' + ta.value.substring(cursorPos);
    } else {
        insertImageBlockAtCaret(imgTag);
    }
    saveToHistory();
    closeImageDialog();
}

function closeImageDialog() {
    document.getElementById('imageUploadDialog').style.display = 'none';
    document.getElementById('imageFile').value = '';
    document.getElementById('imageUrl').value = '';
    document.getElementById('imageCaption').value = '';
    document.getElementById('customWidth').value = '';
    document.getElementById('customHeight').value = '';
    document.getElementById('gridLayout').value = '';
    const noRadiusChk = document.getElementById('noBorderRadius');
    if (noRadiusChk) noRadiusChk.checked = localStorage.getItem('noBorderRadius') === 'true';
    const insertGalleryChk = document.getElementById('insertGallery');
    if (insertGalleryChk) insertGalleryChk.checked = false;
    const insertGalleryContainer = document.getElementById('insertGalleryContainer');
    if (insertGalleryContainer) insertGalleryContainer.style.display = 'none';
    document.querySelector('input[name="imageSource"][value="file"]').checked = true;
    document.getElementById('fileUploadContainer').style.display = 'block';
    document.getElementById('imageGridPreviewContainer').style.display = 'none';
    document.getElementById('imageGridPreviewContainer').innerHTML = '';
    document.getElementById('urlContainer').style.display = 'none';
    gridTileFiles = {};
    selectedImageFiles = [];
    const previewContainer = document.getElementById('imageFilesPreview');
    if (previewContainer) {
        previewContainer.style.display = 'none';
        previewContainer.innerHTML = '';
    }
    const dropzoneText = document.getElementById('imageDropzoneText');
    if (dropzoneText) {
        dropzoneText.textContent = 'Выберите изображения или перетащите их сюда';
    }
}

    // Функции для работы с размером шрифта
    function setFontSize(size) {
        if (editorMode === 'code') {
            var ta = document.getElementById('content');
            var start = colorInsertStart;
            var end = colorInsertEnd;
            var selectedText = ta.value.substring(start, end);
            if (selectedText) {
                var fontSpan = '<span style="font-size: ' + size + 'px;">' + selectedText + '</span>';
                ta.value = ta.value.substring(0, start) + fontSpan + ta.value.substring(end);
                ta.focus();
                saveToHistory();
            }
        } else {
            var text = (savedRange && savedRange.toString()) || document.getSelection().toString();
            if (text) {
                var html = '<span style="font-size: ' + size + 'px;">' + text + '</span>';
                insertHtmlAtCaret(html);
                saveToHistory();
            }
        }
    }

    function closeFontSizeDialog() {
        document.getElementById('fontSizeDialog').style.display = 'none';
        document.getElementById('customFontSize').value = '';
    }

    function setCustomFontSize() {
        const size = document.getElementById('customFontSize').value;
        if (size && size >= 8 && size <= 72) {
            setFontSize(size);
            closeFontSizeDialog();
        } else {
            showNotification('Пожалуйста, введите размер от 8 до 72 пикселей', 'warning');
        }
    }

    // Функции для работы с медиа
    let isMediaDragDropInitialized = false;

    function initMediaDragDrop() {
        if (isMediaDragDropInitialized) return;
        
        const videoDropzone = document.getElementById('videoDropzone');
        const audioDropzone = document.getElementById('audioDropzone');
        
        if (videoDropzone) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                videoDropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });
            
            ['dragenter', 'dragover'].forEach(eventName => {
                videoDropzone.addEventListener(eventName, () => {
                    videoDropzone.classList.add('drag-over');
                }, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                videoDropzone.addEventListener(eventName, () => {
                    videoDropzone.classList.remove('drag-over');
                }, false);
            });
            
            videoDropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files && files[0]) {
                    uploadVideoFileDirect(files[0]);
                }
            }, false);
        }
        
        if (audioDropzone) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                audioDropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });
            
            ['dragenter', 'dragover'].forEach(eventName => {
                audioDropzone.addEventListener(eventName, () => {
                    audioDropzone.classList.add('drag-over');
                }, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                audioDropzone.addEventListener(eventName, () => {
                    audioDropzone.classList.remove('drag-over');
                }, false);
            });
            
            audioDropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files && files[0]) {
                    uploadAudioFileDirect(files[0]);
                }
            }, false);
        }
        
        isMediaDragDropInitialized = true;
    }

    function uploadVideoFileDirect(file) {
        if (!file) return;
        if (!file.type.startsWith('video/')) {
            showNotification('Пожалуйста, выберите видео файл', 'warning');
            return;
        }
        
        const MAX_VIDEO_SIZE = 100 * 1024 * 1024; // 100 MB
        if (file.size > MAX_VIDEO_SIZE) {
            showNotification(`Видео файл слишком большой (${(file.size / 1024 / 1024).toFixed(1)} МБ). Максимальный размер: 100 МБ.`, 'error');
            return;
        }
        
        const filenameEl = document.getElementById('videoFileName');
        if (filenameEl) {
            filenameEl.textContent = `Загрузка: ${file.name}...`;
            filenameEl.style.display = 'block';
        }
        
        const formData = new FormData();
        formData.append('video', file);
        
        fetch('upload_video.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Видео файл загружен', 'success');
                loadVideoFilesList();
            } else {
                showNotification('Ошибка: ' + data.error, 'error');
            }
            if (filenameEl) filenameEl.style.display = 'none';
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showNotification('Ошибка загрузки файла', 'error');
            if (filenameEl) filenameEl.style.display = 'none';
        });
    }

    function uploadAudioFileDirect(file) {
        if (!file) return;
        if (!file.type.startsWith('audio/')) {
            showNotification('Пожалуйста, выберите аудио файл', 'warning');
            return;
        }
        
        const MAX_AUDIO_SIZE = 50 * 1024 * 1024; // 50 MB
        if (file.size > MAX_AUDIO_SIZE) {
            showNotification(`Аудио файл слишком большой (${(file.size / 1024 / 1024).toFixed(1)} МБ). Максимальный размер: 50 МБ.`, 'error');
            return;
        }
        
        const filenameEl = document.getElementById('audioFileName');
        if (filenameEl) {
            filenameEl.textContent = `Загрузка: ${file.name}...`;
            filenameEl.style.display = 'block';
        }
        
        const formData = new FormData();
        formData.append('audio', file);
        
        fetch('upload_audio.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Аудио файл загружен', 'success');
                loadAudioFilesList();
            } else {
                showNotification('Ошибка: ' + data.error, 'error');
            }
            if (filenameEl) filenameEl.style.display = 'none';
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showNotification('Ошибка загрузки файла', 'error');
            if (filenameEl) filenameEl.style.display = 'none';
        });
    }

    window.handleMediaFileChange = function(input, type) {
        if (input.files && input.files[0]) {
            if (type === 'video') {
                uploadVideoFileDirect(input.files[0]);
            } else if (type === 'audio') {
                uploadAudioFileDirect(input.files[0]);
            }
        }
        input.value = '';
    };

    function showMediaDialog() {
        document.getElementById('mediaDialog').style.display = 'block';
        initMediaDragDrop();
        
        const mediaTypeRadios = document.querySelectorAll('input[name="mediaType"]');
        mediaTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('videoUrlSection').style.display = 'none';
                document.getElementById('videoFileSection').style.display = 'none';
                document.getElementById('audioMediaSection').style.display = 'none';
                document.getElementById('audioStreamSection').style.display = 'none';
                
                if (this.value === 'video-url') {
                    document.getElementById('videoUrlSection').style.display = 'block';
                } else if (this.value === 'video-file') {
                    document.getElementById('videoFileSection').style.display = 'block';
                    loadVideoFilesList();
                } else if (this.value === 'audio') {
                    document.getElementById('audioMediaSection').style.display = 'block';
                    loadAudioFilesList();
                } else if (this.value === 'audio-stream') {
                    document.getElementById('audioStreamSection').style.display = 'block';
                }
            });
        });
    }

    function closeMediaDialog() {
        document.getElementById('mediaDialog').style.display = 'none';
        document.getElementById('mediaUrl').value = '';
        document.getElementById('videoFile').value = '';
        document.getElementById('audioFile').value = '';
        document.getElementById('audioStreamUrl').value = '';
        // Сбрасываем на видео URL
        document.querySelector('input[name="mediaType"][value="video-url"]').checked = true;
        document.getElementById('videoUrlSection').style.display = 'block';
        document.getElementById('videoFileSection').style.display = 'none';
        document.getElementById('audioMediaSection').style.display = 'none';
        document.getElementById('audioStreamSection').style.display = 'none';
        
        const videoFileName = document.getElementById('videoFileName');
        if (videoFileName) {
            videoFileName.textContent = '';
            videoFileName.style.display = 'none';
        }
        const audioFileName = document.getElementById('audioFileName');
        if (audioFileName) {
            audioFileName.textContent = '';
            audioFileName.style.display = 'none';
        }
    }

    function insertMedia() {
        const mediaType = document.querySelector('input[name="mediaType"]:checked').value;
        
        if (mediaType === 'video-url') {
            const url = document.getElementById('mediaUrl').value.trim();
            if (!url) {
                showNotification('Пожалуйста, введите URL видео', 'warning');
                return;
            }

            let embedCode = '';

            // Определяем тип медиа по URL
            if (url.includes('youtube.com') || url.includes('youtu.be')) {
                const youtubeId = extractYoutubeId(url);
                embedCode = `<iframe width="560" height="315" src="https://www.youtube.com/embed/${youtubeId}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
            } else if (url.includes('vimeo.com')) {
                const vimeoId = extractVimeoId(url);
                embedCode = `<iframe width="560" height="315" src="https://player.vimeo.com/video/${vimeoId}" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>`;
            } else {
                // Встраиваем как iframe
                embedCode = `<iframe width="560" height="315" src="${url}" frameborder="0" sandbox="allow-same-origin allow-scripts allow-popups" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
            }

            if (editorMode === 'code') {
                const ta = document.getElementById('content');
                const cursorPos = ta.selectionStart;
                ta.value = ta.value.substring(0, cursorPos) + embedCode + ta.value.substring(cursorPos);
            } else {
                insertHtmlAtCaret(wrapMediaWithControls(embedCode, 'iframe'));
            }
            
            saveToHistory();
            closeMediaDialog();
        } else if (mediaType === 'audio-stream') {
            const url = document.getElementById('audioStreamUrl').value.trim();
            if (!url) {
                showNotification('Пожалуйста, введите URL аудиопотока', 'warning');
                return;
            }

            const audioElement = `<audio controls style="width: 100%; max-width: 600px; margin: 10px 0;"><source src="${url}">Ваш браузер не поддерживает аудио элемент.</audio>`;

            if (editorMode === 'code') {
                const ta = document.getElementById('content');
                const cursorPos = ta.selectionStart;
                ta.value = ta.value.substring(0, cursorPos) + audioElement + '\n' + ta.value.substring(cursorPos);
            } else {
                insertHtmlAtCaret(wrapMediaWithControls(audioElement, 'audio'));
            }
            
            saveToHistory();
            closeMediaDialog();
        }
        // Для аудио вставка происходит при клике на файл в списке
    }

    function uploadAudioFile() {
        const fileInput = document.getElementById('audioFile');
        const file = fileInput.files[0];
        
        if (!file) {
            showNotification('Пожалуйста, выберите аудио файл', 'warning');
            return;
        }
        
        // Проверяем тип файла
        if (!file.type.startsWith('audio/')) {
            showNotification('Пожалуйста, выберите аудио файл', 'warning');
            return;
        }
        
        const formData = new FormData();
        formData.append('audio', file);
        
        fetch('upload_audio.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Аудио файл загружен', 'success');
                fileInput.value = '';
                loadAudioFilesList();
            } else {
                showNotification('Ошибка: ' + data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showNotification('Ошибка загрузки файла', 'error');
        });
    }

    function loadAudioFilesList() {
        fetch('get_audio_files.php')
            .then(response => response.json())
            .then(data => {
                const list = document.getElementById('audioFilesList');
                
                if (data.success && data.files.length > 0) {
                    list.innerHTML = data.files.map(file => `
                        <div style="padding: 10px 12px; margin-bottom: 8px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background 0.2s;" 
                             onmouseover="this.style.background='rgba(128,128,128,0.06)'" onmouseout="this.style.background='transparent'"
                             onclick="insertAudioFile('${file.path}', '${file.name}')">
                            <div style="min-width: 0; flex: 1; padding-right: 10px;">
                                <div style="color: var(--text-color); font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">🎵 ${file.name}</div>
                                <div style="color: var(--text-color); opacity: 0.6; font-size: 12px; margin-top: 2px;">${formatFileSize(file.size)}</div>
                            </div>
                            <button onclick="event.stopPropagation(); deleteAudioFile('${file.name}')" 
                                    style="padding: 6px 12px; font-size: 12px; border-radius: 6px; border: 1px solid rgba(220, 53, 69, 0.4); background: transparent; color: #dc3545; cursor: pointer; transition: all 0.2s;"
                                    onmouseover="this.style.background='rgba(220, 53, 69, 0.1)'; this.style.borderColor='#dc3545'"
                                    onmouseout="this.style.background='transparent'; this.style.borderColor='rgba(220, 53, 69, 0.4)'">
                                Удалить
                            </button>
                        </div>
                    `).join('');
                } else {
                    list.innerHTML = '<div style="color: var(--text-color); opacity: 0.6;">Нет загруженных аудио файлов</div>';
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                document.getElementById('audioFilesList').innerHTML = '<div style="color: #f44336;">Ошибка загрузки списка</div>';
            });
    }

    function insertAudioFile(filePath, fileName) {
        const audioElement = `<audio controls style="width: 100%; max-width: 600px; margin: 10px 0;"><source src="${filePath}" type="audio/mpeg">Ваш браузер не поддерживает аудио элемент.</audio>`;
        
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const cursorPos = ta.selectionStart;
            ta.value = ta.value.substring(0, cursorPos) + audioElement + '\n' + ta.value.substring(cursorPos);
        } else {
            // Вставляем аудио элемент
            const wrappedAudioHtml = wrapMediaWithControls(audioElement, 'audio');
            const ve = document.getElementById('contentVisual');
            ve.focus();
            const sel = window.getSelection();
            let range = null;
            
            if (savedRange && ve.contains(savedRange.commonAncestorContainer)) {
                range = savedRange;
            } else if (sel && sel.rangeCount > 0) {
                range = sel.getRangeAt(0);
            }
            
            if (!range) {
                ve.insertAdjacentHTML('beforeend', wrappedAudioHtml);
                const emptyDiv = document.createElement('div');
                emptyDiv.innerHTML = '<br>';
                ve.appendChild(emptyDiv);
            } else {
                range.deleteContents();
                
                // Создаем аудио элемент
                const temp = document.createElement('div');
                temp.innerHTML = wrappedAudioHtml;
                const audioNode = temp.firstChild;
                
                // Создаем пустой блок для курсора
                const emptyDiv = document.createElement('div');
                emptyDiv.innerHTML = '<br>';
                
                // Вставляем аудио
                range.insertNode(audioNode);
                
                // Вставляем пустой блок после аудио
                if (audioNode.nextSibling) {
                    audioNode.parentNode.insertBefore(emptyDiv, audioNode.nextSibling);
                } else {
                    audioNode.parentNode.appendChild(emptyDiv);
                }
                
                // Устанавливаем курсор в пустой блок
                const newRange = document.createRange();
                newRange.setStart(emptyDiv, 0);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
            }
        }
        
        saveToHistory();
        closeMediaDialog();
        showNotification('Аудио файл добавлен в статью', 'success');
    }

    function deleteAudioFile(fileName) {
        showConfirm('Удалить аудио файл?').then(result => {
            if (!result) return;
            
            fetch('delete_audio.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ filename: fileName })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Аудио файл удален', 'success');
                    loadAudioFilesList();
                } else {
                    showNotification('Ошибка: ' + data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                showNotification('Ошибка удаления файла', 'error');
            });
        });
    }

    // Функции для работы с видео файлами
    function uploadVideoFile() {
        const fileInput = document.getElementById('videoFile');
        const file = fileInput.files[0];
        
        if (!file) {
            showNotification('Пожалуйста, выберите видео файл', 'warning');
            return;
        }
        
        // Проверяем тип файла
        if (!file.type.startsWith('video/')) {
            showNotification('Пожалуйста, выберите видео файл', 'warning');
            return;
        }
        
        const formData = new FormData();
        formData.append('video', file);
        
        fetch('upload_video.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Видео файл загружен', 'success');
                fileInput.value = '';
                loadVideoFilesList();
            } else {
                showNotification('Ошибка: ' + data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showNotification('Ошибка загрузки файла', 'error');
        });
    }

    function loadVideoFilesList() {
        fetch('get_video_files.php')
            .then(response => response.json())
            .then(data => {
                const list = document.getElementById('videoFilesList');
                
                if (data.success && data.files.length > 0) {
                    list.innerHTML = data.files.map(file => `
                        <div style="padding: 10px 12px; margin-bottom: 8px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background 0.2s;" 
                             onmouseover="this.style.background='rgba(128,128,128,0.06)'" onmouseout="this.style.background='transparent'"
                             onclick="insertVideoFile('${file.path}', '${file.name}')">
                            <div style="min-width: 0; flex: 1; padding-right: 10px;">
                                <div style="color: var(--text-color); font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">🎬 ${file.name}</div>
                                <div style="color: var(--text-color); opacity: 0.6; font-size: 12px; margin-top: 2px;">${formatFileSize(file.size)}</div>
                            </div>
                            <button onclick="event.stopPropagation(); deleteVideoFile('${file.name}')" 
                                    style="padding: 6px 12px; font-size: 12px; border-radius: 6px; border: 1px solid rgba(220, 53, 69, 0.4); background: transparent; color: #dc3545; cursor: pointer; transition: all 0.2s;"
                                    onmouseover="this.style.background='rgba(220, 53, 69, 0.1)'; this.style.borderColor='#dc3545'"
                                    onmouseout="this.style.background='transparent'; this.style.borderColor='rgba(220, 53, 69, 0.4)'">
                                Удалить
                            </button>
                        </div>
                    `).join('');
                } else {
                    list.innerHTML = '<div style="color: var(--text-color); opacity: 0.6;">Нет загруженных видео файлов</div>';
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                document.getElementById('videoFilesList').innerHTML = '<div style="color: #f44336;">Ошибка загрузки списка</div>';
            });
    }

    function insertVideoFile(filePath, fileName) {
        const videoElement = `<video controls style="width: 100%; max-width: 800px; margin: 10px 0;"><source src="${filePath}" type="video/mp4">Ваш браузер не поддерживает видео элемент.</video>`;
        
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const cursorPos = ta.selectionStart;
            ta.value = ta.value.substring(0, cursorPos) + videoElement + '\n' + ta.value.substring(cursorPos);
        } else {
            // Вставляем видео элемент
            const wrappedVideoHtml = wrapMediaWithControls(videoElement, 'video');
            const ve = document.getElementById('contentVisual');
            ve.focus();
            const sel = window.getSelection();
            let range = null;
            
            if (savedRange && ve.contains(savedRange.commonAncestorContainer)) {
                range = savedRange;
            } else if (sel && sel.rangeCount > 0) {
                range = sel.getRangeAt(0);
            }
            
            if (!range) {
                ve.insertAdjacentHTML('beforeend', wrappedVideoHtml);
                const emptyDiv = document.createElement('div');
                emptyDiv.innerHTML = '<br>';
                ve.appendChild(emptyDiv);
            } else {
                range.deleteContents();
                
                // Создаем видео элемент
                const temp = document.createElement('div');
                temp.innerHTML = wrappedVideoHtml;
                const videoNode = temp.firstChild;
                
                // Создаем пустой блок для курсора
                const emptyDiv = document.createElement('div');
                emptyDiv.innerHTML = '<br>';
                
                // Вставляем видео
                range.insertNode(videoNode);
                
                // Вставляем пустой блок после видео
                if (videoNode.nextSibling) {
                    videoNode.parentNode.insertBefore(emptyDiv, videoNode.nextSibling);
                } else {
                    videoNode.parentNode.appendChild(emptyDiv);
                }
                
                // Устанавливаем курсор в пустой блок
                const newRange = document.createRange();
                newRange.setStart(emptyDiv, 0);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
            }
        }
        
        saveToHistory();
        closeMediaDialog();
        showNotification('Видео файл добавлен в статью', 'success');
    }

    function deleteVideoFile(fileName) {
        showConfirm('Удалить видео файл?').then(result => {
            if (!result) return;
            
            fetch('delete_video.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ filename: fileName })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Видео файл удален', 'success');
                    loadVideoFilesList();
                } else {
                    showNotification('Ошибка: ' + data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                showNotification('Ошибка удаления файла', 'error');
            });
        });
    }

    function deleteAudioFile(fileName) {
        showConfirm('Удалить аудио файл?').then(result => {
            if (!result) return;
            
            fetch('delete_audio.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ filename: fileName })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Аудио файл удален', 'success');
                    loadAudioFilesList();
                } else {
                    showNotification('Ошибка: ' + data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                showNotification('Ошибка удаления файла', 'error');
            });
        });
    }

// Вспомогательные функции для извлечения ID
function extractYoutubeId(url) {
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    const match = url.match(regExp);
    return (match && match[2].length === 11) ? match[2] : null;
}

function extractVimeoId(url) {
    const regExp = /vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|)(\d+)(?:$|\/|\?)/;
    const match = url.match(regExp);
    return match ? match[3] : null;
}

    // Функции для работы со spoiler
    // Переменная для хранения выделенного текста для spoiler
    let savedSpoilerText = '';
    let savedSpoilerRange = null;

    function openSpoilerDialog() {
        savedSpoilerText = '';
        savedSpoilerRange = null;
        
        if (editorMode === 'visual') {
            const sel = window.getSelection();
            if (sel && sel.rangeCount > 0) {
                const range = sel.getRangeAt(0);
                savedSpoilerRange = range.cloneRange();
                const container = document.createElement('div');
                container.appendChild(range.cloneContents());
                savedSpoilerText = container.innerHTML;
            }
        } else if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            savedSpoilerText = ta.value.substring(start, end);
        }
        
        document.getElementById('spoilerDialog').style.display = 'block';
        document.getElementById('spoilerTitle').value = '';
        document.getElementById('spoilerTitle').focus();
    }

    function closeSpoilerDialog() {
        document.getElementById('spoilerDialog').style.display = 'none';
        savedSpoilerText = '';
        savedSpoilerRange = null;
    }

    function insertSpoiler() {
        const title = document.getElementById('spoilerTitle').value.trim() || 'Подробности';
        
        let selectedText = savedSpoilerText || 'Содержимое блока';
        
        const spoilerHtml = `<details class="spoiler-block"><summary class="spoiler-title">${title}</summary><div class="spoiler-content">${selectedText}</div></details>`;
        
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            const before = ta.value.substring(0, start);
            const after = ta.value.substring(end);
            ta.value = before + spoilerHtml + '\n' + after;
        } else {
            // Восстанавливаем сохраненный range если есть
            if (savedSpoilerRange) {
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(savedSpoilerRange);
                savedSpoilerRange.deleteContents();
            }
            insertImageBlockAtCaret(spoilerHtml);
        }
        
        saveToHistory();
        closeSpoilerDialog();
    }

    // Функции для работы с маркером
    let savedMarkerText = '';
    let savedMarkerRange = null;
    let selectedMarkerStyle = 'straight';

    function openMarkerDialog() {
        savedMarkerText = '';
        savedMarkerRange = null;
        
        if (editorMode === 'visual') {
            const sel = window.getSelection();
            if (sel && sel.rangeCount > 0) {
                const range = sel.getRangeAt(0);
                savedMarkerRange = range.cloneRange();
                savedMarkerText = range.toString();
            }
        } else if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            savedMarkerText = ta.value.substring(start, end);
        }
        
        if (!savedMarkerText) {
            showNotification('Выделите текст для применения маркера', 'warning');
            return;
        }
        
        document.getElementById('markerDialog').style.display = 'block';
        
        // Добавляем обработчики на кнопки стилей
        const styleBtns = document.querySelectorAll('.marker-style-btn');
        styleBtns.forEach(btn => {
            btn.onclick = function() {
                styleBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                selectedMarkerStyle = this.getAttribute('data-style');
            };
        });
        
        // Добавляем обработчики на кнопки цветов
        const colorBtns = document.querySelectorAll('.marker-color-btn');
        colorBtns.forEach(btn => {
            btn.onclick = function() {
                const color = this.getAttribute('data-color');
                insertMarker(color, selectedMarkerStyle);
            };
        });
    }

    function closeMarkerDialog() {
        document.getElementById('markerDialog').style.display = 'none';
        savedMarkerText = '';
        savedMarkerRange = null;
    }

    function insertMarker(color, style) {
        if (!savedMarkerText) {
            closeMarkerDialog();
            return;
        }
        
        // Определяем название цвета для data-атрибута
        const colorNames = {
            '#ffeb3b': 'yellow',
            '#4caf50': 'green',
            '#2196f3': 'blue',
            '#ff9800': 'orange',
            '#e91e63': 'pink',
            '#9c27b0': 'purple'
        };
        const colorName = colorNames[color] || 'yellow';
        
        const markerHtml = `<mark data-marker-color="${colorName}" data-marker-style="${style}">${savedMarkerText}</mark>`;
        
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            const before = ta.value.substring(0, start);
            const after = ta.value.substring(end);
            ta.value = before + markerHtml + after;
            // Устанавливаем курсор после маркера
            const newPos = start + markerHtml.length;
            ta.setSelectionRange(newPos, newPos);
            ta.focus();
        } else {
            if (savedMarkerRange) {
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(savedMarkerRange);
                savedMarkerRange.deleteContents();
                
                const temp = document.createElement('div');
                temp.innerHTML = markerHtml;
                const frag = document.createDocumentFragment();
                let node, lastNode;
                while ((node = temp.firstChild)) {
                    lastNode = frag.appendChild(node);
                }
                savedMarkerRange.insertNode(frag);
                
                // Устанавливаем курсор после маркера
                if (lastNode) {
                    const newRange = document.createRange();
                    newRange.setStartAfter(lastNode);
                    newRange.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(newRange);
                    
                    // Добавляем пробел после маркера чтобы выйти из форматирования
                    const space = document.createTextNode('\u200B'); // Zero-width space
                    newRange.insertNode(space);
                    newRange.setStartAfter(space);
                    newRange.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(newRange);
                }
            }
        }
        
        saveToHistory();
        closeMarkerDialog();
    }

    // Функции для работы с кодом
    var editingCodeBlockTarget = null;

    function insertCode() {
        editingCodeBlockTarget = null;
        const titleEl = document.getElementById('codeDialogTitle');
        if (titleEl) titleEl.textContent = 'Вставить код';
        const submitEl = document.getElementById('codeDialogSubmitBtn');
        if (submitEl) submitEl.textContent = 'Вставить';
        document.getElementById('codeLanguage').value = 'javascript';
        document.getElementById('codeInput').value = '';
        document.getElementById('codeDialog').style.display = 'block';
    }

    function openEditCodeBlockDialog(codeBlock) {
        editingCodeBlockTarget = codeBlock;
        const titleEl = document.getElementById('codeDialogTitle');
        if (titleEl) titleEl.textContent = 'Редактировать код';
        const submitEl = document.getElementById('codeDialogSubmitBtn');
        if (submitEl) submitEl.textContent = 'Сохранить';
        
        const lang = codeBlock.getAttribute('data-language') || 'javascript';
        document.getElementById('codeLanguage').value = lang;
        document.getElementById('codeInput').value = codeBlock.textContent;
        document.getElementById('codeDialog').style.display = 'block';
    }

    function closeCodeDialog() {
        document.getElementById('codeDialog').style.display = 'none';
        document.getElementById('codeInput').value = '';
        editingCodeBlockTarget = null;
    }

    function insertCodeBlock() {
        const code = document.getElementById('codeInput').value;
        const language = document.getElementById('codeLanguage').value;
        
        if (code.trim() === '') {
            showNotification('Пожалуйста, введите код', 'warning');
            return;
        }

        const escapedCode = code
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        if (editingCodeBlockTarget) {
            editingCodeBlockTarget.setAttribute('data-language', language);
            editingCodeBlockTarget.innerHTML = escapedCode;
            editingCodeBlockTarget.setAttribute('contenteditable', 'false');
            const wrap = editingCodeBlockTarget.closest('.blog-image-wrap');
            if (wrap) {
                wrap.setAttribute('data-media-type', 'pre');
            }
        } else {
            if (editorMode === 'code') {
                const codeBlock = `<pre class="code-block" data-language="${language}">${escapedCode}</pre>\n`;
                const ta = document.getElementById('content');
                const cursorPos = ta.selectionStart;
                ta.value = ta.value.substring(0, cursorPos) + codeBlock + ta.value.substring(cursorPos);
            } else {
                const codeBlock = wrapMediaWithControls(`<pre class="code-block" data-language="${language}" contenteditable="false">${escapedCode}</pre>`, 'pre');
                insertImageBlockAtCaret(codeBlock);
            }
        }
        
        saveToHistory();
        closeCodeDialog();
    }

    window.insertCode = insertCode;
    window.insertCodeBlock = insertCodeBlock;
    window.closeCodeDialog = closeCodeDialog;
    window.openEditCodeBlockDialog = openEditCodeBlockDialog;

    // Функции для управления статьями
    function toggleManagePosts() {
        const managePanel = document.getElementById('managePosts');
        managePanel.classList.toggle('active');
        
        if (managePanel.classList.contains('active')) {
            if (typeof updateBlogSelectorUI === 'function' && window.allBlogPaths) {
                updateBlogSelectorUI(window.allBlogPaths, window.currentActiveBlogPath);
            }
            loadPosts();
        } else {
            // Очищаем поле поиска при закрытии панели
            const searchInput = document.getElementById('postsSearchInput');
            if (searchInput) {
                searchInput.value = '';
            }
        }
    }

    function loadPosts() {
        // Добавляем timestamp для предотвращения кэширования
        fetch('serve_data.php?file=blog/posts-meta.json&t=' + Date.now())
            .then(response => response.json())
            .then(posts => {
                const postsList = document.getElementById('postsList');
                if (!posts || posts.length === 0) {
                    postsList.innerHTML = '<p class="manage-posts-empty">Пока нет статей</p>';
                    return;
                }
                const escapeHtml = function(str) {
                    if (!str) return '';
                    var div = document.createElement('div');
                    div.textContent = str;
                    return div.innerHTML;
                };
                
                // Сортируем статьи по ID в обратном порядке (новые первыми)
                const sortedPosts = [...posts].sort((a, b) => b.id - a.id);
                
                postsList.innerHTML = '<ul class="post-list">' +
                    sortedPosts.map(post => `
                        <li class="post-item">
                            <div class="post-item-title">${escapeHtml(post.title)}</div>
                            <span class="post-item-date">${escapeHtml(post.date)}</span>
                            <div class="post-item-actions">
                                <button type="button" class="edit-btn" onclick="editPost(${post.id})">Изменить</button>
                                <button type="button" class="additional-btn" onclick="openAdditionalSettings(${post.id}, '${escapeHtml(post.title)}')">Дополнительно</button>
                                <button type="button" class="delete-btn" onclick="deletePost(${post.id})">Удалить</button>
                            </div>
                        </li>
                    `).join('') +
                    '</ul>';
            })
            .catch(error => {
                console.error('Ошибка загрузки статей:', error);
                const postsList = document.getElementById('postsList');
                postsList.innerHTML = '<p class="manage-posts-empty">Пока нет статей</p>';
            });
    }

    function editPost(postId) {
        fetch('get_post_content.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: postId })
        })
        .then(response => {
            // Проверяем что ответ действительно JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Сервер вернул не JSON ответ. Проверьте настройки PHP и nginx.');
            }
            return response.text();
        })
        .then(text => {
            // Пытаемся распарсить JSON
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Ответ сервера:', text);
                throw new Error('Ошибка парсинга JSON. Ответ сервера: ' + text.substring(0, 200));
            }
        })
        .then(data => {
            if (data.success) {
                document.getElementById('title').value = data.title;

                let isMarkdown = false;
                let mdContent = '';
                if (data.content && data.content.includes('id="markdown-source"')) {
                    const match = data.content.match(/id="markdown-source"\s+data-base64="([^"]*)"/);
                    if (match && match[1]) {
                        try {
                            mdContent = decodeURIComponent(escape(atob(match[1])));
                            isMarkdown = true;
                        } catch (e) {
                            console.error('Error decoding markdown', e);
                        }
                    }
                }

                if (isMarkdown) {
                    window.enableMarkdown = true;
                    document.body.classList.add('markdown-mode');
                    const enableMarkdownCheck = document.getElementById('enableMarkdown');
                    if (enableMarkdownCheck) enableMarkdownCheck.checked = true;
                    
                    document.getElementById('content').value = mdContent;
                    const ve = document.getElementById('contentVisual');
                    if (ve) {
                        ve.contentEditable = 'true';
                        if (editorMode === 'visual') {
                            ve.innerHTML = parseMarkdownToHtml(mdContent);
                        }
                    }
                } else {
                    if (window.enableMarkdown) {
                        document.getElementById('content').value = data.content || '';
                        const ve = document.getElementById('contentVisual');
                        if (ve) {
                            ve.contentEditable = 'true';
                            if (editorMode === 'visual') {
                                ve.innerHTML = parseMarkdownToHtml(data.content || '');
                            }
                        }
                    } else {
                        window.enableMarkdown = false;
                        document.body.classList.remove('markdown-mode');
                        const enableMarkdownCheck = document.getElementById('enableMarkdown');
                        if (enableMarkdownCheck) enableMarkdownCheck.checked = false;
                        
                        let editedContent = formatHTML(data.content);
                        document.getElementById('content').value = editedContent;
                        const ve = document.getElementById('contentVisual');
                        if (ve) {
                            ve.contentEditable = 'true';
                            if (editorMode === 'visual') {
                                ve.innerHTML = editedContent;
                                wrapExistingEditorImages();
                                addColumnResizers();
                            }
                        }
                    }
                }

                if (editorMode === 'visual' && !window.enableMarkdown) {
                    // Убеждаемся что блоки кода имеют правильную высоту
                    setTimeout(() => {
                        const ve = document.getElementById('contentVisual');
                        if (!ve) return;
                        const codeBlocks = ve.querySelectorAll('.code-block');
                        codeBlocks.forEach(block => {
                            if (block.scrollHeight > 400) {
                                block.style.maxHeight = '400px';
                            } else {
                                block.style.maxHeight = 'none';
                            }
                        });
                    }, 100);
                }
                currentEditId = postId;
                const submitButton = document.getElementById('submitButton');
                submitButton.textContent = 'Сохранить изменения';
                submitButton.classList.add('editing');
                const floatingSaveBtn = document.getElementById('floatingSaveBtn');
                if (floatingSaveBtn) {
                    floatingSaveBtn.textContent = 'Сохранить изменения';
                    floatingSaveBtn.classList.add('editing');
                }
                toggleManagePosts();
                document.getElementById('blogForm').scrollIntoView();
                
                // Инициализируем историю с текущим состоянием
                clearHistory();
                saveToHistory();
            } else {
                showNotification('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки статьи:', error);
            showNotification('Ошибка при загрузке статьи: ' + error.message, 'error');
        });
    }

    let deletePostId = null;

    function filterPosts() {
        const searchInput = document.getElementById('postsSearchInput');
        const searchTerm = searchInput.value.toLowerCase().trim();
        const postItems = document.querySelectorAll('.post-item');
        
        let visibleCount = 0;
        
        postItems.forEach(item => {
            const title = item.querySelector('.post-item-title').textContent.toLowerCase();
            const date = item.querySelector('.post-item-date').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || date.includes(searchTerm)) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        
        // Показываем сообщение если ничего не найдено
        const postsList = document.getElementById('postsList');
        let emptyMessage = postsList.querySelector('.search-empty-message');
        
        if (visibleCount === 0 && searchTerm !== '') {
            if (!emptyMessage) {
                emptyMessage = document.createElement('p');
                emptyMessage.className = 'manage-posts-empty search-empty-message';
                emptyMessage.textContent = 'Ничего не найдено';
                postsList.appendChild(emptyMessage);
            }
        } else if (emptyMessage) {
            emptyMessage.remove();
        }
    }

    function deletePost(postId) {
        deletePostId = postId;
        const overlay = document.getElementById('deleteConfirmOverlay');
        overlay.classList.add('show');
    }
    
    function closeDeleteConfirm() {
        const overlay = document.getElementById('deleteConfirmOverlay');
        overlay.classList.remove('show');
        deletePostId = null;
    }
    
    function confirmDelete() {
        if (!deletePostId) return;
        
        fetch('delete_post.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: deletePostId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const message = data.renumbered 
                    ? 'Статья удалена, нумерация обновлена' 
                    : 'Статья успешно удалена';
                showNotification(message, 'success');
                loadPosts();
                closeDeleteConfirm();
            } else {
                showNotification('Ошибка при удалении статьи', 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка удаления:', error);
            showNotification('Ошибка при удалении статьи', 'error');
        });
    }

    // Обработчик отправки формы
    document.getElementById('modeVisualBtn').addEventListener('click', function(){ setMode('visual'); });
    document.getElementById('modeCodeBtn').addEventListener('click', function(){ setMode('code'); });
    setMode('visual');

    function handleSubmit(e) {
        if (e) e.preventDefault();
        if (isSavingArticle) return;
        
        const titleInput = document.getElementById('title');
        const title = titleInput.value.trim();
        
        if (!title) {
            showNotification('Пожалуйста, введите заголовок статьи', 'error');
            titleInput.focus();
            return;
        }

        const ta = document.getElementById('content');
        const ve = document.getElementById('contentVisual');
        
        let content;
        if (window.enableMarkdown) {
            if (editorMode === 'visual') {
                ta.value = convertHtmlToMarkdown(ve.innerHTML);
            }
            const rawMarkdown = ta.value;
            const base64Markdown = btoa(unescape(encodeURIComponent(rawMarkdown)));
            content = parseMarkdownToHtml(rawMarkdown) + `\n<script type="text/markdown" id="markdown-source" data-base64="${base64Markdown}"></script>`;
        } else if (editorMode === 'visual') {
            // Очищаем контент от элементов интерфейса редактора
            content = cleanContentForSave(ve.innerHTML);
            ta.value = content;
        } else {
            content = ta.value;
        }
        
        const submitButton = document.getElementById('submitButton');
        const floatingSaveBtn = document.getElementById('floatingSaveBtn');
        
        isSavingArticle = true;
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Сохранение...';
        }
        if (floatingSaveBtn) {
            floatingSaveBtn.disabled = true;
            floatingSaveBtn.textContent = 'Сохранение...';
        }
        
        const endpoint = currentEditId ? 'update_post.php' : 'save_post.php';
        const data = { title: title, content: content };
        if (currentEditId) { data.id = currentEditId; }
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(async (response) => {
            let payload;
            try { payload = await response.json(); } catch (_) { payload = null; }
            if (!response.ok || (payload && payload.success === false)) {
                throw new Error((payload && payload.error) || (payload && payload.message) || 'Server error');
            }
            showNotification(
                currentEditId ? 'Статья успешно обновлена!' : 'Статья успешно добавлена!',
                'success'
            );
            
            // Очищаем локальный черновик и сбрасываем признак несохраненных изменений
            clearLocalDraft();
            
            // Очищаем форму
            document.getElementById('blogForm').reset();
            
            // Очищаем визуальный редактор
            const ve = document.getElementById('contentVisual');
            if (ve) {
                ve.innerHTML = '';
            }
            
            // Очищаем текстовое поле
            const ta = document.getElementById('content');
            if (ta) {
                ta.value = '';
            }
            
            // Обновляем список статей
            loadPosts();
            
            currentEditId = null;
            if (submitButton) {
                submitButton.textContent = 'Сохранить';
                submitButton.classList.remove('editing');
            }
            if (floatingSaveBtn) {
                floatingSaveBtn.textContent = 'Сохранить';
                floatingSaveBtn.classList.remove('editing');
            }
            
            // Очищаем историю
            clearHistory();
        })
        .catch((err) => {
            const msg = err && err.message ? err.message : 'Ошибка при сохранении статьи';
            showNotification(msg, 'error');
        })
        .finally(() => {
            isSavingArticle = false;
            if (submitButton) {
                submitButton.disabled = false;
                if (currentEditId) {
                    submitButton.textContent = 'Сохранить изменения';
                } else {
                    submitButton.textContent = 'Сохранить';
                }
            }
            if (floatingSaveBtn) {
                floatingSaveBtn.disabled = false;
                if (currentEditId) {
                    floatingSaveBtn.textContent = 'Сохранить изменения';
                } else {
                    floatingSaveBtn.textContent = 'Сохранить';
                }
            }
        });
    }

    document.getElementById('blogForm').addEventListener('submit', handleSubmit);
    document.getElementById('submitButton').addEventListener('click', handleSubmit);

    // Обработчики изменения размера
    document.getElementById('imageSize').addEventListener('change', function(e) {
        const customInputs = document.getElementById('customSizeInputs');
        customInputs.style.display = e.target.value === 'custom' ? 'flex' : 'none';
        
        if (e.target.value !== 'custom') {
            document.getElementById('customWidth').value = '';
            document.getElementById('customHeight').value = '';
            document.getElementById('widthUnit').value = 'px';
            document.getElementById('heightUnit').value = 'px';
        }
    });

    document.getElementById('customFontSize').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            setCustomFontSize();
        }
    });
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
            var ve = document.getElementById('contentVisual');
            if (!ve) return;
            ve.focus();
            if (savedRange && ve.contains(savedRange.commonAncestorContainer)) {
                var sel = window.getSelection();
                if (sel) {
                    sel.removeAllRanges();
                    sel.addRange(savedRange);
                }
            }
            try {
                document.execCommand('styleWithCSS', false, true);
            } catch (e) {}
            document.execCommand('foreColor', false, color);
            var sel = window.getSelection();
            if (sel && sel.rangeCount > 0) {
                savedRange = sel.getRangeAt(0).cloneRange();
            }
            saveToHistory();
        }
    }

    (function initColorPalette() {
        var presetColors = ['#000000','#333333','#666666','#999999','#cccccc','#ffffff','#ff0000','#ff6600','#ff9900','#ffcc00','#99cc00','#00cc00','#00cccc','#0066ff','#0000ff','#6600cc','#9900cc','#cc0099','#ff0066','#8b4513','#a0522d','#cd853f','#deb887','#ff69b4','#ffc0cb','#add8e6','#98fb98','#f0e68c','#ffd700','#ff6347'];
        function fillGrid(gridId) {
            var grid = document.getElementById(gridId);
            if (!grid) return;
            grid.innerHTML = '';
            presetColors.forEach(function(hex) {
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
            document.querySelectorAll('.color-picker-wrap.is-open').forEach(function(w) { if (w !== wrap) w.classList.remove('is-open'); });
            wrap.classList.add('is-open');
        }
        function toggleColorPicker(wrap) {
            var isOpen = wrap.classList.contains('is-open');
            document.querySelectorAll('.color-picker-wrap.is-open').forEach(function(w) { w.classList.remove('is-open'); });
            if (!isOpen) {
                wrap.classList.add('is-open');
            }
        }
        function closeAllColorPickers() {
            document.querySelectorAll('.color-picker-wrap.is-open').forEach(function(w) { w.classList.remove('is-open'); });
        }
        function applyColorAndClose(hex, wrap) {
            setTextColor(hex);
            wrap.classList.remove('is-open');
            var preview = wrap.querySelector('.color-preview');
            if (preview) preview.style.background = hex;
        }
        
        // Функция для меню "Прочее"
        window.toggleMoreMenu = function() {
            const wrap = document.getElementById('moreMenuWrap');
            if (!wrap) return;
            
            const isOpen = wrap.classList.contains('is-open');
            
            // Закрываем другие открытые меню
            document.querySelectorAll('.color-picker-wrap.is-open, .font-size-picker-wrap.is-open, .font-family-picker-wrap.is-open').forEach(function(w) {
                w.classList.remove('is-open');
            });
            
            if (!isOpen) {
                wrap.classList.add('is-open');
            } else {
                wrap.classList.remove('is-open');
                // Закрываем подменю
                document.querySelectorAll('.more-menu-item.has-submenu').forEach(function(item) {
                    item.classList.remove('submenu-open');
                });
            }
        };

        ['colorPickerWrapMain'].forEach(function(id) {
            var wrap = document.getElementById(id);
            if (!wrap) return;
            var btn = wrap.querySelector('.color-picker-btn');
            var popover = wrap.querySelector('.color-palette-popover');
            var customInput = wrap.querySelector('input[type="color"]');
            if (btn) {
                btn.addEventListener('mousedown', function(e) {
                    if (editorMode === 'code') {
                        var ta = document.getElementById('content');
                        colorInsertStart = ta.selectionStart;
                        colorInsertEnd = ta.selectionEnd;
                    } else {
                        saveSelection();
                    }
                });
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleColorPicker(wrap);
                });
            }
            if (popover) {
                popover.addEventListener('mousedown', function(e) {
                    if (e.target.tagName !== 'INPUT') {
                        e.preventDefault();
                    }
                });
                popover.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var swatch = e.target.closest('.color-swatch');
                    if (swatch && swatch.dataset.color) applyColorAndClose(swatch.dataset.color, wrap);
                });
            }
            if (customInput) {
                ['change', 'input'].forEach(function(evtType) {
                    customInput.addEventListener(evtType, function() {
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
            document.querySelectorAll('.font-size-picker-wrap.is-open, .font-family-picker-wrap.is-open').forEach(function(w) { w.classList.remove('is-open'); });
        }
        function toggleWrap(wrap) {
            var isOpen = wrap.classList.contains('is-open');
            document.querySelectorAll('.font-size-picker-wrap.is-open, .font-family-picker-wrap.is-open').forEach(function(w) { w.classList.remove('is-open'); });
            if (!isOpen) {
                wrap.classList.add('is-open');
            }
        }
        function openWrap(wrap, closeOthers) {
            if (closeOthers) {
                document.querySelectorAll('.font-size-picker-wrap.is-open, .font-family-picker-wrap.is-open').forEach(function(w) { if (w !== wrap) w.classList.remove('is-open'); });
            }
            wrap.classList.add('is-open');
        }
        
        // Закрытие при клике вне меню
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.more-menu-wrap')) {
                const moreMenu = document.getElementById('moreMenuWrap');
                if (moreMenu) {
                    moreMenu.classList.remove('is-open');
                    // Закрываем подменю
                    document.querySelectorAll('.more-menu-item.has-submenu').forEach(function(item) {
                        item.classList.remove('submenu-open');
                    });
                }
            }
        });
        
        ['fontSizeWrapMain'].forEach(function(id) {
            var wrap = document.getElementById(id);
            if (!wrap) return;
            var btn = wrap.querySelector('.font-size-picker-btn');
            var popover = wrap.querySelector('.font-size-popover-inner');
            if (btn) {
                btn.addEventListener('mousedown', function() {
                    if (editorMode === 'code') {
                        var ta = document.getElementById('content');
                        colorInsertStart = ta.selectionStart;
                        colorInsertEnd = ta.selectionEnd;
                    }
                });
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleWrap(wrap);
                });
            }
            if (popover) {
                popover.addEventListener('click', function(e) {
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
        ['fontFamilyWrapMain'].forEach(function(id) {
            var wrap = document.getElementById(id);
            if (!wrap) return;
            var btn = wrap.querySelector('.font-family-picker-btn');
            var popover = wrap.querySelector('.font-family-popover-inner');
            if (btn) {
                btn.addEventListener('mousedown', function() {
                    if (editorMode === 'code') {
                        var ta = document.getElementById('content');
                        colorInsertStart = ta.selectionStart;
                        colorInsertEnd = ta.selectionEnd;
                    }
                });
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleWrap(wrap);
                });
            }
            if (popover) {
                popover.addEventListener('click', function(e) {
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
        if (editorMode === 'code') {
            var ta = document.getElementById('content');
            var start = colorInsertStart;
            var end = colorInsertEnd;
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
                var fontSpan = '<span style="font-family: \'' + font.replace(/'/g, "\\'") + '\';">​</span>';
                ta.value = ta.value.substring(0, start) + fontSpan + ta.value.substring(start);
                // Ставим курсор перед закрывающим тегом
                ta.selectionStart = ta.selectionEnd = start + fontSpan.length - 8;
                ta.focus();
            }
        } else {
            var ve = document.getElementById('contentVisual');
            if (!ve) return;
            
            ve.focus();
            
            // Применяем шрифт через execCommand
            document.execCommand('fontName', false, font);
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

    function insertImageGrid(layout) {
    const [cols, rows] = layout.split('x').map(Number);
    const gridStyle = `display: grid; grid-template-columns: repeat(${cols}, 1fr); gap: 10px;`;
    let imagesHTML = '';

    for (let i = 0; i < cols * rows; i++) {
        // Плейсхолдер для добавления реальных изображений
        imagesHTML += `<img src="" alt="Изображение ${i+1}" style="width: 100%; height: auto;">`;
    }

    const gridHTML = `<div style="${gridStyle}">${imagesHTML}</div>`;

    if (editorMode === 'code') {
        const ta = document.getElementById('content');
        const cursorPos = ta.selectionStart;
        ta.value = ta.value.substring(0, cursorPos) + gridHTML + '\n' + ta.value.substring(cursorPos);
    } else {
        insertImageBlockAtCaret(gridHTML);
    }
}

// Прилипающая строка кнопок: при прокрутке только панель форматирования фиксируется сверху
(function() {
    var sentinel = document.getElementById('formatBarSentinel');
    var placeholder = document.getElementById('formatBarPlaceholder');
    var formatBar = document.getElementById('formatBarRow');
    var floatingSaveBtn = document.getElementById('floatingSaveBtn');
    var submitButton = document.getElementById('submitButton');
    if (!sentinel || !placeholder || !formatBar) return;
    var stickyObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                formatBar.classList.remove('is-floating');
                placeholder.style.display = 'none';
                if (floatingSaveBtn) floatingSaveBtn.style.display = 'none';
            } else {
                var h = formatBar.offsetHeight;
                placeholder.style.height = h + 'px';
                placeholder.style.display = 'block';
                formatBar.classList.add('is-floating');
                if (floatingSaveBtn && submitButton) {
                    floatingSaveBtn.textContent = submitButton.textContent;
                    floatingSaveBtn.style.display = 'block';
                }
            }
        });
    }, { root: null, rootMargin: '0px', threshold: 0 });
    stickyObserver.observe(sentinel);
})();

// Подсветка активных кнопок при изменении выделения
document.addEventListener('selectionchange', function() {
    updateActiveButtons();
});

// ——— Проверка целостности файлов при загрузке ———
async function checkIntegrity() {
    try {
        const response = await fetch('check_integrity.php');
        const data = await response.json();
        
        if (!data.success && data.errors.length > 0) {
            const overlay = document.getElementById('integrityErrorOverlay');
            overlay.classList.add('show');
        }
    } catch (error) {
        console.error('Ошибка проверки целостности:', error);
    }
}

async function fixIntegrityErrors() {
    const button = document.querySelector('.integrity-error-button');
    button.textContent = 'Исправление...';
    button.disabled = true;
    
    try {
        const response = await fetch('fix_integrity.php');
        const data = await response.json();
        
        if (data.success) {
            showNotification('Все ошибки успешно исправлены!', 'success');
            
            const overlay = document.getElementById('integrityErrorOverlay');
            overlay.classList.remove('show');
            
            button.textContent = 'Исправить';
            button.disabled = false;
        } else {
            showNotification('Не удалось исправить некоторые ошибки: ' + data.errors.join(', '), 'error');
            button.textContent = 'Исправить';
            button.disabled = false;
        }
    } catch (error) {
        console.error('Ошибка исправления:', error);
        showNotification('Ошибка при исправлении файлов', 'error');
        button.textContent = 'Исправить';
        button.disabled = false;
    }
}



// ——— Менеджер бэкапов ———
async function openBackupManager() {
    const overlay = document.getElementById('backupManagerOverlay');
    const content = document.getElementById('backupManagerContent');
    
    overlay.classList.add('show');
    content.innerHTML = '<div class="backup-empty">Загрузка...</div>';
    
    try {
        const response = await fetch('get_backups.php');
        const data = await response.json();
        
        if (data.success) {
            if (Object.keys(data.backups).length === 0) {
                content.innerHTML = '<div class="backup-empty">Нет сохраненных бэкапов</div>';
            } else {
                renderBackups(data.backups);
            }
        } else {
            content.innerHTML = '<div class="backup-empty">Ошибка загрузки бэкапов</div>';
        }
    } catch (error) {
        console.error('Ошибка загрузки бэкапов:', error);
        content.innerHTML = '<div class="backup-empty">Ошибка загрузки бэкапов</div>';
    }
}

function closeBackupManager() {
    const overlay = document.getElementById('backupManagerOverlay');
    overlay.classList.remove('show');
}

function renderBackups(backups) {
    const content = document.getElementById('backupManagerContent');
    let html = '';
    
    for (const postId in backups) {
        const post = backups[postId];
        const isDeleted = post.deleted === true;
        const displayTitle = isDeleted 
            ? `🗑️ ${escapeHtml(post.postTitle)}` 
            : `Статья #${postId}: ${escapeHtml(post.postTitle)}`;
        
        html += `
            <div class="backup-post-group ${isDeleted ? 'deleted-post' : ''}" id="backup-group-${postId}">
                <div class="backup-post-header" onclick="toggleBackupGroup('${postId}')">
                    <h3 class="backup-post-title">${displayTitle}</h3>
                    <span class="backup-post-toggle">▼</span>
                </div>
                <div class="backup-list">
                    ${post.backups.map((backup, index) => `
                        <div class="backup-item">
                            <div class="backup-info">
                                <div class="backup-number">Бэкап #${backup.backupNumber}</div>
                                <div class="backup-date">${escapeHtml(backup.date)}</div>
                                ${isDeleted ? '<div class="backup-date" style="color: #d32f2f; font-weight: 600; margin-top: 4px;">Статья удалена: ' + escapeHtml(post.deletedAt || '') + '</div>' : ''}
                            </div>
                            <div class="backup-actions">
                                <button class="backup-btn view" onclick="viewBackup('${postId}', '${backup.filename}')">Посмотреть</button>
                                ${!isDeleted ? `<button class="backup-btn restore" onclick="restoreBackup('${postId}', '${backup.filename}', ${backup.backupNumber}, '${escapeHtml(backup.date)}')">Восстановить</button>` : ''}
                                <button class="backup-btn delete" onclick="deleteBackup('${postId}', '${backup.filename}', ${backup.backupNumber}, '${escapeHtml(backup.date)}')">Удалить</button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    content.innerHTML = html;
}

function toggleBackupGroup(postId) {
    const group = document.getElementById('backup-group-' + postId);
    if (group) {
        group.classList.toggle('expanded');
    }
}

async function viewBackup(postId, filename) {
    try {
        const response = await fetch('get_backup_content.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
            },
            body: JSON.stringify({ postId: postId, filename: filename })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Открываем в новом окне
            const newWindow = window.open('', '_blank');
            newWindow.document.write(data.content);
            newWindow.document.close();
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Ошибка просмотра бэкапа:', error);
        showNotification('Ошибка при просмотре бэкапа', 'error');
    }
}

// Восстановление бэкапа
function restoreBackup(postId, filename, backupNumber, backupDate) {
    showConfirm(
        `Вы действительно хотите восстановить статью из бэкапа #${backupNumber} от ${backupDate}? Текущая версия статьи в редакторе будет заменена.`,
        'Восстановить бэкап?'
    ).then(async (result) => {
        if (!result) return;
        
        showNotification('Восстановление бэкапа...', 'info');
        
        try {
            const response = await fetch('restore_backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json; charset=utf-8',
                },
                body: JSON.stringify({
                    postId: postId,
                    filename: filename
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showNotification('Бэкап успешно восстановлен', 'success');
                // Закрываем окно менеджера бэкапов
                closeBackupManager();
                // Перезагружаем страницу через секунду для отображения восстановленной статьи
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Ошибка: ' + data.error, 'error');
            }
        } catch (error) {
            console.error('Ошибка восстановления бэкапа:', error);
            showNotification('Ошибка при восстановлении бэкапа', 'error');
        }
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Удаление бэкапа
function deleteBackup(postId, filename, backupNumber, backupDate) {
    showConfirm(
        `Вы действительно хотите окончательно удалить бэкап #${backupNumber} от ${backupDate}? Это действие необратимо.`,
        'Удалить бэкап?'
    ).then(async (result) => {
        if (!result) return;
        
        showNotification('Удаление бэкапа...', 'info');
        
        try {
            const response = await fetch('delete_backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json; charset=utf-8',
                },
                body: JSON.stringify({
                    postId: postId,
                    filename: filename
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showNotification('Бэкап успешно удален', 'success');
                // Обновляем список бэкапов в окне менеджера
                openBackupManager();
            } else {
                showNotification('Ошибка: ' + data.error, 'error');
            }
        } catch (error) {
            console.error('Ошибка удаления бэкапа:', error);
            showNotification('Ошибка при удалении бэкапа', 'error');
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Обработчик для сохранения состояния галочки "Вставить как гиперссылку"
    const insertAsHyperlinkCheckbox = document.getElementById('insertAsHyperlink');
    if (insertAsHyperlinkCheckbox) {
        insertAsHyperlinkCheckbox.addEventListener('change', function() {
            localStorage.setItem('insertAsHyperlink', this.checked);
        });
    }
    
    // Загружаем настройки автосохранения при загрузке страницы
    loadAutosaveSettings();
    
    // Применяем настройки внешнего вида
    applyAppearanceSettings();
    
    // Применяем экспериментальные настройки
    applyExperimentalSettings();

    // Проверяем предупреждение о DEV сборке
    checkDevWarning();

    // Проверяем наличие несохраненного локального черновика
    setTimeout(checkLocalDraftOnStartup, 500);

    // Отслеживаем ввод в поле заголовка для локального сохранения
    const titleInputEl = document.getElementById('title');
    if (titleInputEl) {
        titleInputEl.addEventListener('input', function() {
            markEditorDirty();
        });
    }
});

// ——— Система includes ———
function openSaveInclude() {
    const overlay = document.getElementById('saveIncludeOverlay');
    const input = document.getElementById('includeNameInput');
    input.value = '';
    overlay.classList.add('show');
    
    // Закрываем меню "Прочее"
    const moreMenu = document.getElementById('moreMenuWrap');
    if (moreMenu) moreMenu.classList.remove('is-open');
    
    setTimeout(() => input.focus(), 100);
}

function closeSaveInclude() {
    const overlay = document.getElementById('saveIncludeOverlay');
    overlay.classList.remove('show');
}

async function confirmSaveInclude() {
    const input = document.getElementById('includeNameInput');
    const name = input.value.trim();
    
    if (!name) {
        showNotification('Введите название файла', 'warning');
        return;
    }
    
    // Получаем контент из редактора
    const ve = document.getElementById('contentVisual');
    const ta = document.getElementById('content');
    let content;
    
    if (editorMode === 'visual') {
        content = ve.innerHTML;
    } else {
        content = ta.value;
    }
    
    if (!content.trim()) {
        showNotification('Нет контента для сохранения', 'warning');
        return;
    }
    
    // Блокируем кнопку
    const saveBtn = document.querySelector('.save-include-btn.save');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Сохранение...';
    }
    
    try {
        const response = await fetch('save_include.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
            },
            body: JSON.stringify({ name: name, content: content })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Include сохранен: ' + (data.displayName || data.filename), 'success');
            includesListLoaded = false; // Сбрасываем флаг для перезагрузки списка
            closeSaveInclude();
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Ошибка сохранения include:', error);
        showNotification('Ошибка при сохранении include', 'error');
    } finally {
        // Разблокируем кнопку
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Сохранить';
        }
    }
}

let includesListLoaded = false;
let articlesListLoaded = false;
let draftsListLoaded = false;

// Функции для работы с черновиками
function saveDraft() {
    const title = document.getElementById('title').value.trim();
    let content = editorMode === 'visual' 
        ? document.getElementById('contentVisual').innerHTML 
        : document.getElementById('content').value;
    
    if (window.enableMarkdown) {
        if (editorMode === 'visual') {
            document.getElementById('content').value = convertHtmlToMarkdown(document.getElementById('contentVisual').innerHTML);
        }
        const rawMarkdown = document.getElementById('content').value;
        const base64Markdown = btoa(unescape(encodeURIComponent(rawMarkdown)));
        content = parseMarkdownToHtml(rawMarkdown) + `\n<script type="text/markdown" id="markdown-source" data-base64="${base64Markdown}"></script>`;
    } else if (editorMode === 'visual') {
        content = cleanContentForSave(content);
    }
    
    if (!title && !content) {
        showAlert('Нечего сохранять в черновик');
        return;
    }
    
    fetch('save_draft.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ title: title, content: content })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Черновик сохранен');
            draftsListLoaded = false; // Сбрасываем флаг чтобы перезагрузить список
        } else {
            showAlert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка сохранения черновика:', error);
        showAlert('Ошибка при сохранении черновика');
    });
    
    // Закрываем меню
    const moreMenu = document.getElementById('moreMenuWrap');
    if (moreMenu) moreMenu.classList.remove('is-open');
}

function toggleDraftsSubmenu(event) {
    event.stopPropagation();
    
    const button = event.currentTarget;
    const isOpen = button.classList.contains('submenu-open');
    
    document.querySelectorAll('.more-menu-item.has-submenu').forEach(btn => {
        if (btn !== button) {
            btn.classList.remove('submenu-open');
        }
    });
    
    if (!isOpen) {
        button.classList.add('submenu-open');
        loadDraftsList();
    } else {
        button.classList.remove('submenu-open');
    }
}

async function loadDraftsList() {
    const submenu = document.getElementById('draftsSubmenu');
    if (!submenu) return;
    
    try {
        const response = await fetch('get_drafts.php');
        const data = await response.json();
        
        if (data.success) {
            if (data.drafts.length === 0) {
                submenu.innerHTML = '<div class="more-submenu-empty">Нет черновиков</div>';
            } else {
                submenu.innerHTML = data.drafts.map(draft => {
                    const displayTitle = draft.title || 'Без названия';
                    const date = new Date(draft.timestamp * 1000).toLocaleString('ru-RU', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    return `<div class="draft-item-wrap">
                        <button type="button" class="more-submenu-item draft-load-btn" onclick="loadDraft('${draft.filename}')" title="${displayTitle}">
                            <div class="draft-title">${displayTitle}</div>
                            <div class="draft-date">${date}</div>
                        </button>
                        <button type="button" class="draft-delete-btn" onclick="deleteDraft('${draft.filename}', event)" title="Удалить черновик">×</button>
                    </div>`;
                }).join('');
            }
            draftsListLoaded = true;
        }
    } catch (error) {
        console.error('Ошибка загрузки черновиков:', error);
        submenu.innerHTML = '<div class="more-submenu-empty">Ошибка загрузки</div>';
    }
}

async function loadDraft(filename) {
    try {
        const response = await fetch('get_drafts.php');
        const data = await response.json();
        
        if (data.success) {
            const draft = data.drafts.find(d => d.filename === filename);
            
                if (draft) {
                    // Вставляем заголовок и контент
                    document.getElementById('title').value = draft.title || '';
                    
                    let isMarkdown = false;
                    let mdContent = '';
                    if (draft.content && draft.content.includes('id="markdown-source"')) {
                        const match = draft.content.match(/id="markdown-source"\s+data-base64="([^"]*)"/);
                        if (match && match[1]) {
                            try {
                                mdContent = decodeURIComponent(escape(atob(match[1])));
                                isMarkdown = true;
                            } catch (e) {
                                console.error('Error decoding markdown', e);
                            }
                        }
                    }
                    
                    if (isMarkdown) {
                        window.enableMarkdown = true;
                        document.body.classList.add('markdown-mode');
                        const enableMarkdownCheck = document.getElementById('enableMarkdown');
                        if (enableMarkdownCheck) enableMarkdownCheck.checked = true;
                        
                        document.getElementById('content').value = mdContent;
                        const ve = document.getElementById('contentVisual');
                        if (ve) {
                            ve.contentEditable = 'true';
                            if (editorMode === 'visual') {
                                ve.innerHTML = parseMarkdownToHtml(mdContent);
                            }
                        }
                    } else {
                        if (window.enableMarkdown) {
                            document.getElementById('content').value = draft.content || '';
                            const ve = document.getElementById('contentVisual');
                            if (ve) {
                                ve.contentEditable = 'true';
                                if (editorMode === 'visual') {
                                    ve.innerHTML = parseMarkdownToHtml(draft.content || '');
                                }
                            }
                        } else {
                            window.enableMarkdown = false;
                            document.body.classList.remove('markdown-mode');
                            const enableMarkdownCheck = document.getElementById('enableMarkdown');
                            if (enableMarkdownCheck) enableMarkdownCheck.checked = false;
                            
                            document.getElementById('content').value = draft.content || '';
                            const ve = document.getElementById('contentVisual');
                            if (ve) {
                                ve.contentEditable = 'true';
                                if (editorMode === 'visual') {
                                    ve.innerHTML = draft.content || '';
                                }
                            }
                        }
                    }
                    
                    // Закрываем меню
                    const moreMenu = document.getElementById('moreMenuWrap');
                    if (moreMenu) moreMenu.classList.remove('is-open');
                
                showNotification('Черновик загружен', 'success');
            } else {
                showAlert('Черновик не найден');
            }
        } else {
            showAlert('Ошибка загрузки черновика');
        }
    } catch (error) {
        console.error('Ошибка загрузки черновика:', error);
        showAlert('Ошибка при загрузке черновика');
    }
}

async function deleteDraft(filename, event) {
    event.stopPropagation();
    
    const result = await showConfirm('Удалить этот черновик?');
    if (!result) return;
    
    try {
        const response = await fetch('delete_draft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ filename: filename })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Черновик удален', 'success');
            draftsListLoaded = false;
            loadDraftsList(); // Перезагружаем список
        } else {
            showAlert('Ошибка: ' + data.error);
        }
    } catch (error) {
        console.error('Ошибка удаления черновика:', error);
        showAlert('Ошибка при удалении черновика');
    }
}

function toggleIncludesSubmenu(event) {
    event.stopPropagation();
    
    const button = event.currentTarget;
    const isOpen = button.classList.contains('submenu-open');
    
    document.querySelectorAll('.more-menu-item.has-submenu').forEach(btn => {
        if (btn !== button) {
            btn.classList.remove('submenu-open');
        }
    });
    
    if (!isOpen) {
        button.classList.add('submenu-open');
        loadIncludesList();
    } else {
        button.classList.remove('submenu-open');
    }
}

async function loadIncludesList() {
    if (includesListLoaded) return;
    
    const submenu = document.getElementById('includesSubmenu');
    if (!submenu) return;
    
    try {
        const response = await fetch('get_includes.php');
        const data = await response.json();
        
        if (data.success) {
            if (data.files.length === 0) {
                submenu.innerHTML = '<div class="more-submenu-empty">Нет сохраненных includes</div>';
            } else {
                submenu.innerHTML = data.files.map(file => 
                    `<div class="draft-item-wrap">
                        <button type="button" class="more-submenu-item draft-load-btn" onclick="insertInclude('${file.name}')" title="${file.displayName}">${file.displayName}</button>
                        <button type="button" class="draft-delete-btn" onclick="deleteInclude('${file.name}', event)" title="Удалить include">×</button>
                    </div>`
                ).join('');
            }
            includesListLoaded = true;
        }
    } catch (error) {
        console.error('Ошибка загрузки includes:', error);
        submenu.innerHTML = '<div class="more-submenu-empty">Ошибка загрузки</div>';
    }
}

async function deleteInclude(filename, event) {
    if (event) event.stopPropagation();
    
    const result = await showConfirm('Удалить этот include?');
    if (!result) return;
    
    try {
        const response = await fetch('delete_include.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
            },
            body: JSON.stringify({ filename: filename })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Include успешно удален', 'success');
            includesListLoaded = false;
            loadIncludesList();
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Ошибка удаления include:', error);
        showNotification('Ошибка при удалении include', 'error');
    }
}

async function insertInclude(filename) {
    try {
        const response = await fetch('get_include_content.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ filename: filename })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const ve = document.getElementById('contentVisual');
            const ta = document.getElementById('content');
            
            if (editorMode === 'visual') {
                insertHtmlAtCursor(data.content);
            } else {
                const start = ta.selectionStart;
                const end = ta.selectionEnd;
                const text = ta.value;
                ta.value = text.substring(0, start) + data.content + text.substring(end);
                ta.selectionStart = ta.selectionEnd = start + data.content.length;
            }
            
            // Закрываем меню
            const moreMenu = document.getElementById('moreMenuWrap');
            if (moreMenu) moreMenu.classList.remove('is-open');
            
            showNotification('Include вставлен', 'success');
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Ошибка вставки include:', error);
        showNotification('Ошибка при вставке include', 'error');
    }
}

// Функции для вставки ссылок на статьи
function toggleArticlesSubmenu(event) {
    event.stopPropagation();
    
    const button = event.currentTarget;
    const isOpen = button.classList.contains('submenu-open');
    
    document.querySelectorAll('.more-menu-item.has-submenu').forEach(btn => {
        if (btn !== button) {
            btn.classList.remove('submenu-open');
        }
    });
    
    if (!isOpen) {
        button.classList.add('submenu-open');
        loadArticlesList();
    } else {
        button.classList.remove('submenu-open');
    }
}

async function loadArticlesList() {
    const submenu = document.getElementById('articlesSubmenu');
    if (!submenu) return;
    
    try {
        const response = await fetch('serve_data.php?file=blog/posts-meta.json&t=' + Date.now());
        const articles = await response.json();
        
        if (articles.length === 0) {
            submenu.innerHTML = '<div class="more-submenu-empty">Нет статей</div>';
        } else {
            submenu.innerHTML = articles.map(article => 
                `<button type="button" class="more-submenu-item" onclick="insertArticleLink('${article.filename}', '${article.title.replace(/'/g, "\\'")}')">
                    ${article.title}
                </button>`
            ).join('');
        }
    } catch (error) {
        console.error('Ошибка загрузки статей:', error);
        submenu.innerHTML = '<div class="more-submenu-empty">Ошибка загрузки</div>';
    }
}

function insertArticleLink(filename, title) {
    const ve = document.getElementById('contentVisual');
    const ta = document.getElementById('content');
    
    const linkHtml = `<a href="${filename}">${title}</a>`;
    
    if (editorMode === 'visual') {
        insertHtmlAtCursor(linkHtml);
    } else {
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const text = ta.value;
        ta.value = text.substring(0, start) + linkHtml + text.substring(end);
        ta.selectionStart = ta.selectionEnd = start + linkHtml.length;
    }
    
    // Закрываем меню
    const moreMenu = document.getElementById('moreMenuWrap');
    if (moreMenu) moreMenu.classList.remove('is-open');
    
    showNotification('Ссылка на статью вставлена', 'success');
}

// ——— Проверка нумерации статей ———
async function checkPostNumbering() {
    const overlay = document.getElementById('numberingCheckOverlay');
    const content = document.getElementById('numberingCheckContent');
    const fixBtn = document.getElementById('fixNumberingBtn');
    
    overlay.classList.add('show');
    content.innerHTML = '<div class="numbering-status">Проверка нумерации...</div>';
    fixBtn.style.display = 'none';
    
    try {
        const response = await fetch('renumber_posts.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'check' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (data.needsFix) {
                let issuesHtml = '<div class="numbering-status warning">';
                issuesHtml += '<strong>⚠ Обнаружены проблемы с нумерацией!</strong><br><br>';
                issuesHtml += 'Следующие статьи имеют неправильную нумерацию:';
                issuesHtml += '<div class="numbering-issues-list">';
                
                data.issues.forEach(issue => {
                    issuesHtml += `
                        <div class="numbering-issue-item">
                            <div class="numbering-issue-title">${issue.title}</div>
                            <div class="numbering-issue-detail">
                                Текущий номер: ${issue.currentId} → Должен быть: ${issue.expectedId}
                            </div>
                        </div>
                    `;
                });
                
                issuesHtml += '</div></div>';
                content.innerHTML = issuesHtml;
                fixBtn.style.display = 'block';
            } else {
                content.innerHTML = `
                    <div class="numbering-status success">
                        <strong>✓ Нумерация корректна!</strong><br><br>
                        Все статьи пронумерованы правильно. Исправление не требуется.
                    </div>
                `;
                fixBtn.style.display = 'none';
            }
        } else {
            content.innerHTML = `
                <div class="numbering-status warning">
                    <strong>Ошибка проверки</strong><br><br>
                    ${data.error || 'Не удалось выполнить проверку'}
                </div>
            `;
            fixBtn.style.display = 'none';
        }
    } catch (error) {
        console.error('Ошибка проверки нумерации:', error);
        content.innerHTML = `
            <div class="numbering-status warning">
                <strong>Ошибка проверки</strong><br><br>
                Не удалось выполнить проверку нумерации
            </div>
        `;
        fixBtn.style.display = 'none';
    }
}

async function fixNumbering() {
    const content = document.getElementById('numberingCheckContent');
    const fixBtn = document.getElementById('fixNumberingBtn');
    
    content.innerHTML = '<div class="numbering-status">Исправление нумерации...</div>';
    fixBtn.disabled = true;
    
    try {
        const response = await fetch('renumber_posts.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'fix' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (data.changes && data.changes.length > 0) {
                let changesHtml = '<div class="numbering-status success">';
                changesHtml += '<strong>✓ Нумерация исправлена!</strong><br><br>';
                changesHtml += 'Выполнены следующие изменения:';
                changesHtml += '<div class="numbering-issues-list">';
                
                data.changes.forEach(change => {
                    changesHtml += `
                        <div class="numbering-issue-item">
                            <div class="numbering-issue-title">${change.title}</div>
                            <div class="numbering-issue-detail">
                                Статья №${change.oldId} → Статья №${change.newId}
                            </div>
                        </div>
                    `;
                });
                
                changesHtml += '</div></div>';
                content.innerHTML = changesHtml;
                
                showNotification('Нумерация исправлена', 'success');
                
                // Обновляем список статей если он открыт
                if (document.getElementById('managePosts').classList.contains('active')) {
                    loadPosts();
                }
            } else {
                content.innerHTML = `
                    <div class="numbering-status success">
                        <strong>✓ ${data.message}</strong><br><br>
                        Изменения не требуются.
                    </div>
                `;
            }
            
            fixBtn.style.display = 'none';
        } else {
            content.innerHTML = `
                <div class="numbering-status warning">
                    <strong>Ошибка исправления</strong><br><br>
                    ${data.error || 'Не удалось выполнить исправление'}
                </div>
            `;
            fixBtn.disabled = false;
        }
    } catch (error) {
        console.error('Ошибка исправления нумерации:', error);
        content.innerHTML = `
            <div class="numbering-status warning">
                <strong>Ошибка исправления</strong><br><br>
                Не удалось выполнить исправление нумерации
            </div>
        `;
        fixBtn.disabled = false;
    }
}

function closeNumberingCheck() {
    const overlay = document.getElementById('numberingCheckOverlay');
    overlay.classList.remove('show');
}

// ——— Гайд для первого запуска ———
const tutorialSteps = [
    {
        title: "👋 Добро пожаловать в NPBlog!",
        text: "Это гайд по основам работы в NPBlog.",
        element: null
    },
    {
        title: "📝 Поле для заголовка",
        text: "Сюда вводится заголовок вашей статьи. Он будет жирным шрифтом отображаться в общем списке постов и на самой странице.",
        element: "#title"
    },
    {
        title: "✏️ Главное окно редактора",
        text: "Это основная рабочая область. Вы можете просто писать текст, а также вставлять картинки и другие медиафайлы прямо сюда.",
        element: "#contentVisual"
    },
    {
        title: "👁 Режимы работы",
        text: "Вы можете переключаться между удобным «Визуальным» режимом (как в Word) и «Режимом кода», если вам нужно вручную подправить HTML-теги.",
        element: ".mode-toggle"
    },
    {
        title: "🔙 Отмена, Возврат и Сохранение",
        text: "Когда статья готова — жмите «Сохранить»!",
        element: ".editor-actions"
    },
    {
        title: "🎨 Базовое форматирование",
        text: "Здесь находятся стандартные инструменты: жирный шрифт, курсив, зачеркивание, подзаголовки, а также вставка таблиц и спойлеров.",
        element: "#formatBarRow > .toolbar-group:nth-child(1)"
    },
    {
        title: "📐 Выравнивание текста",
        text: "Эти кнопки позволяют выровнять текущий абзац, таблицу или картинку по левому краю, по центру или по правому краю.",
        element: "#formatBarRow > .toolbar-group:nth-child(3)"
    },
    {
        title: "🖼 Вставка ссылок и медиа",
        text: "Отсюда можно добавить гиперссылку, загрузить изображение с компьютера или вставить аудио/видео файлы.",
        element: "#formatBarRow > .toolbar-group:nth-child(5)"
    },
    {
        title: "🔤 Шрифты и Цвета",
        text: "Настройте размер шрифта, выберите гарнитуру (или загрузите свою!) и измените цвет текста, используя удобную палитру.",
        element: "#formatBarRow > .toolbar-group:nth-child(7)"
    },
    {
        title: "⋯ Дополнительное меню",
        text: "Под тремя точками скрыты важные функции: сохранение в черновик, менеджер файлов и добавление перекрестных ссылок на другие ваши статьи.",
        element: "#moreMenuWrap"
    },
    {
        title: "☰ Главное меню (Настройки)",
        text: "Важный раздел! Здесь находятся Управление статьями, Параметры (например, фоны), Менеджер бэкапов и смена темы.",
        element: "#editorMenuBtn"
    },
    {
        title: "💡 Контекстное меню",
        text: "Секретный совет: если кликнуть правой кнопкой мыши внутри редактора, откроется меню с быстрыми действиями (включая работу с таблицами).",
        element: "#contentVisual"
    },
    {
        title: "🎉 Вы готовы!",
        text: "Теперь вы знаете, где что находится. Если забудете — вы всегда можете заново запустить это обучение из Главного меню.",
        element: null
    }
];

let currentTutorialStep = 0;

function startTutorial() {
    fetch('get_editor_settings.php?t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            const settings = data.settings || {};
            if (settings.tutorialCompleted) return;
            
            currentTutorialStep = 0;
            showTutorialStep();
        })
        .catch(err => {
            console.error('Ошибка проверки настроек обучения:', err);
        });
}

function showTutorialStep() {
    const overlay = document.getElementById('tutorialOverlay');
    const tooltip = document.getElementById('tutorialTooltip');
    const complete = document.getElementById('tutorialComplete');
    const spotlight = document.getElementById('tutorialSpotlight');
    
    overlay.classList.add('show');
    tooltip.style.display = 'block';
    complete.style.display = 'none';
    
    const step = tutorialSteps[currentTutorialStep];
    
    // Обновляем контент
    document.getElementById('tutorialTitle').textContent = step.title;
    document.getElementById('tutorialText').textContent = step.text;
    
    // Обновляем прогресс
    const progressContainer = document.getElementById('tutorialProgress');
    progressContainer.innerHTML = '';
    tutorialSteps.forEach((_, index) => {
        const dot = document.createElement('div');
        dot.className = 'tutorial-progress-dot';
        if (index === currentTutorialStep) dot.classList.add('active');
        progressContainer.appendChild(dot);
    });
    
    // Сбрасываем стили
    tooltip.style.transform = '';
    
    // Позиционируем spotlight и tooltip
    if (step.element) {
        const element = document.querySelector(step.element);
        if (element) {
            const rect = element.getBoundingClientRect();
            const scrollY = window.scrollY || window.pageYOffset;
            const scrollX = window.scrollX || window.pageXOffset;
            
            spotlight.style.display = 'block';
            spotlight.style.top = (rect.top + scrollY - 8) + 'px';
            spotlight.style.left = (rect.left + scrollX - 8) + 'px';
            spotlight.style.width = (rect.width + 16) + 'px';
            spotlight.style.height = (rect.height + 16) + 'px';
            
            // Позиционируем tooltip
            tooltip.style.position = 'fixed';
            const tooltipRect = tooltip.getBoundingClientRect();
            const padding = 20;
            
            // Пробуем разместить снизу
            let tooltipTop = rect.bottom + padding;
            let tooltipLeft = rect.left;
            
            // Если не помещается снизу, размещаем сверху
            if (tooltipTop + tooltipRect.height > window.innerHeight - padding) {
                tooltipTop = rect.top - tooltipRect.height - padding;
            }
            
            // Если не помещается сверху, размещаем справа
            if (tooltipTop < padding) {
                tooltipTop = rect.top;
                tooltipLeft = rect.right + padding;
            }
            
            // Если не помещается справа, размещаем слева
            if (tooltipLeft + tooltipRect.width > window.innerWidth - padding) {
                tooltipLeft = rect.left - tooltipRect.width - padding;
            }
            
            // Проверяем границы по горизонтали
            if (tooltipLeft < padding) {
                tooltipLeft = padding;
            }
            if (tooltipLeft + tooltipRect.width > window.innerWidth - padding) {
                tooltipLeft = window.innerWidth - tooltipRect.width - padding;
            }
            
            // Проверяем границы по вертикали
            if (tooltipTop < padding) {
                tooltipTop = padding;
            }
            if (tooltipTop + tooltipRect.height > window.innerHeight - padding) {
                tooltipTop = window.innerHeight - tooltipRect.height - padding;
            }
            
            tooltip.style.top = tooltipTop + 'px';
            tooltip.style.left = tooltipLeft + 'px';
        }
    } else {
        spotlight.style.display = 'none';
        // Центрируем tooltip
        tooltip.style.position = 'fixed';
        tooltip.style.top = '50%';
        tooltip.style.left = '50%';
        tooltip.style.transform = 'translate(-50%, -50%)';
    }
}

function nextTutorialStep() {
    currentTutorialStep++;
    if (currentTutorialStep >= tutorialSteps.length) {
        showTutorialComplete();
    } else {
        showTutorialStep();
    }
}

function skipTutorial() {
    showConfirm('Вы уверены, что хотите пропустить обучение?').then(result => {
        if (!result) return;
        completeTutorial();
    });
}

function showTutorialComplete() {
    const tooltip = document.getElementById('tutorialTooltip');
    const complete = document.getElementById('tutorialComplete');
    const spotlight = document.getElementById('tutorialSpotlight');
    
    tooltip.style.display = 'none';
    spotlight.style.display = 'none';
    complete.style.display = 'block';
}

function completeTutorial() {
    fetch('save_editor_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tutorialCompleted: true })
    });
    const overlay = document.getElementById('tutorialOverlay');
    overlay.classList.remove('show');
}

function resetTutorial() {
    showConfirm('Вы уверены, что хотите сбросить обучение? Гайд появится снова при следующей загрузке страницы.').then(result => {
        if (!result) return;
        fetch('save_editor_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tutorialCompleted: false })
        }).then(() => {
            showNotification('Обучение сброшено. Перезагрузите страницу для запуска гайда.', 'success');
        });
    });
}

// Запускаем гайд при загрузке страницы
window.addEventListener('load', function() {
    setTimeout(startTutorial, 500);
});

// ——— Функции для загрузки файлов ———

function openFileUploadDialog() {
    document.getElementById('fileUploadDialog').style.display = 'block';
    
    // Инициализируем Drag & Drop
    initDragDrop();
    
    // Загружаем сохраненное состояние галочки из localStorage
    const savedState = localStorage.getItem('insertAsHyperlink');
    if (savedState !== null) {
        document.getElementById('insertAsHyperlink').checked = savedState === 'true';
    }
    
    loadDocumentsList();
    closeMoreMenu();
}

let isDragDropInitialized = false;

function initDragDrop() {
    if (isDragDropInitialized) return;
    const dropzone = document.getElementById('fileDropzone');
    if (!dropzone) return;
    
    // Предотвращаем дефолтное поведение для drag events
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    // Подсветка зоны при перетаскивании
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.classList.add('drag-over');
        }, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.classList.remove('drag-over');
        }, false);
    });
    
    // Обработка сброшенного файла
    dropzone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            const fileNameEl = document.getElementById('documentFileName');
            if (fileNameEl) {
                fileNameEl.textContent = files[0].name;
                fileNameEl.style.display = 'block';
            }
            uploadDocument(files[0]);
        }
    }, false);
    
    isDragDropInitialized = true;
}

function handleFileSelect(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileNameEl = document.getElementById('documentFileName');
        if (fileNameEl) {
            fileNameEl.textContent = file.name;
            fileNameEl.style.display = 'block';
        }
        uploadDocument(file);
    }
}

function closeFileUploadDialog() {
    document.getElementById('fileUploadDialog').style.display = 'none';
}

function closeMoreMenu() {
    var dropdown = document.getElementById('moreMenuDropdown');
    if (dropdown) {
        dropdown.classList.remove('show');
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Б';
    const k = 1024;
    const sizes = ['Б', 'КБ', 'МБ', 'ГБ'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function loadDocumentsList() {
    fetch('get_documents.php')
        .then(response => response.json())
        .then(data => {
            const listContainer = document.getElementById('fileUploadList');
            
            if (data.success && data.files.length > 0) {
                listContainer.innerHTML = '';
                data.files.forEach(file => {
                    const item = document.createElement('div');
                    item.className = 'file-upload-item';
                    
                    const info = document.createElement('div');
                    info.className = 'file-upload-item-info';
                    info.onclick = () => insertFileButton(file.name, file.url, file.size);
                    
                    const ext = file.name.split('.').pop().toLowerCase();
                    let icon = '📄';
                    let iconBg = 'rgba(96, 125, 139, 0.1)';
                    let iconColor = 'rgb(96, 125, 139)';
                    
                    if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext)) {
                        icon = '🖼️';
                        iconBg = 'rgba(76, 175, 80, 0.1)';
                        iconColor = 'rgb(76, 175, 80)';
                    } else if (['mp3', 'wav', 'ogg'].includes(ext)) {
                        icon = '🎵';
                        iconBg = 'rgba(244, 67, 54, 0.1)';
                        iconColor = 'rgb(244, 67, 54)';
                    } else if (['mp4', 'webm', 'avi', 'mov'].includes(ext)) {
                        icon = '🎥';
                        iconBg = 'rgba(156, 39, 176, 0.1)';
                        iconColor = 'rgb(156, 39, 176)';
                    } else if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) {
                        icon = '📦';
                        iconBg = 'rgba(255, 152, 0, 0.1)';
                        iconColor = 'rgb(255, 152, 0)';
                    } else if (['pdf'].includes(ext)) {
                        icon = '📕';
                        iconBg = 'rgba(229, 57, 53, 0.1)';
                        iconColor = 'rgb(229, 57, 53)';
                    } else if (['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'].includes(ext)) {
                        icon = '📝';
                        iconBg = 'rgba(0, 150, 136, 0.1)';
                        iconColor = 'rgb(0, 150, 136)';
                    } else if (['html', 'css', 'js', 'php', 'json', 'py', 'sh'].includes(ext)) {
                        icon = '💻';
                        iconBg = 'rgba(33, 150, 243, 0.1)';
                        iconColor = 'rgb(33, 150, 243)';
                    }

                    const iconSpan = document.createElement('span');
                    iconSpan.className = 'file-upload-item-icon';
                    iconSpan.style.background = iconBg;
                    iconSpan.style.color = iconColor;
                    iconSpan.textContent = icon;
                    info.appendChild(iconSpan);
                    
                    const textDiv = document.createElement('div');
                    textDiv.className = 'file-upload-item-text';
                    
                    const name = document.createElement('div');
                    name.className = 'file-upload-item-name';
                    name.textContent = file.name;
                    name.title = file.name;
                    
                    const meta = document.createElement('div');
                    meta.className = 'file-upload-item-meta';
                    
                    const formattedSize = formatFileSize(file.size);
                    const formattedDate = file.mtime ? new Date(file.mtime * 1000).toLocaleString('ru-RU', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    }) : '';
                    
                    meta.textContent = formattedSize + (formattedDate ? ' • ' + formattedDate : '');
                    
                    textDiv.appendChild(name);
                    textDiv.appendChild(meta);
                    info.appendChild(textDiv);
                    
                    const actionsDiv = document.createElement('div');
                    actionsDiv.className = 'file-upload-item-actions';
                    
                    const insertBtn = document.createElement('button');
                    insertBtn.type = 'button';
                    insertBtn.className = 'file-upload-item-btn insert';
                    insertBtn.textContent = 'Вставить';
                    insertBtn.onclick = (e) => {
                        e.stopPropagation();
                        insertFileButton(file.name, file.url, file.size);
                    };
                    
                    const deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.className = 'file-upload-item-btn delete';
                    deleteBtn.textContent = 'Удалить';
                    deleteBtn.onclick = (e) => {
                        e.stopPropagation();
                        deleteDocument(file.path);
                    };
                    
                    actionsDiv.appendChild(insertBtn);
                    actionsDiv.appendChild(deleteBtn);
                    
                    item.appendChild(info);
                    item.appendChild(actionsDiv);
                    listContainer.appendChild(item);
                });
            } else {
                listContainer.innerHTML = '<div class="file-upload-empty">Нет загруженных файлов</div>';
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки списка файлов:', error);
            document.getElementById('fileUploadList').innerHTML = '<div class="file-upload-empty">Ошибка загрузки списка</div>';
        });
}

function uploadDocument(fileToUpload = null) {
    const fileInput = document.getElementById('documentFile');
    const file = fileToUpload || (fileInput ? fileInput.files[0] : null);
    
    if (!file) {
        showNotification('Выберите файл для загрузки', 'error');
        return;
    }
    
    // Отображаем анимацию загрузки в зоне
    const dropzone = document.getElementById('fileDropzone');
    const dropzoneText = dropzone ? dropzone.querySelector('.dropzone-text') : null;
    let originalText = '';
    if (dropzoneText) {
        originalText = dropzoneText.textContent;
        dropzoneText.innerHTML = `<span class="loading-spinner"></span> Загрузка "${file.name}"...`;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    
    fetch('upload_document.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (dropzoneText) {
            dropzoneText.textContent = originalText;
        }
        if (data.success) {
            showNotification('Файл успешно загружен', 'success');
            if (fileInput) fileInput.value = '';
            const fileNameEl = document.getElementById('documentFileName');
            if (fileNameEl) {
                fileNameEl.textContent = 'Файл не выбран';
            }
            loadDocumentsList();
        } else {
            showNotification('Ошибка загрузки: ' + (data.error || 'Неизвестная ошибка'), 'error');
        }
    })
    .catch(error => {
        if (dropzoneText) {
            dropzoneText.textContent = originalText;
        }
        console.error('Ошибка:', error);
        showNotification('Ошибка загрузки файла', 'error');
    });
}

function deleteDocument(filePath) {
    showConfirm('Удалить этот файл?', 'Подтверждение удаления').then(result => {
        if (!result) return;
        
        fetch('delete_document.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ filePath: filePath })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Файл удален', 'success');
                loadDocumentsList();
            } else {
                showNotification('Ошибка удаления: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showNotification('Ошибка удаления файла', 'error');
        });
    });
}

function insertFileButton(fileName, filePath, fileSize) {
    const ve = document.getElementById('contentVisual');
    ve.focus();
    
    // Преобразуем путь к файлу, добавляя / в начало если его нет
    if (!filePath.startsWith('/')) {
        filePath = '/' + filePath;
    }
    
    // Проверяем, нужно ли вставить как гиперссылку
    const insertAsHyperlink = document.getElementById('insertAsHyperlink').checked;
    
    let elementToInsert;
    
    if (insertAsHyperlink) {
        // Вставляем как простую гиперссылку
        const link = document.createElement('a');
        link.href = filePath;
        link.textContent = fileName;
        link.target = '_blank';
        link.setAttribute('download', fileName);
        elementToInsert = link;
    } else {
        // Создаем стандартную структуру медиа-обертки для поддержки оверлея
        const alignWrap = document.createElement('div');
        alignWrap.className = 'blog-image-align-wrap';
        alignWrap.style.textAlign = 'left'; // По умолчанию слева
        
        const mediaWrap = document.createElement('div');
        mediaWrap.className = 'blog-image-wrap';
        mediaWrap.style.display = 'inline-block';
        
        const fileButton = document.createElement('a');
        fileButton.href = filePath;
        fileButton.className = 'blog-file-button';
        fileButton.target = '_blank';
        fileButton.setAttribute('download', fileName);
        fileButton.contentEditable = 'false';
        fileButton.style.setProperty('font-family', 'Arial, sans-serif', 'important');
        fileButton.style.setProperty('-webkit-font-smoothing', 'antialiased', 'important');
        fileButton.style.setProperty('-moz-osx-font-smoothing', 'grayscale', 'important');
        fileButton.style.setProperty('text-rendering', 'optimizeLegibility', 'important');
        
        const icon = document.createElement('div');
        icon.className = 'blog-file-icon';
        icon.textContent = '📥';
        
        const info = document.createElement('div');
        info.className = 'blog-file-info';
        
        const name = document.createElement('div');
        name.className = 'blog-file-name';
        name.textContent = fileName;
        
        const size = document.createElement('div');
        size.className = 'blog-file-size';
        size.textContent = formatFileSize(fileSize);
        
        info.appendChild(name);
        info.appendChild(size);
        fileButton.appendChild(icon);
        fileButton.appendChild(info);
        
        mediaWrap.appendChild(fileButton);
        alignWrap.appendChild(mediaWrap);
        
        elementToInsert = alignWrap;
    }
    
    // Создаем пустой блок для курсора после элемента
    const emptyDiv = document.createElement('div');
    emptyDiv.innerHTML = '<br>';
    
    // Вставляем в редактор
    const sel = window.getSelection();
    let range = null;
    
    // Используем savedRange если он есть
    if (typeof savedRange !== 'undefined' && savedRange && ve.contains(savedRange.commonAncestorContainer)) {
        range = savedRange;
    } else if (sel && sel.rangeCount > 0) {
        range = sel.getRangeAt(0);
    }
    
    if (!range) {
        // Если нет range, добавляем в конец
        ve.appendChild(elementToInsert);
        if (!insertAsHyperlink) {
            ve.appendChild(emptyDiv);
        }
        range = document.createRange();
        range.setStart(insertAsHyperlink ? elementToInsert : emptyDiv, 0);
        range.collapse(true);
        if (sel) {
            sel.removeAllRanges();
            sel.addRange(range);
        }
        if (typeof savedRange !== 'undefined') {
            savedRange = range.cloneRange();
        }
    } else {
        // Удаляем выделенный контент
        range.deleteContents();
        
        // Вставляем элемент
        range.insertNode(elementToInsert);
        
        if (!insertAsHyperlink) {
            // Вставляем пустой блок после кнопки
            const parent = elementToInsert.parentNode;
            parent.insertBefore(emptyDiv, elementToInsert.nextSibling);
            
            // Устанавливаем курсор в пустой блок
            range.setStart(emptyDiv, 0);
        } else {
            // Для гиперссылки ставим курсор после неё
            range.setStartAfter(elementToInsert);
        }
        
        range.collapse(true);
        if (sel) {
            sel.removeAllRanges();
            sel.addRange(range);
        }
        if (typeof savedRange !== 'undefined') {
            savedRange = range.cloneRange();
        }
    }
    
    saveToHistory();
    closeFileUploadDialog();
    showNotification('Файл добавлен в статью', 'success');
}

// ——— Работа с якорями и содержанием ———
function addAnchor() {
    if (editorMode !== 'visual') {
        showNotification('Якоря можно добавлять только в визуальном режиме', 'warning');
        return;
    }
    
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
        submenu.innerHTML = '<div class="more-submenu-empty">Нет якорей в статье</div>';
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
            text = `Якорь: #${id}`;
        } else {
            if (text.length > 25) {
                text = text.substring(0, 22) + '...';
            }
            text = `${text} (#${id})`;
        }
        
        html += `
        <div class="toc-menu-item-row">
            <button type="button" class="more-submenu-item" onclick="insertAnchorLink('${id}')" title="Вставить ссылку на #${id}">${text}</button>
            <button type="button" class="toc-delete-btn" onclick="removeAnchorById('${id}', event)" title="Удалить якорь #${id}">×</button>
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

function insertAnchorLink(id) {
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
        
        text = prompt("Введите текст для ссылки-якоря:", anchorText);
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

// --- ASCII Drawing Tool Implementation ---
let asciiGridWidth = 40;
let asciiGridHeight = 15;
let asciiCurrentChar = '█';
let asciiCurrentTool = 'draw'; // 'draw', 'erase', 'fill'
let asciiIsDrawing = false;
let asciiHistory = [];
let asciiHistoryIndex = -1;
let asciiTargetWrap = null;

function wrapAsciiWithControls(asciiHtml) {
    const uniqueId = 'ascii-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    return '<div class="blog-image-align-wrap" style="text-align:center" data-image-id="' + uniqueId + '">' +
        '<div class="blog-image-wrap">' + asciiHtml + '</div></div>';
}

function drawCell(cell) {
    if (asciiCurrentTool === 'draw') {
        if (cell.textContent !== asciiCurrentChar) {
            cell.textContent = asciiCurrentChar;
        }
    } else if (asciiCurrentTool === 'erase') {
        if (cell.textContent !== ' ') {
            cell.textContent = ' ';
        }
    }
}

function floodFill(startX, startY, targetChar, replacementChar) {
    if (targetChar === replacementChar) return;
    
    const cells = document.querySelectorAll('.ascii-cell');
    const getCell = (x, y) => {
        if (x < 0 || x >= asciiGridWidth || y < 0 || y >= asciiGridHeight) return null;
        return cells[y * asciiGridWidth + x];
    };
    
    const startCell = getCell(startX, startY);
    if (!startCell || startCell.textContent !== targetChar) return;
    
    const queue = [[startX, startY]];
    
    while (queue.length > 0) {
        const [x, y] = queue.shift();
        const cell = getCell(x, y);
        if (cell && cell.textContent === targetChar) {
            cell.textContent = replacementChar;
            
            queue.push([x + 1, y]);
            queue.push([x - 1, y]);
            queue.push([x, y + 1]);
            queue.push([x, y - 1]);
        }
    }
}

function changeAsciiGridSize(sizeStr) {
    const customContainer = document.getElementById('asciiCustomSizeContainer');
    if (sizeStr === 'custom') {
        if (customContainer) {
            customContainer.style.display = 'flex';
            document.getElementById('asciiCustomWidth').value = asciiGridWidth;
            document.getElementById('asciiCustomHeight').value = asciiGridHeight;
        }
        return;
    }
    
    if (customContainer) {
        customContainer.style.display = 'none';
    }
    
    const parts = sizeStr.split('x');
    const newWidth = parseInt(parts[0]);
    const newHeight = parseInt(parts[1]);
    
    if (confirm('Смена размера сетки очистит текущий рисунок. Продолжить?')) {
        asciiGridWidth = newWidth;
        asciiGridHeight = newHeight;
        createAsciiGrid();
        clearAsciiHistory();
        saveAsciiHistory();
    } else {
        const sizeSelect = document.getElementById('asciiGridSize');
        if (sizeSelect) {
            sizeSelect.value = asciiGridWidth + 'x' + asciiGridHeight;
        }
    }
}

function applyCustomAsciiGridSize() {
    const widthInput = document.getElementById('asciiCustomWidth');
    const heightInput = document.getElementById('asciiCustomHeight');
    if (!widthInput || !heightInput) return;
    
    const newWidth = parseInt(widthInput.value);
    const newHeight = parseInt(heightInput.value);
    
    if (isNaN(newWidth) || newWidth < 5 || newWidth > 120) {
        showNotification('Ширина должна быть от 5 до 120 символов', 'warning');
        return;
    }
    if (isNaN(newHeight) || newHeight < 5 || newHeight > 60) {
        showNotification('Высота должна быть от 5 до 60 символов', 'warning');
        return;
    }
    
    if (confirm('Смена размера сетки очистит текущий рисунок. Продолжить?')) {
        asciiGridWidth = newWidth;
        asciiGridHeight = newHeight;
        createAsciiGrid();
        clearAsciiHistory();
        saveAsciiHistory();
        showNotification(`Установлен размер: ${newWidth}x${newHeight}`, 'success');
    }
}

function createAsciiGrid(initialData = null) {
    const gridContainer = document.getElementById('asciiGrid');
    if (!gridContainer) return;
    
    gridContainer.innerHTML = '';
    gridContainer.style.gridTemplateColumns = `repeat(${asciiGridWidth}, 9px)`;
    gridContainer.style.gridTemplateRows = `repeat(${asciiGridHeight}, 18px)`;
    
    for (let y = 0; y < asciiGridHeight; y++) {
        for (let x = 0; x < asciiGridWidth; x++) {
            const cell = document.createElement('div');
            cell.className = 'ascii-cell';
            cell.setAttribute('data-x', x);
            cell.setAttribute('data-y', y);
            
            const index = y * asciiGridWidth + x;
            if (initialData && initialData[index] !== undefined) {
                cell.textContent = initialData[index];
            } else {
                cell.textContent = ' ';
            }
            
            cell.addEventListener('mousedown', function(e) {
                e.preventDefault();
                if (asciiCurrentTool === 'fill') {
                    const targetChar = cell.textContent;
                    floodFill(x, y, targetChar, asciiCurrentChar);
                    saveAsciiHistory();
                } else {
                    asciiIsDrawing = true;
                    drawCell(cell);
                }
            });
            
            cell.addEventListener('mouseenter', function(e) {
                cell.classList.add('hovered');
                if (asciiIsDrawing) {
                    drawCell(cell);
                }
            });
            
            cell.addEventListener('mouseleave', function() {
                cell.classList.remove('hovered');
            });
            
            gridContainer.appendChild(cell);
        }
    }
    
    setTimeout(fitAsciiGridToContainer, 0);
}

function fitAsciiGridToContainer() {
    const container = document.getElementById('asciiEditorCanvasContainer');
    const grid = document.getElementById('asciiGrid');
    if (!container || !grid) return;
    
    grid.style.transform = 'none';
    grid.style.transformOrigin = 'center center';
    
    const padding = 40;
    const containerWidth = container.clientWidth - padding;
    const containerHeight = container.clientHeight - padding;
    
    const gridWidth = grid.offsetWidth;
    const gridHeight = grid.offsetHeight;
    
    if (gridWidth === 0 || gridHeight === 0) return;
    
    const scaleX = containerWidth / gridWidth;
    const scaleY = containerHeight / gridHeight;
    const scale = Math.min(scaleX, scaleY);
    
    grid.style.transform = `scale(${scale})`;
}

window.addEventListener('resize', function() {
    const modal = document.getElementById('asciiEditorModal');
    if (modal && modal.style.display === 'flex') {
        fitAsciiGridToContainer();
    }
});

// Global mouse up to end drawing
window.addEventListener('mouseup', function() {
    if (asciiIsDrawing) {
        asciiIsDrawing = false;
        saveAsciiHistory();
    }
});

// Global shortcut keys interception (Ctrl+Z) in ASCII editor
window.addEventListener('keydown', function(e) {
    const modal = document.getElementById('asciiEditorModal');
    if (modal && modal.style.display === 'flex') {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
            e.preventDefault();
            undoAsciiState();
        }
    }
});

function clearAsciiHistory() {
    asciiHistory = [];
    asciiHistoryIndex = -1;
    updateAsciiUndoBtn();
}

function saveAsciiHistory() {
    const cells = document.querySelectorAll('.ascii-cell');
    if (cells.length === 0) return;
    const state = Array.from(cells).map(c => c.textContent);
    
    asciiHistory = asciiHistory.slice(0, asciiHistoryIndex + 1);
    asciiHistory.push(state);
    asciiHistoryIndex++;
    
    updateAsciiUndoBtn();
}

function undoAsciiState() {
    if (asciiHistoryIndex <= 0) return;
    
    asciiHistoryIndex--;
    restoreAsciiHistoryState(asciiHistory[asciiHistoryIndex]);
    updateAsciiUndoBtn();
}

function restoreAsciiHistoryState(state) {
    const cells = document.querySelectorAll('.ascii-cell');
    cells.forEach((cell, i) => {
        if (state[i] !== undefined) {
            cell.textContent = state[i];
        }
    });
}

function updateAsciiUndoBtn() {
    const btn = document.getElementById('asciiEditorUndoBtn');
    if (!btn) return;
    btn.disabled = asciiHistoryIndex <= 0;
    btn.style.opacity = asciiHistoryIndex <= 0 ? '0.5' : '1';
    btn.style.cursor = asciiHistoryIndex <= 0 ? 'not-allowed' : 'pointer';
}

function setAsciiTool(tool) {
    asciiCurrentTool = tool;
    document.querySelectorAll('.ascii-tool-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    const activeBtn = document.getElementById('ascii-tool-' + tool);
    if (activeBtn) activeBtn.classList.add('active');
}

const asciiPresetCategories = [
    {
        name: 'Блоки',
        chars: ['█', '▓', '▒', '░', '▄', '▀', '▌', '▐', '■', '▲', '▼', '◆', '●', '○', '★', '☆', '♣', '♦']
    },
    {
        name: 'Линии',
        chars: ['─', '│', '┌', '┐', '└', '┘', '├', '┤', '┬', '┴', '┼', '╭', '╮', '╯', '╰', '╱', '╲', '╳']
    },
    {
        name: 'Двойные',
        chars: ['═', '║', '╔', '╗', '╚', '╝', '╠', '╣', '╦', '╩', '╬', '╒', '╕', '╘', '╛', '╓', '╖', '╙']
    },
    {
        name: 'Стрелки',
        chars: ['↑', '↓', '←', '→', '↖', '↗', '↘', '↙', '↔', '↕', '▲', '▼', '◀', '▶', '➔', '➜', '➘', '➚']
    },
    {
        name: 'Символы',
        chars: ['#', '@', '*', '+', '-', '=', ':', '.', 'o', 'x', 'd', 'b', 'p', 'q', '0', '1', '8', '9']
    }
];

let asciiCurrentCategoryIndex = 0;

function renderAsciiPresets() {
    const container = document.getElementById('asciiCharPresets');
    const indicator = document.getElementById('asciiPageIndicator');
    if (!container) return;
    
    const category = asciiPresetCategories[asciiCurrentCategoryIndex];
    if (indicator) indicator.textContent = category.name;
    
    container.innerHTML = '';
    container.style.opacity = '0';
    
    category.chars.forEach(char => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ascii-char-preset';
        if (char === asciiCurrentChar) {
            btn.classList.add('active');
        }
        btn.textContent = char;
        btn.onclick = function() {
            setAsciiChar(char, btn);
        };
        container.appendChild(btn);
    });
    
    requestAnimationFrame(() => {
        container.style.transition = 'opacity 0.15s ease-in-out';
        container.style.opacity = '1';
    });
}

function nextAsciiPage() {
    asciiCurrentCategoryIndex = (asciiCurrentCategoryIndex + 1) % asciiPresetCategories.length;
    renderAsciiPresets();
}

function prevAsciiPage() {
    asciiCurrentCategoryIndex = (asciiCurrentCategoryIndex - 1 + asciiPresetCategories.length) % asciiPresetCategories.length;
    renderAsciiPresets();
}

function setAsciiChar(char, presetBtn = null) {
    asciiCurrentChar = char;
    
    if (presetBtn) {
        document.querySelectorAll('.ascii-char-preset').forEach(btn => {
            btn.classList.remove('active');
        });
        presetBtn.classList.add('active');
    }
}

function applyCustomAsciiChar() {
    const input = document.getElementById('asciiCustomChar');
    if (input && input.value) {
        setAsciiChar(input.value);
        document.querySelectorAll('.ascii-char-preset').forEach(btn => {
            btn.classList.remove('active');
        });
        showNotification('Установлен символ: ' + input.value, 'success');
    }
}

function openAsciiDrawer(targetWrap = null) {
    if (editorMode !== 'visual') {
        showNotification('ASCII Рисовалка доступна только в визуальном режиме', 'warning');
        return;
    }
    
    asciiTargetWrap = targetWrap;
    const modal = document.getElementById('asciiEditorModal');
    if (!modal) return;
    
    const customInput = document.getElementById('asciiCustomChar');
    if (customInput) customInput.value = '';
    
    setAsciiTool('draw');
    asciiCurrentCategoryIndex = 0;
    setAsciiChar('█');
    renderAsciiPresets();
    
    if (asciiTargetWrap) {
        const width = parseInt(asciiTargetWrap.getAttribute('data-ascii-width')) || 40;
        const height = parseInt(asciiTargetWrap.getAttribute('data-ascii-height')) || 15;
        const gridData = JSON.parse(asciiTargetWrap.getAttribute('data-ascii-grid') || '[]');
        
        asciiGridWidth = width;
        asciiGridHeight = height;
        
        const sizeSelect = document.getElementById('asciiGridSize');
        const customContainer = document.getElementById('asciiCustomSizeContainer');
        if (sizeSelect) {
            const val = width + 'x' + height;
            const hasOption = Array.from(sizeSelect.options).some(opt => opt.value === val);
            if (hasOption) {
                sizeSelect.value = val;
                if (customContainer) customContainer.style.display = 'none';
            } else {
                sizeSelect.value = 'custom';
                if (customContainer) {
                    customContainer.style.display = 'flex';
                    document.getElementById('asciiCustomWidth').value = width;
                    document.getElementById('asciiCustomHeight').value = height;
                }
            }
        }
        
        createAsciiGrid(gridData);
    } else {
        const sizeSelect = document.getElementById('asciiGridSize');
        const customContainer = document.getElementById('asciiCustomSizeContainer');
        const sizeStr = sizeSelect ? sizeSelect.value : '40x15';
        if (sizeStr === 'custom') {
            const widthInput = document.getElementById('asciiCustomWidth');
            const heightInput = document.getElementById('asciiCustomHeight');
            asciiGridWidth = widthInput ? parseInt(widthInput.value) || 40 : 40;
            asciiGridHeight = heightInput ? parseInt(heightInput.value) || 15 : 15;
            if (customContainer) customContainer.style.display = 'flex';
        } else {
            const parts = sizeStr.split('x');
            asciiGridWidth = parseInt(parts[0]) || 40;
            asciiGridHeight = parseInt(parts[1]) || 15;
            if (customContainer) customContainer.style.display = 'none';
        }
        
        createAsciiGrid();
    }
    
    clearAsciiHistory();
    saveAsciiHistory();
    
    modal.style.display = 'flex';
    modal.classList.add('show');
}

function closeAsciiEditor() {
    const modal = document.getElementById('asciiEditorModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
    }
    asciiTargetWrap = null;
}

function clearAsciiGrid() {
    if (confirm('Очистить холст? Это действие удалит весь текущий рисунок.')) {
        const cells = document.querySelectorAll('.ascii-cell');
        cells.forEach(cell => {
            cell.textContent = ' ';
        });
        saveAsciiHistory();
    }
}

function saveAsciiArt() {
    const cells = document.querySelectorAll('.ascii-cell');
    const gridData = Array.from(cells).map(c => c.textContent);
    
    let textLines = [];
    for (let y = 0; y < asciiGridHeight; y++) {
        let line = '';
        for (let x = 0; x < asciiGridWidth; x++) {
            const index = y * asciiGridWidth + x;
            line += gridData[index];
        }
        textLines.push(line.trimRight());
    }
    const plainText = textLines.join('\n');
    
    const gridJson = JSON.stringify(gridData).replace(/"/g, '&quot;');
    const asciiHtml = `<pre class="blog-ascii-art">${plainText}</pre>`;
    
    if (asciiTargetWrap) {
        asciiTargetWrap.setAttribute('data-ascii-width', asciiGridWidth);
        asciiTargetWrap.setAttribute('data-ascii-height', asciiGridHeight);
        asciiTargetWrap.setAttribute('data-ascii-grid', JSON.stringify(gridData));
        
        const artEl = asciiTargetWrap.querySelector('.blog-ascii-art');
        if (artEl) {
            artEl.textContent = plainText;
        }
        showNotification('ASCII-арт обновлен', 'success');
    } else {
        const fullHtml = wrapAsciiWithControls(
            `<div class="blog-ascii-wrap" data-ascii-width="${asciiGridWidth}" data-ascii-height="${asciiGridHeight}" data-ascii-grid="${gridJson}">${asciiHtml}</div>`
        );
        insertHtmlAtCursor(fullHtml);
        showNotification('ASCII-арт вставлен в статью', 'success');
    }
    
    saveToHistory();
    closeAsciiEditor();
}

// Export to window
window.openAsciiDrawer = openAsciiDrawer;
window.closeAsciiEditor = closeAsciiEditor;
window.changeAsciiGridSize = changeAsciiGridSize;
window.setAsciiTool = setAsciiTool;
window.setAsciiChar = setAsciiChar;
window.applyCustomAsciiChar = applyCustomAsciiChar;
window.clearAsciiGrid = clearAsciiGrid;
window.undoAsciiState = undoAsciiState;
window.saveAsciiArt = saveAsciiArt;
window.applyCustomAsciiGridSize = applyCustomAsciiGridSize;
window.fitAsciiGridToContainer = fitAsciiGridToContainer;
window.nextAsciiPage = nextAsciiPage;
window.prevAsciiPage = prevAsciiPage;

// --- Плавная печать (мягкий курсор) ---
let caretTimeout = null;
let caretScrollListener = null;

function applySmoothTypingState() {
    const editor = document.getElementById('contentVisual');
    if (!editor) return;
    
    let caret = document.getElementById('customCaret');
    
    if (window.smoothTypingEnabled) {
        editor.classList.add('smooth-typing');
        
        if (!caret) {
            caret = document.createElement('div');
            caret.id = 'customCaret';
            document.body.appendChild(caret);
        }
        
        if (!window.smoothTypingListenersAdded) {
            document.addEventListener('selectionchange', handleCaretUpdate);
            
            caretScrollListener = () => {
                requestAnimationFrame(updateCustomCaret);
            };
            editor.addEventListener('scroll', caretScrollListener);
            window.addEventListener('resize', caretScrollListener);
            
            editor.addEventListener('focus', handleCaretUpdate);
            editor.addEventListener('blur', handleCaretBlur);
            
            window.smoothTypingListenersAdded = true;
        }
        
        updateCustomCaret();
    } else {
        editor.classList.remove('smooth-typing');
        if (caret) {
            caret.style.display = 'none';
        }
        
        if (window.smoothTypingListenersAdded) {
            document.removeEventListener('selectionchange', handleCaretUpdate);
            if (caretScrollListener) {
                editor.removeEventListener('scroll', caretScrollListener);
                window.removeEventListener('resize', caretScrollListener);
            }
            editor.removeEventListener('focus', handleCaretUpdate);
            editor.removeEventListener('blur', handleCaretBlur);
            window.smoothTypingListenersAdded = false;
        }
    }
}

function handleCaretBlur() {
    setTimeout(() => {
        const editor = document.getElementById('contentVisual');
        if (document.activeElement !== editor) {
            const caret = document.getElementById('customCaret');
            if (caret) caret.style.display = 'none';
        }
    }, 100);
}

function handleCaretUpdate() {
    updateCustomCaret();
}

function getCaretCoordinates() {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return null;
    
    const range = sel.getRangeAt(0);
    let rect = null;
    
    try {
        rect = range.getBoundingClientRect();
    } catch(e) {}
    
    if (rect && rect.height > 0 && rect.left > 0) {
        return rect;
    }
    
    try {
        const rects = range.getClientRects();
        if (rects && rects.length > 0 && rects[0].height > 0) {
            return rects[0];
        }
    } catch(e) {}
    
    let node = range.startContainer;
    let offset = range.startOffset;
    
    if (!node) return null;
    
    if (node.nodeType === Node.ELEMENT_NODE) {
        if (node.childNodes.length > 0 && offset < node.childNodes.length) {
            let child = node.childNodes[offset];
            if (child && child.nodeType === Node.ELEMENT_NODE) {
                try {
                    return child.getBoundingClientRect();
                } catch(e) {}
            }
        }
        try {
            const nodeRect = node.getBoundingClientRect();
            const style = window.getComputedStyle(node);
            const padLeft = parseFloat(style.paddingLeft) || 0;
            const padTop = parseFloat(style.paddingTop) || 0;
            const lineH = parseFloat(style.lineHeight) || parseFloat(style.fontSize) * 1.2 || 20;
            return {
                left: nodeRect.left + padLeft,
                top: nodeRect.top + padTop,
                height: lineH
            };
        } catch(e) {}
    } else if (node.nodeType === Node.TEXT_NODE) {
        let parent = node.parentNode;
        if (parent) {
            try {
                const parentRect = parent.getBoundingClientRect();
                const style = window.getComputedStyle(parent);
                const lineH = parseFloat(style.lineHeight) || parseFloat(style.fontSize) * 1.2 || 20;
                return {
                    left: parentRect.left,
                    top: parentRect.top,
                    height: lineH
                };
            } catch(e) {}
        }
    }
    
    return null;
}

function updateCustomCaret() {
    const editor = document.getElementById('contentVisual');
    const caret = document.getElementById('customCaret');
    if (!editor || !caret || !window.smoothTypingEnabled) return;
    
    if (document.activeElement !== editor) {
        caret.style.display = 'none';
        return;
    }
    
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0 || !sel.isCollapsed) {
        caret.style.display = 'none';
        return;
    }
    
    const rect = getCaretCoordinates();
    if (rect && rect.height > 0) {
        const editorRect = editor.getBoundingClientRect();
        
        if (rect.top >= editorRect.top - 5 && rect.bottom <= editorRect.bottom + 5 &&
            rect.left >= editorRect.left - 5 && rect.left <= editorRect.right + 5) {
            
            caret.style.left = `${rect.left}px`;
            caret.style.top = `${rect.top}px`;
            caret.style.height = `${rect.height}px`;
            caret.style.display = 'block';
            
            caret.classList.remove('blink');
            void caret.offsetWidth;
            
            clearTimeout(caretTimeout);
            caretTimeout = setTimeout(() => {
                caret.classList.add('blink');
            }, 500);
        } else {
            caret.style.display = 'none';
        }
    } else {
        caret.style.display = 'none';
    }
}

window.applySmoothTypingState = applySmoothTypingState;

// --- Markdown to HTML Dynamic Compiler ---
function parseMarkdownToHtml(md) {
    if (!md) return '';
    let html = md;
    html = html.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

    // 1. Code blocks (```lang ... ```)
    const codeBlocks = [];
    html = html.replace(/```([\s\S]*?)```/g, (match, codeContent) => {
        const lines = codeContent.split('\n');
        let lang = '';
        if (lines[0] && !lines[0].includes(' ') && lines[0].trim().length > 0) {
            lang = lines[0].trim();
            lines.shift();
        }
        const code = lines.join('\n');
        const escapedCode = code.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const placeholder = `__CODEBLOCK_PLACEHOLDER_${codeBlocks.length}__`;
        codeBlocks.push(`<pre><code class="${lang ? 'language-' + lang : ''}">${escapedCode}</code></pre>`);
        return placeholder;
    });

    // 2. Inline code (`code`)
    const inlineCodes = [];
    html = html.replace(/`([^`\n]+)`/g, (match, code) => {
        const escapedCode = code.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const placeholder = `__INLINECODE_PLACEHOLDER_${inlineCodes.length}__`;
        inlineCodes.push(`<code>${escapedCode}</code>`);
        return placeholder;
    });

    // 3. Block elements (line by line)
    let lines = html.split('\n');
    let inList = false;
    let listType = ''; // 'ul' or 'ol'
    let listItems = [];
    let newLines = [];

    const flushList = () => {
        if (inList) {
            let listHtml = `<${listType}>` + listItems.map(item => `<li>${parseInlineMarkdown(item)}</li>`).join('') + `</${listType}>`;
            newLines.push(listHtml);
            inList = false;
            listItems = [];
        }
    };

    for (let i = 0; i < lines.length; i++) {
        let line = lines[i];
        
        // Headers
        let headerMatch = line.match(/^(#{1,6})\s+(.*)$/);
        if (headerMatch) {
            flushList();
            let level = headerMatch[1].length;
            let text = headerMatch[2];
            newLines.push(`<h${level}>${parseInlineMarkdown(text)}</h${level}>`);
            continue;
        }

        // Blockquote
        let bqMatch = line.match(/^>\s*(.*)$/);
        if (bqMatch) {
            flushList();
            let text = bqMatch[1];
            newLines.push(`<blockquote>${parseInlineMarkdown(text)}</blockquote>`);
            continue;
        }

        // Unordered List
        let ulMatch = line.match(/^[\*\-\+]\s+(.*)$/);
        if (ulMatch) {
            if (inList && listType !== 'ul') {
                flushList();
            }
            inList = true;
            listType = 'ul';
            listItems.push(ulMatch[1]);
            continue;
        }

        // Ordered List
        let olMatch = line.match(/^(\d+)\.\s+(.*)$/);
        if (olMatch) {
            if (inList && listType !== 'ol') {
                flushList();
            }
            inList = true;
            listType = 'ol';
            listItems.push(olMatch[2]);
            continue;
        }

        // Table
        if (line.trim().startsWith('|')) {
            flushList();
            let isTable = false;
            if (i + 1 < lines.length && lines[i + 1].trim().startsWith('|') && lines[i + 1].includes('-')) {
                isTable = true;
            }
            if (isTable) {
                let tableLines = [];
                while (i < lines.length && lines[i].trim().startsWith('|')) {
                    tableLines.push(lines[i]);
                    i++;
                }
                i--;
                
                let tableHtml = '<table>';
                let headers = tableLines[0].split('|').map(x => x.trim()).filter((x, idx, arr) => idx > 0 && idx < arr.length - 1);
                tableHtml += '<thead><tr>' + headers.map(h => `<th>${parseInlineMarkdown(h)}</th>`).join('') + '</tr></thead>';
                tableHtml += '<tbody>';
                for (let j = 2; j < tableLines.length; j++) {
                    let cells = tableLines[j].split('|').map(x => x.trim()).filter((x, idx, arr) => idx > 0 && idx < arr.length - 1);
                    tableHtml += '<tr>' + cells.map(c => `<td>${parseInlineMarkdown(c)}</td>`).join('') + '</tr>';
                }
                tableHtml += '</tbody></table>';
                newLines.push(tableHtml);
                continue;
            }
        }

        // Empty line
        if (line.trim() === '') {
            flushList();
            newLines.push('');
            continue;
        }

        if (inList) {
            listItems[listItems.length - 1] += '\n' + line;
        } else {
            newLines.push(parseInlineMarkdown(line));
        }
    }
    flushList();

    let finalHtml = '';
    let pContent = [];
    for (let line of newLines) {
        if (line.trim() === '') {
            if (pContent.length > 0) {
                finalHtml += `<p>${pContent.join('<br>')}</p>\n`;
                pContent = [];
            }
        } else if (line.startsWith('<h') || line.startsWith('<pre') || line.startsWith('<blockquote') || line.startsWith('<ul') || line.startsWith('<ol') || line.startsWith('<table') || line.startsWith('<details')) {
            if (pContent.length > 0) {
                finalHtml += `<p>${pContent.join('<br>')}</p>\n`;
                pContent = [];
            }
            finalHtml += line + '\n';
        } else {
            pContent.push(line);
        }
    }
    if (pContent.length > 0) {
        finalHtml += `<p>${pContent.join('<br>')}</p>\n`;
    }

    finalHtml = finalHtml.replace(/__INLINECODE_PLACEHOLDER_(\d+)__/g, (match, idx) => {
        return inlineCodes[parseInt(idx)];
    });
    finalHtml = finalHtml.replace(/__CODEBLOCK_PLACEHOLDER_(\d+)__/g, (match, idx) => {
        return codeBlocks[parseInt(idx)];
    });

    return finalHtml;
}

function parseInlineMarkdown(text) {
    let html = text;
    html = html.replace(/\*\*([\s\S]*?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/__([\s\S]*?)__/g, '<strong>$1</strong>');
    html = html.replace(/\*([\s\S]*?)\*/g, '<em>$1</em>');
    html = html.replace(/_([\s\S]*?)_/g, '<em>$1</em>');
    html = html.replace(/~~([\s\S]*?)~~/g, '<del>$1</del>');
    html = html.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" class="blog-image">');
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');
    return html;
}

window.parseMarkdownToHtml = parseMarkdownToHtml;

function convertHtmlToMarkdown(html) {
    if (!html) return '';
    const temp = document.createElement('div');
    temp.innerHTML = html;
    return nodeToMarkdown(temp).trim();
}

function nodeToMarkdown(node) {
    if (node.nodeType === Node.TEXT_NODE) {
        return node.textContent;
    }
    
    if (node.nodeType !== Node.ELEMENT_NODE) {
        return '';
    }
    
    const tagName = node.tagName.toUpperCase();
    let childrenMarkdown = '';
    
    // Process children
    for (let child of node.childNodes) {
        childrenMarkdown += nodeToMarkdown(child);
    }
    
    switch (tagName) {
        case 'DIV':
            if (node.classList.contains('spoiler-content')) {
                return childrenMarkdown;
            }
            return childrenMarkdown + '\n';
        case 'P':
            return childrenMarkdown.trim() ? '\n\n' + childrenMarkdown.trim() + '\n\n' : '';
        case 'BR':
            return '\n';
        case 'STRONG':
        case 'B':
            return childrenMarkdown.trim() ? `**${childrenMarkdown.trim()}**` : '';
        case 'EM':
        case 'I':
            return childrenMarkdown.trim() ? `*${childrenMarkdown.trim()}*` : '';
        case 'DEL':
        case 'S':
            return childrenMarkdown.trim() ? `~~${childrenMarkdown.trim()}~~` : '';
        case 'H1':
            return `\n# ${childrenMarkdown.trim()}\n`;
        case 'H2':
            return `\n## ${childrenMarkdown.trim()}\n`;
        case 'H3':
            return `\n### ${childrenMarkdown.trim()}\n`;
        case 'H4':
            return `\n#### ${childrenMarkdown.trim()}\n`;
        case 'H5':
            return `\n##### ${childrenMarkdown.trim()}\n`;
        case 'H6':
            return `\n###### ${childrenMarkdown.trim()}\n`;
        case 'BLOCKQUOTE':
            const lines = childrenMarkdown.trim().split('\n');
            return '\n' + lines.map(line => `> ${line}`).join('\n') + '\n';
        case 'UL':
            return '\n' + childrenMarkdown + '\n';
        case 'OL':
            let olMarkdown = '\n';
            let index = 1;
            for (let child of node.childNodes) {
                if (child.nodeType === Node.ELEMENT_NODE && child.tagName.toUpperCase() === 'LI') {
                    olMarkdown += `${index}. ${nodeToMarkdown(child).trim()}\n`;
                    index++;
                } else {
                    olMarkdown += nodeToMarkdown(child);
                }
            }
            return olMarkdown + '\n';
        case 'LI':
            if (node.parentNode && node.parentNode.tagName.toUpperCase() === 'OL') {
                return childrenMarkdown;
            }
            return `* ${childrenMarkdown.trim()}\n`;
        case 'A':
            const href = node.getAttribute('href') || '';
            return `[${childrenMarkdown.trim()}](${href})`;
        case 'IMG':
            const src = node.getAttribute('src') || '';
            const alt = node.getAttribute('alt') || 'Изображение';
            return `![${alt}](${src})`;
        case 'PRE':
            const codeEl = node.querySelector('code');
            if (codeEl) {
                let lang = '';
                for (let cls of codeEl.classList) {
                    if (cls.startsWith('language-')) {
                        lang = cls.replace('language-', '');
                    }
                }
                return `\n\`\`\`${lang}\n${codeEl.textContent}\n\`\`\`\n`;
            }
            return `\n\`\`\`\n${node.textContent}\n\`\`\`\n`;
        case 'CODE':
            if (node.parentNode && node.parentNode.tagName.toUpperCase() === 'PRE') {
                return childrenMarkdown;
            }
            return `\`${node.textContent}\``;
        case 'TABLE':
            let tableMd = '\n';
            const rows = node.querySelectorAll('tr');
            if (rows.length > 0) {
                const firstRowCells = rows[0].querySelectorAll('th, td');
                const colCount = firstRowCells.length;
                tableMd += '| ' + Array.from(firstRowCells).map(cell => nodeToMarkdown(cell).trim()).join(' | ') + ' |\n';
                tableMd += '| ' + Array.from({length: colCount}, () => '---').join(' | ') + ' |\n';
                for (let r = 1; r < rows.length; r++) {
                    const cells = rows[r].querySelectorAll('th, td');
                    tableMd += '| ' + Array.from(cells).map(cell => nodeToMarkdown(cell).trim()).join(' | ') + ' |\n';
                }
            }
            return tableMd + '\n';
        case 'DETAILS':
            const summaryEl = node.querySelector('summary');
            const summaryText = summaryEl ? summaryEl.textContent : 'Подробности';
            const contentEl = node.querySelector('.spoiler-content') || node.querySelector('div');
            const contentMd = contentEl ? nodeToMarkdown(contentEl) : '';
            return `\n<details class="spoiler-block"><summary class="spoiler-title">${summaryText}</summary><div class="spoiler-content">${parseMarkdownToHtml(contentMd)}</div></details>\n`;
        default:
            return childrenMarkdown;
    }
}

window.convertHtmlToMarkdown = convertHtmlToMarkdown;
window.getCurrentEditId = function() {
    return typeof currentEditId !== 'undefined' ? currentEditId : null;
};

    // --- Менеджер шаблонов ---
    let templatesList = [];
    let postsList = [];
    let currentTemplateName = null;
    let postTemplatesMeta = {};
    let defaultTemplateName = 'main';

    function openTemplateManager() {
        fetch('get_templates.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    templatesList = data.templates;
                    postsList = data.posts;
                    defaultTemplateName = data.default;
                    postTemplatesMeta = data.post_templates || {};
                    renderTemplatesGrid();
                    document.getElementById('templateManagerDialog').style.display = 'block';
                } else {
                    showNotification('Не удалось загрузить шаблоны: ' + data.error, 'error');
                }
            })
            .catch(err => {
                showNotification('Ошибка загрузки шаблонов', 'error');
            });
    }

    function closeTemplateManager() {
        document.getElementById('templateManagerDialog').style.display = 'none';
    }

    function renderTemplatesGrid() {
        const grid = document.getElementById('templatesGrid');
        grid.innerHTML = '';
        
        templatesList.forEach(tpl => {
            const card = document.createElement('div');
            card.className = 'template-card';
            card.onclick = () => openTemplateDetails(tpl.name);
            
            // Build badges
            let badges = '';
            if (tpl.name === 'main') {
                badges += `<span style="background: #3b82f6; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; margin-left: 4px;">Главный</span>`;
            }
            if (tpl.name === defaultTemplateName) {
                badges += `<span style="background: #10b981; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; margin-left: 4px;">По умолчанию</span>`;
            }
            
            // Generate miniature preview HTML
            const previewHtml = getTemplatePreviewHtml(tpl.code);
            
            card.innerHTML = `
                <div class="template-preview-card-wrap">
                    <iframe class="template-preview-iframe" srcdoc="${escapeHtml(previewHtml)}"></iframe>
                    <div style="position: absolute; top:0; left:0; right:0; bottom:0; background:transparent; z-index:2;"></div>
                </div>
                <div style="padding: 12px; flex: 1; display: flex; flex-direction: column; gap: 6px;">
                    <div style="font-weight: 600; font-size: 14px; display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                        <span style="color: var(--text-color);">${tpl.title}</span>
                        <div style="display: flex; gap: 2px;">${badges}</div>
                    </div>
                    <div style="font-size: 12px; opacity: 0.7; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 34px; color: var(--text-color);">
                        ${tpl.description || 'Нет описания'}
                    </div>
                </div>
            `;
            grid.appendChild(card);
        });
    }

    function getTemplatePreviewHtml(templateCode) {
        let mockContent = `
            <p>Это пример текста статьи для предпросмотра шаблона. Здесь вы можете увидеть, как будут выглядеть ваши абзацы, ссылки, списки и другие элементы.</p>
            <h2>Подзаголовок статьи</h2>
            <p>А здесь ссылка на <a href="#">какой-то внешний ресурс</a>.</p>
            <ul>
                <li>Первый пункт списка</li>
                <li>Второй пункт списка</li>
            </ul>
            <div class="blog-image-align-wrap" style="text-align:center">
                <div class="blog-image-wrap">
                    <div style="background:#4CAF50;color:white;padding:40px;border-radius:8px;font-weight:bold;">Пример картинки / медиа</div>
                    <span class="caption">Подпись к медиа-файлу</span>
                </div>
            </div>
        `;
        let preview = templateCode
            .replace(/\{\{TITLE\}\}/g, 'Пример заголовка статьи')
            .replace(/\{\{DATE\}\}/g, '20.06.2026 12:00')
            .replace(/\{\{POST_ID\}\}/g, '1')
            .replace(/\{\{META_TAGS\}\}/g, '')
            .replace(/\{\{CUSTOM_FONTS\}\}/g, '')
            .replace(/\{\{BODY_STYLE\}\}/g, '')
            .replace(/\{\{CONTENT_WRAPPER_START\}\}/g, '')
            .replace(/\{\{CONTENT_WRAPPER_END\}\}/g, '')
            .replace(/\{\{CONTENT\}\}/g, mockContent);

        // Inject <base href="data/blog/"> inside <head> if present to resolve relative URLs of CSS and JS assets correctly
        if (!preview.includes('<base ') && preview.includes('<head>')) {
            preview = preview.replace('<head>', '<head>\n    <base href="data/blog/">');
        } else if (!preview.includes('<base ')) {
            preview = '<base href="data/blog/">' + preview;
        }

        return preview;
    }

    function escapeHtml(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function triggerTemplateUpload() {
        document.getElementById('templateFileInput').click();
    }

    async function handleTemplateUpload(input) {
        if (!input.files || input.files.length === 0) return;
        
        const files = Array.from(input.files);
        input.value = '';
        
        let successCount = 0;
        let errors = [];
        
        for (const file of files) {
            const formData = new FormData();
            formData.append('template_file', file);
            
            try {
                const res = await fetch('upload_template.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    successCount++;
                } else {
                    if (data.missing) {
                        errors.push(`Файл ${file.name}: не хватает плейсхолдеров: ${data.missing.join(', ')}`);
                    } else {
                        errors.push(`Файл ${file.name}: ${data.error}`);
                    }
                }
            } catch (err) {
                errors.push(`Файл ${file.name}: ошибка сети`);
            }
        }
        
        if (successCount > 0) {
            showNotification(`Успешно загружено шаблонов: ${successCount}`, 'success');
            openTemplateManager();
        }
        
        if (errors.length > 0) {
            errors.forEach(err => {
                showNotification(err, 'error');
            });
        }
    }

    function openTemplateDetails(name) {
        currentTemplateName = name;
        const tpl = templatesList.find(t => t.name === name);
        if (!tpl) return;
        
        document.getElementById('detailsTemplateTitle').textContent = `Детали шаблона: ${tpl.title}`;
        document.getElementById('detailsTemplateNameInput').value = tpl.title;
        document.getElementById('detailsTemplateNameInput').disabled = tpl.is_system;
        document.getElementById('detailsTemplateDescriptionInput').value = tpl.description || '';
        document.getElementById('detailsTemplateCodeInput').value = tpl.code;
        
        const deleteBtn = document.getElementById('deleteTemplateBtn');
        if (tpl.is_system || tpl.name === 'main' || tpl.name === defaultTemplateName) {
            deleteBtn.style.display = 'none';
        } else {
            deleteBtn.style.display = 'block';
        }
        
        updateTemplateLivePreview();
        document.getElementById('templateDetailsDialog').style.display = 'block';
    }

    // Live update live preview inside text area
    let previewDebounce = null;
    function updateTemplateLivePreview() {
        if (previewDebounce) clearTimeout(previewDebounce);
        previewDebounce = setTimeout(() => {
            const code = document.getElementById('detailsTemplateCodeInput').value;
            const previewHtml = getTemplatePreviewHtml(code);
            const iframe = document.getElementById('templatePreviewIframe');
            iframe.srcdoc = previewHtml;
        }, 300);
    }

    function closeTemplateDetails() {
        document.getElementById('templateDetailsDialog').style.display = 'none';
        document.getElementById('saveTemplateDropdownMenu').style.display = 'none';
    }

    function toggleSaveTemplateDropdown() {
        const menu = document.getElementById('saveTemplateDropdownMenu');
        const isVisible = menu.style.display === 'flex';
        menu.style.display = isVisible ? 'none' : 'flex';
    }

    // Hide dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const btn = document.getElementById('saveTemplateDropdownBtn');
        const menu = document.getElementById('saveTemplateDropdownMenu');
        if (btn && menu && !btn.contains(e.target) && !menu.contains(e.target)) {
            menu.style.display = 'none';
        }
    });

    function saveTemplateData() {
        const title = document.getElementById('detailsTemplateNameInput').value.trim();
        const description = document.getElementById('detailsTemplateDescriptionInput').value.trim();
        const code = document.getElementById('detailsTemplateCodeInput').value;
        
        if (title === '') {
            showNotification('Введите название шаблона', 'warning');
            return Promise.reject('Empty title');
        }
        
        return fetch('save_template.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: currentTemplateName,
                title: title,
                description: description,
                code: code
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                return true;
            } else {
                if (data.missing) {
                    showNotification('В коде отсутствуют обязательные плейсхолдеры: ' + data.missing.join(', '), 'error');
                } else {
                    showNotification('Ошибка сохранения: ' + data.error, 'error');
                }
                throw new Error(data.error);
            }
        });
    }

    function saveAndApplyTemplateToAll() {
        saveTemplateData()
            .then(() => {
                return fetch('apply_template.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        template_name: currentTemplateName,
                        mode: 'default'
                    })
                });
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeTemplateDetails();
                    openTemplateManager(); // Refresh grid
                } else {
                    showNotification('Ошибка применения шаблона: ' + data.error, 'error');
                }
            })
            .catch(err => {
                console.error(err);
            });
    }

    function showApplyToSpecificPostList() {
        saveTemplateData()
            .then(() => {
                document.getElementById('templatePostSearchInput').value = '';
                renderTemplatePostList();
                document.getElementById('applyToPostModal').style.display = 'block';
            })
            .catch(err => {
                console.error(err);
            });
    }

    function closeApplyToPostModal() {
        document.getElementById('applyToPostModal').style.display = 'none';
    }

    function renderTemplatePostList() {
        const container = document.getElementById('templatePostList');
        container.innerHTML = '';
        
        if (postsList.length === 0) {
            container.innerHTML = '<div style="text-align: center; opacity: 0.6; padding: 10px;">Нет статей</div>';
            return;
        }
        
        postsList.forEach(post => {
            const item = document.createElement('div');
            item.className = 'template-post-item';
            item.setAttribute('data-title', post.title.toLowerCase());
            
            // Check if this post currently uses this template
            const isAssigned = postTemplatesMeta[post.id] === currentTemplateName;
            
            item.innerHTML = `
                <div style="flex: 1; min-width: 0; padding-right: 10px;">
                    <div style="font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text-color);">${post.title}</div>
                    <div style="font-size: 11px; opacity: 0.6; margin-top: 2px;">Дата: ${post.date} ${isAssigned ? '• <span style="color:#10b981; font-weight:600;">Уже применен</span>' : ''}</div>
                </div>
                <button type="button" onclick="applyTemplateToPost(${post.id})" style="padding: 6px 12px; background: ${isAssigned ? '#10b981' : 'var(--primary-color, #4CAF50)'}; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 500;">
                    ${isAssigned ? 'Переприменить' : 'Выбрать'}
                </button>
            `;
            container.appendChild(item);
        });
    }

    function filterTemplatePosts() {
        const query = document.getElementById('templatePostSearchInput').value.toLowerCase().trim();
        const items = document.querySelectorAll('.template-post-item');
        
        items.forEach(item => {
            const title = item.getAttribute('data-title');
            if (title.indexOf(query) !== -1) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function applyTemplateToPost(postId) {
        fetch('apply_template.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                template_name: currentTemplateName,
                mode: 'post',
                post_id: postId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                closeApplyToPostModal();
                closeTemplateDetails();
                openTemplateManager(); // Refresh grid
            } else {
                showNotification('Ошибка: ' + data.error, 'error');
            }
        })
        .catch(err => {
            showNotification('Ошибка сети при применении шаблона', 'error');
        });
    }

    function deleteCurrentTemplate() {
        showConfirm('Вы действительно хотите удалить этот шаблон?').then(confirmed => {
            if (!confirmed) return;
            
            fetch('delete_template.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: currentTemplateName
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showNotification('Шаблон удален', 'success');
                    closeTemplateDetails();
                    openTemplateManager(); // Refresh grid
                } else {
                    showNotification('Ошибка при удалении шаблона: ' + data.error, 'error');
                }
            })
            .catch(err => {
                showNotification('Ошибка сети при удалении шаблона', 'error');
            });
        });
    }

    function showTemplatePlaceholdersInfo(e) {
        e.preventDefault();
        const info = `Обязательные плейсхолдеры в шаблоне:\n\n` +
            `{{TITLE}} - заголовок статьи\n` +
            `{{DATE}} - дата публикации\n` +
            `{{POST_ID}} - ID статьи\n` +
            `{{CONTENT}} - основной контент\n` +
            `{{META_TAGS}} - метатеги SEO\n` +
            `{{CUSTOM_FONTS}} - блок шрифтов\n` +
            `{{BODY_STYLE}} - стили тела документа\n` +
            `{{CONTENT_WRAPPER_START}} - начало обертки контента\n` +
            `{{CONTENT_WRAPPER_END}} - конец обертки контента`;
        alert(info);
    }

    function showTemplateInstructions() {
        document.getElementById('templateInstructionsDialog').style.display = 'block';
    }

    function closeTemplateInstructions() {
        document.getElementById('templateInstructionsDialog').style.display = 'none';
    }

    // Export functions to window scope
    window.openTemplateManager = openTemplateManager;
    window.closeTemplateManager = closeTemplateManager;
    window.triggerTemplateUpload = triggerTemplateUpload;
    window.handleTemplateUpload = handleTemplateUpload;
    window.openTemplateDetails = openTemplateDetails;
    window.closeTemplateDetails = closeTemplateDetails;
    window.updateTemplateLivePreview = updateTemplateLivePreview;
    window.toggleSaveTemplateDropdown = toggleSaveTemplateDropdown;
    window.saveAndApplyTemplateToAll = saveAndApplyTemplateToAll;
    window.showApplyToSpecificPostList = showApplyToSpecificPostList;
    window.closeApplyToPostModal = closeApplyToPostModal;
    window.filterTemplatePosts = filterTemplatePosts;
    window.applyTemplateToPost = applyTemplateToPost;
    window.deleteCurrentTemplate = deleteCurrentTemplate;
    window.showTemplatePlaceholdersInfo = showTemplatePlaceholdersInfo;
    window.showTemplateInstructions = showTemplateInstructions;
    window.closeTemplateInstructions = closeTemplateInstructions;

// --- Наборы смайлов ---
let smileFilesToUpload = [];
let smileSetNameTarget = '';

function openSmileSetsDialog() {
    document.getElementById('smileSetsDialog').style.display = 'block';
    loadSmileSetsList();
    resetSmileUploadState();
}

function closeSmileSetsDialog() {
    document.getElementById('smileSetsDialog').style.display = 'none';
    resetSmileUploadState();
}

function handleSmileDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    const zone = document.getElementById('smileDropzone');
    if (zone) {
        zone.style.borderColor = 'var(--text-color)';
        zone.style.background = 'rgba(255, 255, 255, 0.05)';
    }
}

function handleSmileDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    const zone = document.getElementById('smileDropzone');
    if (zone) {
        zone.style.borderColor = 'var(--border-color)';
        zone.style.background = 'rgba(0, 0, 0, 0.02)';
    }
}

async function handleSmileDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    const zone = document.getElementById('smileDropzone');
    if (zone) {
        zone.style.borderColor = 'var(--border-color)';
        zone.style.background = 'rgba(0, 0, 0, 0.02)';
    }

    const files = [];
    if (e.dataTransfer.items) {
        const entries = [];
        for (let i = 0; i < e.dataTransfer.items.length; i++) {
            const item = e.dataTransfer.items[i];
            if (item.kind === 'file') {
                const entry = item.webkitGetAsEntry();
                if (entry) {
                    entries.push(entry);
                }
            }
        }
        await readSmileEntries(entries, files);
    } else {
        for (let i = 0; i < e.dataTransfer.files.length; i++) {
            files.push(e.dataTransfer.files[i]);
        }
    }

    processSelectedSmiles(files);
}

async function readSmileEntries(entries, fileList) {
    for (const entry of entries) {
        if (entry.isFile) {
            const file = await new Promise((resolve) => entry.file(resolve));
            file.customRelativePath = entry.fullPath ? entry.fullPath.replace(/^\//, '') : file.name;
            fileList.push(file);
        } else if (entry.isDirectory) {
            const reader = entry.createReader();
            const childEntries = await new Promise((resolve) => {
                reader.readEntries(resolve);
            });
            await readSmileEntries(childEntries, fileList);
        }
    }
}

function handleSmileFileSelect(e) {
    const input = e.target;
    if (input && input.files.length > 0) {
        processSelectedSmiles(Array.from(input.files));
    }
}

function processSelectedSmiles(files) {
    smileFilesToUpload = files.filter(file => file.name.toLowerCase().endsWith('.gif'));
    
    if (smileFilesToUpload.length === 0) {
        showNotification('В выбранных файлах не найдено изображений формата .gif', 'warning');
        resetSmileUploadState();
        return;
    }

    let detectedName = '';
    const firstFile = smileFilesToUpload[0];
    const relPath = firstFile.customRelativePath || firstFile.webkitRelativePath;
    
    if (relPath && relPath.includes('/')) {
        detectedName = relPath.split('/')[0];
    }

    const nameField = document.getElementById('smileSetNameField');
    const nameInput = document.getElementById('smileSetNameInput');
    const infoText = document.getElementById('smileSelectedFilesInfo');
    const countSpan = document.getElementById('smileSelectedCount');
    const btnContainer = document.getElementById('smileUploadBtnContainer');

    if (detectedName) {
        smileSetNameTarget = detectedName;
        nameInput.value = detectedName;
        if (nameField) nameField.style.display = 'block';
    } else {
        smileSetNameTarget = '';
        nameInput.value = '';
        if (nameField) nameField.style.display = 'block';
    }

    if (countSpan) countSpan.textContent = smileFilesToUpload.length;
    if (infoText) infoText.style.display = 'block';
    if (btnContainer) btnContainer.style.display = 'block';
    
    const dropzoneText = document.getElementById('smileDropzoneText');
    if (dropzoneText) dropzoneText.textContent = 'Файлы успешно выбраны';
}

function resetSmileUploadState() {
    smileFilesToUpload = [];
    smileSetNameTarget = '';
    
    const folderInput = document.getElementById('smileFolderInput');
    const filesInput = document.getElementById('smileFilesInput');
    const nameInput = document.getElementById('smileSetNameInput');
    const nameField = document.getElementById('smileSetNameField');
    const infoText = document.getElementById('smileSelectedFilesInfo');
    const btnContainer = document.getElementById('smileUploadBtnContainer');
    const dropzoneText = document.getElementById('smileDropzoneText');

    if (folderInput) folderInput.value = '';
    if (filesInput) filesInput.value = '';
    if (nameInput) nameInput.value = '';
    if (nameField) nameField.style.display = 'none';
    if (infoText) infoText.style.display = 'none';
    if (btnContainer) btnContainer.style.display = 'none';
    if (dropzoneText) dropzoneText.textContent = 'Перетащите папку со смайлами сюда';
}

async function loadSmileSetsList() {
    const listContainer = document.getElementById('smileSetsList');
    if (!listContainer) return;
    
    try {
        const response = await fetch('get_smiles.php?t=' + Date.now());
        const data = await response.json();
        
        if (data.success) {
            const setNames = Object.keys(data.sets);
            if (setNames.length === 0) {
                listContainer.innerHTML = '<div style="text-align: center; opacity: 0.6; padding: 10px; color: var(--text-color);">Нет загруженных наборов</div>';
            } else {
                listContainer.innerHTML = setNames.map(name => {
                    const count = data.sets[name].length;
                    return `<div class="smile-set-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--border-color); color: var(--text-color); transition: background 0.2s;" onmouseover="this.style.background='rgba(128,128,128,0.04)'" onmouseout="this.style.background='transparent'">
                        <span class="smile-set-name" style="font-weight: 500; font-size: 14px;">${escapeHtml(name)} <span class="smile-set-count" style="font-size: 12px; opacity: 0.5; margin-left: 8px;">(${count} шт.)</span></span>
                        <button type="button" class="smile-set-delete-btn" style="background: transparent; color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 6px; padding: 5px 12px; cursor: pointer; font-size: 12px; transition: all 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.1)'; this.style.borderColor='#ef4444'" onmouseout="this.style.background='transparent'; this.style.borderColor='rgba(239,68,68,0.4)'" onclick="deleteSmileSet('${escapeHtmlJS(name)}')">Удалить</button>
                    </div>`;
                }).join('');
            }
        } else {
            listContainer.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 10px;">Ошибка загрузки списка</div>';
        }
    } catch (error) {
        console.error('Error loading smile sets:', error);
        listContainer.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 10px;">Ошибка сети</div>';
    }
}

async function handleSmileSetUpload() {
    const formData = new FormData();
    let files = smileFilesToUpload;
    let setName = document.getElementById('smileSetNameInput').value.trim();

    if (files.length === 0) {
        showNotification('Выберите файлы или папку для загрузки', 'warning');
        return;
    }
    if (!setName) {
        showNotification('Введите название для набора', 'warning');
        return;
    }

    formData.append('setName', setName);
    files.forEach(file => {
        formData.append('smiles[]', file);
    });

    try {
        showNotification('Загрузка смайлов...', 'info');
        const response = await fetch('upload_smiles.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            showNotification(`Набор "${setName}" успешно загружен (${data.count} смайлов)`, 'success');
            loadSmileSetsList();
            resetSmileUploadState();
        } else {
            showNotification('Ошибка загрузки: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error uploading smiles:', error);
        showNotification('Ошибка сети при загрузке набора', 'error');
    }
}

// Expose functions to window
window.openSmileSetsDialog = openSmileSetsDialog;
window.closeSmileSetsDialog = closeSmileSetsDialog;
window.handleSmileDragOver = handleSmileDragOver;
window.handleSmileDragLeave = handleSmileDragLeave;
window.handleSmileDrop = handleSmileDrop;
window.handleSmileFileSelect = handleSmileFileSelect;
window.handleSmileSetUpload = handleSmileSetUpload;

async function deleteSmileSet(setName) {
    showConfirm(`Удалить набор смайлов "${setName}"? Все файлы этого набора будут удалены.`).then(async (result) => {
        if (!result) return;
        
        const formData = new FormData();
        formData.append('setName', setName);
        
        try {
            const response = await fetch('delete_smile_set.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                showNotification(`Набор "${setName}" успешно удален`, 'success');
                loadSmileSetsList();
            } else {
                showNotification('Ошибка удаления: ' + data.error, 'error');
            }
        } catch (error) {
            console.error('Error deleting smile set:', error);
            showNotification('Ошибка сети при удалении набора', 'error');
        }
    });
}

function toggleSmilesSubmenu(event) {
    event.stopPropagation();
    
    const button = event.currentTarget;
    const isOpen = button.classList.contains('submenu-open');
    
    document.querySelectorAll('.more-menu-item.has-submenu').forEach(btn => {
        if (btn !== button) {
            btn.classList.remove('submenu-open');
        }
    });
    
    if (!isOpen) {
        button.classList.add('submenu-open');
        loadSmilesSubmenuList();
    } else {
        button.classList.remove('submenu-open');
    }
}

async function loadSmilesSubmenuList() {
    const submenu = document.getElementById('smilesSubmenu');
    if (!submenu) return;
    
    try {
        const response = await fetch('get_smiles.php?t=' + Date.now());
        const data = await response.json();
        
        if (data.success) {
            const setNames = Object.keys(data.sets);
            const nonEmptySets = setNames.filter(name => data.sets[name].length > 0);
            
            if (nonEmptySets.length === 0) {
                submenu.innerHTML = '<div class="more-submenu-empty">Нет смайлов. Добавьте их через "Наборы смайлов"</div>';
            } else {
                let html = '<div class="smiles-submenu-container">';
                nonEmptySets.forEach(setName => {
                    html += `<div class="smile-set-section">
                        <div class="smile-set-title">${escapeHtml(setName)}</div>
                        <div class="smile-items-grid">`;
                    
                    data.sets[setName].forEach(url => {
                        html += `<button type="button" class="smile-item-btn" onclick="insertSmile('${escapeHtmlJS(url)}')" title="Вставить смайл">
                            <img src="${url}" alt="smile">
                        </button>`;
                    });
                    
                    html += `</div></div>`;
                });
                html += '</div>';
                submenu.innerHTML = html;
            }
        } else {
            submenu.innerHTML = '<div class="more-submenu-empty">Ошибка загрузки смайлов</div>';
        }
    } catch (error) {
        console.error('Error loading smiles for submenu:', error);
        submenu.innerHTML = '<div class="more-submenu-empty">Ошибка сети</div>';
    }
}

function insertSmile(url) {
    const html = `<img src="${url}" class="blog-smile" alt="smile">`;
    if (editorMode === 'code') {
        const ta = document.getElementById('content');
        const cursorPos = ta.selectionStart;
        ta.value = ta.value.substring(0, cursorPos) + html + ta.value.substring(cursorPos);
    } else {
        insertHtmlAtCaret(html);
    }
    saveToHistory();
}

function escapeHtmlJS(str) {
    return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

window.openSmileSetsDialog = openSmileSetsDialog;
window.closeSmileSetsDialog = closeSmileSetsDialog;
window.handleSmileSetUpload = handleSmileSetUpload;
window.deleteSmileSet = deleteSmileSet;
window.toggleSmilesSubmenu = toggleSmilesSubmenu;
window.insertSmile = insertSmile;

// --- Вставка и редактирование кастомной кнопки со ссылкой ---
let isUpdatingBtnFromRaw = false;
let editingCustomBtnTarget = null;

function openInsertButtonDialog() {
    editingCustomBtnTarget = null;
    const dialogTitle = document.getElementById('customButtonDialogTitle');
    if (dialogTitle) dialogTitle.textContent = 'Вставить кнопку со ссылкой';
    const submitBtn = document.getElementById('customButtonSubmitBtn');
    if (submitBtn) submitBtn.textContent = 'Вставить кнопку';

    const textInput = document.getElementById('btnTextInput');
    const urlInput = document.getElementById('btnUrlInput');
    const targetInput = document.getElementById('btnTargetInput');
    if (textInput) textInput.value = 'Перейти на сайт';
    if (urlInput) urlInput.value = 'https://example.com';
    if (targetInput) targetInput.checked = true;

    applyBtnPreset('editor');

    const dialog = document.getElementById('customButtonDialog');
    if (!dialog) return;
    dialog.style.display = 'flex';
    dialog.classList.add('show');
    switchBtnTab('gui');
    updateCustomBtnPreview();
}

function openEditCustomButtonDialog(customBtn) {
    if (!customBtn) return;
    editingCustomBtnTarget = customBtn;

    const dialogTitle = document.getElementById('customButtonDialogTitle');
    if (dialogTitle) dialogTitle.textContent = 'Редактировать кнопку';
    const submitBtn = document.getElementById('customButtonSubmitBtn');
    if (submitBtn) submitBtn.textContent = 'Сохранить изменения';

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

    const dialog = document.getElementById('customButtonDialog');
    if (!dialog) return;
    dialog.style.display = 'flex';
    dialog.classList.add('show');
    switchBtnTab('gui');
    updateCustomBtnPreview();
}

function closeCustomButtonDialog() {
    const dialog = document.getElementById('customButtonDialog');
    if (!dialog) return;
    dialog.style.display = 'none';
    dialog.classList.remove('show');
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

    const text = textInput.value || 'Текст кнопки';
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
            showNotification('Пожалуйста, введите текст и ссылку для кнопки', 'warning');
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
            showNotification('Кнопка со ссылкой успешно обновлена!', 'success');
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
            showNotification('Кнопка со ссылкой успешно вставлена!', 'success');
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

function checkDevWarning() {
    if (window.isDevBuild) {
        const warningAccepted = localStorage.getItem('devWarningAccepted');
        if (!warningAccepted) {
            const devWarningDialog = document.getElementById('devWarningDialog');
            if (devWarningDialog) {
                devWarningDialog.style.display = 'flex';
            }
        }
    }
}

function confirmDevWarning() {
    localStorage.setItem('devWarningAccepted', 'true');
    const devWarningDialog = document.getElementById('devWarningDialog');
    if (devWarningDialog) {
        devWarningDialog.style.display = 'none';
    }
}

window.checkDevWarning = checkDevWarning;
window.confirmDevWarning = confirmDevWarning;


// Глобальная функция навигации по галерее
window.navigateGallery = function(galleryId, direction) {
    const gallery = document.getElementById(galleryId);
    if (!gallery) return;
    
    const images = gallery.querySelectorAll(`img[data-gallery="${galleryId}"]`);
    if (images.length <= 1) return;
    
    let currentIndex = -1;
    images.forEach((img, index) => {
        if (img.style.display !== 'none') {
            currentIndex = index;
        }
    });
    
    if (currentIndex === -1) currentIndex = 0;
    
    let newIndex = currentIndex + direction;
    if (newIndex < 0) newIndex = images.length - 1;
    if (newIndex >= images.length) newIndex = 0;
    
    images[currentIndex].style.display = 'none';
    images[newIndex].style.display = 'block';
    
    const indicator = gallery.querySelector('.gallery-indicator');
    if (indicator) {
        indicator.textContent = `${newIndex + 1} / ${images.length}`;
    }
};

// Поддержка свайпов на мобильных устройствах для галерей
document.addEventListener('DOMContentLoaded', function() {
    let touchStartX = 0;
    let touchEndX = 0;
    let targetGallery = null;
    
    document.addEventListener('touchstart', function(e) {
        const gallery = e.target.closest('.image-gallery');
        if (gallery) {
            targetGallery = gallery;
            touchStartX = e.changedTouches[0].screenX;
        }
    }, { passive: true });
    
    document.addEventListener('touchend', function(e) {
        if (!targetGallery) return;
        
        touchEndX = e.changedTouches[0].screenX;
        const galleryId = targetGallery.id;
        
        const swipeThreshold = 50;
        if (touchStartX - touchEndX > swipeThreshold) {
            // Свайп влево - следующее изображение
            window.navigateGallery(galleryId, 1);
        } else if (touchEndX - touchStartX > swipeThreshold) {
            // Свайп вправо - предыдущее изображение
            window.navigateGallery(galleryId, -1);
        }
        
        targetGallery = null;
    }, { passive: true });
    
    // Поддержка клавиатуры (стрелки) для активной галереи в редакторе
    const contentVisual = document.getElementById('contentVisual');
    if (contentVisual) {
        contentVisual.addEventListener('keydown', function(e) {
            // Проверяем, находится ли фокус внутри галереи или рядом с ней
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0) return;
            
            let node = selection.anchorNode;
            while (node && node !== contentVisual) {
                if (node.classList && node.classList.contains('image-gallery')) {
                    if (e.key === 'ArrowLeft') {
                        e.preventDefault();
                        window.navigateGallery(node.id, -1);
                    } else if (e.key === 'ArrowRight') {
                        e.preventDefault();
                        window.navigateGallery(node.id, 1);
                    }
                    break;
                }
                node = node.parentNode;
            }
        });
        
        // Добавляем возможность наведения для фокуса на галерее
        contentVisual.addEventListener('mouseenter', function(e) {
            const gallery = e.target.closest('.image-gallery');
            if (gallery) {
                gallery.setAttribute('data-focused', 'true');
            }
        }, true);
        
        contentVisual.addEventListener('mouseleave', function(e) {
            const gallery = e.target.closest('.image-gallery');
            if (gallery) {
                gallery.removeAttribute('data-focused');
            }
        }, true);
    }
});

/* ==========================================================================
   Custom Select Dropdown Engine
   ========================================================================== */
(function() {
    // Intercept HTMLSelectElement.prototype.value and selectedIndex to auto-sync custom UI
    const originalValueDescriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
    if (originalValueDescriptor && originalValueDescriptor.set) {
        Object.defineProperty(HTMLSelectElement.prototype, 'value', {
            get: function() {
                return originalValueDescriptor.get.call(this);
            },
            set: function(val) {
                const res = originalValueDescriptor.set.call(this, val);
                if (this.dataset && this.dataset.customSelectInitialized === 'true') {
                    syncCustomSelectFromNative(this);
                }
                return res;
            },
            configurable: true,
            enumerable: true
        });
    }

    const originalIndexDescriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'selectedIndex');
    if (originalIndexDescriptor && originalIndexDescriptor.set) {
        Object.defineProperty(HTMLSelectElement.prototype, 'selectedIndex', {
            get: function() {
                return originalIndexDescriptor.get.call(this);
            },
            set: function(val) {
                const res = originalIndexDescriptor.set.call(this, val);
                if (this.dataset && this.dataset.customSelectInitialized === 'true') {
                    syncCustomSelectFromNative(this);
                }
                return res;
            },
            configurable: true,
            enumerable: true
        });
    }

    function createChevronSvg() {
        return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>`;
    }

    function getSelectedOptionData(select) {
        const selectedOption = select.options[select.selectedIndex] || select.options[0];
        return {
            text: selectedOption ? (selectedOption.textContent || selectedOption.innerText || '') : '',
            value: selectedOption ? selectedOption.value : ''
        };
    }

    function rebuildCustomSelectOptions(select, wrapper) {
        const popoverInner = wrapper.querySelector('.custom-select-popover-inner');
        if (!popoverInner) return;
        
        popoverInner.innerHTML = '';
        const currentValue = select.value;
        
        Array.from(select.options).forEach((opt, index) => {
            const optBtn = document.createElement('button');
            optBtn.type = 'button';
            optBtn.className = 'custom-select-option' + (opt.value === currentValue || (!currentValue && index === 0) ? ' is-selected' : '');
            optBtn.dataset.value = opt.value;
            optBtn.dataset.index = index;
            optBtn.setAttribute('role', 'option');
            optBtn.setAttribute('aria-selected', opt.value === currentValue ? 'true' : 'false');
            if (opt.disabled) {
                optBtn.disabled = true;
                optBtn.style.opacity = '0.4';
                optBtn.style.cursor = 'not-allowed';
            }
            
            const textSpan = document.createElement('span');
            textSpan.className = 'custom-option-text';
            textSpan.textContent = opt.textContent || opt.innerText;
            
            const checkSpan = document.createElement('span');
            checkSpan.className = 'custom-option-check';
            checkSpan.textContent = '✓';
            
            optBtn.appendChild(textSpan);
            optBtn.appendChild(checkSpan);
            
            optBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (opt.disabled) return;
                
                selectCustomOption(select, wrapper, opt.value);
                closeCustomSelect(wrapper);
                const trigger = wrapper.querySelector('.custom-select-trigger');
                if (trigger) trigger.focus();
            });
            
            popoverInner.appendChild(optBtn);
        });

        // Update trigger label
        const selData = getSelectedOptionData(select);
        const valSpan = wrapper.querySelector('.custom-select-value');
        if (valSpan) {
            valSpan.textContent = selData.text || 'Выберите...';
        }
    }

    function selectCustomOption(select, wrapper, value) {
        if (select.value !== value) {
            select.value = value;
            // Dispatch standard events
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        syncCustomSelectFromNative(select);
    }

    function syncCustomSelectFromNative(select) {
        const wrapper = select.closest('.custom-select-wrapper');
        if (!wrapper) return;
        
        const selData = getSelectedOptionData(select);
        const valSpan = wrapper.querySelector('.custom-select-value');
        if (valSpan) {
            valSpan.textContent = selData.text || 'Выберите...';
        }
        
        const options = wrapper.querySelectorAll('.custom-select-option');
        options.forEach(optBtn => {
            const isMatch = optBtn.dataset.value === String(select.value);
            if (isMatch) {
                optBtn.classList.add('is-selected');
                optBtn.setAttribute('aria-selected', 'true');
            } else {
                optBtn.classList.remove('is-selected');
                optBtn.setAttribute('aria-selected', 'false');
            }
        });
    }

    function getAvailableDropdownSpace(wrapper) {
        const trigger = wrapper.querySelector('.custom-select-trigger') || wrapper;
        const rect = trigger.getBoundingClientRect();
        
        let container = wrapper.parentElement;
        let containerTop = 0;
        let containerBottom = window.innerHeight;
        
        while (container && container !== document.body && container !== document.documentElement) {
            const style = window.getComputedStyle(container);
            const overflow = (style.overflow || '') + (style.overflowY || '');
            if (/auto|scroll|hidden/.test(overflow) || 
                container.classList.contains('dialog-content') || 
                container.classList.contains('modal-content') || 
                container.classList.contains('manage-posts')) {
                const cRect = container.getBoundingClientRect();
                containerTop = Math.max(containerTop, cRect.top);
                containerBottom = Math.min(containerBottom, cRect.bottom);
                break;
            }
            container = container.parentElement;
        }
        
        const spaceBelow = containerBottom - rect.bottom;
        const spaceAbove = rect.top - containerTop;
        return { spaceBelow, spaceAbove };
    }

    function openCustomSelect(wrapper) {
        closeAllCustomSelects(wrapper);
        
        const popover = wrapper.querySelector('.custom-select-popover');
        const popoverInner = wrapper.querySelector('.custom-select-popover-inner');
        const trigger = wrapper.querySelector('.custom-select-trigger');
        if (!popover || !trigger) return;
        
        // Smart flip positioning calculation based on container bounds
        const { spaceBelow, spaceAbove } = getAvailableDropdownSpace(wrapper);
        const estimatedHeight = Math.min(200, popover.scrollHeight || 160);
        
        const shouldDropUp = (spaceBelow < estimatedHeight + 10 && spaceAbove > spaceBelow) || (spaceBelow < 120 && spaceAbove >= 100);
        
        if (shouldDropUp) {
            popover.classList.add('drop-up');
            if (popoverInner) {
                const maxHeight = Math.max(90, Math.min(200, spaceAbove - 16));
                popoverInner.style.maxHeight = maxHeight + 'px';
            }
        } else {
            popover.classList.remove('drop-up');
            if (popoverInner) {
                const maxHeight = Math.max(90, Math.min(200, spaceBelow - 16));
                popoverInner.style.maxHeight = maxHeight + 'px';
            }
        }
        
        wrapper.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        
        // Scroll selected option into view
        const selectedOpt = popover.querySelector('.custom-select-option.is-selected');
        if (selectedOpt) {
            selectedOpt.scrollIntoView({ block: 'nearest' });
        }
    }

    function closeCustomSelect(wrapper) {
        if (!wrapper) return;
        wrapper.classList.remove('is-open');
        const trigger = wrapper.querySelector('.custom-select-trigger');
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }
    }

    function closeAllCustomSelects(exceptWrapper) {
        document.querySelectorAll('.custom-select-wrapper.is-open').forEach(w => {
            if (w !== exceptWrapper) {
                closeCustomSelect(w);
            }
        });
    }

    function initCustomSelect(select) {
        if (!select || select.tagName !== 'SELECT') return;
        if (select.dataset && select.dataset.customSelectInitialized === 'true') return;
        if (select.hasAttribute('data-custom-select-ignore')) return;
        
        select.dataset.customSelectInitialized = 'true';
        select.classList.add('custom-select-native');
        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');
        
        // Create wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'custom-select-wrapper';
        if (select.id) wrapper.dataset.forSelect = select.id;
        
        // Detect compact / inline styling
        if (select.closest('.size-input-group') || select.classList.contains('compact-select')) {
            wrapper.classList.add('custom-select-compact');
        }
        
        // Transfer relevant classes or styling
        if (select.classList.contains('language-select')) {
            wrapper.classList.add('language-select-wrapper');
        }

        // Transfer spacing styles if present on native select
        if (select.style.marginBottom) {
            wrapper.style.marginBottom = select.style.marginBottom;
        }
        if (select.style.marginTop) {
            wrapper.style.marginTop = select.style.marginTop;
        }
        if (select.style.marginRight) {
            wrapper.style.marginRight = select.style.marginRight;
        }
        if (select.style.marginLeft) {
            wrapper.style.marginLeft = select.style.marginLeft;
        }
        
        // Insert wrapper before select and move select inside wrapper
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        
        // Create trigger button
        const selData = getSelectedOptionData(select);
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'custom-select-trigger';
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('tabindex', '0');
        if (select.id) trigger.id = select.id + '_customTrigger';
        if (select.title) trigger.title = select.title;
        
        const valSpan = document.createElement('span');
        valSpan.className = 'custom-select-value';
        valSpan.textContent = selData.text || 'Выберите...';
        
        const arrowSpan = document.createElement('span');
        arrowSpan.className = 'custom-select-arrow';
        arrowSpan.innerHTML = createChevronSvg();
        
        trigger.appendChild(valSpan);
        trigger.appendChild(arrowSpan);
        wrapper.appendChild(trigger);
        
        // Create popover menu
        const popover = document.createElement('div');
        popover.className = 'custom-select-popover';
        popover.setAttribute('role', 'listbox');
        
        const popoverInner = document.createElement('div');
        popoverInner.className = 'custom-select-popover-inner';
        popover.appendChild(popoverInner);
        wrapper.appendChild(popover);
        
        // Populate options
        rebuildCustomSelectOptions(select, wrapper);
        
        // Trigger click handler
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (select.disabled) return;
            if (wrapper.classList.contains('is-open')) {
                closeCustomSelect(wrapper);
            } else {
                openCustomSelect(wrapper);
            }
        });
        
        // Keyboard navigation
        trigger.addEventListener('keydown', function(e) {
            if (select.disabled) return;
            const isOpen = wrapper.classList.contains('is-open');
            const options = Array.from(wrapper.querySelectorAll('.custom-select-option:not([disabled])'));
            if (!options.length) return;
            
            let currentIndex = options.findIndex(opt => opt.classList.contains('is-selected'));
            if (currentIndex === -1) currentIndex = 0;
            
            if (e.key === 'ArrowDown' || e.key === 'Down') {
                e.preventDefault();
                if (!isOpen) {
                    openCustomSelect(wrapper);
                } else {
                    const nextIndex = (currentIndex + 1) % options.length;
                    selectCustomOption(select, wrapper, options[nextIndex].dataset.value);
                    options[nextIndex].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'ArrowUp' || e.key === 'Up') {
                e.preventDefault();
                if (!isOpen) {
                    openCustomSelect(wrapper);
                } else {
                    const prevIndex = (currentIndex - 1 + options.length) % options.length;
                    selectCustomOption(select, wrapper, options[prevIndex].dataset.value);
                    options[prevIndex].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (!isOpen) {
                    openCustomSelect(wrapper);
                } else {
                    closeCustomSelect(wrapper);
                }
            } else if (e.key === 'Escape' || e.key === 'Esc') {
                if (isOpen) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeCustomSelect(wrapper);
                }
            } else if (e.key === 'Tab') {
                if (isOpen) {
                    closeCustomSelect(wrapper);
                }
            }
        });
        
        // MutationObserver for select childList & attributes
        const observer = new MutationObserver(function(mutations) {
            let optionsChanged = false;
            let attrsChanged = false;
            for (const mut of mutations) {
                if (mut.type === 'childList') {
                    optionsChanged = true;
                } else if (mut.type === 'attributes') {
                    attrsChanged = true;
                }
            }
            if (optionsChanged) {
                rebuildCustomSelectOptions(select, wrapper);
            } else if (attrsChanged) {
                syncCustomSelectFromNative(select);
            }
        });
        observer.observe(select, { childList: true, attributes: true, subtree: true });
        
        // Listen to native change event
        select.addEventListener('change', function() {
            syncCustomSelectFromNative(select);
        });
    }

    function initAllCustomSelects(root) {
        const scope = root || document;
        const selects = scope.querySelectorAll('select:not([data-custom-select-initialized="true"]):not([data-custom-select-ignore])');
        selects.forEach(initCustomSelect);
    }

    // Global click listener to close open selects
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.custom-select-wrapper')) {
            closeAllCustomSelects();
        }
    });

    // Global escape key listener
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
            closeAllCustomSelects();
        }
    });

    // Observe document for dynamically added select elements
    if (typeof MutationObserver !== 'undefined') {
        const domObserver = new MutationObserver(function(mutations) {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node.nodeType === 1) {
                        if (node.tagName === 'SELECT') {
                            initCustomSelect(node);
                        } else if (node.querySelectorAll) {
                            initAllCustomSelects(node);
                        }
                    }
                }
            }
        });
        
        if (document.body) {
            domObserver.observe(document.body, { childList: true, subtree: true });
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                domObserver.observe(document.body, { childList: true, subtree: true });
            });
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initAllCustomSelects();
        });
    } else {
        initAllCustomSelects();
    }

    // Expose APIs globally
    window.initCustomSelect = initCustomSelect;
    window.initCustomSelects = initAllCustomSelects;
    window.syncCustomSelect = syncCustomSelectFromNative;
})();

