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
    } catch (e) { }

    if (rect && rect.height > 0 && rect.left > 0) {
        return rect;
    }

    try {
        const rects = range.getClientRects();
        if (rects && rects.length > 0 && rects[0].height > 0) {
            return rects[0];
        }
    } catch (e) { }

    let node = range.startContainer;
    let offset = range.startOffset;

    if (!node) return null;

    if (node.nodeType === Node.ELEMENT_NODE) {
        if (node.childNodes.length > 0 && offset < node.childNodes.length) {
            let child = node.childNodes[offset];
            if (child && child.nodeType === Node.ELEMENT_NODE) {
                try {
                    return child.getBoundingClientRect();
                } catch (e) { }
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
        } catch (e) { }
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
            } catch (e) { }
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
