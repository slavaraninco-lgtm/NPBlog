<?php
/**
 * ==============================================================================
 * NPBlog Editor - Полноэкранный мастер первоначальной настройки (Onboarding Wizard)
 * ==============================================================================
 * Полноэкранный мастер:
 *   Слайд 0: Экран приветствия с анимацией надписи NPBlog и описания.
 *   Слайд 1: Язык интерфейса, заголовок blog.html, автосохранение, путь к папке data.
 *   Слайд 2: Безопасность (защита паролем, ограничение по IP).
 *   Слайд 3: Экран завершения с галочкой в кружочке и выбором ("Пройти обучение" / "Продолжить").
 * ==============================================================================
 */

if (!isset($availableLanguages)) {
    require_once __DIR__ . '/../lang_helper.php';
    $availableLanguages = getAvailableLanguages();
    $currentLanguage = getCurrentLanguage();
}

$defaultDataPath = '';
if (function_exists('getDataPath')) {
    $defaultDataPath = rtrim(str_replace('/', DIRECTORY_SEPARATOR, getDataPath()), DIRECTORY_SEPARATOR);
} else {
    $defaultDataPath = __DIR__ . DIRECTORY_SEPARATOR . 'data';
}

$defaultBlogTitle = 'Блог';
$blogViewSettingsFile = function_exists('getDataPath') ? getDataPath('blog-view-settings.json') : __DIR__ . '/../data/blog-view-settings.json';
if (file_exists($blogViewSettingsFile)) {
    $bView = json_decode(@file_get_contents($blogViewSettingsFile), true);
    if (!empty($bView['title'])) {
        $defaultBlogTitle = $bView['title'];
    }
}
?>

<style>
/* ==============================================================================
 * Полноэкранный контейнер первоначальной настройки
 * ============================================================================== */
#initialSetupModal.modal-overlay,
#initialSetupModal.setup-fullscreen-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    padding: 0 !important;
    background-color: rgba(255, 255, 255, 0.78) !important;
    backdrop-filter: blur(14px) saturate(180%);
    -webkit-backdrop-filter: blur(14px) saturate(180%);
    color: var(--text-color, #111827);
    overflow-y: auto !important;
    overflow-x: hidden !important;
    display: none;
    z-index: 100000;
}

[data-theme="dark"] #initialSetupModal {
    background-color: rgba(18, 18, 22, 0.78) !important;
}

[data-theme="dark"][data-amoled="true"] #initialSetupModal {
    background-color: rgba(0, 0, 0, 0.82) !important;
}

/* Экран приветствия и экран успеха */
.setup-welcome-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 32px 20px;
    box-sizing: border-box;
    text-align: center;
}

