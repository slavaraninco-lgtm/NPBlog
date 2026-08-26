<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно глобальных параметров
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * Поддерживает 11 разделов настроек:
 * 1. Фон статей (фон по умолчанию, режим растягивания, область, Powered by)
 * 2. Вид blog.html (заголовок страницы, фон каталога, мультиблоговая навигация)
 * 3. Автосохранение (интервал, вызов менеджера автосохранений)
 * 4. Внешний вид (AMOLED-тема, плавный курсор, нижняя панель, ширина контента, кастомизация кнопок)
 * 5. Экспериментальные функции (Undo/Redo, Markdown, проверка нумерации, сброс руководства, очистка шаблонов)
 * 6. RSS Виджет (готовый HTML/JS код для вставки на главную)
 * 7. RSS Лента (автогенерация XML feed, base URL, шаблоны элементов)
 * 8. Пути к блогам (мультиблог директории)
 * 9. Безопасность и доступ (защита паролем, ограничение по IP)
 * 10. SEO и соцсети (Open Graph / Twitter meta, базовый URL, дефолтное превью)
 * 11. Язык интерфейса (мгновенное переключение языка)
 * ==============================================================================
 */

if (!isset($availableLanguages)) {
    require_once __DIR__ . '/../lang_helper.php';
    $availableLanguages = getAvailableLanguages();
    $currentLanguage = getCurrentLanguage();
}
?>
<div id="globalSettingsModal" class="modal-overlay" data-size="xl">
    <div class="modal-dialog modal-xl" style="height: 85vh; max-height: 850px; display: flex; flex-direction: column;">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon icon-info">⚙️</span>
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="settings.title">Параметры</h3>
                    <p class="modal-subtitle" data-i18n="settings.subtitle">Глобальные настройки блога, интерфейса, безопасности и интеграций</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeGlobalSettings()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело: Слева боковая навигация, справа контент разделов -->
        <div class="modal-body" style="padding: 0; display: flex; flex: 1; overflow: hidden;">
            <!-- Навигация слева -->
            <div style="width: 210px; min-width: 210px; background: var(--modal-bg-subtle, rgba(0,0,0,0.03)); border-right: 1px solid var(--border-color); padding: 16px 12px; overflow-y: auto; display: flex; flex-direction: column; gap: 4px;">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-color); opacity: 0.5; padding: 4px 8px; margin-bottom: 4px;" data-i18n="settings.nav_title">Навигация</div>
                
                <button type="button" id="nav-btn-backgrounds" onclick="showGlobalSection('backgrounds')" class="global-nav-btn active" data-section="backgrounds" data-i18n="settings.nav_backgrounds" style="display: block; width: 100%; padding: 8px 12px; border: none; border-radius: 8px; cursor: pointer; text-align: left; font-size: 13px; color: var(--text-color); background: transparent; transition: all 0.15s ease;">
                    Фон статей
                </button>
                <button type="button" id="nav-btn-blogview" onclick="showGlobalSection('blogview')" class="global-nav-btn" data-section="blogview" data-i18n="settings.nav_blogview" style="display: block; width: 100%; padding: 8px 12px; border: none; border-radius: 8px; cursor: pointer; text-align: left; font-size: 13px; color: var(--text-color); background: transparent; transition: all 0.15s ease;">
                    Вид blog.html
                </button>
                <button type="button" onclick="showGlobalSection('autosave')" class="global-nav-btn" data-section="autosave" data-i18n="settings.nav_autosave" style="display: block; width: 100%; padding: 8px 12px; border: none; border-radius: 8px; cursor: pointer; text-align: left; font-size: 13px; color: var(--text-color); background: transparent; transition: all 0.15s ease;">
                    Автосохранение
                </button>
                <button type="button" onclick="showGlobalSection('appearance')" class="global-nav-btn" data-section="appearance" data-i18n="settings.nav_appearance" style="display: block; width: 100%; padding: 8px 12px; border: none; border-radius: 8px; cursor: pointer; text-align: left; font-size: 13px; color: var(--text-color); background: transparent; transition: all 0.15s ease;">
                    Внешний вид
                </button>
                <button type="button" onclick="showGlobalSection('experimental')" class="global-nav-btn" data-section="experimental" data-i18n="settings.nav_experimental" style="display: block; width: 100%; padding: 8px 12px; border: none; border-radius: 8px; cursor: pointer; text-align: left; font-size: 13px; color: var(--text-color); background: transparent; transition: all 0.15s ease;">
                    Экспериментальные
                </button>
                <button type="button" onclick="showGlobalSection('rss')" class="global-nav-btn" data-section="rss" data-i18n="settings.nav_rss" style="display: block; width: 100%; padding: 8px 12px; border: none; border-radius: 8px; cursor: pointer; text-align: left; font-size: 13px; color: var(--text-color); background: transparent; transition: all 0.15s ease;">
                    RSS Виджет
                </button>
                <button type="button" id="nav-btn-rss_feed" onclick="showGlobalSection('rss_feed')" class="global-nav-btn" data-section="rss_feed" data-i18n="settings.nav_rss_feed" style="display: block; width: 100%; padding: 8px 12px; border: none; border-radius: 8px; cursor: pointer; text-align: left; font-size: 13px; color: var(--text-color); background: transparent; transition: all 0.15s ease;">
                    RSS Лента
                </button>
                <button type="button" id="nav-btn-paths" onclick="showGlobalSection('paths')" class="global-nav-btn" data-section="paths" data-i18n="settings.nav_paths" style="display: block; width: 100%; padding: 8px 12px; border: none; border-radius: 8px; cursor: pointer; text-align: left; font-size: 13px; color: var(--text-color); background: transparent; transition: all 0.15s ease;">
                    Пути
                </button>
                <button type="button" id="nav-btn-security" onclick="showGlobalSection('security')" class="global-nav-btn" data-section="security" data-i18n="settings.nav_security" style="display: block; width: 100%; padding: 8px 12px; border: none; border-radius: 8px; cursor: pointer; text-align: left; font-size: 13px; color: var(--text-color); background: transparent; transition: all 0.15s ease;">
                    Безопасность
                </button>
                <button type="button" onclick="showGlobalSection('seo')" class="global-nav-btn" data-section="seo" data-i18n="settings.nav_seo" style="display: block; width: 100%; padding: 8px 12px; border: none; border-radius: 8px; cursor: pointer; text-align: left; font-size: 13px; color: var(--text-color); background: transparent; transition: all 0.15s ease;">
                    SEO и соцсети
                </button>
                <button type="button" id="nav-btn-language" onclick="showGlobalSection('language')" class="global-nav-btn" data-section="language" data-i18n="settings.nav_language" style="display: block; width: 100%; padding: 8px 12px; border: none; border-radius: 8px; cursor: pointer; text-align: left; font-size: 13px; color: var(--text-color); background: transparent; transition: all 0.15s ease;">
                    Язык
                </button>
            </div>
            
            <!-- Контент справа -->
            <div style="flex: 1; padding: 24px 28px; overflow-y: auto;">
                <div style="margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color);">
                    <h3 style="margin: 0; color: var(--text-color); font-size: 18px; font-weight: 600;" id="globalSectionTitle" data-i18n="settings.bg_section_title">Фон статей</h3>
                </div>
            
                <!-- Секция 1: Фон статей -->
                <div id="globalSection-backgrounds" class="global-section">
                    <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8; font-size: 13px;" data-i18n="settings.bg_desc">Загрузите фоновое изображение, которое будет применяться ко всем статьям по умолчанию.</p>
                    
                    <!-- Текущий глобальный фон -->
                    <div id="currentGlobalBackgroundInfo" style="display: none; margin-bottom: 20px; padding: 16px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--modal-bg-subtle, rgba(0,0,0,0.02));">
                        <p style="color: var(--text-color); margin-bottom: 10px; font-weight: 600; font-size: 13px;" data-i18n="settings.bg_current_title">Текущий глобальный фон:</p>
                        <img id="currentGlobalBackgroundPreview" src="" style="max-width: 200px; max-height: 140px; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 10px; object-fit: cover;">
                        <p style="color: var(--text-color); font-size: 13px; margin-bottom: 4px;" id="currentGlobalBackgroundName"></p>
                        <p style="color: var(--text-color); font-size: 12px; opacity: 0.7;" id="currentGlobalBackgroundMode"></p>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label class="modal-label" for="globalBackgroundInput" data-i18n="settings.bg_image_label">Фоновое изображение:</label>
                        <input type="file" id="globalBackgroundInput" accept="image/*" class="modal-input" style="margin-bottom: 14px;">
                        
                        <label class="modal-label" for="globalBackgroundMode" data-i18n="settings.bg_mode_label">Режим отображения:</label>
                        <select id="globalBackgroundMode" class="modal-select" style="margin-bottom: 14px;">
                            <option value="cover" data-i18n="settings.bg_mode_cover">Растянуть (cover)</option>
                            <option value="contain" data-i18n="settings.bg_mode_contain">По размеру (contain)</option>
                            <option value="repeat" data-i18n="settings.bg_mode_repeat">Замостить (repeat)</option>
                        </select>
                        
                        <label class="modal-label" for="globalBackgroundScope" data-i18n="settings.bg_scope_label">Область фона:</label>
                        <select id="globalBackgroundScope" class="modal-select" style="margin-bottom: 20px;">
                            <option value="content" data-i18n="settings.bg_scope_content">Только статья (920px)</option>
                            <option value="fullpage" data-i18n="settings.bg_scope_fullpage">Вся страница</option>
                        </select>
                        
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <button type="button" onclick="uploadGlobalBackground()" class="modal-btn modal-btn-primary" data-i18n="settings.bg_upload_btn">Загрузить фон</button>
                            <button type="button" onclick="removeGlobalBackground()" class="modal-btn modal-btn-secondary" data-i18n="settings.bg_remove_btn">Удалить фон</button>
                        </div>
                    </div>
                    
                    <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-color); margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none;">
                            <input type="checkbox" id="hidePoweredByCheckbox" onchange="savePoweredBySetting(this.checked)" style="width: 18px; height: 18px; cursor: pointer;">
                            <span style="color: var(--text-color); font-weight: 500; font-size: 13px;" data-i18n="settings.bg_hide_powered_by">Скрыть надпись "Powered by NPBlog" в статьях</span>
                        </label>
                    </div>
                    
                    <div style="padding: 14px; background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.4); border-radius: 8px; margin-top: 20px;">
                        <p style="color: var(--text-color); font-size: 13px; margin: 0; line-height: 1.5;" data-i18n="settings.bg_warning">
                            ⚠️ Глобальный фон применяется ко всем существующим статьям и будет автоматически применяться к новым статьям. Индивидуальные настройки фона статьи имеют приоритет над глобальным фоном.
                        </p>
                    </div>
                </div>
                
                <!-- Секция 2: Вид blog.html -->
                <div id="globalSection-blogview" class="global-section" style="display: none;">
                    <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8; font-size: 13px;" data-i18n="settings.blogview_desc">Настройте внешний вид страницы со списком статей (blog.html).</p>
                    
                    <div style="margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color);">
                        <label class="modal-label" for="blogPageTitle" data-i18n="settings.blogview_page_title_label">Заголовок страницы:</label>
                        <input type="text" id="blogPageTitle" placeholder="Блог" class="modal-input" style="margin-bottom: 14px;">
                        
                        <button type="button" onclick="saveBlogViewSettings()" class="modal-btn modal-btn-primary" data-i18n="settings.blogview_save_btn">Сохранить настройки</button>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <h4 style="margin: 0 0 14px 0; color: var(--text-color); font-size: 15px; font-weight: 600;" data-i18n="settings.blogview_bg_title">Фон страницы списка статей (blog.html)</h4>
                        
                        <!-- Текущий фон blog.html -->
                        <div id="currentBlogBackgroundInfo" style="display: none; margin-bottom: 20px; padding: 16px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--modal-bg-subtle, rgba(0,0,0,0.02));">
                            <p style="color: var(--text-color); margin-bottom: 10px; font-weight: 600; font-size: 13px;" data-i18n="settings.blogview_current_bg">Текущий фон списка статей:</p>
                            <img id="currentBlogBackgroundPreview" src="" style="max-width: 200px; max-height: 140px; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 10px; object-fit: cover;">
                            <p style="color: var(--text-color); font-size: 13px; margin-bottom: 4px;" id="currentBlogBackgroundName"></p>
                            <p style="color: var(--text-color); font-size: 12px; opacity: 0.7;" id="currentBlogBackgroundMode"></p>
                        </div>

                        <label class="modal-label" for="blogBackgroundInput" data-i18n="settings.bg_image_label">Фоновое изображение:</label>
                        <input type="file" id="blogBackgroundInput" accept="image/*" class="modal-input" style="margin-bottom: 14px;">
                        
                        <label class="modal-label" for="blogBackgroundMode" data-i18n="settings.bg_mode_label">Режим отображения:</label>
                        <select id="blogBackgroundMode" class="modal-select" style="margin-bottom: 20px;">
                            <option value="cover" data-i18n="settings.bg_mode_cover">Растянуть (cover)</option>
                            <option value="contain" data-i18n="settings.bg_mode_contain">По размеру (contain)</option>
                            <option value="repeat" data-i18n="settings.bg_mode_repeat">Замостить (repeat)</option>
                        </select>

                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <button type="button" onclick="uploadBlogBackground()" class="modal-btn modal-btn-primary" data-i18n="settings.bg_upload_btn">Загрузить фон</button>
                            <button type="button" onclick="removeBlogBackground()" class="modal-btn modal-btn-secondary" data-i18n="settings.bg_remove_btn">Удалить фон</button>
                        </div>
                    </div>

                    <div style="margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                        <h4 style="margin: 0 0 14px 0; color: var(--text-color); font-size: 15px; font-weight: 600;" data-i18n="settings.blogview_crossblog_title">Навигация между блогами</h4>
                        
                        <div id="crossBlogNavStatus" style="display: none; padding: 12px; background: rgba(255, 193, 7, 0.1); border-left: 4px solid #ffc107; margin-bottom: 15px; color: var(--text-color); font-size: 13px;" data-i18n="settings.blogview_crossblog_not_supported">
                            В этом блоге используется нестандартный шаблон. Вставка кнопок не поддерживается.
                        </div>

                        <div id="crossBlogNavEditor" style="display: none;">
                            <label style="display: flex; align-items: center; margin-bottom: 15px; cursor: pointer;">
                                <input type="checkbox" id="enableCrossBlogNav" onchange="toggleCrossBlogNavUI()" style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;">
                                <span style="color: var(--text-color); font-weight: 500; font-size: 14px;" data-i18n="settings.blogview_crossblog_enable">Кнопки к разным блогам</span>
                            </label>
                            
                            <div id="crossBlogNavList" style="display: none; margin-bottom: 15px; padding: 16px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--modal-bg-subtle, rgba(0,0,0,0.02));">
                                <p style="margin-bottom: 12px; opacity: 0.7; font-size: 13px;" data-i18n="settings.blogview_crossblog_hint">Добавьте кнопки, которые будут отображаться в шапке blog.html для быстрого перехода к другим вашим блогам.</p>
                                <div id="crossBlogNavItems" style="margin-bottom: 15px; display: flex; flex-direction: column; gap: 8px;"></div>
                                <button type="button" onclick="addCrossBlogNavItem()" class="modal-btn modal-btn-secondary" style="font-size: 12px;" data-i18n="settings.blogview_add_btn">+ Добавить кнопку</button>
                            </div>

                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <button type="button" onclick="saveCrossBlogNav('save')" class="modal-btn modal-btn-primary" data-i18n="settings.blogview_save_current">Сохранить для текущего блога</button>
                                <button type="button" onclick="saveCrossBlogNav('apply_all')" class="modal-btn modal-btn-secondary" data-i18n="settings.blogview_apply_all">Применить во всех блогах</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Секция 3: Автосохранение -->
                <div id="globalSection-autosave" class="global-section" style="display: none;">
                    <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8; font-size: 13px;" data-i18n="settings.autosave_desc">Настройте автоматическое сохранение статей во время редактирования.</p>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; margin-bottom: 18px; cursor: pointer;">
                            <input type="checkbox" id="autosaveEnabled" onchange="toggleAutosavePreview()" style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;">
                            <span style="color: var(--text-color); font-weight: 500; font-size: 14px;" data-i18n="settings.autosave_enable">Включить автосохранение</span>
                        </label>
                        
                        <label class="modal-label" for="autosaveInterval" data-i18n="settings.autosave_interval_label">Интервал автосохранения (секунды):</label>
                        <input type="number" id="autosaveInterval" min="10" max="600" value="60" class="modal-input" style="margin-bottom: 20px;">
                        
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <button type="button" onclick="saveAutosaveSettings()" class="modal-btn modal-btn-primary" data-i18n="settings.autosave_save_btn">Сохранить настройки</button>
                            <button type="button" onclick="openAutosaveManager()" class="modal-btn modal-btn-secondary" data-i18n="settings.autosave_manager_btn">Менеджер автосохранений</button>
                        </div>
                    </div>

                    <div style="padding: 14px; background: rgba(33, 150, 243, 0.1); border: 1px solid rgba(33, 150, 243, 0.3); border-radius: 8px; margin-top: 20px;">
                        <p style="color: var(--text-color); font-size: 13px; margin: 0; line-height: 1.5;" data-i18n="settings.autosave_hint">
                            💡 Автосохранение создает резервную копию вашей работы через заданный интервал времени. Все автосохранения доступны в менеджере.
                        </p>
                    </div>
                </div>
                
                <!-- Секция 4: Внешний вид -->
                <div id="globalSection-appearance" class="global-section" style="display: none;">
                    <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8; font-size: 13px;" data-i18n="settings.appearance_desc">Настройте внешний вид редактора статей.</p>
                    
                    <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" id="amoledTheme" style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;">
                            <span style="color: var(--text-color); font-weight: 500; font-size: 14px;" data-i18n="settings.app_amoled">Включить абсолютно черный фон (для AMOLED дисплеев)</span>
                        </label>
                        
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" id="smoothTyping" style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;">
                            <span style="color: var(--text-color); font-weight: 500; font-size: 14px;" data-i18n="settings.app_smooth_typing">Включить плавную печать текста (мягкий курсор)</span>
                        </label>
                        
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" id="headerBottomPosition" style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;">
                            <span style="color: var(--text-color); font-weight: 500; font-size: 14px;" data-i18n="settings.app_header_bottom">Переместить панель управления в низ экрана</span>
                        </label>

                        <div style="margin-top: 6px;">
                            <label class="modal-label" for="settingsContentWidth" data-i18n="settings.app_content_width">Ширина поля контента (в пикселях):</label>
                            <input type="number" id="settingsContentWidth" min="400" max="2500" placeholder="920" class="modal-input" style="max-width: 250px;">
                        </div>
                        
                        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;">
                            <button type="button" onclick="saveAppearanceSettings()" class="modal-btn modal-btn-primary" data-i18n="settings.app_save_btn">Сохранить настройки</button>
                            <button type="button" onclick="startHeaderCustomization()" class="modal-btn modal-btn-secondary" data-i18n="settings.app_customize_header_btn">Кастомизация верхней панели</button>
                        </div>
                    </div>
                    
                    <div style="padding: 14px; background: rgba(33, 150, 243, 0.1); border: 1px solid rgba(33, 150, 243, 0.3); border-radius: 8px; margin-top: 20px;">
                        <p style="color: var(--text-color); font-size: 13px; margin: 0; line-height: 1.5;" data-i18n="settings.app_hint">
                            💡 При скрытии кнопок переключения режимов редактор будет работать только в визуальном режиме.
                        </p>
                    </div>
                </div>
                
                <!-- Секция 5: Экспериментальные функции -->
                <div id="globalSection-experimental" class="global-section" style="display: none;">
                    <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8; font-size: 13px;" data-i18n="settings.exp_desc">Включите или отключите экспериментальные функции редактора.</p>
                    
                    <div style="margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color);">
                        <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 18px;">
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" id="enableUndoRedo" style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;">
                                <span style="color: var(--text-color); font-weight: 500; font-size: 14px;" data-i18n="settings.exp_undo_redo">Включить Undo/Redo (отмена/возврат изменений)</span>
                            </label>
                            
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" id="enableMarkdown" style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;">
                                <span style="color: var(--text-color); font-weight: 500; font-size: 14px;" data-i18n="settings.exp_markdown">Использовать Markdown</span>
                            </label>
                        </div>
                        
                        <button type="button" onclick="saveExperimentalSettings()" class="modal-btn modal-btn-primary" data-i18n="settings.exp_save_btn">Сохранить настройки</button>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <h4 style="margin: 0 0 12px 0; color: var(--text-color); font-size: 15px; font-weight: 600;" data-i18n="settings.exp_maint_title">Обслуживание и обучение</h4>
                        <p style="color: var(--text-color); margin-bottom: 14px; opacity: 0.8; font-size: 13px;" data-i18n="settings.exp_maint_desc">Запустите проверку целостности или сбросьте интерактивное руководство.</p>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <button type="button" onclick="checkPostNumbering()" class="modal-btn modal-btn-primary" data-i18n="settings.exp_check_num_btn">Проверка нумерации</button>
                            <button type="button" onclick="resetTutorial()" class="modal-btn modal-btn-secondary" data-i18n="settings.exp_reset_guide_btn">Сбросить обучение</button>
                            <button type="button" onclick="deleteAllCustomTemplates()" class="modal-btn modal-btn-danger" data-i18n="settings.exp_delete_templates_btn">Удалить кастомные шаблоны</button>
                        </div>
                    </div>
                    
                    <div style="padding: 14px; background: rgba(255, 152, 0, 0.1); border: 1px solid rgba(255, 152, 0, 0.4); border-radius: 8px; margin-top: 20px;">
                        <p style="color: var(--text-color); font-size: 13px; margin: 0; line-height: 1.5;" data-i18n="settings.exp_warning">
                            ⚠️ Экспериментальные функции могут работать нестабильно. Используйте на свой риск.
                        </p>
                    </div>
                </div>
                
                <!-- Секция 6: Интеграция RSS (Виджет) -->
                <div id="globalSection-rss" class="global-section" style="display: none;">
                    <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8; font-size: 13px;" data-i18n="settings.rss_desc">
                        Получите готовый код интерактивного виджета RSS ленты для вставки на главную страницу вашего сайта
                    </p>
                    
                    <!-- Интерактивное превью виджета -->
                    <div style="margin-bottom: 20px; padding: 16px; background: var(--modal-bg-subtle, rgba(0,0,0,0.02)); border: 1px dashed var(--border-color); border-radius: 10px;">
                        <span style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-color); opacity: 0.5; margin-bottom: 8px;" data-i18n="settings.rss_preview_title">Вид виджета</span>
                        <div id="rssLivePreviewContainer" style="min-height: 44px; display: flex; align-items: center;">
                            <div style="font-size: 13px; color: var(--text-color); opacity: 0.6; font-style: italic;" data-i18n="settings.rss_preview_loading">Загрузка превью виджета...</div>
                        </div>
                    </div>

                    <!-- Поля с кодом для вставки -->
                    <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 20px;">
                        <!-- Шаг 1: HTML код -->
                        <div style="display: flex; flex-direction: column; gap: 6px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <label class="modal-label" for="rssHtmlCode" style="margin: 0;" data-i18n="settings.rss_step1_label">Шаг 1: Вставьте этот HTML-код в место вывода виджета</label>
                                <button type="button" onclick="copyToClipboard('rssHtmlCode', this)" class="modal-btn modal-btn-secondary" style="padding: 4px 10px; font-size: 11px;" data-i18n="settings.rss_copy_html_btn">Копировать HTML</button>
                            </div>
                            <textarea id="rssHtmlCode" readonly class="modal-textarea" style="height: 60px; font-family: Consolas, Monaco, monospace; font-size: 12px; resize: none;"></textarea>
                        </div>

                        <!-- Шаг 2: JS код -->
                        <div style="display: flex; flex-direction: column; gap: 6px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <label class="modal-label" for="rssJsCode" style="margin: 0;" data-i18n="settings.rss_step2_label">Шаг 2: Вставьте этот JS-код в конец страницы (перед &lt;/body&gt;)</label>
                                <button type="button" onclick="copyToClipboard('rssJsCode', this)" class="modal-btn modal-btn-secondary" style="padding: 4px 10px; font-size: 11px;" data-i18n="settings.rss_copy_js_btn">Копировать JS</button>
                            </div>
                            <textarea id="rssJsCode" readonly class="modal-textarea" style="height: 240px; font-family: Consolas, Monaco, monospace; font-size: 12px; resize: none;"></textarea>
                        </div>
                    </div>

                    <div style="padding: 14px; background: rgba(33, 150, 243, 0.1); border: 1px solid rgba(33, 150, 243, 0.3); border-radius: 8px; margin-top: 20px;">
                        <p style="color: var(--text-color); font-size: 13px; margin: 0; line-height: 1.5;" data-i18n-html="settings.rss_styling_tip">
                            💡 <strong>Совет по стилизации:</strong> Вы можете полностью изменить внешний вид ссылки виджета на вашем сайте с помощью CSS стилей для класса <code>.npblog-rss-link</code>, прописав его в файле стилей вашего сайта.
                        </p>
                    </div>
                </div>
                
                <!-- Секция 7: RSS Лента (XML) -->
                <div id="globalSection-rss_feed" class="global-section" style="display: none;">
                    <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8; font-size: 13px;" data-i18n="settings.rss_feed_desc">Настройте автоматическую генерацию RSS ленты (XML-файла) для вашего блога.</p>
                    
                    <div style="margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border-color);">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" id="rssFeedEnabled" style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;">
                            <span style="color: var(--text-color); font-weight: 500; font-size: 14px;" data-i18n="settings.rss_feed_enable">Включить автоматическую генерацию RSS</span>
                        </label>
                        <p style="color: var(--text-color); opacity: 0.7; font-size: 12px; margin-top: 8px;" data-i18n-html="settings.rss_feed_enable_hint">
                            Если включено, файл <code>feed.xml</code> будет создаваться и обновляться автоматически в корне папки <code>data</code> при сохранении/редактировании/удалении статей.
                        </p>
                    </div>
                    
                    <div id="rssFeedSettingsDetails" style="display: none;">
                        <div style="margin-bottom: 16px;">
                            <label class="modal-label" for="rssFeedBaseUrl" data-i18n="settings.rss_feed_base_url_label">Базовый URL сайта (Base URL):</label>
                            <input type="text" id="rssFeedBaseUrl" placeholder="https://myblog.ru" class="modal-input">
                            <p style="color: var(--text-color); opacity: 0.7; font-size: 12px; margin-top: 4px;" data-i18n-html="settings.rss_feed_base_url_hint">
                                Необходим для формирования абсолютных URL-ссылок на ваши статьи в RSS-ленте (например, <code>https://myblog.ru</code>).
                            </p>
                        </div>

                        <div style="margin-bottom: 16px;">
                            <label class="modal-label" for="rssFeedTitle" data-i18n="settings.rss_feed_title_label">Название RSS-канала (Title):</label>
                            <input type="text" id="rssFeedTitle" placeholder="NPBlog Feed" class="modal-input">
                        </div>

                        <div style="margin-bottom: 16px;">
                            <label class="modal-label" for="rssFeedDescription" data-i18n="settings.rss_feed_desc_label">Описание RSS-канала (Description):</label>
                            <input type="text" id="rssFeedDescription" placeholder="NPBlog RSS Feed" class="modal-input">
                        </div>
                        
                        <div style="margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--border-color);">
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" id="rssFeedUseFirstLine" style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;">
                                <span style="color: var(--text-color); font-weight: 500; font-size: 14px;" data-i18n="settings.rss_feed_first_line">Брать только первую строку статьи в описание</span>
                            </label>
                            <p style="color: var(--text-color); opacity: 0.7; font-size: 12px; margin-top: 6px;" data-i18n="settings.rss_feed_first_line_hint">
                                Если включено, в содержание поста для RSS будет попадать только первая текстовая строка. Если выключено — будет передаваться весь HTML-код содержимого статьи.
                            </p>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <label class="modal-label" for="rssFeedContentTemplate" data-i18n="settings.rss_feed_template_label">Шаблон содержания элемента фида:</label>
                            <textarea id="rssFeedContentTemplate" class="modal-textarea" style="height: 100px; font-family: Consolas, Monaco, monospace; font-size: 12px;"></textarea>
                            <p style="color: var(--text-color); opacity: 0.7; font-size: 12px; margin-top: 6px; line-height: 1.4;" data-i18n-html="settings.rss_feed_template_hint">
                                Используйте плейсхолдеры для подстановки данных:<br>
                                <code>*content*</code> — Текст/HTML статьи (вся статья или только первая строка в зависимости от настройки выше).<br>
                                <code>*url*</code> — Полная ссылка на статью в блоге.
                            </p>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <button type="button" onclick="saveRssFeedSettings()" class="modal-btn modal-btn-primary" data-i18n="settings.rss_feed_save_btn">Сохранить настройки RSS</button>
                    </div>
                </div>

                <!-- Секция 8: Пути к блогам -->
                <div id="globalSection-paths" class="global-section" style="display: none;">
                    <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8; font-size: 13px;" data-i18n="settings.paths_desc">Настройте пути к директориям блогов на сервере.</p>
                    
                    <div id="blogPathsListContainer" style="margin-bottom: 20px; display: flex; flex-direction: column; gap: 8px;">
                        <!-- Динамически заполняется через JS -->
                    </div>
                    
                    <div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; align-items: center;">
                        <button type="button" onclick="addBlogPathRow()" class="modal-btn modal-btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                            <span>➕</span> <span data-i18n="settings.paths_add_btn">Добавить путь</span>
                        </button>
                        <button type="button" onclick="savePathsSettings()" class="modal-btn modal-btn-primary" data-i18n="settings.paths_save_btn">
                            Сохранить настройки путей
                        </button>
                    </div>
                    
                    <div style="padding: 14px; background: rgba(33, 150, 243, 0.1); border: 1px solid rgba(33, 150, 243, 0.3); border-radius: 8px;">
                        <p style="color: var(--text-color); font-size: 13px; margin: 0; line-height: 1.5;" data-i18n-html="settings.paths_hint">
                            💡 Укажите абсолютные пути к папкам данных блогов (например: <code>/var/www/html/data</code>). При добавлении нескольких путей переключение между блогами доступно в боковой панели «Управление статьями».
                        </p>
                    </div>
                </div>

                <!-- Секция 9: Безопасность и доступ -->
                <div id="globalSection-security" class="global-section" style="display: none;">
                    <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8; font-size: 13px;" data-i18n="settings.sec_desc">Настройте параметры безопасности и доступа редактора.</p>
                    
                    <div style="margin-bottom: 18px; padding-bottom: 18px; border-bottom: 1px solid var(--border-color);">
                        <!-- Вариант 1: Пароль не установлен -->
                        <div id="securityPasswordNotSet" style="display: block;">
                            <label style="display: flex; align-items: center; margin-bottom: 15px; cursor: pointer;">
                                <input type="checkbox" id="settingsPasswordEnabled" onchange="togglePasswordFieldsVisibility()" style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;">
                                <span style="color: var(--text-color); font-weight: 500; font-size: 14px;" data-i18n="settings.sec_pwd_enable">Включить защиту паролем</span>
                            </label>
                            
                            <div id="securityPasswordFields" style="display: none; margin-bottom: 18px; padding: 16px; background: var(--modal-bg-subtle, rgba(0,0,0,0.02)); border: 1px solid var(--border-color); border-radius: 10px;">
                                <div style="margin-bottom: 12px;">
                                    <label class="modal-label" for="settingsNewPassword" data-i18n="settings.sec_pwd_new">Новый пароль:</label>
                                    <input type="password" id="settingsNewPassword" placeholder="Введите новый пароль" class="modal-input">
                                </div>
                                <div>
                                    <label class="modal-label" for="settingsConfirmPassword" data-i18n="settings.sec_pwd_confirm">Подтверждение пароля:</label>
                                    <input type="password" id="settingsConfirmPassword" placeholder="Повторите новый пароль" class="modal-input">
                                </div>
                            </div>
                        </div>

                        <!-- Вариант 2: Пароль установлен -->
                        <div id="securityPasswordSet" style="display: none;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
                                <span style="color: var(--text-color); font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 6px;">
                                    <span>🔒</span> <span data-i18n="settings.sec_pwd_set_label">Пароль установлен</span>
                                </span>
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" onclick="showChangePasswordForm()" class="modal-btn modal-btn-secondary" style="font-size: 12px;" data-i18n="settings.sec_change_pwd_btn">Изменить пароль</button>
                                    <button type="button" onclick="showDisablePasswordForm()" class="modal-btn modal-btn-secondary" style="font-size: 12px; opacity: 0.85;" data-i18n="settings.sec_disable_pwd_btn">Отключить защиту</button>
                                </div>
                            </div>

                            <!-- Форма изменения пароля -->
                            <div id="changePasswordFormContainer" style="display: none; margin-bottom: 16px; padding: 16px; background: var(--modal-bg-subtle, rgba(0,0,0,0.02)); border: 1px solid var(--border-color); border-radius: 10px;">
                                <div style="margin-bottom: 12px;">
                                    <label class="modal-label" for="changeSettingsOldPassword" data-i18n="settings.sec_pwd_old">Старый пароль:</label>
                                    <input type="password" id="changeSettingsOldPassword" placeholder="Введите старый пароль" class="modal-input">
                                </div>
                                <div style="margin-bottom: 12px;">
                                    <label class="modal-label" for="changeSettingsNewPassword" data-i18n="settings.sec_pwd_new">Новый пароль:</label>
                                    <input type="password" id="changeSettingsNewPassword" placeholder="Введите новый пароль" class="modal-input">
                                </div>
                                <div>
                                    <label class="modal-label" for="changeSettingsConfirmPassword" data-i18n="settings.sec_pwd_confirm">Подтверждение нового пароля:</label>
                                    <input type="password" id="changeSettingsConfirmPassword" placeholder="Повторите новый пароль" class="modal-input">
                                </div>
                            </div>

                            <!-- Форма отключения пароля -->
                            <div id="disablePasswordFormContainer" style="display: none; margin-bottom: 16px; padding: 16px; background: var(--modal-bg-subtle, rgba(0,0,0,0.02)); border: 1px solid var(--border-color); border-radius: 10px;">
                                <div>
                                    <label class="modal-label" for="disableSettingsPassword" data-i18n="settings.sec_pwd_current_to_disable">Введите текущий пароль для отключения защиты:</label>
                                    <input type="password" id="disableSettingsPassword" placeholder="Введите ваш текущий пароль" class="modal-input">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 18px; padding-bottom: 18px; border-bottom: 1px solid var(--border-color);">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" id="settingsIpWhitelistEnabled" style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;">
                            <span style="color: var(--text-color); font-weight: 500; font-size: 14px;" data-i18n="settings.sec_ip_enable">Ограничить доступ по списку IP (allowed_ips.txt)</span>
                        </label>
                        <p style="color: var(--text-color); opacity: 0.7; font-size: 12px; margin-top: 6px;" data-i18n-html="settings.sec_ip_hint">
                            Если включено, доступ к редактору и всем его функциям будет разрешен только с IP-адресов, перечисленных в файле <code>allowed_ips.txt</code> в корне проекта. При включении ваш текущий IP-адрес будет автоматически добавлен в список, чтобы вы не потеряли доступ.
                        </p>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <button type="button" onclick="saveSecuritySettings()" class="modal-btn modal-btn-primary" data-i18n="settings.sec_save_btn">Сохранить настройки безопасности</button>
                    </div>
                    
                    <div style="padding: 14px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; margin-top: 20px;">
                        <p style="color: var(--text-color); font-size: 13px; margin: 0; line-height: 1.5;" data-i18n-html="settings.sec_notice">
                            ⚠️ <strong>Рекомендация по безопасности:</strong><br>
                            Для предотвращения прямого скачивания конфигурации из браузера, заблокируйте доступ к JSON-файлам в настройках вашего веб-сервера.
                        </p>
                    </div>
                </div>

                <!-- Секция 10: SEO и соцсети -->
                <div id="globalSection-seo" class="global-section" style="display: none;">
                    <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8; font-size: 13px;" data-i18n="settings.seo_desc">Настройте метатеги (Open Graph / Twitter Cards) для корректного отображения превью статей в Telegram, Discord и соцсетях.</p>
                    
                    <div style="margin-bottom: 16px;">
                        <label class="modal-label" for="seoBaseUrl" data-i18n="settings.seo_base_url_label">Базовый URL сайта (Base URL):</label>
                        <input type="text" id="seoBaseUrl" placeholder="https://myblog.ru" class="modal-input">
                        <p style="color: var(--text-color); opacity: 0.7; font-size: 12px; margin-top: 4px;" data-i18n-html="settings.seo_base_url_hint">
                            Необходим для генерации абсолютных URL-адресов статей и картинок (например: <code>https://myblog.ru</code>). Без этого соцсети не смогут корректно загрузить картинки.
                        </p>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label class="modal-label" for="seoDefaultImage" data-i18n="settings.seo_default_img_label">Изображение по умолчанию (URL или путь):</label>
                        <input type="text" id="seoDefaultImage" placeholder="https://myblog.ru/data/default-preview.png" class="modal-input">
                        <p style="color: var(--text-color); opacity: 0.7; font-size: 12px; margin-top: 4px;" data-i18n-html="settings.seo_default_img_hint">
                            Ссылка на изображение, которое будет использоваться для превью, если в статье нет картинок. Может быть абсолютной ссылкой или относительным путем (например: <code>data/default-preview.png</code>).
                        </p>
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label class="modal-label" for="seoDefaultDescription" data-i18n="settings.seo_default_desc_label">Описание по умолчанию (Default Description):</label>
                        <textarea id="seoDefaultDescription" placeholder="Интересные статьи о программировании и технологиях." class="modal-textarea" style="height: 80px;"></textarea>
                        <p style="color: var(--text-color); opacity: 0.7; font-size: 12px; margin-top: 4px;" data-i18n-html="settings.seo_default_desc_hint">
                            Описание, которое будет использоваться, если статья слишком короткая или не содержит текста.
                        </p>
                    </div>
                    
                    <div style="margin-bottom: 20px; padding: 12px 14px; background: rgba(33, 150, 243, 0.1); border: 1px solid rgba(33, 150, 243, 0.3); border-radius: 8px;">
                        <p style="color: var(--text-color); font-size: 13px; margin: 0; line-height: 1.5;" data-i18n-html="settings.seo_notice">
                            💡 <strong>Обратите внимание:</strong> При сохранении или обновлении статьи метатеги генерируются автоматически на основе её содержимого. Вы также можете перегенерировать метатеги для всех статей с помощью кнопки ниже.
                        </p>
                    </div>

                    <div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
                        <button type="button" onclick="saveSeoSettings()" class="modal-btn modal-btn-primary" data-i18n="settings.seo_save_btn">Сохранить настройки SEO</button>
                        <button type="button" onclick="regenerateAllPostsMeta(this)" class="modal-btn modal-btn-secondary" data-i18n="settings.seo_regen_btn">Перегенерировать метатеги статей</button>
                    </div>
                </div>

                <!-- Секция 11: Язык -->
                <div id="globalSection-language" class="global-section" style="display: none;">
                    <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8; font-size: 13px;" data-i18n="settings.lang_desc">Выберите язык интерфейса редактора NPBlog.</p>
                    
                    <div id="languageCardsContainer" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px;">
                        <?php foreach ($availableLanguages as $l): 
                            $isSel = ($l['code'] === $currentLanguage);
                            $borderStyle = $isSel ? 'var(--primary-color, #4CAF50)' : 'var(--border-color)';
                            $bgStyle = $isSel ? 'rgba(76, 175, 80, 0.08)' : 'var(--modal-bg-subtle, rgba(0,0,0,0.02))';
                        ?>
                        <div id="langCard-<?php echo htmlspecialchars($l['code']); ?>" data-lang-code="<?php echo htmlspecialchars($l['code']); ?>" class="lang-selection-card" onclick="selectLanguageOption('<?php echo htmlspecialchars($l['code']); ?>', true)" style="border: 2px solid <?php echo $borderStyle; ?>; border-radius: 12px; padding: 16px; cursor: pointer; background: <?php echo $bgStyle; ?>; transition: all 0.2s; position: relative; display: flex; flex-direction: column; gap: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="font-size: 26px;"><?php echo htmlspecialchars($l['smile']); ?></div>
                                <input type="radio" name="editor_lang_radio" id="langRadio-<?php echo htmlspecialchars($l['code']); ?>" value="<?php echo htmlspecialchars($l['code']); ?>" style="cursor: pointer; width: 18px; height: 18px;" onchange="selectLanguageOption('<?php echo htmlspecialchars($l['code']); ?>', true)" <?php echo $isSel ? 'checked' : ''; ?>>
                            </div>
                            <div style="font-size: 16px; font-weight: 700; color: var(--text-color); margin-top: 4px;"><?php echo htmlspecialchars($l['name']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Подвал -->
        <div class="modal-footer">
            <button type="button" onclick="closeGlobalSettings()" class="modal-btn modal-btn-ghost" data-modal-close data-i18n="common.close">Закрыть</button>
        </div>
    </div>
</div>
