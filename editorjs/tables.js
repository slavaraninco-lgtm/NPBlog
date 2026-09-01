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
    if (!window.contextMenuTableRow) return;

    const row = window.contextMenuTableRow;
    const table = row.closest('table');
    if (!table) return;

    const colCount = row.querySelectorAll('td, th').length;
    const newRow = document.createElement('tr');

    for (let i = 0; i < colCount; i++) {
        const cell = document.createElement('td');
        cell.innerHTML = '<br>';
        cell.contentEditable = 'true';
        newRow.appendChild(cell);
    }

    // Вставляем новую строку после текущей
    if (row.parentNode.tagName === 'THEAD') {
        // Если это строка заголовка, добавляем в tbody
        const tbody = table.querySelector('tbody');
        if (tbody && tbody.firstChild) {
            tbody.insertBefore(newRow, tbody.firstChild);
        } else if (tbody) {
            tbody.appendChild(newRow);
        }
    } else {
        row.parentNode.insertBefore(newRow, row.nextSibling);
    }

    saveToHistory();
    showNotification('Строка добавлена', 'success');
}

function deleteTableRow() {
    if (!window.contextMenuTableRow) return;

    const row = window.contextMenuTableRow;
    const table = row.closest('table');
    if (!table) return;

    // Проверяем, не является ли это единственной строкой в tbody
    const tbody = table.querySelector('tbody');
    if (tbody && tbody.querySelectorAll('tr').length === 1 && row.parentNode === tbody) {
        showNotification('Нельзя удалить последнюю строку таблицы', 'warning');
        return;
    }

    // Не даем удалить строку заголовка, если она единственная в thead
    if (row.parentNode.tagName === 'THEAD') {
        showNotification('Нельзя удалить строку заголовка', 'warning');
        return;
    }

    row.parentNode.removeChild(row);
    saveToHistory();
    showNotification('Строка удалена', 'success');
}

function addTableColumn() {
    if (!window.contextMenuTableCell) return;

    const cell = window.contextMenuTableCell;
    const table = cell.closest('table');
    if (!table) return;

    // Определяем индекс текущего столбца
    const row = cell.closest('tr');
    const cells = Array.from(row.querySelectorAll('td, th'));
    const colIndex = cells.indexOf(cell);

    // Добавляем ячейку в заголовок
    const thead = table.querySelector('thead');
    if (thead) {
        const headerRow = thead.querySelector('tr');
        if (headerRow) {
            const headerCells = headerRow.querySelectorAll('th');
            const newHeader = document.createElement('th');
            newHeader.innerHTML = '<br>';
            newHeader.contentEditable = 'true';

            if (colIndex + 1 < headerCells.length) {
                headerRow.insertBefore(newHeader, headerCells[colIndex + 1]);
            } else {
                headerRow.appendChild(newHeader);
            }
        }
    }

    // Добавляем ячейки во все строки tbody
    const tbody = table.querySelector('tbody');
    if (tbody) {
        const rows = tbody.querySelectorAll('tr');
        rows.forEach(function (bodyRow) {
            const bodyCells = bodyRow.querySelectorAll('td');
            const newCell = document.createElement('td');
            newCell.innerHTML = '<br>';
            newCell.contentEditable = 'true';

            if (colIndex + 1 < bodyCells.length) {
                bodyRow.insertBefore(newCell, bodyCells[colIndex + 1]);
            } else {
                bodyRow.appendChild(newCell);
            }
        });
    }

    // Обновляем ресайзеры
    addColumnResizers();
    saveToHistory();
    showNotification('Столбец добавлен', 'success');
}

function deleteTableColumn() {
    if (!window.contextMenuTableCell) return;

    const cell = window.contextMenuTableCell;
    const table = cell.closest('table');
    if (!table) return;

    // Определяем индекс текущего столбца
    const row = cell.closest('tr');
    const cells = Array.from(row.querySelectorAll('td, th'));
    const colIndex = cells.indexOf(cell);

    // Проверяем, не единственный ли это столбец
    if (cells.length === 1) {
        showNotification('Нельзя удалить единственный столбец', 'warning');
        return;
    }

    // Удаляем ячейку из заголовка
    const thead = table.querySelector('thead');
    if (thead) {
        const headerRow = thead.querySelector('tr');
        if (headerRow) {
            const headerCells = headerRow.querySelectorAll('th');
            if (headerCells[colIndex]) {
                headerCells[colIndex].parentNode.removeChild(headerCells[colIndex]);
            }
        }
    }

    // Удаляем ячейки из всех строк tbody
    const tbody = table.querySelector('tbody');
    if (tbody) {
        const rows = tbody.querySelectorAll('tr');
        rows.forEach(function (bodyRow) {
            const bodyCells = bodyRow.querySelectorAll('td');
            if (bodyCells[colIndex]) {
                bodyCells[colIndex].parentNode.removeChild(bodyCells[colIndex]);
            }
        });
    }

    // Обновляем ресайзеры
    addColumnResizers();
    saveToHistory();
    showNotification('Столбец удален', 'success');
}

