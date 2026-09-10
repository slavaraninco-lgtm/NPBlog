<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальные окна менеджера шаблонов
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * Включает:
 *  1. Главный менеджер шаблонов (сетка карточек, загрузка zip/html, инструкция)
 *  2. Инструкция и спецификация базовых требований к HTML-шаблонам
 *  3. Детали шаблона (редактирование названия, описания, HTML-кода, живой предпросмотр в iframe, сохранение/удаление)
 *  4. Диалог выбора статьи для индивидуального применения шаблона
 * ==============================================================================
 */
?>
<!-- 1. Главный менеджер шаблонов -->
<div id="templateManagerDialog" class="modal-overlay" data-size="xl">
    <div class="modal-dialog modal-xl" style="height: 85vh; max-height: 900px; display: flex; flex-direction: column;">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.tpl_title">Менеджер шаблонов</h3>
                    <p class="modal-subtitle" data-i18n="modals.tpl_subtitle">Управление оформлением и HTML-шаблонами статей</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="showTemplateInstructions()" style="font-size: 13px;" data-i18n="modals.tpl_guide_btn">
                    ℹ️ Инструкция
                </button>
                <button type="button" class="modal-btn modal-btn-primary" onclick="triggerTemplateUpload()" style="font-size: 13px;" data-i18n="modals.tpl_upload_btn">
                    📥 Загрузить шаблон
                </button>
                <input type="file" id="templateFileInput" accept=".html,.htm,.zip" multiple style="display: none;" onchange="handleTemplateUpload(this)">
                <button type="button" class="modal-close-btn" onclick="closeTemplateManager()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Сетка шаблонов -->
        <div class="modal-body" style="padding: 24px; overflow-y: auto; flex: 1;">
            <div id="templatesGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px;">
                <!-- Карточки шаблонов генерируются динамически -->
            </div>
        </div>
    </div>
</div>

