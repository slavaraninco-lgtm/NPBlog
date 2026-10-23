const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    try {
        const page = await browser.newPage();
        page.on('pageerror', error => console.error('PAGE ERROR:', error.message));
        await page.setContent(`
            <meta name="csrf-token" content="test-csrf">
            <input type="radio" name="imageSource" value="file" checked>
            <select id="gridLayout"><option value="" selected></option></select>
            <select id="imageSize"><option value="medium" selected></option></select>
            <input id="insertGallery" type="checkbox">
            <input id="imageCaption" value="">
            <input id="noBorderRadius" type="checkbox">
        `);

        const source = fs.readFileSync(path.join(__dirname, '..', 'editorjs/media.js'), 'utf8');
        const helperStart = source.indexOf('function getMediaRequestHeaders()');
        const helperEnd = source.indexOf('function initImageDragDrop()', helperStart);
        const processStart = source.indexOf('function processImage()');
        const processEnd = source.indexOf('function insertImagesInGrid', processStart);
        await page.addScriptTag({ content: source.slice(helperStart, helperEnd) });
        await page.addScriptTag({ content: `
            let selectedImageFiles = [new File(['image'], 'modal.png', { type: 'image/png' })];
            let gridTileFiles = {};
            window.editorContentWidth = 920;
            window.inserted = [];
            window.modalClosed = false;
            window.notices = [];
            function insertImage(url) { inserted.push(url); }
            function insertImagesInGrid(urls) { inserted.push(...urls); }
            function insertImagesAsGallery(urls) { inserted.push(...urls); }
            function closeImageDialog() { modalClosed = true; }
            function showNotification(message, type) { notices.push({ message, type }); }
            window.fetch = async (url, options) => {
                window.requestInfo = {
                    url,
                    csrf: options.headers['X-CSRF-Token'],
                    hasImage: options.body.get('image') instanceof File
                };
                return { ok: true, status: 200, text: async () => JSON.stringify({ success: true, url: '/data/uploads/modal.png' }) };
            };
        ` + source.slice(processStart, processEnd) });

        const state = await page.evaluate(async () => {
            processImage();
            await new Promise(resolve => setTimeout(resolve, 100));
            return { closed: window.modalClosed, requestInfo: window.requestInfo, inserted: window.inserted, notices: window.notices };
        });
        assert.equal(state.closed, true, JSON.stringify(state));
        assert.deepEqual(await page.evaluate(() => window.requestInfo), {
            url: 'upload_image.php',
            csrf: 'test-csrf',
            hasImage: true
        });
        assert.deepEqual(await page.evaluate(() => window.inserted), ['/data/uploads/modal.png']);
        console.log('PASS: modal sends image field with CSRF and inserts returned URL');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
