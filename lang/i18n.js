/**
 * NPBlog Editor - Internationalization (i18n) Engine
 * Full localization support for Russian (ru), English (en), Ukrainian (uk), and Latvian (lv).
 */
(function(window, document) {
    'use strict';

    var currentLang = 'ru';
    var dict = {};
    var loadedDictionaries = {};

    var NPBlogI18n = {
        /**
         * Initialize i18n system
         */
        init: function(initialLang) {
            var stored = localStorage.getItem('npblog_language');
            if (stored && (stored === 'ru' || stored === 'en' || stored === 'uk' || stored === 'lv')) {
                currentLang = stored;
            } else if (initialLang && (initialLang === 'ru' || initialLang === 'en' || initialLang === 'uk' || initialLang === 'lv')) {
                currentLang = initialLang;
            } else if (window.NPBLOG_LANG && (window.NPBLOG_LANG === 'ru' || window.NPBLOG_LANG === 'en' || window.NPBLOG_LANG === 'uk' || window.NPBLOG_LANG === 'lv')) {
                currentLang = window.NPBLOG_LANG;
            }

            // Load dictionary for current language
            this.setLanguage(currentLang, false);

            // Auto-apply translations on DOMContentLoaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    NPBlogI18n.applyTranslations();
                });
            } else {
                NPBlogI18n.applyTranslations();
            }
        },

        /**
         * Register dictionary data
         */
        register: function(langCode, data) {
            loadedDictionaries[langCode] = data;
            if (langCode === currentLang) {
                dict = data;
            }
        },

        /**
         * Get active language code
         */
        getLanguage: function() {
            return currentLang;
        },

        /**
         * Set and switch active language
         */
        setLanguage: function(langCode, saveToServer, callback) {
            if (langCode !== 'ru' && langCode !== 'en' && langCode !== 'uk' && langCode !== 'lv') {
                langCode = 'ru';
            }
            currentLang = langCode;
            localStorage.setItem('npblog_language', langCode);
            document.documentElement.setAttribute('lang', langCode);

            // If dictionary is preloaded
            if (loadedDictionaries[langCode]) {
                dict = loadedDictionaries[langCode];
                this.applyTranslations();
                if (window.dispatchEvent) {
                    window.dispatchEvent(new CustomEvent('npblog:langchange', { detail: { language: langCode } }));
                }
            } else {
                // Fetch JSON from lang folder
                var self = this;
                fetch('lang/' + langCode + '.json?v=' + Date.now())
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        loadedDictionaries[langCode] = data;
                        dict = data;
                        self.applyTranslations();
                        if (window.dispatchEvent) {
                            window.dispatchEvent(new CustomEvent('npblog:langchange', { detail: { language: langCode } }));
                        }
                    })
                    .catch(function(err) {
                        console.warn('[i18n] Failed to load dictionary:', err);
                    });
            }

            if (saveToServer) {
                fetch('save_editor_settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ language: langCode })
                }).then(function(res) { return res.json(); })
                  .then(function(res) {
                      if (typeof callback === 'function') callback(res);
                  })
                  .catch(function(err) {
                      console.error('[i18n] Save language error:', err);
                      if (typeof callback === 'function') callback({ success: false, error: err });
                  });
            } else if (typeof callback === 'function') {
                callback({ success: true });
            }
        },

        /**
         * Translate key with optional fallback and params
         * Usage: t('header.btn_save', 'Сохранить')
         * Usage: t('common.save')
         */
        t: function(keyPath, fallback, params) {
            if (!keyPath) return fallback || '';
            
            var parts = keyPath.split('.');
            var current = dict;
            
            for (var i = 0; i < parts.length; i++) {
                if (current && typeof current === 'object' && parts[i] in current) {
                    current = current[parts[i]];
                } else {
                    current = null;
                    break;
                }
            }

            var text = (typeof current === 'string') ? current : (fallback !== undefined ? fallback : keyPath);

            if (params && Array.isArray(params)) {
                params.forEach(function(p) {
                    text = text.replace(/%s/, p);
                });
            } else if (params && typeof params === 'object') {
                Object.keys(params).forEach(function(pKey) {
                    text = text.replace(new RegExp('{' + pKey + '}', 'g'), params[pKey]);
                });
            }

            return text;
        },

        /**
         * Scan DOM and apply translations to elements with data-i18n attributes
         */
        applyTranslations: function(root) {
            var doc = root || document;
            if (!doc || !doc.querySelectorAll) return;

            // 1. Text content: data-i18n
            var textElements = doc.querySelectorAll('[data-i18n]');
            for (var i = 0; i < textElements.length; i++) {
                var el = textElements[i];
                var key = el.getAttribute('data-i18n');
                var def = el.getAttribute('data-i18n-default') || el.textContent;
                el.textContent = this.t(key, def);
            }

            // 2. HTML content: data-i18n-html
            var htmlElements = doc.querySelectorAll('[data-i18n-html]');
            for (var j = 0; j < htmlElements.length; j++) {
                var hEl = htmlElements[j];
                var hKey = hEl.getAttribute('data-i18n-html');
                var hDef = hEl.getAttribute('data-i18n-default') || hEl.innerHTML;
                hEl.innerHTML = this.t(hKey, hDef);
            }

            // 3. Tooltip / Title: data-i18n-title
            var titleElements = doc.querySelectorAll('[data-i18n-title]');
            for (var k = 0; k < titleElements.length; k++) {
                var tEl = titleElements[k];
                var tKey = tEl.getAttribute('data-i18n-title');
                var tDef = tEl.getAttribute('title') || '';
                tEl.setAttribute('title', this.t(tKey, tDef));
            }

            // 4. Placeholder: data-i18n-placeholder
            var phElements = doc.querySelectorAll('[data-i18n-placeholder]');
            for (var m = 0; m < phElements.length; m++) {
                var pEl = phElements[m];
                var pKey = pEl.getAttribute('data-i18n-placeholder');
                var pDef = pEl.getAttribute('placeholder') || '';
                pEl.setAttribute('placeholder', this.t(pKey, pDef));
            }

            // 5. Aria-label: data-i18n-aria
            var ariaElements = doc.querySelectorAll('[data-i18n-aria]');
            for (var n = 0; n < ariaElements.length; n++) {
                var aEl = ariaElements[n];
                var aKey = aEl.getAttribute('data-i18n-aria');
                var aDef = aEl.getAttribute('aria-label') || '';
                aEl.setAttribute('aria-label', this.t(aKey, aDef));
            }

            // Update document title if needed
            if (doc === document && dict.header && dict.header.editor_title) {
                document.title = dict.header.editor_title;
            }
        }
    };

    // Preload Russian dictionary
    fetch('lang/ru.json?v=' + Date.now()).then(function(r) { return r.json(); }).then(function(data) {
        NPBlogI18n.register('ru', data);
    }).catch(function() {});

    // Preload English dictionary
    fetch('lang/en.json?v=' + Date.now()).then(function(r) { return r.json(); }).then(function(data) {
        NPBlogI18n.register('en', data);
    }).catch(function() {});

    // Preload Ukrainian dictionary
    fetch('lang/uk.json?v=' + Date.now()).then(function(r) { return r.json(); }).then(function(data) {
        NPBlogI18n.register('uk', data);
    }).catch(function() {});

    // Preload Latvian dictionary
    fetch('lang/lv.json?v=' + Date.now()).then(function(r) { return r.json(); }).then(function(data) {
        NPBlogI18n.register('lv', data);
    }).catch(function() {});

    // Expose globally
    window.NPBlogI18n = NPBlogI18n;
    window.t = NPBlogI18n.t.bind(NPBlogI18n);

    // Auto-initialize
    NPBlogI18n.init(window.NPBLOG_LANG || 'ru');

})(window, document);