<!-- 2. Инструкция по шаблонам -->
<div id="templateInstructionsDialog" class="modal-overlay" data-size="lg">
    <div class="modal-dialog modal-lg" style="max-height: 85vh; display: flex; flex-direction: column;">
        <!-- Шапка -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon icon-info">ℹ️</span>
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.tpl_guide_title">Базовые требования к шаблону</h3>
                    <p class="modal-subtitle" data-i18n="modals.tpl_guide_subtitle">Спецификация обязательных плейсхолдеров, CSS и JS элементов</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeTemplateInstructions()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело инструкции -->
        <div class="modal-body" style="padding: 24px; overflow-y: auto; flex: 1; font-size: 14px; line-height: 1.6; color: var(--text-color);">
            <h4 style="margin-top: 0; margin-bottom: 10px; font-size: 15px; font-weight: 600; color: var(--accent-color, #4CAF50);" data-i18n="modals.tpl_req_title">1. Обязательные плейсхолдеры</h4>
            <p style="margin-bottom: 15px; opacity: 0.9;" data-i18n="modals.tpl_req_desc">Ваш HTML-шаблон должен содержать следующие плейсхолдеры. Если хотя бы одного из них нет, шаблон не загрузится:</p>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 13px;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                        <th style="padding: 8px;" data-i18n="modals.tpl_placeholder_col">Плейсхолдер</th>
                        <th style="padding: 8px;" data-i18n="modals.tpl_desc_col">Описание</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 8px; font-family: monospace; font-weight: bold; color: #d63384;">{{TITLE}}</td>
                        <td style="padding: 8px;" data-i18n-html="modals.tpl_ph_title_desc">Вставляет заголовок вашей статьи (встречается в &lt;title&gt; и &lt;h1&gt;)</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 8px; font-family: monospace; font-weight: bold; color: #d63384;">{{DATE}}</td>
                        <td style="padding: 8px;" data-i18n="modals.tpl_ph_date_desc">Дата публикации и последнего редактирования статьи</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 8px; font-family: monospace; font-weight: bold; color: #d63384;">{{POST_ID}}</td>
                        <td style="padding: 8px;" data-i18n="modals.tpl_ph_post_id_desc">Идентификатор статьи (записывается в метатег post-id)</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 8px; font-family: monospace; font-weight: bold; color: #d63384;">{{CONTENT}}</td>
                        <td style="padding: 8px;" data-i18n="modals.tpl_ph_content_desc">Содержимое статьи (HTML-код, сформированный визуальным редактором)</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 8px; font-family: monospace; font-weight: bold; color: #d63384;">{{META_TAGS}}</td>
                        <td style="padding: 8px;" data-i18n="modals.tpl_ph_meta_desc">SEO-метатеги (description, OpenGraph для репостов, Twitter Cards)</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 8px; font-family: monospace; font-weight: bold; color: #d63384;">{{CUSTOM_FONTS}}</td>
                        <td style="padding: 8px;" data-i18n="modals.tpl_ph_fonts_desc">Кастомные шрифты (правила @font-face, загруженные через панель)</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 8px; font-family: monospace; font-weight: bold; color: #d63384;">{{BODY_STYLE}}</td>
                        <td style="padding: 8px;" data-i18n-html="modals.tpl_ph_body_style_desc">Индивидуальный стиль страницы/фона для тега &lt;body&gt;</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 8px; font-family: monospace; font-weight: bold; color: #d63384;">{{CONTENT_WRAPPER_START}}</td>
                        <td style="padding: 8px;" data-i18n="modals.tpl_ph_wrapper_start_desc">Начало блоков подложки и фонового оверлея</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 8px; font-family: monospace; font-weight: bold; color: #d63384;">{{CONTENT_WRAPPER_END}}</td>
                        <td style="padding: 8px;" data-i18n="modals.tpl_ph_wrapper_end_desc">Конец блоков подложки и фонового оверлея</td>
                    </tr>
                </tbody>
            </table>

            <h4 style="margin-top: 0; margin-bottom: 10px; font-size: 15px; font-weight: 600; color: var(--accent-color, #4CAF50);" data-i18n="modals.tpl_css_req_title">2. CSS-требования (Стилизация элементов)</h4>
            <p style="margin-bottom: 10px;" data-i18n="modals.tpl_css_req_intro">Для корректного отображения всех функций редактора в шаблон рекомендуется подключить стандартный файл стилей:</p>
            <pre style="background: #272822; color: #f8f8f2; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 12px; margin-bottom: 15px; overflow-x: auto;">&lt;link rel="stylesheet" href="assets/blog-post.css?v=1.0.6"&gt;</pre>
            <p style="margin-bottom: 10px;" data-i18n="modals.tpl_css_req_custom">Если вы пишете свои стили с нуля, убедитесь, что реализовали оформление для следующих классов:</p>
            <ul style="padding-left: 20px; margin-bottom: 25px; display: flex; flex-direction: column; gap: 8px;">
                <li><strong>Таблицы</strong> (классы `.content table`, `th`, `td`): границы, отступы, выравнивание текста влево.</li>
                <li><strong>Спойлеры / Сворачиваемые списки</strong>: стилизация тегов `.spoiler-block`, `.spoiler-title` (курсор `pointer`, треугольный маркер) и `.spoiler-content` (анимация появления).</li>
                <li><strong>Блоки кода</strong> (`.code-block`): фоновый цвет, monospace шрифт, горизонтальный скролл (`overflow-x: auto`), оформление плашки языка через псевдоэлемент `.code-block::before` с `content: attr(data-language)`.</li>
                <li><strong>Кнопка скачивания файла</strong> (`.blog-file-button`, `.blog-file-icon`, `.blog-file-name`, `.blog-file-size`): гибкий флекс-контейнер со стилизованными текстами и иконкой.</li>
                <li><strong>ASCII-арт</strong> (`.blog-ascii-wrap`, `.blog-ascii-art`): сохранение пробелов и переносов строк (`white-space: pre`), прокрутка.</li>
                <li><strong>Маркеры / Текстовыделитель</strong> (`mark`): стили выделений (`[data-marker-style="rough"]`, wavy, zigzag, straight) и цвета маркера (желтый, зеленый, синий, розовый и др.).</li>
            </ul>

            <h4 style="margin-top: 0; margin-bottom: 10px; font-size: 15px; font-weight: 600; color: var(--accent-color, #4CAF50);" data-i18n="modals.tpl_js_req_title">3. JS-требования (Интерактив)</h4>
            <p style="margin-bottom: 10px;" data-i18n="modals.tpl_js_req_intro">Для работы интерактивных элементов (смена темы оформления, просмотр картинок в полноэкранном модальном окне с зумом) подключите скрипт:</p>
            <pre style="background: #272822; color: #f8f8f2; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 12px; margin-bottom: 15px; overflow-x: auto;">&lt;script src="assets/blog-post.js" defer&gt;&lt;/script&gt;</pre>
            <p style="margin-bottom: 10px;" data-i18n="modals.tpl_js_req_modal_desc">А также скопируйте из стандартного шаблона структуру полноэкранного модального окна для просмотра картинок:</p>
            <pre style="background: #272822; color: #f8f8f2; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 11px; margin-bottom: 15px; overflow-x: auto; max-height: 200px; overflow-y: auto;">&lt;div class="image-modal" id="imageModal"&gt;
    &lt;button class="image-modal-close" onclick="closeImageModal()"&gt;×&lt;/button&gt;
    &lt;div class="image-modal-container" id="imageContainer"&gt;
        &lt;img class="image-modal-content" id="modalImage" src="" alt=""&gt;
    &lt;/div&gt;
    &lt;div class="image-modal-toolbar"&gt;
        &lt;button class="image-modal-btn" onclick="zoomOut()"&gt;−&lt;/button&gt;
        &lt;div class="image-modal-zoom-level" id="zoomLevel"&gt;100%&lt;/div&gt;
        &lt;button class="image-modal-btn" onclick="zoomIn()"&gt;+&lt;/button&gt;
        &lt;button class="image-modal-btn" onclick="resetZoom()"&gt;⟲&lt;/button&gt;
        &lt;button class="image-modal-btn" onclick="downloadImage()"&gt;⬇&lt;/button&gt;
    &lt;/div&gt;
