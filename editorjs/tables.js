/**
 * NPBlog - Tables Engine
 * Provides table insertion, row/column management, cell coloring, and column resizing.
 */

// Helper: Get active table cell from context menu target or current selection
function getActiveTableCell() {
    if (window.contextMenuTableCell && document.contains(window.contextMenuTableCell)) {
        return window.contextMenuTableCell;
    }
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0) {
        const node = sel.getRangeAt(0).startContainer;
        const cell = (node.nodeType === Node.ELEMENT_NODE ? node : node.parentNode).closest('td, th');
        if (cell) {
            const ve = document.getElementById('contentVisual');
            if (ve && ve.contains(cell)) {
                window.contextMenuTableCell = cell;
                window.contextMenuTableRow = cell.closest('tr');
                return cell;
            }
        }
    }
    return null;
}

// Helper: Get active table row from context menu target or current selection
function getActiveTableRow() {
    if (window.contextMenuTableRow && document.contains(window.contextMenuTableRow)) {
        return window.contextMenuTableRow;
    }
    const cell = getActiveTableCell();
    if (cell) {
        return cell.closest('tr');
    }
    return null;
}

function openTableDialog() {
    if (window.Modal) {
        Modal.open('#tableDialog');
    } else {
        const dlg = document.getElementById('tableDialog');
        if (dlg) dlg.style.display = 'block';
    }
    const rowsInput = document.getElementById('tableRows');
    if (rowsInput) rowsInput.focus();
}

function closeTableDialog() {
    if (window.Modal) {
        Modal.close('#tableDialog');
    } else {
        const dlg = document.getElementById('tableDialog');
        if (dlg) dlg.style.display = 'none';
    }
    const rowsInput = document.getElementById('tableRows');
    if (rowsInput) rowsInput.value = '3';
    const colsInput = document.getElementById('tableCols');
    if (colsInput) colsInput.value = '3';
}

function addTableRow() {
    const row = getActiveTableRow();
    if (!row) {
        showNotification(window.t ? window.t('notifications.select_table_cell', 'Выберите ячейку таблицы') : 'Выберите ячейку таблицы', 'warning');
        return;
    }

    const table = row.closest('table');
    if (!table) return;

    // Count columns from header or current row
    const headerCells = table.querySelectorAll('thead th, thead td, tr:first-child th, tr:first-child td');
    const colCount = headerCells.length || row.querySelectorAll('td, th').length || 3;

    const newRow = document.createElement('tr');
    for (let i = 0; i < colCount; i++) {
        const cell = document.createElement('td');
        cell.innerHTML = '<br>';
        cell.contentEditable = 'true';
        newRow.appendChild(cell);
    }

    // Insert new row
    if (row.parentNode && row.parentNode.tagName === 'THEAD') {
        let tbody = table.querySelector('tbody');
        if (!tbody) {
            tbody = document.createElement('tbody');
            table.appendChild(tbody);
        }
        if (tbody.firstChild) {
            tbody.insertBefore(newRow, tbody.firstChild);
        } else {
            tbody.appendChild(newRow);
        }
    } else {
        row.parentNode.insertBefore(newRow, row.nextSibling);
    }

    // Position caret in first cell of newly added row
    if (newRow.firstElementChild && window.VisualEngine) {
        window.VisualEngine.setCursorToStart(newRow.firstElementChild);
    }

    saveToHistory();
    showNotification('Строка добавлена', 'success');
}

function deleteTableRow() {
    const row = getActiveTableRow();
    if (!row) {
        showNotification(window.t ? window.t('notifications.select_table_cell', 'Выберите ячейку таблицы') : 'Выберите ячейку таблицы', 'warning');
        return;
    }

    const table = row.closest('table');
    if (!table) return;

    if (row.parentNode && row.parentNode.tagName === 'THEAD') {
        showNotification('Нельзя удалить строку заголовка', 'warning');
        return;
    }

    const tbody = table.querySelector('tbody');
    const bodyRows = tbody ? tbody.querySelectorAll('tr') : table.querySelectorAll('tr');
    if (bodyRows.length <= 1) {
        showNotification('Нельзя удалить последнюю строку таблицы', 'warning');
        return;
    }

    row.parentNode.removeChild(row);
    saveToHistory();
    showNotification('Строка удалена', 'success');
}

