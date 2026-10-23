/**
 * NPBlog Visual Editing Engine (WYSIWYG 3.0)
 * Modern, deterministic, robust visual editing engine.
 *
 * Key Pillars:
 * 1. Strict Block-and-Inline DOM Model (no orphan text nodes, no collapsed empty divs).
 * 2. Pure DOM Range Splitting & Formatting (zero reliance on deprecated execCommand).
 * 3. Deterministic Key Handling (Enter, Backspace, Delete, Tab, Shift+Enter, Undo/Redo).
 * 4. Rock-solid Focus & Selection Preservation across modals and popovers.
 * 5. Clean, Idempotent HTML Serialization & Pretty Indentation.
 * 6. 100% Backward Compatibility with all NPBlog modules and global APIs.
 */

(function (window, document) {
    'use strict';

    // Global Selection & Input State
    let savedRange = null;
    let linkInsertStart = 0;
    let linkInsertEnd = 0;
    let colorInsertStart = 0;
    let colorInsertEnd = 0;

    window.savedRange = savedRange;
    window.linkInsertStart = linkInsertStart;
    window.linkInsertEnd = linkInsertEnd;
    window.colorInsertStart = colorInsertStart;
    window.colorInsertEnd = colorInsertEnd;

    // Constants
    const BLOCK_TAGS = new Set([
        'P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
        'BLOCKQUOTE', 'PRE', 'UL', 'OL', 'LI', 'DIV',
        'TABLE', 'THEAD', 'TBODY', 'TFOOT', 'TR', 'TH', 'TD',
        'DETAILS', 'SUMMARY', 'SECTION', 'ARTICLE', 'HEADER', 'FOOTER', 'HR'
    ]);

    const EMBED_TAGS = new Set([
        'IMG', 'VIDEO', 'AUDIO', 'IFRAME', 'TABLE', 'HR',
        'OBJECT', 'EMBED', 'CANVAS', 'SVG'
    ]);

    const INLINE_FORMAT_MAP = {
        'b': 'STRONG',
        'strong': 'STRONG',
        'i': 'EM',
        'em': 'EM',
        'u': 'U',
        's': 'S',
        'strike': 'S',
        'del': 'S',
        'sup': 'SUP',
        'sub': 'SUB',
        'code': 'CODE',
        'mark': 'MARK'
    };

    /**
     * =========================================================================
     * DOM UTILITIES & INSPECTION
     * =========================================================================
     */
    const DOM = {
        getEditor() {
            return document.getElementById('contentVisual');
        },

        getCodeEditor() {
            return document.getElementById('content');
        },

        isInside(node, parent) {
            if (!node || !parent) return false;
            if (node === parent) return true;
            try {
                return parent.contains(node);
            } catch (e) {
                return false;
            }
        },

        isBlock(node) {
            if (!node || node.nodeType !== Node.ELEMENT_NODE) return false;
            const tag = node.tagName.toUpperCase();
            if (BLOCK_TAGS.has(tag)) return true;
            if (node.classList && (
                node.classList.contains('blog-image-align-wrap') ||
                node.classList.contains('npblog-formula-block') ||
                node.classList.contains('code-block') ||
                node.classList.contains('spoiler-block')
            )) return true;
            return false;
        },

        getClosestBlock(node, editor) {
            const ed = editor || DOM.getEditor();
            if (!node || !ed) return null;
            let curr = (node.nodeType === Node.TEXT_NODE) ? node.parentNode : node;
            while (curr && curr !== ed) {
                if (DOM.isBlock(curr)) return curr;
                curr = curr.parentNode;
            }
            return null;
        },

        isEmpty(target) {
            if (!target) return true;

            let html = '';
            let text = '';
            let hasEmbed = false;

            if (typeof target === 'string') {
                html = target.trim();
                if (!html || html === '<br>' || html === '<br/>' ||
                    html === '<p><br></p>' || html === '<p><br/></p>' ||
                    html === '<div><br></div>' || html === '<p></p>' || html === '<div></div>') {
                    return true;
                }
                if (/<(img|video|audio|iframe|table|hr|object|embed|canvas|svg)\b/i.test(html)) {
                    return false;
                }
                text = html
                    .replace(/<[^>]*>/g, '')
                    .replace(/&(nbsp|#160|#xa0|#8203|#x200b|#65279|#xfeff|zwnj|zwj);/gi, ' ')
                    .replace(/[\s\u00A0\u200B\u200C\u200D\uFEFF]/g, '');
                return text.length === 0;
            }

            if (target instanceof HTMLElement || target instanceof DocumentFragment || (target && target.nodeType === Node.ELEMENT_NODE)) {
                if (target.querySelector && target.querySelector('img, video, audio, iframe, table, hr, svg, canvas, [data-formula]')) {
                    return false;
                }
                text = (target.textContent || '')
                    .replace(/&(nbsp|#160|#xa0|#8203|#x200b|#65279|#xfeff|zwnj|zwj);/gi, ' ')
                    .replace(/[\s\u00A0\u200B\u200C\u200D\uFEFF]/g, '');
                return text.length === 0;
            }

            if (target && target.nodeType === Node.TEXT_NODE) {
                text = (target.textContent || '').replace(/[\s\u00A0\u200B\u200C\u200D\uFEFF]/g, '');
                return text.length === 0;
            }

            return true;
        },

        normalizeBlock(block) {
            if (!block) return;
            if (DOM.isEmpty(block) && !block.querySelector('img, video, audio, iframe, hr, table, svg, canvas, [data-formula]')) {
                block.innerHTML = '<br>';
            }
        },

        normalizeDOM(root) {
            const editor = root || DOM.getEditor();
            if (!editor) return;

            // 1. Editor completely empty -> guarantee standard <p><br></p>
            if (!editor.hasChildNodes() || (editor.children.length <= 1 && DOM.isEmpty(editor))) {
                if (editor.innerHTML !== '<p><br></p>') {
                    editor.innerHTML = '<p><br></p>';
                }
                return;
            }

            // 2. Wrap stray orphan inline elements or raw text nodes into <p>
            const children = Array.from(editor.childNodes);
            let inlineBuffer = [];

            const flushBuffer = () => {
                if (inlineBuffer.length === 0) return;
                const p = document.createElement('p');
                editor.insertBefore(p, inlineBuffer[0]);
                inlineBuffer.forEach(node => p.appendChild(node));
                if (p.innerHTML.trim() === '') {
                    p.innerHTML = '<br>';
                }
                inlineBuffer = [];
            };

            children.forEach(node => {
                if (node.nodeType === Node.TEXT_NODE) {
                    if (node.textContent.trim() !== '') {
                        inlineBuffer.push(node);
                    } else if (inlineBuffer.length > 0) {
                        inlineBuffer.push(node);
                    } else {
                        editor.removeChild(node);
                    }
                } else if (node.nodeType === Node.ELEMENT_NODE) {
                    const tag = node.tagName.toUpperCase();
                    if (tag === 'BR') {
                        inlineBuffer.push(node);
                        flushBuffer();
                    } else if (!BLOCK_TAGS.has(tag) && !node.classList.contains('blog-image-align-wrap')) {
                        inlineBuffer.push(node);
                    } else {
                        flushBuffer();
                    }
                }
            });
            flushBuffer();

            // 3. Ensure empty blocks have a visible <br>
            editor.querySelectorAll('p, h1, h2, h3, h4, h5, h6, blockquote, li').forEach(block => {
                if (block.innerHTML.trim() === '' && !block.querySelector('img, video, audio, iframe')) {
                    block.innerHTML = '<br>';
                }
            });

            // 4. Clean stray empty inline elements without touching caret
            DOM.cleanEmptyInline(editor, true);

            // 5. Formula widgets integrity
            if (typeof window.refreshAllFormulasInEditor === 'function') {
                window.refreshAllFormulasInEditor(editor);
            }
        },

        cleanEmptyInline(root, skipActive = false) {
            const container = root || DOM.getEditor();
            if (!container) return;

            const sel = window.getSelection();
            const activeNode = (skipActive && sel && sel.rangeCount > 0) ? sel.getRangeAt(0).startContainer : null;

            // Strip redundant zero-width spaces
            const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT, null, false);
            let textNode;
            const toRemove = [];
            while ((textNode = walker.nextNode())) {
                if (activeNode && textNode === activeNode) continue;
                if (textNode.parentElement && textNode.parentElement.closest('.npblog-formula, .npblog-formula-block, .katex, math')) continue;
                const val = textNode.nodeValue;
                if (/[\u200B\u200C\u200D\uFEFF]/.test(val)) {
                    const cleaned = val.replace(/[\u200B\u200C\u200D\uFEFF]/g, '');
                    if (cleaned.length === 0) {
                        toRemove.push(textNode);
                    } else {
                        textNode.nodeValue = cleaned;
                    }
                }
            }
            toRemove.forEach(n => n.parentNode && n.parentNode.removeChild(n));

            // Unwrap useless spans without any attributes
            container.querySelectorAll('span').forEach(span => {
                if (span.id === 'customCaret' || span.dataset.npblogAnchor) return;
                if (span.closest('.npblog-formula, .npblog-formula-block, .katex, math')) return;
                const hasStyle = span.getAttribute('style') && span.getAttribute('style').trim().length > 0;
                const hasClass = span.getAttribute('class') && span.getAttribute('class').trim().length > 0;
                const hasId = span.getAttribute('id') && span.getAttribute('id').trim().length > 0;
                if (!hasStyle && !hasClass && !hasId && span.attributes.length === 0) {
                    const parent = span.parentNode;
                    if (parent) {
                        while (span.firstChild) parent.insertBefore(span.firstChild, span);
                        parent.removeChild(span);
                    }
                }
            });

            // Recursively remove empty inline tags
            const inlineSel = 'span, strong, b, em, i, u, s, strike, del, sup, sub, code, mark, a:not([href])';
            let changed = true;
            let limit = 0;
            while (changed && limit < 5) {
                changed = false;
                limit++;
                container.querySelectorAll(inlineSel).forEach(el => {
                    if (activeNode && el.contains(activeNode)) return;
                    if (el.closest('.npblog-formula, .npblog-formula-block, .katex, math')) return;
                    if (el.id === 'customCaret' || el.dataset.npblogAnchor || el.classList.contains('npblog-formula') || el.classList.contains('npblog-formula-block') || el.hasAttribute('data-formula') || el.classList.contains('katex')) return;
                    if (el.querySelector('img, video, audio, iframe, table, svg, canvas, [data-npblog-anchor], [data-formula]')) return;
                    const text = el.textContent.replace(/[\s\u00A0\u200B\uFEFF]/g, '');
                    if (text.length === 0 && el.parentNode) {
                        el.parentNode.removeChild(el);
                        changed = true;
                    }
                });
            }
        }
    };

    /**
     * =========================================================================
     * SELECTION & FOCUS MANAGER
     * =========================================================================
     */
    const SelectionManager = {
        save(customRange) {
            const editor = DOM.getEditor();
            if (!editor) return;

            if (customRange && customRange.commonAncestorContainer && DOM.isInside(customRange.commonAncestorContainer, editor)) {
                savedRange = customRange.cloneRange();
                window.savedRange = savedRange;
                if (typeof window.setGlobalSavedRange === 'function') window.setGlobalSavedRange(savedRange);
                return;
            }

            if (document.activeElement && document.activeElement !== editor && !DOM.isInside(document.activeElement, editor)) {
                return;
            }

            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;

            const range = sel.getRangeAt(0);
            if (range && range.commonAncestorContainer && DOM.isInside(range.commonAncestorContainer, editor)) {
                savedRange = range.cloneRange();
                window.savedRange = savedRange;
                if (typeof window.setGlobalSavedRange === 'function') window.setGlobalSavedRange(savedRange);
            }
        },

        restore(range) {
            const editor = DOM.getEditor();
            if (!editor) return false;

            const targetRange = range || window.savedRange || savedRange || (typeof window.getSavedRange === 'function' ? window.getSavedRange() : null);
            const sel = window.getSelection();

            if (targetRange && targetRange.commonAncestorContainer && DOM.isInside(targetRange.commonAncestorContainer, editor)) {
                try {
                    editor.focus({ preventScroll: true });
                    if (sel) {
                        sel.removeAllRanges();
                        sel.addRange(targetRange);
                    }
                    savedRange = targetRange;
                    window.savedRange = targetRange;
                    if (typeof window.setGlobalSavedRange === 'function') window.setGlobalSavedRange(targetRange);
                    return true;
                } catch (e) { }
            }

            editor.focus({ preventScroll: true });
            this.setCursorToEnd(editor);
            this.save();
            return true;
        },

        setCursorToStart(el) {
            if (!el) return;
            const range = document.createRange();
            const sel = window.getSelection();
            if (!sel) return;

            if (el.firstChild && el.firstChild.nodeType === Node.TEXT_NODE) {
                range.setStart(el.firstChild, 0);
            } else if (el.firstChild && el.firstChild.nodeName === 'BR') {
                range.setStartBefore(el.firstChild);
            } else if (el.firstChild) {
                range.setStart(el, 0);
            } else {
                range.selectNodeContents(el);
            }
            range.collapse(true);
            sel.removeAllRanges();
            sel.addRange(range);
            this.save(range);
        },

        setCursorToEnd(el) {
            if (!el) return;
            const range = document.createRange();
            const sel = window.getSelection();
            if (!sel) return;

            range.selectNodeContents(el);
            range.collapse(false);
            sel.removeAllRanges();
            sel.addRange(range);
            this.save(range);
        },

        isCaretAtStart(block, range) {
            if (!block || !range) return false;
            try {
                const testRange = document.createRange();
                testRange.setStart(block, 0);
                testRange.setEnd(range.startContainer, range.startOffset);
                const frag = testRange.cloneContents();
                if (frag.querySelector('img, video, audio, iframe, hr, table, svg, canvas')) return false;
                return testRange.toString().replace(/[\s\u00A0\u200B\u200C\u200D\uFEFF]/g, '').length === 0;
            } catch (e) {
                return false;
            }
        },

        isCaretAtEnd(block, range) {
            if (!block || !range) return false;
            try {
                const testRange = document.createRange();
                testRange.setStart(range.endContainer, range.endOffset);
                testRange.setEndAfter(block.lastChild || block);
                const frag = testRange.cloneContents();
                if (frag.querySelector('img, video, audio, iframe, hr, table, svg, canvas')) return false;
                return testRange.toString().replace(/[\s\u00A0\u200B\u200C\u200D\uFEFF]/g, '').length === 0;
            } catch (e) {
                return false;
            }
        },

        getOffsets(container) {
            if (!container) return { start: 0, end: 0 };
            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) return { start: 0, end: 0 };
            const range = sel.getRangeAt(0);

            if (!container.contains(range.commonAncestorContainer)) {
                return { start: 0, end: 0 };
            }

            let start = 0;
            let end = 0;
            const iterator = document.createNodeIterator(container, NodeFilter.SHOW_TEXT);
            let currentNode;

            while ((currentNode = iterator.nextNode())) {
                if (currentNode === range.startContainer) {
                    start += range.startOffset;
                } else if (currentNode.compareDocumentPosition(range.startContainer) & Node.DOCUMENT_POSITION_FOLLOWING) {
                    start += currentNode.length;
                }

                if (currentNode === range.endContainer) {
                    end += range.endOffset;
                } else if (currentNode.compareDocumentPosition(range.endContainer) & Node.DOCUMENT_POSITION_FOLLOWING) {
                    end += currentNode.length;
                }
            }

            return { start, end };
        },

        setOffsets(container, start, end) {
            if (!container) return;
            const sel = window.getSelection();
            if (!sel) return;
            sel.removeAllRanges();

            const range = document.createRange();
            let charIndex = 0;
            let startNode = null, startOffset = 0;
            let endNode = null, endOffset = 0;

            const iterator = document.createNodeIterator(container, NodeFilter.SHOW_TEXT);
            let currentNode;

            while ((currentNode = iterator.nextNode())) {
                const len = currentNode.length;
                if (!startNode && charIndex + len >= start) {
                    startNode = currentNode;
                    startOffset = start - charIndex;
                }
                if (!endNode && charIndex + len >= end) {
                    endNode = currentNode;
                    endOffset = end - charIndex;
                }
                charIndex += len;
            }

            if (!startNode) { startNode = container; startOffset = 0; }
            if (!endNode) { endNode = container; endOffset = 0; }

            try {
                range.setStart(startNode, startOffset);
                range.setEnd(endNode, endOffset);
                sel.addRange(range);
            } catch (e) { }
        }
    };

    /**
     * =========================================================================
     * INLINE FORMATTING ENGINE
     * =========================================================================
     */
    const InlineFormatter = {
        findAncestor(node, tagName, editor) {
            const ed = editor || DOM.getEditor();
            let curr = (node.nodeType === Node.TEXT_NODE) ? node.parentNode : node;
            const target = tagName.toUpperCase();
            while (curr && curr !== ed) {
                if (curr.nodeType === Node.ELEMENT_NODE && curr.tagName.toUpperCase() === target) {
                    return curr;
                }
                curr = curr.parentNode;
            }
            return null;
        },

        findAncestorWithStyle(node, styleProp, editor) {
            const ed = editor || DOM.getEditor();
            let curr = (node.nodeType === Node.TEXT_NODE) ? node.parentNode : node;
            while (curr && curr !== ed && !DOM.isBlock(curr)) {
                if (curr.nodeType === Node.ELEMENT_NODE && curr.tagName === 'SPAN' && curr.style && curr.style[styleProp]) {
                    return curr;
                }
                curr = curr.parentNode;
            }
            return null;
        },

        isFormatActive(range, tagName) {
            const ed = DOM.getEditor();
            const target = tagName.toUpperCase();

            if (range.collapsed) {
                return !!this.findAncestor(range.startContainer, target, ed);
            }

            const ancestor = this.findAncestor(range.commonAncestorContainer, target, ed);
            if (ancestor) return true;

            const startAncestor = this.findAncestor(range.startContainer, target, ed);
            const endAncestor = this.findAncestor(range.endContainer, target, ed);
            return !!(startAncestor && endAncestor);
        },

        formatInline(formatType) {
            if (!SelectionManager.restore()) return;
            const targetTag = INLINE_FORMAT_MAP[formatType.toLowerCase()] || formatType.toUpperCase();
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;

            const range = sel.getRangeAt(0);

            // Caret collapsed: toggle state for subsequent typing
            if (range.collapsed) {
                const existing = this.findAncestor(range.startContainer, targetTag);
                if (existing) {
                    // Turn off format: break out of tag
                    const textNode = document.createTextNode('\u200B');
                    if (existing.nextSibling) {
                        existing.parentNode.insertBefore(textNode, existing.nextSibling);
                    } else {
                        existing.parentNode.appendChild(textNode);
                    }
                    const newRange = document.createRange();
                    newRange.setStart(textNode, 1);
                    newRange.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(newRange);
                    SelectionManager.save(newRange);
                } else {
                    // Turn on format: insert tag with zero-width placeholder
                    const el = document.createElement(targetTag);
                    el.appendChild(document.createTextNode('\u200B'));
                    range.insertNode(el);
                    SelectionManager.setCursorToStart(el);
                }
                VisualEngine.updateActiveButtons();
                if (typeof saveToHistory === 'function') saveToHistory();
                return;
            }

            // Selection active
            const isActive = this.isFormatActive(range, targetTag);

            if (isActive) {
                // Unwrap / Remove format from selection
                this.removeFormat(range, targetTag);
            } else {
                // Apply format to selection
                try {
                    const contents = range.extractContents();
                    // Clean internal duplicate tags
                    contents.querySelectorAll(targetTag).forEach(nested => {
                        const parent = nested.parentNode;
                        if (parent) {
                            while (nested.firstChild) parent.insertBefore(nested.firstChild, nested);
                            parent.removeChild(nested);
                        }
                    });

                    const wrapper = document.createElement(targetTag);
                    wrapper.appendChild(contents);
                    range.insertNode(wrapper);

                    const newRange = document.createRange();
                    newRange.selectNodeContents(wrapper);
                    sel.removeAllRanges();
                    sel.addRange(newRange);
                } catch (e) {
                    console.warn('Inline format error:', e);
                }
            }

            SelectionManager.save();
            VisualEngine.updateActiveButtons();
            if (typeof saveToHistory === 'function') saveToHistory();
        },

        removeFormat(range, targetTag) {
            const ed = DOM.getEditor();
            const target = targetTag.toUpperCase();
            const startAncestor = this.findAncestor(range.startContainer, target, ed);

            if (startAncestor) {
                const parent = startAncestor.parentNode;
                while (startAncestor.firstChild) {
                    parent.insertBefore(startAncestor.firstChild, startAncestor);
                }
                parent.removeChild(startAncestor);
            }

            ed.querySelectorAll(target).forEach(el => {
                if (range.intersectsNode(el)) {
                    const parent = el.parentNode;
                    while (el.firstChild) {
                        parent.insertBefore(el.firstChild, el);
                    }
                    parent.removeChild(el);
                }
            });
        },

        applyInlineStyle(styleProp, value) {
            if (!SelectionManager.restore()) return;
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;

            const range = sel.getRangeAt(0);

            const cleanStyles = (root) => {
                if (!root) return;
                root.querySelectorAll('span').forEach(el => {
                    if (el.style && el.style[styleProp]) {
                        el.style[styleProp] = '';
                        const rem = el.getAttribute('style');
                        if (!rem || rem.trim() === '' || rem.trim() === ';') el.removeAttribute('style');
                    }
                    if (el.attributes.length === 0) {
                        const p = el.parentNode;
                        if (p) {
                            while (el.firstChild) p.insertBefore(el.firstChild, el);
                            p.removeChild(el);
                        }
                    }
                });
            };

            if (range.collapsed) {
                const span = document.createElement('span');
                span.style[styleProp] = value;
                span.appendChild(document.createTextNode('\u200B'));
                range.insertNode(span);
                SelectionManager.setCursorToStart(span);
            } else {
                try {
                    const contents = range.extractContents();
                    cleanStyles(contents);

                    const span = document.createElement('span');
                    span.style[styleProp] = value;
                    span.appendChild(contents);
                    range.insertNode(span);

                    const newRange = document.createRange();
                    newRange.selectNodeContents(span);
                    sel.removeAllRanges();
                    sel.addRange(newRange);
                } catch (e) {
                    console.warn('Style application error:', e);
                }
            }

            SelectionManager.save();
            VisualEngine.updateActiveButtons();
            if (typeof saveToHistory === 'function') saveToHistory();
        }
    };

    /**
     * =========================================================================
     * BLOCK FORMATTING & MANIPULATION
     * =========================================================================
     */
    const BlockFormatter = {
        formatBlock(tag) {
            if (!SelectionManager.restore()) return;
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;

            const range = sel.getRangeAt(0);
            const block = DOM.getClosestBlock(range.startContainer);
            const targetTag = tag.toUpperCase();

            if (block && block.id !== 'contentVisual') {
                if (block.tagName.toUpperCase() === targetTag) {
                    // Toggle back to P
                    const p = document.createElement('p');
                    p.innerHTML = block.innerHTML;
                    block.parentNode.replaceChild(p, block);
                    SelectionManager.setCursorToEnd(p);
                } else {
                    const newBlock = document.createElement(targetTag);
                    newBlock.innerHTML = block.innerHTML;
                    block.parentNode.replaceChild(newBlock, block);
                    SelectionManager.setCursorToEnd(newBlock);
                }
            } else {
                const newBlock = document.createElement(targetTag);
                newBlock.innerHTML = '<br>';
                range.insertNode(newBlock);
                SelectionManager.setCursorToStart(newBlock);
            }

            SelectionManager.save();
            VisualEngine.updateActiveButtons();
            if (typeof saveToHistory === 'function') saveToHistory();
        },

        alignText(side) {
            if (typeof editorMode !== 'undefined' && editorMode === 'code') {
                const ta = DOM.getCodeEditor();
                if (!ta) return;
                const start = ta.selectionStart;
                const end = ta.selectionEnd;
                const text = ta.value.substring(start, end);
                const html = `<div style="text-align: ${side};">${text || '&nbsp;'}</div>`;
                ta.value = ta.value.substring(0, start) + html + ta.value.substring(end);
                return;
            }

            // Check active media selection
            if (typeof activeTarget !== 'undefined' && activeTarget && typeof applyMediaAlignment === 'function') {
                const mediaAlignWrap = activeTarget.closest('.blog-image-align-wrap');
                if (mediaAlignWrap) {
                    applyMediaAlignment(mediaAlignWrap, side, true, () => {
                        if (typeof saveToHistory === 'function') saveToHistory();
                    });
                    return;
                }
            }

            if (!SelectionManager.restore()) return;
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;

            const range = sel.getRangeAt(0);
            const block = DOM.getClosestBlock(range.startContainer);

            if (block && block.id !== 'contentVisual') {
                const alignWrap = block.classList.contains('blog-image-align-wrap')
                    ? block
                    : (block.closest ? block.closest('.blog-image-align-wrap') : null);

                if (alignWrap && typeof applyMediaAlignment === 'function') {
                    applyMediaAlignment(alignWrap, side, true, () => {
                        if (typeof saveToHistory === 'function') saveToHistory();
                    });
                    return;
                }
                block.style.textAlign = (side === 'left' ? '' : side);
                if (!block.getAttribute('style')) block.removeAttribute('style');
            }

            SelectionManager.save();
            if (typeof saveToHistory === 'function') saveToHistory();
        },

        insertList(type = 'ul') {
            const listTag = (type === 'ol') ? 'ol' : 'ul';
            const template = `\n<${listTag}>\n  <li>Пункт 1</li>\n  <li>Пункт 2</li>\n  <li>Пункт 3</li>\n</${listTag}>\n`;

            if (typeof editorMode !== 'undefined' && editorMode === 'code') {
                const ta = DOM.getCodeEditor();
                const pos = ta.selectionStart;
                ta.value = ta.value.substring(0, pos) + template + ta.value.substring(pos);
                ta.focus();
            } else {
                VisualEngine.insertBlockMedia(template);
            }
            if (typeof saveToHistory === 'function') saveToHistory();
        },

        insertHorizontalRule() {
            const ta = DOM.getCodeEditor();
            if (typeof editorMode !== 'undefined' && editorMode === 'code') {
                if (window.enableMarkdown) {
                    const start = ta.selectionStart;
                    const end = ta.selectionEnd;
                    const before = ta.value.substring(0, start);
                    const after = ta.value.substring(end);
                    const prefix = (before.length > 0 && !before.endsWith('\n')) ? '\n\n' : (before.endsWith('\n\n') ? '' : '\n');
                    const hrText = `${prefix}---\n\n`;
                    ta.value = before + hrText + after;
                    const newPos = start + hrText.length;
                    ta.setSelectionRange(newPos, newPos);
                    ta.focus();
                } else {
                    const start = ta.selectionStart;
                    const end = ta.selectionEnd;
                    const before = ta.value.substring(0, start);
                    const after = ta.value.substring(end);
                    const prefix = (before.length > 0 && !before.endsWith('\n')) ? '\n' : '';
                    const hrText = `${prefix}<hr>\n`;
                    ta.value = before + hrText + after;
                    const newPos = start + hrText.length;
                    ta.setSelectionRange(newPos, newPos);
                    ta.focus();
                }
            } else {
                VisualEngine.insertBlockMedia('<hr>');
            }
            if (typeof saveToHistory === 'function') saveToHistory();
        }
    };

    /**
     * =========================================================================
     * KEYBOARD HANDLER (Enter, Backspace, Delete, Tab, Shortcuts)
     * =========================================================================
     */
    const KeyHandler = {
        handleEnter(e) {
            const editor = DOM.getEditor();
            if (!editor || (typeof editorMode !== 'undefined' && editorMode !== 'visual')) return;

            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) return;

            const range = sel.getRangeAt(0);

            // 1. Inside Table: insert <br> without breaking table
            const tableCell = (range.startContainer.nodeType === Node.ELEMENT_NODE ? range.startContainer : range.startContainer.parentNode).closest('td, th');
            if (tableCell && DOM.isInside(tableCell, editor)) {
                e.preventDefault();
                range.deleteContents();
                const br = document.createElement('br');
                range.insertNode(br);
                if (!br.nextSibling) {
                    tableCell.appendChild(document.createElement('br'));
                }
                range.setStartAfter(br);
                range.collapse(true);
                sel.removeAllRanges();
                sel.addRange(range);
                SelectionManager.save();
                if (typeof saveToHistory === 'function') saveToHistory();
                editor.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }

            // 2. Shift+Enter: Insert standard <br>
            if (e.shiftKey) {
                e.preventDefault();
                range.deleteContents();
                const br = document.createElement('br');
                range.insertNode(br);
                range.setStartAfter(br);
                range.collapse(true);
                sel.removeAllRanges();
                sel.addRange(range);
                SelectionManager.save();
                if (typeof saveToHistory === 'function') saveToHistory();
                editor.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }

            let block = DOM.getClosestBlock(range.startContainer, editor);

            // 3. Pre-formatted Code Block: insert literal newline
            if (block && block.tagName === 'PRE') {
                e.preventDefault();
                const textNode = document.createTextNode('\n');
                range.insertNode(textNode);
                range.setStartAfter(textNode);
                range.collapse(true);
                sel.removeAllRanges();
                sel.addRange(range);
                SelectionManager.save();
                if (typeof saveToHistory === 'function') saveToHistory();
                editor.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }

            // 4. List Items
            if (block && block.tagName === 'LI') {
                const liText = block.textContent.trim();
                if (liText === '' && !block.querySelector('img, video, audio, iframe')) {
                    // Empty list item -> escape list into paragraph
                    e.preventDefault();
                    const list = block.parentNode;
                    const newP = document.createElement('p');
                    newP.innerHTML = '<br>';

                    if (list && list.parentNode) {
                        list.parentNode.insertBefore(newP, list.nextSibling);
                        block.parentNode.removeChild(block);
                        if (list.children.length === 0) list.parentNode.removeChild(list);
                    } else {
                        editor.appendChild(newP);
                    }
                    SelectionManager.setCursorToStart(newP);
                    SelectionManager.save();
                    if (typeof saveToHistory === 'function') saveToHistory();
                    editor.dispatchEvent(new Event('input', { bubbles: true }));
                    return;
                }
                return; // Default browser LI splitting
            }

            // 5. Headings (H1 - H6)
            if (block && /^H[1-6]$/.test(block.tagName)) {
                e.preventDefault();
                const isAtEnd = SelectionManager.isCaretAtEnd(block, range);
                const isAtStart = SelectionManager.isCaretAtStart(block, range);
                const isBlockEmpty = block.textContent.trim() === '';

                if (isBlockEmpty) {
                    const newP = document.createElement('p');
                    newP.innerHTML = '<br>';
                    block.parentNode.replaceChild(newP, block);
                    SelectionManager.setCursorToStart(newP);
                } else if (isAtEnd) {
                    const newP = document.createElement('p');
                    newP.innerHTML = '<br>';
                    block.parentNode.insertBefore(newP, block.nextSibling);
                    SelectionManager.setCursorToStart(newP);
                } else if (isAtStart) {
                    const newP = document.createElement('p');
                    newP.innerHTML = '<br>';
                    block.parentNode.insertBefore(newP, block);
                    SelectionManager.setCursorToStart(block);
                } else {
                    const afterRange = document.createRange();
                    afterRange.setStart(range.endContainer, range.endOffset);
                    afterRange.setEndAfter(block.lastChild || block);
                    const afterContent = afterRange.extractContents();

                    const newH = document.createElement(block.tagName);
                    newH.appendChild(afterContent);
                    if (newH.innerHTML.trim() === '') newH.innerHTML = '<br>';
                    if (block.innerHTML.trim() === '') block.innerHTML = '<br>';

                    block.parentNode.insertBefore(newH, block.nextSibling);
                    SelectionManager.setCursorToStart(newH);
                }

                SelectionManager.save();
                if (typeof saveToHistory === 'function') saveToHistory();
                editor.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }

            // 6. Blockquote: escape on empty line
            if (block && block.tagName === 'BLOCKQUOTE' && block.textContent.trim() === '') {
                e.preventDefault();
                const newP = document.createElement('p');
                newP.innerHTML = '<br>';
                block.parentNode.replaceChild(newP, block);
                SelectionManager.setCursorToStart(newP);
                SelectionManager.save();
                if (typeof saveToHistory === 'function') saveToHistory();
                editor.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }

            // 7. Formula block: insert new paragraph below
            if (block && block.classList.contains('npblog-formula-block')) {
                e.preventDefault();
                const newP = document.createElement('p');
                newP.innerHTML = '<br>';
                block.parentNode.insertBefore(newP, block.nextSibling);
                SelectionManager.setCursorToStart(newP);
                SelectionManager.save();
                if (typeof saveToHistory === 'function') saveToHistory();
                editor.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }

            // 8. Spoiler Block Escape: if Enter on empty paragraph inside spoiler-content, escape out
            let spoilerBlock = block ? block.closest('.spoiler-block') : null;
            if (spoilerBlock) {
                const spoilerContent = spoilerBlock.querySelector('.spoiler-content');
                if (spoilerContent && spoilerContent.contains(range.startContainer) && DOM.isEmpty(block)) {
                    e.preventDefault();
                    if (block.parentNode) block.parentNode.removeChild(block);
                    const newP = document.createElement('p');
                    newP.innerHTML = '<br>';
                    spoilerBlock.parentNode.insertBefore(newP, spoilerBlock.nextSibling);
                    SelectionManager.setCursorToStart(newP);
                    SelectionManager.save();
                    if (typeof saveToHistory === 'function') saveToHistory();
                    editor.dispatchEvent(new Event('input', { bubbles: true }));
                    return;
                }
            }

            // 9. Standard Paragraph Split (<p>, <div>)
            e.preventDefault();
            if (!block || block === editor) {
                DOM.normalizeDOM(editor);
                block = DOM.getClosestBlock(range.startContainer, editor) || editor.firstElementChild;
            }

            if (block && block !== editor) {
                range.deleteContents();
                const isAtStart = SelectionManager.isCaretAtStart(block, range);
                const isAtEnd = SelectionManager.isCaretAtEnd(block, range);
                const isBlockEmpty = DOM.isEmpty(block) && !block.querySelector('img, video, audio, iframe, table, hr');

                if (isBlockEmpty) {
                    block.innerHTML = '<br>';
                    const newP = document.createElement('p');
                    newP.innerHTML = '<br>';
                    block.parentNode.insertBefore(newP, block.nextSibling);
                    SelectionManager.setCursorToStart(newP);
                } else if (isAtStart) {
                    const newP = document.createElement('p');
                    newP.innerHTML = '<br>';
                    block.parentNode.insertBefore(newP, block);
                    SelectionManager.setCursorToStart(block);
                } else if (isAtEnd) {
                    const newP = document.createElement('p');
                    newP.innerHTML = '<br>';
                    block.parentNode.insertBefore(newP, block.nextSibling);
                    SelectionManager.setCursorToStart(newP);
                } else {
                    const afterRange = document.createRange();
                    afterRange.setStart(range.endContainer, range.endOffset);
                    afterRange.setEndAfter(block.lastChild || block);

                    let afterContent;
                    try {
                        afterContent = afterRange.extractContents();
                    } catch (err) {
                        afterContent = document.createDocumentFragment();
                    }

                    const newBlock = document.createElement('p');
                    if (block.className) newBlock.className = block.className;
                    if (block.style.cssText) newBlock.style.cssText = block.style.cssText;

                    newBlock.appendChild(afterContent);
                    if (DOM.isEmpty(newBlock) && !newBlock.querySelector('img, video, audio, iframe, table, hr')) {
                        newBlock.innerHTML = '<br>';
                    }
                    if (DOM.isEmpty(block) && !block.querySelector('img, video, audio, iframe, table, hr')) {
                        block.innerHTML = '<br>';
                    }

                    block.parentNode.insertBefore(newBlock, block.nextSibling);
                    SelectionManager.setCursorToStart(newBlock);
                }
            } else {
                const newP = document.createElement('p');
                newP.innerHTML = '<br>';
                editor.appendChild(newP);
                SelectionManager.setCursorToStart(newP);
            }

            SelectionManager.save();
            if (typeof saveToHistory === 'function') saveToHistory();
            editor.dispatchEvent(new Event('input', { bubbles: true }));
        },

        handleBackspaceDelete(e) {
            const editor = DOM.getEditor();
            if (!editor || (typeof editorMode !== 'undefined' && editorMode !== 'visual')) return;

            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) return;

            const range = sel.getRangeAt(0);
            if (!range.collapsed) return; // Browser handles range deletion cleanly

            const isBackspace = (e.key === 'Backspace');
            const block = DOM.getClosestBlock(range.startContainer, editor);
            if (!block || block === editor) return;

            // 0. Protect Table Cells
            const tableCell = (range.startContainer.nodeType === Node.ELEMENT_NODE ? range.startContainer : range.startContainer.parentNode).closest('td, th');
            if (tableCell && DOM.isInside(tableCell, editor)) {
                if (isBackspace && SelectionManager.isCaretAtStart(tableCell, range)) {
                    e.preventDefault();
                    return;
                }
                if (tableCell.innerHTML === '<br>' || tableCell.innerHTML.trim() === '') {
                    e.preventDefault();
                    return;
                }
                return;
            }

            // 1. Backspace at Start of Block
            if (isBackspace && SelectionManager.isCaretAtStart(block, range)) {
                const prevSibling = block.previousElementSibling;

                if (prevSibling) {
                    e.preventDefault();

                    // Table protection
                    if (prevSibling.tagName === 'TABLE') {
                        if (block.textContent.trim() === '' && !block.querySelector('img, video, audio, iframe')) {
                            block.parentNode.removeChild(block);
                            const lastCell = prevSibling.querySelector('tr:last-child td:last-child, tr:last-child th:last-child');
                            if (lastCell) SelectionManager.setCursorToEnd(lastCell);
                            SelectionManager.save();
                            if (typeof saveToHistory === 'function') saveToHistory();
                        }
                        return;
                    }

                    // Formula block protection
                    if (prevSibling.classList.contains('npblog-formula-block')) {
                        if (block.textContent.trim() === '' && !block.querySelector('img, video, audio, iframe')) {
                            block.parentNode.removeChild(block);
                            SelectionManager.setCursorToEnd(prevSibling);
                            SelectionManager.save();
                            if (typeof saveToHistory === 'function') saveToHistory();
                        }
                        return;
                    }

                    // Media wrap / HR protection
                    if (prevSibling.classList.contains('blog-image-align-wrap') || prevSibling.tagName === 'HR') {
                        const isCurrentEmpty = (block.textContent.trim() === '' && !block.querySelector('img, video, audio, iframe'));
                        if (!isCurrentEmpty) {
                            const beforeMedia = prevSibling.previousElementSibling;
                            if (beforeMedia) {
                                SelectionManager.setCursorToEnd(beforeMedia);
                                SelectionManager.save();
                            }
                            return;
                        }
                        block.parentNode.removeChild(block);
                        if (!prevSibling.classList.contains('align-float-left') && !prevSibling.classList.contains('align-float-right')) {
                            prevSibling.parentNode.removeChild(prevSibling);
                        }
                        DOM.normalizeDOM(editor);
                        SelectionManager.save();
                        if (typeof saveToHistory === 'function') saveToHistory();
                        return;
                    }

                    // Merge current block into previous block
                    const isCurrentEmpty = (block.textContent.trim() === '' && !block.querySelector('img, video, audio, iframe'));
                    if (isCurrentEmpty) {
                        SelectionManager.setCursorToEnd(prevSibling);
                        block.parentNode.removeChild(block);
                    } else {
                        const marker = document.createElement('span');
                        marker.id = 'merge-caret-temp';
                        marker.innerHTML = '\uFEFF';

                        if (prevSibling.innerHTML === '<br>') prevSibling.innerHTML = '';
                        prevSibling.appendChild(marker);

                        while (block.firstChild) {
                            if (block.firstChild.nodeName !== 'BR' || block.childNodes.length > 1) {
                                prevSibling.appendChild(block.firstChild);
                            } else {
                                block.removeChild(block.firstChild);
                            }
                        }

                        block.parentNode.removeChild(block);

                        const nextNode = marker.nextSibling;
                        const parent = marker.parentNode;
                        parent.removeChild(marker);

                        const newRange = document.createRange();
                        if (nextNode) {
                            newRange.setStartBefore(nextNode);
                        } else {
                            newRange.selectNodeContents(parent);
                            newRange.collapse(false);
                        }
                        newRange.collapse(true);
                        sel.removeAllRanges();
                        sel.addRange(newRange);
                    }

                    SelectionManager.save();
                    if (typeof saveToHistory === 'function') saveToHistory();
                    return;
                } else {
                    // First block in editor: convert non-P to P, never delete
                    if (block.tagName !== 'P') {
                        e.preventDefault();
                        const p = document.createElement('p');
                        p.innerHTML = block.innerHTML || '<br>';
                        block.parentNode.replaceChild(p, block);
                        SelectionManager.setCursorToStart(p);
                        SelectionManager.save();
                        if (typeof saveToHistory === 'function') saveToHistory();
                        return;
                    }
                }
            }

            // 2. Delete at End of Block
            if (!isBackspace && SelectionManager.isCaretAtEnd(block, range)) {
                const nextSibling = block.nextElementSibling;
                if (nextSibling) {
                    e.preventDefault();

                    if (nextSibling.classList.contains('blog-image-align-wrap') || nextSibling.tagName === 'TABLE' || nextSibling.tagName === 'HR') {
                        nextSibling.parentNode.removeChild(nextSibling);
                    } else {
                        if (nextSibling.innerHTML === '<br>') {
                            nextSibling.parentNode.removeChild(nextSibling);
                        } else {
                            if (block.innerHTML === '<br>') block.innerHTML = '';
                            while (nextSibling.firstChild) {
                                block.appendChild(nextSibling.firstChild);
                            }
                            nextSibling.parentNode.removeChild(nextSibling);
                        }
                    }

                    SelectionManager.save();
                    if (typeof saveToHistory === 'function') saveToHistory();
                    return;
                }
            }
        },

        handleTab(e) {
            const editor = DOM.getEditor();
            if (!editor || (typeof editorMode !== 'undefined' && editorMode !== 'visual')) return false;

            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) return false;

            const range = sel.getRangeAt(0);
            const container = range.startContainer;
            const cell = (container.nodeType === Node.ELEMENT_NODE ? container : container.parentNode).closest('td, th');
            if (!cell || !DOM.isInside(cell, editor)) return false;

            e.preventDefault();
            const table = cell.closest('table');
            if (!table) return false;

            const allCells = Array.from(new Set(table.querySelectorAll('thead th, thead td, tbody td, tbody th, tr td, tr th')));
            const idx = allCells.indexOf(cell);

            if (e.shiftKey) {
                if (idx > 0) {
                    SelectionManager.setCursorToStart(allCells[idx - 1]);
                    SelectionManager.save();
                }
            } else {
                if (idx < allCells.length - 1) {
                    SelectionManager.setCursorToStart(allCells[idx + 1]);
                    SelectionManager.save();
                } else if (typeof addTableRow === 'function') {
                    window.contextMenuTableRow = cell.closest('tr');
                    addTableRow();
                }
            }
            return true;
        }
    };

    /**
     * =========================================================================
     * SERIALIZATION & FORMATTER (Clean Save & Pretty Indent)
     * =========================================================================
     */
    const Serializer = {
        cleanContentForSave(html) {
            if (!html) return '';
            const temp = document.createElement('div');
            temp.innerHTML = html;

            // Remove runtime editor widgets & overlays
            const toRemove = temp.querySelectorAll(
                '.image-toolbar, .image-align-dropdown, .image-size-indicator, ' +
                '.image-resize-handle, .blog-image-overlay, .column-resizer, #customCaret, .media-upload-placeholder'
            );
            toRemove.forEach(el => el.parentNode && el.parentNode.removeChild(el));

            // Remove temporary runtime attributes
            temp.querySelectorAll('[data-image-id], [data-media-id], [data-media-type], table[data-resizers-added], [data-wrap-animating]').forEach(el => {
                el.removeAttribute('data-image-id');
                el.removeAttribute('data-media-id');
                el.removeAttribute('data-media-type');
                el.removeAttribute('data-resizers-added');
                el.removeAttribute('data-wrap-animating');
            });

            // Strip runtime animation styles
            temp.querySelectorAll('[style*="transform"], [style*="transition"], [style*="will-change"]').forEach(el => {
                el.style.transform = '';
                el.style.transition = '';
                el.style.willChange = '';
                const rem = el.getAttribute('style');
                if (rem !== null && rem.trim() === '') el.removeAttribute('style');
            });

            // Preserve contenteditable="false" ONLY on protected widgets
            temp.querySelectorAll('[contenteditable]').forEach(el => {
                if (el.getAttribute('contenteditable') === 'false' && (
                    el.classList.contains('npblog-formula') ||
                    el.classList.contains('npblog-formula-block') ||
                    el.hasAttribute('data-formula') ||
                    el.classList.contains('code-block') ||
                    el.classList.contains('custom-blog-btn')
                )) {
                    return;
                }
                el.removeAttribute('contenteditable');
            });

            temp.querySelectorAll('.selected').forEach(el => el.classList.remove('selected'));

            // Shelter formula elements during cleanup
            const formulaBlocks = [];
            temp.querySelectorAll('.npblog-formula, .npblog-formula-block, [data-formula]').forEach(fEl => {
                if (fEl.parentElement && fEl.parentElement.closest('.npblog-formula, .npblog-formula-block, [data-formula]')) return;
                const idx = formulaBlocks.length;
                formulaBlocks.push(fEl.outerHTML);
                const placeholder = document.createTextNode('___FORMULA_SAVE_HOLDER_' + idx + '___');
                fEl.parentNode.replaceChild(placeholder, fEl);
            });

            DOM.cleanEmptyInline(temp, false);

            // Normalize empty paragraphs to have <br>
            temp.querySelectorAll('p, div').forEach(b => {
                if (b.innerHTML.trim() === '') b.innerHTML = '<br>';
            });

            if (temp.children.length === 0 && temp.textContent.trim() === '') return '';
            if (temp.children.length === 1 && temp.firstElementChild.tagName === 'P' && DOM.isEmpty(temp.firstElementChild)) return '';

            let cleaned = temp.innerHTML.replace(/(?:[?&]|&amp;)t=\d+/g, '');

            // Second pass regex cleanup for stray empty tags
            let prevCleaned;
            do {
                prevCleaned = cleaned;
                cleaned = cleaned.replace(/<(strong|b|em|i|u|s|strike|del|sup|sub|code|span)(?:\s+[^>]*)?>\s*(?:&nbsp;|\u200B|\uFEFF)?\s*<\/\1>/gi, '');
            } while (cleaned !== prevCleaned);

            cleaned = cleaned.replace(/<p(?:\s+[^>]*)?>\s*<\/p>/gi, '<p><br></p>');

            let formatted = this.formatHTML(cleaned);

            // Restore sheltered formulas
            for (let i = 0; i < formulaBlocks.length; i++) {
                formatted = formatted.replace('___FORMULA_SAVE_HOLDER_' + i + '___', formulaBlocks[i]);
            }

            return formatted;
        },

        formatHTML(html) {
            if (!html) return '';

            let formulaBlocks = [];
            let formatted = html;

            if (typeof document !== 'undefined' && (formatted.indexOf('npblog-formula') !== -1 || formatted.indexOf('data-formula') !== -1)) {
                try {
                    const tempDoc = document.createElement('div');
                    tempDoc.innerHTML = formatted;
                    tempDoc.querySelectorAll('.npblog-formula, .npblog-formula-block, [data-formula]').forEach(fEl => {
                        if (fEl.parentElement && fEl.parentElement.closest('.npblog-formula, .npblog-formula-block, [data-formula]')) return;
                        const idx = formulaBlocks.length;
                        formulaBlocks.push(fEl.outerHTML);
                        const placeholder = document.createTextNode('___FORMULA_BLOCK_HOLDER_' + idx + '___');
                        fEl.parentNode.replaceChild(placeholder, fEl);
                    });
                    formatted = tempDoc.innerHTML;
                } catch (e) { }
            }

            let preBlocks = [];
            formatted = formatted.replace(/<pre[^>]*>[\s\S]*?<\/pre>/gi, match => {
                preBlocks.push(match);
                return '___PRE_BLOCK_HOLDER_' + (preBlocks.length - 1) + '___';
            });

            const blockTags = [
                'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
                'blockquote', 'section', 'article', 'header', 'footer', 'hr',
                'details', 'summary'
            ];

            formatted = formatted.replace(/\r/g, '');

            blockTags.forEach(tag => {
                const openRegex = new RegExp('<(' + tag + ')((\\s+[^>]*?>)|>)', 'gi');
                formatted = formatted.replace(openRegex, '\n<$1$2');

                const closeRegex = new RegExp('</(' + tag + ')>', 'gi');
                formatted = formatted.replace(closeRegex, '</$1>\n');
            });

            formatted = formatted.replace(/<hr(\s+[^>]*?>| >|>)/gi, '\n<hr$1\n');
            formatted = formatted.replace(/<br(\s*\/)?>/gi, '<br$1>\n');

            const lines = formatted.split('\n');
            let pad = 0;
            const result = [];

            for (let i = 0; i < lines.length; i++) {
                const line = lines[i].trim();
                if (!line) continue;

                let isClosing = false;
                for (let j = 0; j < blockTags.length; j++) {
                    if (line.toLowerCase().startsWith('</' + blockTags[j])) {
                        isClosing = true;
                        break;
                    }
                }

                let isOpening = false;
                if (!isClosing) {
                    for (let j = 0; j < blockTags.length; j++) {
                        const tag = blockTags[j];
                        const reg = new RegExp('^<' + tag + '(\\s+|>)', 'i');
                        if (reg.test(line)) {
                            const hasClose = new RegExp('</' + tag + '>$', 'i').test(line);
                            if (!hasClose && tag !== 'hr') {
                                isOpening = true;
                            }
                            break;
                        }
                    }
                }

                if (isClosing) pad = Math.max(0, pad - 1);
                result.push('    '.repeat(pad) + line);
                if (isOpening) pad++;
            }

            let finalHtml = result.join('\n');
            for (let i = 0; i < formulaBlocks.length; i++) {
                finalHtml = finalHtml.replace('___FORMULA_BLOCK_HOLDER_' + i + '___', formulaBlocks[i]);
            }
            for (let i = 0; i < preBlocks.length; i++) {
                finalHtml = finalHtml.replace('___PRE_BLOCK_HOLDER_' + i + '___', preBlocks[i]);
            }

            return finalHtml.trim();
        }
    };

    /**
     * =========================================================================
     * CORE VISUAL ENGINE OBJECT
     * =========================================================================
     */
    const VisualEngine = {
        getEditor: DOM.getEditor,
        getCodeEditor: DOM.getCodeEditor,
        isInsideEditor: DOM.isInside,
        isEmpty: DOM.isEmpty,
        getClosestBlock: DOM.getClosestBlock,
        normalizeDOM: DOM.normalizeDOM,
        cleanDOMArtifacts: DOM.cleanEmptyInline,

        isEditorActive() {
            return typeof editorMode !== 'undefined' && editorMode === 'visual';
        },

        isSelectionInEditor() {
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return false;
            const range = sel.getRangeAt(0);
            return range && range.commonAncestorContainer ? DOM.isInside(range.commonAncestorContainer, DOM.getEditor()) : false;
        },

        saveSelection: SelectionManager.save.bind(SelectionManager),
        restoreFocus: SelectionManager.restore.bind(SelectionManager),
        setCursorToStart: SelectionManager.setCursorToStart.bind(SelectionManager),
        setCursorToEnd: SelectionManager.setCursorToEnd.bind(SelectionManager),
        isCaretAtStartOfBlock: SelectionManager.isCaretAtStart.bind(SelectionManager),
        isCaretAtEndOfBlock: SelectionManager.isCaretAtEnd.bind(SelectionManager),
        getSelectionOffsets: SelectionManager.getOffsets.bind(SelectionManager),
        setSelectionOffsets: SelectionManager.setOffsets.bind(SelectionManager),

        formatInline: InlineFormatter.formatInline.bind(InlineFormatter),
        applyInlineStyle: InlineFormatter.applyInlineStyle.bind(InlineFormatter),
        findAncestorTag: InlineFormatter.findAncestor.bind(InlineFormatter),
        isFormatAppliedToRange: InlineFormatter.isFormatActive.bind(InlineFormatter),
        removeFormatFromRange: InlineFormatter.removeFormat.bind(InlineFormatter),

        formatBlock: BlockFormatter.formatBlock.bind(BlockFormatter),
        setTextAlignment: BlockFormatter.alignText.bind(BlockFormatter),
        insertList: BlockFormatter.insertList.bind(BlockFormatter),
        insertHorizontalRule: BlockFormatter.insertHorizontalRule.bind(BlockFormatter),

        handleEnterKey: KeyHandler.handleEnter.bind(KeyHandler),
        handleBackspaceDeleteKey: KeyHandler.handleBackspaceDelete.bind(KeyHandler),
        handleTabKey: KeyHandler.handleTab.bind(KeyHandler),

        cleanContentForSave: Serializer.cleanContentForSave.bind(Serializer),
        formatHTML: Serializer.formatHTML.bind(Serializer),

        insertHTML(html, isBlock = false) {
            if (!SelectionManager.restore()) return;
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;

            const range = sel.getRangeAt(0);
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
                sel.removeAllRanges();
                sel.addRange(range);
            }

            SelectionManager.save();
            if (typeof saveToHistory === 'function') saveToHistory();
        },

        insertBlockMedia(html, targetRange) {
            const editor = DOM.getEditor();
            if (!editor) return;

            const sourceRange = targetRange || window.savedRange || savedRange || (typeof window.getSavedRange === 'function' ? window.getSavedRange() : null);
            // Focus handlers save the current selection. Preserve the explicit
            // drop position independently before restoring editor focus.
            const r = sourceRange ? sourceRange.cloneRange() : null;
            SelectionManager.restore(r);

            const sel = window.getSelection();
            let range = (r && DOM.isInside(r.commonAncestorContainer, editor)) ? r : ((sel && sel.rangeCount > 0 && DOM.isInside(sel.getRangeAt(0).commonAncestorContainer, editor)) ? sel.getRangeAt(0) : null);

            const temp = document.createElement('div');
            temp.innerHTML = html;
            const frag = document.createDocumentFragment();
            let node, lastNode;

            while ((node = temp.firstChild)) {
                lastNode = frag.appendChild(node);
            }

            const emptyP = document.createElement('p');
            emptyP.innerHTML = '<br>';

            if (!range || !DOM.isInside(range.commonAncestorContainer, editor)) {
                editor.appendChild(frag);
                editor.appendChild(emptyP);
                SelectionManager.setCursorToStart(emptyP);
            } else {
                range.deleteContents();
                let block = DOM.getClosestBlock(range.startContainer, editor);

                if (!block || block === editor) {
                    range.insertNode(frag);
                    if (lastNode && lastNode.parentNode) {
                        if (lastNode.nextSibling) {
                            SelectionManager.setCursorToStart(lastNode.nextSibling);
                        } else {
                            lastNode.parentNode.insertBefore(emptyP, lastNode.nextSibling);
                            SelectionManager.setCursorToStart(emptyP);
                        }
                    } else {
                        editor.appendChild(emptyP);
                        SelectionManager.setCursorToStart(emptyP);
                    }
                } else if (DOM.isEmpty(block) && !block.querySelector('img, video, audio, iframe, table, hr')) {
                    const parent = block.parentNode;
                    const next = block.nextSibling;
                    parent.insertBefore(frag, block);
                    parent.removeChild(block);
                    if (next) {
                        SelectionManager.setCursorToStart(next);
                    } else {
                        parent.appendChild(emptyP);
                        SelectionManager.setCursorToStart(emptyP);
                    }
                } else if (SelectionManager.isCaretAtStart(block, range)) {
                    block.parentNode.insertBefore(frag, block);
                    SelectionManager.setCursorToStart(block);
                } else if (SelectionManager.isCaretAtEnd(block, range)) {
                    const parent = block.parentNode;
                    const next = block.nextSibling;
                    if (next) {
                        parent.insertBefore(frag, next);
                        SelectionManager.setCursorToStart(next);
                    } else {
                        parent.appendChild(frag);
                        parent.appendChild(emptyP);
                        SelectionManager.setCursorToStart(emptyP);
                    }
                } else {
                    const afterRange = document.createRange();
                    afterRange.setStart(range.endContainer, range.endOffset);
                    afterRange.setEndAfter(block.lastChild || block);
                    const afterContent = afterRange.extractContents();

                    const newBlock = block.cloneNode(false);
                    newBlock.removeAttribute('id');
                    if (block.className) newBlock.className = block.className;
                    if (block.style.cssText) newBlock.style.cssText = block.style.cssText;
                    newBlock.appendChild(afterContent);

                    const parent = block.parentNode;
                    const next = block.nextSibling;

                    parent.insertBefore(frag, next);
                    parent.insertBefore(newBlock, next);
                    SelectionManager.setCursorToStart(newBlock);
                }
            }

            SelectionManager.save();
            if (typeof saveToHistory === 'function') saveToHistory();
        },

        updateActiveButtons() {
            if (typeof editorMode !== 'undefined' && editorMode !== 'visual') return;
            const editor = DOM.getEditor();
            const sel = window.getSelection();

            const buttonIds = ['btn-bold', 'btn-italic', 'btn-underline', 'btn-strike', 'btn-sup', 'btn-sub', 'btn-h2'];
            const setBtn = (id, active) => {
                const el = document.getElementById(id);
                if (el) el.classList.toggle('active', !!active);
            };

            if (!editor || !sel || sel.rangeCount === 0 || !DOM.isInside(sel.anchorNode, editor)) {
                buttonIds.forEach(id => setBtn(id, false));
                return;
            }

            try {
                const node = sel.anchorNode;
                setBtn('btn-bold', InlineFormatter.findAncestor(node, 'STRONG', editor) || InlineFormatter.findAncestor(node, 'B', editor));
                setBtn('btn-italic', InlineFormatter.findAncestor(node, 'EM', editor) || InlineFormatter.findAncestor(node, 'I', editor));
                setBtn('btn-underline', InlineFormatter.findAncestor(node, 'U', editor));
                setBtn('btn-strike', InlineFormatter.findAncestor(node, 'S', editor) || InlineFormatter.findAncestor(node, 'STRIKE', editor) || InlineFormatter.findAncestor(node, 'DEL', editor));
                setBtn('btn-sup', InlineFormatter.findAncestor(node, 'SUP', editor));
                setBtn('btn-sub', InlineFormatter.findAncestor(node, 'SUB', editor));

                const block = DOM.getClosestBlock(node, editor);
                setBtn('btn-h2', block && block.tagName === 'H2');

                // Inspect active font, size, and color
                let fontName = 'Arial';
                let fontSize = '14px';
                let currentColor = '';
                let check = (node.nodeType === Node.TEXT_NODE) ? node.parentNode : node;

                while (check && check !== editor) {
                    if (check.nodeType === Node.ELEMENT_NODE) {
                        if (check.style && check.style.fontFamily && fontName === 'Arial') {
                            fontName = check.style.fontFamily.split(',')[0].replace(/['"]/g, '').trim();
                        }
                        if (check.style && check.style.fontSize && fontSize === '14px') {
                            fontSize = check.style.fontSize;
                        }
                        if (check.style && check.style.color && !currentColor) {
                            currentColor = check.style.color;
                        }
                    }
                    check = check.parentNode;
                }

                const fontBtn = document.getElementById('fontFamilyBtn');
                if (fontBtn) {
                    fontBtn.textContent = fontName;
                    fontBtn.style.fontFamily = fontName;
                }

                const sizeBtn = document.getElementById('fontSizeBtn');
                if (sizeBtn) {
                    sizeBtn.textContent = fontSize;
                }

                if (currentColor) {
                    const preview = document.querySelector('#colorPickerWrapMain .color-preview');
                    if (preview) preview.style.background = currentColor;
                }
            } catch (e) { }
        }
    };

    /**
     * =========================================================================
     * BACKWARD-COMPATIBLE GLOBAL API EXPORTS
     * =========================================================================
     */
    window.VisualEngine = VisualEngine;
    window.isEditorContentEmpty = function (htmlOrText) { return VisualEngine.isEmpty(htmlOrText); };

    window.saveSelection = function () { VisualEngine.saveSelection(); };
    window.restoreEditorFocus = function () { return VisualEngine.restoreFocus(); };
    window.insertHtmlAtCursor = function (html) { VisualEngine.insertHTML(html); };
    window.insertHtmlAtCaret = function (html) { VisualEngine.insertHTML(html); };
    window.insertImageBlockAtCaret = function (html) { VisualEngine.insertBlockMedia(html); };
    window.cleanContentForSave = function (html) { return VisualEngine.cleanContentForSave(html); };
    window.formatHTML = function (html) { return VisualEngine.formatHTML(html); };
    window.updateActiveButtons = function () { VisualEngine.updateActiveButtons(); };
    window.getSelectionOffsets = function (c) { return VisualEngine.getSelectionOffsets(c); };
    window.setSelectionOffsets = function (c, s, e) { VisualEngine.setSelectionOffsets(c, s, e); };
    window.toggleInlineFormat = function (tag) { VisualEngine.formatInline(tag); };
    window.toggleBlockFormat = function (tag) { VisualEngine.formatBlock(tag); };
    window.alignText = function (side) { VisualEngine.setTextAlignment(side); };

    window.formatText = function (tag) {
        if (tag === 'hr') {
            window.insertHorizontalRule();
            return;
        }
        const ta = DOM.getCodeEditor();
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
                newCursorStart += 2; newCursorEnd += 2;
            } else if (tag === 'i') {
                formattedText = `*${selectedText}*`;
                newCursorStart += 1; newCursorEnd += 1;
            } else if (tag === 's') {
                formattedText = `~~${selectedText}~~`;
                newCursorStart += 2; newCursorEnd += 2;
            } else if (tag === 'h2') {
                formattedText = `\n## ${selectedText}\n`;
                newCursorStart += 4; newCursorEnd += 4;
            }

            ta.value = beforeText + formattedText + afterText;
            ta.setSelectionRange(newCursorStart, newCursorEnd);
            if (typeof saveToHistory === 'function') saveToHistory();
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
            if (typeof saveToHistory === 'function') saveToHistory();
        } else {
            if (tag === 'h2') {
                VisualEngine.formatBlock('h2');
            } else {
                VisualEngine.formatInline(tag);
            }
        }
    };

    window.insertList = function () {
        VisualEngine.insertList('ul');
    };

    window.insertHorizontalRule = function () {
        VisualEngine.insertHorizontalRule();
    };
    window.insertHr = window.insertHorizontalRule;

    window.setMode = function (mode) {
        editorMode = mode;
        const ta = DOM.getCodeEditor();
        const ve = DOM.getEditor();
        const visualBtn = document.getElementById('modeVisualBtn');
        const codeBtn = document.getElementById('modeCodeBtn');

        if (mode === 'visual') {
            ve.contentEditable = 'true';
            if (window.enableMarkdown) {
                ve.innerHTML = (typeof parseMarkdownToHtml === 'function') ? parseMarkdownToHtml(ta.value) : ta.value;
            } else {
                if (ta.style.display !== 'none' || ve.innerHTML === '') {
                    ve.innerHTML = ta.value;
                    if (typeof wrapExistingEditorImages === 'function') wrapExistingEditorImages();
                    if (typeof addColumnResizers === 'function') addColumnResizers();
                }
            }
            VisualEngine.normalizeDOM(ve);
            ve.style.display = '';
            ta.style.display = 'none';
            if (visualBtn) visualBtn.classList.add('active');
            if (codeBtn) codeBtn.classList.remove('active');
        } else {
            if (typeof hideGlobalMediaOverlay === 'function') hideGlobalMediaOverlay();
            ve.contentEditable = 'true';
            if (window.enableMarkdown) {
                if (ve.style.display !== 'none') {
                    ta.value = (typeof convertHtmlToMarkdown === 'function') ? convertHtmlToMarkdown(ve.innerHTML) : ve.innerHTML;
                }
            } else {
                if (ve.style.display !== 'none') {
                    ta.value = VisualEngine.cleanContentForSave(ve.innerHTML);
                }
            }
            ta.style.display = '';
            ve.style.display = 'none';
            if (codeBtn) codeBtn.classList.add('active');
            if (visualBtn) visualBtn.classList.remove('active');
        }
        if (typeof updateAutosaveBadge === 'function') updateAutosaveBadge();
        if (typeof updateBottomBarStats === 'function') updateBottomBarStats();
    };

    /**
     * =========================================================================
     * EVENT LISTENERS INITIALIZATION
     * =========================================================================
     */
    document.addEventListener('DOMContentLoaded', function () {
        const editor = DOM.getEditor();
        if (!editor) return;

        // Toolbar focus protection
        const bar = document.getElementById('formatBarRow');
        if (bar) {
            bar.addEventListener('mousedown', function (e) {
                const btn = e.target.closest('button');
                if (!btn) return;
                if (e.target.closest('.font-size-popover, .font-family-popover, .color-palette-popover')) return;
                e.preventDefault();
                VisualEngine.restoreFocus();
            }, true);
        }

        // Selection & Button State Tracking
        ['mouseup', 'keyup', 'click', 'touchend'].forEach(evt => {
            editor.addEventListener(evt, function () {
                if (VisualEngine.isEditorActive()) {
                    VisualEngine.saveSelection();
                    VisualEngine.updateActiveButtons();
                }
            }, true);
        });

        // Input & Mutation
        editor.addEventListener('input', function () {
            if (VisualEngine.isEditorActive()) {
                if (!editor.hasChildNodes() || editor.innerHTML.trim() === '' || editor.innerHTML === '<br>') {
                    editor.innerHTML = '<p><br></p>';
                    VisualEngine.setCursorToStart(editor.firstElementChild);
                    VisualEngine.saveSelection();
                }
                VisualEngine.saveSelection();
                VisualEngine.updateActiveButtons();
                if (typeof markEditorDirty === 'function') markEditorDirty();
                if (typeof updateAutosaveBadge === 'function') updateAutosaveBadge();
            }
        });

        // Keyboard Handling
        editor.addEventListener('keydown', function (e) {
            if (e.key === 'Tab') {
                if (VisualEngine.handleTabKey(e)) return;
            }
            if (e.key === 'Enter') {
                VisualEngine.handleEnterKey(e);
            } else if (e.key === 'Backspace' || e.key === 'Delete') {
                VisualEngine.handleBackspaceDeleteKey(e);
            }
        });

        // Click on empty space below content -> append or focus empty paragraph
        editor.addEventListener('click', function (e) {
            if (e.target !== editor) return;
            const lastEl = editor.lastElementChild;
            if (!lastEl) return;
            const rect = lastEl.getBoundingClientRect();
            const isFloated = lastEl.classList.contains('align-float-left') || lastEl.classList.contains('align-float-right');
            const clickedBelow = e.clientY > rect.bottom;
            const clickedBeside = isFloated && (e.clientY >= rect.top && e.clientY <= rect.bottom);

            if (clickedBelow || clickedBeside) {
                if (lastEl.tagName === 'P' && DOM.isEmpty(lastEl)) {
                    VisualEngine.setCursorToEnd(lastEl);
                } else {
                    const emptyP = document.createElement('p');
                    emptyP.innerHTML = '<br>';
                    editor.appendChild(emptyP);
                    VisualEngine.setCursorToStart(emptyP);
                    editor.dispatchEvent(new Event('input', { bubbles: true }));
                }
                VisualEngine.saveSelection();
            }
        });

        // Initial DOM Normalization
        VisualEngine.normalizeDOM(editor);
    });

})(window, document);
