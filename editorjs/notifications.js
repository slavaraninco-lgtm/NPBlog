// ——— Система уведомлений ———
function showNotification(message, type = 'info', title = '') {
    const container = document.getElementById('notificationContainer');
    if (!container) return;

    // Auto-translate message and title through i18n engine
    if (window.NPBlogI18n && typeof window.NPBlogI18n.translateMessage === 'function') {
        message = window.NPBlogI18n.translateMessage(message);
        if (title) {
            title = window.NPBlogI18n.translateMessage(title);
        }
    }

    const notification = document.createElement('div');
    notification.className = `notification ${type}`;

    const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };

    const titles = {
        success: title || (window.t ? window.t('common.success', 'Успешно') : 'Успешно'),
        error: title || (window.t ? window.t('common.error', 'Ошибка') : 'Ошибка'),
        warning: title || (window.t ? window.t('common.warning', 'Внимание') : 'Внимание'),
        info: title || (window.t ? window.t('common.info', 'Информация') : 'Информация')
    };

    notification.innerHTML = `
            <div class="notification-icon">${icons[type] || icons.info}</div>
            <div class="notification-content">
                <div class="notification-title">${titles[type]}</div>
                <div class="notification-message">${message}</div>
            </div>
            <button class="notification-close" onclick="closeNotification(this)">×</button>
        `;

    container.appendChild(notification);

    // Анимация появления
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);

    // Автоматическое скрытие через 5 секунд
    setTimeout(() => {
        closeNotification(notification.querySelector('.notification-close'));
    }, 5000);
}

function closeNotification(btnOrEl) {
    if (!btnOrEl) return;
    const notification = (btnOrEl.classList && btnOrEl.classList.contains('notification'))
        ? btnOrEl
        : (btnOrEl.closest ? btnOrEl.closest('.notification') : null);
    if (!notification) return;

    notification.classList.remove('show');
    notification.classList.add('hide');

    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 400);
}
