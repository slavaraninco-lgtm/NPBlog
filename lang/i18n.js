/**
 * NPBlog Editor - Internationalization (i18n) Engine
 * Dynamic localization engine parsing languages from lang/languages.json
 */
(function(window, document) {
    'use strict';

    var currentLang = 'ru';
    var dict = {};
    var loadedDictionaries = {};
    var availableLanguages = [];
    var isInitialized = false;
    var ruStringToKeyMap = {};

    var knownNotificationPhrases = {
        "Статья успешно сохранена!": "notifications.saved",
        "Ошибка при сохранении статьи": "notifications.save_error",
        "Статья успешно удалена!": "notifications.deleted",
        "Ошибка при удалении статьи": "notifications.delete_error",
        "Черновик успешно сохранён!": "notifications.draft_saved",
        "Локальный черновик успешно восстановлен!": "notifications.draft_restored",
        "Строка добавлена": "notifications.row_added",
        "Строка удалена": "notifications.row_deleted",
        "Столбец добавлен": "notifications.col_added",
        "Столбец удален": "notifications.col_deleted",
        "Таблица добавлена": "notifications.table_added",
        "Таблица удалена": "notifications.table_deleted",
        "Цвет ячейки изменен": "notifications.cell_colored",
        "Введите URL ссылки": "notifications.enter_link_url",
        "Файл не является изображением": "notifications.not_an_image",
        "Файл изображения слишком большой (максимум 25 МБ)": "notifications.img_too_big",
        "Загрузка изображения из буфера обмена...": "notifications.clipboard_uploading",
        "Изображение успешно вставлено из буфера обмена": "notifications.clipboard_success",
        "Ошибка при загрузке изображения из буфера обмена": "notifications.clipboard_error",
        "Выберите хотя бы одно изображение для загрузки": "notifications.select_image",
        "Видео файл загружен": "notifications.video_uploaded",
        "Аудио файл загружен": "notifications.audio_uploaded",
        "Аудио файл добавлен в статью": "notifications.audio_added",
        "Аудио файл удален": "notifications.audio_deleted",
        "Видео файл удален": "notifications.video_deleted",
        "Вы уверены, что хотите удалить эту статью?": "notifications.confirm_delete_post",
        "Подтверждение удаления": "notifications.confirm_delete_title",
        "Нельзя удалить последнюю строку таблицы": "notifications.table_cant_delete_last_row",
        "Нельзя удалить строку заголовка": "notifications.table_cant_delete_header_row",
        "Нельзя удалить единственный столбец": "notifications.table_cant_delete_last_col",
        "Введите количество строк от 1 до 20": "notifications.table_rows_range",
        "Введите количество столбцов от 1 до 7": "notifications.table_cols_range",
        "Введите URL изображения (можно несколько — каждое с новой строки или через запятую)": "notifications.img_enter_urls",
        "Ошибка сети при загрузке изображений": "notifications.imgs_network_error",
        "Ошибка при загрузке изображения": "notifications.img_general_error",
        "Пожалуйста, введите размер от 8 до 72 пикселей": "notifications.font_size_range",
        "Пожалуйста, выберите видео файл": "notifications.video_select_file",
        "Ошибка загрузки файла": "notifications.file_upload_error",
        "Пожалуйста, выберите аудио файл": "notifications.audio_select_file",
        "Пожалуйста, введите URL видео": "notifications.video_enter_url",
        "Пожалуйста, введите URL аудиопотока": "notifications.audio_stream_enter_url",
        "Удалить аудио файл?": "notifications.confirm_delete_audio",
        "Ошибка удаления файла": "notifications.file_delete_error",
        "Видео файл добавлен в статью": "notifications.video_added",
        "Удалить видео файл?": "notifications.confirm_delete_video",
        "Выделите текст для применения маркера": "notifications.marker_select_text",
        "Пожалуйста, введите код": "notifications.code_enter",
        "Пожалуйста, введите заголовок статьи": "notifications.post_enter_title",
        "Пожалуйста, введите название шрифта": "notifications.font_enter_name",
        "Все ошибки успешно исправлены!": "notifications.integrity_all_fixed",
        "Ошибка при исправлении файлов": "notifications.integrity_fix_error",
        "Ошибка при просмотре бэкапа": "notifications.backup_view_error",
        "Вы уверены, что хотите восстановить этот бэкап? Текущее содержимое редактора будет заменено.": "notifications.backup_restore_confirm",
        "Восстановление бэкапа...": "notifications.backup_restoring",
        "Бэкап успешно восстановлен": "notifications.backup_restored",
        "Ошибка при восстановлении бэкапа": "notifications.backup_restore_error",
        "Удаление бэкапа...": "notifications.backup_deleting",
        "Бэкап успешно удален": "notifications.backup_deleted",
        "Ошибка при удалении бэкапа": "notifications.backup_delete_error",
        "Введите название файла": "notifications.include_enter_filename",
        "Нет контента для сохранения": "notifications.include_no_content",
        "Ошибка при сохранении include файла": "notifications.include_save_error",
        "Ошибка при удалении include файла": "notifications.include_delete_error",
        "Выберите include файл для вставки": "notifications.include_select_file",
        "Ошибка при загрузке include файла": "notifications.include_load_error",
        "Автосохранение выполнено": "notifications.autosave_completed",
        "Автосохранение загружено": "notifications.autosave_loaded",
        "Автосохранение удалено": "notifications.autosave_deleted",
        "Все автосохранения успешно удалены": "notifications.autosave_deleted_all",
        "Ошибка при автосохранении": "notifications.autosave_save_error",
        "Ошибка при загрузке автосохранения": "notifications.autosave_load_error",
        "Ошибка при удалении автосохранения": "notifications.autosave_delete_error",
        "Ошибка при удалении всех автосохранений": "notifications.autosave_delete_all_error",
        "Настройки автосохранения успешно сохранены!": "notifications.autosave_settings_saved",
        "Ошибка при сохранении настроек автосохранения": "notifications.autosave_settings_error",
        "Удаление кастомных шаблонов...": "notifications.custom_templates_deleting",
        "Все кастомные шаблоны успешно удалены": "notifications.custom_templates_deleted_all",
        "Ошибка при удалении кастомных шаблонов": "notifications.custom_templates_delete_error",
        "Ошибка при сохранении шаблона": "notifications.template_save_error",
        "Ошибка при удалении шаблона": "notifications.template_delete_error",
        "Ошибка при применении шаблона": "notifications.template_apply_error",
        "Ошибка при загрузке шаблона": "notifications.template_load_error",
        "Пожалуйста, введите название шаблона": "notifications.template_enter_name",
        "Выберите шаблон для применения": "notifications.template_select_apply",
        "Выберите шаблон для удаления": "notifications.template_select_delete",
        "Нельзя удалить встроенный шаблон": "notifications.template_cant_delete_built_in",
        "Ошибка при загрузке смайлов": "notifications.smiles_upload_error",
        "Пожалуйста, выберите файл со смайлами": "notifications.smiles_select_file",
        "Ошибка при удалении набора смайлов": "notifications.smiles_set_delete_error",
        "Нельзя удалить стандартный набор смайлов": "notifications.smiles_cant_delete_default",
        "Глобальный фон успешно сохранен!": "notifications.bg_global_saved",
        "Ошибка при сохранении глобального фона": "notifications.bg_global_save_error",
        "Глобальный фон успешно удален!": "notifications.bg_global_removed",
        "Ошибка при удалении глобального фона": "notifications.bg_global_remove_error",
        "Фон блога успешно сохранен!": "notifications.bg_blog_saved",
        "Ошибка при сохранении фона блога": "notifications.bg_blog_save_error",
        "Фон блога успешно удален!": "notifications.bg_blog_removed",
        "Ошибка при удалении фона блога": "notifications.bg_blog_remove_error",
        "Фон статьи успешно сохранен!": "notifications.bg_post_saved",
        "Ошибка при сохранении фона статьи": "notifications.bg_post_save_error",
        "Фон статьи успешно удален!": "notifications.bg_post_removed",
        "Ошибка при удалении фона статьи": "notifications.bg_post_remove_error",
        "Стили фона успешно сохранены!": "notifications.bg_styles_saved",
        "Ошибка при сохранении стилей фона": "notifications.bg_styles_save_error",
        "Пожалуйста, выберите изображение для фона": "notifications.bg_select_image",
        "Вы уверены, что хотите удалить глобальный фон?": "notifications.bg_confirm_remove_global",
        "Вы уверены, что хотите удалить фон блога?": "notifications.bg_confirm_remove_blog",
        "Вы уверены, что хотите удалить фон этой статьи?": "notifications.bg_confirm_remove_post",
        "Настройки успешно сохранены!": "notifications.settings_saved",
        "Ошибка при сохранении настроек": "notifications.settings_save_error",
        "Настройки вида блога успешно сохранены!": "notifications.blog_view_settings_saved",
        "Ошибка при сохранении настроек вида блога": "notifications.blog_view_settings_error",
        "Настройки путей успешно сохранены!": "notifications.paths_saved",
        "Ошибка при сохранении настроек путей": "notifications.paths_save_error",
        "Настройки безопасности успешно сохранены!": "notifications.security_saved",
        "Ошибка при сохранении настроек безопасности": "notifications.security_save_error",
        "Настройки SEO успешно сохранены!": "notifications.seo_saved",
        "Ошибка при сохранении настроек SEO": "notifications.seo_save_error",
        "Ошибка при перегенерации метатегов": "notifications.seo_regen_error",
        "Перегенерация метатегов...": "notifications.seo_regen_in_progress",
        "HTML-код виджета скопирован в буфер обмена!": "notifications.rss_copied_html",
        "JS-код виджета скопирован в буфер обмена!": "notifications.rss_copied_js",
        "Не удалось скопировать код": "notifications.rss_copy_error",
        "Настройки RSS ленты успешно сохранены!": "notifications.rss_feed_saved",
        "Ошибка при сохранении настроек RSS ленты": "notifications.rss_feed_save_error",
        "Тема успешно применена и сохранена!": "notifications.theme_saved",
        "Тема применена локально": "notifications.theme_applied_local",
        "Кастомная тема загружена!": "notifications.theme_custom_loaded",
        "Кастомный CSS код применен и сохранен!": "notifications.theme_custom_css_saved",
        "Ошибка при сохранении кастомного CSS": "notifications.theme_custom_css_error",
        "Шрифт успешно загружен": "notifications.font_uploaded",
        "Ошибка при загрузке шрифта": "notifications.font_upload_error",
        "Шрифт успешно удален": "notifications.font_deleted",
        "Ошибка при удалении шрифта": "notifications.font_delete_error",
        "Шрифт успешно применен": "notifications.font_applied",
        "Сессия успешно восстановлена! Теперь вы можете сохранить вашу работу.": "notifications.session_restored",
        "Не удалось восстановить сессию": "notifications.session_restore_error",
        "Скопировано в буфер обмена!": "notifications.copy_success",
        "Не удалось скопировать": "notifications.copy_error",
        "Ошибка сети": "notifications.network_error",
        "Неизвестная ошибка": "notifications.unknown_error",
        "Язык интерфейса успешно изменён!": "settings.lang_success",
        "Успешно": "common.success",
        "Ошибка": "common.error",
        "Внимание": "common.warning",
        "Информация": "common.info",
        "Уведомление": "common.info",
        "Подтверждение": "common.confirm",
        "Success": "common.success",
        "Error": "common.error",
        "Warning": "common.warning",
        "Info": "common.info"
    };

    var paramPatterns = [
        {
            regex: /^Ошибка:\s*(.+)$/,
            key: 'notifications.error_with_param',
            params: function(m) { return { error: m[1] }; }
        },
        {
            regex: /^Ошибка сохранения:\s*(.+)$/,
            key: 'notifications.image_save_error_param',
            params: function(m) { return { error: m[1] }; }
        },
        {
            regex: /^Ошибка при загрузке изображения:\s*(.+)$/,
            key: 'notifications.img_upload_error_param',
            params: function(m) { return { error: m[1] }; }
        },
        {
            regex: /^Ошибка при загрузке изображений:\s*(.+)$/,
            key: 'notifications.imgs_upload_error_param',
            params: function(m) { return { error: m[1] }; }
        },
        {
            regex: /^Ошибка при загрузке статьи:\s*(.+)$/,
            key: 'notifications.post_load_error_param',
            params: function(m) { return { error: m[1] }; }
        },
        {
            regex: /^Ошибка обновления:\s*(.+)$/,
            key: 'notifications.update_error_param',
            params: function(m) { return { error: m[1] }; }
        },
        {
            regex: /^Файл\s*["“](.+?)["”]\s*не является изображением$/,
            key: 'notifications.file_not_image_param',
            params: function(m) { return { filename: m[1] }; }
        },
        {
            regex: /^Файл\s*["“](.+?)["”]\s*слишком большой\s*\((.+?)\s*МБ\)\.\s*Максимум 25 МБ\.$/,
            key: 'notifications.file_too_big_param',
            params: function(m) { return { filename: m[1], size: m[2] }; }
        },
        {
            regex: /^Видео файл слишком большой\s*\((.+?)\s*МБ\)\.\s*Максимальный размер:\s*100 МБ\.$/,
            key: 'notifications.video_too_big_param',
            params: function(m) { return { size: m[1] }; }
        },
        {
            regex: /^Аудио файл слишком большой\s*\((.+?)\s*МБ\)\.\s*Максимальный размер:\s*50 МБ\.$/,
            key: 'notifications.audio_too_big_param',
            params: function(m) { return { size: m[1] }; }
        },
        {
            regex: /^Шаблон\s*["“](.+?)["”]\s*успешно сохранен!$/,
            key: 'notifications.template_saved_param',
            params: function(m) { return { name: m[1] }; }
        },
        {
            regex: /^Шаблон\s*["“](.+?)["”]\s*успешно удален!$/,
            key: 'notifications.template_deleted_param',
            params: function(m) { return { name: m[1] }; }
        },
        {
            regex: /^Шаблон\s*["“](.+?)["”]\s*успешно применен!$/,
            key: 'notifications.template_applied_param',
            params: function(m) { return { name: m[1] }; }
        },
        {
            regex: /^Вы уверены,\s*что хотите удалить шаблон\s*["“](.+?)["”]\?$/,
            key: 'notifications.template_confirm_delete_param',
            params: function(m) { return { name: m[1] }; }
        },
        {
            regex: /^Include\s*["“](.+?)["”]\s*успешно удален!$/,
            key: 'notifications.include_deleted_param',
            params: function(m) { return { name: m[1] }; }
        },
        {
            regex: /^Include\s*["“](.+?)["”]\s*успешно вставлен в статью$/,
            key: 'notifications.include_inserted_param',
            params: function(m) { return { name: m[1] }; }
        },
        {
            regex: /^Include сохранен:\s*(.+)$/,
            key: 'notifications.include_saved_param',
            params: function(m) { return { name: m[1] }; }
        },
        {
            regex: /^Набор смайлов\s*["“](.+?)["”]\s*успешно удален!$/,
            key: 'notifications.smiles_set_deleted_param',
            params: function(m) { return { name: m[1] }; }
        },
        {
            regex: /^Вы уверены,\s*что хотите удалить набор смайлов\s*["“](.+?)["”]\?$/,
            key: 'notifications.smiles_confirm_delete_set_param',
            params: function(m) { return { name: m[1] }; }
        },
        {
            regex: /^Загружено смайлов:\s*(\d+)$/,
            key: 'notifications.smiles_upload_success_param',
            params: function(m) { return { count: m[1] }; }
        },
        {
            regex: /^Метатеги перегенерированы для\s*(\d+)\s*статей!$/,
            key: 'notifications.seo_regen_completed_param',
            params: function(m) { return { count: m[1] }; }
        },
        {
            regex: /^Выбран блог:\s*(.+)$/,
            key: 'notifications.blog_selected_param',
            params: function(m) { return { name: m[1] }; }
        },
        {
            regex: /^Удалить это\s*(.+?)\?$/,
            key: 'notifications.confirm_delete_element_param',
            params: function(m) { return { label: m[1] }; }
        },
        {
            regex: /^(.+?)\s*добавлен\.\s*Перетащите его в нужное место\.$/,
            key: 'notifications.tb_item_added_param',
            params: function(m) { return { label: m[1] }; }
        },
        {
            regex: /^Не удалось исправить некоторые ошибки:\s*(.+)$/,
            key: 'notifications.integrity_some_failed_param',
            params: function(m) { return { errors: m[1] }; }
        }
    ];

    /**
     * Index dictionary strings for reverse lookup
     */
    function indexDictionary(obj, prefix) {
        if (!obj || typeof obj !== 'object') return;
        var keys = Object.keys(obj);
        for (var i = 0; i < keys.length; i++) {
            var k = keys[i];
            var val = obj[k];
            var fullKey = prefix ? prefix + '.' + k : k;
            if (typeof val === 'string') {
                var trimmed = val.trim();
                if (trimmed && !ruStringToKeyMap[trimmed]) {
                    ruStringToKeyMap[trimmed] = fullKey;
                }
            } else if (val && typeof val === 'object') {
                indexDictionary(val, fullKey);
            }
        }
    }

    // Initialize with known phrases
    for (var phrase in knownNotificationPhrases) {
        if (knownNotificationPhrases.hasOwnProperty(phrase)) {
            ruStringToKeyMap[phrase] = knownNotificationPhrases[phrase];
        }
    }

    /**
     * Escape HTML helper
     */
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Normalize language entry
     */
    function normalizeLanguage(item) {
        if (!item || typeof item !== 'object') return null;
        var file = item.file || item.filename || '';
        var code = (item.code || item.id || item.lang || '').toLowerCase().trim();
        if (!code && file) {
            code = file.replace(/\.json$/i, '').toLowerCase().trim();
        }
        if (!file && code) {
            file = code + '.json';
        }
        if (!code) return null;
        var name = item.name || item.title || item.label || item.lang_name || code.toUpperCase();
        var smile = item.smile || item.emoji || item.icon || item.flag || '🌐';
        return {
            code: code,
            name: name,
            file: file,
            smile: smile,
            icon: smile,
            emoji: smile
        };
    }

    /**
     * Populate available languages list
     */
    function setAvailableLanguages(rawList) {
        if (!Array.isArray(rawList)) return;
        var list = [];
        for (var i = 0; i < rawList.length; i++) {
            var norm = normalizeLanguage(rawList[i]);
            if (norm) {
                // Prevent duplicate codes
                var exists = false;
                for (var j = 0; j < list.length; j++) {
                    if (list[j].code === norm.code) {
                        exists = true;
                        break;
                    }
                }
                if (!exists) {
                    list.push(norm);
                }
            }
        }
        if (list.length > 0) {
            availableLanguages = list;
        }
    }

    /**
     * Resolve file name for language code
     */
    function getLangFile(code) {
        if (!code) return 'ru.json';
        code = code.toLowerCase().trim();
        for (var i = 0; i < availableLanguages.length; i++) {
            if (availableLanguages[i].code === code) {
                return availableLanguages[i].file || (code + '.json');
            }
        }
        return code + '.json';
    }

    /**
     * Check if a language code exists in available languages
     */
    function isLanguageAvailable(code) {
        if (!code) return false;
        code = code.toLowerCase().trim();
        for (var i = 0; i < availableLanguages.length; i++) {
            if (availableLanguages[i].code === code) {
                return true;
            }
        }
        return false;
    }

    var NPBlogI18n = {
        /**
         * Initialize i18n system
         */
        init: function(initialLang) {
            var self = this;

            // 1. Load available languages from window global if available
            if (window.NPBLOG_AVAILABLE_LANGUAGES && Array.isArray(window.NPBLOG_AVAILABLE_LANGUAGES)) {
                setAvailableLanguages(window.NPBLOG_AVAILABLE_LANGUAGES);
            }

            // 2. Fetch languages.json in background to always keep list fresh
            fetch('lang/languages.json?v=' + Date.now())
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (Array.isArray(data) && data.length > 0) {
                        setAvailableLanguages(data);
                        // If language cards container exists in DOM, refresh it
                        var container = document.getElementById('languageCardsContainer');
                        if (container) {
                            self.renderLanguageCards(container, currentLang);
                        }
                    }
                })
                .catch(function(err) {
                    // Fallback to defaults if languages.json is absent
                    if (availableLanguages.length === 0) {
                        setAvailableLanguages([
                            { code: 'ru', name: 'Русский', file: 'ru.json', smile: '🇷🇺' },
                            { code: 'en', name: 'English', file: 'en.json', smile: '🇬🇧' },
                            { code: 'uk', name: 'Українська', file: 'uk.json', smile: '🇺🇦' },
                            { code: 'lv', name: 'Latviešu', file: 'lv.json', smile: '🇱🇻' }
                        ]);
                    }
                })
                .finally(function() {
                    self._completeInit(initialLang);
                });

            if (availableLanguages.length > 0) {
                self._completeInit(initialLang);
            }
        },

        _completeInit: function(initialLang) {
            if (isInitialized) return;
            isInitialized = true;

            var stored = localStorage.getItem('npblog_language');
            if (stored && isLanguageAvailable(stored)) {
                currentLang = stored.toLowerCase().trim();
            } else if (initialLang && isLanguageAvailable(initialLang)) {
                currentLang = initialLang.toLowerCase().trim();
            } else if (window.NPBLOG_LANG && isLanguageAvailable(window.NPBLOG_LANG)) {
                currentLang = window.NPBLOG_LANG.toLowerCase().trim();
            } else if (availableLanguages.length > 0) {
                currentLang = availableLanguages[0].code;
            } else {
                currentLang = 'ru';
            }

            // Load dictionary for active language
            this.setLanguage(currentLang, false);

            // Preload other available dictionaries for instant switching
            this.preloadAll();

            // Auto-apply translations on DOMContentLoaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    NPBlogI18n.applyTranslations();
                });
            } else {
                NPBlogI18n.applyTranslations();
            }
        },

        /**
         * Preload all registered language dictionaries
         */
        preloadAll: function() {
            for (var i = 0; i < availableLanguages.length; i++) {
                var lang = availableLanguages[i];
                if (!loadedDictionaries[lang.code]) {
                    (function(code, file) {
                        fetch('lang/' + file + '?v=' + Date.now())
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                loadedDictionaries[code] = data;
                                if (code === 'ru') {
                                    indexDictionary(data, '');
                                }
                                if (code === currentLang) {
                                    dict = data;
                                    NPBlogI18n.applyTranslations();
                                }
                            })
                            .catch(function() {});
                    })(lang.code, lang.file || (lang.code + '.json'));
                }
            }
        },

        /**
         * Register dictionary data manually
         */
        register: function(langCode, data) {
            if (!langCode) return;
            langCode = langCode.toLowerCase().trim();
            loadedDictionaries[langCode] = data;
            if (langCode === 'ru') {
                indexDictionary(data, '');
            }
            if (langCode === currentLang) {
                dict = data;
            }
        },

        /**
         * Get active language code
         */
        getLanguage: function() {
            return currentLang;
        },

        /**
         * Get available languages array
         */
        getAvailableLanguages: function() {
            if (availableLanguages.length === 0) {
                return [
                    { code: 'ru', name: 'Русский', file: 'ru.json', smile: '🇷🇺', icon: '🇷🇺', emoji: '🇷🇺' },
                    { code: 'en', name: 'English', file: 'en.json', smile: '🇬🇧', icon: '🇬🇧', emoji: '🇬🇧' },
                    { code: 'uk', name: 'Українська', file: 'uk.json', smile: '🇺🇦', icon: '🇺🇦', emoji: '🇺🇦' },
                    { code: 'lv', name: 'Latviešu', file: 'lv.json', smile: '🇱🇻', icon: '🇱🇻', emoji: '🇱🇻' }
                ];
            }
            return availableLanguages;
        },

        /**
         * Get standardized locale string (e.g. 'ru-RU', 'en-US', 'uk-UA', 'lv-LV', 'de-DE')
         */
        getLocale: function() {
            var code = (currentLang || 'ru').toLowerCase();
            var localeMap = {
                'ru': 'ru-RU',
                'en': 'en-US',
                'uk': 'uk-UA',
                'lv': 'lv-LV',
                'de': 'de-DE',
                'fr': 'fr-FR',
                'es': 'es-ES',
                'it': 'it-IT',
                'pl': 'pl-PL',
                'pt': 'pt-PT',
                'zh': 'zh-CN',
                'ja': 'ja-JP'
            };
            if (localeMap[code]) return localeMap[code];
            if (code.length === 2) return code + '-' + code.toUpperCase();
            return code;
        },

        /**
         * Set and switch active language
         */
        setLanguage: function(langCode, saveToServer, callback) {
            if (!langCode) langCode = 'ru';
            langCode = langCode.toLowerCase().trim();

            if (!isLanguageAvailable(langCode) && availableLanguages.length > 0) {
                langCode = availableLanguages[0].code;
            }

            currentLang = langCode;
            localStorage.setItem('npblog_language', langCode);
            document.documentElement.setAttribute('lang', langCode);

            // If dictionary is preloaded
            if (loadedDictionaries[langCode]) {
                dict = loadedDictionaries[langCode];
                this.applyTranslations();
                if (window.dispatchEvent) {
                    window.dispatchEvent(new CustomEvent('npblog:langchange', { detail: { language: langCode } }));
                }
            } else {
                // Fetch JSON from lang folder using mapped file
                var self = this;
                var fileName = getLangFile(langCode);
                fetch('lang/' + fileName + '?v=' + Date.now())
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        loadedDictionaries[langCode] = data;
                        dict = data;
                        if (langCode === 'ru') {
                            indexDictionary(data, '');
                        }
                        self.applyTranslations();
                        if (window.dispatchEvent) {
                            window.dispatchEvent(new CustomEvent('npblog:langchange', { detail: { language: langCode } }));
                        }
                    })
                    .catch(function(err) {
                        console.warn('[i18n] Failed to load dictionary for ' + langCode + ' (' + fileName + '):', err);
                    });
            }

            if (saveToServer) {
                fetch('save_editor_settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ language: langCode })
                }).then(function(res) { return res.json(); })
                  .then(function(res) {
                      if (typeof callback === 'function') callback(res);
                  })
                  .catch(function(err) {
                      console.error('[i18n] Save language error:', err);
                      if (typeof callback === 'function') callback({ success: false, error: err });
                  });
            } else if (typeof callback === 'function') {
                callback({ success: true });
            }
        },

        /**
         * Render language selector cards into a DOM container
         */
        renderLanguageCards: function(container, selectedLang) {
            var el = (typeof container === 'string') ? document.getElementById(container) : container;
            if (!el) return;
            var lang = selectedLang || currentLang;
            var langs = this.getAvailableLanguages();
            var html = '';

            for (var i = 0; i < langs.length; i++) {
                var l = langs[i];
                var isSelected = (l.code === lang);
                var border = isSelected ? 'var(--primary-color, #4CAF50)' : 'transparent';
                var bg = isSelected ? 'rgba(76, 175, 80, 0.08)' : 'var(--modal-bg-subtle, rgba(0,0,0,0.02))';
                var checked = isSelected ? ' checked' : '';
                var langNameLower = (l.name || '').toLowerCase();

                html += '<div id="langCard-' + escapeHtml(l.code) + '" data-lang-code="' + escapeHtml(l.code) + '" data-lang-name="' + escapeHtml(langNameLower) + '" class="lang-selection-card" onclick="selectLanguageOption(\'' + escapeHtml(l.code) + '\', true)" style="border: 1px solid ' + border + '; border-radius: 6px; padding: 8px 12px; cursor: pointer; background: ' + bg + '; transition: all 0.2s; display: flex; align-items: center; justify-content: space-between;">' +
                    '<div style="display: flex; align-items: center; gap: 10px;">' +
                        '<span style="font-size: 18px; line-height: 1;">' + (l.smile || l.icon || '🌐') + '</span>' +
                        '<span style="font-size: 14px; font-weight: 500; color: var(--text-color);">' + escapeHtml(l.name) + '</span>' +
                    '</div>' +
                    '<input type="radio" name="editor_lang_radio" id="langRadio-' + escapeHtml(l.code) + '" value="' + escapeHtml(l.code) + '" style="cursor: pointer; width: 14px; height: 14px; margin: 0;" onchange="selectLanguageOption(\'' + escapeHtml(l.code) + '\', true)"' + checked + '>' +
                '</div>';
            }
            el.innerHTML = html;
        },

        /**
         * Translate message dynamically (key, text, or parameterized pattern)
         */
        translateMessage: function(msg, params) {
            if (!msg || typeof msg !== 'string') return msg;
            var trimmed = msg.trim();
            if (!trimmed) return msg;

            // 1. Direct key match (e.g. 'notifications.saved')
            if (trimmed.indexOf('.') !== -1) {
                var translatedByKey = this.t(trimmed, null, params);
                if (translatedByKey !== null && translatedByKey !== undefined) {
                    return translatedByKey;
                }
            }

            // 2. Direct string reverse match
            if (ruStringToKeyMap[trimmed]) {
                return this.t(ruStringToKeyMap[trimmed], trimmed, params);
            }

            // 3. Match parameterized patterns
            for (var i = 0; i < paramPatterns.length; i++) {
                var item = paramPatterns[i];
                var m = trimmed.match(item.regex);
                if (m) {
                    var extractedParams = item.params(m);
                    return this.t(item.key, trimmed, extractedParams);
                }
            }

            // 4. Return as is if no translation found
            return msg;
        },

        /**
         * Translate key with optional fallback and params
         */
        t: function(keyPath, fallback, params) {
            if (!keyPath) return fallback || '';
            
            var parts = keyPath.split('.');
            var current = dict;
            
            for (var i = 0; i < parts.length; i++) {
                if (current && typeof current === 'object' && parts[i] in current) {
                    current = current[parts[i]];
                } else {
                    current = null;
                    break;
                }
            }

            // Fallback for notification title aliases
            if (current === null || current === undefined) {
                if (keyPath === 'notifications.title_success') current = dict.common && dict.common.success;
                else if (keyPath === 'notifications.title_error') current = dict.common && dict.common.error;
                else if (keyPath === 'notifications.title_warning') current = dict.common && dict.common.warning;
                else if (keyPath === 'notifications.title_info') current = dict.common && dict.common.info;
            }

            var text = (typeof current === 'string') ? current : (fallback !== undefined ? fallback : keyPath);

            if (params && Array.isArray(params)) {
                params.forEach(function(p) {
                    text = text.replace(/%s/, p);
                });
            } else if (params && typeof params === 'object') {
                Object.keys(params).forEach(function(pKey) {
                    text = text.replace(new RegExp('\\{' + pKey + '\\}', 'g'), params[pKey]);
                });
            }

            return text;
        },

        /**
         * Scan DOM and apply translations to elements with data-i18n attributes
         */
        applyTranslations: function(root) {
            var doc = root || document;
            if (!doc || !doc.querySelectorAll) return;

            // 1. Text content: data-i18n
            var textElements = doc.querySelectorAll('[data-i18n]');
            for (var i = 0; i < textElements.length; i++) {
                var el = textElements[i];
                var key = el.getAttribute('data-i18n');
                var def = el.getAttribute('data-i18n-default') || el.textContent;
                el.textContent = this.t(key, def);
            }

            // 2. HTML content: data-i18n-html
            var htmlElements = doc.querySelectorAll('[data-i18n-html]');
            for (var j = 0; j < htmlElements.length; j++) {
                var hEl = htmlElements[j];
                var hKey = hEl.getAttribute('data-i18n-html');
                var hDef = hEl.getAttribute('data-i18n-default') || hEl.innerHTML;
                hEl.innerHTML = this.t(hKey, hDef);
            }

            // 3. Tooltip / Title: data-i18n-title
            var titleElements = doc.querySelectorAll('[data-i18n-title]');
            for (var k = 0; k < titleElements.length; k++) {
                var tEl = titleElements[k];
                var tKey = tEl.getAttribute('data-i18n-title');
                var tDef = tEl.getAttribute('title') || '';
                tEl.setAttribute('title', this.t(tKey, tDef));
            }

            // 4. Placeholder: data-i18n-placeholder
            var phElements = doc.querySelectorAll('[data-i18n-placeholder]');
            for (var m = 0; m < phElements.length; m++) {
                var pEl = phElements[m];
                var pKey = pEl.getAttribute('data-i18n-placeholder');
                var pDef = pEl.getAttribute('placeholder') || '';
                pEl.setAttribute('placeholder', this.t(pKey, pDef));
            }

            // 5. Aria-label: data-i18n-aria
            var ariaElements = doc.querySelectorAll('[data-i18n-aria]');
            for (var n = 0; n < ariaElements.length; n++) {
                var aEl = ariaElements[n];
                var aKey = aEl.getAttribute('data-i18n-aria');
                var aDef = aEl.getAttribute('aria-label') || '';
                aEl.setAttribute('aria-label', this.t(aKey, aDef));
            }

            // Update document title if needed
            if (doc === document && dict.header && dict.header.editor_title) {
                document.title = dict.header.editor_title;
            }
        }
    };

    // Expose globally
    window.NPBlogI18n = NPBlogI18n;
    window.t = NPBlogI18n.t.bind(NPBlogI18n);

    // Auto-initialize
    NPBlogI18n.init(window.NPBLOG_LANG || 'ru');

})(window, document);
