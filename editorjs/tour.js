// ——— Интерактивный тур-обучение ———
function getTutorialText(key, fallback, params) {
    if (window.NPBlogI18n && typeof window.NPBlogI18n.t === 'function') {
        return window.NPBlogI18n.t(key, fallback, params);
    }
    return fallback;
}

function getTutorialSteps() {
    return [
        // 1. Welcome
        {
            title: getTutorialText('tutorial.step_welcome_title', 'Добро пожаловать в NPBlog! 👋'),
            text: getTutorialText('tutorial.step_welcome_text', 'Этот интерактивный тур проведёт вас по всем инструментам, меню и панелям редактора для быстрой и комфортной работы со статьями.'),
            element: null
        },
        // 2. Title
        {
            title: getTutorialText('tutorial.step_title_title', 'Заголовок статьи 📝'),
            text: getTutorialText('tutorial.step_title_text', 'Сюда вводится название вашей статьи. На его основе формируется имя файла статьи (slug), заголовок в списке постов, тег <title> и заголовок H1 в опубликованном блоге.'),
            element: '#title'
        },
        // 3. Modes
        {
            title: getTutorialText('tutorial.step_modes_title', 'Режимы: Визуально и Код 👁️'),
            text: getTutorialText('tutorial.step_modes_text', 'Вы можете в один клик переключаться между удобным визуальным редактором (WYSIWYG) и прямым редактированием чистого HTML-кода.'),
            element: '#headerModeToggle, .mode-toggle'
        },
        // 4. Editor Canvas
        {
            title: getTutorialText('tutorial.step_editor_title', 'Главная рабочая область ✏️'),
            text: getTutorialText('tutorial.step_editor_text', 'Основное поле для написания текста. Поддерживает форматирование, вставку таблиц, спойлеров, картинок и перетаскивание файлов (Drag & Drop).'),
            element: '#contentVisual'
        },
        // 5. Undo & Redo
        {
            title: getTutorialText('tutorial.step_history_title', 'Отмена и возврат действий 🔙'),
            text: getTutorialText('tutorial.step_history_text', 'Кнопки отмены (Ctrl+Z) и повтора (Ctrl+Y) позволяют легко исправлять опечатки и возвращаться к предыдущим редакциям.'),
            element: '#undoBtn, #headerEditorActions'
        },
        // 6. Formatting Basic
        {
            title: getTutorialText('tutorial.step_format_title', 'Форматирование текста 🎨'),
            text: getTutorialText('tutorial.step_format_text', 'Инструменты оформления: жирный шрифт (Ctrl+B), курсив (Ctrl+I), подчёркивание (Ctrl+U), зачёркивание, а также верхний и нижний индексы (X² / X₂).'),
            element: '#btn-bold, #btn-italic, #btn-underline, #btn-strike'
        },
        // 7. Heading H2
        {
            title: getTutorialText('tutorial.step_heading_title', 'Подзаголовки H2 📌'),
            text: getTutorialText('tutorial.step_heading_text', 'Разделяйте текст на смысловые разделы подзаголовками H2. Они структурируют статью и автоматически формируют оглавление (содержание).'),
            element: '#btn-h2'
        },
        // 8. Tables
        {
            title: getTutorialText('tutorial.step_table_title', 'Вставка и оформление таблиц ⊞'),
            text: getTutorialText('tutorial.step_table_text', 'Создавайте адаптивные таблицы с нужным числом строк и колонок. В визуальном редакторе работает контекстное меню таблиц по правому клику.'),
            element: '#btn-table'
        },
        // 9. Spoilers
        {
            title: getTutorialText('tutorial.step_spoiler_title', 'Сворачиваемые спойлеры 📦'),
            text: getTutorialText('tutorial.step_spoiler_text', 'Скрывайте объёмный текст, листинги или ответы на вопросы в компактные раскрывающиеся аккордеоны с произвольным заголовком.'),
            element: '#btn-spoiler'
        },
        // 10. Marker
        {
            title: getTutorialText('tutorial.step_marker_title', 'Маркер выделения текста 🖍️'),
            text: getTutorialText('tutorial.step_marker_text', 'Выделяйте важные фразы ярким маркером (жёлтым, зелёным, синим или кастомным цветом) для привлечения внимания читателя.'),
            element: '#btn-marker'
        },
        // 11. Anchors
        {
            title: getTutorialText('tutorial.step_anchor_title', 'Якоря для навигации ⚓'),
            text: getTutorialText('tutorial.step_anchor_text', 'Устанавливайте именованные якорные метки внутри текста, чтобы строить точные ссылки на конкретные разделы и абзацы статьи.'),
            element: '#btn-anchor'
        },
        // 12. Alignment
        {
            title: getTutorialText('tutorial.step_align_title', 'Выравнивание текста и элементов 📐'),
            text: getTutorialText('tutorial.step_align_text', 'Быстрое выравнивание абзацев, цитат, картинок и таблиц по левому краю, по центру или по правому краю страницы.'),
            element: '#btn-align-left, #btn-align-center, #btn-align-right'
        },
        // 13. Links
        {
            title: getTutorialText('tutorial.step_link_title', 'Гиперссылки 🔗'),
            text: getTutorialText('tutorial.step_link_text', 'Добавляйте внешние и внутренние ссылки с удобной настройкой открывания в новой вкладке (_blank), заголовка и протокола.'),
            element: '#btn-link'
        },
        // 14. Image Upload & WebP
        {
            title: getTutorialText('tutorial.step_image_title', 'Загрузка и оптимизация картинок 🖼️'),
            text: getTutorialText('tutorial.step_image_text', 'Загружайте изображения с автоматической конвертацией в быстрый WebP, сжатием, обрезкой, подписями (captions) и ленивой загрузкой (lazy loading).'),
            element: '#btn-image'
        },
        // 15. Media Audio / Video
        {
            title: getTutorialText('tutorial.step_media_title', 'Аудио и Видео медиа 🎬'),
            text: getTutorialText('tutorial.step_media_text', 'Встраивайте аудиозаписи (.mp3, .ogg, .wav) и видеоролики (.mp4, .webm) со стильными встроенными HTML5-плеерами прямо в тело статьи.'),
            element: '#btn-media'
        },
        // 16. ASCII Drawer
        {
            title: getTutorialText('tutorial.step_ascii_title', 'ASCII-рисовалка 🎨'),
            text: getTutorialText('tutorial.step_ascii_text', 'Встроенный холст для создания ретро-графики, схем, диаграмм и символьных рисунков в стиле ASCII/ANSI art.'),
            element: '#btn-ascii'
        },
        // 17. Font Size Popover (Opens Popover!)
        {
            title: getTutorialText('tutorial.step_font_size_title', 'Размер шрифта 🔠'),
            text: getTutorialText('tutorial.step_font_size_text', 'Выбирайте готовые размеры из списка (12px–32px) или задавайте произвольный точный размер от 8px до 72px для выделенного фрагмента текста.'),
            menu: 'fontSize',
            element: '#fontSizeWrapMain .font-size-popover, #fontSizeWrapMain'
        },
        // 18. Font Family Popover (Opens Popover!)
        {
            title: getTutorialText('tutorial.step_font_family_title', 'Гарнитуры и пользовательские шрифты 🅰️'),
            text: getTutorialText('tutorial.step_font_family_text', 'Выбирайте популярные веб-шрифты (Arial, Times, Open Sans, Georgia и др.) или загружайте собственные файлы шрифтов (.woff2 / .ttf) через менеджер кастомных шрифтов.'),
            menu: 'fontFamily',
            element: '#fontFamilyWrapMain .font-family-popover, #fontFamilyWrapMain'
        },
        // 19. Color Palette Popover (Opens Popover!)
        {
            title: getTutorialText('tutorial.step_colors_title', 'Цветовая палитра 🎨'),
            text: getTutorialText('tutorial.step_colors_text', 'Выбирайте цвет текста из удобной палитры пресетов или задавайте точный оттенок через встроенный селектор цветов HTML5.'),
            menu: 'colorPicker',
            element: '#colorPickerWrapMain .color-palette-popover, #colorPickerWrapMain'
        },
        // 20. More Menu: Overview (Opens Dropdown!)
        {
            title: getTutorialText('tutorial.step_more_overview_title', 'Меню «Прочее» ⋯'),
            text: getTutorialText('tutorial.step_more_overview_text', 'Здесь собраны расширенные инструменты: сохранение черновиков, include-шаблоны, перекрёстные ссылки, вставка кода, кнопок и файлов.'),
            menu: 'moreMenu',
            element: '#moreMenuWrap .more-menu-dropdown, #moreMenuWrap'
        },
        // 21. More Menu: Drafts
        {
            title: getTutorialText('tutorial.step_more_drafts_title', 'Черновики и ревизии 📄'),
            text: getTutorialText('tutorial.step_more_drafts_text', 'Сохраняйте промежуточные версии статьи вручную в один клик. Подменю «Черновики» позволяет мгновенно просматривать и восстанавливать сохранённые ревизии.'),
            menu: 'moreMenu',
            element: '#moreMenuDropdown button[onclick*="saveDraft"], #moreMenuDropdown .more-menu-item:nth-child(2)'
        },
        // 22. More Menu: Includes
        {
            title: getTutorialText('tutorial.step_more_includes_title', 'Include-шаблоны и врезки 🧩'),
            text: getTutorialText('tutorial.step_more_includes_text', 'Сохраняйте повторяющиеся блоки (баннеры, плашки, контакты, виджеты) в папку includes/ и вставляйте их в любые статьи одной кнопкой.'),
            menu: 'moreMenu',
            element: '#moreMenuDropdown button[onclick*="openSaveInclude"], #moreMenuDropdown .more-menu-item:nth-child(4)'
        },
        // 23. More Menu: Article Links & TOC
        {
            title: getTutorialText('tutorial.step_more_articles_toc_title', 'Ссылки на статьи и Оглавление 📑'),
            text: getTutorialText('tutorial.step_more_articles_toc_text', '«Вставить ссылку на статью» строит перекрёстную перелинковку между постами, а «Содержание» автоматически формирует кликабельное оглавление из заголовков H2.'),
            menu: 'moreMenu',
            element: '#moreMenuDropdown .more-menu-item:nth-child(5), #moreMenuDropdown .more-menu-item:nth-child(6)'
        },
        // 24. More Menu: Upload Files & Code Blocks
        {
            title: getTutorialText('tutorial.step_more_files_code_title', 'Файлы и блоки программного кода 💻'),
            text: getTutorialText('tutorial.step_more_files_code_text', 'Загружайте любые вложения (PDF, zip, документы) для скачивания читателями и вставляйте аккуратно оформленные блоки исходного кода с подсветкой.'),
            menu: 'moreMenu',
            element: '#moreMenuDropdown button[onclick*="openFileUploadDialog"], #moreMenuDropdown button[onclick*="insertCode"]'
        },
        // 25. More Menu: Custom Buttons & Smiles
        {
            title: getTutorialText('tutorial.step_more_buttons_smiles_title', 'Кнопки призыва к действию и Смайлы 🔘'),
            text: getTutorialText('tutorial.step_more_buttons_smiles_text', 'Генератор стильных кнопок (Call to Action, Скачать, Купить) с градиентами и тенями, а также коллекция эмодзи и наборов графических стикеров.'),
            menu: 'moreMenu',
            element: '#moreMenuDropdown button[onclick*="openInsertButtonDialog"], #moreMenuDropdown button[onclick*="openSmileSetsDialog"]'
        },
        // 26. Autosave Badge
        {
            title: getTutorialText('tutorial.step_autosave_title', 'Автосохранение черновиков ⏱️'),
            text: getTutorialText('tutorial.step_autosave_text', 'Индикатор автосохранения отображает статус фонового сохранения черновика, гарантируя защиту вашего текста от случайной потери.'),
            element: '#autosaveBadge, #submitButton'
        },
        // 27. Save & Publish
        {
            title: getTutorialText('tutorial.step_save_title', 'Сохранение и Публикация 💾'),
            text: getTutorialText('tutorial.step_save_text', 'Кнопка «Сохранить» мгновенно публикует статью и обновляет каталог blog.html. Также работает быстрое сохранение по горячей клавише Ctrl+S.'),
            element: '#submitButton'
        },
        // 28. Main Menu: Overview (Opens Dropdown!)
        {
            title: getTutorialText('tutorial.step_menu_overview_title', 'Главное меню редактора ☰'),
            text: getTutorialText('tutorial.step_menu_overview_text', 'Центр управления блогом: Управление статьями, Шаблоны, Параметры, Менеджер бэкапов, Менеджер тем, FTP-публикация и Обновления.'),
            menu: 'editorMenu',
            element: '#editorMenuWrap .editor-menu-dropdown, #editorMenuBtn'
        },
        // 29. Main Menu: Posts
        {
            title: getTutorialText('tutorial.step_menu_posts_title', 'Управление статьями 📂'),
            text: getTutorialText('tutorial.step_menu_posts_text', 'Быстрый переход к боковой панели управления постами для поиска, сортировки, редактирования и удаления публикаций.'),
            menu: 'editorMenu',
            element: '.editor-menu-dropdown button[onclick*="toggleManagePosts"]'
        },
        // 30. Main Menu: Templates
        {
            title: getTutorialText('tutorial.step_menu_templates_title', 'Менеджер шаблонов 🎭'),
            text: getTutorialText('tutorial.step_menu_templates_text', 'Выбор внешнего вида блога: классический, карточный, минималистичный или кастомный HTML-шаблон.'),
            menu: 'editorMenu',
            element: '.editor-menu-dropdown button[onclick*="openTemplateManager"]'
        },
        // 31. Main Menu: Settings
        {
            title: getTutorialText('tutorial.step_menu_settings_title', 'Параметры и Настройки блога ⚙️'),
            text: getTutorialText('tutorial.step_menu_settings_text', 'Глобальные настройки: название сайта, описание, фавикон, шапка и подвал, SEO мета-теги, Open Graph, комментарии и безопасность.'),
            menu: 'editorMenu',
            element: '.editor-menu-dropdown button[onclick*="openGlobalSettings"]'
        },
        // 32. Main Menu: Backups
        {
            title: getTutorialText('tutorial.step_menu_backups_title', 'Менеджер бэкапов 📦'),
            text: getTutorialText('tutorial.step_menu_backups_text', 'Создание полных ZIP-архивов блога (статьи, картинки, конфигурация) и удобное восстановление из резервной копии.'),
            menu: 'editorMenu',
            element: '.editor-menu-dropdown button[onclick*="openBackupManager"]'
        },
        // 33. Main Menu: Autosaves
        {
            title: getTutorialText('tutorial.step_menu_autosaves_title', 'Менеджер автосохранений ⏱️'),
            text: getTutorialText('tutorial.step_menu_autosaves_text', 'Просмотр истории автоматически сохранённых версий для каждой статьи с возможностью сравнения и восстановления любого состояния.'),
            menu: 'editorMenu',
            element: '.editor-menu-dropdown button[onclick*="openAutosaveManager"]'
        },
        // 34. Main Menu: Themes
        {
            title: getTutorialText('tutorial.step_menu_themes_title', 'Менеджер тем оформления 🌓'),
            text: getTutorialText('tutorial.step_menu_themes_text', 'Переключение цветовых схем редактора и блога: Светлая, Тёмная, глубокая чёрная AMOLED или загрузка собственной CSS-темы.'),
            menu: 'editorMenu',
            element: '.editor-menu-dropdown button[onclick*="openThemeManager"], #theme-toggle'
        },
        // 35. Main Menu: FTP
        {
            title: getTutorialText('tutorial.step_menu_ftp_title', 'FTP / SFTP публикация на хостинг 🚀'),
            text: getTutorialText('tutorial.step_menu_ftp_text', 'Автоматическая синхронизация локального блога с удалённым сервером по FTP или SFTP. Загрузка новых и изменённых статей в один клик.'),
            menu: 'editorMenu',
            element: '.editor-menu-dropdown button[onclick*="openFtpModal"]'
        },
        // 36. Main Menu: Go to Blog
        {
            title: getTutorialText('tutorial.step_menu_goto_blog_title', 'Переход к блогу 🌐'),
            text: getTutorialText('tutorial.step_menu_goto_blog_text', 'Быстрый переход к главной странице вашего блога (blog.html) для просмотра результатов публикации в живом виде.'),
            menu: 'editorMenu',
            element: '.editor-menu-dropdown button#goToBlogBtn, #goToBlogBtn'
        },
        // 37. Main Menu: Updates
        {
            title: getTutorialText('tutorial.step_menu_updates_title', 'Обновление системы 🔄'),
            text: getTutorialText('tutorial.step_menu_updates_text', 'Проверка наличия новых версий NPBlog и автоматическое обновление системы с сохранением всех ваших настроек, постов и шаблонов.'),
            menu: 'editorMenu',
            element: '.editor-menu-dropdown button[onclick*="openSystemUpdateModal"]'
        },
        // 38. Sidebar Posts: Search & Blogs (Opens Sidebar!)
        {
            title: getTutorialText('tutorial.step_panel_posts_search_title', 'Панель статей: Поиск и Блоги 🔍'),
            text: getTutorialText('tutorial.step_panel_posts_search_text', 'Мгновенный живой поиск по заголовкам статей и переключение между папками блогов в мультиблоговом режиме.'),
            menu: 'managePosts',
            element: '#postsSearchInput, #blogSelectorContainer, #managePosts'
        },
        // 39. Sidebar Posts: List & Actions
        {
            title: getTutorialText('tutorial.step_panel_posts_list_title', 'Панель статей: Список и действия 📋'),
            text: getTutorialText('tutorial.step_panel_posts_list_text', 'Клик по карточке статьи загружает её в редактор. Доступно быстрое удаление, просмотр ID и даты публикации статьи.'),
            menu: 'managePosts',
            element: '#postsList, #managePosts'
        },
        // 40. Template Manager Modal: Grid (Opens Modal!)
        {
            title: getTutorialText('tutorial.step_panel_tpl_grid_title', 'Менеджер шаблонов: Сетка тем 🎭'),
            text: getTutorialText('tutorial.step_panel_tpl_grid_text', 'Каталог доступных HTML-шаблонов. Вы можете в один клик переключать глобальный шаблон оформления или назначать индивидуальный шаблон для конкретной статьи.'),
            modal: 'templateManager',
            element: '#templatesGrid, #templateManagerDialog .modal-dialog'
        },
        // 41. Template Manager Modal: Upload & Specs
        {
            title: getTutorialText('tutorial.step_panel_tpl_actions_title', 'Менеджер шаблонов: Загрузка и Спецификация 📥'),
            text: getTutorialText('tutorial.step_panel_tpl_actions_text', 'Загружайте собственные архивы шаблонов (.zip / .html) и сверяйтесь со встроенной инструкцией по обязательным плейсхолдерам ({{TITLE}}, {{CONTENT}}, {{DATE}} и др.).'),
            modal: 'templateManager',
            element: '#templateManagerDialog .modal-header-actions, #templateManagerDialog .modal-header'
        },
        // 42. Global Settings Modal: Overview (Opens Modal!)
        {
            title: getTutorialText('tutorial.step_panel_settings_overview_title', 'Параметры: Центр настроек ⚙️'),
            text: getTutorialText('tutorial.step_panel_settings_overview_text', 'В этом окне собраны все глобальные конфигурации блога. Боковое меню слева позволяет быстро переключаться между 11 разделами параметров.'),
            modal: 'globalSettings',
            element: '#globalSettingsModal .modal-body > div:first-child, #globalSettingsModal .modal-dialog'
        },
        // 43. Global Settings: Backgrounds
        {
            title: getTutorialText('tutorial.step_panel_settings_bg_title', 'Параметры: Фон статей 🖼️'),
            text: getTutorialText('tutorial.step_panel_settings_bg_text', 'Установка общего фонового изображения для всех постов блога, выбор режима масштабирования (cover, contain, repeat), области применения и отключение подписи Powered by.'),
            modal: 'globalSettings',
            section: 'backgrounds',
            element: '#globalSection-backgrounds, #globalSettingsModal .modal-dialog'
        },
        // 44. Global Settings: blog.html View
        {
            title: getTutorialText('tutorial.step_panel_settings_blogview_title', 'Параметры: Вид blog.html 📰'),
            text: getTutorialText('tutorial.step_panel_settings_blogview_text', 'Настройка заголовка главной страницы каталога статей, индивидуального фонового изображения и стилей отображения списка публикаций.'),
            modal: 'globalSettings',
            section: 'blogview',
            element: '#globalSection-blogview, #globalSettingsModal .modal-dialog'
        },
        // 45. Global Settings: Autosave
        {
            title: getTutorialText('tutorial.step_panel_settings_autosave_title', 'Параметры: Автосохранение ⏱️'),
            text: getTutorialText('tutorial.step_panel_settings_autosave_text', 'Настройка интервала фонового сохранения в секундах для предотвращения случайной потери введённого текста.'),
            modal: 'globalSettings',
            section: 'autosave',
            element: '#globalSection-autosave, #globalSettingsModal .modal-dialog'
        },
        // 46. Global Settings: Appearance
        {
            title: getTutorialText('tutorial.step_panel_settings_appearance_title', 'Параметры: Внешний вид и UX 🎨'),
            text: getTutorialText('tutorial.step_panel_settings_appearance_text', 'Переключение AMOLED-режима, активация плавного курсора, прикрепление панели инструментов внизу экрана и кастомизация кнопок тулбара.'),
            modal: 'globalSettings',
            section: 'appearance',
            element: '#globalSection-appearance, #globalSettingsModal .modal-dialog'
        },
        // 47. Global Settings: Experimental
        {
            title: getTutorialText('tutorial.step_panel_settings_experimental_title', 'Параметры: Экспериментальные функции 🧪'),
            text: getTutorialText('tutorial.step_panel_settings_experimental_text', 'Включение режима Markdown, утилита проверки и исправления нумерации постов, сброс интерактивного обучения и очистка кэша.'),
            modal: 'globalSettings',
            section: 'experimental',
            element: '#globalSection-experimental, #globalSettingsModal .modal-dialog'
        },
        // 48. Global Settings: RSS Feed & Widget
        {
            title: getTutorialText('tutorial.step_panel_settings_rss_title', 'Параметры: RSS Лента и Виджет 📡'),
            text: getTutorialText('tutorial.step_panel_settings_rss_text', 'Автоматическая генерация XML-ленты RSS 2.0 для чтения через RSS-ридеры, настройка базового URL и готовый код встраиваемого виджета.'),
            modal: 'globalSettings',
            section: 'rss_feed',
            element: '#globalSection-rss_feed, #globalSettingsModal .modal-dialog'
        },
        // 49. Global Settings: Paths
        {
            title: getTutorialText('tutorial.step_panel_settings_paths_title', 'Параметры: Пути и папки 📁'),
            text: getTutorialText('tutorial.step_panel_settings_paths_text', 'Настройка путей на сервере: директории данных блогов (data), папки для резервных копий статей (data_backup), автосохранений (autosave) и архивов бэкапов системы (editor_backup).'),
            modal: 'globalSettings',
            section: 'paths',
            element: '#globalSection-paths, #globalSettingsModal .modal-dialog'
        },
        // 50. Global Settings: Security
        {
            title: getTutorialText('tutorial.step_panel_settings_security_title', 'Параметры: Безопасность и пароль 🔒'),
            text: getTutorialText('tutorial.step_panel_settings_security_text', 'Защита входа в редактор надёжным паролем с криптографическим хешированием SHA-256 и ограничение доступа.'),
            modal: 'globalSettings',
            section: 'security',
            element: '#globalSection-security, #globalSettingsModal .modal-dialog'
        },
        // 51. Global Settings: SEO & Social
        {
            title: getTutorialText('tutorial.step_panel_settings_seo_title', 'Параметры: SEO и Социальные сети 🌐'),
            text: getTutorialText('tutorial.step_panel_settings_seo_text', 'Настройка мета-тегов Open Graph и Twitter Cards для красивого отображения превью ссылок в Telegram, WhatsApp, VK и соцсетях.'),
            modal: 'globalSettings',
            section: 'seo',
            element: '#globalSection-seo, #globalSettingsModal .modal-dialog'
        },
        // 52. Global Settings: Language
        {
            title: getTutorialText('tutorial.step_panel_settings_language_title', 'Параметры: Язык интерфейса 🌍'),
            text: getTutorialText('tutorial.step_panel_settings_language_text', 'Мгновенное переключение языка редактора между Русским, Английским, Украинским и Латышским с автосохранением предпочтений.'),
            modal: 'globalSettings',
            section: 'language',
            element: '#globalSection-language, #globalSettingsModal .modal-dialog'
        },
        // 53. Backup Manager Modal (Opens Modal!)
        {
            title: getTutorialText('tutorial.step_panel_backup_title', 'Менеджер бэкапов: Резервные копии 💾'),
            text: getTutorialText('tutorial.step_panel_backup_text', 'Создание полных резервных копий статей, медиафайлов и настроек в формате ZIP, просмотр архивов и восстановление любой сохранённой версии.'),
            modal: 'backupManager',
            element: '#backupManagerContent, #backupManagerOverlay .modal-dialog'
        },
        // 54. Autosave Manager Modal (Opens Modal!)
        {
            title: getTutorialText('tutorial.step_panel_autosaves_title', 'Менеджер автосохранений: Снимки ⏱️'),
            text: getTutorialText('tutorial.step_panel_autosaves_text', 'Просмотр и восстановление автоматически сохранённых снимков статей с возможностью восстановить текст при непредвиденном закрытии браузера.'),
            modal: 'autosaveManager',
            element: '#autosavesList, #autosaveManagerModal .modal-dialog'
        },
        // 55. Theme Manager Modal (Opens Modal!)
        {
            title: getTutorialText('tutorial.step_panel_themes_title', 'Менеджер тем: Тёмная, Светлая, AMOLED и CSS 🎨'),
            text: getTutorialText('tutorial.step_panel_themes_text', 'Выбор цветовой темы (Тёмная / Светлая / AMOLED) и встроенный редактор кастомных стилей CSS с возможностью загрузки файлов тем.'),
            modal: 'themeManager',
            element: '#themeManagerModal .modal-body, #themeManagerModal .modal-dialog'
        },
        // 56. Context Menu & Shortcuts
        {
            title: getTutorialText('tutorial.step_context_title', 'Контекстное меню и Горячие клавиши 💡'),
            text: getTutorialText('tutorial.step_context_text', 'Правый клик мыши внутри редактора открывает контекстное меню (работа со строками и столбцами таблиц). Поддерживаются шорткаты Ctrl+S, Ctrl+B, Ctrl+I, Ctrl+K.'),
            element: '#contentVisual'
        },
        // 57. Toolbar Customizer: Intro & Drag & Drop
        {
            title: getTutorialText('tutorial.step_customizer_intro_title', 'Кастомизация панели: Drag & Drop 🔀'),
            text: getTutorialText('tutorial.step_customizer_intro_text', 'Вы можете полностью настроить верхнюю панель под себя! Режим запускается из «Параметры → Внешний вид». Все кнопки становятся подвижными — просто зажмите любую кнопку левой кнопкой мыши и перетащите в нужное место.'),
            customizer: true,
            element: '.editor-header, #toolbar-row-1'
        },
        // 58. Toolbar Customizer: Two Rows
        {
            title: getTutorialText('tutorial.step_customizer_rows_title', 'Кастомизация: Два ряда инструментов 📑'),
            text: getTutorialText('tutorial.step_customizer_rows_text', 'Панель поддерживает два ряда кнопок. Перетаскивайте инструменты между верхней и нижней строкой, чтобы сгруппировать часто используемые действия и освободить рабочее пространство.'),
            customizer: true,
            element: '#toolbar-row-1, #toolbar-row-2, .editor-header'
        },
        // 59. Toolbar Customizer: Context Menu (Hide / Move to More)
        {
            title: getTutorialText('tutorial.step_customizer_context_title', 'Кастомизация: Контекстное меню ПКМ 🖱️'),
            text: getTutorialText('tutorial.step_customizer_context_text', 'Правый клик (ПКМ) по любой кнопке открывает меню действий: «Скрыть» (временно убрать с экрана) или «Перенести в "Прочее"» (поместить кнопку в выпадающее меню ⋯).'),
            customizer: true,
            element: '#moreMenuWrap, .editor-header'
        },
        // 60. Toolbar Customizer: Dividers & Spacers
        {
            title: getTutorialText('tutorial.step_customizer_dividers_title', 'Кастомизация: Разделители и Спейсеры 📏'),
            text: getTutorialText('tutorial.step_customizer_dividers_text', 'Кнопка «+ Разделитель» добавляет тонкую линию или пустой отступ (Spacer). Ширину пустого разделителя можно менять в пикселях, просто потянув за его правый край.'),
            customizer: true,
            element: '#headerCustomizerBar button[onclick*="toggleDividerDropdown"], #headerCustomizerBar'
        },
        // 61. Toolbar Customizer: Save & Cancel
        {
            title: getTutorialText('tutorial.step_customizer_save_title', 'Кастомизация: Сохранение настроек 💾'),
            text: getTutorialText('tutorial.step_customizer_save_text', 'Нажмите «Применить» на плавающей нижней панели, чтобы сохранить ваш персональный макет кнопок на сервере, или «Отмена», чтобы вернуть всё к прежнему виду.'),
            customizer: true,
            element: '#headerCustomizerBar button[onclick*="saveHeaderCustomization"], #headerCustomizerBar'
        },
        // 62. Finish
        {
            title: getTutorialText('tutorial.step_finish_title', 'Всё готово к работе! 🚀'),
            text: getTutorialText('tutorial.step_finish_text', 'Теперь вы знаете абсолютно все инструменты, меню, настройки, панели и кастомизацию редактора NPBlog. Приятного и продуктивного творчества!'),
            element: null
        }
    ];
}