function addTableColumn() {
    const cell = getActiveTableCell();
    if (!cell) {
        showNotification(window.t ? window.t('notifications.select_table_cell', 'Выберите ячейку таблицы') : 'Выберите ячейку таблицы', 'warning');
        return;
    }

    const table = cell.closest('table');
    if (!table) return;

    // Determine column index of the active cell
    const row = cell.closest('tr');
    const cellsInRow = Array.from(row.querySelectorAll('td, th'));
    const colIndex = cellsInRow.indexOf(cell);
    if (colIndex === -1) return;

    // 1. Locate or create thead and headerRow
    let thead = table.querySelector('thead');
    let headerRow = thead ? thead.querySelector('tr') : null;

    if (!headerRow) {
        const firstRow = table.querySelector('tr');
        if (firstRow) {
            headerRow = firstRow;
        }
    }

    let newHeader = null;
    if (headerRow) {
        const headerCells = Array.from(headerRow.querySelectorAll('th, td'));
        if (headerCells.length >= 12) {
            showNotification('Достигнуто максимальное количество столбцов (12)', 'warning');
            return;
        }

        const isTh = headerCells.length > 0 && headerCells[0].tagName === 'TH';
        newHeader = document.createElement(isTh ? 'th' : 'td');
        newHeader.innerHTML = 'Заголовок ' + (headerCells.length + 1);
        newHeader.contentEditable = 'true';

        if (colIndex + 1 < headerCells.length) {
            headerRow.insertBefore(newHeader, headerCells[colIndex + 1]);
        } else {
            headerRow.appendChild(newHeader);
        }
    }

    // 2. Add cell to all body rows
    const tbody = table.querySelector('tbody');
    const allRows = Array.from(table.querySelectorAll('tr'));
    const bodyRows = tbody ? Array.from(tbody.querySelectorAll('tr')) : allRows.filter(r => r !== headerRow);

    let newBodyCellToFocus = null;
    bodyRows.forEach(function (bodyRow) {
        const bodyCells = Array.from(bodyRow.querySelectorAll('td, th'));
        const newCell = document.createElement('td');
        newCell.innerHTML = '<br>';
        newCell.contentEditable = 'true';

        if (colIndex + 1 < bodyCells.length) {
            bodyRow.insertBefore(newCell, bodyCells[colIndex + 1]);
        } else {
            bodyRow.appendChild(newCell);
        }

        if (bodyRow === row) {
            newBodyCellToFocus = newCell;
        }
    });

    // 3. Redistribute column widths evenly across headers
    if (headerRow) {
        const updatedHeaderCells = Array.from(headerRow.querySelectorAll('th, td'));
        const newColWidth = (100 / updatedHeaderCells.length).toFixed(4) + '%';
        updatedHeaderCells.forEach(th => {
            th.style.width = newColWidth;
        });

        // Clear redundant inline widths from body cells so table-layout: fixed respects headers
        table.querySelectorAll('tbody td, tbody th').forEach(td => {
            td.style.width = '';
        });
    }

    // 4. Force recreation of resizers
    table.querySelectorAll('.column-resizer').forEach(r => r.remove());
    delete table.dataset.resizersAdded;
    addColumnResizers();

    // 5. Position cursor in newly added cell
    const targetFocus = (cell.tagName === 'TH' && newHeader) ? newHeader : (newBodyCellToFocus || newHeader);
    if (targetFocus && window.VisualEngine) {
        window.VisualEngine.setCursorToStart(targetFocus);
    }

    saveToHistory();
    showNotification('Столбец добавлен', 'success');
}

