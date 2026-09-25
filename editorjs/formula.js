/**
 * ==============================================================================
 * NPBlog Editor - Модуль визуального конструктора формул (formula.js)
 * ==============================================================================
 * Реализует полноценный визуальный WYSIWYG редактор математических формул:
 * - Интерактивное визуальное полотно на базе MathLive (<math-field>).
 * - Наглядные заполнители □, навигация стрелками и Tab, ввод прямо с клавиатуры.
 * - Вставка структур (дроби, корни, интегралы, суммы, матрицы, скобки и т.д.).
 * - Вспомогательные действия (Undo, Redo, Backspace, Очистить, Обернуть).
 * - Сворачиваемый режим прямого редактирования LaTeX кода для опытных авторов.
 * - Двойной клик по формуле в статье для мгновенного визуального редактирования.
 * - Автономный статический KaTeX рендеринг для высокой скорости и независимости.
 * ==============================================================================
 */

(function () {
    let editingFormulaTarget = null;
    let previewsRendered = false;
    let mathFieldInitialized = false;

    // Настройка шрифтов MathLive при старте
    if (window.MathfieldElement) {
        window.MathfieldElement.fontsDirectory = 'assets/mathlive/fonts';
        window.MathfieldElement.soundsDirectory = null;
    }

    /**
     * Получение или ленивая инициализация элемента <math-field>
     * @returns {HTMLElement|null}
     */
    function getMathField() {
        const mf = document.getElementById('visualMathField');
        if (!mf) return null;

        if (!mathFieldInitialized) {
            mf.mathVirtualKeyboardPolicy = 'manual';
            mf.addEventListener('input', onVisualMathInput);
            
            // Быстрое сохранение формулы по Ctrl+Enter
            mf.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    insertFormulaToEditor();
                }
            });

            mathFieldInitialized = true;
        }

        return mf;
    }

    /**
     * Обработчик ввода в визуальное поле MathLive
     */
    function onVisualMathInput() {
        const mf = getMathField();
        if (!mf) return;

        const latex = mf.value;
        const codeInput = document.getElementById('formulaInput');
        if (codeInput && document.activeElement !== codeInput) {
            codeInput.value = latex;
        }

        updateSyntaxStatus(latex);
    }

    /**
     * Синхронизация из текстового поля кода в визуальное поле
     */
    window.syncCodeToVisual = function () {
        const mf = getMathField();
        const codeInput = document.getElementById('formulaInput');
        if (!mf || !codeInput) return;

        const val = codeInput.value;
        try {
            mf.setValue(val);
            updateSyntaxStatus(val);
        } catch (e) {
            console.warn('MathLive setValue error:', e);
        }
    };

    /**
     * Синхронизация из визуального поля в поле кода
     */
    window.syncVisualToCode = function () {
        const mf = getMathField();
        const codeInput = document.getElementById('formulaInput');
        if (!mf || !codeInput) return;

        const latex = mf.value;
        codeInput.value = latex;
        updateSyntaxStatus(latex);
    };

    /**
     * Обновление индикатора корректности синтаксиса
     * @param {string} latex 
     */
    function updateSyntaxStatus(latex) {
        const statusBadge = document.getElementById('formulaSyntaxStatus');
        if (!statusBadge) return;

        const trimmed = (latex || '').trim();
        if (!trimmed) {
            statusBadge.textContent = 'Ожидание ввода...';
            statusBadge.className = 'formula-syntax-badge';
            return;
        }

        if (typeof katex !== 'undefined') {
            try {
                katex.renderToString(trimmed, { throwOnError: true });
                statusBadge.textContent = 'Синтаксис корректен ✓';
                statusBadge.className = 'formula-syntax-badge is-valid';
            } catch (err) {
                // Если формула не завершена (например, открыта скобка или плейсхолдер), это нормальный процесс редактирования
                statusBadge.textContent = 'Редактирование...';
                statusBadge.className = 'formula-syntax-badge';
            }
        } else {
            statusBadge.textContent = 'Визуальный режим активен ✓';
            statusBadge.className = 'formula-syntax-badge is-valid';
        }
    }

    /**
     * Открытие модального окна формул
     * @param {HTMLElement|null} targetEl - существующий элемент формулы для редактирования
     */
    window.openFormulaDialog = function (targetEl) {
        if (window.VisualEngine && typeof window.VisualEngine.saveSelection === 'function') {
            window.VisualEngine.saveSelection();
        } else if (typeof window.saveSelection === 'function') {
            window.saveSelection();
        }

        let formulaEl = targetEl || null;

        // Если элемент не передан, проверяем каретку
        if (!formulaEl && typeof window.getSelection === 'function') {
            const sel = window.getSelection();
            if (sel && sel.rangeCount > 0) {
                const node = sel.getRangeAt(0).commonAncestorContainer;
                const parentEl = node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement;
                if (parentEl) {
                    formulaEl = parentEl.closest('.npblog-formula, .npblog-formula-block');
                }
            }
        }

        editingFormulaTarget = formulaEl || null;

        const titleEl = document.getElementById('formulaDialogTitle');
        const submitBtn = document.getElementById('formulaSubmitBtn');
        const codeInput = document.getElementById('formulaInput');
        const typeInlineRadio = document.getElementById('formulaTypeInline');
        const typeBlockRadio = document.getElementById('formulaTypeBlock');
        const sizeSelect = document.getElementById('formulaSizeSelect');
        const mf = getMathField();

        let initialFormula = '';

        if (editingFormulaTarget) {
            // Режим редактирования существующей формулы
            if (titleEl) {
                titleEl.textContent = (window.t ? window.t('modals.formula_edit_title', 'Редактировать формулу') : 'Редактировать формулу');
            }
            if (submitBtn) {
                submitBtn.innerHTML = '<span>💾</span> <span>' + (window.t ? window.t('modals.formula_save_btn', 'Сохранить изменения') : 'Сохранить изменения') + '</span>';
            }

            initialFormula = editingFormulaTarget.getAttribute('data-formula') || '';
            const existingDisplay = editingFormulaTarget.getAttribute('data-display') || (editingFormulaTarget.classList.contains('npblog-formula-block') ? 'block' : 'inline');
            const existingSize = editingFormulaTarget.getAttribute('data-size') || 'normal';

            if (existingDisplay === 'block') {
                if (typeBlockRadio) typeBlockRadio.checked = true;
            } else {
                if (typeInlineRadio) typeInlineRadio.checked = true;
            }
            if (sizeSelect) sizeSelect.value = existingSize;
        } else {
            // Режим вставки новой формулы
            if (titleEl) {
                titleEl.textContent = (window.t ? window.t('modals.formula_title', 'Визуальный конструктор формул') : 'Визуальный конструктор формул');
            }
            if (submitBtn) {
                submitBtn.innerHTML = '<span>📐</span> <span>' + (window.t ? window.t('modals.formula_insert_btn', 'Вставить формулу') : 'Вставить формулу') + '</span>';
            }

            // Проверяем, был ли выделен текст в редакторе
            let selectedText = '';
            if (typeof window.getSelection === 'function') {
                selectedText = window.getSelection().toString().trim();
            }

            if (selectedText && selectedText.length > 0 && selectedText.length < 300) {
                initialFormula = selectedText;
            } else {
                // По умолчанию красивый классический пример
                initialFormula = 'x = \\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}';
            }
        }

        // Заполняем поле MathLive и поле кода
        if (mf) {
            try {
                mf.setValue(initialFormula);
            } catch (e) {
                console.warn('Error setting initial formula to MathLive:', e);
            }
        }
        if (codeInput) {
            codeInput.value = initialFormula;
        }

        updateSyntaxStatus(initialFormula);

        // Открываем модальное окно
        if (window.Modal && typeof window.Modal.open === 'function') {
            window.Modal.open('#formulaDialog');
        } else {
            const dialog = document.getElementById('formulaDialog');
            if (dialog) {
                dialog.style.display = 'flex';
                dialog.classList.add('show');
            }
        }

        // Рендерим превью символов на вкладках палитры
        renderModalKatexPreviews();

        // Фокусируем визуальное поле ввода
        setTimeout(function () {
            if (mf) {
                mf.focus();
            }
        }, 120);
    };

    /**
     * Закрытие модального окна формул
     */
    window.closeFormulaDialog = function () {
        if (window.Modal && typeof window.Modal.close === 'function') {
            window.Modal.close('#formulaDialog');
        } else {
            const dialog = document.getElementById('formulaDialog');
            if (dialog) {
                dialog.style.display = 'none';
                dialog.classList.remove('show');
            }
        }
        editingFormulaTarget = null;
    };

    /**
     * Переключение вкладок категорий на ленте (Ribbon Tabs)
     * @param {string} categoryKey 
     */
    window.switchFormulaTab = function (categoryKey) {
        const tabBtns = document.querySelectorAll('.formula-ribbon-tab-btn');
        tabBtns.forEach(btn => {
            if (btn.getAttribute('data-tab') === categoryKey) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        const panels = document.querySelectorAll('.formula-palette-panel');
        panels.forEach(panel => {
            if (panel.getAttribute('data-category') === categoryKey) {
                panel.classList.add('active');
            } else {
                panel.classList.remove('active');
            }
        });
    };

    /**
     * Вставка визуального фрагмента со слотами-заполнителями в поле MathLive
     * @param {string} snippet - шаблон MathLive с плейсхолдерами #@ и #?
     */
    window.insertVisualSnippet = function (snippet) {
        const mf = getMathField();
        if (!mf) return;

        mf.focus();
        try {
            // selectionMode: 'placeholder' выделяет первый плейсхолдер для немедленного набора
            mf.insert(snippet, { selectionMode: 'placeholder' });
        } catch (e) {
            console.warn('insertVisualSnippet error:', e);
            mf.insert(snippet);
        }

        syncVisualToCode();
    };

    /**
     * Быстрая вставка математического символа
     * @param {string} sym 
     */
    window.insertQuickSymbol = function (sym) {
        const mf = getMathField();
        if (!mf) return;

        mf.focus();
        try {
            mf.insert(sym);
        } catch (e) {
            console.warn('insertQuickSymbol error:', e);
        }

        syncVisualToCode();
    };

    /**
     * Визуальные действия: Отменить (Undo)
     */
    window.formulaActionUndo = function () {
        const mf = getMathField();
        if (mf) {
            mf.executeCommand('undo');
            mf.focus();
            syncVisualToCode();
        }
    };

    /**
     * Визуальные действия: Повторить (Redo)
     */
    window.formulaActionRedo = function () {
        const mf = getMathField();
        if (mf) {
            mf.executeCommand('redo');
            mf.focus();
            syncVisualToCode();
        }
    };

    /**
     * Визуальные действия: Стереть влево (Backspace)
     */
    window.formulaActionDelete = function () {
        const mf = getMathField();
        if (mf) {
            mf.executeCommand('deleteBackward');
            mf.focus();
            syncVisualToCode();
        }
    };

    /**
     * Визуальные действия: Очистить всё полотно
     */
    window.formulaActionClear = function () {
        const mf = getMathField();
        if (mf) {
            mf.setValue('');
            mf.focus();
            syncVisualToCode();
        }
    };

    /**
     * Переключение виртуальной клавиатуры MathLive
     */
    window.formulaActionToggleKeyboard = function () {
        const mf = getMathField();
        if (mf) {
            try {
                mf.executeCommand('toggleMathVirtualKeyboard');
                mf.focus();
            } catch (e) {
                console.warn('toggleMathVirtualKeyboard error:', e);
            }
        }
    };

    /**
     * Переключение отображения сворачиваемого блока LaTeX кода
     */
    window.toggleFormulaCodeSection = function () {
        const sec = document.getElementById('formulaCodeCollapsible');
        const btn = document.getElementById('formulaToggleCodeBtn');
        if (!sec) return;

        const isHidden = (sec.style.display === 'none' || !sec.style.display);
        if (isHidden) {
            sec.style.display = 'block';
            if (btn) btn.classList.add('active');
            const codeInput = document.getElementById('formulaInput');
            if (codeInput) {
                codeInput.focus();
            }
        } else {
            sec.style.display = 'none';
            if (btn) btn.classList.remove('active');
            const mf = getMathField();
            if (mf) {
                mf.focus();
            }
        }
    };

    /**
     * Рендеринг статических превью для кнопок палитры (KaTeX)
     */
    function renderModalKatexPreviews() {
        if (previewsRendered || typeof katex === 'undefined') return;

        const rawItems = document.querySelectorAll('#formulaDialog .katex-raw-render');
        rawItems.forEach(el => {
            const expr = el.getAttribute('data-expr');
            if (expr) {
                try {
                    const rendered = katex.renderToString(expr, {
                        displayMode: false,
                        throwOnError: false
                    });
                    el.innerHTML = rendered;
                    el.classList.remove('katex-raw-render');
                } catch (e) {
                    // оставляем как есть
                }
            }
        });

        previewsRendered = true;
    }

    /**
     * Вставка готовой формулы в редактируемую статью
     */
    window.insertFormulaToEditor = function () {
        const mf = getMathField();
        const codeInput = document.getElementById('formulaInput');
        const typeBlockRadio = document.getElementById('formulaTypeBlock');
        const sizeSelect = document.getElementById('formulaSizeSelect');

        // Получаем TeX формулу из MathLive или текстового поля
        let rawTex = '';
        if (mf && typeof mf.value === 'string') {
            rawTex = mf.value.trim();
        }
        if (!rawTex && codeInput) {
            rawTex = codeInput.value.trim();
        }

        if (!rawTex) {
            if (typeof showNotification === 'function') {
                showNotification(window.t ? window.t('notifications.formula_empty', 'Пожалуйста, составьте формулу') : 'Пожалуйста, составьте формулу', 'warning');
            }
            if (mf) mf.focus();
            return;
        }

        const isBlock = typeBlockRadio ? typeBlockRadio.checked : false;
        const size = sizeSelect ? sizeSelect.value : 'normal';

        // Рендерим чистовой KaTeX HTML
        let renderedKatex = rawTex;
        if (typeof katex !== 'undefined') {
            try {
                renderedKatex = katex.renderToString(rawTex, {
                    displayMode: isBlock,
                    throwOnError: false
                });
            } catch (e) {
                console.warn('KaTeX render error on save:', e);
            }
        }

        const escapedAttrTex = rawTex
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        let formulaHtml = '';
        if (isBlock) {
            formulaHtml = `<div class="npblog-formula-block" data-formula="${escapedAttrTex}" data-display="block" data-size="${size}" contenteditable="false" title="Формула (Двойной клик для редактирования)">${renderedKatex}</div>`;
        } else {
            formulaHtml = `<span class="npblog-formula" data-formula="${escapedAttrTex}" data-display="inline" data-size="${size}" contenteditable="false" title="Формула (Двойной клик для редактирования)">${renderedKatex}</span>`;
        }

        if (editingFormulaTarget) {
            // Редактирование существующей формулы
            const temp = document.createElement('div');
            temp.innerHTML = formulaHtml;
            const newEl = temp.firstElementChild;

            if (newEl && editingFormulaTarget.parentNode) {
                editingFormulaTarget.parentNode.replaceChild(newEl, editingFormulaTarget);
            } else {
                editingFormulaTarget.outerHTML = formulaHtml;
            }

            editingFormulaTarget = null;
            if (typeof showNotification === 'function') {
                showNotification(window.t ? window.t('notifications.formula_updated', 'Формула успешно обновлена!') : 'Формула успешно обновлена!', 'success');
            }
        } else {
            // Вставка новой формулы
            if (typeof editorMode !== 'undefined' && editorMode === 'code') {
                const ta = document.getElementById('content');
                if (ta) {
                    const start = ta.selectionStart;
                    const val = ta.value;
                    const insertStr = isBlock ? '\n' + formulaHtml + '\n' : formulaHtml;
                    ta.value = val.substring(0, start) + insertStr + val.substring(start);
                }
            } else {
                if (window.VisualEngine && typeof window.VisualEngine.restoreFocus === 'function') {
                    window.VisualEngine.restoreFocus();
                } else if (typeof window.restoreEditorFocus === 'function') {
                    window.restoreEditorFocus();
                }

                if (isBlock) {
                    if (typeof window.insertBlockMedia === 'function') {
                        window.insertBlockMedia(formulaHtml);
                    } else if (typeof window.insertHtmlAtCaret === 'function') {
                        window.insertHtmlAtCaret(formulaHtml);
                    } else {
                        const ve = document.getElementById('contentVisual');
                        if (ve) ve.insertAdjacentHTML('beforeend', formulaHtml);
                    }
                } else {
                    if (typeof window.insertHtmlAtCaret === 'function') {
                        window.insertHtmlAtCaret(formulaHtml);
                    } else {
                        const ve = document.getElementById('contentVisual');
                        if (ve) ve.insertAdjacentHTML('beforeend', formulaHtml);
                    }
                }
            }

            if (typeof showNotification === 'function') {
                showNotification(window.t ? window.t('notifications.formula_inserted', 'Формула успешно вставлена в статью!') : 'Формула успешно вставлена в статью!', 'success');
            }
        }

        if (typeof saveToHistory === 'function') {
            saveToHistory();
        }

        closeFormulaDialog();
    };

    /**
     * Принудительное полное обновление и ре-рендеринг всех формул KaTeX в редакторе
     * @param {HTMLElement} [root]
     */
    window.refreshAllFormulasInEditor = function (root) {
        if (typeof katex === 'undefined') return;
        const container = root || document.getElementById('contentVisual');
        if (!container) return;
        container.querySelectorAll('.npblog-formula, .npblog-formula-block').forEach(formulaEl => {
            formulaEl.setAttribute('contenteditable', 'false');
            const formula = formulaEl.getAttribute('data-formula');
            if (formula) {
                const isBlock = formulaEl.getAttribute('data-display') === 'block' || formulaEl.classList.contains('npblog-formula-block');
                try {
                    katex.render(formula, formulaEl, {
                        displayMode: isBlock,
                        throwOnError: false
                    });
                } catch (e) {
                    console.warn('KaTeX refresh error:', e);
                }
            }
        });
    };

    /**
     * Инициализация обработчиков в редакторе
     */
    document.addEventListener('DOMContentLoaded', function () {
        const ve = document.getElementById('contentVisual');
        if (ve) {
            // Двойной клик по формуле для открытия визуального редактирования
            ve.addEventListener('dblclick', function (e) {
                const formulaEl = e.target.closest('.npblog-formula, .npblog-formula-block');
                if (formulaEl) {
                    e.preventDefault();
                    e.stopPropagation();
                    openFormulaDialog(formulaEl);
                }
            });

            // Инициализация существующих формул
            setTimeout(function () {
                window.refreshAllFormulasInEditor(ve);
            }, 50);
        }
    });
})();