.setup-welcome-inner {
    max-width: 600px;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* Простая чистая надпись NPBlog */
.setup-welcome-title {
    font-size: clamp(2.8rem, 6vw, 4rem);
    font-weight: 700;
    letter-spacing: -0.02em;
    margin: 0 0 12px 0;
    color: var(--text-color);
    opacity: 0;
    animation: setupFadeInUp 0.75s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

/* Описание */
.setup-welcome-subtitle {
    font-size: clamp(1.05rem, 2vw, 1.25rem);
    font-weight: 400;
    color: var(--text-color);
    opacity: 0;
    line-height: 1.5;
    margin: 0 0 32px 0;
    animation: setupFadeInUp 0.75s cubic-bezier(0.16, 1, 0.3, 1) 0.18s forwards;
}

.setup-welcome-btn-wrap {
    opacity: 0;
    animation: setupFadeInUp 0.75s cubic-bezier(0.16, 1, 0.3, 1) 0.36s forwards;
}

/* Полноэкранный мастер настройки */
.setup-wizard-fullscreen {
    display: none;
    min-height: 100vh;
    padding: 40px 20px;
    box-sizing: border-box;
    justify-content: center;
    align-items: center;
}

.setup-wizard-content {
    max-width: 720px;
    width: 100%;
    margin: auto;
    display: flex;
    flex-direction: column;
    animation: setupFadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

/* Галочка в кружочке на экране завершения */
.setup-success-circle {
    width: 68px;
    height: 68px;
    border-radius: 50%;
    background: transparent;
    border: 2px solid var(--text-color);
    color: var(--text-color);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 24px;
    animation: setupSuccessPop 0.55s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}

/* Анимации появления */
@keyframes setupFadeInUp {
    0% {
        opacity: 0;
        transform: translateY(18px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes setupSuccessPop {
    0% {
        opacity: 0;
        transform: scale(0.4);
    }
    70% {
        transform: scale(1.1);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}
</style>

<div id="initialSetupModal" class="setup-fullscreen-overlay">
    
    <!-- Кнопка закрытия (доступна при повторном запуске из настроек) -->
    <button type="button" id="initialSetupCloseBtn" onclick="closeInitialSetupModal()" style="display: none; position: fixed; top: 20px; right: 24px; z-index: 100001; background: none; border: none; font-size: 26px; color: var(--text-color); cursor: pointer; opacity: 0.6; line-height: 1; padding: 4px 8px; border-radius: 6px;" title="Закрыть">×</button>

    <!-- ====================================================================== -->
    <!-- СЛАЙД 0: ЭКРАН ПРИВЕТСТВИЯ -->
    <!-- ====================================================================== -->
    <div id="setupSlide0" class="setup-welcome-container">
        <div class="setup-welcome-inner">
            <h1 class="setup-welcome-title" data-i18n="setup.welcome_title">NPBlog</h1>
            <p class="setup-welcome-subtitle" data-i18n="setup.welcome_subtitle">Редактор блогов для статических хостингов</p>
            <div class="setup-welcome-btn-wrap">
                <button type="button" class="modal-btn modal-btn-primary" onclick="goToSetupStep(1)" style="min-width: 140px; padding: 10px 24px; font-size: 14px; font-weight: 600;">
                    <span data-i18n="setup.welcome_btn">Далее →</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- ОСНОВНОЙ МАСТЕР НАСТРОЙКИ (ПОЛНОЭКРАННЫЙ): ШАГИ 1 И 2 -->
    <!-- ====================================================================== -->
    <div id="setupWizardWrap" class="setup-wizard-fullscreen">
        <div class="setup-wizard-content">
            
            <!-- Заголовок страницы -->
            <div style="margin-bottom: 24px;">
                <h2 style="font-size: 24px; font-weight: 700; margin: 0 0 6px 0; color: var(--text-color);" data-i18n="setup.title">Первоначальная настройка</h2>
                <p style="font-size: 13.5px; opacity: 0.75; margin: 0; color: var(--text-color);" data-i18n="setup.subtitle">Быстрая конфигурация основных параметров редактора NPBlog</p>
            </div>

            <!-- Индикатор шагов (Stepper) -->
            <div class="setup-stepper-bar" style="padding: 14px 18px; background: rgba(128, 128, 128, 0.05); border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between;">
                <div class="setup-step-item active" id="setupStepIndicator1" onclick="if(window.setupCanNavigate) goToSetupStep(1)" style="display: flex; align-items: center; gap: 10px; cursor: pointer; flex: 1;">
                    <div class="setup-step-num" style="width: 28px; height: 28px; border-radius: 50%; background: var(--text-color); color: var(--bg-color); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; transition: all 0.2s;">1</div>
                    <div>
                        <div style="font-size: 13px; font-weight: 600; color: var(--text-color);" data-i18n="setup.step1_title">Основные параметры</div>
                        <div style="font-size: 11px; opacity: 0.6; color: var(--text-color);" data-i18n="setup.step1_subtitle">Язык, блог, автосохранение, data</div>
                    </div>
                </div>

                <div class="setup-stepper-divider" style="width: 40px; height: 2px; background: var(--border-color); margin: 0 16px; position: relative;">
                    <div id="setupStepProgressLine" style="position: absolute; left: 0; top: 0; height: 100%; width: 0%; background: var(--primary-color, #4CAF50); transition: width 0.3s ease;"></div>
                </div>

                <div class="setup-step-item" id="setupStepIndicator2" onclick="if(window.setupCanNavigate) goToSetupStep(2)" style="display: flex; align-items: center; gap: 10px; cursor: pointer; flex: 1; opacity: 0.5;">
                    <div class="setup-step-num" style="width: 28px; height: 28px; border-radius: 50%; background: rgba(128,128,128,0.2); color: var(--text-color); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; transition: all 0.2s;">2</div>
                    <div>
                        <div style="font-size: 13px; font-weight: 600; color: var(--text-color);" data-i18n="setup.step2_title">Безопасность и доступ</div>
                        <div style="font-size: 11px; opacity: 0.6; color: var(--text-color);" data-i18n="setup.step2_subtitle">Пароль, IP-фильтр</div>
                    </div>
                </div>
            </div>

            <!-- Сообщение об ошибке валидации -->
            <div id="setupValidationError" class="modal-alert modal-alert-danger" style="display: none; margin-bottom: 20px;">
                <span class="modal-alert-icon">⚠️</span>
                <div class="modal-alert-content">
                    <div class="modal-alert-title" data-i18n="common.warning">Внимание</div>
                    <span id="setupValidationErrorMessage"></span>
                </div>
            </div>

            <!-- ====================================================================== -->
            <!-- СЛАЙД 1: ОСНОВНЫЕ ПАРАМЕТРЫ -->
            <!-- ====================================================================== -->
            <div id="setupSlide1" class="setup-slide active-slide" style="display: block; transition: all 0.25s ease;">
                
                <!-- 1. Выбор языка -->
                <div class="modal-form-group" style="margin-bottom: 22px;">
                    <label class="modal-label" style="font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                        <span>🌐</span> <span data-i18n="setup.lang_label">Язык интерфейса:</span>
                    </label>
                    
                    <div id="setupLanguageCardsContainer" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px;">
                        <?php foreach ($availableLanguages as $lang): ?>
                            <?php $isSel = ($lang['code'] === $currentLanguage); ?>
                            <div class="setup-lang-card <?= $isSel ? 'selected' : '' ?>" 
                                 onclick="selectSetupLanguage('<?= htmlspecialchars($lang['code']) ?>')" 
                                 data-lang="<?= htmlspecialchars($lang['code']) ?>"
                                 style="border: 2px solid <?= $isSel ? 'var(--primary-color, #4CAF50)' : 'var(--border-color)' ?>; background: <?= $isSel ? 'rgba(76, 175, 80, 0.08)' : 'transparent' ?>; border-radius: 10px; padding: 12px 14px; cursor: pointer; text-align: center; transition: all 0.15s ease; user-select: none;">
                                <div style="font-size: 24px; margin-bottom: 4px;"><?= htmlspecialchars($lang['smile'] ?? '🌐') ?></div>
                                <div style="font-size: 13px; font-weight: 600; color: var(--text-color);"><?= htmlspecialchars($lang['name'] ?? strtoupper($lang['code'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="setupSelectedLanguage" value="<?= htmlspecialchars($currentLanguage) ?>">
                    <div class="modal-help-text" style="margin-top: 6px; font-size: 12px; opacity: 0.7;" data-i18n="setup.lang_hint">Выберите язык панели управления (переключается сразу)</div>
                </div>

                <div class="modal-grid-2" style="margin-bottom: 18px; gap: 18px;">
                    <!-- 2. Заголовок страницы blog.html -->
                    <div class="modal-form-group" style="margin-bottom: 0;">
                        <label class="modal-label modal-label-required" for="setupBlogTitle" style="font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                            <span>🏷️</span> <span data-i18n="setup.blog_title_label">Заголовок страницы (blog.html):</span>
                        </label>
                        <input type="text" id="setupBlogTitle" class="modal-input" value="<?= htmlspecialchars($defaultBlogTitle) ?>" placeholder="Например: Мой блог" data-i18n-placeholder="setup.blog_title_ph" required autocomplete="off" onkeydown="if(event.key==='Enter') goToSetupStep(2)">
                        <div class="modal-help-text" style="font-size: 11.5px; opacity: 0.7; margin-top: 4px;" data-i18n="setup.blog_title_hint">Отображается в шапке каталога статей blog.html</div>
                    </div>

                    <!-- 3. Автосохранение -->
                    <div class="modal-form-group" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
                        <label class="modal-label" style="font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                            <span>💾</span> <span data-i18n="setup.autosave_label">Автосохранение:</span>
                        </label>
                        
                        <div style="padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; background: rgba(128,128,128,0.04);">
                            <label class="modal-switch-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin-bottom: 8px;">
                                <div class="modal-switch-control">
                                    <input type="checkbox" id="setupAutosaveEnabled" checked onchange="toggleSetupAutosaveInterval(this.checked)">
                                    <span class="modal-switch-slider"></span>
                                </div>
                                <span style="font-size: 13px; font-weight: 500;" data-i18n="setup.autosave_toggle">Включить сохранение черновиков</span>
                            </label>
                            
                            <div id="setupAutosaveIntervalWrap" style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                <span style="font-size: 12px; opacity: 0.8;" data-i18n="setup.autosave_interval_label">Интервал:</span>
                                <input type="number" id="setupAutosaveInterval" class="modal-input" value="60" min="10" max="600" style="width: 80px; padding: 4px 8px; font-size: 13px; height: 30px;">
                                <span style="font-size: 12px; opacity: 0.6;">сек.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Путь к папке data -->
                <div class="modal-form-group" style="margin-bottom: 8px;">
                    <label class="modal-label modal-label-required" for="setupDataPath" style="font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                        <span>📁</span> <span data-i18n="setup.data_path_label">Путь к папке данных (data):</span>
                    </label>
                    <div style="display: flex; gap: 8px; align-items: stretch;">
                        <input type="text" id="setupDataPath" class="modal-input" value="<?= htmlspecialchars($defaultDataPath) ?>" placeholder="C:\xampp\htdocs\data или data" data-i18n-placeholder="setup.data_path_ph" required autocomplete="off" style="font-family: monospace; font-size: 13px;" onkeydown="if(event.key==='Enter') goToSetupStep(2)">
                        <button type="button" class="modal-btn modal-btn-secondary" onclick="resetSetupDataPathToDefault()" style="white-space: nowrap; padding: 0 16px; font-size: 12px; display: inline-flex; align-items: center; justify-content: center;" title="Восстановить путь по умолчанию">По умолч.</button>
                    </div>
                    <div class="modal-help-text" style="font-size: 11.5px; opacity: 0.7; margin-top: 5px;" data-i18n="setup.data_path_hint">Директория на сервере для хранения статей, файлов и настроек</div>
                </div>

            </div>

            <!-- ====================================================================== -->
            <!-- СЛАЙД 2: БЕЗОПАСНОСТЬ И ДОСТУП -->
            <!-- ====================================================================== -->
            <div id="setupSlide2" class="setup-slide" style="display: none; transition: all 0.25s ease;">
                
                <!-- Баннер-информация -->
                <div class="modal-alert modal-alert-info" style="margin-bottom: 20px;">
                    <span class="modal-alert-icon">🛡️</span>
                    <div class="modal-alert-content">
                        <div class="modal-alert-title" data-i18n="setup.sec_banner_title">Защита редактора</div>
                        <span data-i18n="setup.sec_banner_desc">Вы можете защитить панель управления паролем прямо сейчас или оставить доступ свободным (настройки всегда можно изменить в параметрах).</span>
                    </div>
                </div>

                <!-- 1. Переключатель пароля -->
                <div class="modal-section-card" style="padding: 16px 18px; border: 1px solid var(--border-color); border-radius: 10px; background: rgba(128,128,128,0.04); margin-bottom: 18px;">
                    <label class="modal-switch-label" style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                        <div class="modal-switch-control">
                            <input type="checkbox" id="setupPasswordEnabled" onchange="toggleSetupPasswordFields(this.checked)">
                            <span class="modal-switch-slider"></span>
                        </div>
                        <div>
                            <span style="font-size: 14px; font-weight: 600; color: var(--text-color);" data-i18n="setup.sec_pwd_toggle">Включить защиту паролем</span>
                            <div style="font-size: 11.5px; opacity: 0.6; margin-top: 2px;">Запрашивать пароль при каждом открытии редактора</div>
                        </div>
                    </label>

                    <!-- Поля ввода пароля -->
                    <div id="setupPasswordFields" style="display: none; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                        <div class="modal-grid-2" style="gap: 16px; margin-bottom: 10px;">
                            <div class="modal-form-group" style="margin-bottom: 0;">
                                <label class="modal-label modal-label-required" for="setupNewPassword" data-i18n="setup.sec_pwd_new_label" style="font-size: 12px; font-weight: 600;">Новый пароль:</label>
                                <div style="position: relative;">
                                    <input type="password" id="setupNewPassword" class="modal-input" placeholder="Введите пароль для входа" data-i18n-placeholder="setup.sec_pwd_new_ph" style="padding-right: 38px;" onkeydown="if(event.key==='Enter') finishInitialSetup()">
                                    <button type="button" class="setup-pwd-eye-btn" onclick="toggleSetupPasswordEye('setupNewPassword', this)" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 15px; opacity: 0.6;" title="Показать/скрыть">👁️</button>
                                </div>
                            </div>
                            <div class="modal-form-group" style="margin-bottom: 0;">
                                <label class="modal-label modal-label-required" for="setupConfirmPassword" data-i18n="setup.sec_pwd_confirm_label" style="font-size: 12px; font-weight: 600;">Подтверждение пароля:</label>
                                <div style="position: relative;">
                                    <input type="password" id="setupConfirmPassword" class="modal-input" placeholder="Повторите пароль" data-i18n-placeholder="setup.sec_pwd_confirm_ph" style="padding-right: 38px;" onkeydown="if(event.key==='Enter') finishInitialSetup()">
                                    <button type="button" class="setup-pwd-eye-btn" onclick="toggleSetupPasswordEye('setupConfirmPassword', this)" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 15px; opacity: 0.6;" title="Показать/скрыть">👁️</button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-help-text" style="font-size: 11.5px; opacity: 0.75;" data-i18n="setup.sec_pwd_hint">Запомните этот пароль — он потребуется для входа в панель управления</div>
                    </div>
                </div>

                <!-- 2. Ограничение по IP -->
                <div class="modal-section-card" style="padding: 16px 18px; border: 1px solid var(--border-color); border-radius: 10px; background: rgba(128,128,128,0.04);">
                    <label class="modal-checkbox-label" style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                        <input type="checkbox" id="setupIpWhitelistEnabled" class="modal-checkbox" style="margin-top: 2px;">
                        <div>
                            <span style="font-size: 13.5px; font-weight: 600; color: var(--text-color);" data-i18n="setup.sec_ip_toggle">Ограничить доступ по списку IP (allowed_ips.txt)</span>
                            <div style="font-size: 12px; opacity: 0.7; margin-top: 3px;" data-i18n="setup.sec_ip_hint">Ваш текущий IP-адрес будет автоматически добавлен в список доверенных.</div>
                        </div>
                    </label>
                </div>

            </div>

            <!-- Подвал страницы с кнопками перехода -->
            <div style="margin-top: 36px; padding-top: 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <div id="setupFooterLeft">
                    <button type="button" id="setupBtnBack" class="modal-btn modal-btn-ghost" onclick="handleSetupBack()" data-i18n="setup.btn_back">← Назад</button>
                </div>
                
                <div id="setupFooterRight" style="display: flex; gap: 10px;">
                    <button type="button" id="setupBtnNext" class="modal-btn modal-btn-primary" onclick="goToSetupStep(2)" style="padding: 10px 22px; font-weight: 600;" data-i18n="setup.btn_next">
                        Далее: Безопасность →
                    </button>
                    <button type="button" id="setupBtnFinish" class="modal-btn modal-btn-primary" onclick="finishInitialSetup()" style="display: none; padding: 10px 24px; font-weight: 600;" data-i18n="setup.btn_finish">
                        Завершить настройку
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- СЛАЙД 3: НАСТРОЙКА ЗАВЕРШЕНА -->
    <!-- ====================================================================== -->
    <div id="setupSlideSuccess" class="setup-welcome-container" style="display: none;">
        <div class="setup-welcome-inner">
            <!-- Галочка в кружочке -->
            <div class="setup-success-circle">
                <svg viewBox="0 0 52 52" style="width: 38px; height: 38px;">
                    <path fill="none" stroke="currentColor" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round" d="M14 27 l8 8 l16 -17"/>
                </svg>
            </div>
            
            <h2 class="setup-welcome-title" style="font-size: clamp(2rem, 4.5vw, 2.6rem); margin: 0 0 28px 0;" data-i18n="setup.completed_title">Настройка завершена</h2>
            
            <div class="setup-welcome-btn-wrap" style="display: flex; gap: 12px; justify-content: center; align-items: center; flex-wrap: wrap;">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="launchTutorialFromSetup()" style="padding: 10px 22px; font-weight: 600; font-size: 14px;" data-i18n="setup.btn_start_tutorial">
                    Пройти обучение
                </button>
                <button type="button" class="modal-btn modal-btn-primary" onclick="continueToEditorFromSetup()" style="padding: 10px 24px; font-weight: 600; font-size: 14px;" data-i18n="setup.btn_continue">
                    Продолжить
                </button>
            </div>
        </div>
    </div>

</div>

<script>
/**
 * Initial Setup Wizard Controller (Fullscreen Onboarding)
 */
window.currentSetupStep = 0;
window.setupDefaultDataPath = <?= json_encode($defaultDataPath) ?>;
window.setupCanNavigate = true;

function openInitialSetupModal() {
    window.currentSetupStep = 0;
    goToSetupStep(0);
    hideSetupError();
    
    // Подгружаем актуальные настройки если они уже были сохранены
    fetch('get_editor_settings.php?t=' + Date.now())
        .then(res => res.json())
        .then(data => {
            if (data.success && data.settings) {
                const s = data.settings;
                if (s.language) {
                    selectSetupLanguage(s.language, false);
                }
                if (s.blog_title) {
                    const tInput = document.getElementById('setupBlogTitle');
                    if (tInput) tInput.value = s.blog_title;
                }
                if (s.autosaveEnabled !== undefined) {
                    const chk = document.getElementById('setupAutosaveEnabled');
                    if (chk) {
                        chk.checked = !!s.autosaveEnabled;
                        toggleSetupAutosaveInterval(chk.checked);
                    }
                }
                if (s.autosaveInterval) {
                    const intInput = document.getElementById('setupAutosaveInterval');
                    if (intInput) intInput.value = s.autosaveInterval;
                }
                if (s.active_blog_path || s.data_path) {
                    const pInput = document.getElementById('setupDataPath');
                    if (pInput) pInput.value = s.active_blog_path || s.data_path;
                }
                
                // Если настройка запускается повторно пользователем (уже была завершена ранее), показываем крестик закрытия
                const closeBtn = document.getElementById('initialSetupCloseBtn');
                if (closeBtn) {
                    closeBtn.style.display = (s.initial_setup_completed === true) ? 'block' : 'none';
                }
            }
        }).catch(() => {});

    const modal = document.getElementById('initialSetupModal');
    if (modal) {
        modal.style.display = 'block';
        document.body.classList.add('modal-open');
    }
}

function closeInitialSetupModal() {
    const modal = document.getElementById('initialSetupModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
    }
}

function selectSetupLanguage(langCode, applyLive = true) {
    const langHidden = document.getElementById('setupSelectedLanguage');
    if (langHidden) langHidden.value = langCode;
    
    // Подсвечиваем выбранную карточку
    document.querySelectorAll('.setup-lang-card').forEach(card => {
        const isThis = (card.getAttribute('data-lang') === langCode);
        card.classList.toggle('selected', isThis);
        card.style.border = isThis ? '2px solid var(--primary-color, #4CAF50)' : '2px solid var(--border-color)';
        card.style.background = isThis ? 'rgba(76, 175, 80, 0.08)' : 'transparent';
    });

    // Мгновенное применение перевода интерфейса на лету
    if (applyLive && window.NPBlogI18n && typeof window.NPBlogI18n.setLanguage === 'function') {
        window.NPBlogI18n.setLanguage(langCode, false);
    }
}

function toggleSetupAutosaveInterval(isEnabled) {
    const wrap = document.getElementById('setupAutosaveIntervalWrap');
    if (wrap) wrap.style.opacity = isEnabled ? '1' : '0.4';
    const input = document.getElementById('setupAutosaveInterval');
    if (input) input.disabled = !isEnabled;
}

function toggleSetupPasswordFields(isEnabled) {
    const fields = document.getElementById('setupPasswordFields');
    if (fields) {
        fields.style.display = isEnabled ? 'block' : 'none';
        if (isEnabled) {
            setTimeout(() => {
                const pwdInput = document.getElementById('setupNewPassword');
                if (pwdInput) pwdInput.focus();
            }, 60);
        }
    }
}

function toggleSetupPasswordEye(inputId, btnEl) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btnEl.textContent = '🔒';
    } else {
        input.type = 'password';
        btnEl.textContent = '👁️';
    }
}

function resetSetupDataPathToDefault() {
    const input = document.getElementById('setupDataPath');
    if (input) {
        input.value = window.setupDefaultDataPath || 'data';
    }
}

function showSetupError(msgKey, defaultMsg) {
    const errBox = document.getElementById('setupValidationError');
    const errMsg = document.getElementById('setupValidationErrorMessage');
    if (!errBox || !errMsg) return;
    
    const text = (window.t ? window.t(msgKey, defaultMsg) : defaultMsg);
    errMsg.textContent = text;
    errBox.style.display = 'flex';
    
    const content = document.querySelector('#initialSetupModal .setup-wizard-content');
    if (content) {
        content.classList.remove('modal-shake');
        void content.offsetWidth;
        content.classList.add('modal-shake');
    }
}

function hideSetupError() {
    const errBox = document.getElementById('setupValidationError');
    if (errBox) errBox.style.display = 'none';
}

function validateSetupStep1() {
    hideSetupError();
    const title = document.getElementById('setupBlogTitle').value.trim();
    const dataPath = document.getElementById('setupDataPath').value.trim();
    
    if (!title) {
        showSetupError('setup.validation_title_empty', 'Пожалуйста, введите заголовок страницы blog.html');
        document.getElementById('setupBlogTitle').focus();
        return false;
    }
    
    if (!dataPath) {
        showSetupError('setup.validation_path_empty', 'Пожалуйста, укажите путь к папке data');
        document.getElementById('setupDataPath').focus();
        return false;
    }
    
    return true;
}

function validateSetupStep2() {
    hideSetupError();
    const pwdEnabled = document.getElementById('setupPasswordEnabled').checked;
    
    if (pwdEnabled) {
        const newPwd = document.getElementById('setupNewPassword').value;
        const confirmPwd = document.getElementById('setupConfirmPassword').value;
        
        if (!newPwd) {
            showSetupError('setup.validation_pwd_empty', 'Пожалуйста, введите новый пароль');
            document.getElementById('setupNewPassword').focus();
            return false;
        }
        
        if (newPwd.length < 4) {
            showSetupError('setup.validation_pwd_short', 'Пароль должен содержать минимум 4 символа');
            document.getElementById('setupNewPassword').focus();
            return false;
        }
        
        if (newPwd !== confirmPwd) {
            showSetupError('setup.validation_pwd_mismatch', 'Пароли не совпадают!');
            document.getElementById('setupConfirmPassword').focus();
            return false;
        }
    }
    
    return true;
}

function goToSetupStep(step) {
    if (step === 2) {
        if (!validateSetupStep1()) return;
    }
    
    hideSetupError();
    window.currentSetupStep = step;
    
    const slide0 = document.getElementById('setupSlide0');
    const wizardWrap = document.getElementById('setupWizardWrap');
    const slideSuccess = document.getElementById('setupSlideSuccess');
    
    if (step === 0) {
        if (slide0) slide0.style.display = 'flex';
        if (wizardWrap) wizardWrap.style.display = 'none';
        if (slideSuccess) slideSuccess.style.display = 'none';
    } else if (step === 'success') {
        if (slide0) slide0.style.display = 'none';
        if (wizardWrap) wizardWrap.style.display = 'none';
        if (slideSuccess) slideSuccess.style.display = 'flex';
    } else {
        if (slide0) slide0.style.display = 'none';
        if (wizardWrap) wizardWrap.style.display = 'flex';
        if (slideSuccess) slideSuccess.style.display = 'none';
        updateSetupUIForStep(step);
    }
    
    // Скролл наверх страницы
    const modal = document.getElementById('initialSetupModal');
    if (modal) modal.scrollTop = 0;
}

function handleSetupBack() {
    if (window.currentSetupStep === 2) {
        goToSetupStep(1);
    } else if (window.currentSetupStep === 1) {
        goToSetupStep(0);
    }
}

function updateSetupUIForStep(step) {
    const slide1 = document.getElementById('setupSlide1');
    const slide2 = document.getElementById('setupSlide2');
    const btnNext = document.getElementById('setupBtnNext');
    const btnBack = document.getElementById('setupBtnBack');
    const btnFinish = document.getElementById('setupBtnFinish');
    
    const ind1 = document.getElementById('setupStepIndicator1');
    const ind2 = document.getElementById('setupStepIndicator2');
    const progressLine = document.getElementById('setupStepProgressLine');

    if (step === 1) {
        if (slide1) slide1.style.display = 'block';
        if (slide2) slide2.style.display = 'none';
        if (btnNext) btnNext.style.display = 'inline-flex';
        if (btnBack) btnBack.style.display = 'inline-flex';
        if (btnFinish) btnFinish.style.display = 'none';
        
        if (ind1) {
            ind1.style.opacity = '1';
            const num1 = ind1.querySelector('.setup-step-num');
            if (num1) {
                num1.style.background = 'var(--text-color)';
                num1.style.color = 'var(--bg-color)';
                num1.textContent = '1';
            }
        }
        if (ind2) {
            ind2.style.opacity = '0.5';
            const num2 = ind2.querySelector('.setup-step-num');
            if (num2) {
                num2.style.background = 'rgba(128,128,128,0.2)';
                num2.style.color = 'var(--text-color)';
                num2.textContent = '2';
            }
        }
        if (progressLine) progressLine.style.width = '0%';
    } else if (step === 2) {
        if (slide1) slide1.style.display = 'none';
        if (slide2) slide2.style.display = 'block';
        if (btnNext) btnNext.style.display = 'none';
        if (btnBack) btnBack.style.display = 'inline-flex';
        if (btnFinish) btnFinish.style.display = 'inline-flex';
        
        if (ind1) {
            ind1.style.opacity = '0.7';
            const num1 = ind1.querySelector('.setup-step-num');
            if (num1) {
                num1.style.background = 'var(--primary-color, #4CAF50)';
                num1.style.color = '#ffffff';
                num1.textContent = '✓';
            }
        }
        if (ind2) {
            ind2.style.opacity = '1';
            const num2 = ind2.querySelector('.setup-step-num');
            if (num2) {
                num2.style.background = 'var(--text-color)';
                num2.style.color = 'var(--bg-color)';
                num2.textContent = '2';
            }
        }
        if (progressLine) progressLine.style.width = '100%';
    }
}

async function finishInitialSetup() {
    if (!validateSetupStep2()) return;
    
    const finishBtn = document.getElementById('setupBtnFinish');
    if (finishBtn) finishBtn.classList.add('is-loading');
    
    const lang = document.getElementById('setupSelectedLanguage').value;
    const blogTitle = document.getElementById('setupBlogTitle').value.trim();
    const autosaveEnabled = document.getElementById('setupAutosaveEnabled').checked;
    const autosaveInterval = parseInt(document.getElementById('setupAutosaveInterval').value, 10) || 60;
    const dataPath = document.getElementById('setupDataPath').value.trim();
    const pwdEnabled = document.getElementById('setupPasswordEnabled').checked;
    const newPwd = document.getElementById('setupNewPassword').value;
    const ipWhitelistEnabled = document.getElementById('setupIpWhitelistEnabled').checked;
    
    const payload = {
        initial_setup_completed: true,
        language: lang,
        blog_title: blogTitle,
        autosaveEnabled: autosaveEnabled,
        autosaveInterval: autosaveInterval,
        blog_paths: [dataPath],
        active_blog_path: dataPath,
        data_path: dataPath,
        ip_whitelist_enabled: ipWhitelistEnabled,
        password_enabled: pwdEnabled
    };
    
    if (pwdEnabled && newPwd) {
        payload.new_password = newPwd;
    }
    
    try {
        const res = await fetch('save_editor_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const data = await res.json();
        if (finishBtn) finishBtn.classList.remove('is-loading');
        
        if (data.success) {
            // Применяем настройки в редакторе
            if (typeof loadAndApplyAllSettings === 'function') {
                loadAndApplyAllSettings();
            }
            if (typeof loadPosts === 'function') {
                loadPosts();
            }
            
            // Переходим на экран завершения настройки
            goToSetupStep('success');
        } else {
            showSetupError('common.error', data.error || 'Ошибка при сохранении параметров');
        }
    } catch (err) {
        console.error('Ошибка сохранения первоначальной настройки:', err);
        if (finishBtn) finishBtn.classList.remove('is-loading');
        showSetupError('common.network_error', 'Сетевая ошибка при сохранении настроек');
    }
}

function launchTutorialFromSetup() {
    closeInitialSetupModal();
    
    // Сбрасываем флаг завершения обучения и запускаем гайд
    fetch('save_editor_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tutorialCompleted: false })
    }).finally(() => {
        if (typeof currentTutorialStep !== 'undefined') {
            currentTutorialStep = 0;
        }
        setTimeout(() => {
            if (typeof showTutorialStep === 'function') {
                showTutorialStep();
            } else if (typeof startTutorial === 'function') {
                startTutorial();
            }
        }, 150);
    });
}

function continueToEditorFromSetup() {
    // Отмечаем обучение завершенным и закрываем мастер
    fetch('save_editor_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tutorialCompleted: true })
    }).finally(() => {
        closeInitialSetupModal();
    });
}
</script>
