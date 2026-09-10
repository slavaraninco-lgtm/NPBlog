// --- ASCII Drawing Tool Implementation ---
let asciiGridWidth = 40;
let asciiGridHeight = 15;
let asciiCurrentChar = '█';
let asciiCurrentTool = 'draw'; // 'draw', 'erase', 'fill'
let asciiIsDrawing = false;
let asciiHistory = [];
let asciiHistoryIndex = -1;
let asciiTargetWrap = null;

function wrapAsciiWithControls(asciiHtml) {
    const uniqueId = 'ascii-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    return '<div class="blog-image-align-wrap" style="text-align:center" data-image-id="' + uniqueId + '">' +
        '<div class="blog-image-wrap">' + asciiHtml + '</div></div>';
}

function drawCell(cell) {
    if (asciiCurrentTool === 'draw') {
        if (cell.textContent !== asciiCurrentChar) {
            cell.textContent = asciiCurrentChar;
        }
    } else if (asciiCurrentTool === 'erase') {
        if (cell.textContent !== ' ') {
            cell.textContent = ' ';
        }
    }
}

function floodFill(startX, startY, targetChar, replacementChar) {
    if (targetChar === replacementChar) return;

    const cells = document.querySelectorAll('.ascii-cell');
    const getCell = (x, y) => {
        if (x < 0 || x >= asciiGridWidth || y < 0 || y >= asciiGridHeight) return null;
        return cells[y * asciiGridWidth + x];
    };

    const startCell = getCell(startX, startY);
    if (!startCell || startCell.textContent !== targetChar) return;

    const queue = [[startX, startY]];

    while (queue.length > 0) {
        const [x, y] = queue.shift();
        const cell = getCell(x, y);
        if (cell && cell.textContent === targetChar) {
            cell.textContent = replacementChar;

            queue.push([x + 1, y]);
            queue.push([x - 1, y]);
            queue.push([x, y + 1]);
            queue.push([x, y - 1]);
        }
    }
}

async function changeAsciiGridSize(sizeStr) {
    const customContainer = document.getElementById('asciiCustomSizeContainer');
    if (sizeStr === 'custom') {
        if (customContainer) {
            customContainer.style.display = 'flex';
            const widthInput = document.getElementById('asciiCustomWidth');
            const heightInput = document.getElementById('asciiCustomHeight');
            if (widthInput) widthInput.value = asciiGridWidth;
            if (heightInput) heightInput.value = asciiGridHeight;
        }
        return;
    }

    if (customContainer) {
        customContainer.style.display = 'none';
    }

    const parts = sizeStr.split('x');
    const newWidth = Math.max(5, Math.min(120, parseInt(parts[0]) || 40));
    const newHeight = Math.max(5, Math.min(60, parseInt(parts[1]) || 15));

    const isConfirmed = await showConfirm('Смена размера сетки очистит текущий рисунок. Продолжить?', 'Изменение размера сетки');
    if (isConfirmed) {
        asciiGridWidth = newWidth;
        asciiGridHeight = newHeight;
        const widthInput = document.getElementById('asciiCustomWidth');
        const heightInput = document.getElementById('asciiCustomHeight');
        if (widthInput) widthInput.value = newWidth;
        if (heightInput) heightInput.value = newHeight;
        createAsciiGrid();
        clearAsciiHistory();
        saveAsciiHistory();
    } else {
        const sizeSelect = document.getElementById('asciiGridSize');
        if (sizeSelect) {
            sizeSelect.value = asciiGridWidth + 'x' + asciiGridHeight;
        }
    }
}

