# 🪟 NPBlog Modal Framework

Универсальный легковесный фреймворк для создания модальных окон в едином визуальном и техническом стиле редактора **NPBlog**.

---

## 📋 Содержание
1. [Особенности и возможности](#-особенности-и-возможности)
2. [Структура файлов](#-структура-файлов)
3. [Быстрый старт](#-быстрый-старт)
4. [Анатомия модального окна (HTML-разметка)](#-анатомия-модального-окна-html-разметка)
5. [Размеры окон](#-размеры-окон)
6. [Готовые UI-компоненты](#-готовые-ui-компоненты)
   - [Шапка, иконки и бейджи](#шапка-иконки-и-бейджи)
   - [Вкладки (Tabs)](#вкладки-tabs)
   - [Формы и поля ввода (Inputs, Selects, Grids)](#формы-и-поля-ввода)
   - [Переключатели (iOS Switches) и Чекбоксы](#переключатели-и-чекбоксы)
   - [Блоки уведомлений (Alerts)](#блоки-уведомлений-alerts)
   - [Кнопки действий (Buttons)](#кнопки-действий-buttons)
   - [Зона загрузки файлов (Dropzone)](#зона-загрузки-файлов-dropzone)
   - [Индикатор загрузки и Спиннер](#индикатор-загрузки-и-спиннер)
7. [Декларативное управление (Data Attributes)](#-декларативное-управление-data-attributes)
8. [Программное JavaScript API](#-программное-javascript-api)
   - [Управление окнами](#управление-существующими-окнами)
   - [Динамическое создание `Modal.create()`](#динамическое-создание-modalcreate)
9. [Быстрые диалоги (Alert, Confirm, Prompt, Loading)](#-быстрые-диалоги)
10. [Стек модальных окон (Multi-Modal & ESC)](#-стек-модальных-окон-multi-modal)
11. [Поддержка тем (Light, Dark, AMOLED) и i18n](#-поддержка-тем-и-мультиязычности-i18n)
12. [Использование в PHP (`NPModal`)](#-использование-в-php)
13. [Примеры практического применения](#-примеры-практического-применения)

---

## ✨ Особенности и возможности

- **Единый дизайн с редактором NPBlog**: Автоматически использует CSS-переменные темы (`--bg-color`, `--text-color`, `--border-color`), поддерживает темы: **Светлая**, **Тёмная**, **AMOLED** и **Кастомная**.
- **Эффект Glassmorphism**: Полупрозрачные бэкдропы с аппаратным размытием (`backdrop-filter: blur()`).
- **Плавные анимации**: Всплытие (`zoom`), выезд сверху/снизу (`slide-down`, `slide-up`), эффект встряхивания (`shake`) при ошибке валидации.
- **Поддержка стека окон**: Несколько модальных окон могут открываться друг поверх друга. Z-index рассчитывается автоматически, клавиша `ESC` закрывает именно верхнее окно.
- **Доступность (Accessibility)**: Удержание фокуса внутри окна (Tab/Shift+Tab trapping), блокировка прокрутки фона `body.modal-open` без скачков ширины страницы.
- **Два режима работы**:
  1. *Декларативный* — через HTML-разметку и атрибуты `data-modal-open`, `data-modal-close`, `data-modal-tab`.
  2. *Программный* — через JS методы `Modal.open()`, `Modal.alert()`, `Modal.confirm()`, `Modal.prompt()`, `Modal.create()`.
- **Встроенные вкладки (Tabs)**: Готовая система переключения табов без написания лишнего JS.
- **Перетаскивание (Draggable)**: Возможность свободного перемещения окна по экрану за шапку (`draggable: true`).
- **Интеграция с локализацией**: Автоматический перевод через `data-i18n` и глобальный движок `NPBlogI18n`.

---

## 📁 Структура файлов

```
modals/
├── modal.css        # Единые стили, анимации, адаптив и компоненты
├── modal.js         # Движок управления окнами и JS API
├── modal.php        # PHP-хелпер для генерации разметки в шаблонах
├── demo.html        # Интерактивная песочница со всеми примерами
└── README.md        # Эта инструкция
```

---

## 🚀 Быстрый старт

### 1. Подключение стилей и скриптов

Добавьте в `<head>` и перед закрывающим тегом `</body>` страницы:

```html
<!-- Стили модальных окон -->
<link rel="stylesheet" href="modals/modal.css">

<!-- Скрипт фреймворка -->
<script src="modals/modal.js"></script>
```

Либо с помощью PHP:
```php
<?php
require_once __DIR__ . '/modals/modal.php';
echo NPModal::assets();
?>
```

### 2. Простейшее окно в HTML

```html
<!-- Кнопка вызова -->
<button type="button" class="btn" data-modal-open="#myModal">Открыть окно</button>

<!-- Разметка модального окна -->
<div class="modal-overlay" id="myModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-header">
            <h3 class="modal-title">Привет, мир!</h3>
            <button type="button" class="modal-close-btn" data-modal-close>×</button>
        </div>
        <div class="modal-body">
            <p class="modal-text">Это простое модальное окно в стиле NPBlog.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-primary" data-modal-close>Понятно</button>
        </div>
    </div>
</div>
```

---

## 📐 Анатомия модального окна (HTML-разметка)

Стандартное модальное окно состоит из следующих блоков:

```html
<!-- 1. Оверлей (затемнение и размытие фона) -->
<div class="modal-overlay" id="exampleModal" data-size="md">
    
    <!-- 2. Карточка окна -->
    <div class="modal-dialog modal-md">
        
        <!-- 3. Шапка -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon">⚙️</span>
                <div class="modal-titles">
                    <h3 class="modal-title">Заголовок окна</h3>
                    <p class="modal-subtitle">Второстепенный поясняющий текст</p>
                </div>
                <span class="modal-badge">NEW</span>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-fullscreen-btn" title="Развернуть">⛶</button>
                <button type="button" class="modal-close-btn" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- 4. Навигация по вкладкам (опционально) -->
        <div class="modal-tabs">
            <button type="button" class="modal-tab-btn is-active" data-modal-tab="tab-1">Вкладка 1</button>
            <button type="button" class="modal-tab-btn" data-modal-tab="tab-2">Вкладка 2</button>
        </div>

        <!-- 5. Тело окна (скроллируемая область) -->
        <div class="modal-body">
            <!-- Контент вкладки 1 -->
            <div class="modal-tab-pane is-active" id="tab-1">
                <p class="modal-text">Контент первой вкладки...</p>
            </div>

            <!-- Контент вкладки 2 -->
            <div class="modal-tab-pane" id="tab-2">
                <p class="modal-text">Контент второй вкладки...</p>
            </div>
        </div>

        <!-- 6. Подвал с кнопками действий -->
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-ghost" data-modal-close>Отмена</button>
            <button type="button" class="modal-btn modal-btn-primary" id="btnSubmit">Сохранить</button>
        </div>

    </div>
</div>
```

---

## 📏 Размеры окон

Размер задаётся классом на блоке `.modal-dialog` (например, `class="modal-dialog modal-lg"`):

| Класс | Макс. ширина | Назначение |
|---|---|---|
| `.modal-xs` | `360px` | Спиннеры загрузки, короткие подтверждения |
| `.modal-sm` | `440px` | Диалоги Confirm, Prompt, подтверждение удаления |
| `.modal-md` | `560px` | Стандартные формы, загрузка файлов *(по умолчанию)* |
| `.modal-lg` | `760px` | Большие настройки, менеджеры бэкапов, таблицы |
| `.modal-xl` | `980px` | Сложные редакторы, предпросмотр статей |
| `.modal-fullscreen` | `100vw / 100vh` | Полноэкранный режим редактора |
| `.modal-auto` | `fit-content` | Размер по содержимому |

---

## 🎨 Готовые UI-компоненты

### Шапка, иконки и бейджи

```html
<div class="modal-header">
    <div class="modal-header-start">
        <!-- Типы иконок: icon-info, icon-warning, icon-danger, icon-success -->
        <span class="modal-icon icon-danger">🗑️</span>
        <div class="modal-titles">
            <h3 class="modal-title">Удаление элемента</h3>
            <p class="modal-subtitle">Действие нельзя будет отменить</p>
        </div>
        <span class="modal-badge">Внимание</span>
    </div>
    <div class="modal-header-actions">
        <button type="button" class="modal-close-btn" data-modal-close>×</button>
    </div>
</div>
```

### Вкладки (Tabs)

Для активации вкладок добавьте в разметку навигацию `.modal-tabs` и панели `.modal-tab-pane`:

```html
<!-- Навигация -->
<div class="modal-tabs">
    <button type="button" class="modal-tab-btn is-active" data-modal-tab="generalTab">Общие</button>
    <button type="button" class="modal-tab-btn" data-modal-tab="seoTab">SEO</button>
</div>

<!-- Содержимое внутри .modal-body -->
<div class="modal-body">
    <div class="modal-tab-pane is-active" id="generalTab">
        <!-- Поля первой вкладки -->
    </div>
    <div class="modal-tab-pane" id="seoTab">
        <!-- Поля второй вкладки -->
    </div>
</div>
```
*Переключение происходит автоматически скриптом `modal.js` без дополнительного кода.*

---

### Формы и поля ввода

```html
<!-- Обычное поле -->
<div class="modal-form-group">
    <label class="modal-label modal-label-required" for="postTitle">Заголовок статьи</label>
    <input type="text" id="postTitle" name="title" class="modal-input" placeholder="Введите заголовок...">
    <div class="modal-help-text">Короткое и понятное название.</div>
</div>

<!-- Выпадающий список -->
<div class="modal-form-group">
    <label class="modal-label" for="categorySelect">Категория</label>
    <select id="categorySelect" name="category" class="modal-select">
        <option value="news">Новости</option>
        <option value="tutorials">Уроки</option>
    </select>
</div>

<!-- Сетка из 2-х колонок -->
<div class="modal-grid-2">
    <div class="modal-form-group">
        <label class="modal-label">Ширина (px)</label>
        <input type="number" class="modal-input" value="800">
    </div>
    <div class="modal-form-group">
        <label class="modal-label">Высота (px)</label>
        <input type="number" class="modal-input" value="600">
    </div>
</div>

<!-- Многострочное поле -->
<div class="modal-form-group">
    <label class="modal-label" for="postExcerpt">Краткое описание</label>
    <textarea id="postExcerpt" name="excerpt" class="modal-textarea" placeholder="Текст..."></textarea>
</div>
```

---

### Переключатели и Чекбоксы

```html
<!-- Современный iOS-переключатель (Switch) -->
<label class="modal-switch-label">
    <div class="modal-switch-control">
        <input type="checkbox" name="isPublished" checked>
        <span class="modal-switch-slider"></span>
    </div>
    <span>Опубликовать сразу</span>
</label>

<!-- Стандартный чекбокс -->
<label class="modal-checkbox-label">
    <input type="checkbox" class="modal-checkbox" name="pinPost">
    <span>Закрепить статью вверху</span>
</label>
```

---

### Блоки уведомлений (Alerts)

```html
<div class="modal-alert modal-alert-info">
    <span class="modal-alert-icon">ℹ️</span>
    <div class="modal-alert-content">
        <div class="modal-alert-title">Информация</div>
        Все изменения сохраняются в черновиках автоматически.
    </div>
</div>

<div class="modal-alert modal-alert-warning">
    <span class="modal-alert-icon">⚠️</span>
    <div class="modal-alert-content">
        <div class="modal-alert-title">Внимание</div>
        При изменении структуры URL старые ссылки перестанут работать.
    </div>
</div>

<div class="modal-alert modal-alert-danger">
    <span class="modal-alert-icon">🛑</span>
    <div class="modal-alert-content">
        <div class="modal-alert-title">Ошибка</div>
        Не удалось соединиться с базой данных.
    </div>
</div>

<div class="modal-alert modal-alert-success">
    <span class="modal-alert-icon">✅</span>
    <div class="modal-alert-content">
        <div class="modal-alert-title">Успех</div>
        Файл успешно загружен на сервер.
    </div>
</div>
```

---

### Кнопки действий (Buttons)

```html
<div class="modal-footer">
    <!-- Кнопка без рамки (Ghost) -->
    <button type="button" class="modal-btn modal-btn-ghost" data-modal-close>Отмена</button>
    
    <!-- Обычная кнопка -->
    <button type="button" class="modal-btn">Сбросить</button>
    
    <!-- Опасное действие (Красная) -->
    <button type="button" class="modal-btn modal-btn-danger">Удалить</button>
    
    <!-- Главное действие (Основной цвет темы) -->
    <button type="button" class="modal-btn modal-btn-primary" id="btnSave">Сохранить</button>
</div>
```

#### Состояние загрузки на кнопке (`is-loading`)
Добавьте класс `is-loading` на кнопку — текст скроется, и появится анимированный спиннер:
```js
const btn = document.querySelector('#btnSave');
btn.classList.add('is-loading'); // Включаем спиннер
// После завершения запроса:
btn.classList.remove('is-loading'); // Выключаем спиннер
```

---

### Зона загрузки файлов (Dropzone)

```html
<div class="modal-dropzone" onclick="document.getElementById('fileInput').click()">
    <div class="modal-dropzone-icon">📁</div>
    <div style="font-weight: 600; margin-bottom: 4px;">Перетащите файл сюда или нажмите</div>
    <div style="font-size: 12px; opacity: 0.7;">PNG, JPG, WebP (макс. 25 МБ)</div>
    <input type="file" id="fileInput" style="display: none;">
</div>
```

---

## 🏷️ Декларативное управление (Data Attributes)

Вы можете управлять окнами прямо из HTML без написания скриптов:

| Атрибут | Элемент | Описание |
|---|---|---|
| `data-modal-open="#modalId"` | Кнопка / Ссылка | Открывает указанное модальное окно |
| `data-modal-target="modalId"` | Кнопка / Ссылка | Альтернатива для открытия окна по ID |
| `data-modal-close` | Кнопка | Закрывает текущее окно, в котором находится кнопка |
| `data-modal-tab="tabId"` | Кнопка таба | Переключает активную вкладку внутри окна |
| `data-size="lg"` | `.modal-overlay` | Устанавливает размер окна (`xs`, `sm`, `md`, `lg`, `xl`, `fullscreen`) |
| `data-draggable="true"` | `.modal-overlay` | Включает возможность перетаскивания окна за шапку |
| `data-backdrop-close="false"` | `.modal-overlay` | Запрещает закрытие кликом по затемненному фону |
| `data-esc-close="false"` | `.modal-overlay` | Запрещает закрытие клавишей ESC |

---

## ⚡ Программное JavaScript API

### Управление существующими окнами

```javascript
// Открыть окно
Modal.open('#myModal');

// Открыть с передачей параметров
Modal.open('#myModal', {
    backdropClose: false,
    onOpen: (modal) => console.log('Окно открыто!', modal),
    onClose: () => console.log('Окно закрыто')
});

// Закрыть окно
Modal.close('#myModal');

// Переключить (Toggle)
Modal.toggle('#myModal');

// Получить экземпляр окна
const modal = Modal.get('#myModal');

// Закрыть все открытые окна
Modal.closeAll();

// Закрыть верхнее окно из стека
Modal.closeTop();
```

---

### Динамическое создание `Modal.create()`

Создаёт и открывает модальное окно на лету, автоматически очищая DOM после закрытия:

```javascript
const modal = Modal.create({
    size: 'md',                  // 'xs' | 'sm' | 'md' | 'lg' | 'xl' | 'fullscreen'
    title: 'Редактирование автора',
    subtitle: 'Заполните информацию о профиле',
    icon: '👤',
    draggable: true,             // Можно перетаскивать
    fullscreenable: true,        // Кнопка разворачивания на весь экран
    content: `
        <div class="modal-form-group">
            <label class="modal-label modal-label-required">Имя автора</label>
            <input type="text" name="authorName" class="modal-input" value="Иван Иванов">
        </div>
        <div class="modal-form-group">
            <label class="modal-label">Email</label>
            <input type="email" name="authorEmail" class="modal-input" value="ivan@example.com">
        </div>
    `,
    buttons: [
        {
            text: 'Отмена',
            class: 'modal-btn-ghost',
            close: true
        },
        {
            text: 'Сохранить изменения',
            primary: true,
            handler: async (inst) => {
                // Получаем все данные из формы
                const data = inst.getFormData();
                
                if (!data.authorName.trim()) {
                    inst.shake(); // Анимация встряхивания при ошибке
                    return false; // Запрещаем закрытие
                }

                // Включаем оверлей загрузки
                inst.setLoading(true, 'Сохранение...');
                
                await fetch('save_author.php', {
                    method: 'POST',
                    body: JSON.stringify(data)
                });

                inst.setLoading(false);
                return true; // Закрыть окно
            }
        }
    ]
});
```

---

### Методы экземпляра `ModalInstance`

| Метод | Описание |
|---|---|
| `modal.open()` | Открыть окно |
| `modal.close()` | Закрыть окно с проигрыванием анимации |
| `modal.toggle()` | Переключить состояние |
| `modal.destroy()` | Удалить элемент окна из DOM |
| `modal.shake()` | Запустить анимацию встряхивания (для валидации) |
| `modal.setLoading(true, 'Текст')` | Показать/скрыть индикатор загрузки поверх окна |
| `modal.setTitle('Новый заголовок')` | Динамически обновить заголовок |
| `modal.setContent(htmlOrElement)` | Динамически заменить содержимое `.modal-body` |
| `modal.getFormData()` | Получить объект `{ [name]: value }` всех полей ввода окна |
| `modal.querySelector(selector)` | Найти элемент внутри данного окна |
| `modal.switchTab('tabId')` | Программно переключить вкладку |

---

## 💬 Быстрые диалоги

Фреймворк предоставляет набор готовых функций, возвращающих **Promise**:

### 1. `Modal.alert()`

```javascript
await Modal.alert('Настройки успешно применены!');

// Или с расширенными параметрами:
await Modal.alert({
    title: 'Успех',
    message: 'Статья успешно опубликована в блоге!',
    type: 'success', // 'info' | 'warning' | 'danger' | 'success'
    okText: 'Отлично'
});
```

### 2. `Modal.confirm()`

```javascript
const isConfirmed = await Modal.confirm('Вы уверены, что хотите продолжить?');
if (isConfirmed) {
    console.log('Пользователь нажал ОК');
}

// Опасное подтверждение (удаление):
const canDelete = await Modal.confirm({
    title: 'Удалить статью?',
    message: 'Это действие удалит статью и все прикрепленные изображения.',
    danger: true,
    confirmText: 'Удалить навсегда',
    cancelText: 'Отмена'
});
if (canDelete) {
    // Удаляем
}
```

### 3. `Modal.prompt()`

```javascript
const newName = await Modal.prompt({
    title: 'Новая рубрика',
    label: 'Введите название рубрики:',
    placeholder: 'Например: Путешествия',
    required: true,
    requiredMessage: 'Название рубрики обязательно!'
});

if (newName !== null) {
    console.log('Пользователь ввёл:', newName);
}
```

### 4. `Modal.loading()`

```javascript
// Показываем загрузку
const loader = Modal.loading('Загрузка файла на сервер...');

// Обновляем статус в процессе
setTimeout(() => {
    loader.update('Обработка изображения...');
}, 1500);

// Закрываем по завершении
setTimeout(() => {
    loader.close();
}, 3000);
```

---

## 📚 Стек модальных окон (Multi-Modal)

Фреймворк поддерживает открытие вложенных окон:

1. Если из одного окна открывается другое, новое окно получает увеличенный `z-index` и становится поверх текущего.
2. При нажатии клавиши **`ESC`** закрывается только самое верхнее окно.
3. Прокрутка страницы (`body.modal-open`) остаётся заблокированной, пока открыто хотя бы одно окно.

---

## 🌓 Поддержка тем и мультиязычности (i18n)

### Темы оформления
Стили `modal.css` автоматически подстраиваются под тему документа:
- **Светлая тема**: `<html>` без атрибута темы.
- **Тёмная тема**: `<html data-theme="dark">`.
- **AMOLED Тёмная**: `<html data-theme="dark" data-amoled="true">` — глубокий чёрный цвет `#000000` и оптимизированные контрастные рамки `#222222`.

### Мультиязычность (i18n)
Фреймворк полностью интегрирован с языковым движком NPBlog (`lang/i18n.js`):
- Вы можете использовать атрибуты `data-i18n="modals.my_key"` на любых элементах внутри модального окна.
- При открытии окна `Modal.open()` автоматически сканирует содержимое и применяет переводы через `NPBlogI18n.applyTranslations()`.
- В JS API параметры поддерживают ключи `titleI18n`, `subtitleI18n`, `i18n`.

---

## 🧩 Использование в PHP

Класс `NPModal` (`modals/modal.php`) позволяет рендерить модальные окна в серверных шаблонах:

```php
<?php
require_once __DIR__ . '/modals/modal.php';

echo NPModal::render([
    'id' => 'editCategoryModal',
    'size' => 'md',
    'icon' => '📁',
    'title' => 'Редактировать категорию',
    'subtitle' => 'Изменение названия и настроек категории',
    'body' => '
        <div class="modal-form-group">
            <label class="modal-label">Название категории</label>
            <input type="text" name="cat_name" class="modal-input" value="Новости">
        </div>
    ',
    'buttons' => [
        ['text' => 'Отмена', 'close' => true, 'class' => 'modal-btn-ghost'],
        ['text' => 'Сохранить', 'primary' => true, 'type' => 'submit']
    ]
]);
?>
```

---

## 📚 Примеры практического применения

### Пример 1: Модальное окно подтверждения удаления статьи

```html
<button class="btn-delete" onclick="handleDeletePost(123)">Удалить</button>

<script>
async function handleDeletePost(postId) {
    const confirmed = await Modal.confirm({
        title: 'Удалить статью #' + postId + '?',
        message: 'Вы уверены, что хотите удалить эту статью? Это действие нельзя отменить.',
        danger: true,
        confirmText: 'Удалить',
        cancelText: 'Отмена'
    });

    if (confirmed) {
        const loader = Modal.loading('Удаление статьи...');
        const response = await fetch('delete_post.php?id=' + postId, { method: 'POST' });
        loader.close();
        
        if (response.ok) {
            await Modal.alert({ title: 'Успешно', message: 'Статья удалена!', type: 'success' });
            location.reload();
        } else {
            await Modal.alert({ title: 'Ошибка', message: 'Не удалось удалить статью', type: 'danger' });
        }
    }
}
</script>
```

### Пример 2: Диалог создания include-файла

```javascript
async function openSaveIncludeDialog(content) {
    const filename = await Modal.prompt({
        title: 'Сохранить в includes',
        label: 'Название файла (без расширения):',
        placeholder: 'Например: header_banner',
        required: true,
        validator: (val) => {
            if (!/^[a-zA-Z0-9_-]+$/.test(val)) {
                return 'Имя файла может содержать только латинские буквы, цифры, дефис и подчеркивание';
            }
            return null;
        }
    });

    if (filename) {
        const res = await fetch('save_include.php', {
            method: 'POST',
            body: JSON.stringify({ name: filename, content: content })
        });
        if (res.ok) {
            Modal.alert({ title: 'Сохранено', message: `Файл ${filename}.php сохранен!`, type: 'success' });
        }
    }
}
```

---

## 📄 Лицензия и поддержка

Фреймворк разработан специально для проекта **NPBlog Editor**.
Для просмотра живой интерактивной демонстрации откройте файл [`modals/demo.html`](demo.html) в браузере.
