// ——— Проверка целостности файлов при загрузке ———
async function checkIntegrity() {
    try {
        const response = await fetch('check_integrity.php');
        const data = await response.json();

        if (!data.success && data.errors.length > 0) {
            const overlay = document.getElementById('integrityErrorOverlay');
            overlay.classList.add('show');
        }
    } catch (error) {
        console.error('Ошибка проверки целостности:', error);
    }
}

async function fixIntegrityErrors() {
    const button = document.querySelector('.integrity-error-button');
    button.textContent = 'Исправление...';
    button.disabled = true;

    try {
        const response = await fetch('fix_integrity.php');
        const data = await response.json();

        if (data.success) {
            showNotification('Все ошибки успешно исправлены!', 'success');

            const overlay = document.getElementById('integrityErrorOverlay');
            overlay.classList.remove('show');

            button.textContent = 'Исправить';
            button.disabled = false;
        } else {
            showNotification('Не удалось исправить некоторые ошибки: ' + data.errors.join(', '), 'error');
            button.textContent = 'Исправить';
            button.disabled = false;
        }
    } catch (error) {
        console.error('Ошибка исправления:', error);
        showNotification('Ошибка при исправлении файлов', 'error');
        button.textContent = 'Исправить';
        button.disabled = false;
    }
}



// ——— Проверка нумерации статей ———
async function checkPostNumbering() {
    const content = document.getElementById('numberingCheckContent');
    const fixBtn = document.getElementById('fixNumberingBtn');

    if (window.Modal) {
        Modal.open('#numberingCheckOverlay');
    } else {
        const overlay = document.getElementById('numberingCheckOverlay');
        if (overlay) overlay.classList.add('show');
    }
    content.innerHTML = '<div class="numbering-status">Проверка нумерации...</div>';
    fixBtn.style.display = 'none';

    try {
        const response = await fetch('renumber_posts.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'check' })
        });

        const data = await response.json();

        if (data.success) {
            if (data.needsFix) {
                let issuesHtml = '<div class="numbering-status warning">';
                issuesHtml += '<strong>⚠ Обнаружены проблемы с нумерацией!</strong><br><br>';
                issuesHtml += 'Следующие статьи имеют неправильную нумерацию:';
                issuesHtml += '<div class="numbering-issues-list">';

                data.issues.forEach(issue => {
                    issuesHtml += `
                        <div class="numbering-issue-item">
                            <div class="numbering-issue-title">${issue.title}</div>
                            <div class="numbering-issue-detail">
                                Текущий номер: ${issue.currentId} → Должен быть: ${issue.expectedId}
                            </div>
                        </div>
                    `;
                });

                issuesHtml += '</div></div>';
                content.innerHTML = issuesHtml;
                fixBtn.style.display = 'block';
            } else {
                content.innerHTML = `
                    <div class="numbering-status success">
                        <strong>✓ Нумерация корректна!</strong><br><br>
                        Все статьи пронумерованы правильно. Исправление не требуется.
                    </div>
                `;
                fixBtn.style.display = 'none';
            }
        } else {
            content.innerHTML = `
                <div class="numbering-status warning">
                    <strong>Ошибка проверки</strong><br><br>
                    ${data.error || 'Не удалось выполнить проверку'}
                </div>
            `;
            fixBtn.style.display = 'none';
        }
    } catch (error) {
        console.error('Ошибка проверки нумерации:', error);
        content.innerHTML = `
            <div class="numbering-status warning">
                <strong>Ошибка проверки</strong><br><br>
                Не удалось выполнить проверку нумерации
            </div>
        `;
        fixBtn.style.display = 'none';
    }
}

async function fixNumbering() {
    const content = document.getElementById('numberingCheckContent');
    const fixBtn = document.getElementById('fixNumberingBtn');

    content.innerHTML = '<div class="numbering-status">Исправление нумерации...</div>';
    fixBtn.disabled = true;

    try {
        const response = await fetch('renumber_posts.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'fix' })
        });

        const data = await response.json();

        if (data.success) {
            if (data.changes && data.changes.length > 0) {
                let changesHtml = '<div class="numbering-status success">';
                changesHtml += '<strong>✓ Нумерация исправлена!</strong><br><br>';
                changesHtml += 'Выполнены следующие изменения:';
                changesHtml += '<div class="numbering-issues-list">';

                data.changes.forEach(change => {
                    changesHtml += `
                        <div class="numbering-issue-item">
                            <div class="numbering-issue-title">${change.title}</div>
                            <div class="numbering-issue-detail">
                                Статья №${change.oldId} → Статья №${change.newId}
                            </div>
                        </div>
                    `;
                });

                changesHtml += '</div></div>';
                content.innerHTML = changesHtml;

                showNotification('Нумерация исправлена', 'success');

                // Обновляем список статей если он открыт
                if (document.getElementById('managePosts').classList.contains('active')) {
                    loadPosts();
                }
            } else {
                content.innerHTML = `
                    <div class="numbering-status success">
                        <strong>✓ ${data.message}</strong><br><br>
                        Изменения не требуются.
                    </div>
                `;
            }

            fixBtn.style.display = 'none';
        } else {
            content.innerHTML = `
                <div class="numbering-status warning">
                    <strong>Ошибка исправления</strong><br><br>
                    ${data.error || 'Не удалось выполнить исправление'}
                </div>
            `;
            fixBtn.disabled = false;
        }
    } catch (error) {
        console.error('Ошибка исправления нумерации:', error);
        content.innerHTML = `
            <div class="numbering-status warning">
                <strong>Ошибка исправления</strong><br><br>
                Не удалось выполнить исправление нумерации
            </div>
        `;
        fixBtn.disabled = false;
    }
}

function closeNumberingCheck() {
    if (window.Modal) {
        Modal.close('#numberingCheckOverlay');
    } else {
        const overlay = document.getElementById('numberingCheckOverlay');
        if (overlay) overlay.classList.remove('show');
    }
}