async function applyCustomAsciiGridSize() {
    const widthInput = document.getElementById('asciiCustomWidth');
    const heightInput = document.getElementById('asciiCustomHeight');
    if (!widthInput || !heightInput) return;

    const newWidth = parseInt(widthInput.value);
    const newHeight = parseInt(heightInput.value);

    if (isNaN(newWidth) || newWidth < 5 || newWidth > 120) {
        showNotification('Ширина должна быть от 5 до 120 символов', 'warning');
        widthInput.value = asciiGridWidth;
        return;
    }
    if (isNaN(newHeight) || newHeight < 5 || newHeight > 60) {
        showNotification('Высота должна быть от 5 до 60 символов', 'warning');
        heightInput.value = asciiGridHeight;
        return;
    }

    const isConfirmed = await showConfirm('Смена размера сетки очистит текущий рисунок. Продолжить?', 'Изменение размера сетки');
    if (isConfirmed) {
        asciiGridWidth = newWidth;
        asciiGridHeight = newHeight;
        createAsciiGrid();
        clearAsciiHistory();
        saveAsciiHistory();
        showNotification(`Установлен размер: ${newWidth}x${newHeight}`, 'success');
    } else {
        widthInput.value = asciiGridWidth;
        heightInput.value = asciiGridHeight;
    }
}

function createAsciiGrid(initialData = null) {
    const gridContainer = document.getElementById('asciiGrid');
    if (!gridContainer) return;

    asciiGridWidth = Math.max(5, Math.min(120, parseInt(asciiGridWidth) || 40));
    asciiGridHeight = Math.max(5, Math.min(60, parseInt(asciiGridHeight) || 15));

    gridContainer.innerHTML = '';
    gridContainer.style.gridTemplateColumns = `repeat(${asciiGridWidth}, 9px)`;
    gridContainer.style.gridTemplateRows = `repeat(${asciiGridHeight}, 18px)`;

    for (let y = 0; y < asciiGridHeight; y++) {
        for (let x = 0; x < asciiGridWidth; x++) {
            const cell = document.createElement('div');
            cell.className = 'ascii-cell';
            cell.setAttribute('data-x', x);
            cell.setAttribute('data-y', y);

            const index = y * asciiGridWidth + x;
            if (initialData && initialData[index] !== undefined) {
                cell.textContent = initialData[index];
            } else {
                cell.textContent = ' ';
            }

            cell.addEventListener('mousedown', function (e) {
                e.preventDefault();
                if (asciiCurrentTool === 'fill') {
                    const targetChar = cell.textContent;
                    floodFill(x, y, targetChar, asciiCurrentChar);
                    saveAsciiHistory();
                } else {
                    asciiIsDrawing = true;
                    drawCell(cell);
                }
            });

            cell.addEventListener('mouseenter', function (e) {
                cell.classList.add('hovered');
                if (asciiIsDrawing) {
                    drawCell(cell);
                }
            });

            cell.addEventListener('mouseleave', function () {
                cell.classList.remove('hovered');
            });

            gridContainer.appendChild(cell);
        }
    }

    setTimeout(fitAsciiGridToContainer, 0);
}

function fitAsciiGridToContainer() {
    const container = document.getElementById('asciiEditorCanvasContainer');
    const grid = document.getElementById('asciiGrid');
    if (!container || !grid) return;

    grid.style.transform = 'none';
    grid.style.transformOrigin = 'center center';

    const padding = 40;
    const containerWidth = container.clientWidth - padding;
    const containerHeight = container.clientHeight - padding;

    const gridWidth = grid.offsetWidth;
    const gridHeight = grid.offsetHeight;

    if (gridWidth === 0 || gridHeight === 0) return;

    const scaleX = containerWidth / gridWidth;
    const scaleY = containerHeight / gridHeight;
    const scale = Math.min(scaleX, scaleY);

    grid.style.transform = `scale(${scale})`;
}

window.addEventListener('resize', function () {
    const modal = document.getElementById('asciiEditorModal');
    if (modal && modal.style.display === 'flex') {
        fitAsciiGridToContainer();
    }
});

// Global mouse up to end drawing
window.addEventListener('mouseup', function () {
    if (asciiIsDrawing) {
        asciiIsDrawing = false;
        saveAsciiHistory();
    }
});

