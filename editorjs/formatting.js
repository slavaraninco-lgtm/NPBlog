/**
 * NPBlog Visual Editing Engine 2.0
 * Fully rewritten visual editing engine for maximum stability, predictable block manipulation,
 * robust key handling (Enter, Backspace, Delete), clean inline formatting, and safe HTML serialization.
 */

(function (window, document) {
    'use strict';

    // Global state
    let linkInsertStart = 0;
    let linkInsertEnd = 0;
    let colorInsertStart = 0;
    let colorInsertEnd = 0;
    let savedRange = null;

    window.savedRange = savedRange;
    window.linkInsertStart = linkInsertStart;
    window.linkInsertEnd = linkInsertEnd;
    window.colorInsertStart = colorInsertStart;
    window.colorInsertEnd = colorInsertEnd;

    const BLOCK_TAGS = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'PRE', 'UL', 'OL', 'LI', 'DIV', 'TABLE', 'DETAILS', 'SECTION', 'ARTICLE', 'HEADER', 'FOOTER', 'HR'];
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
        'code': 'CODE'
    };

    /**
     * Core VisualEngine Object
     */
    const VisualEngine = {
        getEditor() {
            return document.getElementById('contentVisual');
        },

        getCodeEditor() {
            return document.getElementById('content');
        },

        isEditorActive() {
            return typeof editorMode !== 'undefined' && editorMode === 'visual';
        },

        isInsideEditor(node) {
            const editor = this.getEditor();
            if (!editor || !node) return false;
            return editor.contains(node) || editor === node;
        },

        isSelectionInEditor() {
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return false;
            const range = sel.getRangeAt(0);
            return this.isInsideEditor(range.commonAncestorContainer);
        },

        restoreFocus() {
            const editor = this.getEditor();
            if (!editor) return false;

            editor.focus();
            const sel = window.getSelection();
            if (!sel) return false;

            if (savedRange && this.isInsideEditor(savedRange.commonAncestorContainer)) {
                try {
                    sel.removeAllRanges();
                    sel.addRange(savedRange);
                    return true;
                } catch (e) { }
            }

            if (this.isSelectionInEditor()) {
                return true;
            }

            // Fallback to end of editor
            this.setCursorToEnd(editor);
            this.saveSelection();
            return true;
        },

        saveSelection() {
            const editor = this.getEditor();
            if (!editor) return;
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;

            const range = sel.getRangeAt(0);
            if (this.isInsideEditor(range.commonAncestorContainer)) {
                savedRange = range.cloneRange();
                window.savedRange = savedRange;
            }
        },

        setCursorToEnd(container) {
            const editor = this.getEditor();
            if (!editor) return;
            const target = container || editor;
            const range = document.createRange();
            range.selectNodeContents(target);
            range.collapse(false);
            const sel = window.getSelection();
            if (sel) {
                sel.removeAllRanges();
                sel.addRange(range);
            }
        },

        setCursorToStart(container) {
            const range = document.createRange();
            range.selectNodeContents(container);
            range.collapse(true);
            const sel = window.getSelection();
            if (sel) {
                sel.removeAllRanges();
                sel.addRange(range);
            }
        },

        getClosestBlock(node) {
            const editor = this.getEditor();
            let current = node;
            if (current && current.nodeType === Node.TEXT_NODE) {
                current = current.parentNode;
            }
            while (current && current !== editor) {
                if (current.nodeType === Node.ELEMENT_NODE) {
                    const tag = current.tagName.toUpperCase();
                    if (BLOCK_TAGS.includes(tag) || current.classList.contains('blog-image-align-wrap')) {
                        return current;
                    }
                }
                current = current.parentNode;
            }
            return null;
        },

        /**
         * Normalize DOM: ensure every piece of text is wrapped in a valid block container
         */
        normalizeDOM(root) {
            const editor = root || this.getEditor();
            if (!editor) return;

            // 1. If editor is completely empty or only contains whitespace
            if (!editor.hasChildNodes() || editor.innerHTML.trim() === '' || editor.innerHTML === '<br>') {
                editor.innerHTML = '<p><br></p>';
                return;
            }

            // 2. Wrap orphan inline elements or loose text nodes into <p>
            const children = Array.from(editor.childNodes);
            let inlineGroup = [];

            function flushInlineGroup() {
                if (inlineGroup.length === 0) return;
                const p = document.createElement('p');
                editor.insertBefore(p, inlineGroup[0]);
                inlineGroup.forEach(node => p.appendChild(node));
                if (p.innerHTML.trim() === '') {
                    p.innerHTML = '<br>';
                }
                inlineGroup = [];
            }

            children.forEach(node => {
                if (node.nodeType === Node.TEXT_NODE) {
                    if (node.textContent.trim() !== '') {
                        inlineGroup.push(node);
                    } else if (inlineGroup.length > 0) {
                        inlineGroup.push(node);
                    } else {
                        editor.removeChild(node);
                    }
                } else if (node.nodeType === Node.ELEMENT_NODE) {
                    const tag = node.tagName.toUpperCase();
                    if (tag === 'BR') {
                        inlineGroup.push(node);
                        flushInlineGroup();
                    } else if (!BLOCK_TAGS.includes(tag) && !node.classList.contains('blog-image-align-wrap')) {
                        inlineGroup.push(node);
                    } else {
                        flushInlineGroup();
                    }
                }
            });
            flushInlineGroup();

            // 3. Ensure empty blocks contain at least a <br>
            editor.querySelectorAll('p, h1, h2, h3, h4, h5, h6, blockquote, li').forEach(block => {
                if (block.innerHTML.trim() === '' && !block.querySelector('img, video, audio, iframe')) {
                    block.innerHTML = '<br>';
                }
            });
        },

        /**
         * Handle Enter Key (Deterministic paragraph and block division)
         */
        handleEnterKey(e) {
            if (!this.isEditorActive()) return;

            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) return;

            // Shift+Enter: Insert standard <br>
            if (e.shiftKey) {
                e.preventDefault();
                const range = sel.getRangeAt(0);
                range.deleteContents();
                const br = document.createElement('br');
                range.insertNode(br);
                range.setStartAfter(br);
                range.collapse(true);
                sel.removeAllRanges();
                sel.addRange(range);
                this.saveSelection();
                if (typeof saveToHistory === 'function') saveToHistory();
                return;
            }

            const range = sel.getRangeAt(0);
            const editor = this.getEditor();
            let block = this.getClosestBlock(range.startContainer);

            // Pre-formatted code blocks: insert real newline
            if (block && block.tagName === 'PRE') {
                e.preventDefault();
                const textNode = document.createTextNode('\n');
                range.insertNode(textNode);
                range.setStartAfter(textNode);
                range.collapse(true);
                sel.removeAllRanges();
                sel.addRange(range);
                this.saveSelection();
                if (typeof saveToHistory === 'function') saveToHistory();
                return;
            }

            // List items
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
                        if (list.children.length === 0) {
                            list.parentNode.removeChild(list);
                        }
                    } else {
                        editor.appendChild(newP);
                    }

                    this.setCursorToStart(newP);
                    this.saveSelection();
                    if (typeof saveToHistory === 'function') saveToHistory();
                    return;
                }
                // Non-empty list item -> default browser behavior handles LI cleanly
                return;
            }

            // Headings (H1 - H6)
            if (block && /^H[1-6]$/.test(block.tagName)) {
                e.preventDefault();
                const isAtEnd = this.isCaretAtEndOfBlock(block, range);
                const isAtStart = this.isCaretAtStartOfBlock(block, range);
                const isBlockEmpty = block.textContent.trim() === '';

                if (isBlockEmpty) {
                    // Empty heading -> convert to paragraph
                    const newP = document.createElement('p');
                    newP.innerHTML = '<br>';
                    block.parentNode.replaceChild(newP, block);
                    this.setCursorToStart(newP);
                } else if (isAtEnd) {
                    // Enter at the end of heading -> start new paragraph below
                    const newP = document.createElement('p');
                    newP.innerHTML = '<br>';
                    block.parentNode.insertBefore(newP, block.nextSibling);
                    this.setCursorToStart(newP);
                } else if (isAtStart) {
                    // Enter at the start of heading -> insert empty paragraph above
                    const newP = document.createElement('p');
                    newP.innerHTML = '<br>';
                    block.parentNode.insertBefore(newP, block);
                } else {
                    // Split heading into two headings
                    const afterRange = document.createRange();
                    afterRange.setStart(range.endContainer, range.endOffset);
                    afterRange.setEndAfter(block.lastChild || block);
                    const afterContent = afterRange.extractContents();

                    const newH = document.createElement(block.tagName);
                    newH.appendChild(afterContent);
                    if (newH.innerHTML.trim() === '') newH.innerHTML = '<br>';
                    if (block.innerHTML.trim() === '') block.innerHTML = '<br>';

                    block.parentNode.insertBefore(newH, block.nextSibling);
                    this.setCursorToStart(newH);
                }

                this.saveSelection();
                if (typeof saveToHistory === 'function') saveToHistory();
                return;
            }

            // Blockquote escape on empty line
            if (block && block.tagName === 'BLOCKQUOTE') {
                if (block.textContent.trim() === '') {
                    e.preventDefault();
                    const newP = document.createElement('p');
                    newP.innerHTML = '<br>';
                    block.parentNode.replaceChild(newP, block);
                    this.setCursorToStart(newP);
                    this.saveSelection();
                    if (typeof saveToHistory === 'function') saveToHistory();
                    return;
                }
            }

            // Standard Block Split (<p>, <div>, etc.)
            e.preventDefault();
            if (!block || block === editor) {
                this.normalizeDOM(editor);
                block = this.getClosestBlock(range.startContainer) || editor.firstElementChild;
            }

            if (block && block !== editor) {
                range.deleteContents();

                const afterRange = document.createRange();
                afterRange.setStart(range.endContainer, range.endOffset);
                afterRange.setEndAfter(block.lastChild || block);

                let afterContent;
                try {
                    afterContent = afterRange.extractContents();
                } catch (err) {
                    afterContent = document.createDocumentFragment();
                }

                const newP = document.createElement('p');
                if (!afterContent.hasChildNodes() || (afterContent.childNodes.length === 1 && afterContent.textContent === '')) {
                    newP.innerHTML = '<br>';
                } else {
                    newP.appendChild(afterContent);
                }

                block.parentNode.insertBefore(newP, block.nextSibling);

                if (block.innerHTML.trim() === '' && !block.querySelector('img, video, audio, iframe')) {
                    block.innerHTML = '<br>';
                }

                this.setCursorToStart(newP);
            } else {
                const newP = document.createElement('p');
                newP.innerHTML = '<br>';
                editor.appendChild(newP);
                this.setCursorToStart(newP);
            }

            this.saveSelection();
            if (typeof saveToHistory === 'function') saveToHistory();
        },

        /**
         * Handle Backspace & Delete keys (Clean block merges & atomic embed deletions)
         */
        handleBackspaceDeleteKey(e) {
            if (!this.isEditorActive()) return;
            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) return;

            const range = sel.getRangeAt(0);
            if (!range.collapsed) return; // Browser handles text range deletion cleanly

            const isBackspace = (e.key === 'Backspace');
            const editor = this.getEditor();
            const block = this.getClosestBlock(range.startContainer);

            if (!block || block === editor) return;

            // 1. Backspace at start of block
            if (isBackspace && this.isCaretAtStartOfBlock(block, range)) {
                const prevSibling = block.previousElementSibling;

                if (prevSibling) {
                    e.preventDefault();

                    // If previous sibling is an atomic media wrapper or table
                    if (prevSibling.classList.contains('blog-image-align-wrap') || prevSibling.tagName === 'TABLE' || prevSibling.tagName === 'HR') {
                        if (block.textContent.trim() === '' && !block.querySelector('img, video, audio, iframe')) {
                            block.parentNode.removeChild(block);
                        }
                        prevSibling.parentNode.removeChild(prevSibling);
                        this.normalizeDOM(editor);
                        this.saveSelection();
                        if (typeof saveToHistory === 'function') saveToHistory();
                        return;
                    }

                    // Merge current block into previous block
                    const isCurrentEmpty = (block.textContent.trim() === '' && !block.querySelector('img, video, audio, iframe'));
                    if (isCurrentEmpty) {
                        this.setCursorToEnd(prevSibling);
                        block.parentNode.removeChild(block);
                    } else {
                        const marker = document.createElement('span');
                        marker.id = 'merge-caret-temp';
                        marker.innerHTML = '\uFEFF';

                        if (prevSibling.innerHTML === '<br>') {
                            prevSibling.innerHTML = '';
                        }
                        prevSibling.appendChild(marker);

                        while (block.firstChild) {
                            if (block.firstChild.nodeName !== 'BR' || block.childNodes.length > 1) {
                                prevSibling.appendChild(block.firstChild);
                            } else {
                                block.removeChild(block.firstChild);
                            }
                        }

                        block.parentNode.removeChild(block);

                        // Position cursor at merge point
                        const newRange = document.createRange();
                        newRange.selectNode(marker);
                        newRange.collapse(true);
                        sel.removeAllRanges();
                        sel.addRange(newRange);
                        marker.parentNode.removeChild(marker);
                    }

                    this.saveSelection();
                    if (typeof saveToHistory === 'function') saveToHistory();
                    return;
                } else {
                    // First block in editor
                    if (block.tagName !== 'P') {
                        e.preventDefault();
                        const p = document.createElement('p');
                        p.innerHTML = block.innerHTML || '<br>';
                        block.parentNode.replaceChild(p, block);
                        this.setCursorToStart(p);
                        this.saveSelection();
                        if (typeof saveToHistory === 'function') saveToHistory();
                        return;
                    }
                }
            }

            // 2. Delete key at end of block
            if (!isBackspace && this.isCaretAtEndOfBlock(block, range)) {
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

                    this.saveSelection();
                    if (typeof saveToHistory === 'function') saveToHistory();
                    return;
                }
            }
        },

        isCaretAtStartOfBlock(block, range) {
            if (!block) return false;
            try {
                const checkRange = document.createRange();
                checkRange.setStart(block, 0);
                checkRange.setEnd(range.startContainer, range.startOffset);
                return checkRange.toString().length === 0;
            } catch (e) {
                return false;
            }
        },

        isCaretAtEndOfBlock(block, range) {
            if (!block) return false;
            try {
                const checkRange = document.createRange();
                checkRange.setStart(range.endContainer, range.endOffset);
                checkRange.setEndAfter(block.lastChild || block);
                return checkRange.toString().length === 0;
            } catch (e) {
                return false;
            }
        },

        /**
         * Apply Inline Formatting (Bold, Italic, Underline, Strike, etc.)
         */
        formatInline(formatType, options = {}) {
            if (!this.restoreFocus()) return;
            const targetTag = INLINE_FORMAT_MAP[formatType.toLowerCase()] || formatType.toUpperCase();
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;

            const range = sel.getRangeAt(0);

            if (range.collapsed) {
                // If collapsed, check if we are inside the tag to unwrap/escape
                const existing = this.findAncestorTag(range.startContainer, targetTag);
                if (existing) {
                    // Escape formatting tag cleanly
                    const afterRange = document.createRange();
                    afterRange.setStart(range.startContainer, range.startOffset);
                    afterRange.setEndAfter(existing.lastChild || existing);
                    const afterContent = afterRange.extractContents();

                    const newSpan = document.createElement('span');
                    newSpan.innerHTML = '&#8203;'; // temporary zero-width space for cursor hold
                    existing.parentNode.insertBefore(newSpan, existing.nextSibling);

                    if (afterContent.hasChildNodes()) {
                        const cloned = existing.cloneNode(false);
                        cloned.appendChild(afterContent);
                        newSpan.parentNode.insertBefore(cloned, newSpan.nextSibling);
                    }

                    this.setCursorToStart(newSpan);
                    this.saveSelection();
                } else {
                    // Create inline empty tag
                    const el = document.createElement(targetTag);
                    el.innerHTML = '&#8203;';
                    range.insertNode(el);
                    this.setCursorToStart(el);
                    this.saveSelection();
                }
                this.updateActiveButtons();
                if (typeof saveToHistory === 'function') saveToHistory();
                return;
            }

            // Non-collapsed selection
            const isApplied = this.isFormatAppliedToRange(range, targetTag);

            if (isApplied) {
                // Remove format from range
                this.removeFormatFromRange(range, targetTag);
            } else {
                // Apply format to range
                try {
                    const contents = range.extractContents();
                    const wrapper = document.createElement(targetTag);
                    wrapper.appendChild(contents);
                    range.insertNode(wrapper);

                    const newRange = document.createRange();
                    newRange.selectNodeContents(wrapper);
                    sel.removeAllRanges();
                    sel.addRange(newRange);
                } catch (e) {
                    console.warn('Range formatting fallback:', e);
                }
            }

            this.saveSelection();
            this.updateActiveButtons();
            if (typeof saveToHistory === 'function') saveToHistory();
        },

        findAncestorTag(node, tagName) {
            const editor = this.getEditor();
            let curr = (node.nodeType === Node.TEXT_NODE) ? node.parentNode : node;
            const target = tagName.toUpperCase();
            while (curr && curr !== editor) {
                if (curr.nodeType === Node.ELEMENT_NODE && curr.tagName.toUpperCase() === target) {
                    return curr;
                }
                curr = curr.parentNode;
            }
            return null;
        },

        isFormatAppliedToRange(range, tagName) {
            const ancestor = this.findAncestorTag(range.commonAncestorContainer, tagName);
            if (ancestor) return true;

            const startAncestor = this.findAncestorTag(range.startContainer, tagName);
            const endAncestor = this.findAncestorTag(range.endContainer, tagName);
            return !!(startAncestor && endAncestor);
        },

        removeFormatFromRange(range, tagName) {
            const target = tagName.toUpperCase();
            const editor = this.getEditor();
            const startAncestor = this.findAncestorTag(range.startContainer, target);

            if (startAncestor) {
                const parent = startAncestor.parentNode;
                while (startAncestor.firstChild) {
                    parent.insertBefore(startAncestor.firstChild, startAncestor);
                }
                parent.removeChild(startAncestor);
            }

            editor.querySelectorAll(target).forEach(el => {
                if (range.intersectsNode(el)) {
                    const parent = el.parentNode;
                    while (el.firstChild) {
                        parent.insertBefore(el.firstChild, el);
                    }
                    parent.removeChild(el);
                }
            });
        },

        /**
         * Apply Block Format (H1 - H6, P, Blockquote)
         */
        formatBlock(tag) {
            if (!this.restoreFocus()) return;
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;

            const range = sel.getRangeAt(0);
            let block = this.getClosestBlock(range.startContainer);
            const targetTag = tag.toUpperCase();

            if (block && block.id !== 'contentVisual') {
                if (block.tagName.toUpperCase() === targetTag) {
                    // Toggle back to <p>
                    const p = document.createElement('p');
                    p.innerHTML = block.innerHTML;
                    block.parentNode.replaceChild(p, block);
                    this.setCursorToEnd(p);
                } else {
                    const newBlock = document.createElement(targetTag);
                    newBlock.innerHTML = block.innerHTML;
                    block.parentNode.replaceChild(newBlock, block);
                    this.setCursorToEnd(newBlock);
                }
            } else {
                const newBlock = document.createElement(targetTag);
                newBlock.innerHTML = '<br>';
                range.insertNode(newBlock);
                this.setCursorToStart(newBlock);
            }

            this.saveSelection();
            this.updateActiveButtons();
            if (typeof saveToHistory === 'function') saveToHistory();
        },

        /**
         * Apply Text Alignment
         */
        setTextAlignment(side) {
            if (typeof editorMode !== 'undefined' && editorMode === 'code') {
                const ta = this.getCodeEditor();
                if (!ta) return;
                const start = ta.selectionStart;
                const end = ta.selectionEnd;
                const text = ta.value.substring(start, end);
                const html = `<div style="text-align: ${side};">${text || '&nbsp;'}</div>`;
                ta.value = ta.value.substring(0, start) + html + ta.value.substring(end);
                return;
            }

            if (!this.restoreFocus()) return;
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;

            const range = sel.getRangeAt(0);
            const block = this.getClosestBlock(range.startContainer);

            if (block && block.id !== 'contentVisual') {
                block.style.textAlign = side;
            } else {
                const div = document.createElement('div');
                div.style.textAlign = side;
                if (!range.collapsed) {
                    try {
                        div.appendChild(range.extractContents());
                        range.insertNode(div);
                    } catch (e) { }
                } else {
                    div.innerHTML = '<br>';
                    range.insertNode(div);
                }
            }

            this.saveSelection();
            if (typeof saveToHistory === 'function') saveToHistory();
        },

        /**
         * Apply Inline Style (Font Family, Font Size, Color)
         */
        applyInlineStyle(styleProp, value) {
            if (!this.restoreFocus()) return;
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;

            const range = sel.getRangeAt(0);

            if (range.collapsed) {
                const span = document.createElement('span');
                span.style[styleProp] = value;
                span.innerHTML = '&#8203;';
                range.insertNode(span);
                this.setCursorToStart(span);
            } else {
                try {
                    const contents = range.extractContents();
                    const span = document.createElement('span');
                    span.style[styleProp] = value;
                    span.appendChild(contents);
                    range.insertNode(span);

                    const newRange = document.createRange();
                    newRange.selectNodeContents(span);
                    sel.removeAllRanges();
                    sel.addRange(newRange);
                } catch (e) {
                    console.error('Style application error:', e);
                }
            }

            this.saveSelection();
            this.updateActiveButtons();
            if (typeof saveToHistory === 'function') saveToHistory();
        },

        /**
         * Insert Arbitrary HTML at Caret / Selection
         */
        insertHTML(html, isBlock = false) {
            if (!this.restoreFocus()) return;
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

            this.saveSelection();
            if (typeof saveToHistory === 'function') saveToHistory();
        },

        /**
         * Insert Block Media / Table / Embed with Auto-Continuation Paragraph
         */
        insertBlockMedia(html) {
            if (!this.restoreFocus()) return;
            const editor = this.getEditor();
            const sel = window.getSelection();
            let range = (sel && sel.rangeCount > 0) ? sel.getRangeAt(0) : null;

            const temp = document.createElement('div');
            temp.innerHTML = html;
            const frag = document.createDocumentFragment();
            let node, lastNode;

            while ((node = temp.firstChild)) {
                lastNode = frag.appendChild(node);
            }

            const emptyP = document.createElement('p');
            emptyP.innerHTML = '<br>';

            if (!range || !this.isInsideEditor(range.commonAncestorContainer)) {
                editor.appendChild(frag);
                editor.appendChild(emptyP);
            } else {
                range.deleteContents();
                range.insertNode(frag);
                if (lastNode && lastNode.parentNode) {
                    lastNode.parentNode.insertBefore(emptyP, lastNode.nextSibling);
                } else {
                    editor.appendChild(emptyP);
                }
            }

            this.setCursorToStart(emptyP);
            this.saveSelection();
            if (typeof saveToHistory === 'function') saveToHistory();
        },

        /**
         * Clean HTML for saving (removes runtime widgets, overlays, resizers, temporary attributes)
         */
        cleanContentForSave(html) {
            if (!html) return '';
            const temp = document.createElement('div');
            temp.innerHTML = html;

            // Remove runtime UI elements
            const elementsToRemove = temp.querySelectorAll(
                '.image-toolbar, .image-align-dropdown, .image-size-indicator, ' +
                '.image-resize-handle, .blog-image-overlay, .column-resizer, #customCaret'
            );
            elementsToRemove.forEach(el => el.parentNode && el.parentNode.removeChild(el));

            // Remove runtime data attributes
            const widgets = temp.querySelectorAll('[data-image-id], [data-media-id], [data-media-type], table[data-resizers-added]');
            widgets.forEach(el => {
                el.removeAttribute('data-image-id');
                el.removeAttribute('data-media-id');
                el.removeAttribute('data-media-type');
                el.removeAttribute('data-resizers-added');
            });

            // Remove contenteditable attributes from cells or blocks
            temp.querySelectorAll('[contenteditable]').forEach(el => el.removeAttribute('contenteditable'));

            // Remove selection markers
            temp.querySelectorAll('.selected').forEach(el => el.classList.remove('selected'));

            // Remove zero-width helper spaces
            let cleaned = temp.innerHTML.replace(/[\u200B\uFEFF]/g, '');
            cleaned = cleaned.replace(/(?:[?&]|&amp;)t=\d+/g, '');

            return this.formatHTML(cleaned);
        },

        /**
         * Tree-Based Formatter with Tag Indentation preserving <pre> blocks
         */
        formatHTML(html) {
            if (!html) return '';

            let preBlocks = [];
            let formatted = html.replace(/<pre[^>]*>[\s\S]*?<\/pre>/gi, function (match) {
                preBlocks.push(match);
                return '___PRE_BLOCK_HOLDER_' + (preBlocks.length - 1) + '___';
            });

            const blockTags = [
                'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
                'blockquote', 'section', 'article', 'header', 'footer', 'hr'
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

                if (isClosing) {
                    pad = Math.max(0, pad - 1);
                }

                result.push('    '.repeat(pad) + line);

                if (isOpening) {
                    pad++;
                }
            }

            let finalHtml = result.join('\n');
            for (let i = 0; i < preBlocks.length; i++) {
                finalHtml = finalHtml.replace('___PRE_BLOCK_HOLDER_' + i + '___', preBlocks[i]);
            }

            return finalHtml.trim();
        },

        updateActiveButtons() {
            if (typeof editorMode !== 'undefined' && editorMode !== 'visual') return;
            const editor = this.getEditor();
            const sel = window.getSelection();

            const buttonIds = ['btn-bold', 'btn-italic', 'btn-underline', 'btn-strike', 'btn-sup', 'btn-sub', 'btn-h2'];
            const setBtn = (id, active) => {
                const el = document.getElementById(id);
                if (el) el.classList.toggle('active', !!active);
            };

            if (!editor || !sel || sel.rangeCount === 0 || !this.isInsideEditor(sel.anchorNode)) {
                buttonIds.forEach(id => setBtn(id, false));
                return;
            }

            try {
                const node = sel.anchorNode;
                setBtn('btn-bold', this.findAncestorTag(node, 'STRONG') || this.findAncestorTag(node, 'B'));
                setBtn('btn-italic', this.findAncestorTag(node, 'EM') || this.findAncestorTag(node, 'I'));
                setBtn('btn-underline', this.findAncestorTag(node, 'U'));
                setBtn('btn-strike', this.findAncestorTag(node, 'S') || this.findAncestorTag(node, 'STRIKE') || this.findAncestorTag(node, 'DEL'));
                setBtn('btn-sup', this.findAncestorTag(node, 'SUP'));
                setBtn('btn-sub', this.findAncestorTag(node, 'SUB'));

                const block = this.getClosestBlock(node);
                setBtn('btn-h2', block && block.tagName === 'H2');

                // Inspect current applied font and size
                let fontName = 'Arial';
                let fontSize = '14px';
                let check = (node.nodeType === Node.TEXT_NODE) ? node.parentNode : node;

                while (check && check !== editor) {
                    if (check.nodeType === Node.ELEMENT_NODE) {
                        if (check.style && check.style.fontFamily && fontName === 'Arial') {
                            fontName = check.style.fontFamily.split(',')[0].replace(/['"]/g, '').trim();
                        }
                        if (check.style && check.style.fontSize && fontSize === '14px') {
                            fontSize = check.style.fontSize;
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
            } catch (e) { }
        },

        getSelectionOffsets(container) {
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

        setSelectionOffsets(container, start, end) {
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
            } catch (e) { }
        }
    };

    // Expose engine to window
    window.VisualEngine = VisualEngine;

    // --- Backward Compatible Global Functions ---
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
        const ta = document.getElementById('content');
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
        const listTemplate = "\n<ul>\n  <li>Пункт 1</li>\n  <li>Пункт 2</li>\n  <li>Пункт 3</li>\n</ul>\n";
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const cursorPos = ta.selectionStart;
            ta.value = ta.value.substring(0, cursorPos) + listTemplate + ta.value.substring(cursorPos);
            ta.focus();
        } else {
            VisualEngine.insertBlockMedia(listTemplate);
        }
        if (typeof saveToHistory === 'function') saveToHistory();
    };

    window.setMode = function (mode) {
        editorMode = mode;
        const ta = document.getElementById('content');
        const ve = document.getElementById('contentVisual');
        const visualBtn = document.getElementById('modeVisualBtn');
        const codeBtn = document.getElementById('modeCodeBtn');

        if (mode === 'visual') {
            ve.contentEditable = 'true';
            if (window.enableMarkdown) {
                ve.innerHTML = (typeof parseMarkdownToHtml === 'function') ? parseMarkdownToHtml(ta.value) : ta.value;
            } else {
                if (ta.style.display !== 'none') {
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
    };

    // --- Keyboard & Selection Event Listeners ---
    document.addEventListener('DOMContentLoaded', function () {
        const editor = VisualEngine.getEditor();
        if (!editor) return;

        // Toolbar focus guard
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

        // Selection tracking inside editor
        ['mouseup', 'keyup', 'input', 'click', 'touchend'].forEach(evt => {
            editor.addEventListener(evt, function () {
                if (VisualEngine.isEditorActive()) {
                    VisualEngine.saveSelection();
                    VisualEngine.updateActiveButtons();
                }
            }, true);
        });

        // Keydown handlers
        editor.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                VisualEngine.handleEnterKey(e);
            } else if (e.key === 'Backspace' || e.key === 'Delete') {
                VisualEngine.handleBackspaceDeleteKey(e);
            }
        });

        // Initial DOM normalization
        VisualEngine.normalizeDOM(editor);
    });

})(window, document);