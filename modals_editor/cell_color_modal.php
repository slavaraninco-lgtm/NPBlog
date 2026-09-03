<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно перекрашивания ячейки таблицы
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * ==============================================================================
 */
?>
<div id="cellColorDialog" class="modal-overlay" data-size="sm">
    <div class="modal-dialog modal-sm">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.cell_color_title">Перекрасить ячейку</h3>
                    <p class="modal-subtitle" data-i18n="modals.cell_color_subtitle">Цвет фона ячейки таблицы</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeCellColorDialog()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело -->
        <div class="modal-body" style="display: flex; flex-direction: column; gap: 16px;">
            <div>
                <label class="modal-label" style="margin-bottom: 10px;" data-i18n="modals.cell_color_select">Выберите цвет:</label>
                <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; margin-bottom: 12px;">
                    <button type="button" onclick="setCellColor('#ffffff')" style="height: 36px; background: #ffffff; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: transform 0.15s ease;" title="Белый" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'"></button>
                    <button type="button" onclick="setCellColor('#f3f4f6')" style="height: 36px; background: #f3f4f6; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: transform 0.15s ease;" title="Светло-серый" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'"></button>
                    <button type="button" onclick="setCellColor('#fee2e2')" style="height: 36px; background: #fee2e2; border: 2px solid rgba(0,0,0,0.1); border-radius: 8px; cursor: pointer; transition: transform 0.15s ease;" title="Светло-красный" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'"></button>
                    <button type="button" onclick="setCellColor('#ffedd5')" style="height: 36px; background: #ffedd5; border: 2px solid rgba(0,0,0,0.1); border-radius: 8px; cursor: pointer; transition: transform 0.15s ease;" title="Светло-оранжевый" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'"></button>
                    <button type="button" onclick="setCellColor('#fef9c3')" style="height: 36px; background: #fef9c3; border: 2px solid rgba(0,0,0,0.1); border-radius: 8px; cursor: pointer; transition: transform 0.15s ease;" title="Светло-желтый" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'"></button>
                    <button type="button" onclick="setCellColor('#dcfce7')" style="height: 36px; background: #dcfce7; border: 2px solid rgba(0,0,0,0.1); border-radius: 8px; cursor: pointer; transition: transform 0.15s ease;" title="Светло-зеленый" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'"></button>
                    <button type="button" onclick="setCellColor('#e0f2fe')" style="height: 36px; background: #e0f2fe; border: 2px solid rgba(0,0,0,0.1); border-radius: 8px; cursor: pointer; transition: transform 0.15s ease;" title="Светло-синий" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'"></button>
                    
                    <button type="button" onclick="setCellColor('#f3e8ff')" style="height: 36px; background: #f3e8ff; border: 2px solid rgba(0,0,0,0.1); border-radius: 8px; cursor: pointer; transition: transform 0.15s ease;" title="Светло-фиолетовый" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'"></button>
                    <button type="button" onclick="setCellColor('#ffcdd2')" style="height: 36px; background: #ffcdd2; border: 2px solid rgba(0,0,0,0.1); border-radius: 8px; cursor: pointer; transition: transform 0.15s ease;" title="Красный" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'"></button>
                    <button type="button" onclick="setCellColor('#ffe0b2')" style="height: 36px; background: #ffe0b2; border: 2px solid rgba(0,0,0,0.1); border-radius: 8px; cursor: pointer; transition: transform 0.15s ease;" title="Оранжевый" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'"></button>
                    <button type="button" onclick="setCellColor('#fff9c4')" style="height: 36px; background: #fff9c4; border: 2px solid rgba(0,0,0,0.1); border-radius: 8px; cursor: pointer; transition: transform 0.15s ease;" title="Желтый" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'"></button>
                    <button type="button" onclick="setCellColor('#c8e6c9')" style="height: 36px; background: #c8e6c9; border: 2px solid rgba(0,0,0,0.1); border-radius: 8px; cursor: pointer; transition: transform 0.15s ease;" title="Зеленый" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'"></button>
                    <button type="button" onclick="setCellColor('#bbdefb')" style="height: 36px; background: #bbdefb; border: 2px solid rgba(0,0,0,0.1); border-radius: 8px; cursor: pointer; transition: transform 0.15s ease;" title="Синий" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'"></button>
                    <button type="button" onclick="setCellColor('#e1bee7')" style="height: 36px; background: #e1bee7; border: 2px solid rgba(0,0,0,0.1); border-radius: 8px; cursor: pointer; transition: transform 0.15s ease;" title="Фиолетовый" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'"></button>
                </div>

                <div class="modal-form-group" style="margin-bottom: 12px;">
                    <label class="modal-label" for="customCellColorInput" data-i18n="common.custom_color">Произвольный цвет:</label>
                    <input type="color" id="customCellColorInput" value="#ffffff" onchange="setCellColor(this.value)" class="modal-input" style="height: 38px; padding: 2px 4px; cursor: pointer; width: 100%;">
                </div>

                <button type="button" onclick="setCellColor('')" class="modal-btn modal-btn-ghost" style="width: 100%; justify-content: center;" data-i18n="modals.cell_color_remove">
                    🚫 Убрать цвет
                </button>
            </div>
        </div>

        <!-- Подвал -->
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-ghost" onclick="closeCellColorDialog()" data-modal-close data-i18n="common.close">Закрыть</button>
        </div>
    </div>
</div>