// Global shortcut keys interception (Ctrl+Z) in ASCII editor
window.addEventListener('keydown', function (e) {
    const modal = document.getElementById('asciiEditorModal');
    if (modal && modal.style.display === 'flex') {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
            e.preventDefault();
            undoAsciiState();
        }
    }
});

function clearAsciiHistory() {
    asciiHistory = [];
    asciiHistoryIndex = -1;
    updateAsciiUndoBtn();
}

function saveAsciiHistory() {
    const cells = document.querySelectorAll('.ascii-cell');
    if (cells.length === 0) return;
    const state = Array.from(cells).map(c => c.textContent);

    asciiHistory = asciiHistory.slice(0, asciiHistoryIndex + 1);
    asciiHistory.push(state);
    asciiHistoryIndex++;

    updateAsciiUndoBtn();
}

function undoAsciiState() {
    if (asciiHistoryIndex <= 0) return;

    asciiHistoryIndex--;
    restoreAsciiHistoryState(asciiHistory[asciiHistoryIndex]);
    updateAsciiUndoBtn();
}

function restoreAsciiHistoryState(state) {
    const cells = document.querySelectorAll('.ascii-cell');
    cells.forEach((cell, i) => {
        if (state[i] !== undefined) {
            cell.textContent = state[i];
        }
    });
}

function updateAsciiUndoBtn() {
    const btn = document.getElementById('asciiEditorUndoBtn');
    if (!btn) return;
    btn.disabled = asciiHistoryIndex <= 0;
    btn.style.opacity = asciiHistoryIndex <= 0 ? '0.5' : '1';
    btn.style.cursor = asciiHistoryIndex <= 0 ? 'not-allowed' : 'pointer';
}

function setAsciiTool(tool) {
    asciiCurrentTool = tool;
    document.querySelectorAll('.ascii-tool-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    const activeBtn = document.getElementById('ascii-tool-' + tool);
    if (activeBtn) activeBtn.classList.add('active');
}

const asciiPresetCategories = [
    {
        name: 'Блоки',
        chars: ['█', '▓', '▒', '░', '▄', '▀', '▌', '▐', '■', '▲', '▼', '◆', '●', '○', '★', '☆', '♣', '♦']
    },
    {
        name: 'Линии',
        chars: ['─', '│', '┌', '┐', '└', '┘', '├', '┤', '┬', '┴', '┼', '╭', '╮', '╯', '╰', '╱', '╲', '╳']
    },
    {
        name: 'Двойные',
        chars: ['═', '║', '╔', '╗', '╚', '╝', '╠', '╣', '╦', '╩', '╬', '╒', '╕', '╘', '╛', '╓', '╖', '╙']
    },
    {
        name: 'Стрелки',
        chars: ['↑', '↓', '←', '→', '↖', '↗', '↘', '↙', '↔', '↕', '▲', '▼', '◀', '▶', '➔', '➜', '➘', '➚']
    },
    {
        name: 'Символы',
        chars: ['#', '@', '*', '+', '-', '=', ':', '.', 'o', 'x', 'd', 'b', 'p', 'q', '0', '1', '8', '9']
    }
];

let asciiCurrentCategoryIndex = 0;

function renderAsciiPresets() {
    const container = document.getElementById('asciiCharPresets');
    const indicator = document.getElementById('asciiPageIndicator');
    if (!container) return;

    const category = asciiPresetCategories[asciiCurrentCategoryIndex];
    if (indicator) indicator.textContent = category.name;

    container.innerHTML = '';
    container.style.opacity = '0';

    category.chars.forEach(char => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ascii-char-preset';
        if (char === asciiCurrentChar) {
            btn.classList.add('active');
        }
        btn.textContent = char;
        btn.onclick = function () {
            setAsciiChar(char, btn);
        };
        container.appendChild(btn);
    });

    requestAnimationFrame(() => {
        container.style.transition = 'opacity 0.15s ease-in-out';
        container.style.opacity = '1';
    });
}