let currentTutorialStep = 0;
let tutorialKeyDownHandler = null;
let tutorialResizeHandler = null;

function prepareStepEnvironment(step) {
    if (!step) return;

    // 1. Handle Dropdown Menus & Popovers
    const currentMenu = step.menu || null;

    // Editor Main Menu (☰)
    const editorMenuWrap = document.getElementById('editorMenuWrap');
    if (editorMenuWrap) {
        const btn = document.getElementById('editorMenuBtn');
        if (currentMenu === 'editorMenu') {
            editorMenuWrap.classList.remove('is-closing');
            editorMenuWrap.classList.add('is-open');
            if (btn) btn.setAttribute('aria-expanded', 'true');
        } else {
            editorMenuWrap.classList.remove('is-open', 'is-closing');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }
    }

    // More Menu (⋯)
    const moreMenuWrap = document.getElementById('moreMenuWrap');
    if (moreMenuWrap) {
        if (currentMenu === 'moreMenu') {
            moreMenuWrap.classList.add('is-open');
        } else {
            moreMenuWrap.classList.remove('is-open');
            document.querySelectorAll('.more-menu-item.has-submenu').forEach(item => item.classList.remove('submenu-open'));
        }
    }

    // Font Size Popover
    const fontSizeWrap = document.getElementById('fontSizeWrapMain');
    if (fontSizeWrap) {
        if (currentMenu === 'fontSize') {
            fontSizeWrap.classList.add('is-open');
        } else {
            fontSizeWrap.classList.remove('is-open');
        }
    }

    // Font Family Popover
    const fontFamilyWrap = document.getElementById('fontFamilyWrapMain');
    if (fontFamilyWrap) {
        if (currentMenu === 'fontFamily') {
            fontFamilyWrap.classList.add('is-open');
        } else {
            fontFamilyWrap.classList.remove('is-open');
        }
    }

    // Color Picker Popover
    const colorPickerWrap = document.getElementById('colorPickerWrapMain');
    if (colorPickerWrap) {
        if (currentMenu === 'colorPicker') {
            colorPickerWrap.classList.add('is-open');
        } else {
            colorPickerWrap.classList.remove('is-open');
        }
    }

    // Manage Posts Sidebar
    const managePosts = document.getElementById('managePosts');
    if (managePosts) {
        if (currentMenu === 'managePosts') {
            if (!managePosts.classList.contains('active')) {
                managePosts.classList.add('active');
                if (typeof updateBlogSelectorUI === 'function' && window.allBlogPaths) {
                    updateBlogSelectorUI(window.allBlogPaths, window.currentActiveBlogPath);
                }
                if (typeof loadPosts === 'function') loadPosts();
            }
        } else {
            if (managePosts.classList.contains('active')) {
                managePosts.classList.remove('active');
            }
        }
    }

    // 2. Handle Modals
    const currentModal = step.modal || null;

    // Template Manager Modal
    const templateDialog = document.getElementById('templateManagerDialog');
    if (currentModal === 'templateManager') {
        const isShown = templateDialog && (templateDialog.classList.contains('show') || templateDialog.style.display === 'flex' || templateDialog.style.display === 'block');
        if (!isShown && typeof openTemplateManager === 'function') {
            openTemplateManager();
        }
    } else if (templateDialog && (templateDialog.classList.contains('show') || templateDialog.style.display === 'flex' || templateDialog.style.display === 'block')) {
        if (typeof closeTemplateManager === 'function') closeTemplateManager();
    }

    // Global Settings Modal
    const settingsModal = document.getElementById('globalSettingsModal');
    if (currentModal === 'globalSettings') {
        const isShown = settingsModal && (settingsModal.classList.contains('show') || settingsModal.style.display === 'flex' || settingsModal.style.display === 'block');
        if (!isShown && typeof openGlobalSettings === 'function') {
            openGlobalSettings();
        }
        if (step.section && typeof showGlobalSection === 'function') {
            showGlobalSection(step.section);
        }
    } else if (settingsModal && (settingsModal.classList.contains('show') || settingsModal.style.display === 'flex' || settingsModal.style.display === 'block')) {
        if (typeof closeGlobalSettings === 'function') closeGlobalSettings();
    }

    // Backup Manager Modal
    const backupModal = document.getElementById('backupManagerOverlay');
    if (currentModal === 'backupManager') {
        const isShown = backupModal && (backupModal.classList.contains('show') || backupModal.style.display === 'flex' || backupModal.style.display === 'block');
        if (!isShown && typeof openBackupManager === 'function') {
            openBackupManager();
        }
    } else if (backupModal && (backupModal.classList.contains('show') || backupModal.style.display === 'flex' || backupModal.style.display === 'block')) {
        if (typeof closeBackupManager === 'function') closeBackupManager();
    }

    // Autosave Manager Modal
    const autosaveModal = document.getElementById('autosaveManagerModal');
    if (currentModal === 'autosaveManager') {
        const isShown = autosaveModal && (autosaveModal.classList.contains('show') || autosaveModal.style.display === 'flex' || autosaveModal.style.display === 'block');
        if (!isShown && typeof openAutosaveManager === 'function') {
            openAutosaveManager();
        }
    } else if (autosaveModal && (autosaveModal.classList.contains('show') || autosaveModal.style.display === 'flex' || autosaveModal.style.display === 'block')) {
        if (typeof closeAutosaveManager === 'function') closeAutosaveManager();
    }

    // Theme Manager Modal
    const themeModal = document.getElementById('themeManagerModal');
    if (currentModal === 'themeManager') {
        const isShown = themeModal && (themeModal.classList.contains('show') || themeModal.style.display === 'flex' || themeModal.style.display === 'block');
        if (!isShown && typeof openThemeManager === 'function') {
            openThemeManager();
        }
    } else if (themeModal && (themeModal.classList.contains('show') || themeModal.style.display === 'flex' || themeModal.style.display === 'block')) {
        if (typeof closeThemeManager === 'function') closeThemeManager();
    }

    // 3. Handle Header Toolbar Customizer
    if (step.customizer) {
        if (!document.body.classList.contains('header-customizing')) {
            if (typeof startHeaderCustomization === 'function') {
                startHeaderCustomization();
            } else {
                document.body.classList.add('header-customizing');
                const bar = document.getElementById('headerCustomizerBar');
                if (bar) bar.style.display = 'flex';
            }
        }
    } else {
        if (document.body.classList.contains('header-customizing')) {
            if (typeof cancelHeaderCustomization === 'function') {
                cancelHeaderCustomization();
            } else {
                document.body.classList.remove('header-customizing');
                const bar = document.getElementById('headerCustomizerBar');
                if (bar) bar.style.display = 'none';
            }
        }
    }
}

