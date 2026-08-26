<?php
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/lang_helper.php';

$availableLanguages = getAvailableLanguages();
$availableCodes = getAvailableLanguageCodes();
$defaultLang = !empty($availableCodes) ? $availableCodes[0] : 'ru';
$settingsFile = 'editor_settings.json';
$amoled = false;
$activeTheme = 'dark';
$currentLanguage = $defaultLang;
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    if (!empty($settings['amoledTheme'])) {
        $amoled = true;
    }
    if (!empty($settings['activeTheme'])) {
        $activeTheme = $settings['activeTheme'];
    }
    if (!empty($settings['language']) && isValidLanguageCode($settings['language'])) {
        $currentLanguage = $settings['language'];
    }
}
if (!empty($_SESSION['editor_language']) && isValidLanguageCode($_SESSION['editor_language'])) {
    $currentLanguage = $_SESSION['editor_language'];
}
$customCssExists = file_exists(getDataPath('custom_editor_theme.css'));

$isDevBuild = false;
$versionFile = __DIR__ . '/version.json';
if (file_exists($versionFile)) {
    $versionData = json_decode(file_get_contents($versionFile), true);
    if (!empty($versionData['dev']) && ($versionData['dev'] === true || $versionData['dev'] === 'true')) {
        $isDevBuild = true;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($currentLanguage); ?>"<?php echo $amoled ? ' data-amoled="true"' : ''; ?>>
<head>
    <title>Редактор</title>
    <meta charset="utf-8">
    <meta name="csrf-token" content="<?php echo isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''; ?>">
    <script src="lang/i18n.js?v=<?php echo file_exists(__DIR__ . '/lang/i18n.js') ? filemtime(__DIR__ . '/lang/i18n.js') : time(); ?>"></script>
    <script>
        window.NPBLOG_AVAILABLE_LANGUAGES = <?php echo json_encode($availableLanguages, JSON_UNESCAPED_UNICODE); ?>;
        window.NPBLOG_LANG = '<?php echo htmlspecialchars($currentLanguage); ?>';
        const savedTheme = localStorage.getItem('theme') || '<?php echo $activeTheme; ?>';
        if (savedTheme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else if (savedTheme === 'light') {
            document.documentElement.removeAttribute('data-theme');
        } else if (savedTheme === 'custom') {
            document.documentElement.setAttribute('data-theme', 'custom');
        }
        if(/Android/i.test(navigator.userAgent)) document.documentElement.classList.add('is-android');
        const DATA_URL_PREFIX = '<?php echo getDataUrl(); ?>';
        window.isDevBuild = <?php echo $isDevBuild ? 'true' : 'false'; ?>;
        
        // Global Fetch Interceptor to automatically append CSRF Token headers
        // Global Fetch Interceptor to automatically append CSRF Token headers and handle session expiration
        (function() {
            const originalFetch = window.fetch;
            window.fetch = function(input, init) {
                if (!init) init = {};
                if (!init.headers) init.headers = {};
                
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (csrfToken) {
                    if (init.headers instanceof Headers) {
                        init.headers.set('X-CSRF-Token', csrfToken);
                    } else if (Array.isArray(init.headers)) {
                        let found = false;
                        for (let i = 0; i < init.headers.length; i++) {
                            if (init.headers[i][0].toLowerCase() === 'x-csrf-token') {
                                init.headers[i][1] = csrfToken;
                                found = true;
                                break;
                            }
                        }
                        if (!found) {
                            init.headers.push(['X-CSRF-Token', csrfToken]);
                        }
                    } else {
                        init.headers['X-CSRF-Token'] = csrfToken;
                    }
                }
                
                return originalFetch(input, init).then(async response => {
                    const isLoginRequest = typeof input === 'string' && input.includes('login.php');
                    if (!isLoginRequest) {
                        let isAuthError = false;
                        if (response.status === 401 || response.status === 403) {
                            isAuthError = true;
                        } else {
                            try {
                                const clone = response.clone();
                                const data = await clone.json();
                                if (data && (data.error === 'unauthorized' || data.error === 'csrf_error')) {
                                    isAuthError = true;
                                }
                            } catch(e) {}
                        }
                        
                        if (isAuthError) {
                            if (typeof hasEditorContent === 'function' && hasEditorContent()) {
                                if (typeof showSessionExpiredModal === 'function') {
                                    showSessionExpiredModal();
                                } else {
                                    showAlert('Сессия истекла. Пожалуйста, скопируйте ваш текст во избежание его потери, откройте блог в новой вкладке, авторизуйтесь и вернитесь.');
                                }
                                throw new Error('Session expired');
                            } else {
                                window.location.reload();
                                throw new Error('Session expired');
                            }
                        }
                    }
                    return response;
                });
            };
        })();
    </script>
    <link rel="stylesheet" href="editor-style.css?v=<?php echo file_exists(__DIR__ . '/editor-style.css') ? filemtime(__DIR__ . '/editor-style.css') : time(); ?>">
    <link rel="stylesheet" href="modals/modal.css?v=<?php echo file_exists(__DIR__ . '/modals/modal.css') ? filemtime(__DIR__ . '/modals/modal.css') : time(); ?>">
    <link rel="stylesheet" id="customThemeStyleLink" href="data/custom_editor_theme.css?v=<?php echo $customCssExists ? filemtime(__DIR__ . '/data/custom_editor_theme.css') : '1'; ?>" <?php echo ($activeTheme === 'custom' && $customCssExists) ? '' : 'disabled'; ?>>
</head>
<body>
    
    <!-- Контейнер для уведомлений -->
    <div class="notification-container" id="notificationContainer"></div>
    
    <!-- Диалог подтверждения удаления -->
    <?php require_once __DIR__ . '/modals_editor/delete_confirm_modal.php'; ?>

    <!-- Диалог сохранения в includes -->
    <?php require_once __DIR__ . '/modals_editor/save_include_modal.php'; ?>

    <!-- Менеджер бэкапов -->
    <?php require_once __DIR__ . '/modals_editor/backup_manager_modal.php'; ?>

    <!-- Диалог проверки нумерации -->
    <?php require_once __DIR__ . '/modals_editor/numbering_check_modal.php'; ?>

    <!-- Гайд для первого запуска -->
    <div class="tutorial-overlay" id="tutorialOverlay">
        <div class="tutorial-spotlight" id="tutorialSpotlight"></div>
        <div class="tutorial-tooltip" id="tutorialTooltip">
            <div class="tutorial-progress" id="tutorialProgress"></div>
            <h3 id="tutorialTitle"></h3>
            <p id="tutorialText"></p>
            <div class="tutorial-buttons">
                <button class="tutorial-btn skip" onclick="skipTutorial()">Пропустить</button>
                <button class="tutorial-btn next" onclick="nextTutorialStep()">Далее</button>
            </div>
        </div>
        <div class="tutorial-complete-dialog" id="tutorialComplete" style="display:none;">
            <div class="tutorial-complete-icon">🎉</div>
            <h2>Обучение завершено!</h2>
            <p>Теперь вы знаете основы работы с редактором NPBlog. Приятного использования!</p>
            <button class="tutorial-complete-btn" onclick="completeTutorial()">OK</button>
        </div>
    </div>

    <!-- Фиксированный хеадер редактора -->
    <header class="editor-header">
        <div class="header-left">
            <div id="toolbar-row-1" class="toolbar-row" data-placeholder="Ряд 1">
                <span class="header-logo">NPBlog</span>
            <span class="toolbar-divider" id="logoDivider"></span>
            
            <div class="mode-toggle" id="headerModeToggle" onmousedown="if(!document.body.classList.contains('header-customizing')) event.preventDefault()">
                <button type="button" id="modeVisualBtn" class="format-btn" title="Визуальный режим" data-i18n="tabs.visual" data-i18n-title="tabs.visual">Визуально</button>
                <button type="button" id="modeCodeBtn" class="format-btn" title="Режим кода" data-i18n="tabs.code" data-i18n-title="tabs.code">Код</button>
            </div>
            
            <span class="toolbar-divider" id="modeActionsDivider"></span>
            
            <div class="editor-actions" id="headerEditorActions" onmousedown="if(!document.body.classList.contains('header-customizing')) event.preventDefault()">
                <button type="button" id="undoBtn" class="format-btn" onclick="undoEdit()" title="Отменить (Ctrl+Z)" data-i18n-title="toolbar.undo">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block;">
                        <path d="M3 7v6h6" />
                        <path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13" />
                    </svg>
                </button>
                <button type="button" id="redoBtn" class="format-btn" onclick="redoEdit()" title="Вернуть (Ctrl+Y)" data-i18n-title="toolbar.redo">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block;">
                        <path d="M21 7v6h-6" />
                        <path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7" />
                    </svg>
                </button>
            </div>
            
            <span class="toolbar-divider" id="actionsFormattingDivider"></span>
            
            <button type="button" id="btn-bold" class="format-btn" onclick="formatText('b')" title="Жирный" data-i18n-title="toolbar.bold"><span class="button-icon"><b>B</b></span><span class="button-text" data-i18n="toolbar.bold">Жирный</span></button>
            <button type="button" id="btn-italic" class="format-btn" onclick="formatText('i')" title="Курсив" data-i18n-title="toolbar.italic"><span class="button-icon"><i>I</i></span><span class="button-text" data-i18n="toolbar.italic">Курсив</span></button>
            <button type="button" id="btn-underline" class="format-btn" onclick="formatText('u')" title="Подчеркнутый" data-i18n-title="toolbar.underline"><span class="button-icon"><u>U</u></span><span class="button-text" data-i18n="toolbar.underline">Подчеркнутый</span></button>
            <button type="button" id="btn-strike" class="format-btn" onclick="formatText('s')" title="Зачеркнутый" data-i18n-title="toolbar.strikethrough"><span class="button-icon"><s>S</s></span><span class="button-text" data-i18n="toolbar.strikethrough">Зачеркнутый</span></button>
            <button type="button" id="btn-sup" class="format-btn" onclick="formatText('sup')" title="Верхний индекс" data-i18n-title="toolbar.sup"><span class="button-icon">X<sup>2</sup></span><span class="button-text" data-i18n="toolbar.sup">Верхний индекс</span></button>
            <button type="button" id="btn-sub" class="format-btn" onclick="formatText('sub')" title="Нижний индекс" data-i18n-title="toolbar.sub"><span class="button-icon">X<sub>2</sub></span><span class="button-text" data-i18n="toolbar.sub">Нижний индекс</span></button>
            <button type="button" id="btn-h2" class="format-btn" onclick="formatText('h2')" title="Подзаголовок" data-i18n-title="toolbar.heading"><span class="button-icon"><b>H</b></span><span class="button-text" data-i18n="toolbar.heading">Подзаголовок</span></button>
            <button type="button" id="btn-table" class="format-btn" onclick="openTableDialog()" title="Вставить таблицу" data-i18n-title="toolbar.table"><span class="button-icon">⊞</span><span class="button-text" data-i18n="toolbar.table">Вставить таблицу</span></button>
            <button type="button" id="btn-spoiler" class="format-btn" onclick="openSpoilerDialog()" title="Сворачиваемый блок" data-i18n-title="toolbar.spoiler"><span class="button-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="display: block;"><path d="M3 7l9 10 9-10H3z" /></svg></span><span class="button-text" data-i18n="toolbar.spoiler">Сворачиваемый блок</span></button>
            <button type="button" id="btn-marker" class="format-btn" onclick="openMarkerDialog()" title="Маркер" data-i18n-title="toolbar.marker"><span class="button-icon">🖍</span><span class="button-text" data-i18n="toolbar.marker">Маркер</span></button>
            <button type="button" id="btn-anchor" class="format-btn" onclick="addAnchor()" title="Добавить якорь" data-i18n-title="toolbar.anchor"><span class="button-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block;"><circle cx="12" cy="5" r="3" /><line x1="12" y1="8" x2="12" y2="22" /><path d="M5 12H2a10 10 0 0 0 20 0h-3" /></svg></span><span class="button-text" data-i18n="toolbar.anchor">Добавить якорь</span></button>
            
            <span class="toolbar-divider" id="divider-align"></span>
            
            <button type="button" id="btn-align-left" class="format-btn" onclick="alignText('left')" title="По левому краю" data-i18n-title="toolbar.align_left"><span class="button-icon">◄</span><span class="button-text" data-i18n="toolbar.align_left">Выравнивание по левому краю</span></button>
            <button type="button" id="btn-align-center" class="format-btn" onclick="alignText('center')" title="По центру" data-i18n-title="toolbar.align_center"><span class="button-icon">≡</span><span class="button-text" data-i18n="toolbar.align_center">Выравнивание по центру</span></button>
            <button type="button" id="btn-align-right" class="format-btn" onclick="alignText('right')" title="По правому краю" data-i18n-title="toolbar.align_right"><span class="button-icon">►</span><span class="button-text" data-i18n="toolbar.align_right">Выравнивание по правому краю</span></button>
            
            <span class="toolbar-divider" id="divider-media"></span>
            
            <button type="button" id="btn-link" class="format-btn" onclick="addLink()" title="Ссылка" data-i18n-title="toolbar.link"><span class="button-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" /><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" /></svg></span><span class="button-text" data-i18n="toolbar.link">Ссылка</span></button>
            <button type="button" id="btn-image" class="format-btn" onclick="showImageUpload()" title="Добавить изображение" data-i18n-title="toolbar.image"><span class="button-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2" /><circle cx="8.5" cy="8.5" r="1.5" /><polyline points="21 15 16 10 5 21" /></svg></span><span class="button-text" data-i18n="toolbar.image">Изображение</span></button>
            <button type="button" id="btn-media" class="format-btn" onclick="showMediaDialog()" title="Добавить медиа" data-i18n-title="toolbar.media"><span class="button-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block;"><polygon points="23 7 16 12 23 17 23 7" /><rect x="1" y="5" width="15" height="14" rx="2" ry="2" /></svg></span><span class="button-text" data-i18n="toolbar.media">Медиа</span></button>
            <button type="button" id="btn-ascii" class="format-btn" onclick="openAsciiDrawer()" title="ASCII Рисовалка" data-i18n-title="toolbar.ascii"><span class="button-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block;"><path d="M12 22C17.5228 22 22 17.5228 22 12C22 11.5 21.5 11 21 11H19C17.8954 11 17 10.1046 17 9V7C17 5.89543 16.1046 5 15 5H14C12.8954 5 12 4.10457 12 3V2C12 1.5 11.5 1 11 1C5.47715 1 1 5.47715 1 11C1 17.0751 5.47715 22 12 22Z" /><circle cx="7.5" cy="10.5" r="1.5" fill="currentColor" /><circle cx="11.5" cy="7.5" r="1.5" fill="currentColor" /><circle cx="16.5" cy="9.5" r="1.5" fill="currentColor" /></svg></span><span class="button-text" data-i18n="toolbar.ascii">ASCII Рисовалка</span></button>
            
            <span class="toolbar-divider" id="divider-fonts"></span>
            
            <div class="font-size-picker-wrap" id="fontSizeWrapMain">
                <button type="button" id="fontSizeBtn" class="format-btn font-size-picker-btn" title="Размер шрифта" data-i18n-title="toolbar.font_size">14px</button>
                <div class="font-size-popover">
                    <div class="font-size-popover-inner">
                        <button type="button" class="font-size-item" data-size="12">12px</button>
                        <button type="button" class="font-size-item" data-size="14">14px</button>
                        <button type="button" class="font-size-item" data-size="16">16px</button>
                        <button type="button" class="font-size-item" data-size="18">18px</button>
                        <button type="button" class="font-size-item" data-size="20">20px</button>
                        <button type="button" class="font-size-item" data-size="24">24px</button>
                        <button type="button" class="font-size-item" data-size="28">28px</button>
                        <button type="button" class="font-size-item" data-size="32">32px</button>
                        <div class="font-size-custom">
                            <label data-i18n="toolbar.font_size_custom_label">Свой размер (8–72)</label>
                            <input type="number" id="fontSizeCustomMain" min="8" max="72" placeholder="px">
                            <button type="button" onclick="applyCustomFontSize('fontSizeWrapMain')" data-i18n="common.apply">Применить</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="font-family-picker-wrap" id="fontFamilyWrapMain">
                <button type="button" id="fontFamilyBtn" class="format-btn font-family-picker-btn" title="Шрифт" data-i18n-title="toolbar.font_family">Arial</button>
                <div class="font-family-popover">
                    <div class="font-family-popover-inner">
                        <button type="button" class="font-family-item" data-font="Arial" style="font-family:Arial">Arial</button>
                        <button type="button" class="font-family-item" data-font="Times New Roman" style="font-family:'Times New Roman'">Times New Roman</button>
                        <button type="button" class="font-family-item" data-font="Open Sans" style="font-family:'Open Sans'">Open Sans</button>
                        <button type="button" class="font-family-item" data-font="Verdana" style="font-family:Verdana">Verdana</button>
                        <button type="button" class="font-family-item" data-font="Helvetica" style="font-family:Helvetica">Helvetica</button>
                        <button type="button" class="font-family-item" data-font="Georgia" style="font-family:Georgia">Georgia</button>
                        <button type="button" class="font-family-item" data-font="PT Sans" style="font-family:'PT Sans'">PT Sans</button>
                        <button type="button" class="font-family-item" data-font="Comic Sans MS" style="font-family:'Comic Sans MS'">Comic Sans MS</button>
                        <div class="font-family-custom">
                            <button type="button" onclick="openCustomFontsModal()" data-i18n="toolbar.font_family_custom_btn">📁 Свой шрифт</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="color-picker-wrap" id="colorPickerWrapMain">
                <button type="button" class="color-picker-btn" title="Цвет текста" aria-label="Цвет текста" data-i18n-title="toolbar.text_color" data-i18n-aria="toolbar.text_color"><span class="color-preview" style="background:#333;"></span></button>
                <div class="color-palette-popover">
                    <div class="color-palette-grid" id="colorPaletteGridMain"></div>
                    <div class="color-palette-custom">
                        <label><span data-i18n="toolbar.custom_color">Свой цвет</span> <input type="color" id="textColorCustomMain" value="#333333"></label>
                    </div>
                </div>
            </div>
            
            <span class="toolbar-divider" id="divider-more"></span>
            
            <div class="more-menu-wrap" id="moreMenuWrap">
                <button type="button" class="format-btn" title="Прочее" data-i18n-title="toolbar.more" onclick="toggleMoreMenu()">⋯</button>
                <div class="more-menu-dropdown" id="moreMenuDropdown">
                    <button type="button" class="more-menu-item" onclick="saveDraft()" data-i18n="more_menu.save_draft">Сохранить в черновик</button>
                    <button type="button" class="more-menu-item has-submenu" onclick="toggleDraftsSubmenu(event)">
                        <span data-i18n="more_menu.drafts">Черновики</span>
                        <div class="more-submenu" id="draftsSubmenu">
                            <div class="more-submenu-empty" data-i18n="common.loading">Загрузка...</div>
                        </div>
                    </button>
                    <button type="button" class="more-menu-item" onclick="openSaveInclude()" data-i18n="more_menu.save_include">Сохранить в includes</button>
                    <button type="button" class="more-menu-item has-submenu" onclick="toggleIncludesSubmenu(event)">
                        <span data-i18n="more_menu.insert_include">Вставить</span>
                        <div class="more-submenu" id="includesSubmenu">
                            <div class="more-submenu-empty" data-i18n="common.loading">Загрузка...</div>
                        </div>
                    </button>
                    <button type="button" class="more-menu-item has-submenu" onclick="toggleArticlesSubmenu(event)">
                        <span data-i18n="more_menu.insert_article_link">Вставить ссылку на статью</span>
                        <div class="more-submenu" id="articlesSubmenu">
                            <div class="more-submenu-empty" data-i18n="common.loading">Загрузка...</div>
                        </div>
                    </button>
                    <button type="button" class="more-menu-item has-submenu" onclick="toggleTocSubmenu(event)">
                        <span data-i18n="more_menu.toc">Содержание</span>
                        <div class="more-submenu" id="tocSubmenu">
                            <div class="more-submenu-empty" data-i18n="more_menu.no_anchors">Нет якорей в статье</div>
                        </div>
                    </button>
                    <button type="button" class="more-menu-item" onclick="openFileUploadDialog()" data-i18n="more_menu.upload_file">Загрузить файл</button>
                    <button type="button" class="more-menu-item" onclick="insertCode()" data-i18n="more_menu.insert_code">Вставить блок кода</button>
                    <button type="button" class="more-menu-item" onclick="openInsertButtonDialog()" data-i18n="more_menu.insert_button">Вставить кнопку</button>
                    <button type="button" class="more-menu-item" onclick="openSmileSetsDialog()" data-i18n="more_menu.smile_sets">Наборы смайлов</button>
                    <button type="button" class="more-menu-item has-submenu" onclick="toggleSmilesSubmenu(event)">
                        <span data-i18n="more_menu.smiles">Смайлы</span>
                        <div class="more-submenu" id="smilesSubmenu">
                            <div class="more-submenu-empty" data-i18n="common.loading">Загрузка...</div>
                        </div>
                    </button>
                </div>
            </div>
            </div>
            <div id="toolbar-row-2" class="toolbar-row" data-placeholder="Ряд 2"></div>
        </div>
        
        <div class="header-right">
            <!-- Таймер автосохранения -->
            <div id="autosaveBadge" onmousedown="event.preventDefault()" style="display: none;">
                <span id="autosaveBadgeText">Автосохранение через 60с</span>
            </div>
            
            <!-- Кнопка сохранения -->
            <button type="submit" id="submitButton" form="blogForm" data-i18n="header.btn_save">Сохранить</button>
            
            <!-- Главное меню -->
            <div class="editor-menu-wrap" id="editorMenuWrap">
                <button type="button" class="editor-menu-btn" id="editorMenuBtn" aria-haspopup="true" aria-expanded="false" data-i18n="header.menu_btn">Меню</button>
                <div class="editor-menu-dropdown" role="menu">
                    <button type="button" class="editor-menu-item" role="menuitem" onclick="toggleManagePosts()" data-i18n="header.menu_manage_posts">Управление статьями</button>
                    <button type="button" class="editor-menu-item" role="menuitem" onclick="openTemplateManager()" data-i18n="header.menu_template_manager">Менеджер шаблонов</button>
                    <button type="button" class="editor-menu-item" role="menuitem" onclick="openGlobalSettings()" data-i18n="header.menu_settings">Параметры</button>
                    <button type="button" class="editor-menu-item" role="menuitem" onclick="openBackupManager()" data-i18n="header.menu_backup_manager">Менеджер бэкапов</button>
                    <button type="button" class="editor-menu-item" role="menuitem" onclick="openAutosaveManager()" data-i18n="header.menu_autosave_manager">Менеджер автосохранений</button>
                    <button type="button" class="editor-menu-item" id="theme-toggle" role="menuitem" onclick="openThemeManager()" data-i18n="header.menu_theme_manager">Изменить тему</button>
                    <button type="button" class="editor-menu-item" role="menuitem" onclick="openFtpModal()" data-i18n="header.menu_ftp_publish">Опубликовать по FTP</button>
                    <button type="button" class="editor-menu-item" id="goToBlogBtn" role="menuitem" onclick="window.location.href='<?php echo getDataUrl('blog.html'); ?>'" data-i18n="header.menu_go_to_blog">Перейти к Blog.html</button>
                    <button type="button" class="editor-menu-item" role="menuitem" onclick="openSystemUpdateModal()" data-i18n="header.menu_update_npblog">Обновить NPBlog</button>
                    <?php if (!empty($passwordHash)): ?>
                    <button type="button" class="editor-menu-item" role="menuitem" onclick="lockEditor()" style="color: #ef4444; font-weight: 600; border-top: 1px solid var(--border-color); padding-top: 8px; margin-top: 8px;" data-i18n="header.menu_lock">Заблокировать</button>
                    <?php endif; ?>
                    <?php
                    $editorVersion = 'unknown';
                    $versionFile = __DIR__ . '/version.json';
                    if (file_exists($versionFile)) {
                        $versionData = json_decode(file_get_contents($versionFile), true);
                        if (!empty($versionData['dev']) && ($versionData['dev'] === true || $versionData['dev'] === 'true')) {
                            $editorVersion = 'dev';
                        } elseif (!empty($versionData['version'])) {
                            $editorVersion = $versionData['version'];
                        }
                    }
                    ?>
                    <div class="editor-menu-version">ver <?php echo htmlspecialchars($editorVersion); ?></div>
                </div>
            </div>
        </div>
    </header>
<!-- тест2 -->
    <form id="blogForm">
        <input class="content228 editor-field" type="text" id="title" placeholder="Заголовок статьи" data-i18n-placeholder="header.placeholder_title" required>
        <textarea class="content228 editor-field" id="content" placeholder="Содержание статьи" data-i18n-placeholder="header.placeholder_content" style="display:none;"></textarea>
        <div id="contentVisual" class="content228 editor-field" contenteditable="true"></div>
    </form>

    <div id="editorContextMenu" class="editor-context-menu" role="menu">
        <button type="button" class="editor-context-item" data-cmd="paste" role="menuitem" data-i18n="context_menu.paste">Вставить</button>
        <button type="button" class="editor-context-item" data-cmd="copy" role="menuitem" data-i18n="context_menu.copy">Копировать</button>
        <button type="button" class="editor-context-item" data-cmd="cut" role="menuitem" data-i18n="context_menu.cut">Вырезать</button>
        <button type="button" class="editor-context-item" data-cmd="delete" role="menuitem" data-i18n="context_menu.delete">Удалить</button>
        <span class="editor-context-sep"></span>
        <button type="button" class="editor-context-item" data-cmd="link" role="menuitem" data-i18n="context_menu.link">Вставить ссылку</button>
        <button type="button" class="editor-context-item" data-cmd="image" role="menuitem" data-i18n="context_menu.image">Вставить изображение</button>
        <button type="button" class="editor-context-item" data-cmd="list" role="menuitem" data-i18n="context_menu.list">Вставить список</button>
        <span class="editor-context-sep table-context-sep" style="display: none;"></span>
        <button type="button" class="editor-context-item table-context-item" data-cmd="addRow" role="menuitem" style="display: none;" data-i18n="context_menu.add_row">Добавить строку</button>
        <button type="button" class="editor-context-item table-context-item" data-cmd="deleteRow" role="menuitem" style="display: none;" data-i18n="context_menu.delete_row">Удалить строку</button>
        <button type="button" class="editor-context-item table-context-item" data-cmd="addColumn" role="menuitem" style="display: none;" data-i18n="context_menu.add_column">Добавить столбец</button>
        <button type="button" class="editor-context-item table-context-item" data-cmd="deleteColumn" role="menuitem" style="display: none;" data-i18n="context_menu.delete_column">Удалить столбец</button>
        <button type="button" class="editor-context-item table-context-item" data-cmd="colorCell" role="menuitem" style="display: none;" data-i18n="context_menu.color_cell">Перекрасить ячейку</button>
        <span class="editor-context-sep table-context-sep" style="display: none;"></span>
        <button type="button" class="editor-context-item table-context-item" data-cmd="deleteTable" role="menuitem" style="display: none;" data-i18n="context_menu.delete_table">Удалить таблицу</button>
    </div>

    <!-- -->

        <div class="manage-posts" id="managePosts">
        <div class="manage-posts-header">
            <h2 data-i18n="header.manage_posts_title">Все статьи</h2>
            <button type="button" class="close-manage" onclick="toggleManagePosts()" aria-label="Закрыть">×</button>
        </div>
        <div id="blogSelectorContainer" style="display: none; padding: 12px 16px 0;">
            <label style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; opacity: 0.8; color: var(--text-color);" data-i18n="header.manage_posts_blog_label">Блог:</label>
            <select id="blogSelector" onchange="selectActiveBlog(this.value)">
            </select>
        </div>
        <div style="padding: 16px 16px 0;">
            <input type="text" id="postsSearchInput" class="posts-search-input" placeholder="🔍 Поиск по статьям..." data-i18n-placeholder="header.manage_posts_search" oninput="filterPosts()">
        </div>
        <div id="postsList"></div>
    </div>
    
    <!-- Менеджер шаблонов -->
    <?php require_once __DIR__ . '/modals_editor/template_manager_modal.php'; ?>

    <!-- Модальное окно добавления изображения -->
    <?php require_once __DIR__ . '/modals_editor/image_upload_modal.php'; ?>

    <!-- Модальное окно вставки кода -->
    <?php require_once __DIR__ . '/modals_editor/code_modal.php'; ?>

<!-- Модальное окно Вставки кнопки со ссылкой -->
<?php require_once __DIR__ . '/modals_editor/custom_button_modal.php'; ?>

<!-- Диалог загрузки файлов -->
<?php require_once __DIR__ . '/modals_editor/file_upload_modal.php'; ?>

<div id="fontSizeDialog" class="dialog">
    <div class="dialog-content">
        <h3 data-i18n="toolbar.font_size">Указать размер шрифта</h3>
        <input type="number" id="customFontSize" min="8" max="72" placeholder="Размер в px">
        <div class="dialog-buttons">
            <button onclick="setCustomFontSize()" data-i18n="common.apply">Применить</button>
            <button onclick="closeFontSizeDialog()" data-i18n="common.cancel">Отмена</button>
        </div>
    </div>
</div>


    <!-- Модальное окно добавления медиа -->
    <?php require_once __DIR__ . '/modals_editor/media_modal.php'; ?>

    <!-- Модальное окно сворачиваемого блока -->
    <?php require_once __DIR__ . '/modals_editor/spoiler_modal.php'; ?>

    <!-- Модальное окно выделения маркером -->
    <?php require_once __DIR__ . '/modals_editor/marker_modal.php'; ?>

    <!-- Модальное окно вставки таблицы -->
    <?php require_once __DIR__ . '/modals_editor/table_modal.php'; ?>

<!-- Модальное окно перекрашивания ячейки -->
<?php require_once __DIR__ . '/modals_editor/cell_color_modal.php'; ?>

    <!-- Модальное окно вставки ссылки -->
    <?php require_once __DIR__ . '/modals_editor/link_modal.php'; ?>

<!-- Модальное окно управления наборами смайлов -->
<?php require_once __DIR__ . '/modals_editor/smile_sets_modal.php'; ?>

<script src="modals/modal.js?v=<?php echo file_exists(__DIR__ . '/modals/modal.js') ? filemtime(__DIR__ . '/modals/modal.js') : time(); ?>"></script>
<script src="editor-main.js?v=<?php echo file_exists(__DIR__ . '/editor-main.js') ? filemtime(__DIR__ . '/editor-main.js') : time(); ?>"></script>

<script src="editor-img.js?v=<?php echo file_exists(__DIR__ . '/editor-img.js') ? filemtime(__DIR__ . '/editor-img.js') : time(); ?>"></script>

<!-- Модальное окно дополнительных настроек -->
<?php require_once __DIR__ . '/modals_editor/additional_settings_modal.php'; ?>

<!-- Модальное окно предупреждения о DEV сборке -->
<?php require_once __DIR__ . '/modals_editor/dev_warning_modal.php'; ?>

<!-- Модальное окно глобальных параметров -->
<?php require_once __DIR__ . '/modals_editor/global_settings_modal.php'; ?>

<!-- Модальное окно пользовательских шрифтов -->
<?php require_once __DIR__ . '/modals_editor/custom_fonts_modal.php'; ?>

<!-- Модальное окно менеджера автосохранений -->
<?php require_once __DIR__ . '/modals_editor/autosave_manager_modal.php'; ?>

<!-- Модальные окна обновления и отката системы -->
<?php require_once __DIR__ . '/modals_editor/system_update_modal.php'; ?>

<!-- Модальное окно публикации и загрузки по FTP -->
<?php require_once __DIR__ . '/modals_editor/ftp_upload_modal.php'; ?>

<script>
function openRestoreModal() {
    closeSystemUpdateModal();
    if (window.Modal) {
        Modal.open('#restoreSystemModal');
    } else {
        const modal = document.getElementById('restoreSystemModal');
        if (modal) modal.style.display = 'flex';
    }
    document.getElementById('restoreProgressContainer').style.display = 'none';
    document.getElementById('restoreSuccessContainer').style.display = 'none';
    
    // Load backups
    const list = document.getElementById('restoreBackupsList');
    list.innerHTML = '<div style="text-align: center; padding: 20px; opacity: 0.6;">Загрузка...</div>';
    
    fetch('restore_system.php?action=list_backups')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                list.innerHTML = '';
                if (data.backups.length === 0) {
                    list.innerHTML = '<p class="modal-text" style="text-align: center; padding: 20px; opacity: 0.6;">Бэкапы не найдены.</p>';
                    return;
                }
                data.backups.forEach(b => {
                    const el = document.createElement('div');
                    el.className = 'modal-section-card';
                    el.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 12px 14px;';
                    
                    const dt = new Date(b.time * 1000).toLocaleString();
                    const size = (b.size / 1024 / 1024).toFixed(2) + ' MB';
                    
                    el.innerHTML = `
                        <div style="color: var(--text-color);">
                            <div style="font-weight: 600; margin-bottom: 2px;">${b.filename}</div>
                            <div style="font-size: 12px; opacity: 0.7;">Создан: ${dt} | Размер: ${size}</div>
                        </div>
                        <button class="modal-btn modal-btn-danger" style="padding: 6px 14px; font-size: 13px;" onclick="startRestore('${b.filename}')">Восстановить</button>
                    `;
                    list.appendChild(el);
                });
            } else {
                list.innerHTML = '<p class="modal-error-msg" style="display:block; text-align: center;">Ошибка: ' + data.error + '</p>';
            }
        });
}

