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
    if (window.Modal) {
        Modal.open('#linkDialog');
    } else {
        const dlg = document.getElementById('linkDialog');
        if (dlg) dlg.style.display = 'block';
    }
    urlInput.focus();
    if (navigator.clipboard && navigator.clipboard.readText) {
        navigator.clipboard.readText().then(function (text) {
            if (text && (text = text.trim())) {
                if (!/^https?:\/\//i.test(text)) text = 'https://' + text.replace(/^\/+/, '');
                urlInput.value = text;
            }
        }).catch(function () { });
    }
}

function closeLinkDialog() {
    if (window.Modal) {
        Modal.close('#linkDialog');
    } else {
        const dlg = document.getElementById('linkDialog');
        if (dlg) dlg.style.display = 'none';
    }
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
            checkInsertGalleryVisibility();
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
            checkInsertGalleryVisibility();
        });
        thumbnail.appendChild(deleteBtn);
        previewContainer.appendChild(thumbnail);

        reader.onload = function (e) {
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
    if (window.Modal) {
        Modal.open('#imageUploadDialog');
    } else {
        const dlg = document.getElementById('imageUploadDialog');
        if (dlg) dlg.style.display = 'block';
    }
    initImageDragDrop();
    checkInsertGalleryVisibility();
}

let gridTileFiles = {};