function cleanupTutorialEnvironment() {
    const editorMenuWrap = document.getElementById('editorMenuWrap');
    if (editorMenuWrap) {
        editorMenuWrap.classList.remove('is-open', 'is-closing');
        const btn = document.getElementById('editorMenuBtn');
        if (btn) btn.setAttribute('aria-expanded', 'false');
    }
    const moreMenuWrap = document.getElementById('moreMenuWrap');
    if (moreMenuWrap) {
        moreMenuWrap.classList.remove('is-open');
        document.querySelectorAll('.more-menu-item.has-submenu').forEach(item => item.classList.remove('submenu-open'));
    }
    const fontSizeWrap = document.getElementById('fontSizeWrapMain');
    if (fontSizeWrap) fontSizeWrap.classList.remove('is-open');
    const fontFamilyWrap = document.getElementById('fontFamilyWrapMain');
    if (fontFamilyWrap) fontFamilyWrap.classList.remove('is-open');
    const colorPickerWrap = document.getElementById('colorPickerWrapMain');
    if (colorPickerWrap) colorPickerWrap.classList.remove('is-open');
    const managePosts = document.getElementById('managePosts');
    if (managePosts) managePosts.classList.remove('active');

    if (document.body.classList.contains('header-customizing')) {
        if (typeof cancelHeaderCustomization === 'function') {
            cancelHeaderCustomization();
        } else {
            document.body.classList.remove('header-customizing');
            const bar = document.getElementById('headerCustomizerBar');
            if (bar) bar.style.display = 'none';
        }
    }

    if (typeof closeTemplateManager === 'function') closeTemplateManager();
    if (typeof closeGlobalSettings === 'function') closeGlobalSettings();
    if (typeof closeBackupManager === 'function') closeBackupManager();
    if (typeof closeAutosaveManager === 'function') closeAutosaveManager();
    if (typeof closeThemeManager === 'function') closeThemeManager();
}

