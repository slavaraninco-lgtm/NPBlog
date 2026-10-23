// Run with Playwright available through NODE_PATH. All uploads are mocked.
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    try {
        const page = await browser.newPage();
        await page.route('http://editor.test/**', route => route.fulfill({ body: '<div id="contentVisual" contenteditable="true" style="padding:30px;width:600px;min-height:400px"></div><textarea id="content"></textarea>' }));
        await page.goto('http://editor.test/');
        await page.evaluate(() => {
            window.editorMode = 'visual';
            window.showNotification = () => {};
            window.getMediaRequestHeaders = () => ({});
            window.escapeHtml = value => {
                const el = document.createElement('span');
                el.textContent = value;
                return el.innerHTML;
            };
            window.pending = [];
            window.fetch = () => new Promise(resolve => pending.push(resolve));
        });
        const root = path.join(__dirname, '..');
        await page.addScriptTag({ path: path.join(root, 'editorjs/formatting.js') });
        const source = fs.readFileSync(path.join(root, 'editorjs/media.js'), 'utf8');
        await page.addScriptTag({ content: source.slice(source.indexOf('function initEditorMediaDragDrop()'), source.indexOf('function renderGridPreview()')) });
        await page.evaluate(() => {
            window.wrapImageWithHint = html => '<div class="blog-image-align-wrap">' + html + '</div>';
            initEditorMediaDragDrop();
        });

        // Split formatted text at the explicit range, preserving heading semantics.
        const split = await page.evaluate(() => {
            const ed = document.getElementById('contentVisual');
            ed.innerHTML = '<h2 id="heading"><strong>beforeafter</strong></h2><p>tail</p>';
            const range = document.createRange();
            range.setStart(ed.querySelector('strong').firstChild, 6);
            range.collapse(true);
            VisualEngine.insertBlockMedia('<div class="media-upload-placeholder">image</div>', range);
            return Array.from(ed.children, el => [el.tagName, el.textContent, el.id]);
        });
        assert.deepEqual(split, [['H2', 'before', 'heading'], ['DIV', 'image', ''], ['H2', 'after', ''], ['P', 'tail', '']]);

        // Drop two files over existing media; complete uploads in reverse order.
        await page.evaluate(() => {
            const ed = document.getElementById('contentVisual');
            ed.innerHTML = '<p>before</p><div class="blog-image-align-wrap" style="height:100px">existing</div><p>after</p>';
            const media = ed.children[1];
            const rect = media.getBoundingClientRect();
            const dt = new DataTransfer();
            dt.items.add(new File(['a'], 'a.png', { type: 'image/png' }));
            dt.items.add(new File(['b'], 'b.png', { type: 'image/png' }));
            media.dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, clientX: rect.left + 10, clientY: rect.bottom - 5, dataTransfer: dt }));
        });
        assert.equal(await page.locator('#contentVisual > .media-upload-placeholder').count(), 2);
        await page.evaluate(async () => {
            pending[1]({ text: async () => JSON.stringify({ success: true, url: 'b.png' }) });
            await new Promise(resolve => setTimeout(resolve, 0));
            pending[0]({ text: async () => JSON.stringify({ success: true, url: 'a.png' }) });
            await new Promise(resolve => setTimeout(resolve, 0));
        });
        assert.deepEqual(await page.locator('#contentVisual img').evaluateAll(imgs => imgs.map(img => img.getAttribute('src'))), ['a.png', 'b.png']);
        assert.equal(await page.locator('#contentVisual > p').last().textContent(), 'after');

        // A second drop after typing uses the caret captured before external drag.
        // Pointer coordinates may be stale while crossing an existing image.
        await page.evaluate(() => {
            const ed = document.getElementById('contentVisual');
            ed.innerHTML = '<p>first</p><div class="blog-image-align-wrap" style="height:100px">existing</div><p>typed text</p>';
            ed.focus();
            const text = ed.lastElementChild.firstChild;
            const selection = window.getSelection();
            const caret = document.createRange();
            caret.setStart(text, text.length);
            caret.collapse(true);
            selection.removeAllRanges();
            selection.addRange(caret);

            const dt = new DataTransfer();
            dt.items.add(new File(['d'], 'd.png', { type: 'image/png' }));
            const firstRect = ed.firstElementChild.getBoundingClientRect();
            ed.dispatchEvent(new DragEvent('dragenter', { bubbles: true, cancelable: true, dataTransfer: dt }));
            ed.dispatchEvent(new DragEvent('drop', {
                bubbles: true,
                cancelable: true,
                clientX: firstRect.left + 5,
                clientY: firstRect.top + 5,
                dataTransfer: dt
            }));
        });
        await page.evaluate(async () => {
            pending[2]({ text: async () => JSON.stringify({ success: true, url: 'd.png' }) });
            await new Promise(resolve => setTimeout(resolve, 0));
        });
        assert.deepEqual(
            await page.locator('#contentVisual').evaluate(ed => Array.from(ed.children, el => el.textContent || el.querySelector('img')?.getAttribute('src') || '').slice(0, 4)),
            ['first', 'existing', 'typed text', 'd.png']
        );
        assert.equal(await page.locator('#contentVisual > :nth-child(4) img').getAttribute('src'), 'd.png');

        // A late response must not insert into another article after placeholders disappear.
        await page.evaluate(async () => {
            const ed = document.getElementById('contentVisual');
            const rect = ed.getBoundingClientRect();
            const dt = new DataTransfer();
            dt.items.add(new File(['c'], 'c.png', { type: 'image/png' }));
            ed.dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, clientX: rect.left + 5, clientY: rect.bottom - 5, dataTransfer: dt }));
            ed.innerHTML = '<p>another article</p>';
            pending[3]({ text: async () => JSON.stringify({ success: true, url: 'c.png' }) });
            await new Promise(resolve => setTimeout(resolve, 0));
        });
        assert.equal(await page.locator('#contentVisual').innerHTML(), '<p>another article</p>');
        console.log('PASS: formatted split, atomic batch drop, second caret drop, reverse completion, discarded late response');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
