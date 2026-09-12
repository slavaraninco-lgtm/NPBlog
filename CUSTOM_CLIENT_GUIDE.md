# 🛠️ NPBlog: Руководство по разработке кастомных клиентов редактора (REST API v1)

Данное руководство представляет собой исчерпывающий технический справочник и архитектурный стандарт для разработчиков, создающих **кастомные клиенты и визуальные редакторы** для CMS **NPBlog**.

Руководство охватывает разработку приложений под любые платформы:
- **Десктопные клиенты**: Windows, macOS, Linux (Delphi / C++ Builder, C# .NET / WPF, Electron / Tauri, Qt / C++, Python PyQt).
- **Мобильные приложения**: Android (Kotlin / Jetpack Compose), iOS (Swift / SwiftUI), кроссплатформенные решения (Flutter, React Native).
- **Веб-клиенты**: Single Page Applications (React, Vue, Svelte), встраиваемые админ-панели и Headless-интеграции.
- **Утилиты автоматизации**: CLI-инструменты, Telegram-боты, интеграция с Obsidian, Notion и CI/CD пайплайнами.

---

## 📑 Содержание

1. [Архитектурная модель NPBlog и роль API](#1-архитектурная-модель-npblog-и-роль-api)
2. [Аутентификация, безопасность и сессии](#2-аутентификация-безопасность-и-сессии)
   - [Процесс авторизации и получение токена](#процесс-авторизации-и-получение-токена)
   - [Передача токена в запросах](#передача-токена-в-запросах)
   - [Безопасное хранение токена на клиенте](#безопасное-хранение-токена-на-клиенте)
   - [Защита от перебора паролей (Lockout) и таймер блокировки](#защита-от-перебора-паролей-lockout-и-таймер-блокировки)
   - [Управление устройствами и завершение сессий](#управление-устройствами-и-завершение-сессий)
3. [Критический контракт медиа-файлов и адресации](#3-критический-контракт-медиа-файлов-и-адресации)
   - [Главное правило: `/data/`, а не `/api/`](#главное-правило-data-а-не-api)
   - [Относительные vs Абсолютные пути](#относительные-vs-абсолютные-пути)
   - [Серверная авто-нормализация путей](#серверная-авто-нормализация-путей)
4. [Сквозные сценарии работы редактора](#4-сквозные-сценарии-работы-редактора)
   - [Сценарий 1: Список, пагинация и поиск статей](#сценарий-1-список-пагинация-и-поиск-статей)
   - [Сценарий 2: Загрузка статьи в редактор](#сценарий-2-загрузка-статьи-в-редактор)
   - [Сценарий 3: Создание новой статьи](#сценарий-3-создание-новой-статьи)
   - [Сценарий 4: Сохранение изменений](#сценарий-4-сохранение-изменений)
   - [Сценарий 5: Удаление статьи](#сценарий-5-удаление-статьи)
   - [Сценарий 6: Загрузка медиа (Multipart и Base64)](#сценарий-6-загрузка-медиа-multipart-и-base64)
5. [Подсистема надежности: Автосохранения, черновики и версионирование](#5-подсистема-надежности-автосохранения-черновики-и-версионирование)
   - [Движок автосохранений (Autosave Engine)](#движок-автосохранений-autosave-engine)
   - [Работа с черновиками (Drafts)](#работа-с-черновиками-drafts)
   - [История версий и откат (Revisions & Backups)](#история-версий-и-откат-revisions--backups)
6. [Оформление, типографика, фоны и сниппеты](#6-оформление-типографика-фоны-и-сниппеты)
   - [Индивидуальный фон статьи](#индивидуальный-фон-статьи)
   - [Пользовательские шрифты и стили](#пользовательские-шрифты-и-стили)
   - [Текстовые сниппеты (Includes) и смайлы](#текстовые-сниппеты-includes-и-смайлы)
   - [Шаблоны блога](#шаблоны-блога)
7. [Архитектурные паттерны реализации клиентов](#7-архитектурные-паттерны-реализации-клиентов)
   - [Паттерн А: WebView / Гибридный визуальный редактор](#паттерн-а-webview--гибридный-визуальный-редактор)
   - [Паттерн Б: Нативный визуальный редактор (Rich Text / Spanned)](#паттерн-б-нативный-визуальный-редактор-rich-text--spanned)
   - [Паттерн В: Markdown-First редактор](#паттерн-в-markdown-first-редактор)
   - [Паттерн Г: Headless CLI / CI/CD Bot](#паттерн-г-headless-cli--cicd-bot)
8. [Готовые эталонные реализации клиентов](#8-готовые-эталонные-реализации-клиентов)
   - [TypeScript / Web / Electron SDK](#typescript--web--electron-sdk)
   - [Android Kotlin Client (Retrofit + Coroutines)](#android-kotlin-client-retrofit--coroutines)
   - [Delphi / Windows Desktop Client (THTTPClient)](#delphi--windows-desktop-client-thttpclient)
   - [Python CLI / Automation Script](#python-cli--automation-script)
9. [Сетевая устойчивость, Offline-режим и диагностика](#9-сетевая-устойчивость-offline-режим-и-диагностика)
   - [Очередь отложенных публикаций (Offline Queue)](#очередь-отложенных-публикаций-offline-queue)
   - [Самодиагностика и восстановление целостности](#самодиагностика-и-восстановление-целостности)
   - [Сервисные операции: перенумерация и перегенерация](#сервисные-операции-перенумерация-и-перегенерация)
10. [Чек-лист готовности кастомного редактора (QA Checklist)](#10-чек-лист-готовности-кастомного-редактора-qa-checklist)

---

## 1. Архитектурная модель NPBlog и роль API

### Концепция файлового хранилища (Flat-File CMS)
CMS NPBlog работает **без реляционных СУБД (MySQL, PostgreSQL)**, сохраняя максимальную скорость, независимость и переносимость:

```
c:\xampp\htdocs\ (Корень блога)
├── data\
│   ├── blog\
│   │   ├── posts-meta.json      <-- Реестр метаданных статей (ID, заголовок, дата, файл)
│   │   ├── post-1.html          <-- Готовый собранный HTML статьи #1 (с шаблоном)
│   │   └── post-2.html
│   ├── uploads\                 <-- Загруженные изображения (m_xxxxxx.jpg)
│   ├── files\                   <-- Видео, аудио, документы
│   ├── drafts\                  <-- Сохраненные черновики (.json)
│   ├── fonts\                   <-- Пользовательские веб-шрифты
│   └── api_tokens.json          <-- Хранилище активных токенов авторизации
├── autosave\                    <-- Временные файлы автосохранений (autosave_1.json)
├── data_backup\                 <-- Версионирование статей (1/1-1.html, 1/1-2.html...)
└── api\                         <-- REST API v1
    ├── index.php                <-- Единая точка входа API
    └── src\Controllers\         <-- Контроллеры по сущностям
```

### Задачи REST API
При создании кастомного редактора вам **не нужно вручную работать с файлами на сервере (через FTP/SSH)**. REST API берет на себя всю внутреннюю механику:
1. **Атомарность и блокировки (`LOCK_EX`)**: защита от повреждения файлов при одновременных запросах.
2. **Изоляция контента**: при запросе статьи API автоматически вырезает шапку, подвал и сайдбары шаблона блога, отдавая клиенту **только чистый контент статьи** для редактирования.
3. **Автоматическое версионирование**: при каждом сохранении API инкрементирует номер версии статьи в `data_backup/` и обновляет `backup-meta.json`.
4. **Автоматическое обновление зависимостей**: API сам пересобирает метаданные `posts-meta.json` и перегенерирует RSS-ленту (`rss.xml`).
5. **Нормализация путей медиа**: API гарантирует корректность путей к изображениям независимо от домена и хостинга.

### Базовый URL API
- Основной маршрут: `http://<ваш-домен>/api/v1`
- Fallback-маршрут (без mod_rewrite): `http://<ваш-домен>/api/index.php?_route=v1`

---

## 2. Аутентификация, безопасность и сессии

### Процесс авторизации и получение токена
В отличие от веб-браузера, кастомный клиент не использует cookies сессий PHP. Авторизация построена на стандарте **Bearer Token (RFC 6750)**:

```mermaid
sequenceDiagram
    autonumber
    actor User as Пользователь
    participant App as Кастомный клиент
    participant API as NPBlog REST API
    participant Store as data/api_tokens.json

    User->App: Ввод пароля блога
    App->>API: POST /api/v1/auth/login { password, device_name }
    API->API: Проверка хеша пароля и Lockout
    API->Store: Генерация токена npb_... (SHA-256 в базе)
    API-->>App: 200 OK { access_token, token_id, expires_in: 2592000 }
    App->App: Сохранение токена в безопасное хранилище
```

#### Запрос на вход:
```http
POST /api/v1/auth/login HTTP/1.1
Host: your-blog.com
Content-Type: application/json

{
  "password": "пароль_к_блогу",
  "device_name": "NPBlog Studio (Windows Desktop)"
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
    "device_name": "NPBlog Studio (Windows Desktop)",
    "expires_at": 1791735875,
    "expires_in": 2592000,
    "created_at": 1789143875
  }
}
```
Срок действия токена по умолчанию — **30 дней** (`2592000` секунд). При каждом запросе сервер автоматически продлевает сессию активного устройства.

---

### Передача токена в запросах
Кастомный клиент должен передавать полученный токен в каждом HTTP-запросе. Поддерживаются 3 способа:

1. **Основной (рекомендуемый)** — стандартный HTTP-заголовок:
   ```http
   Authorization: Bearer npb_9bc3d99d8ea3b4c9e1c81bc0d11505c733fac79dfeb78680911f589b779b0e39
   ```
2. **Альтернативный заголовок** (если прокси или сервер вырезает Authorization):
   ```http
   X-API-Token: npb_9bc3d99d8ea3b4c9e1c81bc0d11505c733fac79dfeb78680911f589b779b0e39
   ```
3. **Query-параметр** (для прямого рендеринга защищенных файлов в WebView / `<img>`):
   ```http
   GET /api/v1/posts/1/preview?api_token=npb_9bc3d99d8ea3...
   ```

---

### Безопасное хранение токена на клиенте
> [!CAUTION]
> Никогда не сохраняйте `access_token` в открытом текстовом виде, не шифрованных `.ini`, `UserDefaults` (без шифрования) или незащищенных файлах! При утечке токена злоумышленник получит полный контроль над блогом.

| Платформа | Рекомендуемое защищенное хранилище |
|---|---|
| **Android** | `EncryptedSharedPreferences` / `Android Keystore` |
| **iOS / macOS** | `Keychain Services` (`kSecClassGenericPassword`) |
| **Windows Desktop (.NET / Delphi)** | `Windows Data Protection API (DPAPI)` (`CryptProtectData`) или `Windows.Security.Credentials.PasswordVault` |
| **Linux Desktop** | `Secret Service API` (`libsecret` / `GNOME Keyring` / `KWallet`) |
| **Electron Desktop** | `electron.safeStorage` API |
| **Web SPA (Браузер)** | `HttpOnly; Secure; SameSite=Strict` Cookie или зашифрованный `IndexedDB` |

---

### Защита от перебора паролей (Lockout) и таймер блокировки
Если пользователь ввел неверный пароль 3 раза подряд, сервер блокирует его IP на **15 минут** (900 секунд).

При блокировке API возвращает HTTP-код `429 Too Many Requests`:
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

#### Требование к UI клиента:
При получении ошибки `429`:
1. Извлеките число секунд из `error.details.lockout_time_remaining`.
2. Заблокируйте кнопку входа в интерфейсе клиента.
3. Запустите визуальный таймер обратного отсчета (например: *"Повторите попытку через 14:07"*).
4. Разблокируйте кнопку только по истечении таймера.

---

### Управление устройствами и завершение сессий
Клиент может предоставить пользователю экран управления активными сессиями:
- `GET /api/v1/auth/tokens` — возвращает список всех подключенных к блогу устройств с датой входа, IP и флагом `is_current: true`.
- `DELETE /api/v1/auth/tokens/{tokenId}` — удаленно отзывает сессию выбранного смартфона или ПК.
- `POST /api/v1/auth/logout` — завершает текущую сессию и удаляет токен из базы.

---

## 3. Критический контракт медиа-файлов и адресации

### Главное правило: `/data/`, а не `/api/`
> [!IMPORTANT]
> **ЖЕЛЕЗНОЕ ПРАВИЛО АДРЕСАЦИИ МЕДИА:**
> - Все статические файлы (картинки, видео, аудио) физически хранятся и раздаются веб-сервером напрямую из папки `/data/uploads/` и `/data/files/`.
> - Эндпоинты API `/api/v1/media/...` предназначены **ТОЛЬКО** для загрузки, каталогизации и удаления файлов.
> - В HTML-код статьи должны вставляться **ТОЛЬКО ссылки на `/data/...`**:
>   - ✅ **Правильно:** `<img src="/data/uploads/m_7fa21b.jpg" alt="Фото" />`
>   - ❌ **ОШИБКА:** `<img src="/api/data/uploads/m_7fa21b.jpg" />`
>   - ❌ **ОШИБКА:** `<img src="/api/v1/media/uploads/m_7fa21b.jpg" />`

### Относительные vs Абсолютные пути
При загрузке любого файла (`POST /api/v1/media/upload`) сервер возвращает объект:
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

#### Где какое поле использовать:
1. **Поле `url` (`/data/uploads/...`)** — вставляйте в HTML-код статьи (`src="/data/uploads/..."`). Это гарантирует, что статья будет корректно открываться при смене домена, переносе на HTTPS или локальном просмотре.
2. **Поле `absolute_url` (`http://.../data/uploads/...`)** — используйте **внутри вашего клиента** для предпросмотра изображения в нативных компонентах (`Image` во Flutter, `AsyncImage` в SwiftUI, `Glide/Coil` в Android, `TImage` в Delphi).

---

### Серверная авто-нормализация путей
Сервер NPBlog реализует защиту от случайных ошибок клиентских приложений:
Если кастомный редактор отправит контент с полным адресом сервера или префиксом `/api/data/`:
```html
<p>Текст статьи</p>
<img src="http://myblog.com/data/uploads/m_test.jpg" />
<img src="/api/data/uploads/m_test2.jpg" />
```
Метод `PostsController::formatArticleContent()` на сервере автоматически перехватит и преобразует ссылки к каноническому виду:
```html
<p>Текст статьи</p>
<img src="/data/uploads/m_test.jpg" />
<img src="/data/uploads/m_test2.jpg" />
```
Однако клиентский редактор обязан изначально следовать стандарту и не полагаться исключительно на автоисправление.

---

## 4. Сквозные сценарии работы редактора

Ниже приведена полная диаграмма состояний и переходов типового кастомного редактора:

```mermaid
stateDiagram-v2
    [*] --> CheckSession: Запуск клиента
    CheckSession --> LoginScreen: Нет токена / 401
    CheckSession --> PostsList: Токен валиден (GET /auth/me)
    LoginScreen --> PostsList: Успешный вход (POST /auth/login)
    
    PostsList --> EditorEmpty: Новая статья (ID = 0)
    PostsList --> EditorExisting: Выбор статьи (GET /posts/{id})
    
    state Editor {
        EditorEmpty --> Typing
        EditorExisting --> Typing
        Typing --> CheckAutosave: Проверка при открытии
        Typing --> Autosaving: Фоновый таймер (POST /autosaves)
        Typing --> MediaUploading: Вставка фото/видео (POST /media/upload)
        MediaUploading --> Typing: Вставка <img src="/data/uploads/...">
        Typing --> Revisions: Просмотр бэкапов (GET /backups/{id})
        Revisions --> Typing: Откат версии (POST /restore)
    }

    Editor --> PostsList: Публикация/Сохранение (POST / PUT /posts)
    Editor --> PostsList: Отмена / Закрытие
```

---

### Сценарий 1: Список, пагинация и поиск статей
При открытии главного окна редактор запрашивает реестр публикаций:

```http
GET /api/v1/posts?page=1&limit=20&sort=id_desc&q=новости HTTP/1.1
Authorization: Bearer <access_token>
```

#### Параметры запроса:
- `page` *(int)*: номер страницы (от 1).
- `limit` *(int)*: элементов на странице (по умолчанию 20, максимум 100).
- `sort` *(enum)*:
  - `id_desc` (по умолчанию, сначала самые свежие по ID).
  - `id_asc` (по возрастанию ID).
  - `date_desc` (по дате публикации, новые первые).
  - `date_asc` (по дате публикации, старые первые).
- `q` *(string, опционально)*: строка живого поиска (ищет по вхождению в заголовок или точному ID статьи).

#### Структура ответа:
```json
{
  "success": true,
  "status": 200,
  "data": [
    {
      "id": 15,
      "title": "Обзор обновлений редактора",
      "date": "12.09.2026 14:30",
      "filename": "post-15.html",
      "url": "/data/blog/post-15.html"
    }
  ],
  "meta": {
    "total": 48,
    "page": 1,
    "limit": 20,
    "total_pages": 3
  }
}
```

---

### Сценарий 2: Загрузка статьи в редактор
Когда пользователь выбирает статью из списка, клиент отправляет запрос:

```http
GET /api/v1/posts/15 HTTP/1.1
Authorization: Bearer <access_token>
```

#### Ответ:
```json
{
  "success": true,
  "status": 200,
  "data": {
    "id": 15,
    "title": "Обзор обновлений редактора",
    "date": "12.09.2026 14:30",
    "filename": "post-15.html",
    "content": "<h2>Введение</h2><p>Текст статьи...</p><img src=\"/data/uploads/m_6aa42c.jpg\" />",
    "background": {
      "has_background": true,
      "image": "/data/uploads/bg_article.jpg",
      "repeat": "no-repeat",
      "size": "cover",
      "position": "center center",
      "overlay_color": "#000000",
      "overlay_opacity": 0.3
    },
    "url": "/data/blog/post-15.html"
  }
}
```

> [!TIP]
> Обратите внимание: поле `data.content` содержит **только чистый HTML контент статьи**. Все шаблоны блога (шапка, меню, виджеты, подвал) уже отсечены сервером. Клиент сразу загружает этот HTML в свой визуальный редактор (WebView, TinyMCE, Quill или нативный компонент).

---

### Сценарий 3: Создание новой статьи
Для публикации новой статьи отправляется POST-запрос:

```http
POST /api/v1/posts HTTP/1.1
Authorization: Bearer <access_token>
Content-Type: application/json

{
  "title": "Заголовок новой публикации",
  "content": "<p>Первый абзац статьи с форматированием.</p><img src=\"/data/uploads/m_fa1234.jpg\" />",
  "date": "12.09.2026 15:00"
}
```

#### Что делает сервер в этот момент:
1. Вычисляет следующий свободный ID (например, `16`).
2. Очищает контент и нормализует адреса медиафайлов.
3. Загружает текущий активный HTML-шаблон блога (`template_post.html`).
4. Встраивает заголовок, дату и контент в шаблон, создавая `data/blog/post-16.html`.
5. Создает начальную резервную копию `data_backup/16/16-1.html` (версия №1) и обновляет `backup-meta.json`.
6. Добавляет запись в `data/blog/posts-meta.json`.
7. Автоматически перегенерирует RSS-ленту `rss.xml`.
8. Возвращает созданную статью с присвоенным `id`.

---

### Сценарий 4: Сохранение изменений
Для сохранения статьи, которая уже существует в блоге, используется метод `PUT`:

```http
PUT /api/v1/posts/15 HTTP/1.1
Authorization: Bearer <access_token>
Content-Type: application/json

{
  "title": "Обновленный заголовок",
  "content": "<p>Отредактированный текст статьи...</p>",
  "date": "12.09.2026 15:15"
}
```

#### Поведение сервера при сохранении:
- Сервер автоматически создает **новую инкрементную версию бэкапа** (например, `15-2.html`), сохраняя всю историю правок.
- Обновляет HTML-файл на диске.
- Обновляет метаданные в `posts-meta.json` и RSS-ленту.

---

### Сценарий 5: Удаление статьи
```http
DELETE /api/v1/posts/15?renumber=false HTTP/1.1
Authorization: Bearer <access_token>
```

#### Параметр `renumber`:
- `false` (по умолчанию) — просто удаляет статью №15, её бэкапы и фоновые изображения.
- `true` — после удаления автоматически запускает **сквозную перенумерацию** всех оставшихся статей блога (1..N).

---

### Сценарий 6: Загрузка медиа (Multipart и Base64)
Клиентский редактор может загружать изображения, видео, аудио и документы двумя способами:

#### Способ 1: Стандартный `multipart/form-data` (рекомендуется для больших файлов и ПК):
```http
POST /api/v1/media/upload HTTP/1.1
Authorization: Bearer <access_token>
Content-Type: multipart/form-data; boundary=----WebKitFormBoundary7MA4YWxkTrZu0gW

------WebKitFormBoundary7MA4YWxkTrZu0gW
Content-Disposition: form-data; name="file"; filename="photo.jpg"
Content-Type: image/jpeg

<бинарные данные файла>
------WebKitFormBoundary7MA4YWxkTrZu0gW
Content-Disposition: form-data; name="type"

image
------WebKitFormBoundary7MA4YWxkTrZu0gW--
```

#### Способ 2: `Base64 JSON` (идеально для камеры смартфона, вставки из буфера Ctrl+V и Drag-and-Drop):
```http
POST /api/v1/media/upload HTTP/1.1
Authorization: Bearer <access_token>
Content-Type: application/json

{
  "base64": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEASABIAAD...",
  "filename": "clipboard_snapshot.jpg",
  "type": "image"
}
```

#### Поддерживаемые типы файлов (`type`):
| Значение `type` | Допустимые расширения | Папка сохранения на сервере | Лимит размера |
|---|---|---|---|
| `image` | jpg, jpeg, png, gif, webp, svg | `/data/uploads/` | 20 МБ |
| `video` | mp4, webm, ogv, mov | `/data/files/videos/` | 100 МБ |
| `audio` | mp3, wav, ogg, m4a, aac | `/data/files/audio/` | 50 МБ |
| `document` | pdf, doc, docx, xls, ppt, zip, rar, txt | `/data/files/documents/` | 50 МБ |
| `font` | ttf, otf, woff, woff2 | `/data/fonts/` | 20 МБ |

---

## 5. Подсистема надежности: Автосохранения, черновики и версионирование

### Движок автосохранений (Autosave Engine)
Кастомный редактор **обязан** защищать пользователя от потери текста при внезапном закрытии приложения, разряде батареи или сбое сети.

#### Алгоритм работы автосохранения на клиенте:
1. Клиент отслеживает событие изменения текста в редакторе (typing / input).
2. Запускается **Debounce-таймер** (задержка 15–30 секунд после последнего нажатия клавиши).
3. При срабатывании таймера клиент отправляет фоновый запрос:
   ```http
   POST /api/v1/autosaves HTTP/1.1
   Authorization: Bearer <access_token>
   Content-Type: application/json

   {
     "post_id": "15",
     "title": "Текущий заголовок",
     "content": "<p>Текущий текст в редакторе...</p>"
   }
   ```
4. **Проверка при открытии статьи:**
   Когда пользователь открывает статью №15 на редактирование, клиент параллельно выполняет:
   ```http
   GET /api/v1/autosaves/15 HTTP/1.1
   Authorization: Bearer <access_token>
   ```
   Если автосохранение существует и его `timestamp` новее даты изменения статьи, клиент отображает диалог:
   > ⚠️ *"Обнаружена несохраненная копия этой статьи от 12.09 15:42. Восстановить её?"*
5. **Очистка после сохранения:**
   Как только пользователь нажимает кнопку «Опубликовать» / «Сохранить» (`PUT /api/v1/posts/15`), клиент сразу удаляет временный файл автосохранения:
   ```http
   DELETE /api/v1/autosaves/15 HTTP/1.1
   Authorization: Bearer <access_token>
   ```

---

### Работа с черновиками (Drafts)
Черновики — это полноценные сохраненные материалы, которые еще не опубликованы в блоге и не имеют присвоенного номера статьи:
- `GET /api/v1/drafts` — список всех доступных черновиков.
- `POST /api/v1/drafts` — сохранение черновика (`{"title": "Идея статьи", "content": "..."}`).
- `GET /api/v1/drafts/{filename}` — загрузка черновика по имени файла.
- `DELETE /api/v1/drafts/{filename}` — удаление черновика после публикации.

---

### История версий и откат (Revisions & Backups)
NPBlog автоматически создает новую версию бэкапа при каждом сохранении статьи. Клиент может предоставить пользователю панель «История изменений»:

```mermaid
sequenceDiagram
    participant App as Клиент Редактора
    participant API as NPBlog API
    participant Disk as data_backup/15/

    App->>API: GET /api/v1/backups/15
    API-->>App: [{ backupNumber: 1, date: "10.09" }, { backupNumber: 2, date: "12.09" }]
    
    Note over App: Пользователь выбирает версию №1
    App->>API: GET /api/v1/backups/15/1
    API-->>App: { backup_number: 1, content: "Исходный текст..." }
    
    Note over App: Пользователь нажимает "Откатить к этой версии"
    App->>API: POST /api/v1/backups/15/1/restore
    API->Disk: Копирование 15-1.html в post-15.html
    API-->>App: 200 OK { message: "Статья восстановлена" }
```

- `GET /api/v1/backups/{postId}` — список доступных ревизий статьи.
- `GET /api/v1/backups/{postId}/{backupNumber}` — получение содержимого конкретной версии для сравнения (Diff) или предпросмотра.
- `POST /api/v1/backups/{postId}/{backupNumber}/restore` — мгновенный откат статьи на выбранную версию на сервере.

---

## 6. Оформление, типографика, фоны и сниппеты

### Индивидуальный фон статьи
NPBlog позволяет настраивать персональный фон для каждой публикации:
```http
POST /api/v1/media/backgrounds HTTP/1.1
Authorization: Bearer <access_token>
Content-Type: application/json

{
  "post_id": 15,
  "background": "/data/uploads/m_custom_bg.jpg",
  "repeat": "no-repeat",
  "size": "cover",
  "position": "center center",
  "overlay_color": "#000000",
  "overlay_opacity": 0.35
}
```
- Сброс фона: `DELETE /api/v1/media/backgrounds/15`

---

### Пользовательские шрифты и стили
Кастомный редактор может подключить те же шрифты, что и сам блог:
- `GET /api/v1/media/fonts` — возвращает список загруженных TTF/WOFF2 шрифтов и готовый блок CSS-правил `@font-face`.
- Клиент внедряет этот CSS в свой WebView или RichText компонент, обеспечивая 100% совпадение внешнего вида при наборе текста (WYSIWYG).

---

### Текстовые сниппеты (Includes) и смайлы
- `GET /api/v1/includes` — возвращает список сохраненных вставок/сниппетов (например: промо-блоки, цитаты, рекламные баннеры, разделители).
- `GET /api/v1/includes/{name}` — возвращает готовый HTML-код сниппета для вставки в текущую позицию курсора редактора.
- `GET /api/v1/media/smiles` — каталог установленных наборов смайлов и стикеров с прямыми URL для панели эмодзи в клиенте.

---

### Шаблоны блога
- `GET /api/v1/templates` — список установленных HTML-шаблонов блога (`name`, `title`, `description`).
- `POST /api/v1/templates/apply` — применение выбранного шаблона (`{"template_name": "dark_modern", "post_id": 15}`) к статье или ко всему блогу разом (`"all": true`).

---

## 7. Архитектурные паттерны реализации клиентов

При проектировании кастомного клиента выберите одну из 4 проверенных архитектурных моделей:

### Паттерн А: WebView / Гибридный визуальный редактор
Самый популярный и функциональный подход:
- Внутри нативного окна (Windows, Android, iOS, macOS) размещается компонент браузера:
  - **Android**: `android.webkit.WebView`
  - **iOS / macOS**: `WKWebView`
  - **Windows (Delphi / C#)**: `WebView2` (`Microsoft.Web.WebView2` / `TEdgeBrowser`)
  - **Electron / Tauri**: нативный веб-стек
- Внутрь WebView загружается современный WYSIWYG движок: **TipTap**, **Quill.js**, **ProseMirror** или **TinyMCE**.
- Связь между нативным кодом и редактором происходит через JS-Bridge (`postMessage` / `evaluateJavaScript`).
- **Преимущество**: 100% совпадение стилей, легкая поддержка таблиц, видео и вставки картинок Drag-and-Drop.

---

### Паттерн Б: Нативный визуальный редактор (Rich Text / Spanned)
Полностью нативный интерфейс без WebView:
- **Android**: `EditText` со `SpannableStringBuilder` или `VisualTransformation` в Jetpack Compose.
- **iOS**: `UITextView` с `NSAttributedString`.
- **Windows Delphi**: `TRichEdit` или нативные компоненты оформления.
- При загрузке статьи: парсер HTML -> Нативные стили (Bold, Italic, Images).
- При сохранении: сериализатор Нативные стили -> HTML.
- **Преимущество**: минимальное потребление оперативной памяти, мгновенный отклик интерфейса.

---

### Паттерн В: Markdown-First редактор
Идеально для технических блогов и авторов, предпочитающих Markdown:
- Пользователь пишет текст в чистом Markdown-формате.
- Редактор поддерживает горячие клавиши, списки и подсветку синтаксиса.
- При сохранении клиент конвертирует Markdown в HTML через любую легковесную библиотеку (`markdown-it`, `commonmark`, `flexmark`) и отправляет в `POST/PUT /api/v1/posts`.

---

### Паттерн Г: Headless CLI / CI/CD Bot
Автоматизированные скрипты и боты:
- Telegram-бот: отправка текста и фото в чат -> бот вызывает `POST /media/upload` и публикует статью в блог.
- GitHub Actions: при пуше Markdown-файла в репозиторий скрипт собирает HTML и публикует через API.
- Плагин для Obsidian: публикация текущей заметки в блог одной кнопкой.

---

## 8. Готовые эталонные реализации клиентов

### TypeScript / Web / Electron SDK

Ниже представлен готовый, production-ready клиентский класс на TypeScript, который можно использовать в Node.js, Electron, React, Vue или любых веб-приложениях:

```typescript
/**
 * NPBlog Client SDK for Custom Editors
 */
export interface NPBlogConfig {
  baseUrl: string; // e.g. "http://localhost/api/v1"
  storageKey?: string;
}

export interface PostItem {
  id: number;
  title: string;
  date: string;
  filename: string;
  url: string;
}

export interface PostDetail extends PostItem {
  content: string;
  background?: Record<string, any>;
}

export interface MediaUploadResult {
  file_name: string;
  url: string; // /data/uploads/...
  absolute_url: string; // http://.../data/uploads/...
  size: number;
  type: string;
}

export class NPBlogClient {
  private baseUrl: string;
  private token: string | null = null;
  private autosaveTimer: any = null;

  constructor(config: NPBlogConfig) {
    this.baseUrl = config.baseUrl.replace(/\/+$/, '');
    if (typeof localStorage !== 'undefined') {
      this.token = localStorage.getItem(config.storageKey || 'npblog_token');
    }
  }

  public setToken(token: string | null) {
    this.token = token;
    if (typeof localStorage !== 'undefined') {
      if (token) localStorage.setItem('npblog_token', token);
      else localStorage.removeItem('npblog_token');
    }
  }

  public isAuthenticated(): boolean {
    return !!this.token;
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

    const response = await fetch(url, { ...options, headers });
    const json = await response.json();

    if (!response.ok || !json.success) {
      const errorMsg = json.error?.message || `HTTP Error ${response.status}`;
      const err: any = new Error(errorMsg);
      err.status = response.status;
      err.code = json.error?.code;
      err.details = json.error?.details;
      throw err;
    }

    return json.data;
  }

  // --- 1. Аутентификация ---
  public async login(password: string, deviceName = 'TypeScript Client'): Promise<string> {
    const data = await this.request<{ access_token: string }>('auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ password, device_name: deviceName }),
    });
    this.setToken(data.access_token);
    return data.access_token;
  }

  public async logout(): Promise<void> {
    try {
      if (this.token) await this.request('auth/logout', { method: 'POST' });
    } finally {
      this.setToken(null);
    }
  }

  // --- 2. Статьи ---
  public async getPosts(page = 1, limit = 20, q = '', sort = 'id_desc') {
    const params = new URLSearchParams({ page: String(page), limit: String(limit), sort });
    if (q) params.set('q', q);
    return this.request<PostItem[]>(`posts?${params.toString()}`);
  }

  public async getPost(id: number): Promise<PostDetail> {
    return this.request<PostDetail>(`posts/${id}`);
  }

  public async createPost(title: string, content: string, date?: string): Promise<PostItem> {
    return this.request<PostItem>('posts', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title, content, date }),
    });
  }

  public async updatePost(id: number, title: string, content: string, date?: string): Promise<PostItem> {
    return this.request<PostItem>(`posts/${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title, content, date }),
    });
  }

  public async deletePost(id: number, renumber = false): Promise<void> {
    await this.request(`posts/${id}?renumber=${renumber}`, { method: 'DELETE' });
  }

  // --- 3. Загрузка медиа (Multipart & Base64) ---
  public async uploadMediaFile(file: File | Blob, filename: string, type = 'image'): Promise<MediaUploadResult> {
    const formData = new FormData();
    formData.append('file', file, filename);
    formData.append('type', type);

    return this.request<MediaUploadResult>('media/upload', {
      method: 'POST',
      body: formData,
    });
  }

  public async uploadMediaBase64(base64Data: string, filename: string, type = 'image'): Promise<MediaUploadResult> {
    return this.request<MediaUploadResult>('media/upload', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ base64: base64Data, filename, type }),
    });
  }

  // --- 4. Автосохранение с Debounce ---
  public scheduleAutosave(postId: number | string, title: string, content: string, delayMs = 20000) {
    if (this.autosaveTimer) clearTimeout(this.autosaveTimer);
    this.autosaveTimer = setTimeout(async () => {
      try {
        await this.request('autosaves', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ post_id: String(postId), title, content }),
        });
        console.log(`[NPBlog] Автосохранение статьи ${postId} успешно выполнено`);
      } catch (e) {
        console.warn('[NPBlog] Ошибка фонового автосохранения:', e);
      }
    }, delayMs);
  }

  public async getAutosave(postId: number | string) {
    try {
      return await this.request<any>(`autosaves/${postId}`);
    } catch {
      return null;
    }
  }

  public async clearAutosave(postId: number | string): Promise<void> {
    if (this.autosaveTimer) clearTimeout(this.autosaveTimer);
    try {
      await this.request(`autosaves/${postId}`, { method: 'DELETE' });
    } catch {}
  }
}
```

---

### Android Kotlin Client (Retrofit + Coroutines)

Пример сетевого интерфейса и репозитория для современного Android-клиента:

```kotlin
package com.npblog.editor.data.api

import okhttp3.MultipartBody
import okhttp3.RequestBody
import retrofit2.Response
import retrofit2.http.*

data class ApiResponse<T>(
    val success: Boolean,
    val status: Int,
    val message: String?,
    val data: T?,
    val error: ApiError?
)

data class ApiError(val code: String, val message: String, val details: Map<String, Any>?)

data class LoginRequest(val password: String, val device_name: String)
data class LoginResponse(val access_token: String, val expires_in: Long)

data class PostItem(val id: Int, val title: String, val date: String, val url: String)
data class PostDetail(val id: Int, val title: String, val date: String, val content: String)

data class PostSaveRequest(val title: String, val content: String, val date: String? = null)
data class AutosaveRequest(val post_id: String, val title: String, val content: String)
data class MediaUploadResponse(val file_name: String, val url: String, val absolute_url: String)

interface NPBlogService {
    @POST("auth/login")
    suspend fun login(@Body req: LoginRequest): Response<ApiResponse<LoginResponse>>

    @GET("posts")
    suspend fun getPosts(
        @Query("page") page: Int = 1,
        @Query("limit") limit: Int = 20,
        @Query("q") query: String? = null
    ): Response<ApiResponse<List<PostItem>>>

    @GET("posts/{id}")
    suspend fun getPost(@Path("id") id: Int): Response<ApiResponse<PostDetail>>

    @POST("posts")
    suspend fun createPost(@Body req: PostSaveRequest): Response<ApiResponse<PostItem>>

    @PUT("posts/{id}")
    suspend fun updatePost(@Path("id") id: Int, @Body req: PostSaveRequest): Response<ApiResponse<PostItem>>

    @DELETE("posts/{id}")
    suspend fun deletePost(@Path("id") id: Int, @Query("renumber") renumber: Boolean = false): Response<ApiResponse<Unit>>

    @Multipart
    @POST("media/upload")
    suspend fun uploadMedia(
        @Part file: MultipartBody.Part,
        @Part("type") type: RequestBody
    ): Response<ApiResponse<MediaUploadResponse>>

    @POST("autosaves")
    suspend fun saveAutosave(@Body req: AutosaveRequest): Response<ApiResponse<Unit>>

    @DELETE("autosaves/{postId}")
    suspend fun deleteAutosave(@Path("postId") postId: String): Response<ApiResponse<Unit>>
}
```

---

### Delphi / Windows Desktop Client (THTTPClient)

Эталонный модуль API-клиента для Delphi (VCL / FireMonkey):

```pascal
unit NPBlog.Client;

interface

uses
  System.SysUtils, System.Classes, System.Net.HttpClient, System.Net.URLClient,
  System.Net.Mime, System.JSON;

type
  ENPBlogApiException = class(Exception);

  TNPBlogClient = class
  private
    FBaseUrl: string;
    FAccessToken: string;
    FHttp: THTTPClient;
    function BuildUrl(const AEndpoint: string): string;
    function DoRequest(const AMethod, AEndpoint: string; const ABody: string = ''): TJSONObject;
  public
    constructor Create(const ABaseUrl: string);
    destructor Destroy; override;

    function Login(const APassword, ADeviceName: string): Boolean;
    function GetPosts(const APage, ALimit: Integer; const ASearch: string = ''): TJSONArray;
    function GetPost(const AId: Integer): TJSONObject;
    function CreatePost(const ATitle, AContent: string): Integer;
    function UpdatePost(const AId: Integer; const ATitle, AContent: string): Boolean;
    function UploadImage(const AFilePath: string): string; // Возвращает /data/uploads/...

    property AccessToken: string read FAccessToken write FAccessToken;
  end;

implementation

constructor TNPBlogClient.Create(const ABaseUrl: string);
begin
  inherited Create;
  FBaseUrl := ABaseUrl.TrimRight(['/']);
  FHttp := THTTPClient.Create;
  FHttp.Accept := 'application/json';
end;

destructor TNPBlogClient.Destroy;
begin
  FHttp.Free;
  inherited;
end;

function TNPBlogClient.BuildUrl(const AEndpoint: string): string;
begin
  Result := FBaseUrl + '/' + AEndpoint.TrimLeft(['/']);
end;

function TNPBlogClient.DoRequest(const AMethod, AEndpoint: string; const ABody: string): TJSONObject;
var
  LHeaders: TNetHeaders;
  LResponse: IHTTPResponse;
  LStream: TStringStream;
  LJsonObj: TJSONObject;
begin
  SetLength(LHeaders, 2);
  LHeaders[0] := TNetHeader.Create('Content-Type', 'application/json');
  if FAccessToken <> '' then
    LHeaders[1] := TNetHeader.Create('Authorization', 'Bearer ' + FAccessToken)
  else
    LHeaders[1] := TNetHeader.Create('X-Client', 'NPBlog-Delphi');

  LStream := nil;
  if ABody <> '' then
    LStream := TStringStream.Create(ABody, TEncoding.UTF8);
  try
    if AMethod = 'GET' then
      LResponse := FHttp.Get(BuildUrl(AEndpoint), nil, LHeaders)
    else if AMethod = 'POST' then
      LResponse := FHttp.Post(BuildUrl(AEndpoint), LStream, nil, LHeaders)
    else if AMethod = 'PUT' then
      LResponse := FHttp.Put(BuildUrl(AEndpoint), LStream, nil, LHeaders)
    else
      raise ENPBlogApiException.Create('Unsupported HTTP Method: ' + AMethod);

    LJsonObj := TJSONObject.ParseJSONValue(LResponse.ContentAsString(TEncoding.UTF8)) as TJSONObject;
    if not Assigned(LJsonObj) then
      raise ENPBlogApiException.Create('Invalid JSON response');

    if not LJsonObj.GetValue<Boolean>('success', False) then
      raise ENPBlogApiException.Create(LJsonObj.GetValue<TJSONObject>('error').GetValue<string>('message', 'API Error'));

    Result := LJsonObj;
  finally
    LStream.Free;
  end;
end;

function TNPBlogClient.Login(const APassword, ADeviceName: string): Boolean;
var
  LBody, LRes: TJSONObject;
begin
  LBody := TJSONObject.Create;
  try
    LBody.AddPair('password', APassword);
    LBody.AddPair('device_name', ADeviceName);
    LRes := DoRequest('POST', 'auth/login', LBody.ToString);
    try
      FAccessToken := LRes.GetValue<TJSONObject>('data').GetValue<string>('access_token');
      Result := FAccessToken <> '';
    finally
      LRes.Free;
    end;
  finally
    LBody.Free;
  end;
end;

function TNPBlogClient.GetPosts(const APage, ALimit: Integer; const ASearch: string): TJSONArray;
var
  LRes: TJSONObject;
  LQuery: string;
begin
  LQuery := Format('posts?page=%d&limit=%d', [APage, ALimit]);
  if ASearch <> '' then
    LQuery := LQuery + '&q=' + TURI.URLEncode(ASearch);
  LRes := DoRequest('GET', LQuery);
  try
    Result := LRes.GetValue<TJSONArray>('data').Clone as TJSONArray;
  finally
    LRes.Free;
  end;
end;

function TNPBlogClient.GetPost(const AId: Integer): TJSONObject;
var
  LRes: TJSONObject;
begin
  LRes := DoRequest('GET', 'posts/' + AId.ToString);
  try
    Result := LRes.GetValue<TJSONObject>('data').Clone as TJSONObject;
  finally
    LRes.Free;
  end;
end;

function TNPBlogClient.CreatePost(const ATitle, AContent: string): Integer;
var
  LBody, LRes: TJSONObject;
begin
  LBody := TJSONObject.Create;
  try
    LBody.AddPair('title', ATitle);
    LBody.AddPair('content', AContent);
    LRes := DoRequest('POST', 'posts', LBody.ToString);
    try
      Result := LRes.GetValue<TJSONObject>('data').GetValue<Integer>('id');
    finally
      LRes.Free;
    end;
  finally
    LBody.Free;
  end;
end;

function TNPBlogClient.UpdatePost(const AId: Integer; const ATitle, AContent: string): Boolean;
var
  LBody, LRes: TJSONObject;
begin
  LBody := TJSONObject.Create;
  try
    LBody.AddPair('title', ATitle);
    LBody.AddPair('content', AContent);
    LRes := DoRequest('PUT', 'posts/' + AId.ToString, LBody.ToString);
    try
      Result := True;
    finally
      LRes.Free;
    end;
  finally
    LBody.Free;
  end;
end;

function TNPBlogClient.UploadImage(const AFilePath: string): string;
var
  LMultipart: TMultipartFormData;
  LResponse: IHTTPResponse;
  LJson: TJSONObject;
begin
  LMultipart := TMultipartFormData.Create;
  try
    LMultipart.AddFile('file', AFilePath);
    LMultipart.AddField('type', 'image');

    FHttp.CustomHeaders['Authorization'] := 'Bearer ' + FAccessToken;
    LResponse := FHttp.Post(BuildUrl('media/upload'), LMultipart);
    LJson := TJSONObject.ParseJSONValue(LResponse.ContentAsString(TEncoding.UTF8)) as TJSONObject;
    try
      Result := LJson.GetValue<TJSONObject>('data').GetValue<string>('url'); // /data/uploads/m_xxx.jpg
    finally
      LJson.Free;
    end;
  finally
    LMultipart.Free;
    FHttp.CustomHeaders['Authorization'] := '';
  end;
end;

end.
```

---

### Python CLI / Automation Script

Скрипт для автоматической публикации статьи из папки с локальными изображениями:

```python
import os
import re
import requests

API_BASE = "http://localhost/api/v1"
PASSWORD = "admin_password"

# 1. Авторизация
session = requests.Session()
auth_res = session.post(f"{API_BASE}/auth/login", json={
    "password": PASSWORD,
    "device_name": "Python Publisher CLI"
})
auth_data = auth_res.json()
if not auth_data.get("success"):
    raise Exception(f"Ошибка входа: {auth_data}")

token = auth_data["data"]["access_token"]
session.headers.update({"Authorization": f"Bearer {token}"})
print(f"Успешный вход! Токен: {token[:12]}...")

def upload_image(filepath: str) -> str:
    """Загружает локальный файл и возвращает канонический путь /data/uploads/..."""
    with open(filepath, "rb") as f:
        res = session.post(f"{API_BASE}/media/upload", files={"file": f}, data={"type": "image"})
    data = res.json()
    if not data.get("success"):
        raise Exception(f"Ошибка загрузки {filepath}: {data}")
    return data["data"]["url"] # e.g. /data/uploads/m_xxxxx.jpg

def publish_article(title: str, html_template: str, local_images_dir: str):
    # Поиск и замена локальных картинок на серверные URL
    def replace_img(match):
        img_filename = match.group(1)
        full_path = os.path.join(local_images_dir, img_filename)
        if os.path.exists(full_path):
            server_url = upload_image(full_path)
            print(f"Загружен {img_filename} -> {server_url}")
            return f'src="{server_url}"'
        return match.group(0)

    # Заменяем src="local.jpg" на канонический /data/uploads/m_...
    processed_html = re.sub(r'src=["\']([^"\']+\.(?:jpg|png|webp|gif))["\']', replace_img, html_template)

    # Публикуем статью
    post_res = session.post(f"{API_BASE}/posts", json={
        "title": title,
        "content": processed_html
    })
    post_data = post_res.json()
    if post_data.get("success"):
        print(f"Статья успешно опубликована! ID: {post_data['data']['id']}")
        print(f"Ссылка: {post_data['data']['url']}")
    else:
        print(f"Ошибка публикации: {post_data}")

# Пример использования:
publish_article(
    title="Автоматическая статья из Python",
    html_template="<p>Текст статьи</p><img src=\"cover.jpg\" />",
    local_images_dir="./my_article_assets"
)
```

---

## 9. Сетевая устойчивость, Offline-режим и диагностика

### Очередь отложенных публикаций (Offline Queue)
При работе на мобильных устройствах или в нестабильных сетях кастомный редактор должен реализовывать **Offline Outbox Pattern**:
1. При отсутствии интернет-соединения операция сохранения помещается в локальную очередь SQLite / Room / IndexedDB со статусом `pending`.
2. Приложение слушает событие восстановления сети (`NetworkCallback` в Android, `NWPathMonitor` в iOS, `window.addEventListener('online')` в Web).
3. При появлении интернета очередь последовательно синхронизируется с сервером.
4. В случае конфликта (статья была изменена на сервере другим клиентом позже даты локальной правки) клиенту выводится окно сравнения версий.

---

### Самодиагностика и восстановление целостности
Если в клиенте возникли ошибки ненайденных файлов или нарушился реестр:
- `GET /api/v1/system/status` — возвращает статус записи в папки (`blog`, `uploads`, `drafts`), свободное место на диске и версию PHP.
- `GET /api/v1/system/integrity` — проверяет соответствие записей в `posts-meta.json` реальным файлам `post-*.html` на диске.
- `POST /api/v1/system/integrity/fix` — автоматически восстанавливает поврежденный файл `posts-meta.json`, сканируя все HTML-файлы статей на сервере.

---

### Сервисные операции: перенумерация и перегенерация
- `POST /api/v1/posts/renumber` — восстанавливает сплошную нумерацию постов (1, 2, 3...) после множественных удалений.
- `POST /api/v1/posts/regenerate` — пересобирает все HTML-страницы блога с обновленным шаблоном.

---

## 10. Чек-лист готовности кастомного редактора (QA Checklist)

Перед релизом вашего кастомного клиента проверьте его по следующему списку критериев:

- [ ] **1. Защищенное хранение**: `access_token` хранится в зашифрованном хранилище ОС (KeyStore/Keychain/DPAPI), а не в открытом виде.
- [ ] **2. Обработка 401**: При получении ошибки `401 Unauthorized` клиент очищает невалидный токен и перенаправляет пользователя на экран ввода пароля.
- [ ] **3. Обработка 429 Lockout**: При блокировке IP отображается таймер обратного отсчета на основе поля `lockout_time_remaining`.
- [ ] **4. Идентификация устройства**: При вызове `/auth/login` передается осмысленное имя `device_name` (например: *"NPBlog Studio v1.2 (MacBook Pro)"*).
- [ ] **5. Канонические пути медиа**: В разметку статьи вставляются ссылки вида `/data/uploads/m_xxxx.jpg`. Клиент **НЕ** отправляет ссылки с префиксом `/api/`.
- [ ] **6. Изоляция шаблонов**: Клиент отображает в редакторе только поле `content`, не показывая пользователю шапку и подвал блога.
- [ ] **7. Автосохранение**: Реализован фоновый дебаунс-таймер (15–30 сек) с отправкой на `POST /api/v1/autosaves`.
- [ ] **8. Защита от потери данных**: При открытии статьи проверяется наличие более свежего автосохранения (`GET /api/v1/autosaves/{id}`).
- [ ] **9. Очистка автосохранения**: После успешной публикации (`PUT` или `POST`) вызывается `DELETE /api/v1/autosaves/{id}`.
- [ ] **10. Буфер обмена (Paste/Drop)**: Поддерживается вставка картинок из буфера обмена (Ctrl+V / Cmd+V) и Drag-and-Drop с фоновой отправкой в `POST /media/upload`.
- [ ] **11. Версионирование**: Реализован интерфейс просмотра ревизий (`/backups/{id}`) с возможностью предпросмотра и отката (`/restore`).
- [ ] **12. Живой поиск и пагинация**: Список публикаций поддерживает поиск по заголовку и постраничную подгрузку.
- [ ] **13. Управление сессиями**: Предусмотрена кнопка логаута (`/auth/logout`) и список активных устройств (`/auth/tokens`).
- [ ] **14. Валидация форм**: Заголовок и контент валидируются на клиенте перед отправкой (запрет публикации пустого заголовка).
- [ ] **15. Обработка обрывов сети**: При потере соединения редактор не сбрасывает введенный текст и предупреждает автора.