function startTutorial(force = false) {
    if (force) {
        currentTutorialStep = 0;
        showTutorialStep();
        return;
    }
    fetch('get_editor_settings.php?t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            const settings = data.settings || {};
            if (settings.tutorialCompleted || settings.initial_setup_completed === false) return;
            currentTutorialStep = 0;
            showTutorialStep();
        })
        .catch(err => {
            console.error('Ошибка проверки настроек обучения:', err);
        });
}

function updateTutorialPosition() {
    const overlay = document.getElementById('tutorialOverlay');
    if (!overlay || !overlay.classList.contains('show')) return;
    
    const steps = getTutorialSteps();
    if (currentTutorialStep < 0 || currentTutorialStep >= steps.length) return;
    
    const step = steps[currentTutorialStep];
    const tooltip = document.getElementById('tutorialTooltip');
    const spotlight = document.getElementById('tutorialSpotlight');
    if (!tooltip || !spotlight) return;
    
    if (step.element) {
        let targetEl = null;
        const selectors = step.element.split(',');
        for (let sel of selectors) {
            const el = document.querySelector(sel.trim());
            if (el && (el.offsetWidth > 0 || el.offsetHeight > 0 || el.getClientRects().length > 0)) {
                targetEl = el;
                break;
            }
        }
        
        if (targetEl) {
            overlay.classList.remove('dimmed');
            
            // Find scroll container or modal dialog/body to clip spotlight bounds
            const scrollContainer = targetEl.closest('.modal-body > div, .modal-body, .manage-posts, #managePosts, .modal-dialog');
            
            const rect = targetEl.getBoundingClientRect();
            const pad = 6;
            
            let spotTop = rect.top - pad;
            let spotBottom = rect.bottom + pad;
            let spotLeft = rect.left - pad;
            let spotRight = rect.right + pad;
            
            if (scrollContainer) {
                const contRect = scrollContainer.getBoundingClientRect();
                // Ensure spotlight does not overflow outside the container/modal boundaries
                spotTop = Math.max(contRect.top + 2, spotTop);
                spotBottom = Math.min(contRect.bottom - 2, spotBottom);
                spotLeft = Math.max(contRect.left + 2, spotLeft);
                spotRight = Math.min(contRect.right - 2, spotRight);
            }
            
            const spotWidth = Math.max(0, Math.min(window.innerWidth, spotRight - spotLeft));
            const spotHeight = Math.max(0, Math.min(window.innerHeight, spotBottom - spotTop));
            
            spotlight.style.display = 'block';
            spotlight.style.top = Math.max(0, Math.round(spotTop)) + 'px';
            spotlight.style.left = Math.max(0, Math.round(spotLeft)) + 'px';
            spotlight.style.width = Math.round(spotWidth) + 'px';
            spotlight.style.height = Math.round(spotHeight) + 'px';
            
            tooltip.style.position = 'fixed';
            tooltip.style.transform = 'none';
            
            const tooltipRect = tooltip.getBoundingClientRect();
            const padding = 16;
            
            let tooltipTop, tooltipLeft;

            // Check if step is inside a modal or modal is currently visible
            const modalDialogEl = targetEl.closest('.modal-dialog') || document.querySelector('.modal.show .modal-dialog') || document.querySelector('.modal[style*="display: block"] .modal-dialog, .modal[style*="display: flex"] .modal-dialog');
            
            if (step.modal || modalDialogEl) {
                const activeModalDialog = modalDialogEl || targetEl;
                const modalRect = activeModalDialog.getBoundingClientRect();
                
                const spaceRight = window.innerWidth - modalRect.right;
                const spaceLeft = modalRect.left;
                const spaceBottom = window.innerHeight - modalRect.bottom;
                
                // Priority 1: To the Right side of the modal dialog
                if (spaceRight >= tooltipRect.width + 24) {
                    tooltipLeft = modalRect.right + 16;
                    tooltipTop = Math.max(padding, Math.min(modalRect.top + 20, window.innerHeight - tooltipRect.height - padding));
                }
                // Priority 2: To the Left side of the modal dialog
                else if (spaceLeft >= tooltipRect.width + 24) {
                    tooltipLeft = modalRect.left - tooltipRect.width - 16;
                    tooltipTop = Math.max(padding, Math.min(modalRect.top + 20, window.innerHeight - tooltipRect.height - padding));
                }
                // Priority 3: Below the modal dialog
                else if (spaceBottom >= tooltipRect.height + 24) {
                    tooltipTop = modalRect.bottom + 12;
                    tooltipLeft = Math.max(padding, Math.min((window.innerWidth - tooltipRect.width) / 2, window.innerWidth - tooltipRect.width - padding));
                }
                // Priority 4: Bottom-Right screen corner dock to avoid obscuring modal content
                else {
                    tooltipLeft = window.innerWidth - tooltipRect.width - 20;
                    tooltipTop = window.innerHeight - tooltipRect.height - 20;
                }
            } else if (step.menu === 'managePosts' || targetEl.closest('.manage-posts')) {
                // Sidebar: place to the left of the sidebar panel
                const sidebar = document.getElementById('managePosts') || targetEl;
                const sidebarRect = sidebar.getBoundingClientRect();
                tooltipLeft = Math.max(padding, sidebarRect.left - tooltipRect.width - 16);
                tooltipTop = Math.max(padding, Math.min(rect.top + 20, window.innerHeight - tooltipRect.height - padding));
            } else if (step.menu === 'editorMenu' || targetEl.closest('.editor-menu-dropdown') || rect.right > window.innerWidth - 320) {
                // Right-side dropdowns/items: place to the left of the item
                if (rect.left - tooltipRect.width - 20 >= padding) {
                    tooltipLeft = rect.left - tooltipRect.width - 16;
                    tooltipTop = Math.max(padding, Math.min(rect.top, window.innerHeight - tooltipRect.height - padding));
                } else {
                    tooltipLeft = Math.max(padding, Math.min(rect.left, window.innerWidth - tooltipRect.width - padding));
                    tooltipTop = rect.bottom + 12;
                    if (tooltipTop + tooltipRect.height > window.innerHeight - padding) {
                        tooltipTop = rect.top - tooltipRect.height - 12;
                    }
                }
            } else if (targetEl.closest('#headerCustomizerBar')) {
                // Floating customizer bar at bottom: place tooltip above the bar
                tooltipTop = rect.top - tooltipRect.height - 16;
                tooltipLeft = Math.max(padding, Math.min((window.innerWidth - tooltipRect.width) / 2, window.innerWidth - tooltipRect.width - padding));
            } else if (step.customizer && targetEl.closest('.editor-header, #toolbar-row-1, #toolbar-row-2')) {
                // Two-row header in customizer mode: place tooltip below the header
                tooltipTop = rect.bottom + 16;
                tooltipLeft = Math.max(padding, Math.min((window.innerWidth - tooltipRect.width) / 2, window.innerWidth - tooltipRect.width - padding));
            } else {
                // Normal placement: below target, or above if overflow
                tooltipTop = rect.bottom + 12;
                tooltipLeft = rect.left;
                
                if (tooltipTop + tooltipRect.height > window.innerHeight - padding) {
                    tooltipTop = rect.top - tooltipRect.height - 12;
                }
                if (tooltipTop < padding) {
                    tooltipTop = padding;
                }
                if (tooltipLeft + tooltipRect.width > window.innerWidth - padding) {
                    tooltipLeft = window.innerWidth - tooltipRect.width - padding;
                }
                if (tooltipLeft < padding) {
                    tooltipLeft = padding;
                }
            }
            
            tooltip.style.top = Math.round(tooltipTop) + 'px';
            tooltip.style.left = Math.round(tooltipLeft) + 'px';
            return;
        }
    }
    
    // Default center placement when no element
    overlay.classList.add('dimmed');
    spotlight.style.display = 'none';
    tooltip.style.position = 'fixed';
    tooltip.style.top = '50%';
    tooltip.style.left = '50%';
    tooltip.style.transform = 'translate(-50%, -50%)';
}

