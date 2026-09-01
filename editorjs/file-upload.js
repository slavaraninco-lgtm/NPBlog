// ——— Функции для загрузки файлов ———

function openFileUploadDialog() {
    if (window.Modal) {
        Modal.open('#fileUploadDialog');
    } else {
        const dlg = document.getElementById('fileUploadDialog');
        if (dlg) dlg.style.display = 'block';
    }

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
    if (window.Modal) {
        Modal.close('#fileUploadDialog');
    } else {
        const dlg = document.getElementById('fileUploadDialog');
        if (dlg) dlg.style.display = 'none';
    }
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
                    insertBtn.textContent = window.t ? window.t('common.insert', 'Вставить') : 'Вставить';
                    insertBtn.onclick = (e) => {
                        e.stopPropagation();
                        insertFileButton(file.name, file.url, file.size);
                    };

                    const deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.className = 'file-upload-item-btn delete';
                    deleteBtn.textContent = window.t ? window.t('common.delete', 'Удалить') : 'Удалить';
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
                listContainer.innerHTML = '<div class="file-upload-empty">' + (window.t ? window.t('modals.file_none_uploaded', 'Нет загруженных файлов') : 'Нет загруженных файлов') + '</div>';
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки списка файлов:', error);
            document.getElementById('fileUploadList').innerHTML = '<div class="file-upload-empty">' + (window.t ? window.t('modals.file_load_error', 'Ошибка загрузки списка') : 'Ошибка загрузки списка') + '</div>';
        });
}

function uploadDocument(fileToUpload = null) {
    const fileInput = document.getElementById('documentFile');
    const file = fileToUpload || (fileInput ? fileInput.files[0] : null);

    if (!file) {
        showNotification(window.t ? window.t('notifications.file_select_upload', 'Выберите файл для загрузки') : 'Выберите файл для загрузки', 'error');
        return;
    }

    // Отображаем анимацию загрузки в зоне
    const dropzone = document.getElementById('fileDropzone');
    const dropzoneText = dropzone ? dropzone.querySelector('.dropzone-text') : null;
    let originalText = '';
    if (dropzoneText) {
        originalText = dropzoneText.textContent;
        const uploadingText = window.t ? window.t('notifications.file_uploading_param', `Загрузка "${file.name}"...`, { name: file.name }) : `Загрузка "${file.name}"...`;
        dropzoneText.innerHTML = `<span class="loading-spinner"></span> ${uploadingText}`;
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
                showNotification(window.t ? window.t('notifications.file_uploaded', 'Файл успешно загружен') : 'Файл успешно загружен', 'success');
                if (fileInput) fileInput.value = '';
                const fileNameEl = document.getElementById('documentFileName');
                if (fileNameEl) {
                    fileNameEl.textContent = window.t ? window.t('modals.file_none', 'Файл не выбран') : 'Файл не выбран';
                }
                loadDocumentsList();
            } else {
                showNotification(window.t ? window.t('notifications.file_upload_error_param', 'Ошибка загрузки: ' + (data.error || 'Неизвестная ошибка'), { error: data.error || 'Неизвестная ошибка' }) : 'Ошибка загрузки: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            if (dropzoneText) {
                dropzoneText.textContent = originalText;
            }
            console.error('Ошибка:', error);
            showNotification(window.t ? window.t('notifications.file_upload_network_error', 'Ошибка загрузки файла') : 'Ошибка загрузки файла', 'error');
        });
}

function deleteDocument(filePath) {
    showConfirm(window.t ? window.t('notifications.confirm_delete_file', 'Удалить этот файл?') : 'Удалить этот файл?').then(result => {
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
                    showNotification(window.t ? window.t('notifications.file_deleted', 'Файл удален') : 'Файл удален', 'success');
                    loadDocumentsList();
                } else {
                    showNotification(window.t ? window.t('notifications.file_delete_error_param', 'Ошибка удаления: ' + (data.error || 'Неизвестная ошибка'), { error: data.error || 'Неизвестная ошибка' }) : 'Ошибка удаления: ' + (data.error || 'Неизвестная ошибка'), 'error');
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                showNotification(window.t ? window.t('notifications.file_delete_network_error', 'Ошибка удаления файла') : 'Ошибка удаления файла', 'error');
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