&lt;/div&gt;</pre>
        </div>

        <!-- Подвал -->
        <div class="modal-footer">
            <button type="button" onclick="closeTemplateInstructions()" class="modal-btn modal-btn-primary" data-modal-close data-i18n="common.got_it">Понятно</button>
        </div>
    </div>
</div>

<!-- 3. Детали шаблона -->
<div id="templateDetailsDialog" class="modal-overlay" data-size="xl">
    <div class="modal-dialog modal-xl" style="height: 88vh; max-height: 900px; display: flex; flex-direction: column;">
        <!-- Шапка -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon icon-info">⚙️</span>
                <div class="modal-titles">
                    <h3 class="modal-title" id="detailsTemplateTitle" data-i18n="modals.tpl_details_title">Детали шаблона</h3>
                    <p class="modal-subtitle" data-i18n="modals.tpl_details_subtitle">Настройка параметров, исходного HTML и предпросмотр</p>
                </div>
            </div>
            <div class="modal-header-actions" style="position: relative;">
                <button type="button" class="modal-btn modal-btn-ghost" onclick="closeTemplateDetails()" data-modal-close data-i18n="common.cancel">Отмена</button>
                <button type="button" id="deleteTemplateBtn" class="modal-btn modal-btn-danger" onclick="deleteCurrentTemplate()" style="display: none;" data-i18n="common.delete">Удалить</button>
                
                <div style="position: relative; display: inline-block;">
                    <button type="button" class="modal-btn modal-btn-primary" id="saveTemplateDropdownBtn" onclick="toggleSaveTemplateDropdown()" style="display: flex; align-items: center; gap: 6px;" data-i18n="common.save_dropdown">
                        Сохранить ▾
                    </button>
                    <div id="saveTemplateDropdownMenu" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 6px; background: var(--modal-bg, #fff); border: 1px solid var(--border-color, #ccc); border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.25); min-width: 260px; z-index: 1030; flex-direction: column; overflow: hidden;">
                        <button type="button" class="dropdown-item" onclick="saveAndApplyTemplateToAll()" style="padding: 12px 16px; text-align: left; background: none; border: none; cursor: pointer; font-size: 13px; border-bottom: 1px solid var(--border-color); width: 100%;" data-i18n-html="modals.tpl_apply_all_btn">
                            Применить ко всем статьям<br><small style="opacity:0.6;">(и сделать шаблоном по умолчанию)</small>
                        </button>
                        <button type="button" class="dropdown-item" onclick="showApplyToSpecificPostList()" style="padding: 12px 16px; text-align: left; background: none; border: none; cursor: pointer; font-size: 13px; width: 100%;" data-i18n="modals.tpl_apply_post_btn">
                            Применить к определенной статье...
                        </button>
                    </div>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeTemplateDetails()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>
        
        <!-- Тело редактора деталей -->
        <div class="modal-body" style="flex: 1; display: flex; overflow: hidden; padding: 0;">
            <!-- Левая колонка: Предпросмотр -->
            <div style="flex: 1; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; background: var(--modal-bg-subtle, rgba(0,0,0,0.02)); position: relative;">
                <div style="padding: 10px 16px; font-size: 11px; font-weight: bold; opacity: 0.7; border-bottom: 1px solid var(--border-color); background: var(--modal-bg); color: var(--text-color);" data-i18n="modals.tpl_preview_label">ПРЕДПРОСМОТР ШАБЛОНА</div>
                <div style="flex: 1; position: relative; padding: 0;">
                    <iframe id="templatePreviewIframe" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
            </div>
            
            <!-- Правая колонка: Детали, описание, код -->
            <div style="width: 480px; display: flex; flex-direction: column; padding: 20px; overflow-y: auto; gap: 14px; background: var(--modal-bg); box-sizing: border-box;">
                <div>
                    <label class="modal-label" for="detailsTemplateNameInput" data-i18n="modals.tpl_name_label">Название шаблона</label>
                    <input type="text" id="detailsTemplateNameInput" class="modal-input" placeholder="Например: Минималистичный" data-i18n-placeholder="modals.tpl_name_ph">
                </div>
                <div>
                    <label class="modal-label" for="detailsTemplateDescriptionInput" data-i18n="modals.tpl_desc_label">Описание шаблона</label>
                    <textarea id="detailsTemplateDescriptionInput" class="modal-textarea" style="height: 70px; resize: none;" placeholder="Краткое описание стилей и особенностей шаблона..." data-i18n-placeholder="modals.tpl_desc_ph"></textarea>
                </div>
                <div style="flex: 1; display: flex; flex-direction: column; min-height: 250px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span class="modal-label" style="margin-bottom: 0;" data-i18n="modals.tpl_code_label">HTML-код шаблона</span>
                        <a href="#" onclick="showTemplatePlaceholdersInfo(event)" style="font-size: 11px; color: var(--accent-color, #4CAF50); text-decoration: underline;" data-i18n="modals.tpl_placeholders_link">Доступные плейсхолдеры</a>
                    </div>
                    <textarea id="detailsTemplateCodeInput" class="modal-textarea" style="flex: 1; width: 100%; font-family: Consolas, Monaco, monospace; font-size: 12px; line-height: 1.4; padding: 10px; background: #272822; color: #f8f8f2; resize: none; tab-size: 4;" oninput="updateTemplateLivePreview()"></textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 4. Выбор статьи для применения шаблона -->
<div id="applyToPostModal" class="modal-overlay" data-size="sm">
    <div class="modal-dialog modal-sm" style="max-height: 75vh; display: flex; flex-direction: column;">
        <!-- Шапка -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon icon-info">📄</span>
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.tpl_apply_title">Применить к статье</h3>
                    <p class="modal-subtitle" data-i18n="modals.tpl_apply_subtitle">Выберите статью для применения шаблона</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeApplyToPostModal()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Поиск статьи -->
        <div style="padding: 16px 20px 0;">
            <input type="text" id="templatePostSearchInput" class="modal-input" placeholder="🔍 Поиск статьи..." data-i18n-placeholder="modals.tpl_search_posts_ph" oninput="filterTemplatePosts()" style="width: 100%;">
        </div>

        <!-- Список статей -->
        <div class="modal-body" id="templatePostList" style="padding: 12px 20px 20px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 8px;">
            <!-- Список статей генерируется динамически -->
        </div>

        <!-- Подвал -->
        <div class="modal-footer">
            <button type="button" onclick="closeApplyToPostModal()" class="modal-btn modal-btn-ghost" data-modal-close data-i18n="common.cancel">Отмена</button>
        </div>
    </div>
</div>