function showTutorialStep() {
    const overlay = document.getElementById('tutorialOverlay');
    const tooltip = document.getElementById('tutorialTooltip');
    const complete = document.getElementById('tutorialComplete');
    const spotlight = document.getElementById('tutorialSpotlight');
    if (!overlay || !tooltip) return;

    overlay.classList.add('show');
    tooltip.style.display = 'block';
    if (complete) complete.style.display = 'none';

    const steps = getTutorialSteps();
    if (currentTutorialStep >= steps.length) {
        showTutorialComplete();
        return;
    }

    const step = steps[currentTutorialStep];

    // Prepare and open necessary menu/modal/panel
    prepareStepEnvironment(step);

    // Update content
    const titleEl = document.getElementById('tutorialTitle');
    const textEl = document.getElementById('tutorialText');
    if (titleEl) titleEl.textContent = step.title;
    if (textEl) textEl.textContent = step.text;

    // Update step counter badge
    const badgeEl = document.getElementById('tutorialStepBadge');
    if (badgeEl) {
        const counterTemplate = getTutorialText('tutorial.step_counter', 'Шаг {current} из {total}');
        badgeEl.textContent = counterTemplate.replace('{current}', currentTutorialStep + 1).replace('{total}', steps.length);
    }

    // Update progress fill
    const fillEl = document.getElementById('tutorialProgressFill');
    if (fillEl) {
        const progressPct = ((currentTutorialStep + 1) / steps.length) * 100;
        fillEl.style.width = progressPct + '%';
    }

    // Update buttons
    const prevBtn = document.getElementById('tutorialPrevBtn');
    if (prevBtn) {
        prevBtn.style.display = currentTutorialStep > 0 ? 'inline-block' : 'none';
    }

    const nextBtn = document.getElementById('tutorialNextBtn');
    if (nextBtn) {
        const nextText = currentTutorialStep === steps.length - 1 
            ? getTutorialText('tutorial.finish_btn', 'Завершить') 
            : getTutorialText('tutorial.next_btn', 'Далее →');
        nextBtn.textContent = nextText;
    }

    // Scroll element into view if needed
    if (step.element) {
        const selectors = step.element.split(',');
        for (let sel of selectors) {
            const el = document.querySelector(sel.trim());
            if (el) {
                const scrollContainer = el.closest('.modal-body > div, .modal-body, .manage-posts, #managePosts');
                if (step.section || step.modal) {
                    if (scrollContainer) {
                        scrollContainer.scrollTop = 0;
                    }
                } else if (scrollContainer) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
                }
                break;
            }
        }
    }

    // Bind event stoppers so clicks inside tutorial overlay don't bubble to document
    if (!tooltip.hasAttribute('data-tutorial-bound')) {
        tooltip.setAttribute('data-tutorial-bound', 'true');
        ['click', 'mousedown', 'mouseup', 'pointerdown', 'pointerup'].forEach(evt => {
            tooltip.addEventListener(evt, e => e.stopPropagation());
            overlay.addEventListener(evt, e => e.stopPropagation());
        });
    }

    // Update position on next animation frame and after transition delays
    requestAnimationFrame(updateTutorialPosition);
    setTimeout(updateTutorialPosition, 50);
    setTimeout(updateTutorialPosition, 200);
    setTimeout(updateTutorialPosition, 400);

    // Attach listeners
    if (!tutorialKeyDownHandler) {
        tutorialKeyDownHandler = function(e) {
            if (!overlay.classList.contains('show')) return;
            if (e.key === 'ArrowRight') {
                e.preventDefault();
                nextTutorialStep();
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                prevTutorialStep();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                skipTutorial();
            }
        };
        window.addEventListener('keydown', tutorialKeyDownHandler);
    }

    if (!tutorialResizeHandler) {
        tutorialResizeHandler = function() {
            updateTutorialPosition();
        };
        window.addEventListener('resize', tutorialResizeHandler);
        window.addEventListener('scroll', tutorialResizeHandler, { passive: true });
    }
}