function nextAsciiPage() {
    asciiCurrentCategoryIndex = (asciiCurrentCategoryIndex + 1) % asciiPresetCategories.length;
    renderAsciiPresets();
}

function prevAsciiPage() {
    asciiCurrentCategoryIndex = (asciiCurrentCategoryIndex - 1 + asciiPresetCategories.length) % asciiPresetCategories.length;
    renderAsciiPresets();
}

function setAsciiChar(char, presetBtn = null) {
    asciiCurrentChar = char;

    if (presetBtn) {
        document.querySelectorAll('.ascii-char-preset').forEach(btn => {
            btn.classList.remove('active');
        });
        presetBtn.classList.add('active');
    }
}

function applyCustomAsciiChar() {
    const input = document.getElementById('asciiCustomChar');
    if (input && input.value) {
        setAsciiChar(input.value);
        document.querySelectorAll('.ascii-char-preset').forEach(btn => {
            btn.classList.remove('active');
        });
        showNotification('Установлен символ: ' + input.value, 'success');
    }
}

function openAsciiDrawer(targetWrap = null) {
    if (editorMode !== 'visual') {
        showNotification('ASCII Рисовалка доступна только в визуальном режиме', 'warning');
        return;
    }

    asciiTargetWrap = targetWrap;
    const modal = document.getElementById('asciiEditorModal');
    if (!modal) return;

    const customInput = document.getElementById('asciiCustomChar');
    if (customInput) customInput.value = '';

    setAsciiTool('draw');
    asciiCurrentCategoryIndex = 0;
    setAsciiChar('█');
    renderAsciiPresets();

    if (asciiTargetWrap) {
        const width = parseInt(asciiTargetWrap.getAttribute('data-ascii-width')) || 40;
        const height = parseInt(asciiTargetWrap.getAttribute('data-ascii-height')) || 15;
        const gridData = JSON.parse(asciiTargetWrap.getAttribute('data-ascii-grid') || '[]');

        asciiGridWidth = Math.max(5, Math.min(120, width));
        asciiGridHeight = Math.max(5, Math.min(60, height));

        const sizeSelect = document.getElementById('asciiGridSize');
        const customContainer = document.getElementById('asciiCustomSizeContainer');
        if (sizeSelect) {
            const val = asciiGridWidth + 'x' + asciiGridHeight;
            const hasOption = Array.from(sizeSelect.options).some(opt => opt.value === val);
            if (hasOption) {
                sizeSelect.value = val;
                if (customContainer) customContainer.style.display = 'none';
            } else {
                sizeSelect.value = 'custom';
                if (customContainer) {
                    customContainer.style.display = 'flex';
                }
            }
        }
        const widthInput = document.getElementById('asciiCustomWidth');
        const heightInput = document.getElementById('asciiCustomHeight');
        if (widthInput) widthInput.value = asciiGridWidth;
        if (heightInput) heightInput.value = asciiGridHeight;

        createAsciiGrid(gridData);
    } else {
        const sizeSelect = document.getElementById('asciiGridSize');
        const customContainer = document.getElementById('asciiCustomSizeContainer');
        const sizeStr = sizeSelect ? sizeSelect.value : '40x15';
        if (sizeStr === 'custom') {
            const widthInput = document.getElementById('asciiCustomWidth');
            const heightInput = document.getElementById('asciiCustomHeight');
            let w = widthInput ? parseInt(widthInput.value) : 40;
            let h = heightInput ? parseInt(heightInput.value) : 15;
            if (isNaN(w) || w < 5 || w > 120) w = 40;
            if (isNaN(h) || h < 5 || h > 60) h = 15;
            asciiGridWidth = w;
            asciiGridHeight = h;
            if (widthInput) widthInput.value = w;
            if (heightInput) heightInput.value = h;
            if (customContainer) customContainer.style.display = 'flex';
        } else {
            const parts = sizeStr.split('x');
            asciiGridWidth = Math.max(5, Math.min(120, parseInt(parts[0]) || 40));
            asciiGridHeight = Math.max(5, Math.min(60, parseInt(parts[1]) || 15));
            if (customContainer) customContainer.style.display = 'none';
            const widthInput = document.getElementById('asciiCustomWidth');
            const heightInput = document.getElementById('asciiCustomHeight');
            if (widthInput) widthInput.value = asciiGridWidth;
            if (heightInput) heightInput.value = asciiGridHeight;
        }

        createAsciiGrid();
    }

    clearAsciiHistory();
    saveAsciiHistory();

    if (window.Modal) {
        Modal.open('#asciiEditorModal');
    } else {
        modal.style.display = 'flex';
        modal.classList.add('show');
    }
    setTimeout(fitAsciiGridToContainer, 50);
}

