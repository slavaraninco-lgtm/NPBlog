// --- Наборы смайлов ---
let smileFilesToUpload = [];
let smileSetNameTarget = '';

function openSmileSetsDialog() {
    if (window.Modal) {
        Modal.open('#smileSetsDialog');
    } else {
        const dlg = document.getElementById('smileSetsDialog');
        if (dlg) dlg.style.display = 'block';
    }
    loadSmileSetsList();
    resetSmileUploadState();
}

function closeSmileSetsDialog() {
    if (window.Modal) {
        Modal.close('#smileSetsDialog');
    } else {
        const dlg = document.getElementById('smileSetsDialog');
        if (dlg) dlg.style.display = 'none';
    }
    resetSmileUploadState();
}

function handleSmileDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    const zone = document.getElementById('smileDropzone');
    if (zone) {
        zone.style.borderColor = 'var(--text-color)';
        zone.style.background = 'rgba(255, 255, 255, 0.05)';
    }
}

function handleSmileDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    const zone = document.getElementById('smileDropzone');
    if (zone) {
        zone.style.borderColor = 'var(--border-color)';
        zone.style.background = 'rgba(0, 0, 0, 0.02)';
    }
}

async function handleSmileDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    const zone = document.getElementById('smileDropzone');
    if (zone) {
        zone.style.borderColor = 'var(--border-color)';
        zone.style.background = 'rgba(0, 0, 0, 0.02)';
    }

    const files = [];
    if (e.dataTransfer.items) {
        const entries = [];
        for (let i = 0; i < e.dataTransfer.items.length; i++) {
            const item = e.dataTransfer.items[i];
            if (item.kind === 'file') {
                const entry = item.webkitGetAsEntry();
                if (entry) {
                    entries.push(entry);
                }
            }
        }
        await readSmileEntries(entries, files);
    } else {
        for (let i = 0; i < e.dataTransfer.files.length; i++) {
            files.push(e.dataTransfer.files[i]);
        }
    }

    processSelectedSmiles(files);
}