function prevTutorialStep() {
    if (currentTutorialStep > 0) {
        currentTutorialStep--;
        showTutorialStep();
    }
}

function nextTutorialStep() {
    const steps = getTutorialSteps();
    currentTutorialStep++;
    if (currentTutorialStep >= steps.length) {
        showTutorialComplete();
    } else {
        showTutorialStep();
    }
}

function skipTutorial() {
    const confirmText = getTutorialText('tutorial_skip_confirm', 'Вы уверены, что хотите пропустить обучение?');
    if (window.Modal && typeof Modal.confirm === 'function') {
        Modal.confirm(confirmText).then(result => {
            if (result) completeTutorial();
        });
    } else if (typeof showConfirm === 'function') {
        showConfirm(confirmText).then(result => {
            if (result) completeTutorial();
        });
    } else {
        if (confirm(confirmText)) completeTutorial();
    }
}

function showTutorialComplete() {
    cleanupTutorialEnvironment();

    const overlay = document.getElementById('tutorialOverlay');
    const tooltip = document.getElementById('tutorialTooltip');
    const complete = document.getElementById('tutorialComplete');
    const spotlight = document.getElementById('tutorialSpotlight');

    if (overlay) overlay.classList.add('dimmed');
    if (tooltip) tooltip.style.display = 'none';
    if (spotlight) spotlight.style.display = 'none';
    if (complete) complete.style.display = 'block';
}