function closeAsciiEditor() {
    if (window.Modal) {
        Modal.close('#asciiEditorModal');
    } else {
        const modal = document.getElementById('asciiEditorModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('show');
        }
    }
    asciiTargetWrap = null;
    const widthInput = document.getElementById('asciiCustomWidth');
    const heightInput = document.getElementById('asciiCustomHeight');
    if (widthInput) widthInput.value = asciiGridWidth;
    if (heightInput) heightInput.value = asciiGridHeight;
}

async function clearAsciiGrid() {
    const isConfirmed = await showConfirm('Очистить холст? Это действие удалит весь текущий рисунок.', 'Очистка холста');
    if (isConfirmed) {
        const cells = document.querySelectorAll('.ascii-cell');
        cells.forEach(cell => {
            cell.textContent = ' ';
        });
        saveAsciiHistory();
    }
}

function saveAsciiArt() {
    const cells = document.querySelectorAll('.ascii-cell');
    const gridData = Array.from(cells).map(c => c.textContent);

    let textLines = [];
    for (let y = 0; y < asciiGridHeight; y++) {
        let line = '';
        for (let x = 0; x < asciiGridWidth; x++) {
            const index = y * asciiGridWidth + x;
            line += gridData[index];
        }
        textLines.push(line.trimRight());
    }
    const plainText = textLines.join('\n');

    const gridJson = JSON.stringify(gridData).replace(/"/g, '&quot;');
    const asciiHtml = `<pre class="blog-ascii-art">${plainText}</pre>`;

    if (asciiTargetWrap) {
        asciiTargetWrap.setAttribute('data-ascii-width', asciiGridWidth);
        asciiTargetWrap.setAttribute('data-ascii-height', asciiGridHeight);
        asciiTargetWrap.setAttribute('data-ascii-grid', JSON.stringify(gridData));

        const artEl = asciiTargetWrap.querySelector('.blog-ascii-art');
        if (artEl) {
            artEl.textContent = plainText;
        }
        showNotification('ASCII-арт обновлен', 'success');
    } else {
        const fullHtml = wrapAsciiWithControls(
            `<div class="blog-ascii-wrap" data-ascii-width="${asciiGridWidth}" data-ascii-height="${asciiGridHeight}" data-ascii-grid="${gridJson}">${asciiHtml}</div>`
        );
        insertHtmlAtCursor(fullHtml);
        showNotification('ASCII-арт вставлен в статью', 'success');
    }

    saveToHistory();
    closeAsciiEditor();
}

// Export to window
window.openAsciiDrawer = openAsciiDrawer;
window.closeAsciiEditor = closeAsciiEditor;
window.changeAsciiGridSize = changeAsciiGridSize;
window.setAsciiTool = setAsciiTool;
window.setAsciiChar = setAsciiChar;
window.applyCustomAsciiChar = applyCustomAsciiChar;
window.clearAsciiGrid = clearAsciiGrid;
window.undoAsciiState = undoAsciiState;
window.saveAsciiArt = saveAsciiArt;
window.applyCustomAsciiGridSize = applyCustomAsciiGridSize;
window.fitAsciiGridToContainer = fitAsciiGridToContainer;
window.nextAsciiPage = nextAsciiPage;
window.prevAsciiPage = prevAsciiPage;
