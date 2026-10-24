# 🤖 AGENTS.md — AI Agent Directives & Repository Standards

This document defines operational constraints, architectural standards, and mandatory protocols for any AI agent (Claude, Gemini, GPT, etc.) working in the **NPBlog** repository.

**Communication language:** reply to the user in the language they write in (default: Russian). Code, identifiers and commit messages follow the conventions of the surrounding files.

---

## 🎯 1. Information Gathering & Clarification Protocol

### 1.1 Ask Only When It Matters
Do not interrogate the user about trivia. Split decisions into two classes:

**Critical — you MUST ask before writing code:**
- file storage layout and anything that moves or renames user data;
- authentication, sessions, access control;
- data migrations and schema/JSON-format changes;
- anything that is hard to undo or breaks backward compatibility.

**Non-critical — decide yourself:**
- naming, small UI details, internal helper structure, wording of default texts.
- Pick the most conventional option for this repository, and **state the assumption in one line** in your final report so the user can veto it.

When you do ask, batch all questions into **one message** (max ~5), each with your proposed default so the user can answer "ok".

### 1.2 Deep Context Exploration
- **Reconnaissance first**: before creating or modifying code, inspect adjacent files, existing helpers (`security_bootstrap.php`, `lang_helper.php`) and styling files (`editor-style.css`, `modals/modal.css`).
- **Reuse existing logic**: always reuse existing helpers instead of writing parallel ones. If you find yourself writing a path sanitizer, an escaper or a JSON loader, search the repo first.

### 1.3 Risk Assessment & Proactive Warnings
If a requested change threatens backward compatibility, opens a security hole (e.g. Path Traversal, XSS, CSRF), or endangers user configuration/content, you **MUST explicitly warn the user** and propose a safer alternative before implementing.

### 1.4 Production-Grade Delivery
Implementations must be complete, handle edge cases (missing files, empty input, malformed JSON, concurrent requests) and require no manual patching by the user. "Complete" does **not** mean "bigger": do not refactor, rename or reformat code unrelated to the task.

### 1.5 Destructive & Scope-Expanding Actions
Never do the following without explicit user approval:
- delete, rename or move existing files or directories;
- change the format of stored data or of `lang/*.json` structure;
- modify server configs (`.htaccess`, `php.ini` overrides, web-server snippets);
- run `git commit`, `git push`, `git reset`, or rewrite history. Leave changes in the working tree unless asked.

---

## 🎨 2. Visual Style & Design System Consistency

NPBlog uses a design system built on native CSS Custom Properties, consistent spacing and smooth micro-interactions. **All UI changes MUST conform to the existing visual language.**

### 2.1 CSS Custom Property Tokens
Do **NOT** hardcode values (`#333`, `#fff`, `rgb(...)`, `12px`, `0 2px 8px rgba(...)`) when a token exists. Before introducing any color, spacing, radius, shadow, font or duration, check the `:root` blocks in `editor-style.css` and `modals/modal.css`.

- **Core surfaces & text**: `--bg-color`, `--text-color`, `--primary-color`, `--border-color`, `--hr-color`
- **Modal dialog**: `--modal-bg`, `--modal-text`, `--modal-border`, `--modal-radius`, `--modal-radius-inner`
- **State colors**: `--modal-color-primary`, `--modal-color-danger`, `--modal-color-warning`, `--modal-color-success`, `--modal-color-info`
- **Motion**: `--anim-ease-out`, `--anim-ease-fluid`, `--anim-dur-fast`, `--anim-dur-normal`

Spacing, typography, shadows: if tokens for them exist in `:root`, use them. If they do not, **copy the values used by the nearest comparable component** rather than inventing new ones, and mention it in your report. Do not add new global tokens without asking.

### 2.2 Strict Multi-Theme Compatibility
Every UI component MUST have adequate contrast and visual harmony in all supported themes:
1. **Light** (default)
2. **Dark** (`[data-theme="dark"]`)
3. **AMOLED** (`[data-theme="dark"][data-amoled="true"]` — pure `#000000` backgrounds)
4. Custom accent colors

Using tokens correctly is what makes this work. Raw colors almost always break at least one theme.

### 2.3 Framework Restrictions
- **No external CSS frameworks or heavy UI libraries** (Tailwind, Bootstrap, etc.).
- Use **Vanilla CSS** in `editor-style.css` or scoped inside modal modules (`modals/modal.css`).

### 2.4 Micro-Interactions
Interactive elements (buttons, inputs, dropdown items, tabs) MUST provide visual feedback for `:hover`, `:focus-visible` and `:active`, using project easing tokens:
```css
transition: all var(--anim-dur-fast) var(--anim-ease-out);
```

---

## 🪟 3. Modal Dialog Architecture