function completeTutorial() {
    cleanupTutorialEnvironment();

    fetch('save_editor_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tutorialCompleted: true })
    });
    const overlay = document.getElementById('tutorialOverlay');
    if (overlay) overlay.classList.remove('show');
    
    if (tutorialKeyDownHandler) {
        window.removeEventListener('keydown', tutorialKeyDownHandler);
        tutorialKeyDownHandler = null;
    }
    if (tutorialResizeHandler) {
        window.removeEventListener('resize', tutorialResizeHandler);
        window.removeEventListener('scroll', tutorialResizeHandler);
        tutorialResizeHandler = null;
    }
}

function resetTutorial() {
    const confirmText = getTutorialText('tutorial_reset_confirm', 'Вы уверены, что хотите сбросить обучение? Гайд появится снова при следующей загрузке страницы.');
    const onConfirm = () => {
        fetch('save_editor_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tutorialCompleted: false })
        }).then(() => {
            if (typeof showNotification === 'function') {
                const notice = getTutorialText('tutorial_reset_notice', 'Обучение сброшено. Запускаем гайд...');
                showNotification(notice, 'success');
            }
            startTutorial(true);
        });
    };

    if (window.Modal && typeof Modal.confirm === 'function') {
        Modal.confirm(confirmText).then(result => {
            if (result) onConfirm();
        });
    } else if (typeof showConfirm === 'function') {
        showConfirm(confirmText).then(result => {
            if (result) onConfirm();
        });
    } else {
        if (confirm(confirmText)) onConfirm();
    }
}

// Запускаем гайд при загрузке страницы
window.addEventListener('load', function () {
    setTimeout(startTutorial, 500);
});
