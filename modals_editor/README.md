# 🪟 Modals Editor - Модули модальных окон редактора NPBlog

В этой директории хранятся все модальные окна редактора **NPBlog**, переписанные на единый фреймворк модальных окон (`modals/modal.css`, `modals/modal.js`, `modals/modal.php`).

---

## 📋 Реестр модальных окон

| Файл | ID окна | Название / Назначение | Статус |
|---|---|---|---|
| [`image_upload_modal.php`](image_upload_modal.php) | `imageUploadDialog` | Добавление изображений (Drag & Drop, по URL, сетки, галереи) | ✅ Переписано на фреймворк |
| [`link_modal.php`](link_modal.php) | `linkDialog` | Вставка и редактирование гиперссылок | ✅ Переписано на фреймворк |
| [`media_modal.php`](media_modal.php) | `mediaDialog` | Вставка видео/аудио файлов и плееров | ✅ Переписано на фреймворк |
| [`ascii_drawer_modal.php`](ascii_drawer_modal.php) | `asciiEditorModal` | Редактор и рисовалка ASCII-графики и псевдографики | ✅ Переписано на фреймворк |
| [`marker_modal.php`](marker_modal.php) | `markerDialog` | Выбор стиля и цвета текстовыделителя (маркера) | ✅ Переписано на фреймворк |
| [`spoiler_modal.php`](spoiler_modal.php) | `spoilerDialog` | Вставка сворачиваемого блока (спойлера `<details>`) | ✅ Переписано на фреймворк |
| [`table_modal.php`](table_modal.php) | `tableDialog` | Конструктор и вставка таблиц | ✅ Переписано на фреймворк |
| [`file_upload_modal.php`](file_upload_modal.php) | `fileUploadDialog` | Загрузка и прикрепление документов и файлов | ✅ Переписано на фреймворк |
| [`code_modal.php`](code_modal.php) | `codeDialog` | Вставка и редактирование блоков кода (`<pre><code>`) | ✅ Переписано на фреймворк |
| [`custom_button_modal.php`](custom_button_modal.php) | `customButtonDialog` | Конструктор интерактивных кнопок со ссылками | ✅ Переписано на фреймворк |
| [`smile_sets_modal.php`](smile_sets_modal.php) | `smileSetsDialog` | Управление наборами и коллекциями смайлов | ✅ Переписано на фреймворк |
| [`template_manager_modal.php`](template_manager_modal.php) | `templateManagerDialog`, `templateInstructionsDialog`, `templateDetailsDialog`, `applyToPostModal` | Менеджер шаблонов, инструкция, редактор HTML и привязка к статьям | ✅ Переписано на фреймворк |
| [`backup_manager_modal.php`](backup_manager_modal.php) | `backupManagerOverlay` | Менеджер резервных копий (бэкапов) и версионирование статей | ✅ Переписано на фреймворк |
| [`autosave_manager_modal.php`](autosave_manager_modal.php) | `autosaveManagerModal` | Менеджер автосохранений и черновиков статей | ✅ Переписано на фреймворк |
| [`theme_manager_modal.php`](theme_manager_modal.php) | `themeManagerModal` | Менеджер тем оформления (тёмная, светлая, кастомный CSS) | ✅ Переписано на фреймворк |
| [`global_settings_modal.php`](global_settings_modal.php) | `globalSettingsModal` | Глобальные параметры блога (11 разделов настроек) | ✅ Переписано на фреймворк |
| [`delete_confirm_modal.php`](delete_confirm_modal.php) | `deleteConfirmOverlay` | Диалог подтверждения удаления статьи | ✅ Переписано на фреймворк |
| [`dev_warning_modal.php`](dev_warning_modal.php) | `devWarningDialog` | Предупреждение о нестабильной DEV-сборке системы | ✅ Переписано на фреймворк |
| [`session_expired_modal.php`](session_expired_modal.php) | `sessionExpiredOverlay` | Окно восстановления истекшей сессии и повторной авторизации | ✅ Переписано на фреймворк |
| [`save_include_modal.php`](save_include_modal.php) | `saveIncludeOverlay` | Диалог быстрого сохранения контента в блок includes | ✅ Переписано на фреймворк |
| [`numbering_check_modal.php`](numbering_check_modal.php) | `numberingCheckOverlay` | Окно проверки и исправления сквозной нумерации статей | ✅ Переписано на фреймворк |
| [`system_update_modal.php`](system_update_modal.php) | `systemUpdateModal`, `restoreSystemModal` | Обновление и откат (Rollback) ядра блога NPBlog | ✅ Переписано на фреймворк |
| [`custom_fonts_modal.php`](custom_fonts_modal.php) | `customFontsModal` | Управление и выбор пользовательских шрифтов (.ttf, .otf, .woff, .woff2) | ✅ Переписано на фреймворк |
| [`additional_settings_modal.php`](additional_settings_modal.php) | `additionalSettingsModal` | Дополнительные настройки статьи (индивидуальный фон, область, подложка) | ✅ Переписано на фреймворк |
| [`cell_color_modal.php`](cell_color_modal.php) | `cellColorDialog` | Выбор цвета заливки ячейки таблицы (палитра и кастомный цвет) | ✅ Переписано на фреймворк |
| [`ftp_upload_modal.php`](ftp_upload_modal.php) | `ftpUploadModal` | Публикация и стриминговая загрузка файлов блога по FTP/FTPS | ✅ Переписано на фреймворк |
| [`initial_setup_modal.php`](initial_setup_modal.php) | `initialSetupModal` | Мастер первоначальной настройки редактора (Onboarding Wizard) | ✅ Переписано на фреймворк |
| [`safe_mode_modal.php`](safe_mode_modal.php) | `safeModeOverlay` | Аварийный режим Safe Mode и восстановление системы из ZIP-архива | ✅ Переписано на фреймворк |

---

## 🛠️ Соглашения по разработке модулей окон

1. **Разметка**:
   Каждый файл содержит независимый PHP/HTML блок с оверлеем и карточкой окна:
   ```html
   <div id="myModalId" class="modal-overlay" data-size="md">
       <div class="modal-dialog modal-md">
           <div class="modal-header">...</div>
           <div class="modal-body">...</div>
           <div class="modal-footer">...</div>
       </div>
   </div>
   ```

2. **Подключение в `index.php`**:
   Модальные окна подключаются одной строкой:
   ```php
   <?php require_once __DIR__ . '/modals_editor/image_upload_modal.php'; ?>
   ```

3. **Управление через JavaScript**:
   - Открытие: `Modal.open('#imageUploadDialog')` или `showImageUpload()`
   - Закрытие: `Modal.close('#imageUploadDialog')` или `closeImageDialog()`
   - Кнопки закрытия размечаются атрибутом `data-modal-close`.