NPBlog has a unified modal framework (`modals/modal.css`, `modals/modal.js`, `modals/modal.php`).

> ⛔ **STRICT PROHIBITION**: NEVER create custom inline popups, floating fixed overlays, ad-hoc backdrop click listeners or uncoordinated z-index layers.

### 3.1 File Organization & Inclusion
- New modal components live in `modals_editor/`:
  - path format: `modals_editor/{feature_name}_modal.php`
- Include in `index.php`:
  ```php
  <?php require_once __DIR__ . '/modals_editor/{feature_name}_modal.php'; ?>
  ```

### 3.2 Canonical HTML Structure
Note: default text inside elements is **Russian** (see §4.1). Copy this structure literally.

```html
<div id="featureModalId" class="modal-overlay" data-size="md">
    <div class="modal-dialog modal-md">
        <div class="modal-header">
            <div class="modal-header-start">
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="feature.title">Заголовок</h3>
                    <p class="modal-subtitle" data-i18n="feature.subtitle">Подзаголовок</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" data-modal-close data-i18n-title="common.close" title="Закрыть">×</button>
            </div>
        </div>

        <div class="modal-body">
            <!-- Alert banner (optional) -->
            <div class="modal-alert modal-alert-info">
                <span class="modal-alert-icon">ℹ️</span>
                <div class="modal-alert-content" data-i18n="feature.notice_text">
                    Важное уведомление
                </div>
            </div>

            <!-- Content -->
        </div>

        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-ghost" data-modal-close data-i18n="common.cancel">Отмена</button>
            <button type="button" class="modal-btn modal-btn-primary" id="saveFeatureBtn" data-i18n="common.save">Сохранить</button>
        </div>
    </div>
</div>
```

### 3.3 Sizes (`data-size` and `.modal-*`)
`xs` (360px), `sm` (460px), `md` (600px), `lg` (840px), `xl` (1060px), `fullscreen`, `auto`.

### 3.4 JavaScript API
- **Open**: `Modal.open('#featureModalId')`
- **Close**: `Modal.close('#featureModalId')`
- Any element with `data-modal-close` dismisses the modal on click.

---

## 🌐 4. Internationalization (i18n) Engine

NPBlog is multilingual. Localization is driven by `lang/i18n.js`, `lang/languages.json`, `lang_helper.php` and JSON dictionaries in `lang/` (`ru.json`, `en.json`, `uk.json`, `lv.json`).

> ⛔ **STRICT PROHIBITION**: NEVER hardcode user-visible text in templates or scripts without localization.

### 4.1 DOM Attributes
- Text: `data-i18n="section.key_name"`
- HTML: `data-i18n-html="section.key_name"` (see §6.3 — trusted dictionary strings only)
- Tooltips/titles: `data-i18n-title="section.key_name"`
- Placeholders: `data-i18n-placeholder="section.key_name"`
- Accessibility labels: `data-i18n-aria="section.key_name"`

Always put the **Russian** default text inside the element as a fallback:
```html
<button type="button" class="modal-btn modal-btn-primary" data-i18n="common.save">Сохранить</button>
```

### 4.2 JavaScript API
```javascript
// Simple lookup with fallback
const label = window.t('settings.theme_label', 'Тема оформления');

// Interpolation: "Файл {filename} успешно сохранен"
const message = window.t('notifications.file_saved', 'Файл {filename} успешно сохранен', { filename: name });
```
After injecting dynamic HTML, re-run translation on the new container:
```javascript
NPBlogI18n.applyTranslations(containerElement);
```

### 4.3 Dictionary Synchronization
When you add new keys:
1. Add them to `lang/ru.json` (baseline, mandatory).
2. Add the English translation to `lang/en.json` (mandatory parity).
3. Do **not** touch `lang/uk.json` and `lang/lv.json` unless the user asks. Missing keys there are expected to fall back to Russian/English. If you discover that is *not* how `i18n.js` behaves, tell the user instead of silently editing those files.
4. Keep key order and formatting of the existing file; do not re-sort or re-indent the whole dictionary.
5. Verify both files are still valid JSON and have the same set of keys (see §7).

---

## 📝 5. Clean, Accessible Code Documentation

### 5.1 Principles
- **Explain intent**: say **why** a decision was made and what problem it solves, not what the syntax does.
- **Language**: professional English or Russian, matching the surrounding file. No slang.
- **Docblocks**:
  - **PHP**: PHPDoc with explicit types (`/** @param string $path ... @return bool ... */`).
  - **JavaScript**: JSDoc (`/** @param {string} selector ... */`).
