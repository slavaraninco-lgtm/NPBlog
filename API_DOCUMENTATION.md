#  NPBlog REST API

Добро пожаловать в официальную документацию REST API для CMS и редактора **NPBlog**. 

API предоставляет полный доступ ко всем функциям блога и редактора: аутентификация с Bearer-токенами, управление статьями, версионирование (бэкапы), черновики, автосохранения, медиа-хранилище (multipart и Base64), шаблоны, сниппеты, настройки блога и системная диагностика.

---

##  Содержание
1. [Быстрый старт и интерактивная документация (Swagger UI)](#-быстрый-старт-и-интерактивная-документация)
2. [Базовый URL и версионирование](#-базовый-url-и-версионирование)
3. [Архитектура безопасности и авторизация](#-архитектура-безопасности-и-авторизация)
   - [Bearer Token авторизация](#bearer-token-авторизация)
   - [Защита от перебора паролей (Lockout)](#защита-от-перебора-паролей-lockout)
   - [Белый список IP-адресов](#белый-список-ip-адресов)
4. [Формат ответов и коды ошибок](#-формат-ответов-и-коды-ошибок)
5. [Полный каталог эндпоинтов](#-полный-каталог-эндпоинтов)
   - [1. Аутентификация и безопасность (`/auth`)](#1-аутентификация-и-безопасность)
   - [2. Статьи и публикации (`/posts`)](#2-статьи-и-публикации)
   - [3. Черновики и автосохранения (`/drafts`, `/autosaves`)](#3-черновики-и-автосохранения)
   - [4. Резервные копии и версионирование (`/backups`)](#4-резервные-копии-и-версионирование)
   - [5. Медиафайлы, фоны, смайлы, шрифты (`/media`) и интеграция со сторонними редакторами](#5-медиафайлы-фоны-смайлы-шрифты-media-и-интеграция-со-сторонними-редакторами)
   - [6. Шаблоны и сниппеты (`/templates`, `/includes`)](#6-шаблоны-и-сниппеты)
   - [7. Настройки редактора и блога (`/settings`)](#7-настройки-редактора-и-блога)
   - [8. Системная диагностика и история (`/system`)](#8-системная-диагностика-и-история)
6. [Готовые примеры кода для мобильных платформ](#-готовые-примеры-кода-для-мобильных-платформ)
   - [Flutter / Dart](#flutter--dart)
   - [Swift / iOS (URLSession & Codable)](#swift--ios-urlsession--codable)
   - [Kotlin / Android (Retrofit 2 & Coroutines)](#kotlin--android-retrofit-2--coroutines)
   - [React Native / TypeScript (Axios)](#react-native--typescript-axios)
7. [🛠️ Руководство по разработке кастомных клиентов редактора](CUSTOM_CLIENT_GUIDE.md)

---

##  Быстрый старт и интерактивная документация

Вы можете интерактивно протестировать любые эндпоинты API прямо в браузере через встроенный **Swagger UI**:

- **Swagger UI**: `http://localhost/api/docs/`
- **Спецификация OpenAPI 3.1.0**: `http://localhost/api/docs/openapi.json`
- **Руководство по созданию кастомных клиентов редактора**: [CUSTOM_CLIENT_GUIDE.md](CUSTOM_CLIENT_GUIDE.md) — подробный архитектурный справочник с примерами на TypeScript, Kotlin, Delphi и Python.

Файл спецификации `openapi.json` можно напрямую импортировать в **Postman**, **Insomnia** или использовать в `openapi-generator-cli` для автоматической генерации клиентского кода на любом языке (Dart, Swift, Kotlin, TypeScript).

---

##  Базовый URL и версионирование

Все эндпоинты API версионированы и доступны по базовому пути:

```
http://<ваш-хост>/api/v1
```

Поддерживаются как «красивые» пути через rewrite (например `/api/v1/posts`), так и fallback-синтаксис query-параметров `/api/index.php?_route=v1/posts` (гарантирует 100% совместимость на любых веб-серверах).

---

##  Архитектура безопасности и авторизация

### Bearer Token авторизация
В отличие от веб-редактора, мобильные приложения не зависят от cookies и сессий браузера. 
1. При первом запуске мобильное приложение запрашивает у пользователя пароль доступа к блогу.
2. Приложение отправляет запрос:
   ```http
   POST /api/v1/auth/login
   Content-Type: application/json

   {
     "password": "ваш_пароль_к_блогу",
     "device_name": "iPhone 15 Pro (Flutter)"
   }
   ```
3. Сервер генерирует криптографически стойкий 64-символьный токен доступа (`npb_...`), срок действия которого по умолчанию составляет **30 дней** с автоматическим продлением при активности.
4. Токен сохраняется в безопасном хранилище устройства (**Flutter Secure Storage**, **iOS Keychain**, **Android EncryptedSharedPreferences**).
5. Все последующие запросы отправляются с HTTP-заголовком:
   ```http
   Authorization: Bearer npb_...
   ```
   *(Также поддерживается альтернативный заголовок `X-API-Token: npb_...` или query-параметр `?api_token=npb_...` для скачивания/предпросмотра картинок в WebView).*

### Защита от перебора паролей (Lockout)
- При 3 неверных попытках ввода пароля подряд IP-адрес блокируется на **15 минут**.
- При каждой неудачной попытке API сообщает количество оставшихся попыток.
- При блокировке API возвращает HTTP-код `429 Too Many Requests` и поле `lockout_time_remaining` (в секундах), что позволяет приложению запустить таймер обратного отсчета.

### Белый список IP-адресов
Если в блоге активирована настройка `ip_whitelist_enabled`, доступ разрешается только IP-адресам из файла `allowed_ips.txt`. В противном случае возвращается `403 Forbidden`.

---

##  Формат ответов и коды ошибок

Все ответы API строго стандартизированы в единую JSON-структуру.

### Успешный ответ (`HTTP 200 / 201`):
```json
{
  "success": true,
  "status": 200,
  "message": "Статья успешно создана",
  "data": {
    "id": 15,
    "title": "Заголовок статьи"
  },
  "meta": {
    "total": 100,
    "page": 1,
    "limit": 20,
    "total_pages": 5
  }
}
```

### Ответ при ошибке (`HTTP 400 / 401 / 403 / 404 / 422 / 429 / 500`):
```json
{
  "success": false,
  "status": 401,
  "error": {
    "code": "unauthorized",
    "message": "Необходима авторизация. Передайте валидный токен в заголовке Authorization: Bearer <token>",
    "details": null
  }
}
```

### Справочник HTTP статус-кодов:
| Код | Описание |
|---|---|
| `200 OK` | Запрос успешно обработан. |
| `201 Created` | Сущность (статья, черновик, файл) успешно создана. |
| `204 No Content` | Успешно обработан CORS preflight запрос `OPTIONS`. |
| `400 Bad Request` | Некорректный синтаксис запроса или неверные параметры. |
| `401 Unauthorized` | Отсутствует, истек или передан неверный токен авторизации. |
| `403 Forbidden` | Доступ запрещен (IP не в белом списке). |
| `404 Not Found` | Запрашиваемая статья, файл или бэкап не найден. |
| `405 Method Not Allowed` | Метод HTTP не поддерживается данным эндпоинтом. |
| `422 Unprocessable Entity` | Ошибка валидации (пустой заголовок, недопустимое расширение файла). |
| `429 Too Many Requests` | Превышен лимит попыток авторизации (активен Lockout). |
| `500 Server Error` | Внутренняя ошибка сервера (ошибка записи на диск и т.д.). |

---

##  Полный каталог эндпоинтов

### 1. Аутентификация и безопасность

#### `POST /v1/auth/login` — Авторизация
- **Авторизация**: Не требуется
- **Тело запроса**:
  ```json
  {
    "password": "ваш_пароль",
    "device_name": "Google Pixel 8 (Android)"
  }
  ```
- **Успешный ответ (`200 OK`)**:
  ```json
  {
    "success": true,
    "status": 200,
    "message": "Авторизация успешна",
    "data": {
      "access_token": "npb_9bc3d99d8ea3b4c9e1c81bc0d11505c733fac79dfeb78680911f589b779b0e39",
      "token_type": "Bearer",
      "token_id": "tok_5588d87a8c890fba",
      "device_name": "Google Pixel 8 (Android)",
      "expires_at": 1791735875,
      "expires_in": 2592000,
      "created_at": 1789143875
    }
  }
  ```

#### `POST /v1/auth/logout` — Выход
- **Авторизация**: `Bearer <token>`
- Отзывает текущий токен устройства.

#### `GET /v1/auth/me` — Проверка сессии
- **Авторизация**: `Bearer <token>`
- Возвращает статус авторизации, версию CMS и свойства текущего токена.

#### `GET /v1/auth/tokens` — Список активных устройств
- **Авторизация**: `Bearer <token>`
- Возвращает список всех авторизованных мобильных сессий с датами входа и флагом `is_current`.

#### `DELETE /v1/auth/tokens/{id}` — Удаление устройства
- **Авторизация**: `Bearer <token>`
- Отзывает токен конкретного устройства по его `id` (например `tok_5588d87a8c890fba`).

#### `POST /v1/auth/change-password` — Смена пароля блога
- **Авторизация**: `Bearer <token>`
- **Тело запроса**:
  ```json
  {
    "current_password": "старый_пароль",
    "new_password": "новый_пароль_от_6_символов"
  }
  ```

---

### 2. Статьи и публикации

#### `GET /v1/posts` — Список статей
- **Параметры запроса**:
  - `page` (int, по умолчанию `1`) — номер страницы.
  - `limit` (int, по умолчанию `20`, макс `100`) — элементов на странице.
  - `q` (string) — поиск по заголовку или ID.
  - `sort` (enum: `id_desc`, `id_asc`, `date_desc`, `date_asc`, по умолчанию `id_desc`).
- **Пример ответа**:
  ```json
  {
    "success": true,
    "status": 200,
    "data": [
      {
        "id": 1,
        "title": "Добро пожаловать в NPBlog",
        "date": "11.09.2026 19:30",
        "filename": "post-1.html",
        "url": "/data/blog/post-1.html"
      }
    ],
    "meta": {
      "total": 1,
      "page": 1,
      "limit": 20,
      "total_pages": 1
    }
  }
  ```

#### `GET /v1/posts/{id}` — Чтение статьи
- **Параметры**: `id` статьи (целое число).
- **Ответ**: возвращает метаданные, чистый HTML контент для редактора (`content`), индивидуальный фон (`background`) и прямую ссылку.

#### `POST /v1/posts` — Создание статьи
- **Тело запроса**:
  ```json
  {
    "title": "Моя новая статья",
    "content": "<p>Основной текст статьи с разметкой</p>",
    "date": "11.09.2026 19:30"
  }
  ```
- **Действия**: автоматически определяет следующий ID, оборачивает контент в активный HTML-шаблон, обновляет метаданные `posts-meta.json`, создает резервную копию версии №1 и генерирует RSS.

#### `PUT /v1/posts/{id}` — Обновление статьи
- **Параметры**: `id` статьи.
- **Тело запроса**: `title`, `content`, `date` (опционально).
- **Действия**: обновляет HTML, автоматически создает новую инкрементную версию бэкапа (например `1-2.html`), сохраняет метаданные и перегенерирует RSS.

#### `DELETE /v1/posts/{id}` — Удаление статьи
- **Параметры запроса**:
  - `renumber` (bool, по умолчанию `false`) — если `true`, после удаления автоматически перенумерует оставшиеся статьи по порядку.

#### `GET /v1/posts/{id}/preview` — HTML-предпросмотр для мобильного WebView
- Возвращает готовый отрендеренный HTML со всеми стилями и скриптами для отображения в компоненте WebView мобильного приложения.

#### `POST /v1/posts/renumber` — Сквозная перенумерация
- Перенумеровывает все статьи от 1 до N, переименовывает файлы, фоновые изображения и бэкапы.

#### `POST /v1/posts/regenerate` — Перегенерация всех статей блога
- Пересобирает все HTML файлы статей блога из активных шаблонов.

---

### 3. Черновики и автосохранения

#### `GET /v1/drafts` — Список черновиков
- Возвращает массив черновиков, отсортированных по дате (новые первыми).

#### `POST /v1/drafts` — Сохранение черновика
- **Тело запроса**: `{"title": "Черновик", "content": "<p>...</p>"}`
- Создает файл в папке черновиков с меткой времени.

#### `GET /v1/drafts/{filename}` — Чтение черновика
#### `DELETE /v1/drafts/{filename}` — Удаление черновика

#### `GET /v1/autosaves` — Список всех автосохранений
#### `GET /v1/autosaves/{postId}` — Получение автосохранения для статьи
#### `POST /v1/autosaves` — Сохранение автосохранения статьи
- **Тело**: `{"post_id": "1", "title": "Заголовок", "content": "<p>Текст</p>"}`
#### `DELETE /v1/autosaves/{postId}` — Удаление автосохранения статьи
#### `DELETE /v1/autosaves` — Полная очистка всех автосохранений

---

### 4. Резервные копии и версионирование

#### `GET /v1/backups` — Реестр резервных копий
- Возвращает все резервные копии, сгруппированные по ID статей.

#### `GET /v1/backups/{postId}` — Список версий статьи
- Возвращает массив версий конкретной статьи (номер версии, дата, имя файла).

#### `GET /v1/backups/{postId}/{backupNumber}` — Контент версии бэкапа
- Возвращает текст и HTML выбранной версии.

#### `POST /v1/backups/{postId}/{backupNumber}/restore` — Восстановление из бэкапа
- Мгновенно откатывает активную статью к выбранной версии.

#### `DELETE /v1/backups/{postId}/{backupNumber}` — Удаление версии бэкапа

---

### 5. Медиафайлы, фоны, смайлы, шрифты (`/media`) и интеграция со сторонними редакторами

> [!IMPORTANT]
> **ПРАВИЛО АДРЕСАЦИИ МЕДИАФАЙЛОВ: ПАПКА `/data/`, А НЕ `/api/`**
> - Все статические медиафайлы (изображения, видео, аудио, смайлы, фоны, шрифты) физически размещаются и отдаются веб-сервером **напрямую из папки `/data/`** (например `/data/uploads/...`, `/data/backgrounds/...`, `/data/smiles/...`).
> - Эндпоинты API `/api/v1/media/...` служат **исключительно для операций управления** (прием загружаемых файлов, получение каталога, удаление). Сами медиафайлы **НЕ должны** адресоваться через префикс `/api/`!
> - Ссылки, вставляемые в статьи, **ОБЯЗАНЫ ссылаться на папку `/data/`**:
>   - ✅ **Правильно**: `<img src="/data/uploads/m_6aa42c.jpg">` или `data/uploads/m_6aa42c.jpg`
>   - ❌ **Неправильно**: `<img src="/api/data/uploads/m_6aa42c.jpg">` или `<img src="/api/v1/media/uploads/m_6aa42c.jpg">`
> 
> *Автоматическая нормализация на сервере*: Даже если сторонний редактор или мобильный клиент по ошибке отправит в теле статьи ссылку с префиксом `/api/data/` или с полным внешним доменом (`http://domain.com/data/uploads/...`), серверный обработчик `PostsController` и `formatArticleContent()` автоматически перехватит, очистит и сохранит канонический путь `/data/uploads/...` при записи HTML на диск.

#### Поля URL в ответах API

При загрузке файла (`POST /v1/media/upload`) или запросе списка файлов (`GET /v1/media`) сервер возвращает расширенный набор полей адресации:

| Поле | Пример значения | Назначение и использование |
|---|---|---|
| `url` | `/data/uploads/m_6aa42c.jpg` | **Канонический относительный URL.** Именно его следует передавать в сторонний HTML-редактор и вставлять в атрибут `src` тегов `<img>`, `<video>`, `<audio>`. Гарантирует переносимость блога между серверами, IP-адресами и протоколами (HTTP/HTTPS). |
| `absolute_url` | `http://example.com/data/uploads/m_6aa42c.jpg` | **Полный абсолютный URL с протоколом и доменом.** Используется для прямого рендеринга картинок в сторонних редакторах, мобильных WebView, нативных виджетах `Image.network()` (Flutter), `AsyncImage` (SwiftUI) или `Glide/Coil` (Android). |
| `data_path` | `data/uploads/m_6aa42c.jpg` | **Внутренний файловый путь** относительно корня проекта. |

---

#### `GET /v1/media` — Список файлов
- **Параметр**: `type` (`all`, `images`, `video`, `audio`, `documents`, `fonts`).
- Возвращает имя файла, размер в байтах, дату загрузки и прямой URL.

#### `POST /v1/media/upload` — Универсальная загрузка медиафайла
Поддерживает два режима передачи:
1. **Multipart/form-data**:
   - Поле `file` (бинарный файл).
   - Поле `type` (`image`, `video`, `audio`, `document`, `font`).
2. **Base64 JSON (идеально для фото с камеры телефона)**:
   ```json
   {
     "base64": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
     "filename": "camera_photo.jpg",
     "type": "image"
   }
   ```
- **Ответ (`201 Created`)**:
  ```json
  {
    "success": true,
    "status": 201,
    "message": "Файл успешно загружен в папку data/uploads/",
    "data": {
      "file_name": "m_6aa42c.jpg",
      "url": "/data/uploads/m_6aa42c.jpg",
      "absolute_url": "http://localhost/data/uploads/m_6aa42c.jpg",
      "data_path": "data/uploads/m_6aa42c.jpg",
      "size": 245120,
      "mime_type": "image/jpeg",
      "type": "image",
      "created_at": "2026-09-11T19:30:00+03:00"
    }
  }
  ```

---

####  Инструкция по интеграции со сторонними редакторами

При интеграции API с любым сторонним визуальным редактором (TinyMCE, Quill, Summernote, Flutter HTML Editor, React Native Rich Editor) используйте следующую регламентированную последовательность:

1. **Перехват загрузки изображения в редакторе**:
   - Настройте кастомный обработчик вставки изображений (upload handler / image handler).
2. **Отправка файла на сервер блога**:
   - Отправьте POST-запрос на `/api/v1/media/upload` с заголовком `Authorization: Bearer <token>`.
3. **Получение пути к файлу**:
   - Из ответа возьмите поле `data.url` (значение вида `/data/uploads/photo.jpg`).
   - Для прямого предпросмотра внутри мобильного WebView или нативного редактора используйте `data.absolute_url`.
4. **Вставка в контент статьи**:
   - Вставьте в HTML разметку статьи тег: `<img src="/data/uploads/m_6aa42c.jpg" alt="Описание" />`.
   - **Важно:** Передавайте в атрибут `src` именно путь `/data/uploads/...`. Не используйте префикс `/api/`!
5. **Сохранение статьи**:
   - Передайте сформированный HTML в теле запроса `POST /v1/posts` или `PUT /v1/posts/{id}`. Сервер гарантирует сохранение ссылок на `/data/uploads/` и автоматическую валидацию.

##### Пример: Интеграция с TinyMCE 6 / 7
```javascript
tinymce.init({
  selector: '#editor',
  plugins: 'image link media',
  toolbar: 'undo redo | formatselect | bold italic | image media link',
  images_upload_handler: (blobInfo, progress) => new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'http://localhost/api/v1/media/upload');
    xhr.setRequestHeader('Authorization', 'Bearer ' + userAccessToken);
    
    xhr.onload = () => {
      if (xhr.status !== 201) {
        reject('HTTP Error: ' + xhr.status);
        return;
      }
      const json = JSON.parse(xhr.responseText);
      if (!json || !json.data || !json.data.url) {
        reject('Invalid JSON response: ' + xhr.responseText);
        return;
      }
      // Передаем канонический URL из папки /data/ в TinyMCE:
      resolve(json.data.url); // -> /data/uploads/m_xxxxxx.jpg
    };
    
    const formData = new FormData();
    formData.append('file', blobInfo.blob(), blobInfo.filename());
    formData.append('type', 'image');
    xhr.send(formData);
  })
});
```

##### Пример: Интеграция с Quill.js
```javascript
const quill = new Quill('#editor', { theme: 'snow', modules: { toolbar: ['bold', 'italic', 'image'] } });

quill.getModule('toolbar').addHandler('image', () => {
  const input = document.createElement('input');
  input.setAttribute('type', 'file');
  input.setAttribute('accept', 'image/*');
  input.click();

  input.onchange = async () => {
    const file = input.files[0];
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', 'image');

    const res = await fetch('http://localhost/api/v1/media/upload', {
      method: 'POST',
      headers: { 'Authorization': 'Bearer ' + userAccessToken },
      body: formData
    });
    const result = await res.json();
    
    if (result.success) {
      const range = quill.getSelection(true);
      // Вставляем изображение с каноническим URL из папки data:
      quill.insertEmbed(range.index, 'image', result.data.url);
    }
  };
});
```

##### Пример: Интеграция во Flutter (HTML Editor / WebView)
```dart
Future<void> insertImageFromGallery(HtmlEditorController controller) async {
  final picker = ImagePicker();
  final XFile? image = await picker.pickImage(source: ImageSource.gallery);
  if (image == null) return;

  final bytes = await image.readAsBytes();
  final base64String = 'data:image/jpeg;base64,' + base64Encode(bytes);

  // Загружаем в NPBlog API
  final uploadRes = await apiService.uploadMediaBase64(
    base64String, 
    image.name, 
    type: 'image'
  );

  if (uploadRes['success'] == true) {
    final String dataUrl = uploadRes['data']['url']; // /data/uploads/...
    // Вставляем тег изображения с ссылкой на data в HTML редактор
    controller.insertHtml('<img src="$dataUrl" style="max-width:100%; height:auto;" />');
  }
}
```

---

#### `DELETE /v1/media` — Удаление файла
- **Тело запроса**: `{"filename": "m_6aa42c.jpg", "type": "image"}`

#### `GET /v1/media/backgrounds` — Фоны статей
#### `POST /v1/media/backgrounds` — Установка фона для статьи
- **Тело запроса**:
  ```json
  {
    "post_id": 1,
    "background": "/data/uploads/bg.jpg",
    "repeat": "no-repeat",
    "size": "cover",
    "position": "center",
    "overlay_color": "#000000",
    "overlay_opacity": 0.4
  }
  ```
#### `DELETE /v1/media/backgrounds/{postId}` — Удаление фона статьи

#### `GET /v1/media/smiles` — Список наборов смайлов и стикеров
- **Авторизация**: `Bearer <token>`
- Возвращает массив всех наборов смайлов, количество картинок в каждом и их прямые и абсолютные URL.

#### `POST /v1/media/smiles` — Загрузка набора смайлов или добавление файлов
- **Авторизация**: `Bearer <token>`
- Поддерживает два формата передачи:
  1. **Multipart/form-data**:
     - `setName` (string, обязательно) — имя набора (например `kolobok` или `аниме`).
     - `smiles[]` или `file` — файлы картинок (`.gif`, `.png`, `.webp`, `.svg`, `.jpg`).
  2. **JSON Base64**:
     ```json
     {
       "setName": "kolobok",
       "items": [
         { "filename": "smile1.gif", "base64": "data:image/gif;base64,R0lGODlh..." },
         { "filename": "smile2.gif", "base64": "data:image/gif;base64,R0lGODlh..." }
       ]
     }
     ```
- **Ответ (`201 Created`)**: возвращает `set_name`, `count` и массив загруженных элементов с `url` (`/data/smiles/{setName}/...`).

#### `DELETE /v1/media/smiles/{setName}` — Удаление набора смайлов
- **Авторизация**: `Bearer <token>`
- Полностью удаляет директорию набора смайлов `data/smiles/{setName}/` со всеми файлами.

#### `GET /v1/media/fonts` — Список шрифтов и готовый CSS `@font-face`
- **Авторизация**: `Bearer <token>`
- Возвращает массив файлов шрифтов (`.ttf`, `.otf`, `.woff`, `.woff2`) и готовый блок стилей `css` со всеми правилами `@font-face` для мгновенного внедрения в редактор клиента.

#### `DELETE /v1/media/fonts/{filename}` — Удаление пользовательского шрифта
- **Авторизация**: `Bearer <token>`
- Удаляет файл шрифта из директории `data/fonts/`. Также удаление шрифтов поддерживается через общий метод `DELETE /v1/media` с телом `{"filename": "Roboto.ttf", "type": "font"}`.

---

### 6. Шаблоны и сниппеты

#### `GET /v1/templates` — Список HTML-шаблонов
#### `GET /v1/templates/{name}` — Код шаблона
#### `POST /v1/templates` — Сохранение шаблона (`name`, `title`, `description`, `code`)
#### `POST /v1/templates/apply` — Применение шаблона к статье или ко всему блогу
#### `DELETE /v1/templates/{name}` — Удаление шаблона

#### `GET /v1/includes` — Список текстовых сниппетов (вставок)
#### `GET /v1/includes/{name}` — Содержимое сниппета
#### `POST /v1/includes` — Сохранение сниппета (`display_name`, `content`)
#### `DELETE /v1/includes/{name}` — Удаление сниппета

---

### 7. Настройки редактора и блога

#### `GET /v1/settings/editor` — Настройки редактора
- Возвращает ширину контента (`contentWidth`), тему (`amoledTheme`), интервал автосохранения (`autosaveInterval`), язык (`language`), параметры RSS.

#### `PUT /v1/settings/editor` — Обновление настроек редактора
- Принимает обновляемые поля (например `{"contentWidth": 920, "amoledTheme": true}`).

#### `GET /v1/settings/global` — Глобальные параметры блога
#### `PUT /v1/settings/global` — Обновление глобальных параметров блога

---

### 8. Системная диагностика и история

#### `GET /v1/system/status` — Диагностика сервера
- Возвращает версию блога, версию PHP, используемую ОС, количество статей, файлов и черновиков, свободное и общее место на диске, статус доступности папок на запись.

#### `GET /v1/system/integrity` — Проверка целостности
- Проверяет наличие всех HTML-файлов для записей в `posts-meta.json` и шаблонов.

#### `POST /v1/system/integrity/fix` — Автовосстановление целостности
- Автоматически восстанавливает `posts-meta.json` из существующих файлов на диске.

#### `GET /v1/system/languages` — Доступные языки интерфейса
#### `GET /v1/system/history` — Стек отмены/повтора (undo/redo)
#### `POST /v1/system/history` — Сохранение истории
#### `DELETE /v1/system/history` — Очистка истории

---

## 💻 Готовые примеры кода для мобильных платформ

### Flutter / Dart

Используйте пакеты `dio` и `flutter_secure_storage`:

```dart
import 'dart:convert';
import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class NPBlogApiService {
  static const String baseUrl = 'http://YOUR_SERVER_IP/api/v1';
  final Dio _dio = Dio(BaseOptions(baseUrl: baseUrl));
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  NPBlogApiService() {
    // Автоматическое добавление Bearer токена ко всем запросам
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _storage.read(key: 'npblog_access_token');
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        return handler.next(options);
      },
    ));
  }

  /// 1. Авторизация в приложении
  Future<bool> login(String password, String deviceName) async {
    try {
      final response = await _dio.post('/auth/login', data: {
        'password': password,
        'device_name': deviceName,
      });

      if (response.data['success'] == true) {
        final token = response.data['data']['access_token'];
        await _storage.write(key: 'npblog_access_token', value: token);
        return true;
      }
      return false;
    } catch (e) {
      print('Login error: $e');
      return false;
    }
  }

  /// 2. Получение списка статей
  Future<List<dynamic>> getPosts({int page = 1, int limit = 20, String? query}) async {
    final response = await _dio.get('/posts', queryParameters: {
      'page': page,
      'limit': limit,
      if (query != null) 'q': query,
    });
    return response.data['data'];
  }

  /// 3. Создание статьи
  Future<int> createPost(String title, String htmlContent) async {
    final response = await _dio.post('/posts', data: {
      'title': title,
      'content': htmlContent,
    });
    return response.data['data']['id'];
  }

  /// 4. Загрузка фото с камеры устройства (Base64 или Multipart)
  Future<String> uploadImageFile(File imageFile) async {
    String fileName = imageFile.path.split('/').last;
    FormData formData = FormData.fromMap({
      'file': await MultipartFile.fromFile(imageFile.path, filename: fileName),
      'type': 'image',
    });

    final response = await _dio.post('/media/upload', data: formData);
    return response.data['data']['url'];
  }

  /// 5. Выход из аккаунта
  Future<void> logout() async {
    try {
      await _dio.post('/auth/logout');
    } finally {
      await _storage.delete(key: 'npblog_access_token');
    }
  }
}
```

---

### Swift / iOS (URLSession & Codable)

```swift
import Foundation

class NPBlogClient {
    static let shared = NPBlogClient()
    let baseURL = URL(string: "http://YOUR_SERVER_IP/api/v1")!
    private var accessToken: String? {
        get { UserDefaults.standard.string(forKey: "npblog_token") }
        set { UserDefaults.standard.set(newValue, forKey: "npblog_token") }
    }

    struct ApiResponse<T: Codable>: Codable {
        let success: Bool
        let status: Int
        let message: String?
        let data: T?
    }

    struct LoginData: Codable {
        let access_token: String
        let token_type: String
        let expires_at: Int
    }

    struct PostItem: Codable {
        let id: Int
        let title: String
        let date: String
        let filename: String
    }

    // Авторизация
    func login(password: String, deviceName: String) async throws -> Bool {
        var request = URLRequest(url: baseURL.appendingPathComponent("auth/login"))
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        
        let body = ["password": password, "device_name": deviceName]
        request.httpBody = try JSONSerialization.data(withJSONObject: body)

        let (data, _) = try await URLSession.shared.data(for: request)
        let response = try JSONDecoder().decode(ApiResponse<LoginData>.self, from: data)

        if let token = response.data?.access_token {
            self.accessToken = token
            return true
        }
        return false
    }

    // Запрос списка статей
    func fetchPosts() async throws -> [PostItem] {
        var request = URLRequest(url: baseURL.appendingPathComponent("posts"))
        request.httpMethod = "GET"
        if let token = self.accessToken {
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }

        let (data, _) = try await URLSession.shared.data(for: request)
        let response = try JSONDecoder().decode(ApiResponse<[PostItem]>.self, from: data)
        return response.data ?? []
    }
}
```

---

### Kotlin / Android (Retrofit 2 & Coroutines)

```kotlin
import okhttp3.Interceptor
import okhttp3.OkHttpClient
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import retrofit2.http.*

data class ApiResponse<T>(
    val success: Boolean,
    val status: Int,
    val message: String?,
    val data: T?
)

data class LoginRequest(val password: String, val device_name: String)
data class LoginResponse(val access_token: String, val token_type: String)
data class PostItem(val id: Int, val title: String, val date: String, val url: String)

interface NPBlogApi {
    @POST("auth/login")
    suspend fun login(@Body req: LoginRequest): ApiResponse<LoginResponse>

    @GET("posts")
    suspend fun getPosts(
        @Query("page") page: Int = 1,
        @Query("limit") limit: Int = 20,
        @Query("q") search: String? = null
    ): ApiResponse<List<PostItem>>

    @POST("posts")
    suspend fun createPost(@Body req: Map<String, String>): ApiResponse<Map<String, Any>>
}

class ApiClient(private val tokenProvider: () -> String?) {
    private val client = OkHttpClient.Builder()
        .addInterceptor(Interceptor { chain ->
            val reqBuilder = chain.request().newBuilder()
            tokenProvider()?.let { token ->
                reqBuilder.addHeader("Authorization", "Bearer $token")
            }
            chain.proceed(reqBuilder.build())
        })
        .build()

    val api: NPBlogApi = Retrofit.Builder()
        .baseUrl("http://YOUR_SERVER_IP/api/v1/")
        .client(client)
        .addConverterFactory(GsonConverterFactory.create())
        .build()
        .create(NPBlogApi::class.java)
}
```

---

### React Native / TypeScript (Axios)

```typescript
import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

const api = axios.create({
  baseURL: 'http://YOUR_SERVER_IP/api/v1',
  headers: {
    'Content-Type': 'application/json',
  },
});

// Автоматическая подстановка токена
api.interceptors.request.use(async (config) => {
  const token = await AsyncStorage.getItem('@npblog_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export const NPBlogService = {
  login: async (password: string, deviceName = 'React Native Client') => {
    const res = await api.post('/auth/login', { password, device_name: deviceName });
    if (res.data.success) {
      await AsyncStorage.setItem('@npblog_token', res.data.data.access_token);
      return res.data.data;
    }
    throw new Error(res.data.error?.message || 'Login failed');
  },

  getPosts: async (page = 1, limit = 20, q?: string) => {
    const res = await api.get('/posts', { params: { page, limit, q } });
    return res.data.data;
  },

  createPost: async (title: string, content: string) => {
    const res = await api.post('/posts', { title, content });
    return res.data.data;
  },

  logout: async () => {
    try {
      await api.post('/auth/logout');
    } finally {
      await AsyncStorage.removeItem('@npblog_token');
    }
  }
};
```

---

## 🛠️ Тестирование и отладка

Для запуска автоматизированного тестового набора всех 30+ эндпоинтов выполните команду:
```powershell
php C:\Users\ftod\.gemini\antigravity-ide\brain\a90236db-e452-4e15-aa19-7503d7d59fbe\scratch\test_all_api.php
```

Все тесты проверяют реальные сетевые запросы к локальному серверу и валидируют структуру каждого ответа.
