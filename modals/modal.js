/**
 * ==============================================================================
 * NPBlog Modal Framework - Unified Modal Window Engine
 * ==============================================================================
 * A lightweight, modern, and accessible modal management framework.
 * 
 * Features:
 * - Declarative HTML triggers (data-modal-open, data-modal-close, data-modal-tab)
 * - Programmatic Modals (Modal.create, Modal.alert, Modal.confirm, Modal.prompt, Modal.loading)
 * - Multi-modal stacking with automatic z-index management
 * - Focus trapping and ESC key navigation
 * - Theme-aware (Light, Dark, AMOLED) and i18n integrated
 * - Form validation helpers, loading state management, and draggability
 * ==============================================================================
 */

(function (window, document) {
    'use strict';

    // Base z-index for stacked modals
    const BASE_Z_INDEX = 10000;
    
    // Stack of active open modals (ordered from bottom to top)
    const activeModals = [];

    // Map of modal instances by ID or element
    const instanceMap = new WeakMap();
    const idMap = new Map();

    /**
     * Helper to get translated string if i18n engine is present
     */
    function translate(key, defaultText) {
        if (typeof window.t === 'function') {
            return window.t(key, defaultText);
        }
        if (window.NPBlogI18n && typeof window.NPBlogI18n.t === 'function') {
            return window.NPBlogI18n.t(key, defaultText);
        }
        return defaultText || key;
    }

    /**
     * Apply translations to a DOM subtree if i18n engine is present
     */
    function applySubtreeTranslations(element) {
        if (!element) return;
        if (window.NPBlogI18n && typeof window.NPBlogI18n.applyTranslations === 'function') {
            window.NPBlogI18n.applyTranslations(element);
        }
    }

    /**
     * Generate unique ID
     */
    let idCounter = 0;
    function generateUniqueId(prefix = 'np-modal') {
        return `${prefix}-${Date.now()}-${++idCounter}`;
    }

    /**
     * Modal Class Definition
     */
    class ModalInstance {
        constructor(elementOrConfig, userOptions = {}) {
            this.isDynamic = false;
            this.isOpen = false;
            this.overlay = null;
            this.dialog = null;
            this.options = {
                size: 'md',                  // 'xs', 'sm', 'md', 'lg', 'xl', 'fullscreen', 'auto'
                animation: 'zoom',           // 'zoom', 'slide-down', 'slide-up'
                backdropClose: true,         // Close on overlay backdrop click
                escClose: true,              // Close on ESC key press
                destroyOnClose: false,       // Remove from DOM when closed
                draggable: false,            // Allow dragging by header
                fullscreenable: false,       // Show fullscreen button
                onOpen: null,                // Callback after opened
                onClose: null,               // Callback after closed
                beforeClose: null,           // Callback returning boolean (or Promise) to prevent close
                onTabChange: null,           // Callback when tab changes
                ...userOptions
            };

            if (typeof elementOrConfig === 'string' || (elementOrConfig instanceof HTMLElement)) {
                this.initFromElement(elementOrConfig);
            } else if (typeof elementOrConfig === 'object') {
                this.initFromConfig(elementOrConfig);
            } else {
                throw new Error('[ModalFramework] Invalid argument passed to constructor.');
            }

            this.bindEvents();
        }

        /**
         * Initialize from existing DOM element or selector
         */
        initFromElement(target) {
            let el = typeof target === 'string' ? document.querySelector(target) : target;
            if (!el) {
                // If it's just an ID without #
                el = document.getElementById(target);
            }

            if (!el) {
                throw new Error(`[ModalFramework] Element not found: ${target}`);
            }

            // Check if element is already an overlay or the inner dialog
            if (el.classList.contains('modal-overlay') || el.classList.contains('modal-backdrop') || el.classList.contains('np-modal-overlay')) {
                this.overlay = el;
                this.dialog = el.querySelector('.modal-dialog, .modal-window, .modal-container') || el.firstElementChild;
            } else {
                // Wrap inner dialog into an overlay if needed
                this.dialog = el;
                let parent = el.parentElement;
                if (parent && (parent.classList.contains('modal-overlay') || parent.classList.contains('modal-backdrop'))) {
                    this.overlay = parent;
                } else {
                    this.overlay = document.createElement('div');
                    this.overlay.className = 'modal-overlay';
                    el.parentNode.insertBefore(this.overlay, el);
                    this.overlay.appendChild(el);
                }
            }

            this.id = this.overlay.id || this.dialog.id || generateUniqueId();
            if (!this.overlay.id) this.overlay.id = this.id;

            // Extract data attributes for options
            if (this.overlay.dataset.backdropClose !== undefined) {
                this.options.backdropClose = this.overlay.dataset.backdropClose !== 'false';
            }
            if (this.overlay.dataset.escClose !== undefined) {
                this.options.escClose = this.overlay.dataset.escClose !== 'false';
            }
            if (this.overlay.dataset.size) {
                this.options.size = this.overlay.dataset.size;
            }
            if (this.overlay.dataset.draggable !== undefined) {
                this.options.draggable = this.overlay.dataset.draggable !== 'false';
            }
            if (this.dialog.dataset.draggable !== undefined) {
                this.options.draggable = this.dialog.dataset.draggable !== 'false';
            }
            const headerEl = this.dialog.querySelector('.modal-header');
            if (headerEl && (headerEl.classList.contains('is-draggable') || headerEl.dataset.draggable !== undefined)) {
                this.options.draggable = true;
            }

            // Apply size class if not present
            if (this.options.size && !this.dialog.className.match(/modal-(xs|sm|md|lg|xl|fullscreen|auto)/)) {
                this.dialog.classList.add(`modal-${this.options.size}`);
            }

            // Setup draggable if needed
            if (this.options.draggable) {
                this.setupDraggable();
            }

            this.initTabs();
            instanceMap.set(this.overlay, this);
            idMap.set(this.id, this);
        }

        /**
         * Initialize dynamically from JavaScript configuration object
         */
        initFromConfig(config) {
            this.isDynamic = true;
            this.options = { ...this.options, destroyOnClose: true, ...config };
            this.id = config.id || generateUniqueId();

            // 1. Build Overlay
            this.overlay = document.createElement('div');
            this.overlay.id = this.id;
            this.overlay.className = `modal-overlay ${config.animation ? `modal-${config.animation}` : ''} ${config.overlayClass || ''}`.trim();

            // 2. Build Dialog Card
            this.dialog = document.createElement('div');
            const sizeClass = `modal-${config.size || this.options.size || 'md'}`;
            this.dialog.className = `modal-dialog ${sizeClass} ${config.dialogClass || ''}`.trim();

            // 3. Build Header
            if (config.header !== false && (config.title || config.closable !== false || config.icon)) {
                const header = document.createElement('div');
                header.className = 'modal-header';

                const headerStart = document.createElement('div');
                headerStart.className = 'modal-header-start';

                // Optional Icon
                if (config.icon) {
                    const iconEl = document.createElement('span');
                    iconEl.className = `modal-icon ${config.iconType ? `icon-${config.iconType}` : ''}`;
                    if (typeof config.icon === 'string' && config.icon.startsWith('<')) {
                        iconEl.innerHTML = config.icon;
                    } else {
                        iconEl.textContent = config.icon;
                    }
                    headerStart.appendChild(iconEl);
                }

                // Titles wrapper
                const titles = document.createElement('div');
                titles.className = 'modal-titles';

                if (config.title) {
                    const titleEl = document.createElement('h3');
                    titleEl.className = 'modal-title';
                    if (config.titleI18n) {
                        titleEl.setAttribute('data-i18n', config.titleI18n);
                        titleEl.textContent = translate(config.titleI18n, config.title);
                    } else {
                        titleEl.innerHTML = config.title;
                    }
                    titles.appendChild(titleEl);
                }

                if (config.subtitle) {
                    const subtitleEl = document.createElement('p');
                    subtitleEl.className = 'modal-subtitle';
                    if (config.subtitleI18n) {
                        subtitleEl.setAttribute('data-i18n', config.subtitleI18n);
                        subtitleEl.textContent = translate(config.subtitleI18n, config.subtitle);
                    } else {
                        subtitleEl.textContent = config.subtitle;
                    }
                    titles.appendChild(subtitleEl);
                }

                headerStart.appendChild(titles);

                if (config.badge) {
                    const badgeEl = document.createElement('span');
                    badgeEl.className = 'modal-badge';
                    badgeEl.textContent = config.badge;
                    headerStart.appendChild(badgeEl);
                }

                header.appendChild(headerStart);

                // Header Actions (Fullscreen + Close)
                const headerActions = document.createElement('div');
                headerActions.className = 'modal-header-actions';

                if (config.fullscreenable || this.options.fullscreenable) {
                    const fsBtn = document.createElement('button');
                    fsBtn.type = 'button';
                    fsBtn.className = 'modal-fullscreen-btn';
                    fsBtn.title = translate('modals.toggle_fullscreen', 'Развернуть');
                    fsBtn.innerHTML = '⛶';
                    fsBtn.addEventListener('click', () => this.toggleFullscreen());
                    headerActions.appendChild(fsBtn);
                }

                if (config.closable !== false) {
                    const closeBtn = document.createElement('button');
                    closeBtn.type = 'button';
                    closeBtn.className = 'modal-close-btn';
                    closeBtn.title = translate('common.close', 'Закрыть');
                    closeBtn.setAttribute('data-modal-close', '');
                    closeBtn.innerHTML = '×';
                    headerActions.appendChild(closeBtn);
                }

                header.appendChild(headerActions);
                this.dialog.appendChild(header);
            }

            // 4. Build Tabs if provided
            if (Array.isArray(config.tabs) && config.tabs.length > 0) {
                const tabsNav = document.createElement('div');
                tabsNav.className = 'modal-tabs';

                config.tabs.forEach((tab, index) => {
                    const tabBtn = document.createElement('button');
                    tabBtn.type = 'button';
                    tabBtn.className = `modal-tab-btn ${index === 0 ? 'is-active' : ''}`;
                    tabBtn.setAttribute('data-modal-tab', tab.id || `tab-${index}`);
                    if (tab.icon) {
                        tabBtn.innerHTML = `<span class="modal-tab-icon">${tab.icon}</span> ${tab.title || ''}`;
                    } else {
                        tabBtn.textContent = tab.title || `Вкладка ${index + 1}`;
                    }
                    tabsNav.appendChild(tabBtn);
                });
                this.dialog.appendChild(tabsNav);
            }

            // 5. Build Body
            const body = document.createElement('div');
            body.className = `modal-body ${config.bodyClass || ''}`;

            if (Array.isArray(config.tabs) && config.tabs.length > 0) {
                // Tab panes
                config.tabs.forEach((tab, index) => {
                    const pane = document.createElement('div');
                    pane.className = `modal-tab-pane ${index === 0 ? 'is-active' : ''}`;
                    pane.id = tab.id || `tab-${index}`;
                    if (typeof tab.content === 'string') {
                        pane.innerHTML = tab.content;
                    } else if (tab.content instanceof HTMLElement) {
                        pane.appendChild(tab.content);
                    }
                    body.appendChild(pane);
                });
            } else {
                // Standard Body Content
                if (typeof config.content === 'string') {
                    body.innerHTML = config.content;
                } else if (config.content instanceof HTMLElement) {
                    body.appendChild(config.content);
                } else if (config.html) {
                    body.innerHTML = config.html;
                } else if (config.message) {
                    const p = document.createElement('p');
                    p.className = 'modal-text';
                    p.innerHTML = config.message;
                    body.appendChild(p);
                }
            }

            this.dialog.appendChild(body);

            // 6. Build Footer
            if (config.footer !== false && (config.footer || config.buttons)) {
                const footer = document.createElement('div');
                footer.className = `modal-footer ${config.footerClass || ''}`;

                if (typeof config.footer === 'string') {
                    footer.innerHTML = config.footer;
                } else if (config.footer instanceof HTMLElement) {
                    footer.appendChild(config.footer);
                } else if (Array.isArray(config.buttons)) {
                    config.buttons.forEach(btnConfig => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = `modal-btn ${btnConfig.class || (btnConfig.primary ? 'modal-btn-primary' : (btnConfig.danger ? 'modal-btn-danger' : ''))}`.trim();
                        
                        if (btnConfig.i18n) {
                            btn.setAttribute('data-i18n', btnConfig.i18n);
                            btn.textContent = translate(btnConfig.i18n, btnConfig.text);
                        } else {
                            btn.innerHTML = btnConfig.text || 'ОК';
                        }

                        if (btnConfig.close !== false && !btnConfig.handler) {
                            btn.setAttribute('data-modal-close', '');
                        }

                        btn.addEventListener('click', async (e) => {
                            if (typeof btnConfig.handler === 'function') {
                                const result = await btnConfig.handler(this, e);
                                if (result !== false && btnConfig.close !== false) {
                                    this.close();
                                }
                            }
                        });

                        footer.appendChild(btn);
                    });
                }

                this.dialog.appendChild(footer);
            }

            this.overlay.appendChild(this.dialog);
            document.body.appendChild(this.overlay);

            if (this.options.draggable) {
                this.setupDraggable();
            }

            this.initTabs();
            applySubtreeTranslations(this.overlay);

            instanceMap.set(this.overlay, this);
            idMap.set(this.id, this);
        }

        /**
         * Setup tab switching inside modal
         */
        initTabs() {
            const tabs = this.overlay.querySelectorAll('.modal-tab-btn[data-modal-tab]');
            if (!tabs.length) return;

            tabs.forEach(tabBtn => {
                tabBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const targetTabId = tabBtn.getAttribute('data-modal-tab');
                    this.switchTab(targetTabId);
                });
            });
        }

        /**
         * Switch active tab
         */
        switchTab(targetTabId) {
            const tabs = this.overlay.querySelectorAll('.modal-tab-btn');
            const panes = this.overlay.querySelectorAll('.modal-tab-pane');

            tabs.forEach(btn => {
                const isCurrent = btn.getAttribute('data-modal-tab') === targetTabId;
                btn.classList.toggle('is-active', isCurrent);
                btn.setAttribute('aria-selected', isCurrent ? 'true' : 'false');
            });

            panes.forEach(pane => {
                const isCurrent = pane.id === targetTabId || pane.getAttribute('data-modal-pane') === targetTabId;
                pane.classList.toggle('is-active', isCurrent);
            });

            if (typeof this.options.onTabChange === 'function') {
                this.options.onTabChange(targetTabId, this);
            }

            this.triggerEvent('modal:tab-change', { tabId: targetTabId });
        }

        /**
         * Setup draggable header
         */
        setupDraggable() {
            const header = this.dialog.querySelector('.modal-header');
            if (!header) return;

            header.classList.add('is-draggable');

            this.dragState = this.dragState || {
                currentX: 0,
                currentY: 0,
                initialX: 0,
                initialY: 0,
                xOffset: 0,
                yOffset: 0,
                isDragging: false
            };

            const state = this.dragState;

            const dragStart = (e) => {
                // Only primary mouse button or touch
                if (e.type === 'mousedown' && e.button !== 0) return;

                // Don't drag if clicking buttons or interactive elements inside header
                if (e.target.closest('.modal-close-btn, .modal-fullscreen-btn, button, input, select, textarea, a, .modal-tab-btn')) {
                    return;
                }

                // Disable CSS animation so inline transform takes immediate effect
                this.dialog.style.animation = 'none';
                this.dialog.style.opacity = '1';
                this.dialog.classList.add('is-dragging');
                document.body.classList.add('modal-is-dragging');

                const clientX = e.type.startsWith('touch') ? e.touches[0].clientX : e.clientX;
                const clientY = e.type.startsWith('touch') ? e.touches[0].clientY : e.clientY;

                state.initialX = clientX - state.xOffset;
                state.initialY = clientY - state.yOffset;
                state.isDragging = true;

                if (e.cancelable) {
                    e.preventDefault();
                }

                document.addEventListener('mousemove', dragMove, { passive: false });
                document.addEventListener('mouseup', dragEnd);
                document.addEventListener('touchmove', dragMove, { passive: false });
                document.addEventListener('touchend', dragEnd);
                document.addEventListener('touchcancel', dragEnd);
            };

            const dragMove = (e) => {
                if (!state.isDragging) return;

                if (e.cancelable) {
                    e.preventDefault();
                }

                const clientX = e.type.startsWith('touch') ? e.touches[0].clientX : e.clientX;
                const clientY = e.type.startsWith('touch') ? e.touches[0].clientY : e.clientY;

                state.currentX = clientX - state.initialX;
                state.currentY = clientY - state.initialY;

                state.xOffset = state.currentX;
                state.yOffset = state.currentY;

                this.dialog.style.transform = `translate3d(${state.currentX}px, ${state.currentY}px, 0)`;
            };

            const dragEnd = () => {
                if (!state.isDragging) return;
                state.isDragging = false;
                this.dialog.classList.remove('is-dragging');
                document.body.classList.remove('modal-is-dragging');

                document.removeEventListener('mousemove', dragMove);
                document.removeEventListener('mouseup', dragEnd);
                document.removeEventListener('touchmove', dragMove);
                document.removeEventListener('touchend', dragEnd);
                document.removeEventListener('touchcancel', dragEnd);
            };

            if (this._dragStartHandler) {
                header.removeEventListener('mousedown', this._dragStartHandler);
                header.removeEventListener('touchstart', this._dragStartHandler);
            }
            this._dragStartHandler = dragStart;

            header.addEventListener('mousedown', dragStart);
            header.addEventListener('touchstart', dragStart, { passive: false });
        }

        /**
         * Toggle fullscreen state
         */
        toggleFullscreen() {
            this.dialog.classList.toggle('modal-fullscreen');
            const fsBtn = this.dialog.querySelector('.modal-fullscreen-btn');
            if (fsBtn) {
                const isFs = this.dialog.classList.contains('modal-fullscreen');
                fsBtn.innerHTML = isFs ? '🗗' : '⛶';
            }
        }

        /**
         * Bind overlay, close buttons, and keyboard events
         */
        bindEvents() {
            // Close buttons click
            this.overlay.addEventListener('click', (e) => {
                const closeBtn = e.target.closest('[data-modal-close], [data-modal-dismiss], .modal-close-btn');
                if (closeBtn) {
                    e.preventDefault();
                    this.close();
                    return;
                }

                // Backdrop click
                if (e.target === this.overlay && this.options.backdropClose) {
                    this.close();
                }
            });
        }

        /**
         * Open the modal
         */
        async open() {
            if (this.isOpen) return this;

            // Trigger beforeOpen hook
            if (typeof this.options.beforeOpen === 'function') {
                const canOpen = await this.options.beforeOpen(this);
                if (canOpen === false) return this;
            }

            // Stack handling: increment z-index
            const stackIndex = activeModals.length;
            const computedZ = BASE_Z_INDEX + stackIndex * 10;
            this.overlay.style.setProperty('--modal-z-index', computedZ);
            this.overlay.style.zIndex = computedZ;

            // Clean previous closing states
            this.overlay.classList.remove('is-closing');
            this.overlay.style.display = 'flex';

            // Force reflow for CSS animation
            void this.overlay.offsetHeight;
            this.overlay.classList.add('is-active');

            // Apply translations dynamically
            applySubtreeTranslations(this.overlay);

            // Register in active stack
            activeModals.push(this);
            this.isOpen = true;

            // Clear CSS animation lock after entrance finishes so dragging and custom transforms work freely
            const onAnimationEnd = () => {
                if (this.isOpen && !this.overlay.classList.contains('is-closing')) {
                    this.dialog.style.animation = 'none';
                    this.dialog.style.opacity = '1';
                }
            };
            this.dialog.addEventListener('animationend', onAnimationEnd, { once: true });

            // Lock document body scroll
            document.body.classList.add('modal-open');

            // Focus management
            this.trapFocus();

            // Trigger events
            this.triggerEvent('modal:open');
            if (typeof this.options.onOpen === 'function') {
                this.options.onOpen(this);
            }

            return this;
        }

        /**
         * Close the modal
         */
        async close() {
            if (!this.isOpen) return this;

            // Check beforeClose guard
            if (typeof this.options.beforeClose === 'function') {
                const canClose = await this.options.beforeClose(this);
                if (canClose === false) return this;
            }

            this.triggerEvent('modal:close');

            // Play exit animation
            this.overlay.classList.remove('is-active');
            this.overlay.classList.add('is-closing');

            // Remove from active stack
            const index = activeModals.indexOf(this);
            if (index > -1) {
                activeModals.splice(index, 1);
            }

            // Restore body scroll if no other modals are open
            if (activeModals.length === 0) {
                document.body.classList.remove('modal-open');
            }

            setTimeout(() => {
                this.overlay.style.display = 'none';
                this.overlay.classList.remove('is-closing');
                this.isOpen = false;

                if (typeof this.options.onClose === 'function') {
                    this.options.onClose(this);
                }
                this.triggerEvent('modal:closed');

                // Destroy if dynamic or destroyOnClose enabled
                if (this.options.destroyOnClose || this.isDynamic) {
                    this.destroy();
                }
            }, parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--modal-transition-speed') || '0.24') * 1000);

            return this;
        }

        /**
         * Toggle modal state
         */
        toggle() {
            return this.isOpen ? this.close() : this.open();
        }

        /**
         * Destroy modal and remove from DOM
         */
        destroy() {
            if (this.isOpen) {
                this.close();
            }
            if (this.overlay && this.overlay.parentNode) {
                this.overlay.parentNode.removeChild(this.overlay);
            }
            instanceMap.delete(this.overlay);
            idMap.delete(this.id);
        }

        /**
         * Shake animation for feedback / error
         */
        shake() {
            this.dialog.classList.remove('is-shaking');
            void this.dialog.offsetWidth;
            this.dialog.classList.add('is-shaking');
            setTimeout(() => this.dialog.classList.remove('is-shaking'), 500);
        }

        /**
         * Toggle loading overlay on modal
         */
        setLoading(isLoading, message = '') {
            let loadingEl = this.dialog.querySelector('.modal-loading-overlay');
            if (isLoading) {
                if (!loadingEl) {
                    loadingEl = document.createElement('div');
                    loadingEl.className = 'modal-loading-overlay';
                    loadingEl.innerHTML = `
                        <div class="modal-spinner"></div>
                        <div class="modal-loading-text">${message || translate('common.loading', 'Загрузка...')}</div>
                    `;
                    this.dialog.appendChild(loadingEl);
                } else {
                    const textEl = loadingEl.querySelector('.modal-loading-text');
                    if (textEl) textEl.textContent = message || translate('common.loading', 'Загрузка...');
                }
            } else if (loadingEl) {
                loadingEl.remove();
            }
        }

        /**
         * Set modal title dynamically
         */
        setTitle(title) {
            const titleEl = this.dialog.querySelector('.modal-title');
            if (titleEl) titleEl.innerHTML = title;
        }

        /**
         * Set modal body content dynamically
         */
        setContent(content) {
            const bodyEl = this.dialog.querySelector('.modal-body');
            if (bodyEl) {
                if (typeof content === 'string') {
                    bodyEl.innerHTML = content;
                } else if (content instanceof HTMLElement) {
                    bodyEl.innerHTML = '';
                    bodyEl.appendChild(content);
                }
                applySubtreeTranslations(bodyEl);
            }
        }

        /**
         * Get data from all form inputs inside modal
         */
        getFormData() {
            const form = this.dialog.querySelector('form') || this.dialog;
            const inputs = form.querySelectorAll('input, select, textarea');
            const data = {};

            inputs.forEach(input => {
                if (!input.name) return;
                if (input.type === 'checkbox') {
                    data[input.name] = input.checked;
                } else if (input.type === 'radio') {
                    if (input.checked) data[input.name] = input.value;
                } else {
                    data[input.name] = input.value;
                }
            });

            return data;
        }

        /**
         * Query selector within modal dialog
         */
        querySelector(selector) {
            return this.dialog.querySelector(selector);
        }

        /**
         * Query selector all within modal dialog
         */
        querySelectorAll(selector) {
            return this.dialog.querySelectorAll(selector);
        }

        /**
         * Focus trap for accessibility
         */
        trapFocus() {
            const focusableElements = this.dialog.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            if (focusableElements.length > 0) {
                // Focus first non-close interactive element or the close button
                const autoFocusEl = this.dialog.querySelector('[autofocus]') || focusableElements[0];
                setTimeout(() => autoFocusEl.focus(), 50);
            }
        }

        /**
         * Trigger Custom DOM Event
         */
        triggerEvent(eventName, detail = {}) {
            const event = new CustomEvent(eventName, {
                bubbles: true,
                cancelable: true,
                detail: { modal: this, ...detail }
            });
            this.overlay.dispatchEvent(event);
        }
    }

    /**
     * Global Keydown Listener (ESC & Tab Trap)
     */
    document.addEventListener('keydown', (e) => {
        if (!activeModals.length) return;
        const topModal = activeModals[activeModals.length - 1];

        // ESC Key Close
        if (e.key === 'Escape' || e.keyCode === 27) {
            if (topModal.options.escClose) {
                e.preventDefault();
                topModal.close();
            }
            return;
        }

        // Tab Key Focus Trapping
        if (e.key === 'Tab' || e.keyCode === 9) {
            const focusable = topModal.dialog.querySelectorAll(
                'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
            if (!focusable.length) return;

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (e.shiftKey) {
                if (document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                if (document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        }
    });

    /**
     * Global Click Delegator for data-modal-open / data-modal-target
     */
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-modal-open], [data-modal-target]');
        if (trigger) {
            e.preventDefault();
            const target = trigger.getAttribute('data-modal-open') || trigger.getAttribute('data-modal-target');
            if (target) {
                Modal.open(target);
            }
        }
    });

    /**
     * ==============================================================================
     * Public Static API
     * ==============================================================================
     */
    const Modal = {
        /**
         * Open a modal by selector, element, or ID
         */
        open(target, options = {}) {
            let instance = Modal.get(target);
            if (!instance) {
                instance = new ModalInstance(target, options);
            } else if (Object.keys(options).length > 0) {
                instance.options = { ...instance.options, ...options };
                if (instance.options.draggable) {
                    instance.setupDraggable();
                }
            }
            return instance.open();
        },

        /**
         * Close a modal
         */
        close(target) {
            const instance = Modal.get(target);
            if (instance) {
                return instance.close();
            }
            return Promise.resolve();
        },

        /**
         * Toggle modal state
         */
        toggle(target) {
            const instance = Modal.get(target);
            if (instance) {
                return instance.toggle();
            }
            return Modal.open(target);
        },

        /**
         * Get ModalInstance by ID, element, or selector
         */
        get(target) {
            if (!target) return null;
            if (target instanceof ModalInstance) return target;
            if (typeof target === 'string') {
                if (idMap.has(target)) return idMap.get(target);
                const el = document.querySelector(target) || document.getElementById(target);
                if (el) {
                    if (instanceMap.has(el)) return instanceMap.get(el);
                    const parentOverlay = el.closest('.modal-overlay');
                    if (parentOverlay && instanceMap.has(parentOverlay)) return instanceMap.get(parentOverlay);
                }
            } else if (target instanceof HTMLElement) {
                if (instanceMap.has(target)) return instanceMap.get(target);
                const parentOverlay = target.closest('.modal-overlay');
                if (parentOverlay && instanceMap.has(parentOverlay)) return instanceMap.get(parentOverlay);
            }
            return null;
        },

        /**
         * Close all currently open modals
         */
        closeAll() {
            const list = [...activeModals];
            list.forEach(m => m.close());
        },

        /**
         * Close the topmost open modal
         */
        closeTop() {
            if (activeModals.length > 0) {
                return activeModals[activeModals.length - 1].close();
            }
            return Promise.resolve();
        },

        /**
         * Create a new programmatic modal
         */
        create(config) {
            const instance = new ModalInstance(config);
            instance.open();
            return instance;
        },

        /**
         * Quick Alert Dialog
         * @returns {Promise<void>}
         */
        alert(optionsOrMessage) {
            return new Promise((resolve) => {
                const config = typeof optionsOrMessage === 'string'
                    ? { message: optionsOrMessage }
                    : optionsOrMessage;

                const iconMap = {
                    info: 'ℹ️',
                    warning: '⚠️',
                    danger: '⚠️',
                    success: '✅'
                };

                Modal.create({
                    size: 'sm',
                    title: config.title || translate('common.notification', 'Уведомление'),
                    icon: config.icon || (config.type ? iconMap[config.type] : 'ℹ️'),
                    iconType: config.type || 'info',
                    message: config.message || '',
                    buttons: [
                        {
                            text: config.okText || translate('common.ok', 'OK'),
                            primary: true,
                            handler: () => resolve()
                        }
                    ],
                    onClose: () => resolve()
                });
            });
        },

        /**
         * Quick Confirm Dialog
         * @returns {Promise<boolean>}
         */
        confirm(optionsOrMessage) {
            return new Promise((resolve) => {
                const config = typeof optionsOrMessage === 'string'
                    ? { message: optionsOrMessage }
                    : optionsOrMessage;

                let isConfirmed = false;

                Modal.create({
                    size: 'sm',
                    title: config.title || translate('common.confirm', 'Подтверждение'),
                    icon: config.icon || (config.danger ? '⚠️' : '❓'),
                    iconType: config.danger ? 'danger' : (config.iconType || 'info'),
                    message: config.message || '',
                    buttons: [
                        {
                            text: config.cancelText || translate('common.cancel', 'Отмена'),
                            class: 'modal-btn-ghost',
                            handler: () => {
                                isConfirmed = false;
                                resolve(false);
                            }
                        },
                        {
                            text: config.confirmText || (config.danger ? translate('common.delete', 'Удалить') : translate('common.confirm_action', 'Подтвердить')),
                            class: config.danger ? 'modal-btn-danger' : 'modal-btn-primary',
                            handler: () => {
                                isConfirmed = true;
                                resolve(true);
                            }
                        }
                    ],
                    onClose: () => {
                        if (!isConfirmed) resolve(false);
                    }
                });
            });
        },

        /**
         * Quick Prompt Dialog (Input value)
         * @returns {Promise<string|null>}
         */
        prompt(optionsOrMessage) {
            return new Promise((resolve) => {
                const config = typeof optionsOrMessage === 'string'
                    ? { title: optionsOrMessage }
                    : optionsOrMessage;

                let isSubmitted = false;
                const inputId = generateUniqueId('input');

                const contentHtml = `
                    <div class="modal-form-group">
                        ${config.message ? `<p class="modal-text">${config.message}</p>` : ''}
                        ${config.label ? `<label class="modal-label" for="${inputId}">${config.label}</label>` : ''}
                        <input type="${config.inputType || 'text'}" id="${inputId}" class="modal-input" placeholder="${config.placeholder || ''}" value="${config.defaultValue || ''}">
                        ${config.help ? `<div class="modal-help-text">${config.help}</div>` : ''}
                        <div class="modal-error-msg" id="${inputId}-err"></div>
                    </div>
                `;

                const modal = Modal.create({
                    size: 'sm',
                    title: config.title || translate('common.enter_value', 'Введите значение'),
                    content: contentHtml,
                    buttons: [
                        {
                            text: config.cancelText || translate('common.cancel', 'Отмена'),
                            class: 'modal-btn-ghost',
                            handler: () => {
                                isSubmitted = true;
                                resolve(null);
                            }
                        },
                        {
                            text: config.confirmText || translate('common.save', 'Сохранить'),
                            primary: true,
                            handler: (inst) => {
                                const input = inst.querySelector(`#${inputId}`);
                                const val = input ? input.value : '';
                                
                                if (config.required && !val.trim()) {
                                    inst.shake();
                                    const errEl = inst.querySelector(`#${inputId}-err`);
                                    if (errEl) errEl.textContent = config.requiredMessage || translate('modals.field_required', 'Поле обязательно для заполнения');
                                    input.focus();
                                    return false;
                                }

                                if (typeof config.validator === 'function') {
                                    const err = config.validator(val);
                                    if (err) {
                                        inst.shake();
                                        const errEl = inst.querySelector(`#${inputId}-err`);
                                        if (errEl) errEl.textContent = err;
                                        input.focus();
                                        return false;
                                    }
                                }

                                isSubmitted = true;
                                resolve(val);
                            }
                        }
                    ],
                    onOpen: (inst) => {
                        const input = inst.querySelector(`#${inputId}`);
                        if (input) {
                            input.focus();
                            input.select();
                            input.addEventListener('keydown', (e) => {
                                if (e.key === 'Enter') {
                                    const okBtn = inst.dialog.querySelector('.modal-btn-primary');
                                    if (okBtn) okBtn.click();
                                }
                            });
                        }
                    },
                    onClose: () => {
                        if (!isSubmitted) resolve(null);
                    }
                });
            });
        },

        /**
         * Quick Loading Dialog
         * @returns {{ update: Function, close: Function }}
         */
        loading(optionsOrMessage) {
            const config = typeof optionsOrMessage === 'string'
                ? { message: optionsOrMessage }
                : optionsOrMessage;

            const modal = Modal.create({
                size: 'xs',
                header: false,
                footer: false,
                backdropClose: false,
                escClose: false,
                content: `
                    <div style="text-align: center; padding: 24px 16px;">
                        <div class="modal-spinner" style="margin: 0 auto 16px auto;"></div>
                        <div class="modal-loading-text" id="npLoadingText">${config.message || translate('common.loading', 'Загрузка...')}</div>
                    </div>
                `
            });

            return {
                update(newMessage) {
                    const textEl = modal.querySelector('#npLoadingText');
                    if (textEl) textEl.textContent = newMessage;
                },
                close() {
                    return modal.close();
                }
            };
        }
    };

    // Expose globally
    window.Modal = Modal;
    window.NPModal = Modal;
    window.ModalFramework = Modal;

})(window, document);