- **Complex logic & regex**: every non-trivial regex or bitmask MUST have a comment with example input and output:
  ```php
  // Matches local image paths: "data/uploads/image_2026.png" -> extracts basename
  // Regex: #^data/uploads/([^/]+\.(?:png|jpg|webp))$#i
  ```
- **Preservation**: never delete existing docstrings, architectural notes or license headers when refactoring.

---

## 🔒 6. Security & Architectural Invariants

### 6.1 Path Traversal Immunity
- File operations MUST prevent path traversal.
- Sanitize via `realpath()`, `basename()` and prefix validation against the blog root. Remember that `realpath()` returns `false` for non-existent paths — validate the parent directory when creating new files.
- Use helpers from `security_bootstrap.php` first.

### 6.2 Output Escaping (PHP)
Every dynamic value rendered into HTML MUST be escaped:
```php
<?= htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') ?>
```

### 6.3 Client-Side XSS
- Prefer `textContent`, `setAttribute` and DOM APIs over `innerHTML`.
- If `innerHTML` is unavoidable, the content must be a static template or escaped first.
- `data-i18n-html` and `data-i18n-title` etc. must only reference dictionary strings. **Never** feed user-generated content (post titles, file names, comments) through them.

### 6.4 Request Integrity
For every new or modified endpoint:
- reuse the existing CSRF/token and authorization checks from `security_bootstrap.php` (do not invent a new scheme); if the endpoint changes state, it must be POST and must be protected the same way as its neighbours;
- validate and whitelist all input (types, lengths, allowed values);
- for uploads: check extension **and** real MIME type, size limit, and generate/sanitize the stored file name; never trust the client-provided one;
- never expose absolute server paths or stack traces in responses.

### 6.5 Native Vanilla Ecosystem
- **Backend**: native procedural/OOP PHP, **compatible with PHP 7.4 through 8.x**, running on XAMPP/Apache/Nginx with no mandatory Composer dependencies.
  - ⛔ **Do NOT use PHP 8+ features**: `match`, named arguments, union types (`int|string`), `mixed`, constructor property promotion, nullsafe `?->`, `str_contains()` / `str_starts_with()` / `str_ends_with()`, `throw` as an expression, trailing comma in parameter lists, attributes, enums, `readonly`, first-class callable syntax, `never`. Use `strpos`/`substr` equivalents and classic constructs.
  - Allowed (7.4+): typed properties, arrow functions `fn`, `??=`, spread in arrays.
- **Frontend**: vanilla ES6+ JavaScript. No build pipelines, bundlers or SPA frameworks.

### 6.6 User State Protection
- User files (`editor_settings.json`, `allowed_ips.txt`, drafts, backups, posts) MUST NEVER be overwritten with default/empty templates.
- Provide fallback resolution to `.example` files (e.g. `editor_settings.example.json`) **for reading only**.
- When writing JSON/state files: write to a temporary file in the same directory and `rename()` it, or use `file_put_contents(..., LOCK_EX)`, so a crash or parallel request cannot leave a half-written file.
- If a stored file is corrupt, do not "repair" it by overwriting. Back it up (or abort) and report to the user.

---

## ✅ 7. Agent Pre-Completion Checklist

Run the checks yourself (when you have shell access) instead of ticking boxes from memory. If you cannot run a command, say so explicitly in your report.

1. [ ] **Clarifications**: critical ambiguities were confirmed with the user; non-critical assumptions are listed in the report.
2. [ ] **Style cohesion**: UI uses existing tokens; checked mentally or visually in Light, Dark and AMOLED.
3. [ ] **Modal compliance**: dialogs live in `modals_editor/` and use `Modal.open` / `Modal.close` / `data-modal-close`.
4. [ ] **i18n completeness**: all new strings use `data-i18n-*` or `window.t()` with Russian fallbacks; keys added to `ru.json` and `en.json`.
5. [ ] **Comment quality**: complex logic and functions have docblocks explaining intent; no existing docs deleted.
6. [ ] **Security**: no new Path Traversal, XSS, CSRF, upload or authorization gaps (§6.1–6.4).
7. [ ] **PHP compatibility**: no PHP 8+ only features (§6.5).
8. [ ] **User data safe**: nothing in §6.6 overwritten; no files deleted/renamed without approval (§1.5).
9. [ ] **Syntax checks pass**:
   ```bash
   php -l path/to/file.php                 # every changed PHP file
   node --check path/to/file.js            # every changed JS file
   php -r 'foreach (["ru","en"] as $l) { json_decode(file_get_contents("lang/$l.json"), true); echo "$l: ", json_last_error_msg(), PHP_EOL; }'
   ```
   And confirm that `lang/ru.json` and `lang/en.json` contain the same set of keys.
10. [ ] **Report**: final message lists changed files, assumptions made, anything you could not verify, and any risks found.