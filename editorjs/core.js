// ——— Вспомогательные функции экранирования ———
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeHtmlJS(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

let currentEditId = null;
let editorMode = 'visual'; // 'visual' | 'code'
let savedRange = null;

// Флаги состояния и защита от потери данных
let isEditorDirty = false;
let localDraftSaveTimeout = null;
let isSavingArticle = false;

// Система истории изменений
const MAX_HISTORY_STATES = 50;
let historyStack = [];
let historyIndex = -1;
let isRestoringHistory = false;
let historySaveTimeout = null;
let lastActionType = null;
let lastActionTime = 0;
let cursorMoved = false;

function checkDevWarning() {
    if (window.isDevBuild) {
        const warningAccepted = localStorage.getItem('devWarningAccepted');
        if (!warningAccepted) {
            if (window.Modal) {
                Modal.open('#devWarningDialog');
            } else {
                const devWarningDialog = document.getElementById('devWarningDialog');
                if (devWarningDialog) devWarningDialog.style.display = 'flex';
            }
        }
    }
}

function confirmDevWarning() {
    localStorage.setItem('devWarningAccepted', 'true');
    if (window.Modal) {
        Modal.close('#devWarningDialog');
    } else {
        const devWarningDialog = document.getElementById('devWarningDialog');
        if (devWarningDialog) devWarningDialog.style.display = 'none';
    }
}

window.checkDevWarning = checkDevWarning;
window.confirmDevWarning = confirmDevWarning;


// Глобальная функция навигации по галерее
window.navigateGallery = function (galleryId, direction) {
    const gallery = document.getElementById(galleryId);
    if (!gallery) return;

    const images = gallery.querySelectorAll(`img[data-gallery="${galleryId}"]`);
    if (images.length <= 1) return;

    let currentIndex = -1;
    images.forEach((img, index) => {
        if (img.style.display !== 'none') {
            currentIndex = index;
        }
    });

    if (currentIndex === -1) currentIndex = 0;

    let newIndex = currentIndex + direction;
    if (newIndex < 0) newIndex = images.length - 1;
    if (newIndex >= images.length) newIndex = 0;

    images[currentIndex].style.display = 'none';
    images[newIndex].style.display = 'block';

    const indicator = gallery.querySelector('.gallery-indicator');
    if (indicator) {
        indicator.textContent = `${newIndex + 1} / ${images.length}`;
    }
};

// Поддержка свайпов на мобильных устройствах для галерей
document.addEventListener('DOMContentLoaded', function () {
    let touchStartX = 0;
    let touchEndX = 0;
    let targetGallery = null;

    document.addEventListener('touchstart', function (e) {
        const gallery = e.target.closest('.image-gallery');
        if (gallery) {
            targetGallery = gallery;
            touchStartX = e.changedTouches[0].screenX;
        }
    }, { passive: true });

    document.addEventListener('touchend', function (e) {
        if (!targetGallery) return;

        touchEndX = e.changedTouches[0].screenX;
        const galleryId = targetGallery.id;

        const swipeThreshold = 50;
        if (touchStartX - touchEndX > swipeThreshold) {
            // Свайп влево - следующее изображение
            window.navigateGallery(galleryId, 1);
        } else if (touchEndX - touchStartX > swipeThreshold) {
            // Свайп вправо - предыдущее изображение
            window.navigateGallery(galleryId, -1);
        }

        targetGallery = null;
    }, { passive: true });

    // Поддержка клавиатуры (стрелки) для активной галереи в редакторе
    const contentVisual = document.getElementById('contentVisual');
    if (contentVisual) {
        contentVisual.addEventListener('keydown', function (e) {
            // Проверяем, находится ли фокус внутри галереи или рядом с ней
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0) return;

            let node = selection.anchorNode;
            while (node && node !== contentVisual) {
                if (node.classList && node.classList.contains('image-gallery')) {
                    if (e.key === 'ArrowLeft') {
                        e.preventDefault();
                        window.navigateGallery(node.id, -1);
                    } else if (e.key === 'ArrowRight') {
                        e.preventDefault();
                        window.navigateGallery(node.id, 1);
                    }
                    break;
                }
                node = node.parentNode;
            }
        });

        // Добавляем возможность наведения для фокуса на галерее
        contentVisual.addEventListener('mouseenter', function (e) {
            const gallery = e.target.closest('.image-gallery');
            if (gallery) {
                gallery.setAttribute('data-focused', 'true');
            }
        }, true);

        contentVisual.addEventListener('mouseleave', function (e) {
            const gallery = e.target.closest('.image-gallery');
            if (gallery) {
                gallery.removeAttribute('data-focused');
            }
        }, true);
    }
});

