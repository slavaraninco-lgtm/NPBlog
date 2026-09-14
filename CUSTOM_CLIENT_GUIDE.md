# 🛠️ NPBlog: Руководство по разработке кастомных клиентов и сторонних редакторов (REST API v1)

Данное руководство представляет собой исчерпывающий технический справочник и архитектурный стандарт для разработчиков, создающих **кастомные визуальные редакторы, десктопные/мобильные приложения, плагины и Headless-интеграции** для Flat-File CMS **NPBlog**.

Руководство охватывает **все до единой функции и подсистемы** оригинального веб-редактора NPBlog, описывает точные контракты разметки, алгоритмы работы, структуры данных и взаимодействие с REST API, гарантируя 100% функциональную и визуальную совместимость сторонних редакторов с блогом.

Поддерживаемые платформы реализации:
- **Веб-клиенты**: Single Page Applications (React, Vue, Svelte), TipTap, Quill, TinyMCE, ProseMirror.
- **Мобильные приложения**: Android (Kotlin / Jetpack Compose), iOS (Swift / SwiftUI), кроссплатформенные решения (Flutter, React Native).
- **Десктопные клиенты**: Windows, macOS, Linux (C# .NET / WPF, Delphi / VCL / FireMonkey, Electron / Tauri, Qt / C++, Python PyQt).
- **Автоматизация и Headless**: CLI-инструменты, Telegram-боты, интеграция с Obsidian, Notion, VS Code и CI/CD пайплайнами.

---

## 📑 Содержание

1. [Архитектурная модель NPBlog и роль API](#1-архитектурная-модель-npblog-и-роль-api)
2. [Аутентификация, безопасность и управление сессиями](#2-аутентификация-безопасность-и-управление-сессиями)
3. [Критический контракт медиа-файлов и адресации](#3-критический-контракт-медиа-файлов-и-адресации)
4. [Сквозные сценарии работы со статьями (Жизненный цикл)](#4-сквозные-сценарии-работы-со-статьями-жизненный-цикл)
5. [Подсистема надежности: Автосохранения, черновики и версионирование](#5-подсистема-надежности-автосохранения-черновики-и-версионирование)
   - [Локальные черновики (LocalStorage Engine)](#локальные-черновики-localstorage-engine)
   - [Сетевые автосохранения (Server Autosaves)](#сетевые-автосохранения-server-autosaves)
   - [Серверные черновики (Drafts)](#серверные-черновики-drafts)
   - [История версий и откат в один клик (Revisions & Backups)](#история-версий-и-откат-в-один-клик-revisions--backups)
6. [Форматирование текста и типографика](#6-форматирование-текста-и-типографика)
   - [Инлайн-форматы (Жирный, Курсив, Подчеркнутый, Зачеркнутый, Индексы, Код)](#инлайн-форматы)
   - [Блочные структуры (Заголовки H1–H6, Цитаты, Списки, Разделители)](#блочные-структуры)
   - [Выравнивание текста](#выравнивание-текста)
   - [Цвет текста (Палитра и произвольный HEX)](#цвет-текста)
   - [Размер шрифта (Пресеты и произвольный пиксельный размер)](#размер-шрифта)
   - [Семейство шрифтов и пользовательские шрифты (@font-face)](#семейство-шрифтов-и-пользовательские-шрифты-font-face)
7. [Специализированные контент-блоки и интерактивные виджеты](#7-специализированные-контент-блоки-и-интерактивные-виджеты)
   - [7.1. Маркер-текстовыделитель (Highlighter: 5 стилей и палитра)](#71-маркер-текстовыделитель-highlighter)
   - [7.2. Сворачиваемый блок / Спойлер (Details / Summary)](#72-сворачиваемый-блок--спойлер)
   - [7.3. Блоки исходного кода с подсветкой и кликом для редактирования](#73-блоки-исходного-кода)
   - [7.4. Интерактивные CTA-кнопки со ссылками (8 пресетов и 2-Way Sync)](#74-интерактивные-cta-кнопки-со-ссылками)
   - [7.5. Встроенный графический ASCII-редактор (Холст 40x15, Flood Fill, Пресеты)](#75-встроенный-графический-ascii-редактор)
   - [7.6. Табличный процессор (Ресайзеры колонок, заливка ячеек, контекстное меню)](#76-табличный-процессор)
   - [7.7. Якоря и автоматическое оглавление статьи (TOC)](#77-якоря-и-автоматическое-оглавление-статьи-toc)
   - [7.8. Прикрепление файлов и документов (Карточки скачивания)](#78-прикрепление-файлов-и-документов)
   - [7.9. Медиа-контейнеры, сетки фото, галереи, видео и аудио](#79-медиа-контейнеры-сетки-фото-галереи-видео-и-аудио)
8. [Двухрежимный движок, Markdown и очистка перед сохранением](#8-двухрежимный-движок-markdown-и-очистка-перед-сохранением)
   - [Режимы Visual и Code](#режимы-visual-и-code)
   - [Динамический двухсторонний компилятор Markdown <-> HTML](#динамический-двухсторонний-компилятор-markdown---html)
   - [Алгоритм очистки разметки перед сохранением (cleanContentForSave)](#алгоритм-очистки-разметки-перед-сохранением-cleancontentforsave)
9. [Плавная печать (Soft Animated Caret) и адаптация тем](#9-плавная-печать-soft-animated-caret-и-адаптация-тем)
10. [Шаблоны, сниппеты, фоны статей и коллекции смайлов](#10-шаблоны-сниппеты-фоны-статей-и-коллекции-смайлов)
    - [Шаблоны блога и плейсхолдеры](#шаблоны-блога-и-плейсхолдеры)
    - [Текстовые сниппеты (Includes)](#текстовые-сниппеты-includes)
    - [Индивидуальный фон и атмосфера статьи](#индивидуальный-фон-и-атмосфера-статьи)
    - [Коллекции смайлов и стикеров (REST API)](#коллекции-смайлов-и-стикеров-rest-api)
11. [Эталонные реализации клиентов (SDK)](#11-эталонные-реализации-клиентов-sdk)
    - [TypeScript / Web / Electron SDK](#typescript--web--electron-sdk)
    - [Android Kotlin Client (Retrofit + Coroutines)](#android-kotlin-client-retrofit--coroutines)
    - [Delphi / Windows Desktop Client (THTTPClient)](#delphi--windows-desktop-client-thttpclient)
    - [Python CLI / Headless Automation](#python-cli--headless-automation)
12. [Сводная матрица соответствия функций основного редактора (Feature Parity Matrix)](#12-сводная-матрица-соответствия-функций-основного-редактора)

---

## 1. Архитектурная модель NPBlog и роль API

### Концепция файлового хранилища (Flat-File CMS)
CMS NPBlog функционирует **полностью без реляционных СУБД (MySQL, PostgreSQL)**, сохраняя максимальную переносимость, скорость и устойчивость к сбоям:

```
c:\xampp\htdocs\ (Корень блога)
├── data\
│   ├── blog\
│   │   ├── posts-meta.json      <-- Реестр метаданных статей (ID, заголовок, дата, файл)
│   │   ├── post-1.html          <-- Готовый собранный HTML статьи #1 (с шаблоном блога)
│   │   └── post-2.html
│   ├── uploads\                 <-- Загруженные изображения (m_xxxxxx.jpg)
│   ├── files\
│   │   ├── videos\              <-- Локальные видеофайлы (mp4, webm...)
│   │   ├── audio\               <-- Локальные аудиофайлы (mp3, wav, ogg...)
│   │   └── documents\           <-- Документы и архивы (pdf, docx, zip...)
│   ├── smiles\
│   │   ├── kolobok\             <-- Наборы смайлов по папкам
│   │   └── anime\
│   ├── drafts\                  <-- Серверные черновики (.json)
│   ├── fonts\                   <-- Пользовательские шрифты (.ttf, .otf, .woff2)
│   ├── backgrounds.json         <-- Метаданные индивидуальных фонов статей
│   └── api_tokens.json          <-- Хранилище активных Bearer-токенов
├── autosave\                    <-- Временные файлы серверных автосохранений (autosave_1.json)
├── data_backup\                 <-- Инкрементные ревизии статей (1/1-1.html, 1/1-2.html...)
└── api\                         <-- REST API v1
    ├── index.php                <-- Единая точка входа API
    └── src\Controllers\         <-- Контроллеры сущностей блога
```

### Задачи REST API для сторонних редакторов
Стороннему клиенту **не требуется прямой доступ к файловой системе (через FTP, SFTP или SSH)**. Все операции выполняются через REST API:
1. **Атомарность и файловые блокировки (`LOCK_EX`)**: исключает повреждение файлов при конкурентных запросах.
2. **Изоляция контента**: при запросе `GET /api/v1/posts/{id}` API автоматически отсекает шапку, подвал и сайдбары шаблона блога, возвращая в поле `data.content` **только чистый HTML контент статьи** для редактирования.
3. **Автоматическое версионирование**: при каждом вызове `PUT /api/v1/posts/{id}` API инкрементирует ревизию в `data_backup/{id}/` и обновляет историю изменений.
4. **Автоматическая синхронизация зависимостей**: API сам пересобирает метаданные `posts-meta.json` и перегенерирует RSS-ленту (`rss.xml`).
5. **Нормализация медиа-путей**: автоматическая коррекция ссылок на изображения и файлы к каноническому формату `/data/...`.

### Базовый URL API
- Основной rewrite-путь: `http://<ваш-домен>/api/v1`
- Универсальный fallback (без mod_rewrite): `http://<ваш-домен>/api/index.php?_route=v1`

---

## 2. Аутентификация, безопасность и управление сессиями

### Bearer Token авторизация
Кастомные клиенты и редакторы не используют cookies PHP-сессий. Авторизация построена на стандарте **Bearer Token (RFC 6750)**:

```mermaid
sequenceDiagram
    autonumber
    actor Author as Автор
    participant App as Сторонний Редактор
    participant API as NPBlog REST API
    participant DB as data/api_tokens.json

    Author->App: Ввод пароля блога
    App->>API: POST /api/v1/auth/login { password, device_name }
    API->API: Проверка хеша пароля и статуса Lockout
    API->DB: Генерация 64-символьного токена npb_... (SHA-256)
    API-->>App: 200 OK { access_token, token_id, expires_in: 2592000 }
    App->App: Сохранение токена в защищенное хранилище ОС
```

#### Запрос на авторизацию:
```http
POST /api/v1/auth/login HTTP/1.1
Host: your-blog.com
Content-Type: application/json

{
  "password": "пароль_к_блогу",
  "device_name": "NPBlog Desktop Studio (Windows)"
}
```

#### Успешный ответ (`200 OK`):
```json
{
  "success": true,
  "status": 200,
  "message": "Авторизация успешна",
  "data": {
    "access_token": "npb_9bc3d99d8ea3b4c9e1c81bc0d11505c733fac79dfeb78680911f589b779b0e39",
    "token_type": "Bearer",
    "token_id": "tok_5588d87a8c890fba",
    "device_name": "NPBlog Desktop Studio (Windows)",
    "expires_at": 1791735875,
    "expires_in": 2592000,
    "created_at": 1789143875
  }
}
```
Срок жизни токена — **30 дней** (`2592000` секунд). При каждом валидном запросе сервер автоматически продлевает сессию.

### Передача токена в запросах
1. **Основной заголовок (RFC 6750)**:
   ```http
   Authorization: Bearer npb_9bc3d99d8ea3b4c9e1c81bc0d11505c733fac79dfeb78680911f589b779b0e39
   ```
2. **Альтернативный заголовок**:
   ```http
   X-API-Token: npb_9bc3d99d8ea3b4c9e1c81bc0d11505c733fac79dfeb78680911f589b779b0e39
   ```
3. **Query-параметр** (для предпросмотра защищенных страниц в WebView / нативных компонентах):
   ```http
   GET /api/v1/posts/15/preview?api_token=npb_9bc3d99d8ea3...
   ```

### Безопасное хранение токена на клиенте
> [!CAUTION]
> Никогда не сохраняйте `access_token` в открытых `.ini`, `.json` или незашифрованных базах данных!
- **Android**: `EncryptedSharedPreferences` / `Android Keystore`.
- **iOS / macOS**: `Keychain Services` (`kSecClassGenericPassword`).
- **Windows Desktop**: `Windows Data Protection API (DPAPI)` (`CryptProtectData`) или `Windows.Security.Credentials.PasswordVault`.
- **Linux Desktop**: `Secret Service API` (`libsecret` / `KWallet`).
- **Electron**: `electron.safeStorage` API.

### Защита от перебора паролей (Lockout)
После 3 неверных попыток ввода пароля подряд IP блокируется на **15 минут** (900 секунд). Сервер возвращает `429 Too Many Requests`:
```json
{
  "success": false,
  "status": 429,
  "error": {
    "code": "account_locked",
    "message": "Превышен лимит неверных попыток входа. IP-адрес временно заблокирован.",
    "details": {
      "lockout_time_remaining": 847,
      "lockout_duration": 900
    }
  }
}
```
**Требование к интерфейсу клиента**: при получении ошибки `429` клиент обязан заблокировать кнопку отправки пароля и отобразить обратный таймер обратного отсчета на основе поля `lockout_time_remaining`.

---

## 3. Критический контракт медиа-файлов и адресации

> [!IMPORTANT]
> **ГЛАВНОЕ ПРАВИЛО АДРЕСАЦИИ: ПАПКА `/data/`, А НЕ `/api/`**
> 1. Все статические файлы (изображения, видео, аудио, документы, смайлы, шрифты) физически раздаются веб-сервером **напрямую из папки `/data/`**.
> 2. Эндпоинты `/api/v1/media/...` предназначены **исключительно для загрузки и управления** файлами.
> 3. В разметку HTML-статьи должны вставляться **ТОЛЬКО ссылки на `/data/...`**:
>    - ✅ **Правильно:** `<img src="/data/uploads/m_6aa42c.jpg" alt="Фото" />`
>    - ❌ **ОШИБКА:** `<img src="/api/data/uploads/m_6aa42c.jpg" />`
>    - ❌ **ОШИБКА:** `<img src="/api/v1/media/uploads/m_6aa42c.jpg" />`

### Относительные vs Абсолютные пути
При загрузке любого файла (`POST /api/v1/media/upload`) API возвращает объект:
```json
{
  "file_name": "m_7fa21b.jpg",
  "url": "/data/uploads/m_7fa21b.jpg",
  "absolute_url": "http://your-blog.com/data/uploads/m_7fa21b.jpg",
  "data_path": "data/uploads/m_7fa21b.jpg",
  "size": 312540,
  "type": "image"
}
```
- **Поле `url` (`/data/uploads/...`)** — вставляется в HTML разметку статьи (`src="/data/uploads/..."`). Обеспечивает независимость контента при смене домена, IP или переходе с HTTP на HTTPS.
- **Поле `absolute_url` (`http://.../data/uploads/...`)** — используется внутри приложения для предпросмотра изображения в нативных компонентах (`Image` во Flutter, `AsyncImage` в SwiftUI, `Glide` в Android, `TImage` в Delphi).

---

## 4. Сквозные сценарии работы со статьями (Жизненный цикл)

### 1. Список статей с поиском и пагинацией
```http
GET /api/v1/posts?page=1&limit=20&sort=id_desc&q=новости HTTP/1.1
Authorization: Bearer <access_token>
```
- Параметры: `page` (от 1), `limit` (1–100, по умолч. 20), `sort` (`id_desc`, `id_asc`, `date_desc`, `date_asc`), `q` (поисковая фраза по заголовку или точный ID).

### 2. Загрузка статьи в редактор
```http
GET /api/v1/posts/15 HTTP/1.1
Authorization: Bearer <access_token>
```
- **Ответ**: поле `data.content` содержит **только очищенный контент статьи**. Сторонний редактор загружает этот HTML напрямую в свою рабочую область (WYSIWYG/Markdown).

### 3. Создание новой статьи
```http
POST /api/v1/posts HTTP/1.1
Authorization: Bearer <access_token>
Content-Type: application/json

{
  "title": "Заголовок новой публикации",
  "content": "<p>Основной текст статьи...</p>",
  "date": "12.09.2026 18:00"
}
```
- Автоматически инкрементирует ID, генерирует HTML на основе активного шаблона блога, создает начальную ревизию в `data_backup/` и обновляет `rss.xml`.

### 4. Сохранение изменений статьи
```http
PUT /api/v1/posts/15 HTTP/1.1
Authorization: Bearer <access_token>
Content-Type: application/json

{
  "title": "Обновленный заголовок",
  "content": "<p>Отредактированный текст...</p>",
  "date": "12.09.2026 18:15"
}
```
- Создает новую инкрементную версию резервной копии (например `15-2.html`), перезаписывает файл статьи и синхронизирует метаданные.

### 5. Удаление статьи
```http
DELETE /api/v1/posts/15?renumber=false HTTP/1.1
Authorization: Bearer <access_token>
```
- Если параметр `renumber=true`, после удаления сервер выполнит сквозную перенумерацию всех оставшихся статей от 1 до N.

---

## 5. Подсистема надежности: Автосохранения, черновики и версионирование

Редактор NPBlog реализует **трехуровневую систему защиты от потери данных**:

### Локальные черновики (LocalStorage Engine)
Защищает автора от внезапного закрытия вкладки, краша браузера или обрыва связи до того, как сработает сетевой таймер.

1. **Ключ хранилища**:
   - Для редактируемой статьи: `npblog_draft_post_{postId}`
   - Для новой статьи: `npblog_draft_new`
2. **Структура сохраняемого объекта**:
   ```json
   {
     "id": 15,
     "title": "Черновой заголовок",
     "content": "<p>Текст в редакторе...</p>",
     "timestamp": 1789210000,
     "savedAt": "12.09.2026 18:30:45"
   }
   ```
3. **Отслеживание изменений (`isEditorDirty`)**:
   - При каждом вводе символа или форматировании выставляется флаг `isEditorDirty = true`.
   - Запускается локальный таймер с задержкой **3 секунды** после последнего нажатия клавиши.
4. **Восстановление при запуске (`checkLocalDraftOnStartup`)**:
   - При открытии статьи клиент считывает локальный черновик.
   - Если его `timestamp` новее времени открытия статьи, пользователю показывается уведомление (Toast):
     > 💡 *"Обнаружена несохраненная локальная копия от 18:30. [Восстановить] [Удалить]"*
5. **Очистка**: при нажатии кнопки «Сохранить» / «Опубликовать» локальный черновик мгновенно стирается (`localStorage.removeItem`).

### Сетевые автосохранения (Server Autosaves)
- **Фоновый Debounce-таймер**: срабатывает каждые 15–30 секунд при наличии несохраненных изменений.
- **Сохранение на сервер**:
  ```http
  POST /api/v1/autosaves HTTP/1.1
  Content-Type: application/json
  
  {
    "post_id": "15",
    "title": "Черновой заголовок",
    "content": "<p>Текст статьи...</p>"
  }
  ```
- **Проверка при загрузке**: `GET /api/v1/autosaves/15`.
- **Очистка после успешной публикации**: `DELETE /api/v1/autosaves/15`.
- **Менеджер автосохранений**: `GET /api/v1/autosaves` (список всех автосохранений) и `DELETE /api/v1/autosaves` (очистка всех).

### Серверные черновики (Drafts)
Полноценные именованные материалы, которые автор сохраняет осознанно, не публикуя в публичный блог:
- `POST /api/v1/drafts` — `{"title": "Черновик статьи", "content": "<p>...</p>"}`
- `GET /api/v1/drafts` — список черновиков
- `GET /api/v1/drafts/{filename}` — чтение черновика
- `DELETE /api/v1/drafts/{filename}` — удаление черновика

### История версий и откат в один клик (Revisions & Backups)
При каждом сохранении (`PUT /api/v1/posts/{id}`) сервер автоматически создает снапшот статьи в папке `data_backup/{id}/{id}-{version}.html`:
- `GET /api/v1/backups/{postId}` — список всех доступных версий статьи с датами создания.
- `GET /api/v1/backups/{postId}/{backupNumber}` — получение содержимого конкретной версии для сравнения (Diff) или предпросмотра.
- `POST /api/v1/backups/{postId}/{backupNumber}/restore` — мгновенный откат статьи к выбранной версии на сервере без перезаписи последующих версий.
- `DELETE /api/v1/backups/{postId}/{backupNumber}` — удаление устаревшей ревизии.

---

## 6. Форматирование текста и типографика

### Инлайн-форматы
Кастомный редактор должен генерировать следующие стандартные теги:
| Операция | HTML-тег | Горячая клавиша | Описание |
|---|---|---|---|
| **Жирный** | `<b>...</b>` или `<strong>...</strong>` | `Ctrl+B` / `Cmd+B` | Полужирное начертание |
| *Курсив* | `<i>...</i>` или `<em>...</em>` | `Ctrl+I` / `Cmd+I` | Курсивное начертание |
| <u>Подчеркнутый</u> | `<u>...</u>` | `Ctrl+U` / `Cmd+U` | Нижнее подчеркивание |
| ~~Зачеркнутый~~ | `<s>...</s>` или `<del>...</del>` | `Ctrl+Shift+X` | Зачеркивание текста |
| Верхний индекс | `<sup>...</sup>` | — | Математические степени (X<sup>2</sup>) |
| Нижний индекс | `<sub>...</sub>` | — | Химические формулы (H<sub>2</sub>O) |
| Инлайн-код | `<code>...</code>` | `Ctrl+` ` | Моноширинный фрагмент кода |

### Блочные структуры
- Заголовки: `<h1>`, `<h2>`, `<h3>` (в основном редакторе кнопка «Подзаголовок» вставляет `<h2>`).
- Цитата: `<blockquote><p>Текст цитаты...</p></blockquote>`.
- Маркированный список: `<ul><li>Пункт 1</li><li>Пункт 2</li></ul>`.
- Нумерованный список: `<ol><li>Шаг 1</li><li>Шаг 2</li></ol>`.
- Горизонтальный разделитель: `<hr>`.

### Выравнивание текста
Выравнивание блоков реализуется через CSS-свойство `text-align` непосредственно на абзацах или блоках:
- По левому краю: `<p style="text-align: left;">...</p>`
- По центру: `<p style="text-align: center;">...</p>`
- По правому краю: `<p style="text-align: right;">...</p>`
- По ширине: `<p style="text-align: justify;">...</p>`

### Цвет текста
- Разметка: `<span style="color: #hex;">Текст</span>`.
- Встроенная палитра основного редактора содержит 30 гармоничных цветов:
  `#000000`, `#333333`, `#666666`, `#999999`, `#cccccc`, `#ffffff`, `#ff0000`, `#ff6600`, `#ff9900`, `#ffcc00`, `#99cc00`, `#00cc00`, `#00cccc`, `#0066ff`, `#0000ff`, `#6600cc`, `#9900cc`, `#cc0099`, `#ff0066`, `#8b4513`, `#a0522d`, `#cd853f`, `#deb887`, `#ff69b4`, `#ffc0cb`, `#add8e6`, `#98fb98`, `#f0e68c`, `#ffd700`, `#ff6347`.
- Также поддерживается ввод любого произвольного цвета через Color Picker.

### Размер шрифта
- Разметка: `<span style="font-size: 16px;">Текст</span>`.
- Стандартные пресеты в интерфейсе: `12px`, `14px`, `16px`, `18px`, `20px`, `24px`, `28px`, `32px`.
- Произвольный ввод: допустимый диапазон от `8px` до `72px`.

### Семейство шрифтов и пользовательские шрифты (@font-face)
- Разметка: `<span style="font-family: 'Open Sans', sans-serif;">Текст</span>`.
- Системные шрифты: `Arial`, `Times New Roman`, `Open Sans`, `Verdana`, `Helvetica`, `Georgia`, `PT Sans`, `Comic Sans MS`.
- **Пользовательские шрифты**:
  1. Клиент запрашивает установленные шрифты: `GET /api/v1/media/fonts`.
  2. Сервер возвращает массив шрифтов и готовый блок CSS-правил `@font-face`:
     ```css
     @font-face {
       font-family: 'Oswald-Bold';
       src: url('/data/fonts/Oswald-Bold.ttf') format('truetype');
     }
     ```
  3. Клиент внедряет этот CSS в свой компонент отображения / WebView.
  4. Загрузка нового шрифта: `POST /api/v1/media/upload` (тип `font`, расширения `.ttf`, `.otf`, `.woff`, `.woff2`). Сервер автоматически сохраняет оригинальное имя гарнитуры.
  5. Удаление шрифта: `DELETE /api/v1/media/fonts/{filename}`.

---

## 7. Специализированные контент-блоки и интерактивные виджеты

### 7.1. Маркер-текстовыделитель (Highlighter)
Позволяет выделять ключевые фразы стилизованным маркером.

#### Спецификация HTML:
```html
<mark data-marker-color="yellow" data-marker-style="straight">Выделенный фрагмент</mark>
```

#### Доступные стили маркера (`data-marker-style`):
1. `straight` — классическая сплошная подсветка фона полупрозрачным цветом.
2. `wave` — декоративное волнистое цветное подчеркивание.
3. `dashed` — пунктирное подчеркивание акцентным цветом.
4. `double` — двойная цветная черта под текстом.
5. `gradient` — градиентная полупрозрачная заливка с переливом цвета.

#### Цветовая палитра маркера (`data-marker-color`):
- `yellow` (`#ffeb3b`) — желтый
- `green` (`#4caf50`) — зеленый
- `blue` (`#2196f3`) — синий
- `orange` (`#ff9800`) — оранжевый
- `pink` (`#e91e63`) — розовый
- `purple` (`#9c27b0`) — фиолетовый

При открытии диалога маркера клиент запоминает текущее выделение текста (`savedMarkerRange`). Если выделения нет, выводится предупреждение.

---

### 7.2. Сворачиваемый блок / Спойлер
Используется для объемных пояснений, спойлеров, скрытых цитат и аккордеонов.

#### Спецификация HTML:
```html
<details class="spoiler-block">
  <summary class="spoiler-title">Нажмите, чтобы развернуть подробности</summary>
  <div class="spoiler-content">
    <p>Скрытый текст, который разворачивается при клике на заголовок.</p>
  </div>
</details>
```
- Тег `<details>` нативно поддерживается всеми современными браузерами и мобильными WebView без применения внешних библиотек.
- При вставке в редакторе спойлер оборачивается в блок с кнопкой закрытия или отображается в раскрытом виде для редактирования содержимого.

---

### 7.3. Блоки исходного кода
Профессиональное форматирование фрагментов программного кода с указанием языка и кнопкой быстрого копирования.

#### Спецификация HTML:
```html
<div class="blog-image-align-wrap" style="text-align:left; display:block; margin:14px 0; width:100%; clear:both; position:relative;">
  <div class="blog-image-wrap" style="position:relative; display:inline-block; max-width:100%;" data-media-type="pre">
    <pre class="code-block" data-language="javascript" contenteditable="false"><code>const greet = (name) =&gt; {
    console.log(`Hello, ${name}!`);
};</code></pre>
  </div>
</div>
```

#### Правила генерации:
1. Обязательное экранирование символов: `&` -> `&amp;`, `<` -> `&lt;`, `>` -> `&gt;`.
2. Атрибут `data-language` определяет язык: `javascript`, `php`, `python`, `html`, `css`, `sql`, `bash`, `json`, `csharp`, `cpp`, `java`.
3. **Механика повторного редактирования (`openEditCodeBlockDialog`)**:
   - При клике на существующий блок `<pre class="code-block">` в визуальном редакторе клиент считывает атрибут `data-language` и текстовое содержимое `codeBlock.textContent`.
   - Открывается диалог редактирования кода с предзаполненными полями.
   - После изменения текста обновляется существующий DOM-элемент без сброса курсора и без дублирования обертки.

---

### 7.4. Интерактивные CTA-кнопки со ссылками
Мощный визуальный конструктор кнопок призыва к действию (CTA), ведущих на внешние сайты или разделы блога.

#### Спецификация HTML:
```html
<div class="blog-image-align-wrap" style="text-align:center; display:block; margin:14px 0; width:100%; clear:both; position:relative;">
  <div class="blog-image-wrap" style="position:relative; display:inline-block; max-width:100%; text-align:center;" data-media-type="button">
    <a href="https://github.com" class="custom-blog-btn" target="_blank" style="background:#2563eb; color:#ffffff; padding:12px 24px; border-radius:8px; font-size:16px; font-weight:600; text-decoration:none; display:inline-block; box-shadow:0 4px 14px rgba(37,99,235,0.39); transition:all 0.2s ease;">
      <span>🚀</span> Перейти к репозиторию
    </a>
  </div>
</div>
```

#### Встроенные дизайн-пресеты:
1. `editor` / `modern` — классический корпоративный синий (`#2563eb`).
2. `gradient-sunset` — стильный линейный градиент `linear-gradient(135deg, #ec4899, #8b5cf6)`.
3. `glass` — эффект матового стекла `background:rgba(255,255,255,0.15); backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,0.3)`.
4. `retro` — неоновый ретрофутуризм / киберпанк с контрастной рамкой и жесткой тенью.
5. `neon` — яркое неоновое свечение `box-shadow: 0 0 15px #06b6d4`.
6. `minimal` — прозрачный фон, тонкая рамка (`border: 2px solid currentColor`).
7. `success` — изумрудно-зеленый градиент для успешных конверсий (`#10b981`).
8. `danger` — акцентный кораллово-красный цвет предупреждения (`#ef4444`).

#### Двухсторонняя синхронизация (2-Way Sync):
- Вкладка **«Конструктор»**: поля ввода URL, текста, цвета фона, цвета текста, радиуса скругления (`border-radius`), отступов (`padding`), размера шрифта.
- Вкладка **«Сырой код»**: поле прямого редактирования HTML и инлайн-CSS. Любые правки кода мгновенно отражаются в интерактивном предпросмотре и обновляют селекторы конструктора.
- **Повторное редактирование**: клик по кнопке в редакторе открывает диалог `openEditCustomButtonDialog(customBtn)`, парсит стили регулярными выражениями и восстанавливает все ползунки и настройки.

---

### 7.5. Встроенный графический ASCII-редактор
Уникальный инструмент NPBlog для рисования текстовой псевдографики, схем и блок-схем прямо внутри редактора.

#### Спецификация HTML:
```html
<div class="blog-image-align-wrap" style="text-align:center;" data-image-id="ascii-1789214">
  <div class="blog-image-wrap">
    <div class="blog-ascii-wrap" data-ascii-width="40" data-ascii-height="15" data-ascii-grid="[&quot;█&quot;,&quot;█&quot;,...]">
      <pre class="blog-ascii-art">████████████████████████████████████████
█                                      █
█         NPBlog ASCII ENGINE          █
█                                      █
████████████████████████████████████████</pre>
    </div>
  </div>
</div>
```

#### Архитектура и алгоритмы ASCII-движка:
1. **Сетка ячеек**: по умолчанию 40 столбцов на 15 строк (настраивается от 5×5 до 120×60).
2. **Инструменты**:
   - `draw` (Карандаш) — при клике или зажатом перетаскивании мыши записывает активный символ в ячейку.
   - `erase` (Ластик) — очищает ячейку, записывая пробел `' '`.
   - `fill` (Заливка Flood Fill) — рекурсивный 4-направленный алгоритм заливки области:
     ```javascript
     function floodFill(startX, startY, targetChar, replacementChar) {
         if (targetChar === replacementChar) return;
         const queue = [[startX, startY]];
         while (queue.length > 0) {
             const [x, y] = queue.shift();
             const cell = getCell(x, y);
             if (cell && cell.textContent === targetChar) {
                 cell.textContent = replacementChar;
                 queue.push([x + 1, y], [x - 1, y], [x, y + 1], [x, y - 1]);
             }
         }
     }
     ```
3. **Палитры символов**:
   - Блоки: `█`, `▓`, `▒`, `░`, `▄`, `▀`, `▌`, `▐`, `■`
   - Одинарные рамки: `┌`, `─`, `┐`, `│`, `└`, `┘`, `├`, `┤`, `┬`, `┴`, `┼`
   - Двойные рамки: `╔`, `═`, `╗`, `║`, `╚`, `╝`, `╠`, `╣`, `╦`, `╩`, `╬`
   - Стрелки: `▲`, `▼`, `◄`, `►`, `↑`, `↓`, `←`, `→`, `↔`, `↕`
   - Символы: `★`, `☆`, `♥`, `♦`, `♣`, `♠`, `☺`, `☻`, `☼`, `♪`, `♫`
   - Произвольный ввод: автор может ввести любой Unicode-символ.
4. **Внутренняя история**: локальный стек `asciiHistory` (до 30 состояний) с кнопкой Undo внутри модального окна.
5. **Повторное редактирование**: клик по блоку `.blog-ascii-wrap` в редакторе считывает `data-ascii-width`, `data-ascii-height` и JSON-массив ячеек из `data-ascii-grid`, открывая холст для продолжения рисования.

---

### 7.6. Табличный процессор
Полнофункциональный редактор таблиц с поддержкой ресайзеров колонок и контекстного меню.

#### Спецификация HTML:
```html
<table class="blog-table">
  <thead>
    <tr>
      <th style="width: 25%;">Параметр</th>
      <th style="width: 25%;">Значение</th>
      <th style="width: 50%;">Примечание</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Версия</td>
      <td>2.287</td>
      <td style="background-color: #dcfce7; color: #14532d;">Стабильный релиз</td>
    </tr>
  </tbody>
</table>
```

#### Интерактивные функции таблицы:
1. **Динамические ресайзеры колонок (`addColumnResizers`)**:
   - На правые границы ячеек `<th>` динамически монтируются элементы `.column-resizer`.
   - При перетаскивании мышью вычисляется изменение ширины столбца в процентах, и пропорционально обновляются все остальные столбцы.
2. **Контекстное меню ячейки**:
   - Добавить строку выше / ниже (`addTableRow`)
   - Удалить текущую строку (`deleteTableRow`)
   - Добавить столбец слева / справа (`addTableColumn`) с авто-пересчетом ширины всех колонок
   - Удалить текущий столбец (`deleteTableColumn`)
   - Удалить таблицу (`deleteTable`)
3. **Заливка фона ячейки (`setCellColor`)**:
   - Устанавливает инлайн-стиль `style="background-color: #hex;"`.
   - **Алгоритм контраста цвета текста**:
     ```javascript
     function getContrastTextColor(hex) {
         let clean = hex.replace('#', '');
         if (clean.length === 3) clean = clean.split('').map(c => c + c).join('');
         const r = parseInt(clean.substring(0, 2), 16) || 0;
         const g = parseInt(clean.substring(2, 4), 16) || 0;
         const b = parseInt(clean.substring(4, 6), 16) || 0;
         const brightness = (r * 299 + g * 587 + b * 114) / 1000;
         return (brightness >= 128) ? '#000000' : '#ffffff';
     }
     ```

---

### 7.7. Якоря и автоматическое оглавление статьи (TOC)
Позволяет размечать длинные статьи якорными ссылками и генерировать кликабельное содержание.

#### Спецификация HTML:
```html
<!-- Якорь на выделенном тексте -->
<span id="1" data-npblog-anchor="true">Раздел первый: Введение</span>

<!-- Якорь-маркер без текста -->
<span id="2" data-npblog-anchor="true">⚓</span>
```

#### Механизм работы:
1. **Добавление якоря (`addAnchor`)**:
   - Вычисляет следующий свободный числовой ID (`1`, `2`, `3`...).
   - Если текст выделен — оборачивает его в `<span id="{id}" data-npblog-anchor="true">`.
   - Если курсор пуст — вставляет символ `⚓`.
   - Повторное выделение существующего якоря снимает обертку (unwrap).
2. **Меню содержания (Table of Contents)**:
   - Клиент сканирует документ на наличие элементов с атрибутом `id`.
   - Формирует список пунктов с их названиями.
   - Позволяет вставить гиперссылку на любой якорь: `<a href="#1" class="blog-toc-link">Перейти к введению</a>`.

---

### 7.8. Прикрепление файлов и документов
Позволяет загружать и встраивать любые файлы (PDF, DOCX, XLSX, ZIP, RAR, TXT, PPTX).

#### Спецификация HTML:
```html
<div class="blog-image-align-wrap" style="text-align:left;">
  <div class="blog-image-wrap" style="display:inline-block;">
    <a href="/data/files/documents/project_spec.pdf" class="blog-file-button" target="_blank" download="project_spec.pdf">
      <div class="blog-file-icon">📥</div>
      <div class="blog-file-info">
        <div class="blog-file-name">project_spec.pdf</div>
        <div class="blog-file-size">2.4 МБ</div>
      </div>
    </a>
  </div>
</div>
```
- Загрузка файлов: `POST /api/v1/media/upload` (тип `document`).
- Список документов: `GET /api/v1/media?type=documents`.
- Удаление: `DELETE /api/v1/media` (`{"filename": "...", "type": "document"}`).
- Опция вставки: возможность вставить либо как стилизованную карточку `.blog-file-button`, либо как простую текстовую ссылку `<a href="..." download="...">`.

---

### 7.9. Медиа-контейнеры, сетки фото, галереи, видео и аудио

#### Обертка медиа-элементов (`wrapMediaWithControls`)
Каждое изображение, видео, аудио или виджет оборачивается в двухслойный контейнер:
```html
<div class="blog-image-align-wrap" style="text-align:left; display:block; margin:14px 0; width:100%; clear:both; position:relative;">
  <div class="blog-image-wrap" style="position:relative; display:inline-block; max-width:100%; text-align:center;" data-media-type="image">
    <img src="/data/uploads/m_6aa42c.jpg" alt="Описание" class="blog-image" style="width:100%; height:auto;" />
    <div class="blog-image-caption">Подпись к изображению</div>
  </div>
</div>
```

#### Интерактивный оверлей масштабирования (Resize Handles):
- При выборе картинки в визуальном редакторе поверх нее отображается рамка с угловыми маркерами (`nw`, `ne`, `sw`, `se`).
- Панель выравнивания позволяет переключать стили родительского `.blog-image-align-wrap`:
  - `text-align: left`
  - `text-align: center`
  - `text-align: right`
  - Обтекание текстом слева: `float: left; margin: 0 16px 14px 0;`
  - Обтекание текстом справа: `float: right; margin: 0 0 14px 16px;`

#### Сетки изображений (Image Grids):
```html
<div class="image-grid" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:10px;">
  <img src="/data/uploads/m_1.jpg" style="width:100%; height:auto;" />
  <img src="/data/uploads/m_2.jpg" style="width:100%; height:auto;" />
  <img src="/data/uploads/m_3.jpg" style="width:100%; height:auto;" />
</div>
```

#### Видео и Аудио:
1. **Локальное видео**: `<video controls src="/data/files/videos/clip.mp4" style="max-width:100%;"></video>`
2. **Локальное аудио**: `<audio controls src="/data/files/audio/podcast.mp3" style="width:100%;"></audio>`
3. **YouTube embed**:
   - Клиент извлекает video ID из ссылок `youtube.com/watch?v=ID`, `youtu.be/ID`, `youtube.com/shorts/ID`.
   - Вставляет отзывчивый контейнер 16:9:
     ```html
     <div class="blog-video-wrap" style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden;">
       <iframe src="https://www.youtube.com/embed/{id}" style="position:absolute; top:0; left:0; width:100%; height:100%;" frameborder="0" allowfullscreen></iframe>
     </div>
     ```
4. **Vimeo embed**: извлечение ID и вставка `https://player.vimeo.com/video/{id}`.

---

## 8. Двухрежимный движок, Markdown и очистка перед сохранением

### Режимы Visual и Code
Основной редактор поддерживает мгновенное переключение между:
- **Visual Mode**: редактируемый WYSIWYG контейнер (`contenteditable="true"`).
- **Code Mode**: поле ввода сырого HTML или Markdown (`<textarea>`).

### Динамический двухсторонний компилятор Markdown <-> HTML
Редактор оснащен встроенным компилятором, позволяющим авторам работать в чистом Markdown:
1. **Markdown -> HTML (`parseMarkdownToHtml`)**:
   - ```` ```lang ... ``` ```` -> `<pre class="code-block" data-language="lang"><code>...</code></pre>`
   - `` `код` `` -> `<code>...</code>`
   - `# Заголовок` -> `<h1>Заголовок</h1>`, `## Подзаголовок` -> `<h2>Подзаголовок</h2>`
   - `> Цитата` -> `<blockquote>Цитата</blockquote>`
   - `* Пункт` / `- Пункт` -> `<ul><li>Пункт</li></ul>`
   - `1. Пункт` -> `<ol><li>Пункт</li></ol>`
   - `| Заголовок 1 | Заголовок 2 |` -> `<table>...</table>`
   - `**жирный**` -> `<b>жирный</b>`, `*курсив*` -> `<i>курсив</i>`, `~~зачеркнутый~~` -> `<s>...</s>`
   - `[текст](url)` -> `<a href="url">текст</a>`
   - `![alt](url)` -> `<img src="url" alt="alt">`
2. **HTML -> Markdown (`convertHtmlToMarkdown`)**:
   - Рекурсивно трансформирует DOM-узлы обратно в канонический Markdown.

### Алгоритм очистки разметки перед сохранением (`cleanContentForSave`)
> [!IMPORTANT]
> Сторонний редактор **обязан очищать контент** перед отправкой на сервер (`POST / PUT /posts`):
1. **Удаление служебных элементов интерфейса**:
   - Селекторы: `.image-toolbar`, `.image-align-dropdown`, `.image-size-indicator`, `.image-resize-handle`, `.blog-image-overlay`, `.column-resizer`, `#customCaret`.
2. **Удаление runtime-атрибутов**:
   - Атрибуты: `data-image-id`, `data-media-id`, `data-media-type`, `data-resizers-added`.
3. **Снятие служебных флагов**:
   - Удаление `contenteditable` со всех вложенных блоков и ячеек таблиц.
   - Удаление классов `.selected`.
4. **Очистка DOM-артефактов**:
   - Удаление пустых тегов: `<b></b>`, `<i></i>`, `<span></span>`, пустых абзацев `<p></p>`.
   - Удаление нулевых пробелов (`\u200B`, `\uFEFF`, `&nbsp;` без текста).
5. **Удаление пустых хвостовых строк**:
   - Удаление пустых `<p><br></p>` в самом конце документа.

---

## 9. Плавная печать (Soft Animated Caret) и адаптация тем

### Алгоритм плавного курсора (`smooth-typing`)
Создает эффект премиального редактора с плавно плывущим курсором:
1. Создается плавающий оверлей `<div id="customCaret"></div>`.
2. При каждом изменении позиции каретки (`selectionchange`) вычисляются абсолютные экранные координаты:
   ```javascript
   const sel = window.getSelection();
   const range = sel.getRangeAt(0);
   const rect = range.getBoundingClientRect();
   ```
3. Координаты плавно присваиваются `#customCaret`:
   ```css
   #customCaret {
     position: fixed;
     width: 2px;
     background-color: var(--accent-color, #3b82f6);
     pointer-events: none;
     transition: left 0.08s cubic-bezier(0.2, 0, 0, 1), top 0.08s cubic-bezier(0.2, 0, 0, 1), height 0.08s ease;
     z-index: 1000;
   }
   ```
4. Таймер мерцания: при непрерывном вводе курсор перестает мигать. Если автор остановился более чем на 500 мс — запускается плавная CSS-анимация `blink`.

### Темы оформления
- `data-theme="dark"` — классическая темная тема.
- `data-theme="light"` — светлая тема.
- `data-amoled="true"` — ультра-черная тема `#000000` для OLED-экранов смартфонов.
- `custom` — пользовательская CSS-тема из `data/custom_editor_theme.css`.

---

## 10. Шаблоны, сниппеты, фоны статей и коллекции смайлов

### Шаблоны блога и плейсхолдеры
HTML-статьи в NPBlog собираются на основе активного шаблона.
- Получение списка: `GET /api/v1/templates`
- Код шаблона: `GET /api/v1/templates/{name}`
- Сохранение шаблона: `POST /api/v1/templates` (`{"name": "dark_modern", "code": "..."}`)
- Применение шаблона: `POST /api/v1/templates/apply`
  - К одной статье: `{"template": "dark_modern", "post_id": 15}`
  - Ко всему блогу: `{"template": "dark_modern"}`
- Список плейсхолдеров, подставляемых сервером при компиляции:
  - `{{TITLE}}` — заголовок публикации
  - `{{CONTENT}}` — HTML-контент статьи
  - `{{DATE}}` — дата и время публикации
  - `{{POST_ID}}` — числовой номер статьи
  - `{{CUSTOM_FONTS}}` — CSS правила `@font-face`
  - `{{META_TAGS}}` — SEO мета-теги (Open Graph, Twitter Cards)
  - `{{NAV}}` — навигация блога

### Текстовые сниппеты (Includes)
Повторяющиеся блоки (промо-баннеры, дисклеймеры, подписи автора):
- Список: `GET /api/v1/includes`
- Чтение: `GET /api/v1/includes/{name}`
- Сохранение выделенного фрагмента в сниппет: `POST /api/v1/includes` (`{"display_name": "Подпись", "content": "<p>С уважением, Автор</p>"}`)
- Удаление: `DELETE /api/v1/includes/{name}`

### Индивидуальный фон и атмосфера статьи
Каждой статье можно задать персональный атмосферный фон:
```http
POST /api/v1/media/backgrounds HTTP/1.1
Content-Type: application/json

{
  "post_id": 15,
  "background": "/data/uploads/cyberpunk_city.jpg",
  "repeat": "no-repeat",
  "size": "cover",
  "position": "center center",
  "overlay_color": "#000000",
  "overlay_opacity": 0.45
}
```
- Удаление фона: `DELETE /api/v1/media/backgrounds/15`

### Коллекции смайлов и стикеров (REST API)
- Получение каталога смайлов: `GET /api/v1/media/smiles`
- Загрузка нового набора смайлов:
  ```http
  POST /api/v1/media/smiles HTTP/1.1
  Content-Type: multipart/form-data
  
  setName=kolobok
  smiles[]=<файл1.gif>
  smiles[]=<файл2.png>
  ```
  Или через Base64 JSON:
  ```json
  {
    "setName": "kolobok",
    "items": [
      { "filename": "smile1.gif", "base64": "data:image/gif;base64,R0lGODlh..." }
    ]
  }
  ```
- Удаление набора: `DELETE /api/v1/media/smiles/{setName}`
- Вставка смайла в текст:
  ```html
  <img src="/data/smiles/kolobok/smile.gif" class="blog-smile" alt="smile" style="vertical-align:middle; width:auto; height:1.4em;" />
  ```

---

## 11. Эталонные реализации клиентов (SDK)

### TypeScript / Web / Electron SDK

```typescript
/**
 * NPBlog Complete Custom Client SDK
 */
export interface NPBlogConfig {
  baseUrl: string; // http://your-blog.com/api/v1
  token?: string;
}

export class NPBlogClient {
  private baseUrl: string;
  private token: string | null = null;

  constructor(config: NPBlogConfig) {
    this.baseUrl = config.baseUrl.replace(/\/+$/, '');
    this.token = config.token || null;
  }

  public setToken(token: string | null) {
    this.token = token;
  }

  private async request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
    const url = `${this.baseUrl}/${endpoint.replace(/^\/+/, '')}`;
    const headers: Record<string, string> = {
      'Accept': 'application/json',
      ...(options.headers as Record<string, string> || {}),
    };
    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }
    const res = await fetch(url, { ...options, headers });
    const json = await res.json();
    if (!res.ok || !json.success) {
      throw new Error(json.error?.message || `HTTP ${res.status}`);
    }
    return json.data;
  }

  // --- Аутентификация ---
  async login(password: string, deviceName = 'TS Client') {
    const data = await this.request<{ access_token: string }>('auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ password, device_name: deviceName }),
    });
    this.token = data.access_token;
    return data.access_token;
  }

  // --- Статьи ---
  async getPosts(page = 1, limit = 20, q = '', sort = 'id_desc') {
    const p = new URLSearchParams({ page: String(page), limit: String(limit), sort });
    if (q) p.set('q', q);
    return this.request<any[]>(`posts?${p}`);
  }

  async getPost(id: number) {
    return this.request<any>(`posts/${id}`);
  }

  async savePost(title: string, content: string, id?: number, date?: string) {
    const cleanedHtml = this.cleanContentForSave(content);
    if (id && id > 0) {
      return this.request<any>(`posts/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, content: cleanedHtml, date }),
      });
    } else {
      return this.request<any>('posts', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, content: cleanedHtml, date }),
      });
    }
  }

  // --- Загрузка медиа ---
  async uploadMedia(file: File | Blob, filename: string, type = 'image') {
    const fd = new FormData();
    fd.append('file', file, filename);
    fd.append('type', type);
    return this.request<{ url: string; absolute_url: string }>('media/upload', {
      method: 'POST',
      body: fd,
    });
  }

  // --- Смайлы и шрифты ---
  async getSmiles() {
    return this.request<any[]>('media/smiles');
  }

  async deleteSmileSet(setName: string) {
    return this.request<any>(`media/smiles/${setName}`, { method: 'DELETE' });
  }

  async getFonts() {
    return this.request<{ fonts: any[]; css: string }>('media/fonts');
  }

  async deleteFont(filename: string) {
    return this.request<any>(`media/fonts/${filename}`, { method: 'DELETE' });
  }

  // --- Очистка разметки перед отправкой ---
  cleanContentForSave(html: string): string {
    if (!html) return '';
    const div = document.createElement('div');
    div.innerHTML = html;
    div.querySelectorAll('.image-toolbar, .image-align-dropdown, .image-size-indicator, .image-resize-handle, .blog-image-overlay, .column-resizer, #customCaret')
      .forEach(el => el.remove());
    div.querySelectorAll('[data-image-id], [data-media-id], [data-media-type], [data-resizers-added]')
      .forEach(el => {
        el.removeAttribute('data-image-id');
        el.removeAttribute('data-media-id');
        el.removeAttribute('data-media-type');
        el.removeAttribute('data-resizers-added');
      });
    div.querySelectorAll('[contenteditable]').forEach(el => el.removeAttribute('contenteditable'));
    div.querySelectorAll('.selected').forEach(el => el.classList.remove('selected'));
    return div.innerHTML.trim();
  }
}
```

### Android Kotlin Client (Retrofit + Coroutines)

```kotlin
interface NPBlogApiService {
    @POST("auth/login")
    suspend fun login(@Body req: Map<String, String>): Response<ApiResponse<LoginData>>

    @GET("posts")
    suspend fun getPosts(
        @Query("page") page: Int = 1,
        @Query("limit") limit: Int = 20,
        @Query("q") search: String? = null
    ): Response<ApiResponse<List<PostItem>>>

    @GET("posts/{id}")
    suspend fun getPost(@Path("id") id: Int): Response<ApiResponse<PostDetail>>

    @POST("posts")
    suspend fun createPost(@Body req: PostPayload): Response<ApiResponse<PostItem>>

    @PUT("posts/{id}")
    suspend fun updatePost(@Path("id") id: Int, @Body req: PostPayload): Response<ApiResponse<PostItem>>

    @Multipart
    @POST("media/upload")
    suspend fun uploadFile(
        @Part file: MultipartBody.Part,
        @Part("type") type: RequestBody
    ): Response<ApiResponse<MediaResult>>

    @POST("autosaves")
    suspend fun saveAutosave(@Body req: AutosavePayload): Response<ApiResponse<Unit>>

    @GET("media/smiles")
    suspend fun getSmiles(): Response<ApiResponse<List<SmileSet>>>

    @DELETE("media/smiles/{name}")
    suspend fun deleteSmileSet(@Path("name") setName: String): Response<ApiResponse<Unit>>
}
```

### Delphi / Windows Desktop Client (THTTPClient)

```pascal
function TNPBlogClient.UploadMedia(const AFilePath, AType: string): string;
var
  LMultipart: TMultipartFormData;
  LResponse: IHTTPResponse;
  LJson: TJSONObject;
begin
  LMultipart := TMultipartFormData.Create;
  try
    LMultipart.AddFile('file', AFilePath);
    LMultipart.AddField('type', AType);

    FHttp.CustomHeaders['Authorization'] := 'Bearer ' + FAccessToken;
    LResponse := FHttp.Post(BuildUrl('media/upload'), LMultipart);
    LJson := TJSONObject.ParseJSONValue(LResponse.ContentAsString(TEncoding.UTF8)) as TJSONObject;
    try
      Result := LJson.GetValue<TJSONObject>('data').GetValue<string>('url'); // /data/uploads/...
    finally
      LJson.Free;
    end;
  finally
    LMultipart.Free;
  end;
end;
```

### Python CLI / Headless Automation

```python
import requests

def publish_from_markdown(title: str, md_text: str, api_token: str, base_url="http://localhost/api/v1"):
    headers = {"Authorization": f"Bearer {api_token}"}
    res = requests.post(f"{base_url}/posts", json={"title": title, "content": md_text}, headers=headers)
    data = res.json()
    if data.get("success"):
        print(f"Статья опубликована! ID: {data['data']['id']}, URL: {data['data']['url']}")
    else:
        print(f"Ошибка: {data}")
```

---

## 12. Сводная матрица соответствия функций основного редактора

В таблице ниже сопоставлены все ключевые функции основного редактора NPBlog (`funcs.txt`), их визуальное поведение и соответствующие механизмы REST API:

| № | Функция / Модуль | Назначение в редакторе | Реализация на клиенте | Эндпоинт API |
|---|---|---|---|---|
| 1 | `formatText('b'/'i'/'u'/'s')` | Базовое инлайн-форматирование | Обертывание в `<b>`, `<i>`, `<u>`, `<s>` | — (в теле статьи) |
| 2 | `setTextColor(color)` | Смена цвета текста | `<span style="color: #hex;">` | — |
| 3 | `setFontSize(size)` | Смена размера шрифта | `<span style="font-size: Npx;">` | — |
| 4 | `setFontFamily(font)` | Выбор гарнитуры шрифта | `<span style="font-family: ...">` | `GET /v1/media/fonts` |
| 5 | `alignText(side)` | Выравнивание блоков текста | `style="text-align: left\|center\|right"` | — |
| 6 | `insertMarker(color, style)` | Выделитель текста (5 стилей) | `<mark data-marker-color="..." data-marker-style="...">` | — |
| 7 | `insertSpoiler()` | Сворачиваемый блок спойлера | `<details class="spoiler-block"><summary>...` | — |
| 8 | `insertCodeBlock()` | Вставка блока кода с языком | `<pre class="code-block" data-language="...">` | — |
| 9 | `openEditCodeBlockDialog()` | Редактирование кода по клику | Считывание DOM `<pre>`, замена содержимого | — |
| 10 | `insertCustomButtonToEditor()` | Вставка интерактивной CTA-кнопки | `<a class="custom-blog-btn" ...>` | — |
| 11 | `openEditCustomButtonDialog()` | Редактирование кнопки по клику | Считывание инлайн-стилей кнопки в GUI | — |
| 12 | `saveAsciiArt()` | Вставка ASCII-арта | `<div class="blog-ascii-wrap"><pre class="blog-ascii-art">` | — |
| 13 | `openAsciiDrawer(targetWrap)` | Редактирование ASCII по клику | Загрузка `data-ascii-grid` на холст 40x15 | — |
| 14 | `insertTable()` | Конструктор таблиц | `<table><thead>...<tbody>...` | — |
| 15 | `addColumnResizers()` | Живые ресайзеры колонок таблицы | Drag-маркеры на границах ячеек `<th>` | — |
| 16 | `setCellColor(color)` | Заливка фона ячейки таблицы | `td.style.backgroundColor` + авто-контраст | — |
| 17 | `addAnchor()` | Добавление якоря в текст | `<span id="..." data-npblog-anchor="true">` | — |
| 18 | `insertHorizontalRule()` | Вставка разделительной линии | `<hr>` (в визуальном) или `---` (в Markdown) | — |
| 19 | `loadTocList()` | Оглавление статьи (TOC) | Парсинг элементов `[id]`, вставка ссылок `#id` | — |
| 20 | `insertFileButton()` | Карточка скачивания файла | `<a class="blog-file-button" download="...">` | `POST /v1/media/upload` |
| 21 | `uploadImage()` | Загрузка и вставка картинки | Обертка в `.blog-image-align-wrap` | `POST /v1/media/upload` |
| 22 | `insertImagesInGrid()` | Сетка картинок (Grid Layout) | `<div class="image-grid">...</div>` | `POST /v1/media/upload` |
| 23 | `insertImagesAsGallery()` | Фотогалерея (Lightbox) | `<div class="image-gallery">...</div>` | `POST /v1/media/upload` |
| 24 | `insertMedia()` | Вставка видео/аудио/YouTube | `<video>`, `<audio>`, iframe YouTube/Vimeo | `POST /v1/media/upload` |
| 25 | `showGlobalMediaOverlay()` | Оверлей ресайза и выравнивания | Рамка с маркерами nw/ne/sw/se вокруг медиа | — |
| 26 | `parseMarkdownToHtml()` | Компиляция Markdown в HTML | Двухсторонний парсер MD в HTML | — |
| 27 | `convertHtmlToMarkdown()` | Преобразование HTML в Markdown | Рекурсивный парсер DOM в MD | — |
| 28 | `cleanContentForSave()` | Очистка разметки перед сейвом | Удаление оверлеев, ресайзеров и `contenteditable` | — |
| 29 | `applySmoothTypingState()` | Мягкий скользящий курсор | Floating div `#customCaret` с анимацией | — |
| 30 | `scheduleLocalDraftSave()` | Локальный автосейв (LocalStorage) | Запись в `localStorage['npblog_draft_...']` | — |
| 31 | `checkLocalDraftOnStartup()` | Восстановление после сбоя | Проверка таймстемпа и вывод Toast | — |
| 32 | `saveDraft()` | Серверный черновик | Сохранение черновика без публикации | `POST /v1/drafts` |
| 33 | `restoreBackup()` | Откат к версии бэкапа | Замена текущей статьи на выбранную версию | `POST /v1/backups/.../restore` |
| 34 | `applyTemplateToPost()` | Применение HTML-шаблона | Выбор шаблона блога | `POST /v1/templates/apply` |
| 35 | `openSaveInclude()` | Сохранение фрагмента в сниппет | Сохранение текста в `includes/` | `POST /v1/includes` |
| 36 | `insertSmile()` | Вставка смайла / стикера | `<img src="/data/smiles/..." class="blog-smile">` | `GET /v1/media/smiles` |
| 37 | `openSmileSetsDialog()` | Управление наборами смайлов | Загрузка и удаление паков смайлов | `POST / DELETE /v1/media/smiles` |
| 38 | `updatePostBackground()` | Персональный фон статьи | Настройка фонового изображения и затемнения | `POST /v1/media/backgrounds` |
| 39 | `undoEdit() / redoEdit()` | Отмена / повтор действий | Стек из 50 состояний с оффсетами курсора | `GET/POST /v1/system/history` |
| 40 | `filterPosts()` | Живой поиск по публикациям | Фильтрация по подстроке заголовка или ID | `GET /v1/posts?q=...` |
| 41 | `renumberPosts()` | Сквозная перенумерация статей | Восстановление нумерации 1..N | `POST /v1/posts/renumber` |

---

> Данное руководство является официальной нормативной спецификацией. Любой сторонний редактор, реализованный в соответствии с описанными стандартами, обеспечивает 100% совместимость с экосистемой NPBlog.