function deleteTable() {
    if (!window.contextMenuTableCell && !window.contextMenuTableRow) return;

    const cell = window.contextMenuTableCell || window.contextMenuTableRow.querySelector('td, th');
    if (!cell) return;

    const table = cell.closest('table');
    if (!table) return;

    // Удаляем таблицу
    table.parentNode.removeChild(table);
    saveToHistory();
    showNotification('Таблица удалена', 'success');
}

function openCellColorDialog() {
    if (!window.contextMenuTableCell) return;
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
    if (!window.contextMenuTableCell) return;

    const cell = window.contextMenuTableCell;

    if (color) {
        cell.style.backgroundColor = color;
        cell.style.color = '#000000'; // Устанавливаем черный цвет текста
    } else {
        cell.style.backgroundColor = '';
        cell.style.color = ''; // Сбрасываем цвет текста
    }

    saveToHistory();
    closeCellColorDialog();
    showNotification(window.t ? window.t('notifications.cell_color_changed', 'Цвет ячейки изменен') : 'Цвет ячейки изменен', 'success');
}

window.openCellColorDialog = openCellColorDialog;
window.closeCellColorDialog = closeCellColorDialog;
window.setCellColor = setCellColor;

function insertTable() {
    const rows = parseInt(document.getElementById('tableRows').value);
    const cols = parseInt(document.getElementById('tableCols').value);

    if (!rows || rows < 1 || rows > 20) {
        showNotification('Введите количество строк от 1 до 20', 'warning');
        return;
    }

    if (!cols || cols < 1 || cols > 7) {
        showNotification('Введите количество столбцов от 1 до 7', 'warning');
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

    let tableHtml = '<table><thead><tr>';

    // Создаем заголовки
    for (let i = 0; i < cols; i++) {
        tableHtml += `<th>Заголовок ${i + 1}</th>`;
    }
    tableHtml += '</tr></thead><tbody>';

    // Создаем строки с пустыми ячейками
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

// Функция для вставки таблицы в визуальном редакторе
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

    // Создаем пустой блок для курсора после таблицы
    const emptyDiv = document.createElement('div');
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

        // Создаем временный контейнер для парсинга HTML
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

    // Добавляем ручки изменения размера после небольшой задержки
    setTimeout(() => {
        addColumnResizers();
    }, 100);
}

// Функция для добавления ручек изменения размера столбцов
function addColumnResizers() {
    const ve = document.getElementById('contentVisual');
    if (!ve) return;

    const tables = ve.querySelectorAll('table');
    tables.forEach(table => {
        // Проверяем, не добавлены ли уже ручки
        if (table.dataset.resizersAdded) return;
        table.dataset.resizersAdded = 'true';

        const headerCells = table.querySelectorAll('thead th');

        // Устанавливаем начальную ширину в процентах
        const colWidth = 100 / headerCells.length;
        headerCells.forEach(th => {
            th.style.width = colWidth + '%';
        });

        headerCells.forEach((th, index) => {
            // Не добавляем ручку к последнему столбцу
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
                startX = e.pageX;
                tableWidth = table.offsetWidth;

                // Получаем текущую ширину в процентах
                startWidthPercent = (th.offsetWidth / tableWidth) * 100;

                const nextTh = headerCells[index + 1];
                nextStartWidthPercent = nextTh ? (nextTh.offsetWidth / tableWidth) * 100 : 0;

                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            });

            function onMouseMove(e) {
                const diff = e.pageX - startX;
                const diffPercent = (diff / tableWidth) * 100;

                const newWidthPercent = startWidthPercent + diffPercent;
                const newNextWidthPercent = nextStartWidthPercent - diffPercent;

                // Минимальная ширина 5%
                if (newWidthPercent > 5 && newNextWidthPercent > 5) {
                    th.style.width = newWidthPercent + '%';
                    const nextTh = headerCells[index + 1];
                    if (nextTh) {
                        nextTh.style.width = newNextWidthPercent + '%';
                    }

                    // Применяем ширину ко всем ячейкам в столбце
                    const rows = table.querySelectorAll('tbody tr');
                    rows.forEach(row => {
                        const cells = row.querySelectorAll('td');
                        if (cells[index]) {
                            cells[index].style.width = newWidthPercent + '%';
                        }
                        if (cells[index + 1]) {
                            cells[index + 1].style.width = newNextWidthPercent + '%';
                        }
                    });
                }
            }

            function onMouseUp() {
                resizer.classList.remove('resizing');
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
            }
        });
    });
}

// Вызываем функцию при загрузке контента в визуальный редактор
function initTableResizers() {
    const ve = document.getElementById('contentVisual');
    if (!ve) return;

    // Добавляем ручки к существующим таблицам
    addColumnResizers();

    // Наблюдаем за изменениями в редакторе
    const observer = new MutationObserver(() => {
        addColumnResizers();
    });

    observer.observe(ve, {
        childList: true,
        subtree: true
    });
}

// Инициализируем при загрузке страницы
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTableResizers);
} else {
    initTableResizers();
}