function closeRestoreModal() {
    if (window.Modal) {
        Modal.close('#restoreSystemModal');
    } else {
        const modal = document.getElementById('restoreSystemModal');
        if (modal) modal.style.display = 'none';
    }
}

async function startRestore(filename) {
    const isConfirmed = await showConfirm('Вы уверены? Это перезапишет текущие файлы системы файлами из бэкапа.', 'Восстановление системы');
    if (!isConfirmed) return;
    
    document.getElementById('restoreProgressContainer').style.display = 'flex';
    
    fetch('restore_system.php?action=restore', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ filename: filename })
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('restoreProgressContainer').style.display = 'none';
        if (data.success) {
            document.getElementById('restoreSuccessContainer').style.display = 'flex';
            document.getElementById('restoreBackupsList').style.display = 'none';
        } else {
            showAlert('Ошибка восстановления: ' + data.error);
        }
    })
    .catch(err => {
        document.getElementById('restoreProgressContainer').style.display = 'none';
        showAlert('Критическая ошибка при восстановлении');
    });
}

let currentAdditionalPostId = null;
let currentSelectedFont = null;

function openAdditionalSettings(postId, postTitle) {
    currentAdditionalPostId = postId;
    document.getElementById('additionalSettingsPostTitle').textContent = (window.t ? window.t('modals.post_label', 'Статья: ') : 'Статья: ') + postTitle;
    
    // Загружаем настройки из post_backgrounds.json
    fetch('get_post_backgrounds.php?postId=' + postId)
        .then(response => response.json())
        .then(data => {
            const settings = data.settings || {};
            
            // Устанавливаем режим отображения
            document.getElementById('backgroundMode').value = settings.backgroundMode || 'cover';
            
            // Устанавливаем область фона
            document.getElementById('backgroundScope').value = settings.backgroundScope || 'content';
            
            // Проверяем глобальный фон
            return fetch('serve_data.php?file=global-settings.json&t=' + Date.now())
                .then(response => {
                    if (!response.ok) {
                        throw new Error('No global settings');
                    }
                    return response.json();
                })
                .then(globalSettings => {
                    return { settings, globalSettings };
                })
                .catch(() => {
                    return { settings, globalSettings: null };
                });
        })
        .then(({ settings, globalSettings }) => {
            const currentBgInfo = document.getElementById('currentBackgroundInfo');
            const globalBgInfo = document.getElementById('globalBackgroundInfo');
            
            // Отображаем текущий фон статьи если есть
            if (settings.background) {
                const bgPreview = document.getElementById('currentBackgroundPreview');
                const bgName = document.getElementById('currentBackgroundName');
                const bgMode = document.getElementById('currentBackgroundMode');
                
                bgPreview.src = 'serve_data.php?file=backgrounds/' + settings.background;
                bgName.textContent = settings.background;
                
                const modeText = {
                    'cover': 'Растянуть',
                    'contain': 'По размеру',
                    'repeat': 'Замостить'
                };
                const scopeText = {
                    'content': 'Только статья',
                    'fullpage': 'Вся страница'
                };
                bgMode.textContent = 'Режим: ' + (modeText[settings.backgroundMode] || 'Растянуть') + ' | Область: ' + (scopeText[settings.backgroundScope] || 'Только статья');
                
                currentBgInfo.style.display = 'block';
                globalBgInfo.style.display = 'none';
            } else if (globalSettings && globalSettings.background) {
                // Показываем глобальный фон если у статьи нет своего
                const bgPreview = document.getElementById('globalBackgroundPreview');
                const bgName = document.getElementById('globalBackgroundName');
                const bgMode = document.getElementById('globalBackgroundModeText');
                
                bgPreview.src = 'serve_data.php?file=backgrounds/' + globalSettings.background;
                bgName.textContent = globalSettings.background;
                
                const modeText = {
                    'cover': 'Растянуть',
                    'contain': 'По размеру',
                    'repeat': 'Замостить'
                };
                const scopeText = {
                    'content': 'Только статья',
                    'fullpage': 'Вся страница'
                };
                bgMode.textContent = 'Режим: ' + (modeText[globalSettings.backgroundMode] || 'Растянуть') + ' | Область: ' + (scopeText[globalSettings.backgroundScope] || 'Только статья');
                
                globalBgInfo.style.display = 'block';
                currentBgInfo.style.display = 'none';
                
                // Устанавливаем значения из глобальных настроек
                document.getElementById('backgroundMode').value = globalSettings.backgroundMode || 'cover';
                document.getElementById('backgroundScope').value = globalSettings.backgroundScope || 'content';
            } else {
                currentBgInfo.style.display = 'none';
                globalBgInfo.style.display = 'none';
            }
            
            // Загружаем настройки подложки
            if (settings.overlayEnabled) {
                document.getElementById('overlayEnabled').checked = true;
                document.getElementById('overlayColor').value = settings.overlayColor || '#ffffff';
                document.getElementById('overlayOpacity').value = settings.overlayOpacity || 90;
                document.getElementById('overlayOpacityValue').textContent = (settings.overlayOpacity || 90) + '%';
                document.getElementById('overlaySettings').style.display = 'block';
            } else {
                document.getElementById('overlayEnabled').checked = false;
                document.getElementById('overlayColor').value = '#ffffff';
                document.getElementById('overlayOpacity').value = 90;
                document.getElementById('overlayOpacityValue').textContent = '90%';
                document.getElementById('overlaySettings').style.display = 'none';
            }
        })
        .catch(() => {
            document.getElementById('backgroundMode').value = 'cover';
            document.getElementById('backgroundScope').value = 'content';
            document.getElementById('currentBackgroundInfo').style.display = 'none';
            document.getElementById('globalBackgroundInfo').style.display = 'none';
            document.getElementById('overlayEnabled').checked = false;
            document.getElementById('overlaySettings').style.display = 'none';
        });
    
    if (window.Modal) {
        Modal.open('#additionalSettingsModal');
    } else {
        const modal = document.getElementById('additionalSettingsModal');
        if (modal) {
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        }
    }
}

function closeAdditionalSettings() {
    if (window.Modal) {
        Modal.close('#additionalSettingsModal');
    } else {
        const modal = document.getElementById('additionalSettingsModal');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        }
    }
    const bgInput = document.getElementById('backgroundInput');
    if (bgInput) bgInput.value = '';
    currentAdditionalPostId = null;
}

function uploadBackground() {
    const fileInput = document.getElementById('backgroundInput');
    const file = fileInput.files[0];
    const mode = document.getElementById('backgroundMode').value;
    const scope = document.getElementById('backgroundScope').value;
    
    if (!file) {
        showAlert(window.t ? window.t('notifications.select_file_alert', 'Выберите файл') : 'Выберите файл');
        return;
    }
    
    const formData = new FormData();
    formData.append('background', file);
    formData.append('postId', currentAdditionalPostId);
    formData.append('mode', mode);
    formData.append('scope', scope);
    
    fetch('upload_background.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(window.t ? window.t('notifications.bg_uploaded_alert', 'Фон успешно загружен') : 'Фон успешно загружен');
            fileInput.value = '';
            
            // Обновляем отображение текущего фона
            const bgPreview = document.getElementById('currentBackgroundPreview');
            const bgName = document.getElementById('currentBackgroundName');
            const bgMode = document.getElementById('currentBackgroundMode');
            const currentBgInfo = document.getElementById('currentBackgroundInfo');
            
            bgPreview.src = 'serve_data.php?file=backgrounds/' + data.filename;
            bgName.textContent = data.filename;
            
            const modeText = {
                'cover': 'Растянуть',
                'contain': 'По размеру',
                'repeat': 'Замостить'
            };
            const scopeText = {
                'content': 'Только статья',
                'fullpage': 'Вся страница'
            };
            bgMode.textContent = 'Режим: ' + (modeText[mode] || 'Растянуть') + ' | Область: ' + (scopeText[scope] || 'Только статья');
            
            currentBgInfo.style.display = 'block';
        } else {
            showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert(window.t ? window.t('notifications.bg_upload_error_alert', 'Ошибка загрузки фона') : 'Ошибка загрузки фона');
    });
}

function removeBackground() {
    showConfirm(window.t ? window.t('notifications.bg_restore_default_confirm', 'Вернуть стандартный фон?') : 'Вернуть стандартный фон?').then(result => {
        if (!result) return;
        
        fetch('remove_background.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ postId: currentAdditionalPostId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.globalApplied) {
                showAlert(window.t ? window.t('notifications.bg_individual_removed_alert', 'Индивидуальный фон удален. Применен глобальный фон.') : 'Индивидуальный фон удален. Применен глобальный фон.');
            } else {
                showAlert(window.t ? window.t('notifications.bg_removed_alert', 'Фон удален') : 'Фон удален');
            }
            
            // Перезагружаем настройки чтобы показать глобальный фон если он есть
            closeAdditionalSettings();
            // Небольшая задержка перед повторным открытием
            setTimeout(() => {
                // Находим название статьи
                fetch('serve_data.php?file=blog/posts-meta.json')
                    .then(response => response.json())
                    .then(meta => {
                        const post = meta.find(p => p.id === currentAdditionalPostId);
                        if (post) {
                            openAdditionalSettings(currentAdditionalPostId, post.title);
                        }
                    });
            }, 100);
        } else {
            showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert(window.t ? window.t('notifications.bg_remove_error_alert', 'Ошибка удаления фона') : 'Ошибка удаления фона');
    });
    });
}

function toggleOverlaySettings() {
    const enabled = document.getElementById('overlayEnabled').checked;
    const settings = document.getElementById('overlaySettings');
    settings.style.display = enabled ? 'block' : 'none';
}

function updateOpacityValue() {
    const value = document.getElementById('overlayOpacity').value;
    document.getElementById('overlayOpacityValue').textContent = value + '%';
}

function saveOverlaySettings() {
    const enabled = document.getElementById('overlayEnabled').checked;
    const color = document.getElementById('overlayColor').value;
    const opacity = document.getElementById('overlayOpacity').value;
    
    const data = {
        postId: currentAdditionalPostId,
        overlayEnabled: enabled,
        overlayColor: color,
        overlayOpacity: opacity
    };
    
    fetch('save_overlay_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(window.t ? window.t('notifications.overlay_settings_saved', 'Настройки подложки сохранены') : 'Настройки подложки сохранены');
        } else {
            showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert(window.t ? window.t('notifications.settings_save_error', 'Ошибка сохранения настроек') : 'Ошибка сохранения настроек');
    });
}

