/* Velora Store - small vanilla JS helpers (no libraries needed) */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  // Close buttons (top bar, filters drawer)
  $$('[data-close]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = $(b.getAttribute('data-close'));
      if (!t) return;
      if (t.classList.contains('filters')) t.classList.remove('open'); else t.hidden = true;
    });
  });
  $$('[data-open]').forEach(function (b) {
    b.addEventListener('click', function () { var t = $(b.getAttribute('data-open')); if (t) t.classList.add('open'); });
  });

  // Mobile menu
  var nav = $('#main-nav');
  $$('[data-toggle-menu]').forEach(function (b) {
    b.addEventListener('click', function (e) { e.stopPropagation(); nav.classList.toggle('open'); });
  });
  document.addEventListener('click', function (e) {
    if (nav && nav.classList.contains('open') && !nav.contains(e.target)) nav.classList.remove('open');
  });
  $$('.has-drop > a').forEach(function (a) {
    a.addEventListener('click', function (e) {
      if (window.innerWidth <= 900) { e.preventDefault(); a.parentNode.classList.toggle('open'); }
    });
  });

  // Dark mode
  $$('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var root = document.documentElement;
      var cur = root.getAttribute('data-theme') ||
        (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      var next = cur === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('theme', next); } catch (e) {}
    });
  });

  // Flash sale countdown
  $$('[data-countdown]').forEach(function (el) {
    var end = new Date(el.getAttribute('data-countdown')).getTime();
    if (isNaN(end)) return;
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var tick = function () {
      var d = Math.max(0, end - Date.now());
      $('[data-d]', el).textContent = pad(Math.floor(d / 864e5));
      $('[data-h]', el).textContent = pad(Math.floor(d / 36e5) % 24);
      $('[data-m]', el).textContent = pad(Math.floor(d / 6e4) % 60);
      $('[data-s]', el).textContent = pad(Math.floor(d / 1e3) % 60);
    };
    tick(); setInterval(tick, 1000);
  });

  // Slider arrows
  $$('[data-slider-nav]').forEach(function (navEl) {
    var slider = $(navEl.getAttribute('data-slider-nav'));
    if (!slider) return;
    $$('button', navEl).forEach(function (b) {
      b.addEventListener('click', function () {
        var dir = parseInt(b.getAttribute('data-dir'), 10);
        var item = slider.firstElementChild;
        var step = item ? item.getBoundingClientRect().width + 20 : 300;
        var atEnd = slider.scrollLeft + slider.clientWidth >= slider.scrollWidth - 5;
        if (dir > 0 && atEnd) slider.scrollTo({ left: 0, behavior: 'smooth' });
        else slider.scrollBy({ left: dir * step, behavior: 'smooth' });
      });
    });
  });

  // Quantity +/- buttons
  $$('[data-qty]').forEach(function (b) {
    b.addEventListener('click', function () {
      var input = $('input', b.parentNode);
      var min = parseInt(input.min || '0', 10), max = parseInt(input.max || '99', 10);
      var v = (parseInt(input.value, 10) || 0) + parseInt(b.getAttribute('data-qty'), 10);
      input.value = Math.min(max, Math.max(min, v));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });

  // Auto-submit selects (sort) and cart quantity changes
  $$('[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });
  var timer;
  $$('[data-autosubmit-delay]').forEach(function (i) {
    i.addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(function () { i.form.submit(); }, 600); });
  });

  // Product gallery
  var main = $('#main-img');
  $$('[data-thumb]').forEach(function (t) {
    t.addEventListener('click', function () {
      main.src = t.getAttribute('data-thumb');
      $$('[data-thumb]').forEach(function (x) { x.classList.remove('active'); });
      t.classList.add('active');
    });
  });

  // Products with variants (e.g. Printful): update price/image and block unavailable size+color combinations
  var vform = $('[data-variants]');
  if (vform) {
    var variants = JSON.parse(vform.getAttribute('data-variants'));
    var val = function (name) { var c = $('input[name=' + name + ']:checked', vform); return c ? c.value : ''; };
    var update = function () {
      var size = val('size'), color = val('color');
      var match = variants.filter(function (v) { return v.size === size && v.color === color; })[0];
      var priceEl = $('[data-price]');
      if (match && priceEl) priceEl.textContent = match.price;
      if (match && match.image && main) main.src = match.image;
      $$('[data-add-btn]', vform).forEach(function (b) { b.disabled = !match; });
      $('[data-variant-msg]', vform).hidden = !!match;
      // Grey out sizes that do not exist in the selected color
      $$('input[name=size]', vform).forEach(function (i) {
        var ok = variants.some(function (v) { return v.size === i.value && (color === '' || v.color === color); });
        i.parentNode.classList.toggle('unavailable', !ok);
      });
    };
    $$('input[name=size]', vform).forEach(function (i) { i.addEventListener('change', update); });
    // Keep the chosen size if it exists in the selected color, otherwise pick the first size that does
    var fixSize = function () {
      var color = val('color');
      var hasSize = function (s) { return variants.some(function (v) { return v.color === color && v.size === s; }); };
      if (!hasSize(val('size'))) {
        var first = $$('input[name=size]', vform).filter(function (x) { return hasSize(x.value); })[0];
        if (first) first.checked = true;
      }
      update();
    };
    $$('input[name=color]', vform).forEach(function (i) { i.addEventListener('change', fixSize); });
    fixSize();
  }

  // Prevent double submit on checkout
  var co = $('#checkout-form');
  if (co) co.addEventListener('submit', function () {
    var b = $('button[type=submit]', co);
    if (b) { b.disabled = true; b.textContent = b.getAttribute('data-loading-text') || '...'; }
  });
})();
