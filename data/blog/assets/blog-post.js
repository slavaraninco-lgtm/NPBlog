// Детекция Android для применения специальных шрифтов
if (/Android/i.test(navigator.userAgent)) {
    document.documentElement.classList.add('is-android');
}

function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
}

const savedTheme = localStorage.getItem('theme') || 'light';
document.documentElement.setAttribute('data-theme', savedTheme);

let currentZoom = 1;
let currentImageSrc = '';
let isDragging = false;
let startX, startY, translateX = 0, translateY = 0;
let currentFittedWidth = 0;
let currentFittedHeight = 0;

let modalImages = [];
let currentModalIndex = 0;

document.addEventListener('DOMContentLoaded', function() {
    // Collect all valid images in content (exclude smiles/emojis)
    const contentImages = Array.from(document.querySelectorAll('.content img')).filter(function(img) {
        return !img.classList.contains('blog-smile');
    });
    
    modalImages = contentImages.map(img => img.src);
    
    contentImages.forEach(function(img, index) {
        img.addEventListener('click', function(e) {
            e.stopPropagation();
            openImageModalAtIndex(index);
        });
    });
    
    // Dynamically insert navigation arrows if they are not in the DOM
    ensureModalNavigation();
    
    // Инициализация галерей
    initGalleries();
    
    // Подгрузка глобального фона и шрифтов
    applyGlobalSettings();
});

function openImageModal(src) {
    let index = modalImages.indexOf(src);
    if (index === -1) {
        modalImages.push(src);
        index = modalImages.length - 1;
    }
    openImageModalAtIndex(index);
}

function openImageModalAtIndex(index) {
    if (index < 0 || index >= modalImages.length) return;
    currentModalIndex = index;
    currentImageSrc = modalImages[index];
    
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    if (!modal || !modalImg) return;
    
    modalImg.src = currentImageSrc;
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    currentZoom = 1;
    translateX = 0;
    translateY = 0;
    
    modalImg.onload = function() {
        centerImage();
    };
    
    if (modalImg.complete) {
        centerImage();
    }
    
    updateModalNavigationVisibility();
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    if (modal) modal.classList.remove('show');
    document.body.style.overflow = '';
    currentZoom = 1;
    translateX = 0;
    translateY = 0;
}

function centerImage() {
    const modalImg = document.getElementById('modalImage');
    const container = document.getElementById('imageContainer');
    if (!modalImg || !container) return;
    
    const containerRect = container.getBoundingClientRect();
    const naturalWidth = modalImg.naturalWidth || 800;
    const naturalHeight = modalImg.naturalHeight || 600;
    
    // Fit the image in the container (only shrink, don't upscale beyond natural size)
    const ratio = Math.min(containerRect.width / naturalWidth, containerRect.height / naturalHeight, 1);
    
    currentFittedWidth = naturalWidth * ratio;
    currentFittedHeight = naturalHeight * ratio;
    
    const imgWidth = currentFittedWidth * currentZoom;
    const imgHeight = currentFittedHeight * currentZoom;
    
    translateX = (containerRect.width - imgWidth) / 2;
    translateY = (containerRect.height - imgHeight) / 2;
    
    updateImageTransform();
}

function updateImageTransform() {
    const modalImg = document.getElementById('modalImage');
    const zoomLevel = document.getElementById('zoomLevel');
    if (!modalImg) return;
    
    if (currentFittedWidth && currentFittedHeight) {
        modalImg.style.width = currentFittedWidth + 'px';
        modalImg.style.height = currentFittedHeight + 'px';
    }
    
    modalImg.style.transform = 'translate(' + translateX + 'px, ' + translateY + 'px) scale(' + currentZoom + ')';
    if (zoomLevel) {
        zoomLevel.textContent = Math.round(currentZoom * 100) + '%';
    }
}

function zoomIn() {
    if (currentZoom < 5) {
        currentZoom += 0.25;
        updateImageTransform();
    }
}

function zoomOut() {
    if (currentZoom > 0.25) {
        currentZoom -= 0.25;
        updateImageTransform();
    }
}

function resetZoom() {
    currentZoom = 1;
    centerImage();
}

