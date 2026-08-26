<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно ASCII Рисовалки
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * Поддерживает рисование символьной графики, ластик, заливку (flood fill),
 * пресеты символов по категориям, историю изменений (Undo / Ctrl+Z),
 * кастомные размеры сетки и вставку/редактирование в статье.
 * ==============================================================================
 */
?>
<div id="asciiEditorModal" class="modal-overlay" data-size="xl">
    <div class="modal-dialog modal-xl" style="height: 85vh; max-height: 900px; display: flex; flex-direction: column;">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon icon-info">👾</span>
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.ascii_title">ASCII Рисовалка</h3>
                    <p class="modal-subtitle" data-i18n="modals.ascii_subtitle">Рисование символьной графики и псевдографики</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" id="asciiEditorUndoBtn" onclick="undoAsciiState()" class="modal-btn modal-btn-ghost" style="padding: 6px 14px; font-size: 13px; display: flex; align-items: center; gap: 6px;" title="Отменить (Ctrl+Z)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block;">
                        <path d="M3 7v6h6" />
                        <path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13" />
                    </svg>
                    <span data-i18n="common.undo">Отменить</span>
                </button>
                <button type="button" onclick="saveAsciiArt()" class="modal-btn modal-btn-primary" style="padding: 6px 18px; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                    <span>💾</span> <span data-i18n="common.save">Сохранить</span>
                </button>
                <button type="button" class="modal-close-btn" onclick="closeAsciiEditor()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>
        
        <!-- Основная область -->
        <div class="modal-body" style="padding: 0; flex: 1; display: flex; overflow: hidden;">
            <!-- Левая панель инструментов -->
            <div style="width: 260px; border-right: 1px solid var(--border-color); background: var(--modal-bg); display: flex; flex-direction: column; gap: 18px; padding: 20px; overflow-y: auto; box-sizing: border-box;">
                
                <!-- Размер сетки -->
                <div>
                    <label class="modal-label" for="asciiGridSize" data-i18n="modals.ascii_grid_size">Размер сетки:</label>
                    <select id="asciiGridSize" class="modal-select" onchange="changeAsciiGridSize(this.value)" style="margin-bottom: 8px;">
                        <option value="20x10" data-i18n="modals.ascii_size_small">Маленький (20x10)</option>
                        <option value="40x15" selected data-i18n="modals.ascii_size_medium">Средний (40x15)</option>
                        <option value="60x20" data-i18n="modals.ascii_size_large">Большой (60x20)</option>
                        <option value="80x25" data-i18n="modals.ascii_size_huge">Огромный (80x25)</option>
                        <option value="custom" data-i18n="modals.ascii_size_custom">Свой размер...</option>
                    </select>
                    
                    <div id="asciiCustomSizeContainer" style="display: none; gap: 6px; align-items: center; margin-top: 8px;">
                        <input type="number" id="asciiCustomWidth" class="modal-input" min="5" max="120" value="40" style="width: 60px; text-align: center; padding: 6px;" title="Ширина (колонки)">
                        <span style="color: var(--text-color); opacity: 0.7;">×</span>
                        <input type="number" id="asciiCustomHeight" class="modal-input" min="5" max="60" value="15" style="width: 60px; text-align: center; padding: 6px;" title="Высота (строки)">
                        <button type="button" onclick="applyCustomAsciiGridSize()" class="modal-btn modal-btn-primary" style="flex: 1; padding: 6px; font-size: 12px;">ОК</button>
                    </div>
                </div>
                
                <!-- Инструменты -->
                <div>
                    <label class="modal-label" data-i18n="modals.ascii_tools">Инструменты:</label>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                        <button type="button" class="ascii-tool-btn active" id="ascii-tool-draw" onclick="setAsciiTool('draw')">
                            <span data-i18n="modals.ascii_tool_draw">Рисовать</span>
                        </button>
                        <button type="button" class="ascii-tool-btn" id="ascii-tool-erase" onclick="setAsciiTool('erase')">
                            <span data-i18n="modals.ascii_tool_erase">Ластик</span>
                        </button>
                        <button type="button" class="ascii-tool-btn" id="ascii-tool-fill" onclick="setAsciiTool('fill')" style="grid-column: span 2;">
                            <span data-i18n="modals.ascii_tool_fill">Заливка</span>
                        </button>
                    </div>
                </div>

                <!-- Выбор символа -->
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label class="modal-label" style="margin: 0;" data-i18n="modals.ascii_symbol_label">Символ:</label>
                        <div style="display: flex; gap: 4px; align-items: center;">
                            <button type="button" onclick="prevAsciiPage()" class="ascii-pager-btn" id="asciiPrevPageBtn" title="Предыдущая группа">◀</button>
                            <span id="asciiPageIndicator" style="color: var(--text-color); font-size: 11px; opacity: 0.8; font-weight: bold; min-width: 65px; text-align: center;">Блоки</span>
                            <button type="button" onclick="nextAsciiPage()" class="ascii-pager-btn" id="asciiNextPageBtn" title="Следующая группа">▶</button>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; margin-bottom: 12px; min-height: 108px;" id="asciiCharPresets">
                        <!-- Пресеты символов заполняются динамически -->
                    </div>
                    
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="text" id="asciiCustomChar" class="modal-input" maxlength="1" placeholder="Свой" style="width: 50px; text-align: center; padding: 6px; font-family: monospace; font-size: 16px;">
                        <button type="button" onclick="applyCustomAsciiChar()" class="modal-btn" style="flex: 1; padding: 6px; font-size: 12px;" data-i18n="common.apply">Применить</button>
                    </div>
                </div>

                <!-- Кнопка очистки -->
                <div style="margin-top: auto; padding-top: 10px;">
                    <button type="button" onclick="clearAsciiGrid()" class="modal-btn modal-btn-danger" style="width: 100%; justify-content: center; padding: 8px 12px; font-size: 13px;">
                        🗑️ <span data-i18n="modals.ascii_clear">Очистить холст</span>
                    </button>
                </div>
            </div>
            
            <!-- Центральная область холста -->
            <div style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 25px; overflow: auto; position: relative; background: rgba(128,128,128,0.03);" id="asciiEditorCanvasContainer">
                <div id="asciiGrid" style="display: grid; border: 1px solid var(--border-color); background: var(--modal-bg); box-shadow: 0 4px 25px rgba(0,0,0,0.15); max-width: 100%; cursor: crosshair; user-select: none; -webkit-user-select: none;">
                    <!-- Ячейки сетки генерируются динамически через JS -->
                </div>
            </div>
        </div>
    </div>
</div>
