<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно вставки и редактирования блока кода
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * Поддерживает подсветку синтаксиса популярных языков программирования,
 * создание новых блоков <pre><code> и редактирование существующих блоков в статье.
 * ==============================================================================
 */
?>
<div id="codeDialog" class="modal-overlay" data-size="md">
    <div class="modal-dialog modal-md">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                
                <div class="modal-titles">
                    <h3 class="modal-title" id="codeDialogTitle" data-i18n="modals.code_title">Вставить код</h3>
                    <p class="modal-subtitle" data-i18n="modals.code_subtitle">Блок исходного кода с подсветкой синтаксиса</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeCodeDialog()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело окна -->
        <div class="modal-body">
            <!-- Выбор языка -->
            <div class="modal-form-group">
                <label class="modal-label modal-label-required" for="codeLanguage" data-i18n="modals.code_lang_label">Язык программирования:</label>
                <select id="codeLanguage" class="modal-select">
                    <option value="javascript">JavaScript</option>
                    <option value="php">PHP</option>
                    <option value="html">HTML</option>
                    <option value="css">CSS</option>
                    <option value="python">Python</option>
                    <option value="sql">SQL</option>
                    <option value="java">Java</option>
                    <option value="cpp">C++</option>
                    <option value="csharp">C#</option>
                    <option value="ruby">Ruby</option>
                    <option value="typescript">TypeScript</option>
                    <option value="json">JSON</option>
                    <option value="bash">Bash / Shell</option>
                    <option value="rust">Rust</option>
                    <option value="go">Go</option>
                    <option value="plain" data-i18n="modals.code_lang_plain">Текст</option>
                </select>
            </div>

            <!-- Поле ввода кода -->
            <div class="modal-form-group" style="margin-top: 14px;">
                <label class="modal-label modal-label-required" for="codeInput" data-i18n="modals.code_textarea_label">Код:</label>
                <textarea id="codeInput" class="modal-textarea" placeholder="Вставьте ваш код сюда..." data-i18n-placeholder="modals.code_ph" style="font-family: Consolas, Monaco, 'Courier New', monospace; font-size: 13px; line-height: 1.5; min-height: 200px; tab-size: 4; white-space: pre;" spellcheck="false"></textarea>
            </div>
        </div>

        <!-- Подвал окна -->
        <div class="modal-footer">
            <button type="button" onclick="closeCodeDialog()" class="modal-btn modal-btn-ghost" data-modal-close data-i18n="common.cancel">Отмена</button>
            <button type="button" id="codeDialogSubmitBtn" onclick="insertCodeBlock()" class="modal-btn modal-btn-primary" data-i18n="modals.code_insert_btn">Вставить</button>
        </div>
    </div>
</div>