function downloadImage() {
    const link = document.createElement('a');
    link.href = currentImageSrc;
    link.download = currentImageSrc.split('/').pop();
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function navigateModalImage(direction) {
    if (modalImages.length <= 1) return;
    
    let newIndex = currentModalIndex + direction;
    if (newIndex < 0) newIndex = modalImages.length - 1;
    if (newIndex >= modalImages.length) newIndex = 0;
    
    const modalImg = document.getElementById('modalImage');
    if (modalImg) {
        modalImg.style.opacity = 0;
        setTimeout(() => {
            openImageModalAtIndex(newIndex);
            modalImg.style.opacity = 1;
        }, 150);
    }
}

function ensureModalNavigation() {
    const modal = document.getElementById('imageModal');
    if (!modal) return;
    
    if (!document.querySelector('.image-modal-prev')) {
        const prevBtn = document.createElement('button');
        prevBtn.className = 'image-modal-nav image-modal-prev';
        prevBtn.innerHTML = '‹';
        prevBtn.title = 'Предыдущее изображение';
        prevBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            navigateModalImage(-1);
        });
        modal.appendChild(prevBtn);
    }
    
    if (!document.querySelector('.image-modal-next')) {
        const nextBtn = document.createElement('button');
        nextBtn.className = 'image-modal-nav image-modal-next';
        nextBtn.innerHTML = '›';
        nextBtn.title = 'Следующее изображение';
        nextBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            navigateModalImage(1);
        });
        modal.appendChild(nextBtn);
    }
}

function updateModalNavigationVisibility() {
    const prevBtn = document.querySelector('.image-modal-prev');
    const nextBtn = document.querySelector('.image-modal-next');
    
    if (prevBtn && nextBtn) {
        if (modalImages.length > 1) {
            prevBtn.style.display = 'flex';
            nextBtn.style.display = 'flex';
        } else {
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
        }
    }
}

window.addEventListener('resize', function() {
    const modal = document.getElementById('imageModal');
    if (modal && modal.classList.contains('show')) {
        centerImage();
    }
});

// Export to global scope
window.openImageModal = openImageModal;
window.closeImageModal = closeImageModal;
window.zoomIn = zoomIn;
window.zoomOut = zoomOut;
window.resetZoom = resetZoom;
window.downloadImage = downloadImage;
window.navigateModalImage = navigateModalImage;

// Загрузка глобальных настроек (фон, шрифты)
async function applyGlobalSettings() {
    try {
        // Загружаем глобальные настройки
        const globalRes = await fetch('../global-settings.json?t=' + Date.now());
        const globalSettings = globalRes.ok ? await globalRes.json() : {};
        
        // Скрываем или показываем "Powered by NPBlog"
        const poweredBy = document.querySelector('.powered-by');
        if (poweredBy) {
            if (globalSettings.hidePoweredBy) {
                poweredBy.style.display = 'none';
            } else {
                poweredBy.style.display = '';
            }
        }
        
        // Получаем ID статьи
        const metaTag = document.querySelector('meta[name="post-id"]');
        const postId = metaTag ? metaTag.getAttribute('content') : null;
        
        // Загружаем настройки фонов для статей
        let postBackgrounds = {};
        try {
            const bgRes = await fetch('../post_backgrounds.json?t=' + Date.now());
            if (bgRes.ok) {
                postBackgrounds = await bgRes.json();
            }
        } catch (e) {
            console.warn('Could not load post backgrounds', e);
        }
        
        // Применяем настройки
        resetBackgrounds();
        let appliedBg = false;
        if (postId && postBackgrounds[postId]) {
            const bgSettings = postBackgrounds[postId];
            if (bgSettings.background) {
                applyBackground(bgSettings);
                appliedBg = true;
            }
            if (bgSettings.overlayEnabled) {
                applyOverlay(bgSettings);
            }
        }
        
        // Если для статьи нет своего фона, применяем глобальный
        if (!appliedBg && globalSettings.background) {
            applyBackground(globalSettings);
        }
    } catch (error) {
        console.error('Ошибка загрузки настроек:', error);
    }
}

function resetBackgrounds() {
    document.documentElement.style.backgroundImage = '';
    document.documentElement.style.backgroundRepeat = '';
    document.documentElement.style.backgroundPosition = '';
    document.documentElement.style.backgroundSize = '';
    document.documentElement.style.backgroundAttachment = '';
    
    document.body.style.backgroundImage = '';
    document.body.style.backgroundRepeat = '';
    document.body.style.backgroundPosition = '';
    document.body.style.backgroundSize = '';
    document.body.style.backgroundAttachment = '';
    document.body.style.backgroundColor = '';
}