function deleteTableColumn() {
    const cell = getActiveTableCell();
    if (!cell) {
        showNotification(window.t ? window.t('notifications.select_table_cell', 'Выберите ячейку таблицы') : 'Выберите ячейку таблицы', 'warning');
        return;
    }

    const table = cell.closest('table');
    if (!table) return;

    const row = cell.closest('tr');
    const cellsInRow = Array.from(row.querySelectorAll('td, th'));
    const colIndex = cellsInRow.indexOf(cell);
    if (colIndex === -1) return;

    // Check minimum columns
    const thead = table.querySelector('thead');
    const headerRow = thead ? thead.querySelector('tr') : table.querySelector('tr');
    const headerCells = headerRow ? Array.from(headerRow.querySelectorAll('th, td')) : cellsInRow;

    if (headerCells.length <= 1) {
        showNotification('Нельзя удалить единственный столбец', 'warning');
        return;
    }

    // Remove from header
    if (headerRow) {
        const hCells = Array.from(headerRow.querySelectorAll('th, td'));
        if (hCells[colIndex]) {
            hCells[colIndex].parentNode.removeChild(hCells[colIndex]);
        }
    }

    // Remove from body rows
    const tbody = table.querySelector('tbody');
    const allRows = Array.from(table.querySelectorAll('tr'));
    const bodyRows = tbody ? Array.from(tbody.querySelectorAll('tr')) : allRows.filter(r => r !== headerRow);

    bodyRows.forEach(function (bodyRow) {
        const bodyCells = Array.from(bodyRow.querySelectorAll('td, th'));
        if (bodyCells[colIndex]) {
            bodyCells[colIndex].parentNode.removeChild(bodyCells[colIndex]);
        }
    });

    // Redistribute remaining widths
    if (headerRow) {
        const remainingHeaders = Array.from(headerRow.querySelectorAll('th, td'));
        const newColWidth = (100 / remainingHeaders.length).toFixed(4) + '%';
        remainingHeaders.forEach(th => {
            th.style.width = newColWidth;
        });

        table.querySelectorAll('tbody td, tbody th').forEach(td => {
            td.style.width = '';
        });
    }

    // Refresh resizers
    table.querySelectorAll('.column-resizer').forEach(r => r.remove());
    delete table.dataset.resizersAdded;
    addColumnResizers();

    saveToHistory();
    showNotification('Столбец удален', 'success');
}

function deleteTable() {
    const cell = getActiveTableCell();
    const row = getActiveTableRow();
    const target = cell || row;
    if (!target) return;

    const table = target.closest('table');
    if (!table) return;

    const parent = table.parentNode;
    parent.removeChild(table);

    // Ensure visual editor has at least one paragraph
    const ve = document.getElementById('contentVisual');
    if (ve && (!ve.hasChildNodes() || ve.innerHTML.trim() === '')) {
        ve.innerHTML = '<p><br></p>';
        if (window.VisualEngine) {
            window.VisualEngine.setCursorToStart(ve.firstElementChild);
        }
    }

    saveToHistory();
    showNotification('Таблица удалена', 'success');
}

function openCellColorDialog() {
    const cell = getActiveTableCell();
    if (!cell) {
        showNotification(window.t ? window.t('notifications.select_table_cell', 'Выберите ячейку таблицы') : 'Выберите ячейку таблицы', 'warning');
        return;
    }
    if (window.Modal) {
        Modal.open('#cellColorDialog');
    } else {
        const dlg = document.getElementById('cellColorDialog');
        if (dlg) dlg.style.display = 'flex';
    }
}

function closeCellColorDialog() {
    if (window.Modal) {
        Modal.close('#cellColorDialog');
    } else {
        const dlg = document.getElementById('cellColorDialog');
        if (dlg) dlg.style.display = 'none';
    }
}

function setCellColor(color) {
    const cell = getActiveTableCell();
    if (!cell) return;

    function getContrastTextColor(hex) {
        if (!hex) return '#000000';
        let cleanHex = hex.replace('#', '');
        if (cleanHex.length === 3) cleanHex = cleanHex.split('').map(c => c + c).join('');
        const r = parseInt(cleanHex.substring(0, 2), 16) || 0;
        const g = parseInt(cleanHex.substring(2, 4), 16) || 0;
        const b = parseInt(cleanHex.substring(4, 6), 16) || 0;
        const brightness = (r * 299 + g * 587 + b * 114) / 1000;
        return (brightness >= 128) ? '#000000' : '#ffffff';
    }

    if (color) {
        cell.style.backgroundColor = color;
        cell.style.color = getContrastTextColor(color);
    } else {
        cell.style.backgroundColor = '';
        cell.style.color = '';
    }

    saveToHistory();
    closeCellColorDialog();
    showNotification(window.t ? window.t('notifications.cell_color_changed', 'Цвет ячейки изменен') : 'Цвет ячейки изменен', 'success');
}