async function readSmileEntries(entries, fileList) {
    for (const entry of entries) {
        if (entry.isFile) {
            const file = await new Promise((resolve) => entry.file(resolve));
            file.customRelativePath = entry.fullPath ? entry.fullPath.replace(/^\//, '') : file.name;
            fileList.push(file);
        } else if (entry.isDirectory) {
            const reader = entry.createReader();
            const childEntries = await new Promise((resolve) => {
                reader.readEntries(resolve);
            });
            await readSmileEntries(childEntries, fileList);
        }
    }
}

function handleSmileFileSelect(e) {
    const input = e.target;
    if (input && input.files.length > 0) {
        processSelectedSmiles(Array.from(input.files));
    }
}

function processSelectedSmiles(files) {
    smileFilesToUpload = files.filter(file => file.name.toLowerCase().endsWith('.gif'));

    if (smileFilesToUpload.length === 0) {
        showNotification('В выбранных файлах не найдено изображений формата .gif', 'warning');
        resetSmileUploadState();
        return;
    }

    let detectedName = '';
    const firstFile = smileFilesToUpload[0];
    const relPath = firstFile.customRelativePath || firstFile.webkitRelativePath;

    if (relPath && relPath.includes('/')) {
        detectedName = relPath.split('/')[0];
    }

    const nameField = document.getElementById('smileSetNameField');
    const nameInput = document.getElementById('smileSetNameInput');
    const infoText = document.getElementById('smileSelectedFilesInfo');
    const countSpan = document.getElementById('smileSelectedCount');
    const btnContainer = document.getElementById('smileUploadBtnContainer');

    if (detectedName) {
        smileSetNameTarget = detectedName;
        nameInput.value = detectedName;
        if (nameField) nameField.style.display = 'block';
    } else {
        smileSetNameTarget = '';
        nameInput.value = '';
        if (nameField) nameField.style.display = 'block';
    }

    if (countSpan) countSpan.textContent = smileFilesToUpload.length;
    if (infoText) infoText.style.display = 'block';
    if (btnContainer) btnContainer.style.display = 'block';

    const dropzoneText = document.getElementById('smileDropzoneText');
    if (dropzoneText) dropzoneText.textContent = window.t ? window.t('modals.smiles_files_selected_notice', 'Файлы успешно выбраны') : 'Файлы успешно выбраны';
}

function resetSmileUploadState() {
    smileFilesToUpload = [];
    smileSetNameTarget = '';

    const folderInput = document.getElementById('smileFolderInput');
    const filesInput = document.getElementById('smileFilesInput');
    const nameInput = document.getElementById('smileSetNameInput');
    const nameField = document.getElementById('smileSetNameField');
    const infoText = document.getElementById('smileSelectedFilesInfo');
    const btnContainer = document.getElementById('smileUploadBtnContainer');
    const dropzoneText = document.getElementById('smileDropzoneText');

    if (folderInput) folderInput.value = '';
    if (filesInput) filesInput.value = '';
    if (nameInput) nameInput.value = '';
    if (nameField) nameField.style.display = 'none';
    if (infoText) infoText.style.display = 'none';
    if (btnContainer) btnContainer.style.display = 'none';
    if (dropzoneText) dropzoneText.textContent = window.t ? window.t('modals.smiles_drop_text', 'Перетащите ZIP архив со смайлами сюда или кликните для выбора') : 'Перетащите ZIP архив со смайлами сюда или кликните для выбора';
}

async function loadSmileSetsList() {
    const listContainer = document.getElementById('smileSetsList');
    if (!listContainer) return;

    try {
        const response = await fetch('get_smiles.php?t=' + Date.now());
        const data = await response.json();

        if (data.success) {
            const setNames = Object.keys(data.sets);
            if (setNames.length === 0) {
                listContainer.innerHTML = '<div style="text-align: center; opacity: 0.6; padding: 10px; color: var(--text-color);">' + (window.t ? window.t('modals.smiles_no_sets', 'Нет загруженных наборов') : 'Нет загруженных наборов') + '</div>';
            } else {
                const pcsTemplate = window.t ? window.t('modals.smiles_count_pcs', '({count} шт.)') : '({count} шт.)';
                const delText = window.t ? window.t('common.delete', 'Удалить') : 'Удалить';
                listContainer.innerHTML = setNames.map(name => {
                    const count = data.sets[name].length;
                    const countFormatted = pcsTemplate.replace('{count}', count);
                    return `<div class="smile-set-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--border-color); color: var(--text-color); transition: background 0.2s;" onmouseover="this.style.background='rgba(128,128,128,0.04)'" onmouseout="this.style.background='transparent'">
                        <span class="smile-set-name" style="font-weight: 500; font-size: 14px;">${escapeHtml(name)} <span class="smile-set-count" style="font-size: 12px; opacity: 0.5; margin-left: 8px;">${countFormatted}</span></span>
                        <button type="button" class="smile-set-delete-btn" style="background: transparent; color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 6px; padding: 5px 12px; cursor: pointer; font-size: 12px; transition: all 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.1)'; this.style.borderColor='#ef4444'" onmouseout="this.style.background='transparent'; this.style.borderColor='rgba(239,68,68,0.4)'" onclick="deleteSmileSet('${escapeHtmlJS(name)}')">${delText}</button>
                    </div>`;
                }).join('');
            }
        } else {
            listContainer.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 10px;">' + (window.t ? window.t('modals.smiles_load_error', 'Ошибка загрузки списка') : 'Ошибка загрузки списка') + '</div>';
        }
    } catch (error) {
        console.error('Error loading smile sets:', error);
        listContainer.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 10px;">' + (window.t ? window.t('more_menu.network_error', 'Ошибка сети') : 'Ошибка сети') + '</div>';
    }
}

async function handleSmileSetUpload() {
    const formData = new FormData();
    let files = smileFilesToUpload;
    let setName = document.getElementById('smileSetNameInput').value.trim();

    if (files.length === 0) {
        showNotification(window.t ? window.t('notifications.smiles_select_files_or_folder', 'Выберите файлы или папку для загрузки') : 'Выберите файлы или папку для загрузки', 'warning');
        return;
    }
    if (!setName) {
        showNotification(window.t ? window.t('notifications.smiles_enter_set_name', 'Введите название для набора') : 'Введите название для набора', 'warning');
        return;
    }

    formData.append('setName', setName);
    files.forEach(file => {
        formData.append('smiles[]', file);
    });

    try {
        showNotification(window.t ? window.t('notifications.smiles_uploading', 'Загрузка смайлов...') : 'Загрузка смайлов...', 'info');
        const response = await fetch('upload_smiles.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            showNotification(window.t ? window.t('notifications.smiles_uploaded_param', `Набор "${setName}" успешно загружен (${data.count} смайлов)`, { setName: setName, count: data.count }) : `Набор "${setName}" успешно загружен (${data.count} смайлов)`, 'success');
            loadSmileSetsList();
            resetSmileUploadState();
        } else {
            showNotification(window.t ? window.t('notifications.file_upload_error_param', 'Ошибка загрузки: ' + data.error, { error: data.error }) : 'Ошибка загрузки: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error uploading smiles:', error);
        showNotification(window.t ? window.t('notifications.smiles_upload_network_error', 'Ошибка сети при загрузке набора') : 'Ошибка сети при загрузке набора', 'error');
    }
}

// Expose functions to window
window.openSmileSetsDialog = openSmileSetsDialog;
window.closeSmileSetsDialog = closeSmileSetsDialog;
window.handleSmileDragOver = handleSmileDragOver;
window.handleSmileDragLeave = handleSmileDragLeave;
window.handleSmileDrop = handleSmileDrop;
window.handleSmileFileSelect = handleSmileFileSelect;
window.handleSmileSetUpload = handleSmileSetUpload;

async function deleteSmileSet(setName) {
    showConfirm(window.t ? window.t('notifications.smiles_delete_confirm_param', `Удалить набор смайлов "${setName}"? Все файлы этого набора будут удалены.`, { setName: setName }) : `Удалить набор смайлов "${setName}"? Все файлы этого набора будут удалены.`).then(async (result) => {
        if (!result) return;

        const formData = new FormData();
        formData.append('setName', setName);

        try {
            const response = await fetch('delete_smile_set.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                showNotification(window.t ? window.t('notifications.smiles_deleted_param', `Набор "${setName}" успешно удален`, { setName: setName }) : `Набор "${setName}" успешно удален`, 'success');
                loadSmileSetsList();
            } else {
                showNotification(window.t ? window.t('notifications.file_delete_error_param', 'Ошибка удаления: ' + data.error, { error: data.error }) : 'Ошибка удаления: ' + data.error, 'error');
            }
        } catch (error) {
            console.error('Error deleting smile set:', error);
            showNotification(window.t ? window.t('notifications.smiles_delete_network_error', 'Ошибка сети при удалении набора') : 'Ошибка сети при удалении набора', 'error');
        }
    });
}

function toggleSmilesSubmenu(event) {
    event.stopPropagation();

    const button = event.currentTarget;
    const isOpen = button.classList.contains('submenu-open');

    document.querySelectorAll('.more-menu-item.has-submenu').forEach(btn => {
        if (btn !== button) {
            btn.classList.remove('submenu-open');
        }
    });

    if (!isOpen) {
        button.classList.add('submenu-open');
        loadSmilesSubmenuList();
    } else {
        button.classList.remove('submenu-open');
    }
}

async function loadSmilesSubmenuList() {
    const submenu = document.getElementById('smilesSubmenu');
    if (!submenu) return;

    try {
        const response = await fetch('get_smiles.php?t=' + Date.now());
        const data = await response.json();

        if (data.success) {
            const setNames = Object.keys(data.sets);
            const nonEmptySets = setNames.filter(name => data.sets[name].length > 0);

            if (nonEmptySets.length === 0) {
                submenu.innerHTML = '<div class="more-submenu-empty">' + (window.t ? window.t('more_menu.no_smiles_hint', 'Нет смайлов. Добавьте их через "Наборы смайлов"') : 'Нет смайлов. Добавьте их через "Наборы смайлов"') + '</div>';
            } else {
                let html = '<div class="smiles-submenu-container">';
                nonEmptySets.forEach(setName => {
                    html += `<div class="smile-set-section">
                        <div class="smile-set-title">${escapeHtml(setName)}</div>
                        <div class="smile-items-grid">`;

                    data.sets[setName].forEach(url => {
                        html += `<button type="button" class="smile-item-btn" onclick="insertSmile('${escapeHtmlJS(url)}')" title="Вставить смайл">
                            <img src="${url}" alt="smile">
                        </button>`;
                    });

                    html += `</div></div>`;
                });
                html += '</div>';
                submenu.innerHTML = html;
            }
        } else {
            submenu.innerHTML = '<div class="more-submenu-empty">' + (window.t ? window.t('more_menu.smiles_load_error', 'Ошибка загрузки смайлов') : 'Ошибка загрузки смайлов') + '</div>';
        }
    } catch (error) {
        console.error('Error loading smiles for submenu:', error);
        submenu.innerHTML = '<div class="more-submenu-empty">' + (window.t ? window.t('more_menu.network_error', 'Ошибка сети') : 'Ошибка сети') + '</div>';
    }
}

function insertSmile(url) {
    const html = `<img src="${url}" class="blog-smile" alt="smile">`;
    if (editorMode === 'code') {
        const ta = document.getElementById('content');
        const cursorPos = ta.selectionStart;
        ta.value = ta.value.substring(0, cursorPos) + html + ta.value.substring(cursorPos);
    } else {
        insertHtmlAtCaret(html);
    }
    saveToHistory();
}

function escapeHtmlJS(str) {
    return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

window.openSmileSetsDialog = openSmileSetsDialog;
window.closeSmileSetsDialog = closeSmileSetsDialog;
window.handleSmileSetUpload = handleSmileSetUpload;
window.deleteSmileSet = deleteSmileSet;
window.toggleSmilesSubmenu = toggleSmilesSubmenu;
window.insertSmile = insertSmile;