function applyBackground(settings) {
    const bgFile = settings.background;
    const bgMode = settings.backgroundMode || 'cover';
    const bgScope = settings.backgroundScope || 'content';
    
    let targetElement;
    if (bgScope === 'fullpage') {
        targetElement = document.documentElement;
        // Make body transparent
        document.body.style.background = 'transparent';
        document.body.style.backgroundColor = 'transparent';
    } else {
        let contentWrapper = document.querySelector('.content-wrapper');
        if (!contentWrapper) {
            contentWrapper = document.createElement('div');
            contentWrapper.className = 'content-wrapper';
            
            const h1 = document.querySelector('h1');
            if (h1) {
                const backLink = document.querySelector('.back-link');
                let node = h1;
                const nodesToMove = [];
                while (node) {
                    nodesToMove.push(node);
                    if (node === backLink) break;
                    node = node.nextSibling;
                }
                h1.parentNode.insertBefore(contentWrapper, h1);
                nodesToMove.forEach(n => contentWrapper.appendChild(n));
            }
        }
        targetElement = contentWrapper;
        
        // Clean up root html element background
        document.documentElement.style.backgroundImage = '';
        document.body.style.background = '';
    }
    
    if (targetElement) {
        targetElement.style.backgroundImage = `url('../backgrounds/${bgFile}')`;
        if (bgMode === 'repeat') {
            targetElement.style.backgroundRepeat = 'repeat';
            targetElement.style.backgroundPosition = 'center';
            targetElement.style.backgroundSize = 'auto';
            targetElement.style.backgroundAttachment = 'scroll';
        } else if (bgMode === 'contain') {
            targetElement.style.backgroundRepeat = 'no-repeat';
            targetElement.style.backgroundPosition = 'center';
            targetElement.style.backgroundSize = 'contain';
            targetElement.style.backgroundAttachment = 'fixed';
        } else { // cover
            targetElement.style.backgroundRepeat = 'no-repeat';
            targetElement.style.backgroundPosition = 'center';
            targetElement.style.backgroundSize = 'cover';
            targetElement.style.backgroundAttachment = 'fixed';
        }
    }
}

function applyOverlay(settings) {
    const overlayColor = settings.overlayColor;
    const overlayOpacity = settings.overlayOpacity;
    
    const hex = overlayColor.replace('#', '');
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    const alpha = overlayOpacity / 100;
    
    const overlayWrapper = document.createElement('div');
    overlayWrapper.className = 'overlay-wrapper';
    overlayWrapper.style.background = `rgba(${r}, ${g}, ${b}, ${alpha})`;
    
    const h1 = document.querySelector('h1');
    if (!h1) return;
    
    const backLink = document.querySelector('.back-link');
    
    let node = h1;
    const nodesToMove = [];
    while (node) {
        nodesToMove.push(node);
        if (node === backLink) break;
        node = node.nextSibling;
    }
    
    h1.parentNode.insertBefore(overlayWrapper, h1);
    nodesToMove.forEach(n => overlayWrapper.appendChild(n));
}

// Слушатели событий
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('imageContainer');
    const modalImg = document.getElementById('modalImage');
    
    if (modalImg) {
        modalImg.addEventListener('dragstart', function(e) {
            e.preventDefault();
        });
    }
    
    if (container) {
        container.addEventListener('mousedown', function(e) {
            if (e.target === modalImg) {
                e.preventDefault();
                isDragging = true;
                startX = e.clientX - translateX;
                startY = e.clientY - translateY;
                container.classList.add('dragging');
            }
        });
    }
});

document.addEventListener('mousemove', function(e) {
    if (isDragging) {
        e.preventDefault();
        translateX = e.clientX - startX;
        translateY = e.clientY - startY;
        updateImageTransform();
    }
});

document.addEventListener('mouseup', function() {
    isDragging = false;
    const container = document.getElementById('imageContainer');
    if (container) container.classList.remove('dragging');
});

document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('imageModal');
    if (modal && modal.classList.contains('show')) {
        if (e.key === 'Escape') {
            closeImageModal();
        } else if (e.key === 'ArrowLeft') {
            navigateModalImage(-1);
        } else if (e.key === 'ArrowRight') {
            navigateModalImage(1);
        }
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('imageModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });
    }
});