function insertTable() {
    const rows = parseInt(document.getElementById('tableRows').value, 10);
    const cols = parseInt(document.getElementById('tableCols').value, 10);

    if (!rows || rows < 1 || rows > 20) {
        showNotification('Введите количество строк от 1 до 20', 'warning');
        return;
    }

    if (!cols || cols < 1 || cols > 10) {
        showNotification('Введите количество столбцов от 1 до 10', 'warning');
        return;
    }

    if (window.enableMarkdown && editorMode === 'code') {
        let mdTable = '\n';
        mdTable += '| ' + Array.from({ length: cols }, (_, i) => `Заголовок ${i + 1}`).join(' | ') + ' |\n';
        mdTable += '| ' + Array.from({ length: cols }, () => '---').join(' | ') + ' |\n';
        for (let i = 0; i < rows; i++) {
            mdTable += '| ' + Array.from({ length: cols }, () => ' ').join(' | ') + ' |\n';
        }
        mdTable += '\n';

        const ta = document.getElementById('content');
        const cursorPos = ta.selectionStart;
        ta.value = ta.value.substring(0, cursorPos) + mdTable + ta.value.substring(cursorPos);
        ta.focus();
        saveToHistory();
        closeTableDialog();
        showNotification('Таблица добавлена', 'success');
        return;
    }

    const colWidth = (100 / cols).toFixed(4);
    let tableHtml = '<table><thead><tr>';

    for (let i = 0; i < cols; i++) {
        tableHtml += `<th style="width: ${colWidth}%;">Заголовок ${i + 1}</th>`;
    }
    tableHtml += '</tr></thead><tbody>';

    for (let i = 0; i < rows; i++) {
        tableHtml += '<tr>';
        for (let j = 0; j < cols; j++) {
            tableHtml += '<td><br></td>';
        }
        tableHtml += '</tr>';
    }

    tableHtml += '</tbody></table>';

    if (editorMode === 'code') {
        const ta = document.getElementById('content');
        const cursorPos = ta.selectionStart;
        ta.value = ta.value.substring(0, cursorPos) + tableHtml + '\n' + ta.value.substring(cursorPos);
        ta.focus();
    } else {
        insertTableAtCaret(tableHtml);
    }

    saveToHistory();
    closeTableDialog();
    showNotification('Таблица добавлена', 'success');
}

function insertTableAtCaret(tableHtml) {
    const ve = document.getElementById('contentVisual');
    ve.focus();
    const sel = window.getSelection();
    let range = null;

    if (savedRange && ve.contains(savedRange.commonAncestorContainer)) {
        range = savedRange;
    } else if (sel && sel.rangeCount > 0) {
        range = sel.getRangeAt(0);
    }

    const emptyDiv = document.createElement('p');
    emptyDiv.innerHTML = '<br>';

    if (!range) {
        ve.insertAdjacentHTML('beforeend', tableHtml);
        ve.appendChild(emptyDiv);
        range = document.createRange();
        range.setStart(emptyDiv, 0);
        range.collapse(true);
        if (sel) {
            sel.removeAllRanges();
            sel.addRange(range);
        }
        savedRange = range.cloneRange();
    } else {
        range.deleteContents();

        const temp = document.createElement('div');
        temp.innerHTML = tableHtml;

        const frag = document.createDocumentFragment();
        let node, lastNode;
        while ((node = temp.firstChild)) {
            lastNode = frag.appendChild(node);
        }

        range.insertNode(frag);

        if (lastNode) {
            const parent = lastNode.parentNode;
            parent.insertBefore(emptyDiv, lastNode.nextSibling);
            range.setStart(emptyDiv, 0);
            range.collapse(true);
            if (sel) {
                sel.removeAllRanges();
                sel.addRange(range);
            }
            savedRange = range.cloneRange();
        }
    }

    setTimeout(() => {
        addColumnResizers();
    }, 50);
}

