(function() {
  'use strict';

  let currentIndex = 0;
  let images = [];

  function openLightbox(index) {
    const items = document.querySelectorAll('[data-lightbox]');
    if (!items.length) return;
    images = Array.from(items).map(el => ({
      src: el.getAttribute('data-lightbox'),
      alt: el.getAttribute('alt') || 'Image'
    }));
    currentIndex = Math.min(index, images.length - 1);
    renderLightbox();
  }

  function renderLightbox() {
    const existing = document.getElementById('rdl-lightbox');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'rdl-lightbox';
    overlay.innerHTML = `
      <div class="lightbox-backdrop" id="lightbox-backdrop"></div>
      <div class="lightbox-container">
        <button class="lightbox-close" id="lightbox-close">&times;</button>
        <button class="lightbox-nav lightbox-prev" id="lightbox-prev">&#8249;</button>
        <button class="lightbox-nav lightbox-next" id="lightbox-next">&#8250;</button>
        <div class="lightbox-content">
          <img id="lightbox-image" src="${images[currentIndex].src}" alt="${images[currentIndex].alt}">
          <div class="lightbox-counter">${currentIndex + 1} / ${images.length}</div>
        </div>
      </div>
    `;

    document.body.appendChild(overlay);

    document.getElementById('lightbox-close').addEventListener('click', closeLightbox);
    document.getElementById('lightbox-backdrop').addEventListener('click', closeLightbox);
    document.getElementById('lightbox-prev').addEventListener('click', () => navigate(-1));
    document.getElementById('lightbox-next').addEventListener('click', () => navigate(1));

    document.addEventListener('keydown', keyHandler);

    document.body.style.overflow = 'hidden';
  }

  function navigate(dir) {
    currentIndex = (currentIndex + dir + images.length) % images.length;
    const img = document.getElementById('lightbox-image');
    img.src = images[currentIndex].src;
    img.alt = images[currentIndex].alt;
    document.querySelector('.lightbox-counter').textContent = `${currentIndex + 1} / ${images.length}`;
  }

  function keyHandler(e) {
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') navigate(-1);
    if (e.key === 'ArrowRight') navigate(1);
  }

  function closeLightbox() {
    const overlay = document.getElementById('rdl-lightbox');
    if (overlay) overlay.remove();
    document.removeEventListener('keydown', keyHandler);
    document.body.style.overflow = '';
  }

  // Click-to-open binding
  document.addEventListener('click', function(e) {
    const trigger = e.target.closest('[data-lightbox]');
    if (trigger) {
      e.preventDefault();
      const items = document.querySelectorAll('[data-lightbox]');
      const idx = Array.from(items).indexOf(trigger);
      openLightbox(idx);
    }
  });

  // Touch swipe support
  let touchStartX = 0;
  let touchEndX = 0;

  document.addEventListener('touchstart', function(e) {
    if (!document.getElementById('rdl-lightbox')) return;
    touchStartX = e.changedTouches[0].screenX;
  }, { passive: true });

  document.addEventListener('touchend', function(e) {
    if (!document.getElementById('rdl-lightbox')) return;
    touchEndX = e.changedTouches[0].screenX;
    const diff = touchStartX - touchEndX;
    if (Math.abs(diff) > 50) {
      navigate(diff > 0 ? 1 : -1);
    }
  }, { passive: true });
})();