// Инициализация галерей с пролистыванием
function initGalleries() {
    const galleries = document.querySelectorAll('.image-gallery');
    galleries.forEach(gallery => {
        const galleryId = gallery.id;
        if (!galleryId) return;
        
        // Добавляем обработчики для изображений - клик открывает модальное окно
        const images = gallery.querySelectorAll(`img[data-gallery="${galleryId}"]`);
        images.forEach(img => {
            if (!img.hasAttribute('data-gallery-initialized')) {
                img.setAttribute('data-gallery-initialized', 'true');
                img.style.cursor = 'pointer';
                img.addEventListener('click', function(e) {
                    // Только если клик не по кнопке навигации
                    if (!e.target.closest('.gallery-nav')) {
                        openImageModal(this.src);
                    }
                });
            }
        });
        
        // Убираем старые обработчики с кнопок, чтобы не было дублирования
        const prevBtn = gallery.querySelector('.gallery-prev');
        const nextBtn = gallery.querySelector('.gallery-next');
        
        if (prevBtn) {
            prevBtn.removeAttribute('onclick');
            if (!prevBtn.hasAttribute('data-initialized')) {
                prevBtn.setAttribute('data-initialized', 'true');
                prevBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    window.navigateGallery(galleryId, -1);
                });
            }
        }
        
        if (nextBtn) {
            nextBtn.removeAttribute('onclick');
            if (!nextBtn.hasAttribute('data-initialized')) {
                nextBtn.setAttribute('data-initialized', 'true');
                nextBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    window.navigateGallery(galleryId, 1);
                });
            }
        }
        
        // Поддержка клавиатуры (стрелки влево/вправо) при наведении на галерею
        gallery.addEventListener('mouseenter', function() {
            gallery.setAttribute('data-focused', 'true');
        });
        
        gallery.addEventListener('mouseleave', function() {
            gallery.removeAttribute('data-focused');
        });
    });
    
    // Глобальный обработчик клавиатуры для активной галереи
    document.addEventListener('keydown', function(e) {
        const focusedGallery = document.querySelector('.image-gallery[data-focused="true"]');
        if (focusedGallery) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                window.navigateGallery(focusedGallery.id, -1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                window.navigateGallery(focusedGallery.id, 1);
            }
        }
    });
}

// Навигация по галерее
function navigateGallery(galleryId, direction) {
    const gallery = document.getElementById(galleryId);
    if (!gallery) return;
    
    const images = gallery.querySelectorAll(`img[data-gallery="${galleryId}"]`);
    if (images.length <= 1) return;
    
    let currentIndex = -1;
    images.forEach((img, index) => {
        const computedDisplay = window.getComputedStyle(img).display;
        if (computedDisplay === 'block' || (computedDisplay !== 'none' && index === 0)) {
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
}

// Экспортируем функцию в глобальный scope
window.navigateGallery = navigateGallery;

// Поддержка свайпов на мобильных устройствах
(function() {
    let touchStartX = 0;
    let touchEndX = 0;
    let targetGallery = null;
    
    document.addEventListener('touchstart', function(e) {
        const gallery = e.target.closest('.image-gallery');
        if (gallery) {
            targetGallery = gallery;
            touchStartX = e.changedTouches[0].screenX;
        }
    }, { passive: true });
    
    document.addEventListener('touchend', function(e) {
        if (!targetGallery) return;
        
        touchEndX = e.changedTouches[0].screenX;
        const galleryId = targetGallery.id;
        
        const swipeThreshold = 50;
        if (touchStartX - touchEndX > swipeThreshold) {
            // Свайп влево - следующее изображение
            navigateGallery(galleryId, 1);
        } else if (touchEndX - touchStartX > swipeThreshold) {
            // Свайп вправо - предыдущее изображение
            navigateGallery(galleryId, -1);
        }
        
        targetGallery = null;
    }, { passive: true });
})();

// Поддержка свайпов внутри модального окна просмотра изображений
(function() {
    let touchStartX = 0;
    let touchEndX = 0;
    let touchStartY = 0;
    let touchEndY = 0;
    
    document.addEventListener('touchstart', function(e) {
        const modal = document.getElementById('imageModal');
        if (modal && modal.classList.contains('show')) {
            // Разрешаем свайп только если нет зума
            if (typeof currentZoom !== 'undefined' && currentZoom === 1) {
                touchStartX = e.changedTouches[0].screenX;
                touchStartY = e.changedTouches[0].screenY;
            }
        }
    }, { passive: true });
    
    document.addEventListener('touchend', function(e) {
        const modal = document.getElementById('imageModal');
        if (modal && modal.classList.contains('show') && typeof currentZoom !== 'undefined' && currentZoom === 1) {
            touchEndX = e.changedTouches[0].screenX;
            touchEndY = e.changedTouches[0].screenY;
            
            const diffX = touchStartX - touchEndX;
            const diffY = touchStartY - touchEndY;
            
            const swipeThreshold = 50;
            // Проверяем, что свайп горизонтальный и превышает порог
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > swipeThreshold) {
                if (diffX > 0) {
                    // Свайп влево -> Следующее изображение
                    navigateModalImage(1);
                } else {
                    // Свайп вправо -> Предыдущее изображение
                    navigateModalImage(-1);
                }
            }
        }
    }, { passive: true });
})();