function addColumnResizers() {
    const ve = document.getElementById('contentVisual');
    if (!ve) return;

    const tables = ve.querySelectorAll('table');
    tables.forEach(table => {
        const headerCells = Array.from(table.querySelectorAll('thead th, tr:first-child th'));
        if (headerCells.length === 0) return;

        const existingResizers = table.querySelectorAll('.column-resizer');
        if (table.dataset.resizersAdded === 'true' && existingResizers.length === headerCells.length - 1) {
            return;
        }

        // Clean any existing resizers
        existingResizers.forEach(r => r.remove());
        table.dataset.resizersAdded = 'true';

        // Check if headers have widths assigned
        let hasCustomWidths = true;
        headerCells.forEach(th => {
            if (!th.style.width || !th.style.width.includes('%')) {
                hasCustomWidths = false;
            }
        });

        if (!hasCustomWidths) {
            const colWidth = (100 / headerCells.length).toFixed(4) + '%';
            headerCells.forEach(th => {
                th.style.width = colWidth;
            });
        }

        headerCells.forEach((th, index) => {
            if (index === headerCells.length - 1) return;

            const resizer = document.createElement('div');
            resizer.className = 'column-resizer';
            resizer.contentEditable = 'false';
            th.appendChild(resizer);

            let startX, startWidthPercent, nextStartWidthPercent, tableWidth;

            resizer.addEventListener('mousedown', function (e) {
                e.preventDefault();
                e.stopPropagation();

                resizer.classList.add('resizing');
                document.body.style.cursor = 'col-resize';
                document.body.style.userSelect = 'none';

                startX = e.pageX;
                tableWidth = table.offsetWidth;

                startWidthPercent = (th.offsetWidth / tableWidth) * 100;
                const nextTh = headerCells[index + 1];
                nextStartWidthPercent = nextTh ? (nextTh.offsetWidth / tableWidth) * 100 : 0;

                function onMouseMove(ev) {
                    const diff = ev.pageX - startX;
                    const diffPercent = (diff / tableWidth) * 100;

                    const newWidthPercent = startWidthPercent + diffPercent;
                    const newNextWidthPercent = nextStartWidthPercent - diffPercent;

                    if (newWidthPercent > 4 && newNextWidthPercent > 4) {
                        th.style.width = newWidthPercent.toFixed(4) + '%';
                        if (nextTh) {
                            nextTh.style.width = newNextWidthPercent.toFixed(4) + '%';
                        }
                    }
                }

                function onMouseUp() {
                    resizer.classList.remove('resizing');
                    document.body.style.cursor = '';
                    document.body.style.userSelect = '';
                    document.removeEventListener('mousemove', onMouseMove);
                    document.removeEventListener('mouseup', onMouseUp);

                    saveToHistory();
                }

                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            });
        });
    });
}

function initTableResizers() {
    const ve = document.getElementById('contentVisual');
    if (!ve) return;

    addColumnResizers();

    let debounceTimer = null;
    const observer = new MutationObserver(() => {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            addColumnResizers();
        }, 150);
    });

    observer.observe(ve, {
        childList: true,
        subtree: true
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTableResizers);
} else {
    initTableResizers();
}

window.openTableDialog = openTableDialog;
window.closeTableDialog = closeTableDialog;
window.addTableRow = addTableRow;
window.deleteTableRow = deleteTableRow;
window.addTableColumn = addTableColumn;
window.deleteTableColumn = deleteTableColumn;
window.deleteTable = deleteTable;
window.openCellColorDialog = openCellColorDialog;
window.closeCellColorDialog = closeCellColorDialog;
window.setCellColor = setCellColor;
window.insertTable = insertTable;
window.addColumnResizers = addColumnResizers;
window.initTableResizers = initTableResizers;