<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно вставки таблицы
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * Поддерживает создание HTML/Markdown таблиц с настраиваемым количеством строк
 * и колонок, валидацию диапазонов, отправку по Enter и мультиязычность (i18n).
 * ==============================================================================
 */
?>
<div id="tableDialog" class="modal-overlay" data-size="sm">
    <div class="modal-dialog modal-sm">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon icon-info">⊞</span>
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.table_title">Вставить таблицу</h3>
                    <p class="modal-subtitle" data-i18n="modals.table_subtitle">Настройка сетки строк и столбцов</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeTableDialog()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело окна -->
        <div class="modal-body">
            <div class="modal-grid-2">
                <!-- Количество строк -->
                <div class="modal-form-group">
                    <label class="modal-label modal-label-required" for="tableRows" data-i18n="modals.table_rows">Количество строк:</label>
                    <input type="number" id="tableRows" class="modal-input" min="1" max="20" value="3" placeholder="Введите количество строк" data-i18n-placeholder="modals.table_rows_ph" autofocus onkeydown="if(event.key==='Enter') insertTable()">
                    <div class="modal-help-text">От 1 до 20 строк</div>
                </div>

                <!-- Количество столбцов -->
                <div class="modal-form-group">
                    <label class="modal-label modal-label-required" for="tableCols" data-i18n="modals.table_cols">Количество столбцов:</label>
                    <input type="number" id="tableCols" class="modal-input" min="1" max="7" value="3" placeholder="Введите количество столбцов" data-i18n-placeholder="modals.table_cols_ph" onkeydown="if(event.key==='Enter') insertTable()">
                    <div class="modal-help-text">От 1 до 7 столбцов</div>
                </div>
            </div>
        </div>

        <!-- Подвал окна -->
        <div class="modal-footer">
            <button type="button" onclick="closeTableDialog()" class="modal-btn modal-btn-ghost" data-modal-close data-i18n="common.cancel">Отмена</button>
            <button type="button" onclick="insertTable()" class="modal-btn modal-btn-primary" data-i18n="common.insert">Вставить</button>
        </div>
    </div>
</div>