// Глобальные параметры
function checkTemplateAndToggleTabs() {
    return new Promise((resolve) => {
        const editId = (typeof window.getCurrentEditId === 'function') ? window.getCurrentEditId() : null;
        
        fetch('get_templates.php?t=' + Date.now())
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const defaultTemplate = data.default || 'main';
                    const postTemplates = data.post_templates || {};
                    
                    const activeTemplate = (editId && postTemplates[editId])
                                           ? postTemplates[editId]
                                           : defaultTemplate;
                    
                    const isCustom = (activeTemplate && activeTemplate !== 'main');
                    
                    const btnBackgrounds = document.getElementById('nav-btn-backgrounds');
                    const btnBlogview = document.getElementById('nav-btn-blogview');
                    
                    if (isCustom) {
                        if (btnBackgrounds) btnBackgrounds.style.display = 'none';
                        if (btnBlogview) btnBlogview.style.display = 'none';
                        
                        // Если в данный момент активна скрываемая вкладка, переключаем на 'autosave'
                        const activeBtn = document.querySelector('.global-nav-btn.active');
                        if (activeBtn) {
                            const sec = activeBtn.dataset.section;
                            if (sec === 'backgrounds' || sec === 'blogview') {
                                showGlobalSection('autosave');
                            }
                        }
                    } else {
                        if (btnBackgrounds) btnBackgrounds.style.display = 'block';
                        if (btnBlogview) btnBlogview.style.display = 'block';
                    }
                    resolve(isCustom);
                } else {
                    resolve(false);
                }
            })
            .catch(err => {
                console.error('Ошибка проверки шаблонов:', err);
                resolve(false);
            });
    });
}

function openGlobalSettings() {
    if (window.Modal) {
        Modal.open('#globalSettingsModal');
    } else {
        const modal = document.getElementById('globalSettingsModal');
        if (modal) {
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        }
    }
    
    checkTemplateAndToggleTabs().then(() => {
        loadGlobalBackground();
    });
}

function closeGlobalSettings() {
    if (window.Modal) {
        Modal.close('#globalSettingsModal');
    } else {
        const modal = document.getElementById('globalSettingsModal');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        }
    }
}

function showGlobalSection(sectionName) {
    const btn = document.getElementById('nav-btn-' + sectionName);
    if (btn && btn.style.display === 'none') {
        return;
    }
    // Обновляем активную кнопку навигации
    document.querySelectorAll('.global-nav-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.section === sectionName) {
            btn.classList.add('active');
        }
    });
    
    // Показываем нужную секцию
    document.querySelectorAll('.global-section').forEach(section => {
        section.style.display = 'none';
    });
    document.getElementById('globalSection-' + sectionName).style.display = 'block';
    
    // Обновляем заголовок
    const titles = {
        'backgrounds': (window.NPBlogI18n ? window.NPBlogI18n.t('settings.nav_backgrounds', 'Фон статей') : 'Фон статей'),
        'blogview': (window.NPBlogI18n ? window.NPBlogI18n.t('settings.nav_blogview', 'Вид blog.html') : 'Вид blog.html'),
        'autosave': (window.NPBlogI18n ? window.NPBlogI18n.t('settings.nav_autosave', 'Автосохранение') : 'Автосохранение'),
        'appearance': (window.NPBlogI18n ? window.NPBlogI18n.t('settings.nav_appearance', 'Внешний вид') : 'Внешний вид'),
        'experimental': (window.NPBlogI18n ? window.NPBlogI18n.t('settings.nav_experimental', 'Экспериментальные функции') : 'Экспериментальные функции'),
        'rss': (window.NPBlogI18n ? window.NPBlogI18n.t('settings.nav_rss', 'Интеграция RSS (Виджет)') : 'Интеграция RSS (Виджет)'),
        'rss_feed': (window.NPBlogI18n ? window.NPBlogI18n.t('settings.nav_rss_feed', 'RSS Лента (XML)') : 'RSS Лента (XML)'),
        'paths': (window.NPBlogI18n ? window.NPBlogI18n.t('settings.nav_paths', 'Пути к блoгам') : 'Пути к блoгам'),
        'security': (window.NPBlogI18n ? window.NPBlogI18n.t('settings.nav_security', 'Безопасность и доступ') : 'Безопасность и доступ'),
        'seo': (window.NPBlogI18n ? window.NPBlogI18n.t('settings.nav_seo', 'SEO и соцсети') : 'SEO и соцсети'),
        'language': (window.NPBlogI18n ? window.NPBlogI18n.t('settings.nav_language', 'Язык') : 'Язык')
    };
    document.getElementById('globalSectionTitle').textContent = titles[sectionName] || '';
    
    // Загружаем настройки для секции
    if (sectionName === 'blogview') {
        loadBlogViewSettings();
        checkCrossBlogNavStatus();
    } else if (sectionName === 'autosave') {
        loadAutosaveSettings();
    } else if (sectionName === 'appearance') {
        loadAppearanceSettings();
    } else if (sectionName === 'experimental') {
        loadExperimentalSettings();
    } else if (sectionName === 'rss') {
        loadRssSection();
    } else if (sectionName === 'rss_feed') {
        loadAndApplyAllSettings();
    } else if (sectionName === 'paths') {
        loadPathsSettings();
    } else if (sectionName === 'security') {
        loadSecuritySettings();
    } else if (sectionName === 'seo') {
        loadSeoSettings();
    } else if (sectionName === 'language') {
        loadLanguageSettings();
    }
}

function loadLanguageSettings() {
    var lang = (window.NPBlogI18n ? window.NPBlogI18n.getLanguage() : (localStorage.getItem('npblog_language') || 'ru'));
    var container = document.getElementById('languageCardsContainer');
    if (container && window.NPBlogI18n && typeof window.NPBlogI18n.renderLanguageCards === 'function') {
        window.NPBlogI18n.renderLanguageCards(container, lang);
    }
    selectLanguageOption(lang, false);
}

function selectLanguageOption(lang, autoSave) {
    if (!lang) return;
    lang = String(lang).toLowerCase().trim();
    
    var cards = document.querySelectorAll('.lang-selection-card');
    cards.forEach(function(c) {
        var cLang = c.getAttribute('data-lang-code');
        var radio = document.getElementById('langRadio-' + cLang);
        if (cLang === lang) {
            c.style.borderColor = 'var(--primary-color, #4CAF50)';
            c.style.background = 'rgba(76, 175, 80, 0.08)';
            if (radio) radio.checked = true;
        } else {
            c.style.borderColor = 'var(--border-color)';
            c.style.background = 'transparent';
            if (radio) radio.checked = false;
        }
    });

    if (autoSave && window.NPBlogI18n) {
        applySelectedLanguage(lang);
    }
}

function applySelectedLanguage(lang) {
    if (!lang) {
        var checkedRadio = document.querySelector('input[name="editor_lang_radio"]:checked');
        if (checkedRadio) {
            lang = checkedRadio.value;
        } else {
            lang = 'ru';
        }
    }
    
    if (window.NPBlogI18n) {
        window.NPBlogI18n.setLanguage(lang, true, function(res) {
            var ind = document.getElementById('langSavedIndicator');
            if (ind) {
                ind.style.display = 'inline';
                setTimeout(function() { ind.style.display = 'none'; }, 3000);
            }
            if (typeof showNotification === 'function') {
                showNotification(window.NPBlogI18n.t('settings.lang_success', 'Язык интерфейса успешно изменён!'), 'success');
            }
            var title = window.NPBlogI18n.t('settings.nav_language', 'Язык');
            var titleEl = document.getElementById('globalSectionTitle');
            if (titleEl) titleEl.textContent = title;
        });
    }
}

function loadRssSection() {
    // 1. Путь к папке блога относительно главной страницы сайта
    var blogPath = "data/blog/";
    
    // 2. Генерируем HTML код
    var htmlCode = '<!-- Контейнер виджета RSS ленты NPBlog -->\n<div id="npblog-rss-ticker"></div>';
    document.getElementById('rssHtmlCode').value = htmlCode;
    
    // 3. Генерируем чистый JS код без заготовленных стилей
    var jsCode = '<script>\n' +
        '(function() {\n' +
        '    // Путь к вашей папке блога относительно главной страницы\n' +
        '    var blogPath = "' + blogPath + '";\n\n' +
        '    fetch(blogPath + "posts-meta.json?t=" + Date.now())\n' +
        '        .then(function(response) {\n' +
        '            if (!response.ok) throw new Error("HTTP error " + response.status);\n' +
        '            return response.json();\n' +
        '        })\n' +
        '        .then(function(posts) {\n' +
        '            if (!posts || posts.length === 0) return;\n\n' +
        '            // Сортируем статьи по ID по убыванию, чтобы получить самую свежую\n' +
        '            posts.sort(function(a, b) { return b.id - a.id; });\n' +
        '            var latestPost = posts[0];\n' +
        '            if (!latestPost) return;\n\n' +
        '            var tickerContainer = document.getElementById("npblog-rss-ticker");\n' +
        '            if (!tickerContainer) return;\n\n' +
        '            // Создаем чистую ссылку с названием новой статьи без инлайновых стилей\n' +
        '            var link = document.createElement("a");\n' +
        '            link.href = blogPath + latestPost.filename;\n' +
        '            link.className = "npblog-rss-link";\n' +
        '            link.textContent = "Вышла новая статья: " + latestPost.title;\n\n' +
        '            tickerContainer.appendChild(link);\n' +
        '        })\n' +
        '        .catch(function(err) {\n' +
        '            console.error("NPBlog RSS Ticker error:", err);\n' +
        '        });\n' +
        '})();\n' +
        '<\/script>';
    document.getElementById('rssJsCode').value = jsCode;
    
    // 4. Отрисовываем чистый предпросмотр в админке
    var previewContainer = document.getElementById('rssLivePreviewContainer');
    previewContainer.innerHTML = '<div style="font-size: 14px; color: var(--text-color); opacity: 0.6; font-style: italic;">Загрузка данных...</div>';
    
    fetch('serve_data.php?file=blog/posts-meta.json&t=' + Date.now())
        .then(response => response.json())
        .then(posts => {
            if (!posts || posts.length === 0) {
                previewContainer.innerHTML = '<div style="font-size: 14px; color: #f44336; font-weight: 500;">Нет опубликованных статей для вывода в виджет</div>';
                return;
            }
            
            posts.sort((a, b) => b.id - a.id);
            var latestPost = posts[0];
            
            previewContainer.innerHTML = '';
            
            var link = document.createElement('a');
            link.href = DATA_URL_PREFIX + 'blog/' + latestPost.filename;
            link.target = '_blank';
            link.className = 'npblog-rss-link';
            link.textContent = 'Вышла новая статья: ' + latestPost.title;
            
            // Простые дефолтные стили ссылок браузера для чистого превью
            link.style.color = '#3b82f6';
            link.style.textDecoration = 'underline';
            link.style.cursor = 'pointer';
            link.style.fontSize = '14px';
            
            previewContainer.appendChild(link);
        })
        .catch(err => {
            console.error(err);
            previewContainer.innerHTML = '<div style="font-size: 14px; color: #f44336; font-weight: 500;">Ошибка загрузки превью виджета</div>';
        });
}

function copyToClipboard(elementId, btnElement) {
    var textarea = document.getElementById(elementId);
    if (!textarea) return;
    
    textarea.select();
    textarea.setSelectionRange(0, 99999); // Для мобильных устройств
    
    try {
        navigator.clipboard.writeText(textarea.value).then(function() {
            var originalText = btnElement.textContent;
            btnElement.textContent = 'Скопировано! ✓';
            btnElement.style.background = '#4CAF50';
            
            setTimeout(function() {
                btnElement.textContent = originalText;
                btnElement.style.background = '';
            }, 2000);
        });
    } catch (err) {
        // Резервный способ
        document.execCommand('copy');
        var originalText = btnElement.textContent;
        btnElement.textContent = 'Скопировано! ✓';
        btnElement.style.background = '#4CAF50';
        
        setTimeout(function() {
            btnElement.textContent = originalText;
            btnElement.style.background = '';
        }, 2000);
    }
}

function loadGlobalBackground() {
    fetch('serve_data.php?file=global-settings.json&t=' + Date.now())
        .then(response => {
            if (!response.ok) {
                // Файл не существует
                throw new Error('File not found');
            }
            return response.json();
        })
        .then(settings => {
            if (settings) {
                document.getElementById('hidePoweredByCheckbox').checked = !!settings.hidePoweredBy;
            } else {
                document.getElementById('hidePoweredByCheckbox').checked = false;
            }

            if (settings && settings.background) {
                document.getElementById('globalBackgroundMode').value = settings.backgroundMode || 'cover';
                document.getElementById('globalBackgroundScope').value = settings.backgroundScope || 'content';
                
                const bgPreview = document.getElementById('currentGlobalBackgroundPreview');
                const bgName = document.getElementById('currentGlobalBackgroundName');
                const bgMode = document.getElementById('currentGlobalBackgroundMode');
                const currentBgInfo = document.getElementById('currentGlobalBackgroundInfo');
                
                bgPreview.src = 'serve_data.php?file=backgrounds/' + settings.background;
                bgName.textContent = settings.background;
                
                const modeText = {
                    'cover': 'Растянуть',
                    'contain': 'По размеру',
                    'repeat': 'Замостить'
                };
                const scopeText = {
                    'content': 'Только статья',
                    'fullpage': 'Вся страница'
                };
                bgMode.textContent = 'Режим: ' + (modeText[settings.backgroundMode] || 'Растянуть') + ' | Область: ' + (scopeText[settings.backgroundScope] || 'Только статья');
                
                currentBgInfo.style.display = 'block';
            } else {
                document.getElementById('currentGlobalBackgroundInfo').style.display = 'none';
                // Устанавливаем значения по умолчанию
                document.getElementById('globalBackgroundMode').value = 'cover';
                document.getElementById('globalBackgroundScope').value = 'content';
            }
        })
        .catch(() => {
            // Файл не существует или произошла ошибка
            document.getElementById('hidePoweredByCheckbox').checked = false;
            document.getElementById('currentGlobalBackgroundInfo').style.display = 'none';
            // Устанавливаем значения по умолчанию
            document.getElementById('globalBackgroundMode').value = 'cover';
            document.getElementById('globalBackgroundScope').value = 'content';
        });
}

function uploadGlobalBackground() {
    const fileInput = document.getElementById('globalBackgroundInput');
    const file = fileInput.files[0];
    const mode = document.getElementById('globalBackgroundMode').value;
    const scope = document.getElementById('globalBackgroundScope').value;
    
    if (!file) {
        showAlert(window.t ? window.t('notifications.select_file_alert', 'Выберите файл') : 'Выберите файл');
        return;
    }
    
    const formData = new FormData();
    formData.append('background', file);
    formData.append('mode', mode);
    formData.append('scope', scope);
    
    fetch('upload_global_background.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(window.t ? window.t('notifications.global_bg_applied_all', 'Глобальный фон успешно загружен и применен ко всем статьям') : 'Глобальный фон успешно загружен и применен ко всем статьям');
            fileInput.value = '';
            loadGlobalBackground();
        } else {
            showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert(window.t ? window.t('notifications.bg_upload_error_alert', 'Ошибка загрузки фона') : 'Ошибка загрузки фона');
    });
}

function removeGlobalBackground() {
    showConfirm(window.t ? window.t('notifications.global_bg_delete_confirm', 'Удалить глобальный фон из всех статей?') : 'Удалить глобальный фон из всех статей?').then(result => {
        if (!result) return;
        
        fetch('remove_global_background.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(window.t ? window.t('notifications.global_bg_deleted', 'Глобальный фон удален') : 'Глобальный фон удален');
            loadGlobalBackground();
        } else {
            showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert(window.t ? window.t('notifications.bg_remove_error_alert', 'Ошибка удаления фона') : 'Ошибка удаления фона');
    });
    });
}

function savePoweredBySetting(checked) {
    fetch('save_global_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ hidePoweredBy: checked })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(window.t ? window.t('notifications.setting_saved', 'Настройка сохранена') : 'Настройка сохранена');
        } else {
            showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert(window.t ? window.t('notifications.setting_save_error', 'Ошибка сохранения настройки') : 'Ошибка сохранения настройки');
    });
}

function loadSeoSettings() {
    fetch('serve_data.php?file=global-settings.json&t=' + Date.now())
        .then(response => {
            if (!response.ok) {
                throw new Error('File not found');
            }
            return response.json();
        })
        .then(settings => {
            document.getElementById('seoBaseUrl').value = settings.baseUrl || '';
            document.getElementById('seoDefaultImage').value = settings.defaultOgImage || '';
            document.getElementById('seoDefaultDescription').value = settings.defaultOgDescription || '';
        })
        .catch(() => {
            document.getElementById('seoBaseUrl').value = '';
            document.getElementById('seoDefaultImage').value = '';
            document.getElementById('seoDefaultDescription').value = '';
        });
}

function saveSeoSettings() {
    const baseUrl = document.getElementById('seoBaseUrl').value.trim();
    const defaultOgImage = document.getElementById('seoDefaultImage').value.trim();
    const defaultOgDescription = document.getElementById('seoDefaultDescription').value.trim();
    
    fetch('save_global_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            baseUrl: baseUrl,
            defaultOgImage: defaultOgImage,
            defaultOgDescription: defaultOgDescription
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(window.t ? window.t('notifications.seo_settings_saved', 'Настройки SEO успешно сохранены') : 'Настройки SEO успешно сохранены');
        } else {
            showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert(window.t ? window.t('notifications.seo_settings_save_error', 'Ошибка сохранения настроек SEO') : 'Ошибка сохранения настроек SEO');
    });
}

function regenerateAllPostsMeta(btn) {
    showConfirm(window.t ? window.t('notifications.seo_regenerate_confirm', 'Перегенерировать метатеги во всех опубликованных статьях? Это обновит Open Graph и Twitter Cards превью на основе текущих глобальных настроек SEO.') : 'Перегенерировать метатеги во всех опубликованных статьях? Это обновит Open Graph и Twitter Cards превью на основе текущих глобальных настроек SEO.').then(result => {
        if (!result) return;
        
        const originalText = btn ? btn.textContent : '';
        if (btn) {
            btn.textContent = 'Обновление...';
            btn.disabled = true;
        }
        
        fetch('regenerate_seo.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(window.t ? window.t('notifications.seo_regenerated_param', 'Метатеги успешно обновлены! Обработано статей: ' + data.processed + ', обновлено: ' + data.updated, { processed: data.processed, updated: data.updated }) : 'Метатеги успешно обновлены! Обработано статей: ' + data.processed + ', обновлено: ' + data.updated);
            } else {
                showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showAlert(window.t ? window.t('notifications.seo_regenerate_error', 'Произошла ошибка при обновлении метатегов') : 'Произошла ошибка при обновлении метатегов');
        })
        .finally(() => {
            if (btn) {
                btn.textContent = originalText;
                btn.disabled = false;
            }
        });
    });
}

function updateBackgroundStyles() {
    showConfirm(window.t ? window.t('notifications.bg_update_styles_confirm', 'Обновить стили фона во всех статьях? Это применит новые отступы padding к существующим статьям.') : 'Обновить стили фона во всех статьях? Это применит новые отступы padding к существующим статьям.').then(result => {
        if (!result) return;
        
        fetch('update_background_styles.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(window.t ? window.t('notifications.bg_styles_updated_param', 'Стили обновлены в ' + data.updated + ' статьях', { updated: data.updated }) : 'Стили обновлены в ' + data.updated + ' статьях');
        } else {
            showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert(window.t ? window.t('notifications.bg_styles_update_error', 'Ошибка обновления стилей') : 'Ошибка обновления стилей');
    });
    });
}

// Функции для модальных предупреждений и подтверждений на базе Modal Framework
function showAlert(message, title = null) {
    if (window.NPBlogI18n && typeof window.NPBlogI18n.translateMessage === 'function') {
        message = window.NPBlogI18n.translateMessage(message);
        if (title) title = window.NPBlogI18n.translateMessage(title);
    }
    
    const defaultTitle = window.t ? window.t('common.info', 'Уведомление') : 'Уведомление';
    const okText = window.t ? window.t('common.ok', 'OK') : 'OK';

    if (window.Modal && typeof window.Modal.alert === 'function') {
        return window.Modal.alert({
            title: title || defaultTitle,
            message: message,
            okText: okText
        });
    }
    
    return new Promise((resolve) => {
        alert(message);
        resolve();
    });
}

function showConfirm(message, title = null) {
    if (window.NPBlogI18n && typeof window.NPBlogI18n.translateMessage === 'function') {
        message = window.NPBlogI18n.translateMessage(message);
        if (title) title = window.NPBlogI18n.translateMessage(title);
    }
    
    const defaultTitle = window.t ? window.t('common.confirm', 'Подтверждение') : 'Подтверждение';
    const confirmText = window.t ? window.t('common.confirm', 'Подтвердить') : 'Подтвердить';
    const cancelText = window.t ? window.t('common.cancel', 'Отмена') : 'Отмена';

    if (window.Modal && typeof window.Modal.confirm === 'function') {
        return window.Modal.confirm({
            title: title || defaultTitle,
            message: message,
            confirmText: confirmText,
            cancelText: cancelText
        });
    }
    
    return new Promise((resolve) => {
        const res = confirm(message);
        resolve(res);
    });
}

function showPrompt(message, defaultValue = '', title = null) {
    if (window.NPBlogI18n && typeof window.NPBlogI18n.translateMessage === 'function') {
        message = window.NPBlogI18n.translateMessage(message);
        if (title) title = window.NPBlogI18n.translateMessage(title);
    }
    
    const defaultTitle = window.t ? window.t('common.enter_value', 'Введите значение') : 'Введите значение';
    const saveText = window.t ? window.t('common.save', 'Сохранить') : 'Сохранить';
    const cancelText = window.t ? window.t('common.cancel', 'Отмена') : 'Отмена';

    if (window.Modal && typeof window.Modal.prompt === 'function') {
        return window.Modal.prompt({
            title: title || defaultTitle,
            message: message,
            defaultValue: defaultValue,
            confirmText: saveText,
            cancelText: cancelText
        });
    }
    
    return new Promise((resolve) => {
        const res = prompt(message, defaultValue);
        resolve(res);
    });
}

window.showAlert = showAlert;
window.showConfirm = showConfirm;
window.showPrompt = showPrompt;

