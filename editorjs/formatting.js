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

    const INLINE_TAG_ALIASES = {
        'STRONG': ['STRONG', 'B'],
        'B': ['STRONG', 'B'],
        'EM': ['EM', 'I'],
        'I': ['EM', 'I'],
        'S': ['S', 'STRIKE', 'DEL'],
        'STRIKE': ['S', 'STRIKE', 'DEL'],
        'DEL': ['S', 'STRIKE', 'DEL'],
        'U': ['U'],
        'SUP': ['SUP'],
        'SUB': ['SUB'],
        'CODE': ['CODE'],
        'MARK': ['MARK']
    };

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
                    const hasBr = !!el.querySelector('br');
                    const text = el.textContent.replace(/[\s\u00A0\u200B\uFEFF]/g, '');
                    if (text.length === 0 && el.parentNode) {
                        if (hasBr) {
                            el.parentNode.insertBefore(document.createElement('br'), el);
                        }
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
        getTagVariants(tagName) {
            const upper = String(tagName || '').toUpperCase();
            return INLINE_TAG_ALIASES[upper] || [upper];
        },

        getTagSelector(tagName) {
            return this.getTagVariants(tagName).map(t => t.toLowerCase()).join(', ');
        },

        findAncestor(node, tagName, editor) {
            if (!node) return null;
            const ed = editor || DOM.getEditor();
            let curr = (node.nodeType === Node.TEXT_NODE) ? node.parentNode : node;
            const variants = this.getTagVariants(tagName);
            while (curr && curr !== ed) {
                if (curr.nodeType === Node.ELEMENT_NODE && variants.includes(curr.tagName.toUpperCase())) {
                    return curr;
                }
                curr = curr.parentNode;
            }
            return null;
        },

        getMatchingAncestors(node, tagName, boundary) {
            const list = [];
            let curr = (node && node.nodeType === Node.TEXT_NODE) ? node.parentNode : node;
            const variants = this.getTagVariants(tagName);
            while (curr && curr !== boundary && !DOM.isBlock(curr)) {
                if (curr.nodeType === Node.ELEMENT_NODE && variants.includes(curr.tagName.toUpperCase())) {
                    list.push(curr);
                }
                curr = curr.parentNode;
            }
            return list;
        },

        unwrapMatchingDescendants(root, tagName) {
            if (!root || !root.querySelectorAll) return;
            const sel = this.getTagSelector(tagName);
            if (!sel) return;
            root.querySelectorAll(sel).forEach(nested => {
                const parent = nested.parentNode;
                if (parent) {
                    while (nested.firstChild) parent.insertBefore(nested.firstChild, nested);
                    parent.removeChild(nested);
                }
            });
        },

        mergeAdjacentInline(root, targetTag) {
            if (!root || !root.querySelectorAll) return;
            const variants = this.getTagVariants(targetTag);
            const sel = this.getTagSelector(targetTag);
            if (!sel) return;
            root.querySelectorAll(sel).forEach(el => {
                if (!el.parentNode || el.attributes.length > 0) return;
                while (el.nextSibling && el.nextSibling.nodeType === Node.ELEMENT_NODE &&
                    variants.includes(el.nextSibling.tagName.toUpperCase()) &&
                    el.nextSibling.attributes.length === 0) {
                    const next = el.nextSibling;
                    while (next.firstChild) el.appendChild(next.firstChild);
                    next.parentNode.removeChild(next);
                }
            });
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
            if (!ed || !range) return false;

            if (range.collapsed) {
                return !!this.findAncestor(range.startContainer, tagName, ed);
            }

            if (this.findAncestor(range.commonAncestorContainer, tagName, ed)) {
                return true;
            }

            const startAncestor = this.findAncestor(range.startContainer, tagName, ed);
            const endAncestor = this.findAncestor(range.endContainer, tagName, ed);
            if (startAncestor && endAncestor && startAncestor === endAncestor) {
                return true;
            }

            // Active across non-contiguous fragments only if all visible selected text nodes carry the format
            try {
                const walker = document.createTreeWalker(
                    range.commonAncestorContainer.nodeType === Node.TEXT_NODE ? range.commonAncestorContainer.parentNode : range.commonAncestorContainer,
                    NodeFilter.SHOW_TEXT,
                    null,
                    false
                );
                let textNode;
                let hasText = false;
                while ((textNode = walker.nextNode())) {
                    if (!range.intersectsNode(textNode)) continue;
                    const cleaned = (textNode.nodeValue || '').replace(/[\s\u00A0\u200B\u200C\u200D\uFEFF]/g, '');
                    if (cleaned.length === 0) continue;
                    hasText = true;
                    if (!this.findAncestor(textNode, tagName, ed)) {
                        return false;
                    }
                }
                return hasText;
            } catch (e) {
                return !!(startAncestor && endAncestor);
            }
        },

        formatInline(formatType) {
            if (!SelectionManager.restore()) return;
            const targetTag = INLINE_FORMAT_MAP[formatType.toLowerCase()] || formatType.toUpperCase();
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;

            const ed = DOM.getEditor();
            const range = sel.getRangeAt(0);
            const currBlock = DOM.getClosestBlock(range.startContainer, ed) || ed;

            // Caret collapsed: toggle state for subsequent typing cleanly without affecting surrounding text
            if (range.collapsed) {
                const ancestors = this.getMatchingAncestors(range.startContainer, targetTag, currBlock);
                if (ancestors.length > 0) {
                    const outerAnc = ancestors[ancestors.length - 1];
                    if (!this.hasVisibleInlineContent(outerAnc)) {
                        // Empty formatted placeholder: remove the tag directly
                        const textNode = document.createTextNode('\u200B');
                        outerAnc.parentNode.insertBefore(textNode, outerAnc);
                        outerAnc.parentNode.removeChild(outerAnc);
                        const newRange = document.createRange();
                        newRange.setStart(textNode, 1);
                        newRange.collapse(true);
                        sel.removeAllRanges();
                        sel.addRange(newRange);
                    } else {
                        // Split formatted tag at caret so unformatted typing continues cleanly in place
                        let postFrag = null;
                        try {
                            const postRange = document.createRange();
                            postRange.setStart(range.endContainer, range.endOffset);
                            postRange.setEnd(outerAnc, outerAnc.childNodes.length);
                            postFrag = postRange.extractContents();
                        } catch (e) { }

                        const textNode = document.createTextNode('\u200B');
                        const parent = outerAnc.parentNode;
                        if (parent) {
                            if (this.hasVisibleInlineContent(outerAnc)) {
                                parent.insertBefore(textNode, outerAnc.nextSibling);
                            } else {
                                parent.insertBefore(textNode, outerAnc);
                                parent.removeChild(outerAnc);
                            }
                            if (postFrag && this.hasVisibleInlineContent(postFrag)) {
                                const postWrap = outerAnc.cloneNode(false);
                                postWrap.appendChild(postFrag);
                                parent.insertBefore(postWrap, textNode.nextSibling);
                            }
                        }

                        const newRange = document.createRange();
                        newRange.setStart(textNode, 1);
                        newRange.collapse(true);
                        sel.removeAllRanges();
                        sel.addRange(newRange);
                    }
                } else {
                    // Turn on format: insert tag with zero-width placeholder
                    const el = document.createElement(targetTag);
                    el.appendChild(document.createTextNode('\u200B'));
                    range.insertNode(el);
                    SelectionManager.setCursorToStart(el);
                }

                DOM.cleanEmptyInline(currBlock, true);
                SelectionManager.save();
                VisualEngine.updateActiveButtons();
                if (typeof saveToHistory === 'function') saveToHistory();
                return;
            }

            // Selection active
            const isActive = this.isFormatActive(range, targetTag);

            if (isActive) {
                // Split & unwrap format strictly within the selected range
                this.removeFormat(range, targetTag);
            } else {
                // Apply format to selection
                try {
                    const contents = range.extractContents();
                    this.unwrapMatchingDescendants(contents, targetTag);

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

            this.mergeAdjacentInline(currBlock, targetTag);
            DOM.cleanEmptyInline(ed, true);
            SelectionManager.save();
            VisualEngine.updateActiveButtons();
            if (typeof saveToHistory === 'function') saveToHistory();
        },

        removeFormat(range, targetTag) {
            const ed = DOM.getEditor();
            if (!ed || !range) return;
            const sel = window.getSelection();

            const selectNodeSlice = (nodes) => {
                if (!sel || !nodes || nodes.length === 0) return;
                const visible = nodes.filter(n => n.parentNode);
                if (visible.length === 0) return;
                const newRange = document.createRange();
                newRange.setStartBefore(visible[0]);
                newRange.setEndAfter(visible[visible.length - 1]);
                sel.removeAllRanges();
                sel.addRange(newRange);
            };

            try {
                // 1. Selection sits strictly inside one or more matching ancestor tags: split around selection
                const commonAncestors = this.getMatchingAncestors(range.commonAncestorContainer, targetTag, ed);
                if (commonAncestors.length > 0) {
                    const outerAnc = commonAncestors[commonAncestors.length - 1];

                    const postRange = document.createRange();
                    postRange.setStart(range.endContainer, range.endOffset);
                    postRange.setEnd(outerAnc, outerAnc.childNodes.length);
                    const postFrag = postRange.extractContents();

                    const midFrag = range.extractContents();
                    this.unwrapMatchingDescendants(midFrag, targetTag);
                    const midNodes = Array.from(midFrag.childNodes);

                    const parent = outerAnc.parentNode;
                    if (parent) {
                        const insertAnchor = this.hasVisibleInlineContent(outerAnc) ? outerAnc.nextSibling : outerAnc;
                        parent.insertBefore(midFrag, insertAnchor);
                        if (!this.hasVisibleInlineContent(outerAnc)) {
                            parent.removeChild(outerAnc);
                        }
                        if (this.hasVisibleInlineContent(postFrag)) {
                            const postWrap = outerAnc.cloneNode(false);
                            postWrap.appendChild(postFrag);
                            const lastMid = midNodes[midNodes.length - 1];
                            if (lastMid && lastMid.parentNode === parent) {
                                parent.insertBefore(postWrap, lastMid.nextSibling);
                            } else {
                                parent.appendChild(postWrap);
                            }
                        }
                    }
                    selectNodeSlice(midNodes);
                    return;
                }

                // 2. Selection crosses boundaries of separate matching tags: extract selection and preserve unhighlighted ends
                const startAncestors = this.getMatchingAncestors(range.startContainer, targetTag, ed);
                if (startAncestors.length > 0) {
                    const startOuter = startAncestors[startAncestors.length - 1];
                    const tailRange = document.createRange();
                    tailRange.setStart(range.startContainer, range.startOffset);
                    tailRange.setEnd(startOuter, startOuter.childNodes.length);
                    const tailFrag = tailRange.extractContents();
                    this.unwrapMatchingDescendants(tailFrag, targetTag);
                    const tailNodes = Array.from(tailFrag.childNodes);
                    if (tailNodes.length > 0 && startOuter.parentNode) {
                        startOuter.parentNode.insertBefore(tailFrag, startOuter.nextSibling);
                        range.setStartBefore(tailNodes[0]);
                    } else {
                        range.setStartAfter(startOuter);
                    }
                    if (!this.hasVisibleInlineContent(startOuter) && startOuter.parentNode) {
                        startOuter.parentNode.removeChild(startOuter);
                    }
                }

                const endAncestors = this.getMatchingAncestors(range.endContainer, targetTag, ed);
                if (endAncestors.length > 0) {
                    const endOuter = endAncestors[endAncestors.length - 1];
                    const headRange = document.createRange();
                    headRange.setStart(endOuter, 0);
                    headRange.setEnd(range.endContainer, range.endOffset);
                    const headFrag = headRange.extractContents();
                    this.unwrapMatchingDescendants(headFrag, targetTag);
                    const headNodes = Array.from(headFrag.childNodes);
                    if (headNodes.length > 0 && endOuter.parentNode) {
                        endOuter.parentNode.insertBefore(headFrag, endOuter);
                        range.setEndAfter(headNodes[headNodes.length - 1]);
                    } else {
                        range.setEndBefore(endOuter);
                    }
                    if (!this.hasVisibleInlineContent(endOuter) && endOuter.parentNode) {
                        endOuter.parentNode.removeChild(endOuter);
                    }
                }

                const contents = range.extractContents();
                this.unwrapMatchingDescendants(contents, targetTag);
                const extractedNodes = Array.from(contents.childNodes);
                range.insertNode(contents);
                selectNodeSlice(extractedNodes);
            } catch (e) {
                console.warn('Inline format removal error:', e);
            }
        },

        stripStyleProp(el, styleProp) {
            if (!el || !el.style) return;
            el.style[styleProp] = '';
            const rem = el.getAttribute('style');
            if (!rem || rem.trim() === '' || rem.trim() === ';') {
                el.removeAttribute('style');
            }
            if (el.attributes.length === 0 && el.parentNode) {
                const parent = el.parentNode;
                while (el.firstChild) parent.insertBefore(el.firstChild, el);
                parent.removeChild(el);
            }
        },

        hasVisibleInlineContent(root) {
            if (!root) return false;
            if (root.querySelector && root.querySelector('img, video, audio, iframe, table, svg, canvas, hr, [data-npblog-anchor], [data-formula]')) {
                return true;
            }
            const cleaned = (root.textContent || '').replace(/[\u200B\u200C\u200D\uFEFF]/g, '');
            return cleaned.length > 0;
        },

        getStyledSpanAncestors(node, styleProp, boundary) {
            const list = [];
            let curr = (node && node.nodeType === Node.TEXT_NODE) ? node.parentNode : node;
            while (curr && curr !== boundary && !DOM.isBlock(curr)) {
                if (curr.nodeType === Node.ELEMENT_NODE && curr.tagName === 'SPAN' && curr.style && curr.style[styleProp]) {
                    list.push(curr);
                }
                curr = curr.parentNode;
            }
            return list;
        },

        applyInlineStyle(styleProp, value) {
            if (!SelectionManager.restore()) return;
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;

            const ed = DOM.getEditor();
            const range = sel.getRangeAt(0);
            const currBlock = DOM.getClosestBlock(range.startContainer, ed) || ed;

            const cleanStyles = (root) => {
                if (!root || !root.querySelectorAll) return;
                root.querySelectorAll('span').forEach(el => {
                    this.stripStyleProp(el, styleProp);
                });
            };

            if (range.collapsed) {
                const ancestors = this.getStyledSpanAncestors(range.startContainer, styleProp, currBlock);
                if (ancestors.length > 0) {
                    const outerSpan = ancestors[ancestors.length - 1];
                    if (!this.hasVisibleInlineContent(outerSpan)) {
                        // Empty or zero-width-only span: update in place instead of nesting a child span whose outer font-size keeps inflating line-height
                        cleanStyles(outerSpan);
                        outerSpan.style[styleProp] = value;
                        const textOnly = (outerSpan.textContent || '').replace(/[\u200B\u200C\u200D\uFEFF]/g, '');
                        if (textOnly.length === 0 && !outerSpan.querySelector('img, video, audio, iframe, table, svg, canvas')) {
                            outerSpan.innerHTML = '';
                            outerSpan.appendChild(document.createTextNode('\u200B'));
                        }
                        SelectionManager.setCursorToStart(outerSpan);
                    } else {
                        // Split the styled ancestor at caret so the new size becomes a flat sibling, never trapped inside an old larger wrapper
                        let postFrag = null;
                        try {
                            const postRange = document.createRange();
                            postRange.setStart(range.endContainer, range.endOffset);
                            postRange.setEnd(outerSpan, outerSpan.childNodes.length);
                            postFrag = postRange.extractContents();
                        } catch (e) { }

                        const span = document.createElement('span');
                        if (outerSpan.style.cssText) span.style.cssText = outerSpan.style.cssText;
                        span.style[styleProp] = value;
                        span.appendChild(document.createTextNode('\u200B'));

                        const refParent = outerSpan.parentNode;
                        if (refParent) {
                            const hasPre = this.hasVisibleInlineContent(outerSpan);
                            if (hasPre) {
                                refParent.insertBefore(span, outerSpan.nextSibling);
                            } else {
                                refParent.insertBefore(span, outerSpan);
                                refParent.removeChild(outerSpan);
                            }
                            if (postFrag && this.hasVisibleInlineContent(postFrag)) {
                                const postSpan = outerSpan.cloneNode(false);
                                postSpan.appendChild(postFrag);
                                refParent.insertBefore(postSpan, span.nextSibling);
                            }
                        }
                        SelectionManager.setCursorToStart(span);
                    }
                } else {
                    const span = document.createElement('span');
                    span.style[styleProp] = value;
                    span.appendChild(document.createTextNode('\u200B'));
                    range.insertNode(span);
                    SelectionManager.setCursorToStart(span);
                }

                DOM.cleanEmptyInline(currBlock, true);
                SelectionManager.save();
                VisualEngine.updateActiveButtons();
                if (typeof saveToHistory === 'function') saveToHistory();
                return;
            }

            try {
                // 1. If selection boundaries start or end midway through an existing styled span without spanning its full container, split those edge spans
                const startAncestors = this.getStyledSpanAncestors(range.startContainer, styleProp, ed);
                for (let i = startAncestors.length - 1; i >= 0; i--) {
                    const anc = startAncestors[i];
                    if (anc.contains(range.endContainer)) break;
                    const tailRange = document.createRange();
                    tailRange.setStart(range.startContainer, range.startOffset);
                    tailRange.setEnd(anc, anc.childNodes.length);
                    const tailFrag = tailRange.extractContents();
                    if (this.hasVisibleInlineContent(tailFrag)) {
                        const tailSpan = anc.cloneNode(false);
                        tailSpan.appendChild(tailFrag);
                        anc.parentNode.insertBefore(tailSpan, anc.nextSibling);
                        range.setStartBefore(tailSpan);
                    } else {
                        range.setStartAfter(anc);
                    }
                    if (!this.hasVisibleInlineContent(anc) && anc.parentNode) {
                        anc.parentNode.removeChild(anc);
                    }
                    break;
                }

                const endAncestors = this.getStyledSpanAncestors(range.endContainer, styleProp, ed);
                for (let i = endAncestors.length - 1; i >= 0; i--) {
                    const anc = endAncestors[i];
                    if (anc.contains(range.startContainer)) break;
                    const headRange = document.createRange();
                    headRange.setStart(anc, 0);
                    headRange.setEnd(range.endContainer, range.endOffset);
                    const headFrag = headRange.extractContents();
                    if (this.hasVisibleInlineContent(headFrag)) {
                        const headSpan = anc.cloneNode(false);
                        headSpan.appendChild(headFrag);
                        anc.parentNode.insertBefore(headSpan, anc);
                        range.setEndAfter(headSpan);
                    } else {
                        range.setEndBefore(anc);
                    }
                    if (!this.hasVisibleInlineContent(anc) && anc.parentNode) {
                        anc.parentNode.removeChild(anc);
                    }
                    break;
                }

                // 2. If the whole selection sits inside a styled span (e.g. selecting text after applying 32px, then changing to 14px), split/replace it flatly instead of nesting
                const commonAncestors = this.getStyledSpanAncestors(range.commonAncestorContainer, styleProp, ed);
                if (commonAncestors.length > 0) {
                    const outerSpan = commonAncestors[commonAncestors.length - 1];

                    const postRange = document.createRange();
                    postRange.setStart(range.endContainer, range.endOffset);
                    postRange.setEnd(outerSpan, outerSpan.childNodes.length);
                    const postFrag = postRange.extractContents();

                    const midFrag = range.extractContents();
                    cleanStyles(midFrag);

                    const span = document.createElement('span');
                    if (outerSpan.style.cssText) span.style.cssText = outerSpan.style.cssText;
                    span.style[styleProp] = value;
                    span.appendChild(midFrag);

                    const parent = outerSpan.parentNode;
                    if (parent) {
                        if (this.hasVisibleInlineContent(outerSpan)) {
                            parent.insertBefore(span, outerSpan.nextSibling);
                        } else {
                            parent.insertBefore(span, outerSpan);
                            parent.removeChild(outerSpan);
                        }
                        if (this.hasVisibleInlineContent(postFrag)) {
                            const postSpan = outerSpan.cloneNode(false);
                            postSpan.appendChild(postFrag);
                            parent.insertBefore(postSpan, span.nextSibling);
                        }
                    }

                    const newRange = document.createRange();
                    newRange.selectNodeContents(span);
                    sel.removeAllRanges();
                    sel.addRange(newRange);
                } else {
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
                }
            } catch (e) {
                console.warn('Style application error:', e);
            }

            DOM.cleanEmptyInline(ed, true);
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

            const ed = DOM.getEditor();
            const range = sel.getRangeAt(0);
            const block = DOM.getClosestBlock(range.startContainer, ed);
            const targetTag = tag.toUpperCase();
            const isHeadingTarget = /^H[1-6]$/.test(targetTag);

            const stripFontSizes = (root) => {
                if (!root) return;
                if (root.style && root.style.fontSize) {
                    InlineFormatter.stripStyleProp(root, 'fontSize');
                }
                root.querySelectorAll('span, [style]').forEach(el => {
                    if (el.tagName === 'SPAN') {
                        InlineFormatter.stripStyleProp(el, 'fontSize');
                    } else if (el.style && el.style.fontSize) {
                        el.style.fontSize = '';
                        const rem = el.getAttribute('style');
                        if (!rem || rem.trim() === '' || rem.trim() === ';') el.removeAttribute('style');
                    }
                });
            };

            if (block && block !== ed && block.id !== 'contentVisual') {
                if (block.tagName.toUpperCase() === targetTag) {
                    // Toggle back to P
                    const p = document.createElement('p');
                    p.innerHTML = block.innerHTML;
                    block.parentNode.replaceChild(p, block);
                    if (DOM.isEmpty(p)) {
                        SelectionManager.setCursorToStart(p);
                    } else {
                        SelectionManager.setCursorToEnd(p);
                    }
                } else {
                    const newBlock = document.createElement(targetTag);
                    newBlock.innerHTML = block.innerHTML;
                    if (isHeadingTarget) {
                        stripFontSizes(newBlock);
                    }
                    if (DOM.isEmpty(newBlock) && !newBlock.querySelector('img, video, audio, iframe, hr, table')) {
                        newBlock.innerHTML = '<br>';
                    }
                    block.parentNode.replaceChild(newBlock, block);
                    if (DOM.isEmpty(newBlock)) {
                        SelectionManager.setCursorToStart(newBlock);
                    } else {
                        SelectionManager.setCursorToEnd(newBlock);
                    }
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

        /**
         * Inserts or removes tab indentation in the code/markdown textarea (#content).
         */
        handleTextareaTab(e, ta) {
            if (!ta || e.ctrlKey || e.metaKey || e.altKey) return false;
            e.preventDefault();

            const start = ta.selectionStart || 0;
            const end = ta.selectionEnd || 0;
            const val = ta.value || '';

            const unindentLine = (line) => {
                if (line.startsWith('\t')) return { text: line.slice(1), removed: 1 };
                const m = line.match(/^( {1,4})/);
                if (m) return { text: line.slice(m[1].length), removed: m[1].length };
                return { text: line, removed: 0 };
            };

            const hasMultiline = val.slice(start, end).includes('\n');
            if (e.shiftKey) {
                const lineStart = val.lastIndexOf('\n', start - 1) + 1;
                if (hasMultiline) {
                    const blockEndIdx = val.indexOf('\n', end);
                    const blockEnd = blockEndIdx === -1 ? val.length : blockEndIdx;
                    const lines = val.slice(lineStart, blockEnd).split('\n');
                    let totalRemoved = 0;
                    let firstRemoved = 0;
                    const updated = lines.map((line, idx) => {
                        const res = unindentLine(line);
                        if (idx === 0) firstRemoved = res.removed;
                        totalRemoved += res.removed;
                        return res.text;
                    }).join('\n');
                    ta.setRangeText(updated, lineStart, blockEnd, 'preserve');
                    ta.selectionStart = Math.max(lineStart, start - firstRemoved);
                    ta.selectionEnd = Math.max(ta.selectionStart, end - totalRemoved);
                } else {
                    const lineEndIdx = val.indexOf('\n', start);
                    const lineEnd = lineEndIdx === -1 ? val.length : lineEndIdx;
                    const res = unindentLine(val.slice(lineStart, lineEnd));
                    if (res.removed > 0) {
                        ta.setRangeText(res.text, lineStart, lineEnd, 'preserve');
                        const nextPos = Math.max(lineStart, start - res.removed);
                        ta.selectionStart = nextPos;
                        ta.selectionEnd = Math.max(nextPos, end - res.removed);
                    }
                }
            } else if (hasMultiline) {
                const lineStart = val.lastIndexOf('\n', start - 1) + 1;
                const blockEndIdx = val.indexOf('\n', end);
                const blockEnd = blockEndIdx === -1 ? val.length : blockEndIdx;
                const lines = val.slice(lineStart, blockEnd).split('\n');
                const updated = lines.map(l => '\t' + l).join('\n');
                ta.setRangeText(updated, lineStart, blockEnd, 'preserve');
                ta.selectionStart = start + 1;
                ta.selectionEnd = end + lines.length;
            } else {
                ta.setRangeText('\t', start, end, 'end');
            }

            ta.dispatchEvent(new Event('input', { bubbles: true }));
            if (typeof saveToHistory === 'function') saveToHistory();
            if (typeof markEditorDirty === 'function') markEditorDirty();
            if (typeof updateAutosaveBadge === 'function') updateAutosaveBadge();
            return true;
        },

        /**
         * Handles Tab/Shift+Tab inside visual mode: navigates table cells or inserts/removes 4-nbsp tab stops.
         */
        handleTab(e) {
            const editor = DOM.getEditor();
            if (!editor || (typeof editorMode !== 'undefined' && editorMode !== 'visual')) return false;
            if (e.ctrlKey || e.metaKey || e.altKey) return false;

            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) return false;

            const range = sel.getRangeAt(0);
            if (!DOM.isInside(range.startContainer, editor)) return false;

            const startEl = range.startContainer.nodeType === Node.ELEMENT_NODE
                ? range.startContainer
                : range.startContainer.parentNode;
            const cell = startEl && startEl.closest ? startEl.closest('td, th') : null;

            if (cell && DOM.isInside(cell, editor)) {
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

            e.preventDefault();
            const TAB_UNIT = '\u00A0\u00A0\u00A0\u00A0';

            const getBlocksInRange = () => {
                const startBlock = DOM.getClosestBlock(range.startContainer, editor);
                const endBlock = DOM.getClosestBlock(range.endContainer, editor);
                if (!startBlock || startBlock === editor) return [];
                if (!endBlock || startBlock === endBlock) return [startBlock];
                const blocks = [];
                let started = false;
                Array.from(editor.children).forEach(child => {
                    if (child === startBlock || child.contains(startBlock)) started = true;
                    if (started) {
                        if (BLOCK_TAGS.has(child.tagName.toUpperCase())) blocks.push(child);
                    }
                    if (child === endBlock || child.contains(endBlock)) started = false;
                });
                return blocks.length ? blocks : [startBlock];
            };

            const prependIndentToBlock = (block) => {
                if (!block) return;
                if (block.innerHTML === '<br>' || block.innerHTML === '<br/>') {
                    block.innerHTML = '';
                }
                const first = block.firstChild;
                if (first && first.nodeType === Node.TEXT_NODE) {
                    first.insertData(0, TAB_UNIT);
                } else {
                    block.insertBefore(document.createTextNode(TAB_UNIT), first);
                }
            };

            const stripIndentFromBlockStart = (block) => {
                if (!block) return 0;
                let removed = 0;
                const walker = document.createTreeWalker(block, NodeFilter.SHOW_TEXT, null, false);
                let node;
                while ((node = walker.nextNode()) && removed < 4) {
                    if (node.parentElement && node.parentElement.closest('.npblog-formula, .npblog-formula-block, .katex, math')) continue;
                    const text = node.nodeValue || '';
                    if (/^[\u200B\u200C\u200D\uFEFF]+$/.test(text)) continue;
                    const m = text.match(/^([\u00A0 \t]{1,4})/);
                    if (m) {
                        const cut = Math.min(m[1].length, 4 - removed);
                        node.deleteData(0, cut);
                        removed += cut;
                        break;
                    }
                    if (text.length > 0) break;
                }
                if (DOM.isEmpty(block) && !block.querySelector('img, video, audio, iframe, hr, table')) {
                    block.innerHTML = '<br>';
                }
                return removed;
            };

            if (e.shiftKey) {
                if (range.collapsed) {
                    const sc = range.startContainer;
                    let removedHere = 0;
                    if (sc.nodeType === Node.TEXT_NODE && range.startOffset > 0) {
                        const before = sc.nodeValue.slice(0, range.startOffset);
                        const m = before.match(/([\u00A0 \t]{1,4})$/);
                        if (m) {
                            const cut = m[1].length;
                            const delStart = range.startOffset - cut;
                            sc.deleteData(delStart, cut);
                            const nr = document.createRange();
                            nr.setStart(sc, delStart);
                            nr.collapse(true);
                            sel.removeAllRanges();
                            sel.addRange(nr);
                            removedHere = cut;
                        }
                    }
                    if (removedHere === 0) {
                        const block = DOM.getClosestBlock(sc, editor);
                        if (block && block !== editor) {
                            stripIndentFromBlockStart(block);
                        }
                    }
                } else {
                    getBlocksInRange().forEach(b => stripIndentFromBlockStart(b));
                }
            } else {
                const startBlock = DOM.getClosestBlock(range.startContainer, editor);
                const endBlock = DOM.getClosestBlock(range.endContainer, editor);
                if (!range.collapsed && startBlock && endBlock && startBlock !== endBlock) {
                    getBlocksInRange().forEach(b => prependIndentToBlock(b));
                } else {
                    if (!range.collapsed) {
                        range.deleteContents();
                    }
                    const sc = range.startContainer;
                    if (sc.nodeType === Node.TEXT_NODE) {
                        let offset = range.startOffset;
                        const val = sc.nodeValue || '';
                        if (/^[\u200B\u200C\u200D\uFEFF]$/.test(val)) {
                            sc.nodeValue = TAB_UNIT;
                            offset = TAB_UNIT.length;
                        } else {
                            sc.insertData(offset, TAB_UNIT);
                            offset += TAB_UNIT.length;
                        }
                        const nr = document.createRange();
                        nr.setStart(sc, offset);
                        nr.collapse(true);
                        sel.removeAllRanges();
                        sel.addRange(nr);
                    } else {
                        if (sc.nodeType === Node.ELEMENT_NODE && sc.childNodes.length === 1 && sc.firstChild.tagName === 'BR') {
                            const tabNode = document.createTextNode(TAB_UNIT);
                            sc.insertBefore(tabNode, sc.firstChild);
                            const nr = document.createRange();
                            nr.setStart(tabNode, TAB_UNIT.length);
                            nr.collapse(true);
                            sel.removeAllRanges();
                            sel.addRange(nr);
                        } else {
                            const tabNode = document.createTextNode(TAB_UNIT);
                            range.insertNode(tabNode);
                            const nr = document.createRange();
                            nr.setStart(tabNode, TAB_UNIT.length);
                            nr.collapse(true);
                            sel.removeAllRanges();
                            sel.addRange(nr);
                        }
                    }
                }
            }

            SelectionManager.save();
            VisualEngine.updateActiveButtons();
            if (typeof saveToHistory === 'function') saveToHistory();
            if (typeof markEditorDirty === 'function') markEditorDirty();
            if (typeof updateAutosaveBadge === 'function') updateAutosaveBadge();
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
                let fontSize = '';
                let currentColor = '';
                let check = (node.nodeType === Node.TEXT_NODE) ? node.parentNode : node;

                while (check && check !== editor) {
                    if (check.nodeType === Node.ELEMENT_NODE) {
                        if (check.style && check.style.fontFamily && fontName === 'Arial') {
                            fontName = check.style.fontFamily.split(',')[0].replace(/['"]/g, '').trim();
                        }
                        if (check.style && check.style.fontSize && !fontSize) {
                            fontSize = check.style.fontSize;
                        }
                        if (check.style && check.style.color && !currentColor) {
                            currentColor = check.style.color;
                        }
                    }
                    check = check.parentNode;
                }

                if (!fontSize) {
                    if (block && /^H[1-6]$/.test(block.tagName)) {
                        const computedPx = parseFloat(window.getComputedStyle(block).fontSize);
                        fontSize = (!isNaN(computedPx) && computedPx > 0) ? (Math.round(computedPx) + 'px') : '14px';
                    } else {
                        fontSize = '14px';
                    }
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

    /**
     * Позиционирует плавающий индикатор активной вкладки внутри сегментированного переключателя.
     * При первом рендере или смене размеров/перевода перемещает без анимации (is-instant),
     * чтобы избежать визуального скачка; при клике пользователя — плавно скользит.
     *
     * @param {HTMLElement|string|null} container Контейнер переключателя или его ID
     * @param {string} indicatorSelector Селектор элемента-индикатора
     * @param {string} activeSelector Селектор активной кнопки
     * @param {boolean} [immediate=false] Отключить переход при расчете позиции
     */
    window.syncSegmentedIndicator = function (container, indicatorSelector, activeSelector, immediate) {
        const root = typeof container === 'string' ? document.getElementById(container) : container;
        if (!root) return;
        const indicator = root.querySelector(indicatorSelector);
        if (!indicator) return;

        const activeBtn = root.querySelector(activeSelector);
        if (!activeBtn || root.offsetParent === null || activeBtn.offsetWidth === 0) {
            indicator.style.opacity = '0';
            delete indicator.dataset.ready;
            return;
        }

        const shouldSnap = immediate === true || indicator.dataset.ready !== '1';
        if (shouldSnap) {
            indicator.classList.add('is-instant');
        }

        indicator.style.width = activeBtn.offsetWidth + 'px';
        indicator.style.height = activeBtn.offsetHeight + 'px';
        indicator.style.transform = 'translate3d(' + activeBtn.offsetLeft + 'px, ' + activeBtn.offsetTop + 'px, 0)';
        indicator.style.opacity = '1';

        if (shouldSnap) {
            // Принудительный reflow нужен, чтобы снятие is-instant не анимировало стартовую позицию
            void indicator.offsetWidth;
            indicator.classList.remove('is-instant');
            indicator.dataset.ready = '1';
        }
    };

    /**
     * Обновляет позицию индикатора переключателя режимов редактора на нижней панели.
     *
     * @param {boolean} [immediate=false] Отключить переход при расчете позиции
     */
    window.updateModeToggleIndicator = function (immediate) {
        window.syncSegmentedIndicator('bottomModeToggle', '.mode-toggle-indicator', '.format-btn.active', immediate);
    };

    /**
     * Обновляет позицию индикатора вкладок секции параметров («Страница блога»).
     *
     * @param {boolean} [immediate=false] Отключить переход при расчете позиции
     */
    window.updateGlobalSubnavIndicator = function (immediate) {
        document.querySelectorAll('.global-subnav').forEach(function (subnav) {
            window.syncSegmentedIndicator(subnav, '.global-subnav-indicator', '.global-subnav-btn.active', immediate);
        });
    };

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
            if (visualBtn) {
                visualBtn.classList.add('active');
                visualBtn.setAttribute('aria-selected', 'true');
            }
            if (codeBtn) {
                codeBtn.classList.remove('active');
                codeBtn.setAttribute('aria-selected', 'false');
            }
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
            if (codeBtn) {
                codeBtn.classList.add('active');
                codeBtn.setAttribute('aria-selected', 'true');
            }
            if (visualBtn) {
                visualBtn.classList.remove('active');
                visualBtn.setAttribute('aria-selected', 'false');
            }
        }
        window.updateModeToggleIndicator(false);
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

        // Keyboard Handling (Code/Markdown Textarea Tab support)
        const contentTextarea = document.getElementById('content');
        if (contentTextarea) {
            contentTextarea.addEventListener('keydown', function (e) {
                if (e.key === 'Tab') {
                    KeyHandler.handleTextareaTab(e, contentTextarea);
                }
            });
        }

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

        // Синхронизируем индикаторы сегментированных переключателей при изменении шрифтов/перевода/размеров
        function bindSegmentedIndicatorObserver(root, syncFn, buttonSelector) {
            if (!root) return;
            syncFn(true);
            if (typeof ResizeObserver === 'function') {
                const observer = new ResizeObserver(function () {
                    syncFn(true);
                });
                observer.observe(root);
                root.querySelectorAll(buttonSelector).forEach(function (btn) {
                    observer.observe(btn);
                });
            }
        }

        bindSegmentedIndicatorObserver(
            document.getElementById('bottomModeToggle'),
            window.updateModeToggleIndicator,
            '.format-btn'
        );
        document.querySelectorAll('.global-subnav').forEach(function (subnav) {
            bindSegmentedIndicatorObserver(subnav, window.updateGlobalSubnavIndicator, '.global-subnav-btn');
        });
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function () {
                window.updateModeToggleIndicator(true);
                window.updateGlobalSubnavIndicator(true);
            });
        }
    });

})(window, document);