/* ==========================================================================
   Custom Select Dropdown Engine
   ========================================================================== */
(function () {
    // Intercept HTMLSelectElement.prototype.value and selectedIndex to auto-sync custom UI
    const originalValueDescriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
    if (originalValueDescriptor && originalValueDescriptor.set) {
        Object.defineProperty(HTMLSelectElement.prototype, 'value', {
            get: function () {
                return originalValueDescriptor.get.call(this);
            },
            set: function (val) {
                const res = originalValueDescriptor.set.call(this, val);
                if (this.dataset && this.dataset.customSelectInitialized === 'true') {
                    syncCustomSelectFromNative(this);
                }
                return res;
            },
            configurable: true,
            enumerable: true
        });
    }

    const originalIndexDescriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'selectedIndex');
    if (originalIndexDescriptor && originalIndexDescriptor.set) {
        Object.defineProperty(HTMLSelectElement.prototype, 'selectedIndex', {
            get: function () {
                return originalIndexDescriptor.get.call(this);
            },
            set: function (val) {
                const res = originalIndexDescriptor.set.call(this, val);
                if (this.dataset && this.dataset.customSelectInitialized === 'true') {
                    syncCustomSelectFromNative(this);
                }
                return res;
            },
            configurable: true,
            enumerable: true
        });
    }

    function createChevronSvg() {
        return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>`;
    }

    function getSelectedOptionData(select) {
        const selectedOption = select.options[select.selectedIndex] || select.options[0];
        return {
            text: selectedOption ? (selectedOption.textContent || selectedOption.innerText || '') : '',
            value: selectedOption ? selectedOption.value : ''
        };
    }

    function rebuildCustomSelectOptions(select, wrapper) {
        const popoverInner = wrapper.querySelector('.custom-select-popover-inner');
        if (!popoverInner) return;

        popoverInner.innerHTML = '';
        const currentValue = select.value;

        Array.from(select.options).forEach((opt, index) => {
            const optBtn = document.createElement('button');
            optBtn.type = 'button';
            optBtn.className = 'custom-select-option' + (opt.value === currentValue || (!currentValue && index === 0) ? ' is-selected' : '');
            optBtn.dataset.value = opt.value;
            optBtn.dataset.index = index;
            optBtn.setAttribute('role', 'option');
            optBtn.setAttribute('aria-selected', opt.value === currentValue ? 'true' : 'false');
            if (opt.disabled) {
                optBtn.disabled = true;
                optBtn.style.opacity = '0.4';
                optBtn.style.cursor = 'not-allowed';
            }

            const textSpan = document.createElement('span');
            textSpan.className = 'custom-option-text';
            textSpan.textContent = opt.textContent || opt.innerText;

            const checkSpan = document.createElement('span');
            checkSpan.className = 'custom-option-check';
            checkSpan.textContent = '✓';

            optBtn.appendChild(textSpan);
            optBtn.appendChild(checkSpan);

            optBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (opt.disabled) return;

                selectCustomOption(select, wrapper, opt.value);
                closeCustomSelect(wrapper);
                const trigger = wrapper.querySelector('.custom-select-trigger');
                if (trigger) trigger.focus();
            });

            popoverInner.appendChild(optBtn);
        });

        // Update trigger label
        const selData = getSelectedOptionData(select);
        const valSpan = wrapper.querySelector('.custom-select-value');
        if (valSpan) {
            valSpan.textContent = selData.text || 'Выберите...';
        }
    }

    function selectCustomOption(select, wrapper, value) {
        if (select.value !== value) {
            select.value = value;
            // Dispatch standard events
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        syncCustomSelectFromNative(select);
    }

    function syncCustomSelectFromNative(select) {
        const wrapper = select.closest('.custom-select-wrapper');
        if (!wrapper) return;

        const selData = getSelectedOptionData(select);
        const valSpan = wrapper.querySelector('.custom-select-value');
        if (valSpan) {
            valSpan.textContent = selData.text || 'Выберите...';
        }

        const options = wrapper.querySelectorAll('.custom-select-option');
        options.forEach(optBtn => {
            const isMatch = optBtn.dataset.value === String(select.value);
            if (isMatch) {
                optBtn.classList.add('is-selected');
                optBtn.setAttribute('aria-selected', 'true');
            } else {
                optBtn.classList.remove('is-selected');
                optBtn.setAttribute('aria-selected', 'false');
            }
        });
    }

    function getAvailableDropdownSpace(wrapper) {
        const trigger = wrapper.querySelector('.custom-select-trigger') || wrapper;
        const rect = trigger.getBoundingClientRect();

        let container = wrapper.parentElement;
        let containerTop = 0;
        let containerBottom = window.innerHeight;

        while (container && container !== document.body && container !== document.documentElement) {
            const style = window.getComputedStyle(container);
            const overflow = (style.overflow || '') + (style.overflowY || '');
            if (/auto|scroll|hidden/.test(overflow) ||
                container.classList.contains('dialog-content') ||
                container.classList.contains('modal-content') ||
                container.classList.contains('manage-posts')) {
                const cRect = container.getBoundingClientRect();
                containerTop = Math.max(containerTop, cRect.top);
                containerBottom = Math.min(containerBottom, cRect.bottom);
                break;
            }
            container = container.parentElement;
        }

        const spaceBelow = containerBottom - rect.bottom;
        const spaceAbove = rect.top - containerTop;
        return { spaceBelow, spaceAbove };
    }

    function openCustomSelect(wrapper) {
        closeAllCustomSelects(wrapper);

        const popover = wrapper.querySelector('.custom-select-popover');
        const popoverInner = wrapper.querySelector('.custom-select-popover-inner');
        const trigger = wrapper.querySelector('.custom-select-trigger');
        if (!popover || !trigger) return;

        // Smart flip positioning calculation based on container bounds
        const { spaceBelow, spaceAbove } = getAvailableDropdownSpace(wrapper);
        const estimatedHeight = Math.min(200, popover.scrollHeight || 160);

        const shouldDropUp = (spaceBelow < estimatedHeight + 10 && spaceAbove > spaceBelow) || (spaceBelow < 120 && spaceAbove >= 100);

        if (shouldDropUp) {
            popover.classList.add('drop-up');
            if (popoverInner) {
                const maxHeight = Math.max(90, Math.min(200, spaceAbove - 16));
                popoverInner.style.maxHeight = maxHeight + 'px';
            }
        } else {
            popover.classList.remove('drop-up');
            if (popoverInner) {
                const maxHeight = Math.max(90, Math.min(200, spaceBelow - 16));
                popoverInner.style.maxHeight = maxHeight + 'px';
            }
        }

        wrapper.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');

        // Scroll selected option into view
        const selectedOpt = popover.querySelector('.custom-select-option.is-selected');
        if (selectedOpt) {
            selectedOpt.scrollIntoView({ block: 'nearest' });
        }
    }

    function closeCustomSelect(wrapper) {
        if (!wrapper) return;
        wrapper.classList.remove('is-open');
        const trigger = wrapper.querySelector('.custom-select-trigger');
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }
    }

    function closeAllCustomSelects(exceptWrapper) {
        document.querySelectorAll('.custom-select-wrapper.is-open').forEach(w => {
            if (w !== exceptWrapper) {
                closeCustomSelect(w);
            }
        });
    }

    function initCustomSelect(select) {
        if (!select || select.tagName !== 'SELECT') return;
        if (select.dataset && select.dataset.customSelectInitialized === 'true') return;
        if (select.hasAttribute('data-custom-select-ignore')) return;

        select.dataset.customSelectInitialized = 'true';
        select.classList.add('custom-select-native');
        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');

        // Create wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'custom-select-wrapper';
        if (select.id) wrapper.dataset.forSelect = select.id;

        // Detect compact / inline styling
        if (select.closest('.size-input-group') || select.classList.contains('compact-select')) {
            wrapper.classList.add('custom-select-compact');
        }

        // Transfer relevant classes or styling
        if (select.classList.contains('language-select')) {
            wrapper.classList.add('language-select-wrapper');
        }

        // Transfer spacing styles if present on native select
        if (select.style.marginBottom) {
            wrapper.style.marginBottom = select.style.marginBottom;
        }
        if (select.style.marginTop) {
            wrapper.style.marginTop = select.style.marginTop;
        }
        if (select.style.marginRight) {
            wrapper.style.marginRight = select.style.marginRight;
        }
        if (select.style.marginLeft) {
            wrapper.style.marginLeft = select.style.marginLeft;
        }

        // Insert wrapper before select and move select inside wrapper
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);

        // Create trigger button
        const selData = getSelectedOptionData(select);
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'custom-select-trigger';
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('tabindex', '0');
        if (select.id) trigger.id = select.id + '_customTrigger';
        if (select.title) trigger.title = select.title;

        const valSpan = document.createElement('span');
        valSpan.className = 'custom-select-value';
        valSpan.textContent = selData.text || 'Выберите...';

        const arrowSpan = document.createElement('span');
        arrowSpan.className = 'custom-select-arrow';
        arrowSpan.innerHTML = createChevronSvg();

        trigger.appendChild(valSpan);
        trigger.appendChild(arrowSpan);
        wrapper.appendChild(trigger);

        // Create popover menu
        const popover = document.createElement('div');
        popover.className = 'custom-select-popover';
        popover.setAttribute('role', 'listbox');

        const popoverInner = document.createElement('div');
        popoverInner.className = 'custom-select-popover-inner';
        popover.appendChild(popoverInner);
        wrapper.appendChild(popover);

        // Populate options
        rebuildCustomSelectOptions(select, wrapper);

        // Trigger click handler
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (select.disabled) return;
            if (wrapper.classList.contains('is-open')) {
                closeCustomSelect(wrapper);
            } else {
                openCustomSelect(wrapper);
            }
        });

        // Keyboard navigation
        trigger.addEventListener('keydown', function (e) {
            if (select.disabled) return;
            const isOpen = wrapper.classList.contains('is-open');
            const options = Array.from(wrapper.querySelectorAll('.custom-select-option:not([disabled])'));
            if (!options.length) return;

            let currentIndex = options.findIndex(opt => opt.classList.contains('is-selected'));
            if (currentIndex === -1) currentIndex = 0;

            if (e.key === 'ArrowDown' || e.key === 'Down') {
                e.preventDefault();
                if (!isOpen) {
                    openCustomSelect(wrapper);
                } else {
                    const nextIndex = (currentIndex + 1) % options.length;
                    selectCustomOption(select, wrapper, options[nextIndex].dataset.value);
                    options[nextIndex].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'ArrowUp' || e.key === 'Up') {
                e.preventDefault();
                if (!isOpen) {
                    openCustomSelect(wrapper);
                } else {
                    const prevIndex = (currentIndex - 1 + options.length) % options.length;
                    selectCustomOption(select, wrapper, options[prevIndex].dataset.value);
                    options[prevIndex].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (!isOpen) {
                    openCustomSelect(wrapper);
                } else {
                    closeCustomSelect(wrapper);
                }
            } else if (e.key === 'Escape' || e.key === 'Esc') {
                if (isOpen) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeCustomSelect(wrapper);
                }
            } else if (e.key === 'Tab') {
                if (isOpen) {
                    closeCustomSelect(wrapper);
                }
            }
        });

        // MutationObserver for select childList & attributes
        const observer = new MutationObserver(function (mutations) {
            let optionsChanged = false;
            let attrsChanged = false;
            for (const mut of mutations) {
                if (mut.type === 'childList') {
                    optionsChanged = true;
                } else if (mut.type === 'attributes') {
                    attrsChanged = true;
                }
            }
            if (optionsChanged) {
                rebuildCustomSelectOptions(select, wrapper);
            } else if (attrsChanged) {
                syncCustomSelectFromNative(select);
            }
        });
        observer.observe(select, { childList: true, attributes: true, subtree: true });

        // Listen to native change event
        select.addEventListener('change', function () {
            syncCustomSelectFromNative(select);
        });
    }

    function initAllCustomSelects(root) {
        const scope = root || document;
        const selects = scope.querySelectorAll('select:not([data-custom-select-initialized="true"]):not([data-custom-select-ignore])');
        selects.forEach(initCustomSelect);
    }

    // Global click listener to close open selects
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.custom-select-wrapper')) {
            closeAllCustomSelects();
        }
    });

    // Global escape key listener
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
            closeAllCustomSelects();
        }
    });

    // Observe document for dynamically added select elements
    if (typeof MutationObserver !== 'undefined') {
        const domObserver = new MutationObserver(function (mutations) {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node.nodeType === 1) {
                        if (node.tagName === 'SELECT') {
                            initCustomSelect(node);
                        } else if (node.querySelectorAll) {
                            initAllCustomSelects(node);
                        }
                    }
                }
            }
        });

        if (document.body) {
            domObserver.observe(document.body, { childList: true, subtree: true });
        } else {
            document.addEventListener('DOMContentLoaded', function () {
                domObserver.observe(document.body, { childList: true, subtree: true });
            });
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initAllCustomSelects();
        });
    } else {
        initAllCustomSelects();
    }

    // Expose APIs globally
    window.initCustomSelect = initCustomSelect;
    window.initCustomSelects = initAllCustomSelects;
    window.syncCustomSelect = syncCustomSelectFromNative;
})();

// Listen for language changes to invalidate caches and re-render dynamic submenus
window.addEventListener('npblog:langchange', function (e) {
    if (typeof draftsListLoaded !== 'undefined') draftsListLoaded = false;
    if (typeof includesListLoaded !== 'undefined') includesListLoaded = false;

    // If manage posts panel is active, refresh the list
    const managePanel = document.getElementById('managePosts');
    if (managePanel && managePanel.classList.contains('active')) {
        if (typeof loadPosts === 'function') loadPosts();
    }

    // If drafts submenu is currently open, refresh it
    const draftsSubmenu = document.getElementById('draftsSubmenu');
    const draftsItem = draftsSubmenu ? draftsSubmenu.closest('.more-menu-item.has-submenu') : null;
    if (draftsItem && draftsItem.classList.contains('submenu-open') && typeof loadDraftsList === 'function') {
        loadDraftsList();
    }

    // If includes submenu is currently open, refresh it
    const includesSubmenu = document.getElementById('includesSubmenu');
    const includesItem = includesSubmenu ? includesSubmenu.closest('.more-menu-item.has-submenu') : null;
    if (includesItem && includesItem.classList.contains('submenu-open') && typeof loadIncludesList === 'function') {
        loadIncludesList();
    }

    // If articles submenu is currently open, refresh it
    const articlesSubmenu = document.getElementById('articlesSubmenu');
    const articlesItem = articlesSubmenu ? articlesSubmenu.closest('.more-menu-item.has-submenu') : null;
    if (articlesItem && articlesItem.classList.contains('submenu-open') && typeof loadArticlesList === 'function') {
        loadArticlesList();
    }

    // If TOC submenu is currently open, refresh it
    const tocSubmenu = document.getElementById('tocSubmenu');
    const tocItem = tocSubmenu ? tocSubmenu.closest('.more-menu-item.has-submenu') : null;
    if (tocItem && tocItem.classList.contains('submenu-open') && typeof loadTocList === 'function') {
        loadTocList();
    }

    // If smiles submenu is currently open, refresh it
    const smilesSubmenu = document.getElementById('smilesSubmenu');
    const smilesItem = smilesSubmenu ? smilesSubmenu.closest('.more-menu-item.has-submenu') : null;
    if (smilesItem && smilesItem.classList.contains('submenu-open') && typeof loadSmilesSubmenuList === 'function') {
        loadSmilesSubmenuList();
    }
});