// Функции для настроек вида blog.html
function loadBlogViewSettings() {
    fetch('serve_data.php?file=blog-view-settings.json&t=' + Date.now())
        .then(response => {
            if (!response.ok) {
                throw new Error('Settings not found');
            }
            return response.json();
        })
        .then(settings => {
            document.getElementById('blogPageTitle').value = settings.title || 'Блог';
            
            // Загружаем инфо о текущем фоне
            const bgInfo = document.getElementById('currentBlogBackgroundInfo');
            if (settings.background) {
                document.getElementById('currentBlogBackgroundPreview').src = 'serve_data.php?file=backgrounds/' + settings.background + '&t=' + Date.now();
                document.getElementById('currentBlogBackgroundName').textContent = 'Имя файла: ' + settings.background;
                
                let modeText = 'Режим: ';
                if (settings.backgroundMode === 'cover') modeText += 'Растянуть (cover)';
                else if (settings.backgroundMode === 'contain') modeText += 'По размеру (contain)';
                else if (settings.backgroundMode === 'repeat') modeText += 'Замостить (repeat)';
                
                document.getElementById('currentBlogBackgroundMode').textContent = modeText;
                document.getElementById('blogBackgroundMode').value = settings.backgroundMode || 'cover';
                bgInfo.style.display = 'block';
            } else {
                bgInfo.style.display = 'none';
            }
        })
        .catch(() => {
            document.getElementById('blogPageTitle').value = 'Блог';
            document.getElementById('currentBlogBackgroundInfo').style.display = 'none';
        });
}

function uploadBlogBackground() {
    const fileInput = document.getElementById('blogBackgroundInput');
    const file = fileInput.files[0];
    const mode = document.getElementById('blogBackgroundMode').value;
    
    if (!file) {
        showAlert(window.t ? window.t('notifications.select_file_alert', 'Выберите файл') : 'Выберите файл');
        return;
    }
    
    const formData = new FormData();
    formData.append('background', file);
    formData.append('mode', mode);
    
    fetch('upload_blog_background.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(window.t ? window.t('notifications.blog_bg_uploaded', 'Фон для blog.html успешно загружен и применен') : 'Фон для blog.html успешно загружен и применен');
            fileInput.value = '';
            loadBlogViewSettings();
        } else {
            showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert(window.t ? window.t('notifications.bg_upload_error_alert', 'Ошибка загрузки фона') : 'Ошибка загрузки фона');
    });
}

function removeBlogBackground() {
    showConfirm(window.t ? window.t('notifications.blog_bg_delete_confirm', 'Удалить фон со страницы blog.html?') : 'Удалить фон со страницы blog.html?').then(result => {
        if (!result) return;
        
        fetch('remove_blog_background.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(window.t ? window.t('notifications.blog_bg_deleted', 'Фон удален со страницы blog.html') : 'Фон удален со страницы blog.html');
                loadBlogViewSettings();
            } else {
                showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showAlert(window.t ? window.t('notifications.bg_remove_error_alert', 'Ошибка удаления фона') : 'Ошибка удаления фона');
        });
    });
}

// Переменные для автосохранения
let autosaveCountdownTimer = null;
let autosaveEnabled = false;
let autosaveInterval = 60;
let autosaveCountdown = 0;

// Функции для автосохранения
function loadAutosaveSettings() {
    loadAndApplyAllSettings();
}

function saveAutosaveSettings() {
    const enabled = document.getElementById('autosaveEnabled').checked;
    const interval = parseInt(document.getElementById('autosaveInterval').value);
    
    if (interval < 10 || interval > 600) {
        showAlert(window.t ? window.t('notifications.autosave_interval_range', 'Интервал должен быть от 10 до 600 секунд') : 'Интервал должен быть от 10 до 600 секунд');
        return;
    }
    
    fetch('save_editor_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ autosaveEnabled: enabled, autosaveInterval: interval })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadAndApplyAllSettings();
            showAlert(window.t ? window.t('notifications.autosave_settings_saved', 'Настройки автосохранения сохранены') : 'Настройки автосохранения сохранены');
        } else {
            showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert(window.t ? window.t('notifications.settings_save_error', 'Ошибка сохранения настроек') : 'Ошибка сохранения настроек');
    });
}

function startAutosave() {
    stopAutosave(); // Останавливаем предыдущий таймер если есть
    
    autosaveCountdown = autosaveInterval;
    updateAutosaveBadge();
    
    // Единый таймер обратного отсчета
    autosaveCountdownTimer = setInterval(() => {
        // Проверяем наличие контента
        if (!hasEditorContent()) {
            // Если контента нет, сбрасываем таймер
            autosaveCountdown = autosaveInterval;
            updateAutosaveBadge();
            return;
        }
        
        autosaveCountdown--;
        updateAutosaveBadge();
        
        if (autosaveCountdown <= 0) {
            // Выполняем автосохранение
            performAutosave();
            // Сбрасываем счетчик
            autosaveCountdown = autosaveInterval;
            updateAutosaveBadge();
        }
    }, 1000);
    
    document.getElementById('autosaveBadge').style.display = 'block';
}

function hasEditorContent() {
    const title = document.getElementById('title').value.trim();
    const content = editorMode === 'visual' 
        ? document.getElementById('contentVisual').innerHTML.trim()
        : document.getElementById('content').value.trim();
    
    // Проверяем, есть ли заголовок или контент (не считая пустые теги)
    const hasTitle = title.length > 0;
    const hasContent = content.length > 0 && content !== '<br>' && content !== '<div><br></div>';
    
    return hasTitle || hasContent;
}

function stopAutosave() {
    if (autosaveCountdownTimer) {
        clearInterval(autosaveCountdownTimer);
        autosaveCountdownTimer = null;
    }
    
    document.getElementById('autosaveBadge').style.display = 'none';
}

function updateAutosaveBadge() {
    const badge = document.getElementById('autosaveBadgeText');
    if (badge) {
        if (hasEditorContent()) {
            badge.textContent = window.t ? window.t('header.autosave_badge_timer', `Автосохранение через ${autosaveCountdown}с`, { sec: autosaveCountdown }) : `Автосохранение через ${autosaveCountdown}с`;
        } else {
            badge.textContent = window.t ? window.t('header.autosave_badge_waiting', 'Ожидание контента...') : 'Ожидание контента...';
        }
    }
}

function performAutosave() {
    const title = document.getElementById('title').value.trim();
    let content = editorMode === 'visual' 
        ? document.getElementById('contentVisual').innerHTML 
        : document.getElementById('content').value;
    
    if (window.enableMarkdown) {
        if (editorMode === 'visual') {
            document.getElementById('content').value = convertHtmlToMarkdown(document.getElementById('contentVisual').innerHTML);
        }
        const rawMarkdown = document.getElementById('content').value;
        const base64Markdown = btoa(unescape(encodeURIComponent(rawMarkdown)));
        content = parseMarkdownToHtml(rawMarkdown) + '\n<script type="text/markdown" id="markdown-source" data-base64="' + base64Markdown + '"></' + 'script>';
    }
    
    if (!title && !content) {
        return; // Нечего сохранять
    }
    
    const formData = new FormData();
    formData.append('title', title);
    formData.append('content', content);
    
    fetch('save_autosave.php', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        const text = await response.text();
        if (!text) throw new Error('Сервер вернул пустой ответ (0 байт). Возможно, ошибка PHP (без вывода ошибок) или блокировка Nginx.');
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Невалидный JSON от сервера. Ответ:', text);
            throw e;
        }
    })
    .then(data => {
        if (data.success) {
            console.log('Автосохранение выполнено');
            // Можно показать небольшое уведомление
            showNotification(window.t ? window.t('notifications.autosave_completed', 'Автосохранение выполнено') : 'Автосохранение выполнено', 'success');
        }
    })
    .catch(error => {
        console.error('Ошибка автосохранения:', error);
    });
}

function checkAutosaveExists() {
    // Эта функция сохранена для совместимости,
    // но больше не отображает элемент autosaveInfo, так как он был удален.
}

function toggleAutosavePreview() {
    // Эта функция вызывается при изменении чекбокса
    // Можно добавить дополнительную логику если нужно
}

// Функции менеджера автосохранений
function openAutosaveManager() {
    if (window.Modal) {
        Modal.open('#autosaveManagerModal');
    } else {
        const modal = document.getElementById('autosaveManagerModal');
        if (modal) {
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        }
    }
    
    loadAutosavesList();
}

function closeAutosaveManager() {
    if (window.Modal) {
        Modal.close('#autosaveManagerModal');
    } else {
        const modal = document.getElementById('autosaveManagerModal');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        }
    }
}

