let linkInsertStart = 0;
let linkInsertEnd = 0;
let colorInsertStart = 0;
let colorInsertEnd = 0;

function saveSelection() {
    const ve = document.getElementById('contentVisual');
    if (!ve) return;
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return;
    const range = sel.getRangeAt(0);
    if (ve.contains(range.commonAncestorContainer) || range.commonAncestorContainer === ve) {
        savedRange = range.cloneRange();
    }
}

// Стабильная логика тулбара: не даём кнопкам забирать фокус у редактора.
// Это сохраняет каретку/выделение и делает execCommand предсказуемым.
(function initToolbarFocusGuard() {
    var bar = document.getElementById('formatBarRow');
    if (!bar) return;
    bar.addEventListener('mousedown', function (e) {
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
    ['mouseup', 'keyup', 'input', 'click', 'touchend', 'compositionend'].forEach(function (evt) {
        ve.addEventListener(evt, function () {
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
        while ((node = el.firstChild)) {
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
    let formatted = html.replace(/<pre[^>]*>[\s\S]*?<\/pre>/gi, function (match) {
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
    elementsToRemove.forEach(function (el) {
        el.parentNode.removeChild(el);
    });

    // Удаляем атрибуты data-image-id, data-media-id, data-media-type
    var wraps = temp.querySelectorAll('[data-image-id], [data-media-id], [data-media-type]');
    wraps.forEach(function (el) {
        el.removeAttribute('data-image-id');
        el.removeAttribute('data-media-id');
        el.removeAttribute('data-media-type');
    });

    // Очистка таблиц: удаляем атрибуты редактирования и состояния ресайзера
    var tables = temp.querySelectorAll('table[data-resizers-added]');
    tables.forEach(function (table) {
        table.removeAttribute('data-resizers-added');
    });

    var editableCells = temp.querySelectorAll('[contenteditable]');
    editableCells.forEach(function (el) {
        el.removeAttribute('contenteditable');
    });

    // Удаляем классы selected
    var selected = temp.querySelectorAll('.selected');
    selected.forEach(function (el) {
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
    if (typeof editorMode !== 'undefined' && editorMode !== 'visual') return;
    const ve = document.getElementById('contentVisual');
    const sel = window.getSelection();
    // Не подсвечиваем кнопки, если выделение/каретка не в поле статьи
    if (!ve || !sel || sel.rangeCount === 0) {
        ['btn-bold', 'btn-italic', 'btn-underline', 'btn-strike', 'btn-sup', 'btn-sub', 'btn-h2'].forEach(function (id) { setBtnActive(id, false); });
        return;
    }
    try {
        const r = sel.getRangeAt(0);
        if (!r || !r.commonAncestorContainer || !(r.commonAncestorContainer instanceof Node) || !ve.contains(r.commonAncestorContainer)) {
            ['btn-bold', 'btn-italic', 'btn-underline', 'btn-strike', 'btn-sup', 'btn-sub', 'btn-h2'].forEach(function (id) { setBtnActive(id, false); });
            return;
        }

        const node = r.startContainer;
        if (!node || !(node instanceof Node)) return;

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
    } catch (e) {
        // Безопасный перехват при отсоединенном или невалидном диапазоне выделения
    }
}

// Теги форматирования, которые нужно «покидать» при выключении режима
var FORMAT_TAGS = {
    bold: ['B', 'STRONG'],
    italic: ['I', 'EM'],
    underline: ['U'],
    strikeThrough: ['S', 'STRIKE', 'DEL'],
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
            } catch (e) {
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
            } catch (e) {
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
                } catch (e) {
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
document.getElementById('contentVisual').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey && !e.defaultPrevented) {
        const sel = window.getSelection();
        if (!sel || !sel.rangeCount) return;
        const node = sel.anchorNode;

        // Если мы внутри списка или преформатированного текста, пусть браузер обрабатывает сам
        let inListOrPre = false;
        let curr = node;
        while (curr && curr.id !== 'contentVisual') {
            if (curr.tagName === 'LI' || curr.tagName === 'PRE') {
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
            } catch (err) {
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