document.addEventListener('DOMContentLoaded', function () {
    const gridLayoutSelect = document.getElementById('gridLayout');
    if (gridLayoutSelect) {
        gridLayoutSelect.addEventListener('change', function () {
            if (this.value) {
                const insertGalleryChk = document.getElementById('insertGallery');
                if (insertGalleryChk) insertGalleryChk.checked = false;
            }
            checkInsertGalleryVisibility();
            renderGridPreview();
        });
    }

    const insertGalleryChk = document.getElementById('insertGallery');
    if (insertGalleryChk) {
        insertGalleryChk.addEventListener('change', function () {
            if (this.checked) {
                const gridLayoutSelect = document.getElementById('gridLayout');
                if (gridLayoutSelect && gridLayoutSelect.value) {
                    gridLayoutSelect.value = '';
                    renderGridPreview();
                }
            }
        });
    }

    // Remember state of "Remove rounded corners" checkbox
    const noRadiusChk = document.getElementById('noBorderRadius');
    if (noRadiusChk) {
        noRadiusChk.checked = localStorage.getItem('noBorderRadius') === 'true';
        noRadiusChk.addEventListener('change', function () {
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
        const removeComments = function (element) {
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
    const handlePaste = function (e) {
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

window.triggerTileUpload = function (index) {
    const input = document.getElementById(`tile-file-input-${index}`);
    if (input) input.click();
};

window.clearTileImage = function (e, index) {
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

window.handleTileFileChange = function (e, index) {
    const file = e.target.files[0];
    if (!file) return;

    gridTileFiles[index] = file;

    const reader = new FileReader();
    reader.onload = function (evt) {
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
    radio.addEventListener('change', function () {
        document.querySelectorAll('input[name="imageSource"]').forEach(r => {
            r.closest('.modal-tab-btn')?.classList.toggle('is-active', r.checked);
        });
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

    const gridLayout = document.getElementById('gridLayout')?.value;

    if (gridLayout) {
        insertGalleryContainer.style.opacity = '0.4';
        insertGalleryContainer.style.pointerEvents = 'none';
        const chk = document.getElementById('insertGallery');
        if (chk) chk.checked = false;
    } else {
        insertGalleryContainer.style.opacity = '1';
        insertGalleryContainer.style.pointerEvents = 'auto';
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
        const urls = urlInput.split(/[\n,]+/).map(function (s) { return s.trim(); }).filter(Boolean);
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
    legacyElements.forEach(function (el) {
        el.parentNode.removeChild(el);
    });

    // Обрабатываем галереи отдельно
    var galleries = ve.querySelectorAll('.image-gallery');
    galleries.forEach(function (gallery) {
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
    overlay.querySelectorAll('.image-align-option').forEach(function (opt) {
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

async function showImageResizeDialog(img) {
    var isGallery = img.classList && img.classList.contains('image-gallery');
    var currentWidth = img.offsetWidth || (img.naturalWidth || img.videoWidth || 0);
    var isAudio = img.tagName.toLowerCase() === 'audio';
    var isVideo = img.tagName.toLowerCase() === 'video';
    var label = isGallery ? 'галереи' : (isAudio ? 'плеера аудио' : (isVideo ? 'плеера видео' : 'изображения'));

    var newWidth = await showPrompt('Введите новую ширину ' + label + ' (в пикселях):', currentWidth, 'Размер ' + label);
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
            galleryImages.forEach(function (image) {
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

    overlay.addEventListener('mousedown', function (e) {
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

    overlay.addEventListener('click', function (e) {
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

                overlay.querySelectorAll('.image-align-option').forEach(function (opt) {
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

    ve.addEventListener('mouseover', function (e) {
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

    ve.addEventListener('click', function (e) {
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

document.addEventListener('mousemove', function (e) {
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
                galleryImages.forEach(function (img) {
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

document.addEventListener('mouseup', function (e) {
    if (isResizingMedia) {
        isResizingMedia = false;
        document.body.style.cursor = '';
        var overlay = document.getElementById('editorGlobalMediaOverlay');
        if (overlay) overlay.classList.remove('selected');
        currentHandle = null;
    }
});

document.addEventListener('click', function (e) {
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
    ve.addEventListener('keydown', function (e) {
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
        requestAnimationFrame(function () {
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
        tableItems.forEach(function (item) {
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
                tableItems.forEach(function (item) {
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

    const onCursorMove = function (e) {
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

    contentVisual.addEventListener('input', function (e) {
        const inputType = e.inputType || 'insertText';
        const data = e.data || '';
        handleInputHistory(inputType, data);
    });

    if (contentTa) {
        contentTa.addEventListener('input', function (e) {
            const inputType = e.inputType || 'insertText';
            const data = e.data || '';
            handleInputHistory(inputType, data);
        });
    }

    const onUndoRedoShortcut = function (e) {
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
    contentVisual.addEventListener('click', function (e) {
        // Проверяем, кликнули ли мы на spoiler блок или рядом с ним
        const ve = document.getElementById('contentVisual');
        const spoilers = ve.querySelectorAll('.spoiler-block');

        spoilers.forEach(function (spoiler) {
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
    contentVisual.addEventListener('keydown', function (e) {
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

    menu.addEventListener('click', function (e) {
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
    document.addEventListener('contextmenu', function (e) {
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
    if (window.Modal) {
        Modal.close('#imageUploadDialog');
    } else {
        const dlg = document.getElementById('imageUploadDialog');
        if (dlg) dlg.style.display = 'none';
    }
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
    if (insertGalleryContainer) {
        insertGalleryContainer.style.opacity = '1';
        insertGalleryContainer.style.pointerEvents = 'auto';
    }
    const fileRadio = document.querySelector('input[name="imageSource"][value="file"]');
    if (fileRadio) fileRadio.checked = true;
    document.querySelectorAll('input[name="imageSource"]').forEach(r => {
        r.closest('.modal-tab-btn')?.classList.toggle('is-active', r.value === 'file');
    });
    const fileUploadContainer = document.getElementById('fileUploadContainer');
    if (fileUploadContainer) fileUploadContainer.style.display = 'block';
    const imageGridPreviewContainer = document.getElementById('imageGridPreviewContainer');
    if (imageGridPreviewContainer) {
        imageGridPreviewContainer.style.display = 'none';
        imageGridPreviewContainer.innerHTML = '';
    }
    const urlContainer = document.getElementById('urlContainer');
    if (urlContainer) urlContainer.style.display = 'none';
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
    if (!size) return;
    var sizeNum = parseInt(size, 10);
    if (isNaN(sizeNum) || sizeNum <= 0) return;
    var sizePx = sizeNum + 'px';

    if (editorMode === 'code') {
        var ta = document.getElementById('content');
        if (!ta) return;
        var start = (ta.selectionStart !== undefined && ta.selectionEnd > ta.selectionStart) ? ta.selectionStart : colorInsertStart;
        var end = (ta.selectionEnd !== undefined && ta.selectionEnd > ta.selectionStart) ? ta.selectionEnd : colorInsertEnd;
        var selectedText = ta.value.substring(start, end);
        if (selectedText) {
            var fontSpan = '<span style="font-size: ' + sizePx + ';">' + selectedText + '</span>';
            ta.value = ta.value.substring(0, start) + fontSpan + ta.value.substring(end);
            ta.selectionStart = start;
            ta.selectionEnd = start + fontSpan.length;
        } else {
            var fontSpan = '<span style="font-size: ' + sizePx + ';"></span>';
            ta.value = ta.value.substring(0, start) + fontSpan + ta.value.substring(start);
            ta.selectionStart = ta.selectionEnd = start + fontSpan.length - 7;
        }
        ta.focus();
        saveToHistory();
        return;
    }

    var ve = document.getElementById('contentVisual');
    if (!ve) return;
    ve.focus();

    var sel = window.getSelection();
    var range = null;

    // Приоритет выбора активного непустого выделения
    if (sel && sel.rangeCount > 0 && ve.contains(sel.getRangeAt(0).commonAncestorContainer) && !sel.getRangeAt(0).collapsed) {
        range = sel.getRangeAt(0);
        savedRange = range.cloneRange();
    } else if (savedRange && ve.contains(savedRange.commonAncestorContainer) && !savedRange.collapsed) {
        range = savedRange;
        if (sel) {
            sel.removeAllRanges();
            sel.addRange(savedRange);
        }
    } else if (sel && sel.rangeCount > 0 && ve.contains(sel.getRangeAt(0).commonAncestorContainer)) {
        range = sel.getRangeAt(0);
    } else if (savedRange && ve.contains(savedRange.commonAncestorContainer)) {
        range = savedRange;
        if (sel) {
            sel.removeAllRanges();
            sel.addRange(savedRange);
        }
    }

    if (!range) {
        var newRange = document.createRange();
        newRange.selectNodeContents(ve);
        newRange.collapse(false);
        if (sel) {
            sel.removeAllRanges();
            sel.addRange(newRange);
        }
        range = newRange;
    }

    if (range.collapsed) {
        var span = document.createElement('span');
        span.style.fontSize = sizePx;
        span.appendChild(document.createTextNode('\u200B'));
        range.insertNode(span);

        var newRange = document.createRange();
        newRange.setStart(span.firstChild, 1);
        newRange.collapse(true);
        if (sel) {
            sel.removeAllRanges();
            sel.addRange(newRange);
        }
        savedRange = newRange.cloneRange();
    } else {
        var applied = false;
        try {
            // Если всё выделение находится внутри одного span с font-size
            var parentSpan = range.commonAncestorContainer.nodeType === 1 ? range.commonAncestorContainer : range.commonAncestorContainer.parentElement;
            if (parentSpan && parentSpan !== ve && parentSpan.tagName === 'SPAN' && parentSpan.style.fontSize && range.toString() === parentSpan.textContent) {
                parentSpan.style.fontSize = sizePx;
                var newRange = document.createRange();
                newRange.selectNodeContents(parentSpan);
                if (sel) {
                    sel.removeAllRanges();
                    sel.addRange(newRange);
                }
                savedRange = newRange.cloneRange();
                applied = true;
            }
        } catch (e) { }

        if (!applied) {
            try {
                document.execCommand('styleWithCSS', false, false);
                document.execCommand('fontSize', false, '7');

                var fontElements = Array.from(ve.querySelectorAll('font[size="7"], font[size="xxx-large"]'));
                if (fontElements.length > 0) {
                    var firstSpan = null;
                    var lastSpan = null;

                    fontElements.forEach(function (fontEl) {
                        var span = document.createElement('span');
                        span.style.fontSize = sizePx;

                        while (fontEl.firstChild) {
                            span.appendChild(fontEl.firstChild);
                        }

                        span.querySelectorAll('[style*="font-size"]').forEach(function (child) {
                            child.style.fontSize = '';
                            if (!child.getAttribute('style') || child.getAttribute('style').trim() === '') {
                                child.removeAttribute('style');
                            }
                        });

                        if (!firstSpan) firstSpan = span;
                        lastSpan = span;

                        if (fontEl.parentNode) {
                            fontEl.parentNode.replaceChild(span, fontEl);
                        }
                    });

                    if (firstSpan && lastSpan) {
                        var newRange = document.createRange();
                        newRange.setStartBefore(firstSpan);
                        newRange.setEndAfter(lastSpan);
                        sel = window.getSelection();
                        if (sel) {
                            sel.removeAllRanges();
                            sel.addRange(newRange);
                        }
                        savedRange = newRange.cloneRange();
                        applied = true;
                    }
                }
            } catch (e) { }
        }

        if (!applied) {
            try {
                var contents = range.extractContents();
                var span = document.createElement('span');
                span.style.fontSize = sizePx;
                span.appendChild(contents);
                span.querySelectorAll('[style*="font-size"]').forEach(function (child) {
                    child.style.fontSize = '';
                    if (!child.getAttribute('style') || child.getAttribute('style').trim() === '') {
                        child.removeAttribute('style');
                    }
                });
                range.insertNode(span);

                var newRange = document.createRange();
                newRange.selectNodeContents(span);
                sel = window.getSelection();
                if (sel) {
                    sel.removeAllRanges();
                    sel.addRange(newRange);
                }
                savedRange = newRange.cloneRange();
            } catch (e) {
                console.error('Ошибка применения размера шрифта:', e);
            }
        }
    }

    var sizeBtn = document.getElementById('fontSizeBtn');
    if (sizeBtn) {
        sizeBtn.textContent = sizePx;
    }

    if (typeof updateActiveButtons === 'function') {
        updateActiveButtons();
    }
    saveToHistory();
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

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    };
    if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

    fetch('upload_video.php', {
        method: 'POST',
        headers: headers,
        body: formData
    })
        .then(async response => {
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                const clean = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                throw new Error(clean || `HTTP ${response.status}`);
            }
            return data;
        })
        .then(data => {
            if (data.success) {
                showNotification('Видео файл загружен', 'success');
                loadVideoFilesList();
            } else {
                showNotification('Ошибка: ' + (data.error || data.message || 'Не удалось загрузить видео'), 'error');
            }
            if (filenameEl) filenameEl.style.display = 'none';
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showNotification('Ошибка загрузки видео: ' + error.message, 'error');
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

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    };
    if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

    fetch('upload_audio.php', {
        method: 'POST',
        headers: headers,
        body: formData
    })
        .then(async response => {
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                const clean = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                throw new Error(clean || `HTTP ${response.status}`);
            }
            return data;
        })
        .then(data => {
            if (data.success) {
                showNotification('Аудио файл загружен', 'success');
                loadAudioFilesList();
            } else {
                showNotification('Ошибка: ' + (data.error || data.message || 'Не удалось загрузить аудио'), 'error');
            }
            if (filenameEl) filenameEl.style.display = 'none';
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showNotification('Ошибка загрузки файла: ' + error.message, 'error');
            if (filenameEl) filenameEl.style.display = 'none';
        });
}

window.handleMediaFileChange = function (input, type) {
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
    if (window.Modal) {
        Modal.open('#mediaDialog');
    } else {
        const dlg = document.getElementById('mediaDialog');
        if (dlg) dlg.style.display = 'block';
    }
    initMediaDragDrop();

    const mediaTypeRadios = document.querySelectorAll('input[name="mediaType"]');
    mediaTypeRadios.forEach(radio => {
        radio.addEventListener('change', function () {
            document.querySelectorAll('input[name="mediaType"]').forEach(r => {
                r.closest('.modal-tab-btn')?.classList.toggle('is-active', r.checked);
            });
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
    if (window.Modal) {
        Modal.close('#mediaDialog');
    } else {
        const dlg = document.getElementById('mediaDialog');
        if (dlg) dlg.style.display = 'none';
    }
    document.getElementById('mediaUrl').value = '';
    document.getElementById('videoFile').value = '';
    document.getElementById('audioFile').value = '';
    document.getElementById('audioStreamUrl').value = '';
    // Сбрасываем на видео URL
    const videoRadio = document.querySelector('input[name="mediaType"][value="video-url"]');
    if (videoRadio) videoRadio.checked = true;
    document.querySelectorAll('input[name="mediaType"]').forEach(r => {
        r.closest('.modal-tab-btn')?.classList.toggle('is-active', r.value === 'video-url');
    });
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

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    };
    if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

    fetch('upload_audio.php', {
        method: 'POST',
        headers: headers,
        body: formData
    })
        .then(async response => {
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                const clean = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                throw new Error(clean || `HTTP ${response.status}`);
            }
            return data;
        })
        .then(data => {
            if (data.success) {
                showNotification('Аудио файл загружен', 'success');
                fileInput.value = '';
                loadAudioFilesList();
            } else {
                showNotification('Ошибка: ' + (data.error || data.message || 'Не удалось загрузить аудио'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showNotification('Ошибка загрузки аудио: ' + error.message, 'error');
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

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    };
    if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

    fetch('upload_video.php', {
        method: 'POST',
        headers: headers,
        body: formData
    })
        .then(async response => {
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                const clean = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                throw new Error(clean || `HTTP ${response.status}`);
            }
            return data;
        })
        .then(data => {
            if (data.success) {
                showNotification('Видео файл загружен', 'success');
                fileInput.value = '';
                loadVideoFilesList();
            } else {
                showNotification('Ошибка: ' + (data.error || data.message || 'Не удалось загрузить видео'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showNotification('Ошибка загрузки видео: ' + error.message, 'error');
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

    if (window.Modal) {
        Modal.open('#spoilerDialog');
    } else {
        const dlg = document.getElementById('spoilerDialog');
        if (dlg) dlg.style.display = 'block';
    }
    const titleInput = document.getElementById('spoilerTitle');
    if (titleInput) {
        titleInput.value = '';
        titleInput.focus();
    }
}

function closeSpoilerDialog() {
    if (window.Modal) {
        Modal.close('#spoilerDialog');
    } else {
        const dlg = document.getElementById('spoilerDialog');
        if (dlg) dlg.style.display = 'none';
    }
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

    if (window.Modal) {
        Modal.open('#markerDialog');
    } else {
        const dlg = document.getElementById('markerDialog');
        if (dlg) dlg.style.display = 'block';
    }

    // Добавляем обработчики на кнопки стилей
    const styleBtns = document.querySelectorAll('.marker-style-btn');
    styleBtns.forEach(btn => {
        btn.onclick = function () {
            styleBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            selectedMarkerStyle = this.getAttribute('data-style');
        };
    });

    // Добавляем обработчики на кнопки цветов
    const colorBtns = document.querySelectorAll('.marker-color-btn');
    colorBtns.forEach(btn => {
        btn.onclick = function () {
            const color = this.getAttribute('data-color');
            insertMarker(color, selectedMarkerStyle);
        };
    });
}

function closeMarkerDialog() {
    if (window.Modal) {
        Modal.close('#markerDialog');
    } else {
        const dlg = document.getElementById('markerDialog');
        if (dlg) dlg.style.display = 'none';
    }
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
    if (titleEl) titleEl.textContent = window.t ? window.t('modals.code_title', 'Вставить код') : 'Вставить код';
    const submitEl = document.getElementById('codeDialogSubmitBtn');
    if (submitEl) submitEl.textContent = window.t ? window.t('modals.code_insert_btn', 'Вставить') : 'Вставить';
    const langEl = document.getElementById('codeLanguage');
    if (langEl) langEl.value = 'javascript';
    const inputEl = document.getElementById('codeInput');
    if (inputEl) inputEl.value = '';
    if (window.Modal) {
        Modal.open('#codeDialog');
    } else {
        const dlg = document.getElementById('codeDialog');
        if (dlg) dlg.style.display = 'block';
    }
    if (inputEl) inputEl.focus();
}

function openEditCodeBlockDialog(codeBlock) {
    editingCodeBlockTarget = codeBlock;
    const titleEl = document.getElementById('codeDialogTitle');
    if (titleEl) titleEl.textContent = window.t ? window.t('modals.code_edit_title', 'Редактировать код') : 'Редактировать код';
    const submitEl = document.getElementById('codeDialogSubmitBtn');
    if (submitEl) submitEl.textContent = window.t ? window.t('common.save', 'Сохранить') : 'Сохранить';

    const lang = codeBlock.getAttribute('data-language') || 'javascript';
    const langEl = document.getElementById('codeLanguage');
    if (langEl) langEl.value = lang;
    const inputEl = document.getElementById('codeInput');
    if (inputEl) inputEl.value = codeBlock.textContent;
    if (window.Modal) {
        Modal.open('#codeDialog');
    } else {
        const dlg = document.getElementById('codeDialog');
        if (dlg) dlg.style.display = 'block';
    }
    if (inputEl) inputEl.focus();
}

function closeCodeDialog() {
    if (window.Modal) {
        Modal.close('#codeDialog');
    } else {
        const dlg = document.getElementById('codeDialog');
        if (dlg) dlg.style.display = 'none';
    }
    const inputEl = document.getElementById('codeInput');
    if (inputEl) inputEl.value = '';
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
function insertImageGrid(layout) {
    const [cols, rows] = layout.split('x').map(Number);
    const gridStyle = `display: grid; grid-template-columns: repeat(${cols}, 1fr); gap: 10px;`;
    let imagesHTML = '';

    for (let i = 0; i < cols * rows; i++) {
        // Плейсхолдер для добавления реальных изображений
        imagesHTML += `<img src="" alt="Изображение ${i + 1}" style="width: 100%; height: auto;">`;
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
(function () {
    var sentinel = document.getElementById('formatBarSentinel');
    var placeholder = document.getElementById('formatBarPlaceholder');
    var formatBar = document.getElementById('formatBarRow');
    var floatingSaveBtn = document.getElementById('floatingSaveBtn');
    var submitButton = document.getElementById('submitButton');
    if (!sentinel || !placeholder || !formatBar) return;
    var stickyObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
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
document.addEventListener('selectionchange', function () {
    if (editorMode === 'visual') {
        saveSelection();
    }
    updateActiveButtons();
});