function loadAutosavesList() {
    fetch('get_all_autosaves.php')
        .then(response => response.json())
        .then(data => {
            const listDiv = document.getElementById('autosavesList');
            const emptyDiv = document.getElementById('autosavesEmpty');
            
            if (data.success && data.autosaves && data.autosaves.length > 0) {
                listDiv.innerHTML = '';
                listDiv.style.display = 'block';
                emptyDiv.style.display = 'none';
                
                const untitledText = window.t ? window.t('common.untitled', 'Без названия') : 'Без названия';
                const autosaveLabel = window.t ? window.t('modals.autosave_item_label', 'Автосохранение') : 'Автосохранение';
                const loadText = window.t ? window.t('common.load', 'Загрузить') : 'Загрузить';
                const deleteText = window.t ? window.t('common.delete', 'Удалить') : 'Удалить';

                const groupedAutosaves = {};
                data.autosaves.forEach(autosave => {
                    const title = autosave.title || untitledText;
                    if (!groupedAutosaves[title]) {
                        groupedAutosaves[title] = [];
                    }
                    groupedAutosaves[title].push(autosave);
                });
                
                let html = '';
                let groupIndex = 0;
                for (const title in groupedAutosaves) {
                    const saves = groupedAutosaves[title];
                    const safeTitle = escapeHtml(title);
                    
                    html += `
                        <div class="backup-post-group" id="autosave-group-${groupIndex}">
                            <div class="backup-post-header" onclick="toggleAutosaveGroup(${groupIndex})">
                                <h3 class="backup-post-title">${safeTitle}</h3>
                                <span class="backup-post-toggle">▼</span>
                            </div>
                            <div class="backup-list">
                                ${saves.map(autosave => {
                                    const dateObj = new Date(autosave.timestamp * 1000);
                                    const dateStr = dateObj.toLocaleString('ru-RU', {
                                        day: '2-digit', month: '2-digit', year: 'numeric',
                                        hour: '2-digit', minute: '2-digit'
                                    });
                                    return `
                                        <div class="backup-item">
                                            <div class="backup-info">
                                                <div class="backup-number">${autosaveLabel}</div>
                                                <div class="backup-date">${escapeHtml(dateStr)}</div>
                                            </div>
                                            <div class="backup-actions">
                                                <button type="button" class="backup-btn" onclick="loadAutosaveById('${autosave.id}')">${loadText}</button>
                                                <button type="button" class="backup-btn" onclick="deleteAutosaveById('${autosave.id}')" style="color: #dc3545; border-color: rgba(220, 53, 69, 0.3);">${deleteText}</button>
                                            </div>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                    `;
                    groupIndex++;
                }
                listDiv.innerHTML = html;
            } else {
                listDiv.style.display = 'none';
                emptyDiv.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки автосохранений:', error);
            document.getElementById('autosavesList').style.display = 'none';
            document.getElementById('autosavesEmpty').style.display = 'block';
        });
}

function toggleAutosaveGroup(index) {
    const group = document.getElementById('autosave-group-' + index);
    if (group) {
        group.classList.toggle('expanded');
    }
}

function loadAutosaveById(id) {
    fetch('get_autosave.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.autosave) {
            document.getElementById('title').value = data.autosave.title || '';
            
            let isMarkdown = false;
            let mdContent = '';
            if (data.autosave.content && data.autosave.content.includes('id="markdown-source"')) {
                const match = data.autosave.content.match(/id="markdown-source"\s+data-base64="([^"]*)"/);
                if (match && match[1]) {
                    try {
                        mdContent = decodeURIComponent(escape(atob(match[1])));
                        isMarkdown = true;
                    } catch (e) {
                        console.error('Error decoding markdown', e);
                    }
                }
            }
            
            if (isMarkdown) {
                window.enableMarkdown = true;
                document.body.classList.add('markdown-mode');
                const enableMarkdownCheck = document.getElementById('enableMarkdown');
                if (enableMarkdownCheck) enableMarkdownCheck.checked = true;
                
                document.getElementById('content').value = mdContent;
                const ve = document.getElementById('contentVisual');
                if (ve) {
                    ve.contentEditable = 'true';
                    if (editorMode === 'visual') {
                        ve.innerHTML = parseMarkdownToHtml(mdContent);
                    }
                }
            } else {
                if (window.enableMarkdown) {
                    document.getElementById('content').value = data.autosave.content || '';
                    const ve = document.getElementById('contentVisual');
                    if (ve) {
                        ve.contentEditable = 'true';
                        if (editorMode === 'visual') {
                            ve.innerHTML = parseMarkdownToHtml(data.autosave.content || '');
                        }
                    }
                } else {
                    window.enableMarkdown = false;
                    document.body.classList.remove('markdown-mode');
                    const enableMarkdownCheck = document.getElementById('enableMarkdown');
                    if (enableMarkdownCheck) enableMarkdownCheck.checked = false;
                    
                    document.getElementById('content').value = data.autosave.content || '';
                    const ve = document.getElementById('contentVisual');
                    if (ve) {
                        ve.contentEditable = 'true';
                        if (editorMode === 'visual') {
                            ve.innerHTML = data.autosave.content || '';
                        }
                    }
                }
            }
            
            showNotification(window.t ? window.t('notifications.autosave_loaded', 'Автосохранение загружено') : 'Автосохранение загружено', 'success');
            closeAutosaveManager();
            closeGlobalSettings();
        } else {
            showAlert(window.t ? window.t('notifications.autosave_load_error', 'Ошибка загрузки автосохранения') : 'Ошибка загрузки автосохранения');
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert(window.t ? window.t('notifications.autosave_load_failed', 'Ошибка при загрузке автосохранения') : 'Ошибка при загрузке автосохранения');
    });
}

function deleteAutosaveById(id) {
    showConfirm(window.t ? window.t('notifications.autosave_delete_confirm', 'Удалить это автосохранение?') : 'Удалить это автосохранение?').then(result => {
        if (!result) return;
        
        fetch('delete_autosave.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(window.t ? window.t('notifications.autosave_deleted', 'Автосохранение удалено') : 'Автосохранение удалено', 'success');
                loadAutosavesList();
                checkAutosaveExists();
            } else {
                showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showAlert(window.t ? window.t('notifications.autosave_delete_error', 'Ошибка при удалении автосохранения') : 'Ошибка при удалении автосохранения');
        });
    });
}

function saveBlogViewSettings() {
    const title = document.getElementById('blogPageTitle').value.trim() || 'Блог';
    
    fetch('save_blog_view_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ title: title })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(window.t ? window.t('notifications.blogview_saved_notice', 'Настройки сохранены! Изменения применятся при следующем обновлении списка статей.') : 'Настройки сохранены! Изменения применятся при следующем обновлении списка статей.');
        } else {
            showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert(window.t ? window.t('notifications.settings_save_error', 'Ошибка сохранения настроек') : 'Ошибка сохранения настроек');
    });
}

// Функции для навигации между блогами
let currentCrossBlogNavItems = [];

function checkCrossBlogNavStatus() {
    fetch('save_blog_nav.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=check'
    })
    .then(r => r.json())
    .then(data => {
        if (!data.is_standard) {
            document.getElementById('crossBlogNavStatus').style.display = 'block';
            document.getElementById('crossBlogNavEditor').style.display = 'none';
        } else {
            document.getElementById('crossBlogNavStatus').style.display = 'none';
            document.getElementById('crossBlogNavEditor').style.display = 'block';
            currentCrossBlogNavItems = data.buttons || [];
            document.getElementById('enableCrossBlogNav').checked = currentCrossBlogNavItems.length > 0;
            toggleCrossBlogNavUI();
            renderCrossBlogNavItems();
        }
    });
}

function toggleCrossBlogNavUI() {
    const isEnabled = document.getElementById('enableCrossBlogNav').checked;
    document.getElementById('crossBlogNavList').style.display = isEnabled ? 'block' : 'none';
}

function addCrossBlogNavItem() {
    currentCrossBlogNavItems.push({ text: 'Блог', url: '../data/blog.html' });
    renderCrossBlogNavItems();
}

function removeCrossBlogNavItem(index) {
    currentCrossBlogNavItems.splice(index, 1);
    renderCrossBlogNavItems();
}

function updateCrossBlogNavItem(index, field, value) {
    currentCrossBlogNavItems[index][field] = value;
}

function renderCrossBlogNavItems() {
    const container = document.getElementById('crossBlogNavItems');
    container.innerHTML = '';
    currentCrossBlogNavItems.forEach((item, index) => {
        const div = document.createElement('div');
        div.style.display = 'flex';
        div.style.gap = '10px';
        div.style.marginBottom = '10px';
        div.style.alignItems = 'center';
        
        div.innerHTML = `
            <input type="text" value="${item.text.replace(/"/g, '&quot;')}" onchange="updateCrossBlogNavItem(${index}, 'text', this.value)" placeholder="Название кнопки" style="flex: 1; min-width: 100px; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-color); color: var(--text-color);">
            <input type="text" value="${item.url.replace(/"/g, '&quot;')}" onchange="updateCrossBlogNavItem(${index}, 'url', this.value)" placeholder="URL (например: ../data2/blog.html)" style="flex: 2; min-width: 150px; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-color); color: var(--text-color);">
            <button type="button" onclick="removeCrossBlogNavItem(${index})" style="background: transparent; border: none; color: #dc3545; cursor: pointer; font-size: 18px; padding: 4px;" title="Удалить">✖</button>
        `;
        container.appendChild(div);
    });
}

function saveCrossBlogNav(action) {
    const isEnabled = document.getElementById('enableCrossBlogNav').checked;
    const buttonsToSave = isEnabled ? currentCrossBlogNavItems : [];
    
    fetch('save_blog_nav.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=' + action + '&buttons=' + encodeURIComponent(JSON.stringify(buttonsToSave))
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (action === 'apply_all') {
                showAlert(window.t ? window.t('notifications.crossblog_applied_param', 'Кнопки успешно применены к ' + data.updated_count + ' блогам со стандартными шаблонами!', { count: data.updated_count }) : 'Кнопки успешно применены к ' + data.updated_count + ' блогам со стандартными шаблонами!');
            } else {
                showAlert(window.t ? window.t('notifications.crossblog_saved', 'Кнопки навигации успешно сохранены!') : 'Кнопки навигации успешно сохранены!');
            }
            checkCrossBlogNavStatus();
        } else {
            showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + (data.message || (window.t ? window.t('notifications.unknown_error', 'Неизвестная ошибка') : 'Неизвестная ошибка')), { error: data.message || (window.t ? window.t('notifications.unknown_error', 'Неизвестная ошибка') : 'Неизвестная ошибка') }) : 'Ошибка: ' + (data.message || 'Неизвестная ошибка'));
        }
    })
    .catch(err => {
        showAlert(window.t ? window.t('notifications.crossblog_save_error', 'Произошла ошибка при сохранении кнопок.') : 'Произошла ошибка при сохранении кнопок.');
        console.error(err);
    });
}

// Единая функция загрузки и применения всех настроек редактора
function loadAndApplyAllSettings() {
    fetch('get_editor_settings.php?t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            if (data.success && data.settings) {
                const settings = data.settings;
                
                // 1. Автосохранение
                autosaveEnabled = settings.autosaveEnabled || false;
                autosaveInterval = settings.autosaveInterval || 60;
                
                const autosaveEnabledCheck = document.getElementById('autosaveEnabled');
                const autosaveIntervalInput = document.getElementById('autosaveInterval');
                if (autosaveEnabledCheck) autosaveEnabledCheck.checked = autosaveEnabled;
                if (autosaveIntervalInput) autosaveIntervalInput.value = autosaveInterval;
                
                if (autosaveEnabled) {
                    startAutosave();
                } else {
                    stopAutosave();
                }
                // 2. Язык интерфейса
                if (settings.language && window.NPBlogI18n) {
                    if (window.NPBlogI18n.getLanguage() !== settings.language) {
                        window.NPBlogI18n.setLanguage(settings.language, false);
                    }
                }
                
                // 3. Внешний вид и экспериментальные функции
                const hideModeButtons = settings.hideEditorModeButtons || false;
                const amoledTheme = settings.amoledTheme || false;
                const enableUndoRedo = settings.enableUndoRedo || false;
                const smoothTyping = settings.smoothTyping || false;
                const headerBottomPosition = settings.headerBottomPosition || false;
                const enableMarkdown = settings.enableMarkdown || false;
                const contentWidth = settings.contentWidth || 920;
                
                const hideModeCheck = document.getElementById('hideEditorModeButtons');
                const amoledCheck = document.getElementById('amoledTheme');
                const enableUndoRedoCheck = document.getElementById('enableUndoRedo');
                const smoothTypingCheck = document.getElementById('smoothTyping');
                const headerBottomCheck = document.getElementById('headerBottomPosition');
                const enableMarkdownCheck = document.getElementById('enableMarkdown');
                const contentWidthInput = document.getElementById('settingsContentWidth');
                
                if (hideModeCheck) hideModeCheck.checked = hideModeButtons;
                if (amoledCheck) amoledCheck.checked = amoledTheme;
                if (enableUndoRedoCheck) enableUndoRedoCheck.checked = enableUndoRedo;
                if (smoothTypingCheck) smoothTypingCheck.checked = smoothTyping;
                if (headerBottomCheck) headerBottomCheck.checked = headerBottomPosition;
                if (enableMarkdownCheck) enableMarkdownCheck.checked = enableMarkdown;
                if (contentWidthInput) contentWidthInput.value = contentWidth;

                // Apply content width dynamically
                window.editorContentWidth = contentWidth;
                let widthStyle = document.getElementById('editor-width-style');
                if (!widthStyle) {
                    widthStyle = document.createElement('style');
                    widthStyle.id = 'editor-width-style';
                    document.head.appendChild(widthStyle);
                }
                const bodyMaxWidth = Math.max(1200, contentWidth + 40);
                widthStyle.innerHTML = `
                    body {
                        max-width: ${bodyMaxWidth}px !important;
                    }
                    .editor-field,
                    .editor-toolbar-wrap,
                    .formatting-buttons.is-floating {
                        max-width: ${contentWidth}px !important;
                    }
                `;
                
                window.enableMarkdown = enableMarkdown;
                if (enableMarkdown) {
                    document.body.classList.add('markdown-mode');
                    const ve = document.getElementById('contentVisual');
                    if (ve) ve.contentEditable = 'true';
                } else {
                    document.body.classList.remove('markdown-mode');
                    const ve = document.getElementById('contentVisual');
                    if (ve) ve.contentEditable = 'true';
                }
                
                if (headerBottomPosition) {
                    document.body.classList.add('header-bottom');
                } else {
                    document.body.classList.remove('header-bottom');
                }
                
                window.smoothTypingEnabled = smoothTyping;
                if (typeof applySmoothTypingState === 'function') {
                    applySmoothTypingState();
                }
                
                window.amoledThemeEnabled = amoledTheme;
                if (typeof updateAmoledState === 'function') {
                    updateAmoledState();
                }
                
                // Переключение отображения переключателя режимов и разделителей
                const modeToggle = document.getElementById('headerModeToggle');
                const logoDivider = document.getElementById('logoDivider');
                if (modeToggle) {
                    if (hideModeButtons) {
                        modeToggle.style.display = 'none';
                        if (logoDivider) logoDivider.style.display = 'none';
                        if (typeof setMode === 'function') {
                            setMode('visual');
                        }
                    } else {
                        modeToggle.style.display = 'flex';
                        if (logoDivider) logoDivider.style.display = '';
                    }
                }
                
                // Переключение отображения кнопок истории (undo/redo) и разделителя
                const editorActions = document.getElementById('headerEditorActions');
                const modeActionsDivider = document.getElementById('modeActionsDivider');
                if (editorActions) {
                    if (enableUndoRedo) {
                        editorActions.style.display = 'flex';
                        if (modeActionsDivider) modeActionsDivider.style.display = '';
                        
                        const undoBtn = document.getElementById('undoBtn');
                        const redoBtn = document.getElementById('redoBtn');
                        if (undoBtn) undoBtn.style.display = '';
                        if (redoBtn) redoBtn.style.display = '';
                    } else {
                        editorActions.style.display = 'none';
                        if (modeActionsDivider) modeActionsDivider.style.display = 'none';
                    }
                }
                
                // Управление разделителем перед панелью форматирования
                const actionsFormattingDivider = document.getElementById('actionsFormattingDivider');
                if (actionsFormattingDivider) {
                    if (!hideModeButtons || enableUndoRedo) {
                        actionsFormattingDivider.style.display = '';
                    } else {
                        actionsFormattingDivider.style.display = 'none';
                    }
                }
                
                // RSS Лента
                const rssEnabled = settings.rss_enabled || false;
                const rssBaseUrl = settings.rss_base_url || '';
                const rssTitle = settings.rss_title || 'NPBlog Feed';
                const rssDesc = settings.rss_description || 'NPBlog RSS Feed';
                const rssUseFirstLine = (settings.rss_use_first_line !== undefined) ? settings.rss_use_first_line : true;
                const rssContentTemplate = settings.rss_content_template || '*content*\n\n<p><a href="*url*">Читать в блоге</a></p>';

                const rssFeedEnabledCheck = document.getElementById('rssFeedEnabled');
                const rssFeedBaseUrlInput = document.getElementById('rssFeedBaseUrl');
                const rssFeedTitleInput = document.getElementById('rssFeedTitle');
                const rssFeedDescriptionInput = document.getElementById('rssFeedDescription');
                const rssFeedUseFirstLineCheck = document.getElementById('rssFeedUseFirstLine');
                const rssFeedContentTemplateInput = document.getElementById('rssFeedContentTemplate');

                if (rssFeedEnabledCheck) {
                    rssFeedEnabledCheck.checked = rssEnabled;
                    document.getElementById('rssFeedSettingsDetails').style.display = rssEnabled ? 'block' : 'none';
                }
                if (rssFeedBaseUrlInput) rssFeedBaseUrlInput.value = rssBaseUrl;
                if (rssFeedTitleInput) rssFeedTitleInput.value = rssTitle;
                if (rssFeedDescriptionInput) rssFeedDescriptionInput.value = rssDesc;
                if (rssFeedUseFirstLineCheck) rssFeedUseFirstLineCheck.checked = rssUseFirstLine;
                if (rssFeedContentTemplateInput) rssFeedContentTemplateInput.value = rssContentTemplate;
                
                // 3. Пути к блогам и Безопасность
                var blogPaths = settings.blog_paths || [];
                if (!Array.isArray(blogPaths) || blogPaths.length === 0) {
                    if (settings.data_path) {
                        blogPaths = [settings.data_path];
                    } else {
                        blogPaths = ['/var/www/html/data'];
                    }
                }
                window.allBlogPaths = blogPaths;
                window.currentActiveBlogPath = settings.active_blog_path || blogPaths[0];
                
                renderBlogPathsInputs(blogPaths, window.currentActiveBlogPath);
                updateBlogSelectorUI(blogPaths, window.currentActiveBlogPath);
                
                const passwordEnabled = settings.password_set || false;
                const ipWhitelistEnabled = settings.ip_whitelist_enabled || false;
                
                const ipWhitelistCheck = document.getElementById('settingsIpWhitelistEnabled');
                if (ipWhitelistCheck) ipWhitelistCheck.checked = ipWhitelistEnabled;
                
                const notSetDiv = document.getElementById('securityPasswordNotSet');
                const setDiv = document.getElementById('securityPasswordSet');
                
                if (passwordEnabled) {
                    if (notSetDiv) notSetDiv.style.display = 'none';
                    if (setDiv) setDiv.style.display = 'block';
                    
                    const changeForm = document.getElementById('changePasswordFormContainer');
                    const disableForm = document.getElementById('disablePasswordFormContainer');
                    if (changeForm) changeForm.style.display = 'none';
                    if (disableForm) disableForm.style.display = 'none';
                } else {
                    if (notSetDiv) notSetDiv.style.display = 'block';
                    if (setDiv) setDiv.style.display = 'none';
                    
                    const passwordEnabledCheck = document.getElementById('settingsPasswordEnabled');
                    if (passwordEnabledCheck) {
                        passwordEnabledCheck.checked = false;
                        togglePasswordFieldsVisibility();
                    }
                }
                if (typeof applyHeaderLayout === 'function') {
                    applyHeaderLayout(settings.headerLayout);
                }
                
                // Автоматическое обновление высоты хедера в зависимости от кнопок во втором ряду
                if (typeof updateHeaderHeightState === 'function') {
                    updateHeaderHeightState();
                }
                
                if (typeof adjustHeaderPadding === 'function') {
                    adjustHeaderPadding();
                }
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки настроек редактора:', error);
        });
}

// Функции для настроек внешнего вида
function loadAppearanceSettings() {
    loadAndApplyAllSettings();
}

function saveAppearanceSettings() {
    const hideModeCheck = document.getElementById('hideEditorModeButtons');
    const hideButtons = hideModeCheck ? hideModeCheck.checked : false;
    const amoled = document.getElementById('amoledTheme').checked;
    const smooth = document.getElementById('smoothTyping').checked;
    const headerBottom = document.getElementById('headerBottomPosition').checked;
    const contentWidth = parseInt(document.getElementById('settingsContentWidth').value) || 920;
    
    fetch('save_editor_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ 
            hideEditorModeButtons: hideButtons,
            amoledTheme: amoled,
            smoothTyping: smooth,
            headerBottomPosition: headerBottom,
            contentWidth: contentWidth
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadAndApplyAllSettings();
            showAlert(window.t ? window.t('notifications.appearance_settings_saved', 'Настройки внешнего вида сохранены!') : 'Настройки внешнего вида сохранены!');
        } else {
            showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert(window.t ? window.t('notifications.settings_save_error', 'Ошибка сохранения настроек') : 'Ошибка сохранения настроек');
    });
}

function applyAppearanceSettings() {
    loadAndApplyAllSettings();
}

// Функции для экспериментальных настроек
function loadExperimentalSettings() {
    loadAndApplyAllSettings();
}

function saveExperimentalSettings() {
    const enableUndoRedo = document.getElementById('enableUndoRedo').checked;
    const enableMarkdown = document.getElementById('enableMarkdown').checked;
    
    fetch('save_editor_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ 
            enableUndoRedo: enableUndoRedo,
            enableMarkdown: enableMarkdown
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadAndApplyAllSettings();
            showAlert(window.t ? window.t('notifications.experimental_settings_saved', 'Экспериментальные настройки сохранены!') : 'Экспериментальные настройки сохранены!');
        } else {
            showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert(window.t ? window.t('notifications.settings_save_error', 'Ошибка сохранения настроек') : 'Ошибка сохранения настроек');
    });
}

function deleteAllCustomTemplates() {
    showConfirm(window.t ? window.t('notifications.custom_templates_delete_all_confirm', 'Вы действительно хотите безвозвратно удалить ВСЕ кастомные шаблоны? Стандартный шаблон NPBlog будет сохранен.') : 'Вы действительно хотите безвозвратно удалить ВСЕ кастомные шаблоны? Стандартный шаблон NPBlog будет сохранен.').then(confirmed => {
        if (!confirmed) return;
        
        showNotification(window.t ? window.t('notifications.custom_templates_deleting', 'Удаление кастомных шаблонов...') : 'Удаление кастомных шаблонов...', 'info');
        
        fetch('delete_custom_templates.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(window.t ? window.t('notifications.custom_templates_deleted_all', 'Все кастомные шаблоны успешно удалены') : 'Все кастомные шаблоны успешно удалены', 'success');
                // Обновляем список шаблонов в менеджере, если он открыт
                const templateDialog = document.getElementById('templateManagerDialog');
                if (typeof openTemplateManager === 'function' && templateDialog && (templateDialog.classList.contains('show') || templateDialog.style.display === 'block')) {
                    openTemplateManager();
                }
            } else {
                showAlert(window.t ? window.t('notifications.custom_templates_delete_error_param', 'Ошибка удаления: ' + data.error, { error: data.error }) : 'Ошибка удаления: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showAlert(window.t ? window.t('notifications.custom_templates_network_error', 'Ошибка сети при удалении шаблонов') : 'Ошибка сети при удалении шаблонов');
        });
    });
}

function applyExperimentalSettings() {
    loadAndApplyAllSettings();
}

function loadSecuritySettings() {
    loadAndApplyAllSettings();
}

function togglePasswordFieldsVisibility() {
    const enabled = document.getElementById('settingsPasswordEnabled').checked;
    const passwordFields = document.getElementById('securityPasswordFields');
    if (passwordFields) {
        passwordFields.style.display = enabled ? 'block' : 'none';
    }
}

function showChangePasswordForm() {
    const changeForm = document.getElementById('changePasswordFormContainer');
    const disableForm = document.getElementById('disablePasswordFormContainer');
    if (changeForm) {
        changeForm.style.display = changeForm.style.display === 'none' ? 'block' : 'none';
        if (changeForm.style.display === 'block') {
            const oldPassInput = document.getElementById('changeSettingsOldPassword');
            if (oldPassInput) oldPassInput.focus();
        }
    }
    if (disableForm) disableForm.style.display = 'none';
}

function showDisablePasswordForm() {
    const changeForm = document.getElementById('changePasswordFormContainer');
    const disableForm = document.getElementById('disablePasswordFormContainer');
    if (disableForm) {
        disableForm.style.display = disableForm.style.display === 'none' ? 'block' : 'none';
        if (disableForm.style.display === 'block') {
            const disablePassInput = document.getElementById('disableSettingsPassword');
            if (disablePassInput) disablePassInput.focus();
        }
    }
    if (changeForm) changeForm.style.display = 'none';
}

function saveSecuritySettings() {
    const isPasswordSet = document.getElementById('securityPasswordSet').style.display === 'block';
    const ipWhitelistEnabled = document.getElementById('settingsIpWhitelistEnabled').checked;
    
    let payload = {
        ip_whitelist_enabled: ipWhitelistEnabled
    };
    
    if (!isPasswordSet) {
        const passwordEnabled = document.getElementById('settingsPasswordEnabled').checked;
        payload.password_enabled = passwordEnabled;
        
        if (passwordEnabled) {
            const newPassword = document.getElementById('settingsNewPassword').value;
            const confirmPassword = document.getElementById('settingsConfirmPassword').value;
            
            if (!newPassword) {
                showAlert(window.t ? window.t('notifications.sec_enter_new_pwd', 'Введите новый пароль!') : 'Введите новый пароль!');
                return;
            }
            if (newPassword !== confirmPassword) {
                showAlert(window.t ? window.t('notifications.sec_pwd_mismatch', 'Пароли не совпадают!') : 'Пароли не совпадают!');
                return;
            }
            payload.new_password = newPassword;
        }
    } else {
        const changeFormVisible = document.getElementById('changePasswordFormContainer').style.display === 'block';
        const disableFormVisible = document.getElementById('disablePasswordFormContainer').style.display === 'block';
        
        if (changeFormVisible) {
            const oldPassword = document.getElementById('changeSettingsOldPassword').value;
            const newPassword = document.getElementById('changeSettingsNewPassword').value;
            const confirmPassword = document.getElementById('changeSettingsConfirmPassword').value;
            
            if (!oldPassword) {
                showAlert(window.t ? window.t('notifications.sec_enter_old_pwd', 'Введите старый пароль!') : 'Введите старый пароль!');
                return;
            }
            if (!newPassword) {
                showAlert(window.t ? window.t('notifications.sec_enter_new_pwd', 'Введите новый пароль!') : 'Введите новый пароль!');
                return;
            }
            if (newPassword !== confirmPassword) {
                showAlert(window.t ? window.t('notifications.sec_new_pwd_mismatch', 'Новые пароли не совпадают!') : 'Новые пароли не совпадают!');
                return;
            }
            
            payload.password_enabled = true;
            payload.old_password = oldPassword;
            payload.new_password = newPassword;
        } else if (disableFormVisible) {
            const oldPassword = document.getElementById('disableSettingsPassword').value;
            if (!oldPassword) {
                showAlert(window.t ? window.t('notifications.sec_enter_current_to_disable', 'Введите текущий пароль для подтверждения отключения!') : 'Введите текущий пароль для подтверждения отключения!');
                return;
            }
            
            payload.password_enabled = false;
            payload.old_password = oldPassword;
        } else {
            payload.password_enabled = true;
        }
    }
    
    fetch('save_editor_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const inputs = [
                'settingsNewPassword', 'settingsConfirmPassword',
                'changeSettingsOldPassword', 'changeSettingsNewPassword', 'changeSettingsConfirmPassword',
                'disableSettingsPassword'
            ];
            inputs.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            
            loadAndApplyAllSettings();
            showAlert(window.t ? window.t('notifications.sec_settings_saved', 'Настройки безопасности успешно сохранены!') : 'Настройки безопасности успешно сохранены!');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert(window.t ? window.t('notifications.sec_save_error', 'Ошибка сохранения настроек безопасности') : 'Ошибка сохранения настроек безопасности');
    });
}

// --- Функции управления путями к блогам ---
function loadPathsSettings() {
    loadAndApplyAllSettings();
}

function renderBlogPathsInputs(paths, activePath) {
    const container = document.getElementById('blogPathsListContainer');
    if (!container) return;
    container.innerHTML = '';
    
    const list = (Array.isArray(paths) && paths.length > 0) ? paths : ['/var/www/html/data'];
    list.forEach(pathVal => {
        addBlogPathRow(pathVal);
    });
}

function addBlogPathRow(value = '') {
    const container = document.getElementById('blogPathsListContainer');
    if (!container) return;
    
    const row = document.createElement('div');
    row.className = 'blog-path-item-row';
    row.style.cssText = 'display: flex; gap: 10px; align-items: center;';
    
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'blog-path-input';
    input.placeholder = '/var/www/html/data';
    input.value = value;
    input.style.cssText = 'flex: 1; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); font-size: 14px; box-sizing: border-box;';
    
    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'global-action-btn';
    deleteBtn.style.cssText = 'background: #ef4444; color: #ffffff; border-color: #ef4444; padding: 8px 14px; font-size: 13px; cursor: pointer; border-radius: 8px;';
    deleteBtn.textContent = 'Удалить';
    deleteBtn.onclick = function() {
        removeBlogPathRow(this);
    };
    
    row.appendChild(input);
    row.appendChild(deleteBtn);
    container.appendChild(row);
    if (value === '') {
        input.focus();
    }
}

function removeBlogPathRow(btn) {
    const container = document.getElementById('blogPathsListContainer');
    if (!container) return;
    const rows = container.querySelectorAll('.blog-path-item-row');
    if (rows.length <= 1) {
        const input = rows[0].querySelector('.blog-path-input');
        if (input) input.value = '';
        return;
    }
    const row = btn.closest('.blog-path-item-row');
    if (row) row.remove();
}

function savePathsSettings() {
    const inputs = document.querySelectorAll('.blog-path-input');
    const paths = [];
    inputs.forEach(input => {
        const val = input.value.trim();
        if (val !== '' && !paths.includes(val)) {
            paths.push(val);
        }
    });
    
    if (paths.length === 0) {
        showAlert(window.t ? window.t('notifications.paths_at_least_one', 'Укажите хотя бы один путь к папке блога!') : 'Укажите хотя бы один путь к папке блога!');
        return;
    }
    
    fetch('save_editor_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ blog_paths: paths })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadAndApplyAllSettings();
            showAlert(window.t ? window.t('notifications.paths_saved', 'Настройки путей сохранены!') : 'Настройки путей сохранены!');
        } else {
            showAlert(window.t ? window.t('notifications.paths_save_error_param', 'Ошибка сохранения путей: ' + data.error, { error: data.error }) : 'Ошибка сохранения путей: ' + data.error);
        }
    })
    .catch(err => {
        console.error('Ошибка сохранения путей:', err);
        showAlert(window.t ? window.t('notifications.paths_save_failed', 'Ошибка при сохранении путей') : 'Ошибка при сохранении путей');
    });
}

function getBlogFolderName(pathStr, allPaths) {
    if (!pathStr) return 'data';
    const clean = pathStr.replace(/\\/g, '/').replace(/\/+$/, '');
    const parts = clean.split('/');
    const last = parts[parts.length - 1] || clean;
    if (allPaths && Array.isArray(allPaths)) {
        const duplicates = allPaths.filter(p => {
            const c = p.replace(/\\/g, '/').replace(/\/+$/, '');
            const pts = c.split('/');
            return (pts[pts.length - 1] || c) === last;
        });
        if (duplicates.length > 1 && parts.length >= 2) {
            return parts.slice(-2).join('/');
        }
    }
    return last;
}

function updateBlogSelectorUI(paths, activePath) {
    const container = document.getElementById('blogSelectorContainer');
    const selector = document.getElementById('blogSelector');
    if (!container || !selector) return;
    
    if (!Array.isArray(paths) || paths.length <= 1) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'block';
    selector.innerHTML = '';
    
    paths.forEach(p => {
        const option = document.createElement('option');
        option.value = p;
        option.textContent = getBlogFolderName(p, paths);
        option.title = p;
        if (p === activePath) {
            option.selected = true;
        }
        selector.appendChild(option);
    });
}

function selectActiveBlog(selectedPath) {
    fetch('save_editor_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ active_blog_path: selectedPath })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.currentActiveBlogPath = selectedPath;
            const folderName = getBlogFolderName(selectedPath, window.allBlogPaths);
            if (typeof showNotification === 'function') {
                showNotification(window.t ? window.t('notifications.blog_selected_param', 'Выбран блог: ' + folderName, { name: folderName }) : 'Выбран блог: ' + folderName, 'info');
            }
            if (typeof loadPosts === 'function') {
                loadPosts();
            }
            if (data.blogUrl) {
                const goToBlogBtn = document.getElementById('goToBlogBtn');
                if (goToBlogBtn) {
                    goToBlogBtn.onclick = function() { window.location.href = data.blogUrl; };
                }
            }
        } else {
            showAlert(window.t ? window.t('notifications.blog_select_error_param', 'Ошибка при выборе блога: ' + data.error, { error: data.error }) : 'Ошибка при выборе блога: ' + data.error);
        }
    })
    .catch(err => {
        console.error('Ошибка выбора блога:', err);
    });
}

// Настройка интерактивного переключения отображения деталей RSS настроек
document.addEventListener('DOMContentLoaded', () => {
    const rssFeedEnabledCheck = document.getElementById('rssFeedEnabled');
    if (rssFeedEnabledCheck) {
        rssFeedEnabledCheck.addEventListener('change', function() {
            const details = document.getElementById('rssFeedSettingsDetails');
            if (details) {
                details.style.display = this.checked ? 'block' : 'none';
            }
        });
    }
});

function saveRssFeedSettings() {
    const rssEnabled = document.getElementById('rssFeedEnabled').checked;
    const rssBaseUrl = document.getElementById('rssFeedBaseUrl').value.trim();
    const rssTitle = document.getElementById('rssFeedTitle').value.trim();
    const rssDescription = document.getElementById('rssFeedDescription').value.trim();
    const rssUseFirstLine = document.getElementById('rssFeedUseFirstLine').checked;
    const rssContentTemplate = document.getElementById('rssFeedContentTemplate').value;

    fetch('save_editor_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            rss_enabled: rssEnabled,
            rss_base_url: rssBaseUrl,
            rss_title: rssTitle,
            rss_description: rssDescription,
            rss_use_first_line: rssUseFirstLine,
            rss_content_template: rssContentTemplate
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(window.t ? window.t('notifications.rss_feed_saved', 'Настройки RSS ленты успешно сохранены!') : 'Настройки RSS ленты успешно сохранены!');
            loadAndApplyAllSettings();
        } else {
            showAlert(window.t ? window.t('notifications.rss_feed_save_error_param', 'Ошибка при сохранении настроек: ' + data.error, { error: data.error }) : 'Ошибка при сохранении настроек: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert(window.t ? window.t('notifications.rss_feed_save_failed', 'Ошибка при сохранении настроек RSS ленты') : 'Ошибка при сохранении настроек RSS ленты');
    });
}

function showSessionExpiredModal() {
    if (window.Modal) {
        Modal.open('#sessionExpiredOverlay');
    } else {
        const modal = document.getElementById('sessionExpiredOverlay');
        if (modal) modal.style.display = 'flex';
    }
    const passwordInput = document.getElementById('sessionExpiredPassword');
    if (passwordInput) {
        passwordInput.value = '';
        setTimeout(() => passwordInput.focus(), 50);
    }
    const errDiv = document.getElementById('sessionExpiredError');
    if (errDiv) errDiv.style.display = 'none';
}

function submitSessionReauth() {
    const passwordInput = document.getElementById('sessionExpiredPassword');
    const errDiv = document.getElementById('sessionExpiredError');
    if (!passwordInput || !errDiv) return;
    
    const password = passwordInput.value;
    if (!password) {
        errDiv.textContent = 'Введите пароль!';
        errDiv.style.display = 'block';
        return;
    }
    
    errDiv.style.display = 'none';
    
    fetch('login.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ password: password })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.csrf_token) {
            // Update CSRF token in meta tag
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) {
                csrfMeta.setAttribute('content', data.csrf_token);
            }
            
            // Hide modal
            if (window.Modal) {
                Modal.close('#sessionExpiredOverlay');
            } else {
                const modal = document.getElementById('sessionExpiredOverlay');
                if (modal) modal.style.display = 'none';
            }
            
            showNotification(window.t ? window.t('notifications.session_restored', 'Сессия успешно восстановлена! Теперь вы можете сохранить вашу работу.') : 'Сессия успешно восстановлена! Теперь вы можете сохранить вашу работу.', 'success');
        } else {
            errDiv.textContent = data.message || 'Неверный пароль';
            errDiv.style.display = 'block';
            passwordInput.focus();
        }
    })
    .catch(error => {
        console.error('Ошибка реавторизации:', error);
        errDiv.textContent = 'Сетевая ошибка при проверке пароля';
        errDiv.style.display = 'block';
    });
}

function cancelSessionReauth() {
    window.location.reload();
}

let currentSelectedTheme = localStorage.getItem('theme') || 'dark';

function openThemeManager() {
    currentSelectedTheme = localStorage.getItem('theme') || 'dark';
    updateThemeSelectionUI(currentSelectedTheme);
    
    fetch('get_editor_settings.php?t=' + Date.now())
        .then(res => res.json())
        .then(data => {
            const settings = data.settings || {};
            if (settings.customThemeCss) {
                const textarea = document.getElementById('customCssEditor');
                if (textarea) textarea.value = settings.customThemeCss;
            }
        }).catch(() => {});
        
    if (window.Modal) {
        Modal.open('#themeManagerModal');
    } else {
        const modal = document.getElementById('themeManagerModal');
        if (modal) modal.style.display = 'block';
    }
}

function closeThemeManager() {
    if (window.Modal) {
        Modal.close('#themeManagerModal');
    } else {
        const modal = document.getElementById('themeManagerModal');
        if (modal) modal.style.display = 'none';
    }
}

function selectThemeOption(themeName) {
    currentSelectedTheme = themeName;
    updateThemeSelectionUI(themeName);
}

function updateThemeSelectionUI(themeName) {
    const darkCard = document.getElementById('themeCardDark');
    const lightCard = document.getElementById('themeCardLight');
    const customCard = document.getElementById('themeCardCustom');
    
    const activeStyle = '2px solid var(--primary-color, #4CAF50)';
    const defaultStyle = '2px solid var(--border-color)';
    
    if (darkCard) darkCard.style.border = (themeName === 'dark') ? activeStyle : defaultStyle;
    if (lightCard) lightCard.style.border = (themeName === 'light') ? activeStyle : defaultStyle;
    if (customCard) customCard.style.border = (themeName === 'custom') ? activeStyle : defaultStyle;
    
    const cssContainer = document.getElementById('customCssContainer');
    if (cssContainer) {
        cssContainer.style.display = (themeName === 'custom') ? 'block' : 'none';
    }
}

function saveSelectedTheme() {
    applyTheme(currentSelectedTheme);
    
    fetch('save_editor_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ activeTheme: currentSelectedTheme })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification(window.t ? window.t('notifications.theme_saved', 'Тема успешно применена и сохранена!') : 'Тема успешно применена и сохранена!', 'success');
            closeThemeManager();
        } else {
            showAlert(window.t ? window.t('notifications.theme_save_error', 'Ошибка сохранения настройки темы') : 'Ошибка сохранения настройки темы');
        }
    })
    .catch(err => {
        console.error(err);
        showNotification(window.t ? window.t('notifications.theme_applied_local', 'Тема применена локально') : 'Тема применена локально', 'info');
        closeThemeManager();
    });
}

function applyTheme(themeName) {
    const docEl = document.documentElement;
    const customLink = document.getElementById('customThemeStyleLink');
    
    if (themeName === 'dark') {
        docEl.setAttribute('data-theme', 'dark');
        localStorage.setItem('theme', 'dark');
        if (customLink) customLink.disabled = true;
    } else if (themeName === 'light') {
        docEl.removeAttribute('data-theme');
        localStorage.setItem('theme', 'light');
        if (customLink) customLink.disabled = true;
    } else if (themeName === 'custom') {
        docEl.setAttribute('data-theme', 'custom');
        localStorage.setItem('theme', 'custom');
        if (customLink) customLink.disabled = false;
    }
    
    if (typeof updateAmoledState === 'function') {
        updateAmoledState();
    }
}

function handleCustomThemeFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    if (!file.name.toLowerCase().endsWith('.css')) {
        showAlert(window.t ? window.t('notifications.theme_select_css_file', 'Пожалуйста, выберите файл с расширением .css') : 'Пожалуйста, выберите файл с расширением .css');
        return;
    }
    
    const formData = new FormData();
    formData.append('themeFile', file);
    
    fetch('upload_custom_theme.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || (window.t ? window.t('notifications.theme_custom_loaded', 'Кастомная тема загружена!') : 'Кастомная тема загружена!'), 'success');
            
            const textarea = document.getElementById('customCssEditor');
            if (textarea && data.cssContent) {
                textarea.value = data.cssContent;
            }
            
            let customLink = document.getElementById('customThemeStyleLink');
            if (!customLink) {
                customLink = document.createElement('link');
                customLink.id = 'customThemeStyleLink';
                customLink.rel = 'stylesheet';
                document.head.appendChild(customLink);
            }
            customLink.href = data.themeUrl;
            customLink.disabled = false;
            
            selectThemeOption('custom');
            applyTheme('custom');
        } else {
            showAlert(window.t ? window.t('notifications.theme_load_error_param', 'Ошибка загрузки темы: ' + (data.error || (window.t ? window.t('notifications.unknown_error', 'Неизвестная ошибка') : 'Неизвестная ошибка')), { error: data.error || (window.t ? window.t('notifications.unknown_error', 'Неизвестная ошибка') : 'Неизвестная ошибка') }) : 'Ошибка загрузки темы: ' + (data.error || 'Неизвестная ошибка'));
        }
    })
    .catch(err => {
        console.error('Ошибка при загрузке темы:', err);
        showAlert(window.t ? window.t('notifications.theme_network_error', 'Сетевая ошибка при загрузке темы') : 'Сетевая ошибка при загрузке темы');
    });
}

function saveCustomCssCode() {
    const textarea = document.getElementById('customCssEditor');
    if (!textarea) return;
    
    const cssContent = textarea.value;
    
    fetch('save_editor_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            activeTheme: 'custom',
            customThemeCss: cssContent
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            let customLink = document.getElementById('customThemeStyleLink');
            if (!customLink) {
                customLink = document.createElement('link');
                customLink.id = 'customThemeStyleLink';
                customLink.rel = 'stylesheet';
                document.head.appendChild(customLink);
            }
            customLink.href = 'data/custom_editor_theme.css?v=' + Date.now();
            customLink.disabled = false;
            
            selectThemeOption('custom');
            applyTheme('custom');
            showNotification(window.t ? window.t('notifications.theme_custom_css_saved', 'Кастомный CSS код применен и сохранен!') : 'Кастомный CSS код применен и сохранен!', 'success');
        } else {
            showAlert(window.t ? window.t('notifications.theme_css_save_error', 'Ошибка при сохранении CSS кода') : 'Ошибка при сохранении CSS кода');
        }
    })
    .catch(err => {
        console.error(err);
        showAlert(window.t ? window.t('notifications.theme_css_network_error', 'Ошибка сети при сохранении CSS кода') : 'Ошибка сети при сохранении CSS кода');
    });
}

function lockEditor() {
    fetch('logout.php', { method: 'POST' })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            showAlert(window.t ? window.t('notifications.lock_editor_failed', 'Не удалось заблокировать редактор') : 'Не удалось заблокировать редактор');
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        window.location.reload();
    });
}

// Функции для работы с пользовательскими шрифтами
function openCustomFontsModal() {
    if (window.Modal) {
        Modal.open('#customFontsModal');
    } else {
        const modal = document.getElementById('customFontsModal');
        if (modal) {
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        }
    }
    
    loadCustomFonts();
}

function closeCustomFontsModal() {
    if (window.Modal) {
        Modal.close('#customFontsModal');
    } else {
        const modal = document.getElementById('customFontsModal');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        }
    }
}

function uploadFontFile() {
    const input = document.getElementById('fontUploadInput');
    const file = input.files[0];
    if (!file) return;
    
    const formData = new FormData();
    formData.append('fontFile', file);
    
    fetch('upload_font.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(window.t ? window.t('notifications.font_uploaded', 'Шрифт успешно загружен') : 'Шрифт успешно загружен', 'success');
            loadCustomFonts(); // Refresh list
            
            // Reload custom fonts globally
            const styleElement = document.getElementById('customFontsStyle');
            if (styleElement) {
                fetch('get_custom_fonts_css.php?t=' + Date.now())
                    .then(r => r.text())
                    .then(css => {
                        styleElement.textContent = css;
                    });
            }
        } else {
            showNotification(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error, 'error');
        }
        input.value = ''; // Reset file input
    })
    .catch(error => {
        console.error('Ошибка загрузки шрифта:', error);
        showNotification(window.t ? window.t('notifications.font_upload_error', 'Ошибка при загрузке шрифта') : 'Ошибка при загрузке шрифта', 'error');
        input.value = '';
    });
}

function loadCustomFonts() {
    fetch('get_custom_fonts.php')
        .then(response => response.json())
        .then(data => {
            const fontsList = document.getElementById('customFontsList');
            const fontsEmpty = document.getElementById('customFontsEmpty');
            if (!fontsList || !fontsEmpty) return;
            
            if (data.success && data.fonts && data.fonts.length > 0) {
                fontsList.innerHTML = '';
                fontsEmpty.style.display = 'none';
                fontsList.style.display = 'grid';
                
                // Создаём @font-face правила для каждого шрифта
                let styleTag = document.getElementById('customFontsStyles');
                if (!styleTag) {
                    styleTag = document.createElement('style');
                    styleTag.id = 'customFontsStyles';
                    document.head.appendChild(styleTag);
                }
                
                let fontFaceRules = '';
                
                data.fonts.forEach(font => {
                    let format = 'truetype';
                    if (font.format === 'woff') format = 'woff';
                    else if (font.format === 'woff2') format = 'woff2';
                    else if (font.format === 'otf') format = 'opentype';
                    
                    fontFaceRules += `
                        @font-face {
                            font-family: '${font.name}';
                            src: url('${font.path}') format('${format}');
                        }
                    `;
                    
                    const fontBtn = document.createElement('button');
                    fontBtn.type = 'button';
                    fontBtn.className = 'font-family-item modal-section-card';
                    fontBtn.style.fontFamily = `'${font.name}', sans-serif`;
                    fontBtn.style.padding = '12px 16px';
                    fontBtn.style.fontSize = '16px';
                    fontBtn.style.cursor = 'pointer';
                    fontBtn.style.textAlign = 'left';
                    fontBtn.style.width = '100%';
                    fontBtn.style.display = 'flex';
                    fontBtn.style.justifyContent = 'space-between';
                    fontBtn.style.alignItems = 'center';
                    fontBtn.style.borderRadius = '8px';
                    fontBtn.style.border = '1px solid var(--border-color)';
                    fontBtn.style.background = 'var(--bg-card, rgba(0,0,0,0.03))';
                    fontBtn.style.color = 'var(--text-color)';
                    
                    const nameSpan = document.createElement('span');
                    nameSpan.textContent = font.name;
                    
                    const tagSpan = document.createElement('span');
                    tagSpan.textContent = (font.format || 'ttf').toUpperCase();
                    tagSpan.style.fontSize = '11px';
                    tagSpan.style.opacity = '0.6';
                    tagSpan.style.fontFamily = 'sans-serif';
                    tagSpan.style.padding = '2px 6px';
                    tagSpan.style.borderRadius = '4px';
                    tagSpan.style.background = 'rgba(0,0,0,0.08)';
                    
                    fontBtn.appendChild(nameSpan);
                    fontBtn.appendChild(tagSpan);
                    fontBtn.onclick = () => applyCustomFont(font.name);
                    
                    fontsList.appendChild(fontBtn);
                });
                
                styleTag.textContent = fontFaceRules;
            } else {
                fontsList.style.display = 'none';
                fontsEmpty.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки шрифтов:', error);
        });
}

function applyCustomFont(fontName) {
    currentSelectedFont = fontName;
    
    // Обновляем текст кнопки
    const fontBtn = document.getElementById('fontFamilyBtn');
    if (fontBtn) {
        fontBtn.textContent = fontName;
        fontBtn.style.fontFamily = fontName;
    }
    
    // Применяем шрифт
    setFontFamily(fontName);
    
    // Закрываем модальное окно
    closeCustomFontsModal();
    
    // Закрываем popover шрифтов
    const wrap = document.getElementById('fontFamilyWrapMain');
    if (wrap) {
        wrap.classList.remove('is-open');
    }
}

// Система обновления NPBlog
let updateToken = '';
let updateRootFolder = '';

function openSystemUpdateModal() {
    if (window.Modal) {
        Modal.open('#systemUpdateModal');
    } else {
        const modal = document.getElementById('systemUpdateModal');
        if (modal) {
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        }
    }
    
    const previewContainer = document.getElementById('updatePreviewContainer');
    const progressContainer = document.getElementById('updateProgressContainer');
    const successContainer = document.getElementById('updateSuccessContainer');
    const startBtn = document.getElementById('startUpdateProcessBtn');
    const updateBtn = document.getElementById('systemUpdateBtn');
    const updateInput = document.getElementById('systemUpdateInput');
    
    if (previewContainer) previewContainer.style.display = 'none';
    if (progressContainer) progressContainer.style.display = 'none';
    if (successContainer) successContainer.style.display = 'none';
    if (startBtn) startBtn.style.display = 'none';
    if (updateBtn) updateBtn.style.display = 'flex';
    if (updateInput) updateInput.value = '';
    
    // Fetch current version via PHP endpoint
    fetch('update_system.php?action=get_version&t=' + Date.now())
        .then(response => {
            if (!response.ok) throw new Error('version not found');
            return response.json();
        })
        .then(data => {
            const versionEl = document.getElementById('currentSysVersion');
            if (!versionEl) return;
            if (data && data.success) {
                if (data.dev === true || data.dev === 'true') {
                    versionEl.textContent = 'dev';
                } else if (data.version) {
                    versionEl.textContent = data.version;
                } else {
                    versionEl.textContent = 'Неизвестно';
                }
            } else {
                versionEl.textContent = 'Неизвестно';
            }
        })
        .catch(() => {
            const versionEl = document.getElementById('currentSysVersion');
            if (versionEl) versionEl.textContent = 'Не найдена (вероятно < 2.174)';
        });
    
    // Закрываем меню, если оно открыто
    const menuWrap = document.getElementById('editorMenuWrap');
    if (menuWrap && menuWrap.classList.contains('is-open')) {
        menuWrap.classList.remove('is-open');
    }
}

function closeSystemUpdateModal() {
    if (window.Modal) {
        Modal.close('#systemUpdateModal');
    } else {
        const modal = document.getElementById('systemUpdateModal');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        }
    }
}

function handleSystemUpdatePreview() {
    const input = document.getElementById('systemUpdateInput');
    const file = input.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('updateFile', file);

    document.getElementById('systemUpdateBtn').style.display = 'none';
    document.getElementById('updateProgressContainer').style.display = 'flex';
    document.getElementById('updateStatusText').textContent = 'Анализ архива...';
    document.getElementById('updateProgressBar').style.width = '30%';

    fetch('update_system.php?action=preview', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('updateProgressContainer').style.display = 'none';
        
            if (data.success) {
            updateToken = data.token;
            updateRootFolder = data.rootFolder;
            
            document.getElementById('currentSysVersion').textContent = data.currentVersion || 'Неизвестно';
            document.getElementById('newSysVersion').textContent = data.newVersion || 'Неизвестно';
            
            const listContainer = document.getElementById('updateFileList');
            listContainer.innerHTML = '';
            
            if (data.files.length === 0) {
                listContainer.innerHTML = '<p style="color: #d32f2f;">В архиве не найдено подходящих файлов для обновления.</p>';
                document.getElementById('startUpdateProcessBtn').style.display = 'none';
            } else {
                data.files.forEach(f => {
                    const el = document.createElement('div');
                    el.textContent = f;
                    listContainer.appendChild(el);
                });
                document.getElementById('startUpdateProcessBtn').style.display = 'block';
            }
            
            document.getElementById('updatePreviewContainer').style.display = 'flex';
        } else {
            showNotification(window.t ? window.t('notifications.error_with_param', 'Ошибка: ' + data.error, { error: data.error }) : 'Ошибка: ' + data.error, 'error');
            document.getElementById('systemUpdateBtn').style.display = 'block';
            input.value = '';
        }
    })
    .catch(error => {
        console.error('Update preview error:', error);
        showNotification(window.t ? window.t('notifications.update_analyze_error', 'Ошибка при анализе архива') : 'Ошибка при анализе архива', 'error');
        document.getElementById('updateProgressContainer').style.display = 'none';
        document.getElementById('systemUpdateBtn').style.display = 'block';
        input.value = '';
    });
}

function startSystemUpdateProcess() {
    document.getElementById('updatePreviewContainer').style.display = 'none';
    document.getElementById('updateProgressContainer').style.display = 'flex';
    document.getElementById('updateStatusText').textContent = 'Создание бекапа проекта и обновление файлов... (не закрывайте вкладку)';
    document.getElementById('updateProgressBar').style.width = '70%';

    fetch('update_system.php?action=update', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ token: updateToken, rootFolder: updateRootFolder })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('updateProgressBar').style.width = '100%';
            document.getElementById('updateProgressContainer').style.display = 'none';
            document.getElementById('updateSuccessContainer').style.display = 'flex';
        } else {
            document.getElementById('updateProgressContainer').style.display = 'none';
            document.getElementById('systemUpdateBtn').style.display = 'block';
            showNotification(window.t ? window.t('notifications.update_error_param', 'Ошибка обновления: ' + data.error, { error: data.error }) : 'Ошибка обновления: ' + data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Update process error:', error);
        document.getElementById('updateProgressContainer').style.display = 'none';
        document.getElementById('systemUpdateBtn').style.display = 'block';
        showNotification(window.t ? window.t('notifications.update_critical_error', 'Критическая ошибка при обновлении') : 'Критическая ошибка при обновлении', 'error');
    });
}
</script>

<!-- Модальное окно Редактора изображений -->
<div id="imageEditorModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); z-index: 10005; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: var(--bg-color); border-radius: 16px; max-width: 95vw; width: 1200px; height: 90vh; box-shadow: 0 10px 40px rgba(0,0,0,0.5); overflow: hidden; display: flex; flex-direction: column; border: 1px solid var(--border-color);">
        <!-- Заголовок -->
        <div style="padding: 15px 25px; border-bottom: 2px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.03);">
            <div style="display: flex; flex-direction: column; gap: 2px;">
                <h3 style="margin: 0; color: var(--text-color); font-size: 18px; display: flex; align-items: center; gap: 10px;">
                    <span>🎨</span> <span data-i18n="modals.img_editor_title">Редактор изображений</span>
                </h3>
                <p class="modal-subtitle" style="margin: 0; font-size: 12px; opacity: 0.7; color: var(--text-color);" data-i18n="modals.img_editor_subtitle">Рисование, аннотации, стрелки и обрезка</p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" id="imgEditorUndoBtn" onclick="undoImgEditorState()" class="global-action-btn" style="background: transparent; border: 1px solid var(--border-color); color: var(--text-color); padding: 6px 14px; font-size: 14px; display: flex; align-items: center; gap: 8px;" title="Отменить последнее действие (Ctrl+Z)" data-i18n-title="modals.img_editor_undo_title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block;">
                        <path d="M3 7v6h6" />
                        <path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13" />
                    </svg>
                    <span data-i18n="modals.ascii_undo">Отменить</span>
                </button>
                <button type="button" onclick="saveImgEditorChanges()" class="global-action-btn global-action-btn-primary" style="padding: 6px 18px; font-size: 14px; background: var(--accent-color); color: #fff; border: none; font-weight: bold; border-radius: 6px; display: flex; align-items: center; gap: 6px;">
                    <span>💾</span> <span data-i18n="common.save">Сохранить</span>
                </button>
                <button type="button" onclick="closeImgEditorModal()" style="background: transparent; border: none; font-size: 32px; color: var(--text-color); cursor: pointer; line-height: 1; padding: 0 5px; margin-left: 10px;" title="Закрыть" data-i18n-title="common.close">×</button>
            </div>
        </div>
        
        <!-- Основная область -->
        <div style="flex: 1; display: flex; overflow: hidden; background: rgba(0,0,0,0.05);">
            <!-- Левая панель инструментов -->
            <div style="width: 260px; border-right: 2px solid var(--border-color); background: var(--bg-color); display: flex; flex-direction: column; gap: 20px; padding: 25px; overflow-y: auto;">
                
                <!-- Инструменты -->
                <div>
                    <h4 style="margin: 0 0 12px 0; color: var(--text-color); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;" data-i18n="modals.img_editor_tools">Инструменты</h4>
                    <div style="display: flex; flex-direction: column; gap: 8px;" id="imgEditorToolsContainer">
                        <button type="button" class="img-editor-tool-btn active" data-tool="pencil" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); text-align: left; cursor: pointer; font-weight: 500; width: 100%;">
                            <span style="font-size: 16px;">✏️</span> <span data-i18n="modals.img_editor_tool_pencil">Карандаш</span>
                        </button>
                        <button type="button" class="img-editor-tool-btn" data-tool="line" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); text-align: left; cursor: pointer; font-weight: 500; width: 100%;">
                            <span style="font-size: 16px;">📏</span> <span data-i18n="modals.img_editor_tool_line">Прямая линия</span>
                        </button>
                        <button type="button" class="img-editor-tool-btn" data-tool="arrow" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); text-align: left; cursor: pointer; font-weight: 500; width: 100%;">
                            <span style="font-size: 16px;">↗️</span> <span data-i18n="modals.img_editor_tool_arrow">Стрелка</span>
                        </button>
                        <button type="button" class="img-editor-tool-btn" data-tool="pixelate" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); text-align: left; cursor: pointer; font-weight: 500; width: 100%;">
                            <span style="font-size: 16px;">⬛</span> <span data-i18n="modals.img_editor_tool_pixelate">Пикселизация</span>
                        </button>
                        <button type="button" class="img-editor-tool-btn" data-tool="text" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); text-align: left; cursor: pointer; font-weight: 500; width: 100%;">
                            <span style="font-size: 16px;">🔤</span> <span data-i18n="modals.img_editor_tool_text">Текст</span>
                        </button>
                    </div>
                </div>
                
                <!-- Цвет -->
                <div id="imgEditorColorSection">
                    <h4 style="margin: 0 0 12px 0; color: var(--text-color); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;" data-i18n="modals.img_editor_color">Цвет</h4>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <input type="color" id="imgEditorColorPicker" value="#ff0000" style="width: 100%; height: 40px; border: 1px solid var(--border-color); border-radius: 8px; padding: 2px; cursor: pointer; background: transparent;">
                        <!-- Пресеты -->
                        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px;">
                            <div class="color-preset active" data-color="#ff0000" style="background: #ff0000;"></div>
                            <div class="color-preset" data-color="#00ff00" style="background: #00ff00;"></div>
                            <div class="color-preset" data-color="#0000ff" style="background: #0000ff;"></div>
                            <div class="color-preset" data-color="#ffff00" style="background: #ffff00;"></div>
                            <div class="color-preset" data-color="#00ffff" style="background: #00ffff;"></div>
                            <div class="color-preset" data-color="#ff00ff" style="background: #ff00ff;"></div>
                            <div class="color-preset" data-color="#ffffff" style="background: #ffffff; border: 1px solid #ddd;"></div>
                            <div class="color-preset" data-color="#000000" style="background: #000000;"></div>
                            <div class="color-preset" data-color="#ff9800" style="background: #ff9800;"></div>
                            <div class="color-preset" data-color="#9c27b0" style="background: #9c27b0;"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Толщина -->
                <div id="imgEditorSizeSection">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <h4 style="margin: 0; color: var(--text-color); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;" id="imgEditorSizeLabel">Толщина кисти</h4>
                        <span id="imgEditorSizeValue" style="color: var(--text-color); font-weight: bold; font-size: 12px;">5 px</span>
                    </div>
                    <input type="range" id="imgEditorSizeSlider" min="1" max="50" value="5" style="width: 100%; cursor: pointer;">
                </div>

                <!-- Размер шрифта -->
                <div id="imgEditorFontSizeSection" style="display: none;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <h4 style="margin: 0; color: var(--text-color); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;">Размер текста</h4>
                        <span id="imgEditorFontSizeValue" style="color: var(--text-color); font-weight: bold; font-size: 12px;">30 px</span>
                    </div>
                    <input type="range" id="imgEditorFontSizeSlider" min="10" max="100" value="30" style="width: 100%; cursor: pointer;">
                </div>
                
                <div style="margin-top: auto; padding: 12px; border-radius: 8px; background: rgba(0,0,0,0.03); font-size: 12px; color: var(--text-color); opacity: 0.8; border: 1px solid var(--border-color);">
                    <strong>💡 Подсказка:</strong><br>
                    <span id="imgEditorHelpText">Рисуйте мышкой на изображении зажав левую кнопку.</span>
                </div>
            </div>
            
            <!-- Центральная область холста -->
            <div style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 30px; overflow: auto; position: relative;" id="imgEditorCanvasContainer">
                <canvas id="imgEditorCanvas" style="box-shadow: 0 4px 30px rgba(0,0,0,0.15); max-width: 100%; max-height: 100%; object-fit: contain; cursor: crosshair; background-image: radial-gradient(var(--border-color) 15%, transparent 16%), radial-gradient(var(--border-color) 15%, transparent 16%); background-size: 16px 16px; background-position: 0 0, 8px 8px; background-color: var(--bg-color); border: 1px dashed var(--border-color);"></canvas>
            </div>
        </div>
    </div>
</div>

    <!-- Модальное окно Редактора ASCII-арта -->
    <?php require_once __DIR__ . '/modals_editor/ascii_drawer_modal.php'; ?>



<script>
let imgEditorTargetImg = null;
let imgEditorCanvas = null;
let imgEditorCtx = null;
let imgEditorHistory = [];
let imgEditorIsDrawing = false;
let imgEditorCurrentTool = 'pencil';
let imgEditorCurrentColor = '#ff0000';
let imgEditorCurrentSize = 5;
let imgEditorCurrentFontSize = 30;
let imgEditorDragBaseState = null;
let imgEditorStartX = 0;
let imgEditorStartY = 0;

function openImageEditorModal(imgElement) {
    imgEditorTargetImg = imgElement;
    imgEditorCanvas = document.getElementById('imgEditorCanvas');
    imgEditorCtx = imgEditorCanvas.getContext('2d');
    
    const modal = document.getElementById('imageEditorModal');
    modal.style.display = 'flex';
    modal.classList.add('show');
    
    const tempImg = new Image();
    tempImg.crossOrigin = 'anonymous'; 
    tempImg.onload = function() {
        imgEditorCanvas.width = tempImg.naturalWidth;
        imgEditorCanvas.height = tempImg.naturalHeight;
        
        imgEditorCtx.clearRect(0, 0, imgEditorCanvas.width, imgEditorCanvas.height);
        imgEditorCtx.drawImage(tempImg, 0, 0);
        
        imgEditorHistory = [imgEditorCtx.getImageData(0, 0, imgEditorCanvas.width, imgEditorCanvas.height)];
        updateImgEditorUndoBtnState();
        
        setImgEditorTool('pencil');
    };
    tempImg.src = imgElement.src.replace(/[?&]t=\d+/, '');
}

function closeImgEditorModal() {
    const modal = document.getElementById('imageEditorModal');
    modal.style.display = 'none';
    modal.classList.remove('show');
    imgEditorTargetImg = null;
}

function updateImgEditorUndoBtnState() {
    const undoBtn = document.getElementById('imgEditorUndoBtn');
    if (imgEditorHistory.length > 1) {
        undoBtn.disabled = false;
        undoBtn.style.opacity = '1';
        undoBtn.style.cursor = 'pointer';
    } else {
        undoBtn.disabled = true;
        undoBtn.style.opacity = '0.5';
        undoBtn.style.cursor = 'not-allowed';
    }
}

function saveImgEditorState() {
    if (imgEditorHistory.length >= 30) {
        imgEditorHistory.shift();
    }
    imgEditorHistory.push(imgEditorCtx.getImageData(0, 0, imgEditorCanvas.width, imgEditorCanvas.height));
    updateImgEditorUndoBtnState();
}

function undoImgEditorState() {
    if (imgEditorHistory.length > 1) {
        imgEditorHistory.pop();
        const prevState = imgEditorHistory[imgEditorHistory.length - 1];
        imgEditorCtx.putImageData(prevState, 0, 0);
        updateImgEditorUndoBtnState();
    }
}

function setImgEditorTool(tool) {
    imgEditorCurrentTool = tool;
    
    document.querySelectorAll('.img-editor-tool-btn').forEach(btn => {
        if (btn.getAttribute('data-tool') === tool) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    const sizeLabel = document.getElementById('imgEditorSizeLabel');
    const colorSection = document.getElementById('imgEditorColorSection');
    const sizeSection = document.getElementById('imgEditorSizeSection');
    const fontSizeSection = document.getElementById('imgEditorFontSizeSection');
    const helpText = document.getElementById('imgEditorHelpText');
    
    if (tool === 'text') {
        colorSection.style.display = 'block';
        sizeSection.style.display = 'none';
        fontSizeSection.style.display = 'block';
        helpText.textContent = 'Кликните на изображение, чтобы добавить текст в эту точку.';
    } else if (tool === 'pixelate') {
        colorSection.style.display = 'none';
        sizeSection.style.display = 'block';
        fontSizeSection.style.display = 'none';
        sizeLabel.textContent = 'Размер кисти размытия';
        helpText.textContent = 'Зажмите кнопку мыши и водите по областям, которые хотите размыть пикселями.';
    } else {
        colorSection.style.display = 'block';
        sizeSection.style.display = 'block';
        fontSizeSection.style.display = 'none';
        sizeLabel.textContent = 'Толщина кисти';
        helpText.textContent = 'Зажмите кнопку мыши и рисуйте на изображении.';
    }
}

function getImgEditorCoordinates(e) {
    const rect = imgEditorCanvas.getBoundingClientRect();
    const scaleX = imgEditorCanvas.width / rect.width;
    const scaleY = imgEditorCanvas.height / rect.height;
    
    let clientX = e.clientX;
    let clientY = e.clientY;
    
    if (e.touches && e.touches.length > 0) {
        clientX = e.touches[0].clientX;
        clientY = e.touches[0].clientY;
    }
    
    return {
        x: (clientX - rect.left) * scaleX,
        y: (clientY - rect.top) * scaleY
    };
}

function startImgEditorDrawing(e) {
    imgEditorIsDrawing = true;
    const coords = getImgEditorCoordinates(e);
    imgEditorStartX = coords.x;
    imgEditorStartY = coords.y;
    
    if (imgEditorCurrentTool === 'pencil') {
        imgEditorCtx.beginPath();
        imgEditorCtx.moveTo(imgEditorStartX, imgEditorStartY);
    } else if (imgEditorCurrentTool === 'line' || imgEditorCurrentTool === 'arrow') {
        imgEditorDragBaseState = imgEditorCtx.getImageData(0, 0, imgEditorCanvas.width, imgEditorCanvas.height);
    } else if (imgEditorCurrentTool === 'pixelate') {
        pixelateRegionAt(imgEditorStartX, imgEditorStartY);
    } else if (imgEditorCurrentTool === 'text') {
        imgEditorIsDrawing = false;
        addTextAt(imgEditorStartX, imgEditorStartY);
    }
}

function drawImgEditor(e) {
    if (!imgEditorIsDrawing) return;
    const coords = getImgEditorCoordinates(e);
    const currX = coords.x;
    const currY = coords.y;
    
    if (imgEditorCurrentTool === 'pencil') {
        imgEditorCtx.lineTo(currX, currY);
        imgEditorCtx.strokeStyle = imgEditorCurrentColor;
        imgEditorCtx.lineWidth = imgEditorCurrentSize;
        imgEditorCtx.lineCap = 'round';
        imgEditorCtx.lineJoin = 'round';
        imgEditorCtx.stroke();
    } else if (imgEditorCurrentTool === 'line') {
        imgEditorCtx.putImageData(imgEditorDragBaseState, 0, 0);
        imgEditorCtx.beginPath();
        imgEditorCtx.moveTo(imgEditorStartX, imgEditorStartY);
        imgEditorCtx.lineTo(currX, currY);
        imgEditorCtx.strokeStyle = imgEditorCurrentColor;
        imgEditorCtx.lineWidth = imgEditorCurrentSize;
        imgEditorCtx.lineCap = 'round';
        imgEditorCtx.stroke();
    } else if (imgEditorCurrentTool === 'arrow') {
        imgEditorCtx.putImageData(imgEditorDragBaseState, 0, 0);
        
        imgEditorCtx.beginPath();
        imgEditorCtx.moveTo(imgEditorStartX, imgEditorStartY);
        imgEditorCtx.lineTo(currX, currY);
        imgEditorCtx.strokeStyle = imgEditorCurrentColor;
        imgEditorCtx.lineWidth = imgEditorCurrentSize;
        imgEditorCtx.lineCap = 'round';
        imgEditorCtx.stroke();
        
        drawArrowheadHead(imgEditorStartX, imgEditorStartY, currX, currY);
    } else if (imgEditorCurrentTool === 'pixelate') {
        pixelateRegionAt(currX, currY);
    }
}

function stopImgEditorDrawing() {
    if (imgEditorIsDrawing) {
        imgEditorIsDrawing = false;
        saveImgEditorState();
    }
}

function drawArrowheadHead(fromX, fromY, toX, toY) {
    const headLength = imgEditorCurrentSize * 3 + 12;
    const angle = Math.atan2(toY - fromY, toX - fromX);
    
    imgEditorCtx.beginPath();
    imgEditorCtx.moveTo(toX, toY);
    imgEditorCtx.lineTo(toX - headLength * Math.cos(angle - Math.PI / 6), toY - headLength * Math.sin(angle - Math.PI / 6));
    imgEditorCtx.moveTo(toX, toY);
    imgEditorCtx.lineTo(toX - headLength * Math.cos(angle + Math.PI / 6), toY - headLength * Math.sin(angle + Math.PI / 6));
    
    imgEditorCtx.strokeStyle = imgEditorCurrentColor;
    imgEditorCtx.lineWidth = imgEditorCurrentSize;
    imgEditorCtx.lineCap = 'round';
    imgEditorCtx.lineJoin = 'round';
    imgEditorCtx.stroke();
}

function pixelateRegionAt(x, y) {
    const radius = imgEditorCurrentSize * 2 + 10;
    const pixelSize = Math.max(4, Math.round(imgEditorCurrentSize / 1.5) + 6);
    
    const startX = Math.max(0, Math.round(x - radius));
    const startY = Math.max(0, Math.round(y - radius));
    const width = Math.min(imgEditorCanvas.width - startX, radius * 2);
    const height = Math.min(imgEditorCanvas.height - startY, radius * 2);
    
    if (width <= 0 || height <= 0) return;
    
    const imgData = imgEditorCtx.getImageData(startX, startY, width, height);
    const data = imgData.data;
    
    for (let i = 0; i < height; i += pixelSize) {
        for (let j = 0; j < width; j += pixelSize) {
            let r = 0, g = 0, b = 0, a = 0, count = 0;
            
            for (let dy = 0; dy < pixelSize && (i + dy) < height; dy++) {
                for (let dx = 0; dx < pixelSize && (j + dx) < width; dx++) {
                    const idx = ((i + dy) * width + (j + dx)) * 4;
                    r += data[idx];
                    g += data[idx + 1];
                    b += data[idx + 2];
                    a += data[idx + 3];
                    count++;
                }
            }
            
            r = Math.round(r / count);
            g = Math.round(g / count);
            b = Math.round(b / count);
            a = Math.round(a / count);
            
            for (let dy = 0; dy < pixelSize && (i + dy) < height; dy++) {
                for (let dx = 0; dx < pixelSize && (j + dx) < width; dx++) {
                    const idx = ((i + dy) * width + (j + dx)) * 4;
                    data[idx] = r;
                    data[idx + 1] = g;
                    data[idx + 2] = b;
                    data[idx + 3] = a;
                }
            }
        }
    }
    
    imgEditorCtx.putImageData(imgData, startX, startY);
}

async function addTextAt(x, y) {
    const text = await showPrompt("Введите текст для нанесения на изображение:", "", "Текст на изображении");
    if (!text || text.trim() === '') return;
    
    imgEditorCtx.font = `bold ${imgEditorCurrentFontSize}px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif`;
    imgEditorCtx.textBaseline = 'middle';
    
    imgEditorCtx.strokeStyle = imgEditorCurrentColor === '#000000' ? '#ffffff' : '#000000';
    imgEditorCtx.lineWidth = Math.max(3, imgEditorCurrentFontSize / 8);
    imgEditorCtx.strokeText(text, x, y);
    
    imgEditorCtx.fillStyle = imgEditorCurrentColor;
    imgEditorCtx.fillText(text, x, y);
    
    saveImgEditorState();
}

function saveImgEditorChanges() {
    if (!imgEditorTargetImg) return;
    
    const saveBtn = document.querySelector('[onclick="saveImgEditorChanges()"]');
    const oldText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span>⏳</span> Сохранение...';
    
    const dataUrl = imgEditorCanvas.toDataURL('image/png');
    
    const formData = new URLSearchParams();
    formData.append('image_data', dataUrl);
    
    fetch('save_edited_image.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = oldText;
        
        if (data.success) {
            imgEditorTargetImg.setAttribute('src', data.url);
            const separator = data.url.includes('?') ? '&' : '?';
            imgEditorTargetImg.src = data.url + separator + 't=' + Date.now();
            
            showNotification(window.t ? window.t('notifications.image_saved', 'Изображение успешно сохранено!') : 'Изображение успешно сохранено!', 'success');
            closeImgEditorModal();
            
            if (typeof triggerAutosave === 'function') {
                triggerAutosave();
            }
        } else {
            showNotification(window.t ? window.t('notifications.image_save_error_param', 'Ошибка сохранения: ' + data.error, { error: data.error }) : 'Ошибка сохранения: ' + data.error, 'error');
        }
    })
    .catch(error => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = oldText;
        console.error('Save edited image error:', error);
        showNotification(window.t ? window.t('notifications.image_save_critical_error', 'Критическая ошибка сохранения изображения') : 'Критическая ошибка сохранения изображения', 'error');
    });
}

// Инициализация обработчиков холста редактора и применение настроек
document.addEventListener('DOMContentLoaded', function() {
    loadAndApplyAllSettings();
    
    const canvas = document.getElementById('imgEditorCanvas');
    if (!canvas) return;
    
    canvas.addEventListener('mousedown', startImgEditorDrawing);
    canvas.addEventListener('mousemove', drawImgEditor);
    window.addEventListener('mouseup', stopImgEditorDrawing);
    
    canvas.addEventListener('touchstart', function(e) {
        e.preventDefault();
        startImgEditorDrawing(e);
    });
    canvas.addEventListener('touchmove', function(e) {
        e.preventDefault();
        drawImgEditor(e);
    });
    canvas.addEventListener('touchend', function(e) {
        e.preventDefault();
        stopImgEditorDrawing(e);
    });
    
    document.querySelectorAll('.img-editor-tool-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            setImgEditorTool(this.getAttribute('data-tool'));
        });
    });
    
    const colorPicker = document.getElementById('imgEditorColorPicker');
    colorPicker.addEventListener('input', function() {
        imgEditorCurrentColor = this.value;
        document.querySelectorAll('.color-preset').forEach(p => p.classList.remove('active'));
    });
    
    document.querySelectorAll('.color-preset').forEach(preset => {
        preset.addEventListener('click', function() {
            document.querySelectorAll('.color-preset').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            imgEditorCurrentColor = this.getAttribute('data-color');
            colorPicker.value = imgEditorCurrentColor;
        });
    });
    
    const sizeSlider = document.getElementById('imgEditorSizeSlider');
    const sizeValue = document.getElementById('imgEditorSizeValue');
    sizeSlider.addEventListener('input', function() {
        imgEditorCurrentSize = parseInt(this.value);
        sizeValue.textContent = imgEditorCurrentSize + ' px';
    });
    
    const fontSizeSlider = document.getElementById('imgEditorFontSizeSlider');
    const fontSizeValue = document.getElementById('imgEditorFontSizeValue');
    fontSizeSlider.addEventListener('input', function() {
        imgEditorCurrentFontSize = parseInt(this.value);
        fontSizeValue.textContent = imgEditorCurrentFontSize + ' px';
    });
});

    window.addEventListener('keydown', function(e) {
        const modal = document.getElementById('imageEditorModal');
        if (modal && modal.style.display === 'flex') {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
                e.preventDefault();
                undoImgEditorState();
            }
        }
    });

    // Динамический расчет высоты фиксированного хедера и автоматическое применение padding к body
    function adjustHeaderPadding() {
        const header = document.querySelector('.editor-header');
        if (!header) return;
        
        const height = header.offsetHeight;
        const gap = 15; // Небольшой отступ между хедером и рабочей областью
        
        if (document.body.classList.contains('header-bottom')) {
            document.body.style.setProperty('padding-top', '0px', 'important');
            document.body.style.setProperty('padding-bottom', (height + gap) + 'px', 'important');
        } else {
            document.body.style.setProperty('padding-top', (height + gap) + 'px', 'important');
            document.body.style.setProperty('padding-bottom', '0px', 'important');
        }
    }
    
    // Динамическое обновление высоты хедера в зависимости от наличия кнопок во втором ряду
    function updateHeaderHeightState() {
        const row2 = document.getElementById('toolbar-row-2');
        const header = document.querySelector('.editor-header');
        if (!row2 || !header) return;
        
        // В режиме кастомизации всегда показываем оба ряда
        if (document.body.classList.contains('header-customizing')) {
            document.body.classList.add('header-two-rows');
            header.style.height = '108px';
            if (typeof adjustHeaderPadding === 'function') {
                adjustHeaderPadding();
            }
            return;
        }
        
        // Проверяем, есть ли видимые элементы во втором ряду
        const visibleItems = Array.from(row2.children).filter(el => {
            return el.id && !el.classList.contains('customizer-hidden');
        });
        
        if (visibleItems.length > 0) {
            document.body.classList.add('header-two-rows');
            header.style.height = '108px';
        } else {
            document.body.classList.remove('header-two-rows');
            header.style.height = ''; // возвращается к 64px по CSS
        }
        
        if (typeof adjustHeaderPadding === 'function') {
            adjustHeaderPadding();
        }
    }
    
    // Слушатели изменения размера экрана и полной загрузки для обновления отступа
    window.addEventListener('resize', adjustHeaderPadding);
    window.addEventListener('load', adjustHeaderPadding);
    document.addEventListener('DOMContentLoaded', adjustHeaderPadding);
    // Дополнительный запуск с задержкой на случай ленивой подгрузки элементов
    setTimeout(adjustHeaderPadding, 100);
    setTimeout(adjustHeaderPadding, 500);

    // --- КАСТОМИЗАЦИЯ ВЕРХНЕЙ ПАНЕЛИ ---

    // Применяет сохраненный порядок и видимость кнопок хедера
    function applyHeaderLayout(layout) {
        const container = document.querySelector('.header-left');
        if (!container) return;
        
        const row1 = document.getElementById('toolbar-row-1');
        const row2 = document.getElementById('toolbar-row-2');
        const dropdown = document.getElementById('moreMenuDropdown');
        
        const defaultIds = [
            'logoDivider',
            'headerModeToggle',
            'modeActionsDivider',
            'headerEditorActions',
            'actionsFormattingDivider',
            'btn-bold',
            'btn-italic',
            'btn-underline',
            'btn-strike',
            'btn-sup',
            'btn-sub',
            'btn-h2',
            'btn-table',
            'btn-spoiler',
            'btn-marker',
            'btn-anchor',
            'divider-align',
            'btn-align-left',
            'btn-align-center',
            'btn-align-right',
            'divider-media',
            'btn-link',
            'btn-image',
            'btn-media',
            'btn-ascii',
            'divider-fonts',
            'fontSizeWrapMain',
            'fontFamilyWrapMain',
            'colorPickerWrapMain',
            'divider-more',
            'moreMenuWrap'
        ];
        
        if (layout && Array.isArray(layout) && layout.length > 0) {
            layout.forEach(item => {
                let el = document.getElementById(item.id);
                if (!el && item.id && item.id.startsWith('divider-')) {
                    el = document.createElement('span');
                    if (item.id.includes('-spacer-')) {
                        el.className = 'toolbar-spacer';
                        el.style.width = (item.width || 32) + 'px';
                    } else {
                        el.className = 'toolbar-divider';
                    }
                    el.id = item.id;
                } else if (el && item.id.includes('-spacer-') && item.width) {
                    el.style.width = item.width + 'px';
                }
                if (el) {
                    if (item.visible === false) {
                        el.classList.add('customizer-hidden');
                    } else {
                        el.classList.remove('customizer-hidden');
                    }
                    
                    if (item.inDropdown === true && dropdown) {
                        dropdown.appendChild(el);
                    } else {
                        const targetRow = (item.row === 2 && row2) ? row2 : row1;
                        if (targetRow) targetRow.appendChild(el);
                    }
                }
            });
            
            // Failsafe: добавляем элементы, которых нет в сохраненном layout
            defaultIds.forEach(id => {
                const el = document.getElementById(id);
                if (el && !layout.some(item => item.id === id)) {
                    el.classList.remove('customizer-hidden');
                    if (row1) row1.appendChild(el);
                }
            });
        } else {
            // Дефолтное состояние
            defaultIds.forEach(id => {
                const el = document.getElementById(id);
                if (el && row1) {
                    el.classList.remove('customizer-hidden');
                    row1.appendChild(el);
                }
            });
        }
        
        adjustHeaderPadding();
    }

    // Сохранение временного состояния перед входом в режим кастомизации
    let originalHeaderHTML = '';
    let originalDropdownHTML = '';
    
    function startHeaderCustomization() {
        // Закрываем модалку настроек
        closeGlobalSettings();
        
        const container = document.querySelector('.header-left');
        const dropdown = document.getElementById('moreMenuDropdown');
        if (!container) return;
        
        // Сохраняем исходное состояние для отмены
        originalHeaderHTML = container.innerHTML;
        originalDropdownHTML = dropdown ? dropdown.innerHTML : '';
        
        // Входим в режим редактирования
        document.body.classList.add('header-customizing');
        document.getElementById('headerCustomizerBar').style.display = 'flex';
        
        // Расширяем панель до двух строк во время редактирования
        if (typeof updateHeaderHeightState === 'function') {
            updateHeaderHeightState();
        }
        
        const draggableItems = [];
        container.querySelectorAll('.toolbar-row > *:not(.header-logo)').forEach(el => {
            if (el.id) draggableItems.push(el);
        });
        if (dropdown) {
            dropdown.querySelectorAll('*').forEach(el => {
                if (el.id && el.parentNode === dropdown) {
                    draggableItems.push(el);
                }
            });
        }
        
        draggableItems.forEach(item => {
            item.setAttribute('draggable', 'true');
            if (item.classList.contains('toolbar-spacer')) {
                makeSpacerResizable(item);
            }
            
            // Обработчики Drag and Drop
            item.addEventListener('dragstart', handleDragStart);
            item.addEventListener('dragover', handleDragOver);
            item.addEventListener('drop', handleDrop);
            item.addEventListener('dragend', handleDragEnd);
            
            // Обработчик правого клика (ПКМ) для контекстного меню
            item.addEventListener('contextmenu', handleRightClick);
        });
        
        adjustHeaderPadding();
    }
    
    let dragSrcEl = null;
    
    function handleDragStart(e) {
        if (!document.body.classList.contains('header-customizing')) return;
        dragSrcEl = this;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', this.id);
        
        // Откладываем добавление класса, чтобы браузер успел сделать снимок "призрака" для перетаскивания
        setTimeout(() => {
            this.classList.add('dragging');
        }, 0);
    }
    
    function handleDragOver(e) {
        if (!document.body.classList.contains('header-customizing')) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        
        const draggingEl = document.querySelector('.dragging');
        if (!draggingEl || draggingEl === this || this.classList.contains('header-logo')) return;
        
        const container = this.parentNode;
        const children = Array.from(container.children);
        const fromIndex = children.indexOf(draggingEl);
        const toIndex = children.indexOf(this);
        
        if (fromIndex < toIndex) {
            container.insertBefore(draggingEl, this.nextSibling);
        } else {
            container.insertBefore(draggingEl, this);
        }
        
        adjustHeaderPadding();
        return false;
    }
    
    function handleDrop(e) {
        e.stopPropagation();
        e.preventDefault();
        return false;
    }
    
    function handleDragEnd(e) {
        this.classList.remove('dragging');
        const container = document.querySelector('.header-left');
        const dropdown = document.getElementById('moreMenuDropdown');
        if (container) {
            container.querySelectorAll('.toolbar-row > *').forEach(item => {
                item.style.opacity = '';
            });
        }
        if (dropdown) {
            dropdown.querySelectorAll('*').forEach(item => {
                item.style.opacity = '';
            });
        }
        adjustHeaderPadding();
    }
    
    function handleRightClick(e) {
        if (!document.body.classList.contains('header-customizing')) return;
        e.preventDefault();
        e.stopPropagation();
        
        const dropdown = document.getElementById('moreMenuDropdown');
        let target = e.target;
        while (target && target.parentNode && !target.parentNode.classList.contains('toolbar-row') && target.parentNode !== dropdown) {
            target = target.parentNode;
        }
        
        if (!target || !target.id) return;
        
        const menu = document.getElementById('customizerContextMenu');
        if (!menu) return;
        
        // Позиционируем и показываем кастомное меню
        menu.style.left = e.clientX + 'px';
        menu.style.top = e.clientY + 'px';
        menu.style.display = 'block';
        
        // Обновляем пункт Скрыть/Показать / Удалить (для кастомных разделителей)
        const visibilityBtn = document.getElementById('ctxToggleVisibility');
        if (target.id.startsWith('divider-custom-')) {
            visibilityBtn.innerText = 'Удалить';
        } else if (target.classList.contains('customizer-hidden')) {
            visibilityBtn.innerText = 'Показать';
        } else {
            visibilityBtn.innerText = 'Скрыть';
        }
        
        // Обновляем пункт Переместить в меню / Вернуть на панель
        const positionBtn = document.getElementById('ctxTogglePosition');
        if (target.id.startsWith('divider-')) {
            // Разделители нельзя помещать в выпадающее меню
            positionBtn.style.display = 'none';
        } else {
            positionBtn.style.display = 'block';
            if (target.parentNode === dropdown) {
                positionBtn.innerText = 'Вернуть на панель';
            } else {
                positionBtn.innerText = 'Перенести в "Прочее"';
            }
        }
        
        // Обработчики действий контекстного меню
        visibilityBtn.onclick = function() {
            if (target.id.startsWith('divider-custom-')) {
                target.remove();
                showNotification(window.t ? window.t('notifications.tb_divider_removed', 'Разделитель удален') : 'Разделитель удален', 'info');
            } else {
                if (target.classList.contains('customizer-hidden')) {
                    target.classList.remove('customizer-hidden');
                    showNotification(window.t ? window.t('notifications.tb_item_enabled', 'Элемент включен') : 'Элемент включен', 'success');
                } else {
                    target.classList.add('customizer-hidden');
                    showNotification(window.t ? window.t('notifications.tb_item_hidden', 'Элемент скрыт') : 'Элемент скрыт', 'info');
                }
            }
            menu.style.display = 'none';
            adjustHeaderPadding();
        };
        
        positionBtn.onclick = function() {
            if (target.parentNode === dropdown) {
                // Возвращаем на главную панель (в первый ряд по умолчанию)
                const row1 = document.getElementById('toolbar-row-1');
                if (row1) {
                    const dividerMore = document.getElementById('divider-more');
                    if (dividerMore && dividerMore.parentNode === row1) {
                        row1.insertBefore(target, dividerMore);
                    } else {
                        row1.appendChild(target);
                    }
                }
                showNotification(window.t ? window.t('notifications.tb_item_restored', 'Элемент возвращен на панель') : 'Элемент возвращен на панель', 'success');
            } else {
                // Переносим в выпадающее меню
                if (dropdown) {
                    dropdown.appendChild(target);
                    showNotification(window.t ? window.t('notifications.tb_item_moved_more', 'Элемент перенесен в меню "Прочее"') : 'Элемент перенесен в меню "Прочее"', 'info');
                }
            }
            menu.style.display = 'none';
            adjustHeaderPadding();
        };
    }
    
    function makeSpacerResizable(spacer) {
        let handle = spacer.querySelector('.spacer-resize-handle');
        if (!handle) {
            handle = document.createElement('span');
            handle.className = 'spacer-resize-handle';
            spacer.appendChild(handle);
        }
        
        handle.addEventListener('mousedown', (e) => {
            if (!document.body.classList.contains('header-customizing')) return;
            e.preventDefault();
            e.stopPropagation();
            
            // Отключаем draggable на время ресайза, чтобы браузер не начинал drag-and-drop!
            spacer.setAttribute('draggable', 'false');
            
            const startWidth = spacer.offsetWidth;
            const startX = e.clientX;
            
            function onMouseMove(moveEvent) {
                const deltaX = moveEvent.clientX - startX;
                let newWidth = startWidth + deltaX;
                if (newWidth < 16) newWidth = 16;
                if (newWidth > 300) newWidth = 300;
                spacer.style.width = newWidth + 'px';
            }
            
            function onMouseUp() {
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
                spacer.setAttribute('draggable', 'true');
                adjustHeaderPadding();
            }
            
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });
    }

    function toggleDividerDropdown(event) {
        event.stopPropagation();
        const menu = document.getElementById('dividerDropdownMenu');
        if (!menu) return;
        if (menu.style.display === 'block') {
            menu.style.display = 'none';
        } else {
            menu.style.display = 'block';
        }
    }

    function createCustomDivider(type) {
        const row1 = document.getElementById('toolbar-row-1');
        if (!row1) return;
        
        const divider = document.createElement('span');
        if (type === 'spacer') {
            divider.className = 'toolbar-spacer';
            divider.id = 'divider-custom-spacer-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
            divider.style.width = '32px';
        } else {
            divider.className = 'toolbar-divider';
            divider.id = 'divider-custom-line-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
        }
        
        // Свойства перетаскивания для нового разделителя
        divider.setAttribute('draggable', 'true');
        divider.addEventListener('dragstart', handleDragStart);
        divider.addEventListener('dragover', handleDragOver);
        divider.addEventListener('drop', handleDrop);
        divider.addEventListener('dragend', handleDragEnd);
        divider.addEventListener('contextmenu', handleRightClick);
        
        if (type === 'spacer') {
            makeSpacerResizable(divider);
        }
        
        row1.appendChild(divider);
        adjustHeaderPadding();
        
        const label = type === 'spacer' ? 'Пустой разделитель' : 'Разделитель';
        showNotification(window.t ? window.t('notifications.tb_item_added_param', label + ' добавлен. Перетащите его в нужное место.', { label: label }) : label + ' добавлен. Перетащите его в нужное место.', 'success');
        
        const dividerMenu = document.getElementById('dividerDropdownMenu');
        if (dividerMenu) dividerMenu.style.display = 'none';
    }
    
    function exitHeaderCustomizationMode() {
        document.body.classList.remove('header-customizing');
        document.getElementById('headerCustomizerBar').style.display = 'none';
        
        const container = document.querySelector('.header-left');
        const dropdown = document.getElementById('moreMenuDropdown');
        
        const draggableItems = [];
        if (container) {
            container.querySelectorAll('.toolbar-row > *').forEach(item => {
                if (item.id) draggableItems.push(item);
            });
        }
        if (dropdown) {
            dropdown.querySelectorAll('*').forEach(item => {
                if (item.id && item.parentNode === dropdown) {
                    draggableItems.push(item);
                }
            });
        }
        
        draggableItems.forEach(item => {
            if (item && item.removeAttribute) {
                item.removeAttribute('draggable');
                item.removeEventListener('dragstart', handleDragStart);
                item.removeEventListener('dragover', handleDragOver);
                item.removeEventListener('drop', handleDrop);
                item.removeEventListener('dragend', handleDragEnd);
                item.removeEventListener('contextmenu', handleRightClick);
                
                if (item.classList.contains('toolbar-spacer')) {
                    const handle = item.querySelector('.spacer-resize-handle');
                    if (handle) handle.remove();
                }
            }
        });
        
        adjustHeaderPadding();
    }
    
    function cancelHeaderCustomization() {
        exitHeaderCustomizationMode();
        
        const container = document.querySelector('.header-left');
        const dropdown = document.getElementById('moreMenuDropdown');
        if (container && originalHeaderHTML) {
            container.innerHTML = originalHeaderHTML;
        }
        if (dropdown && originalDropdownHTML) {
            dropdown.innerHTML = originalDropdownHTML;
        }
        
        // Загружаем сохраненный макет заново
        loadAndApplyAllSettings();
    }
    
    function saveHeaderCustomization() {
        const layout = [];
        const container = document.querySelector('.header-left');
        const dropdown = document.getElementById('moreMenuDropdown');
        
        if (container) {
            const row1 = document.getElementById('toolbar-row-1');
            if (row1) {
                row1.querySelectorAll('#toolbar-row-1 > *').forEach(el => {
                    if (el.id) {
                        const itemObj = {
                            id: el.id,
                            name: el.title || el.innerText || el.id,
                            visible: !el.classList.contains('customizer-hidden'),
                            inDropdown: false,
                            row: 1
                        };
                        if (el.classList.contains('toolbar-spacer')) {
                            itemObj.width = parseInt(el.style.width) || el.offsetWidth || 32;
                        }
                        layout.push(itemObj);
                    }
                });
            }
            const row2 = document.getElementById('toolbar-row-2');
            if (row2) {
                row2.querySelectorAll('#toolbar-row-2 > *').forEach(el => {
                    if (el.id) {
                        const itemObj = {
                            id: el.id,
                            name: el.title || el.innerText || el.id,
                            visible: !el.classList.contains('customizer-hidden'),
                            inDropdown: false,
                            row: 2
                        };
                        if (el.classList.contains('toolbar-spacer')) {
                            itemObj.width = parseInt(el.style.width) || el.offsetWidth || 32;
                        }
                        layout.push(itemObj);
                    }
                });
            }
        }
        
        if (dropdown) {
            dropdown.querySelectorAll('*').forEach(el => {
                if (el.id && el.parentNode === dropdown) {
                    layout.push({
                        id: el.id,
                        name: el.title || el.innerText || el.id,
                        visible: !el.classList.contains('customizer-hidden'),
                        inDropdown: true
                    });
                }
            });
        }
        
        // Автоматически определяем двухрядность на основе видимых кнопок во втором ряду
        const isTwoRowsActive = layout.some(item => item.row === 2 && item.visible === true);
        const currentHeaderHeight = isTwoRowsActive ? 108 : 64;
        
        fetch('save_editor_settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                headerLayout: layout,
                headerHeight: currentHeaderHeight,
                headerTwoRows: isTwoRowsActive
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showNotification(window.t ? window.t('notifications.tb_layout_saved', 'Раскладка панели успешно сохранена') : 'Раскладка панели успешно сохранена', 'success');
                exitHeaderCustomizationMode();
                if (typeof updateHeaderHeightState === 'function') {
                    updateHeaderHeightState();
                }
            } else {
                showAlert(window.t ? window.t('notifications.image_save_error_param', 'Ошибка сохранения: ' + data.error, { error: data.error }) : 'Ошибка сохранения: ' + data.error);
            }
        })
        .catch(err => {
            console.error('Ошибка сохранения:', err);
            showAlert(window.t ? window.t('notifications.tb_save_network_error', 'Ошибка сети при сохранении раскладки') : 'Ошибка сети при сохранении раскладки');
        });
    }

    // Инициализация Drag and Drop для пустых рядов хедера
    document.addEventListener('DOMContentLoaded', () => {
        const rows = [document.getElementById('toolbar-row-1'), document.getElementById('toolbar-row-2')];
        rows.forEach(row => {
            if (row) {
                row.addEventListener('dragover', (e) => {
                    if (!document.body.classList.contains('header-customizing')) return;
                    e.preventDefault();
                    
                    const draggingEl = document.querySelector('.dragging');
                    if (!draggingEl || draggingEl.contains(row) || row.contains(draggingEl)) return;
                    
                    // Если перетаскиваем на сам контейнер ряда или его псевдоэлемент, переносим в этот ряд
                    const isOverRow = e.target === row || e.target.classList.contains('toolbar-row') || e.target.closest('.toolbar-row') === row;
                    if (isOverRow) {
                        const hoveredItem = e.target.closest('.toolbar-row > *');
                        if (!hoveredItem || hoveredItem === draggingEl) {
                            row.appendChild(draggingEl);
                            adjustHeaderPadding();
                        }
                    }
                });
            }
        });

        // Интерактивное превью выбранного фонового изображения
        const setupBgPreview = (inputId, previewId, wrapperId = null) => {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            if (input && preview) {
                input.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            preview.src = e.target.result;
                            if (wrapperId) {
                                const wrapper = document.getElementById(wrapperId);
                                if (wrapper) wrapper.style.display = 'block';
                            }
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        };
        setupBgPreview('backgroundInput', 'currentBackgroundPreview', 'currentBackgroundInfo');
        setupBgPreview('globalBackgroundInput', 'currentGlobalBackgroundPreview');
        setupBgPreview('blogBackgroundInput', 'currentBlogBackgroundPreview');
    });

    // Закрытие контекстных меню при клике в любое место
    document.addEventListener('click', function() {
        const menu = document.getElementById('customizerContextMenu');
        if (menu) menu.style.display = 'none';
        const dividerMenu = document.getElementById('dividerDropdownMenu');
        if (dividerMenu) dividerMenu.style.display = 'none';
    });
</script>

<!-- Панель управления кастомизацией хедера -->
<div id="headerCustomizerBar" style="display: none; position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background: var(--bg-color); border: 2px solid var(--primary-color, #4CAF50); border-radius: 12px; padding: 16px 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.35); z-index: 10005; align-items: center; gap: 16px; box-sizing: border-box; flex-wrap: wrap; justify-content: center;">
    <span style="color: var(--text-color); font-weight: 600; font-size: 14px; margin-right: 8px;">Режим редактирования панели: перетаскивайте кнопки, ПКМ — меню опций.</span>
    <div style="display: flex; gap: 8px; align-items: center;">
        <div style="position: relative; display: inline-block;">
            <button type="button" onclick="toggleDividerDropdown(event)" class="global-action-btn global-action-btn-accent" style="padding: 8px 16px; border-width: 1px;">+ Разделитель</button>
            <div id="dividerDropdownMenu" class="customizer-dropdown-menu" style="display: none; position: absolute; bottom: 100%; left: 0; margin-bottom: 8px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.25); z-index: 10006; min-width: 180px; padding: 4px 0;">
                <div class="customizer-dropdown-item" onclick="createCustomDivider('line')" style="padding: 10px 16px; cursor: pointer; color: var(--text-color); font-size: 13px; font-weight: 500; text-align: left; transition: background 0.2s;">Обычный разделитель</div>
                <div class="customizer-dropdown-item" onclick="createCustomDivider('spacer')" style="padding: 10px 16px; cursor: pointer; color: var(--text-color); font-size: 13px; font-weight: 500; text-align: left; transition: background 0.2s;">Пустой разделитель</div>
            </div>
        </div>
        <button type="button" onclick="saveHeaderCustomization()" class="global-action-btn global-action-btn-primary" style="padding: 8px 16px; border-width: 1px;">Применить</button>
        <button type="button" onclick="cancelHeaderCustomization()" class="global-action-btn global-action-btn-secondary" style="padding: 8px 16px; border-width: 1px;">Отмена</button>
    </div>
</div>

<!-- Контекстное меню для кастомизации хедера -->
<div id="customizerContextMenu" style="display: none; position: fixed; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); z-index: 100007; min-width: 180px; padding: 4px 0;">
    <button type="button" id="ctxToggleVisibility" class="context-menu-item" style="display: block; width: 100%; text-align: left; padding: 10px 16px; background: none; border: none; color: var(--text-color); cursor: pointer; font-size: 14px; font-weight: 500;">Скрыть</button>
    <button type="button" id="ctxTogglePosition" class="context-menu-item" style="display: block; width: 100%; text-align: left; padding: 10px 16px; background: none; border: none; color: var(--text-color); cursor: pointer; font-size: 14px; font-weight: 500;">Перенести в "Прочее"</button>
</div>

<!-- Диалог восстановления сессии -->
<?php require_once __DIR__ . '/modals_editor/session_expired_modal.php'; ?>

<!-- Модальное окно: Менеджер тем -->
<?php require_once __DIR__ . '/modals_editor/theme_manager_modal.php'; ?>

</body>
</html>

