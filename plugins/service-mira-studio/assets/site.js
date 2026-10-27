/* Accessible mobile navigation and standalone dropdown behavior. */
(function () {
  'use strict';
  const nav = document.getElementById('sm-main-nav');
  const toggle = document.querySelector('.sm-mobile-toggle');
  if (!nav || !toggle) return;
  function closeNav() { nav.classList.remove('is-open'); toggle.setAttribute('aria-expanded','false'); toggle.setAttribute('aria-label','Menü öffnen'); }
  toggle.addEventListener('click', function () {
    const next = toggle.getAttribute('aria-expanded') !== 'true';
    nav.classList.toggle('is-open',next);
    toggle.setAttribute('aria-expanded',String(next));
    toggle.setAttribute('aria-label',next?'Menü schließen':'Menü öffnen');
  });
  const drops = Array.prototype.slice.call(nav.querySelectorAll('.sm-nav-dropdown'));
  drops.forEach(function(drop) {
    const button = drop.querySelector('.sm-nav-dropbtn');
    if (!button) return;
    button.addEventListener('click', function () {
      const opened = button.getAttribute('aria-expanded') === 'true';
      drops.forEach(function(d) {
        d.classList.remove('is-open');
        const b = d.querySelector('.sm-nav-dropbtn');
        if (b) b.setAttribute('aria-expanded','false');
      });
      if (!opened) { drop.classList.add('is-open'); button.setAttribute('aria-expanded','true'); }
    });
  });
  document.addEventListener('keydown',function(ev) {
    if (ev.key === 'Escape') {
      closeNav();
      drops.forEach(function(d) { d.classList.remove('is-open'); const b=d.querySelector('.sm-nav-dropbtn'); if(b)b.setAttribute('aria-expanded','false'); });
    }
  });
  document.addEventListener('click', function(ev) {
    if (!nav.contains(ev.target) && !toggle.contains(ev.target)) {
      closeNav();
      drops.forEach(function(d) { d.classList.remove('is-open'); const b=d.querySelector('.sm-nav-dropbtn'); if(b)b.setAttribute('aria-expanded','false'); });
    }
  });
  window.addEventListener('resize', function() { if (window.innerWidth > 800) closeNav(); });
}());

/* v1.4: Legacy pricing blocks intentionally retain their original DOM order.
 * No service-price grouping or text rewriting is performed in JavaScript. */

/* v1.5: Build optional legal page section navigation from existing headings.
 * No original heading text, price, legal text or content ordering is changed. */
(function () {
  "use strict";
  var article = document.querySelector('.sm-legal-page .sm-standard-content');
  var target = document.querySelector('[data-sm-toc]');
  if (!article) return;
  // No contents sidebar on legal pages: cleanup must not depend on it.
  if (!target) return;
  var heads = Array.prototype.filter.call(article.querySelectorAll('h2,h3'), function (h) {
    return (h.textContent || '').trim().length > 2;
  });
  heads.slice(0, 24).forEach(function (h, index) {
    if (!h.id) h.id = 'sm-legal-section-' + (index + 1);
    var a = document.createElement('a');
    a.href = '#' + encodeURIComponent(h.id);
    a.textContent = h.textContent.trim();
    if (h.tagName.toLowerCase() === 'h3') a.className = 'sm-toc-subitem';
    target.appendChild(a);
  });
  if (target.children.length) target.closest('.sm-content-aside-card').classList.add('sm-has-toc');
  // On small screens, keep long legal notices comfortable to scroll.
  var drawer = target.closest('.sm-toc-drawer');
  if (drawer && window.matchMedia && window.matchMedia('(max-width: 760px)').matches) drawer.open = false;
  // Collapse only completely blank legacy table rows. All data cells are retained.
  article.querySelectorAll('table tr').forEach(function (row) {
    var cells = Array.prototype.slice.call(row.querySelectorAll('td,th'));
    if (cells.length && cells.every(function (cell) {
      return !cell.textContent.trim() && !cell.querySelector('img,svg,a,input,button,select,textarea');
    })) row.classList.add('sm-empty-legal-row');
  });
})();

/* v2.0.50: Presentational cleanup of old legal markup, never rewrite legal text. */
(function () {
  'use strict';
  var article = document.querySelector('.sm-legal-page .sm-standard-content');
  if (!article) return;
  article.querySelectorAll('table tr').forEach(function (row) {
    var cells = Array.prototype.slice.call(row.querySelectorAll('td,th'));
    if (cells.length && cells.every(function (cell) {
      return !(cell.textContent || '').replace(/[\s\u00a0\u200b]/g, '') && !cell.querySelector('img,svg,a,input,button,select,textarea');
    })) row.classList.add('sm-empty-legal-row');
  });
  article.querySelectorAll('p,h1,h2,h3,h4,h5,h6,div,pre').forEach(function (node) {
    if (node === article || node.closest('td,th')) return;
    if (!(node.textContent || '').replace(/[\s\u00a0\u200b]/g, '') && !node.querySelector('img,svg,iframe,video,input,button,a')) {
      node.classList.add('sm-empty-legal-spacer');
    }
  });
})();
