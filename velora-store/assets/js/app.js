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

  // Money formatting like the server (currency symbol, decimals)
  var formatMoney = function (n) {
    var fmt = (window.STORE && window.STORE.money) || { symbol: '$', after: false, dec: 2, trim: true };
    var str = n.toFixed(fmt.dec).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    if (fmt.trim) str = str.replace(/\.0+$/, '');
    return fmt.after ? str + ' ' + fmt.symbol : fmt.symbol + str;
  };

  // Product price = selected variant price (or base price) + extra price of the chosen custom options
  var addForm = $('.add-form');
  var currentBase = addForm ? parseFloat(addForm.getAttribute('data-base-price')) || 0 : 0;
  var updatePrice = function (base) {
    if (!addForm) return;
    if (typeof base === 'number') currentBase = base;
    var extra = 0;
    $$('input[name^="opt["]:checked', addForm).forEach(function (i) { extra += parseFloat(i.getAttribute('data-extra')) || 0; });
    var el = $('[data-price]');
    if (el) el.textContent = formatMoney(currentBase + extra);
  };
  if (addForm) $$('input[name^="opt["]', addForm).forEach(function (i) { i.addEventListener('change', function () { updatePrice(); }); });

  // Products with variants (e.g. Printful): update price/image and block unavailable size+color combinations
  var vform = $('[data-variants]');
  if (vform) {
    var variants = JSON.parse(vform.getAttribute('data-variants'));
    var val = function (name) { var c = $('input[name=' + name + ']:checked', vform); return c ? c.value : ''; };
    var update = function () {
      var size = val('size'), color = val('color');
      var match = variants.filter(function (v) { return v.size === size && v.color === color; })[0];
      if (match) updatePrice(match.amount);
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

  // Checkout: update delivery cost and total when another delivery method is chosen
  var summary = $('[data-summary]');
  if (summary) {
    var money = formatMoney;
    $$('input[name=shipping_method]').forEach(function (r) {
      r.addEventListener('change', function () {
        var cost = summary.getAttribute('data-free') === '1' ? 0 : parseFloat(r.getAttribute('data-cost')) || 0;
        $('[data-ship]', summary).textContent = cost > 0 ? money(cost) : 'Free';
        $('[data-total]').textContent = money(parseFloat(summary.getAttribute('data-base')) + cost);
      });
    });
  }

  // Product tabs (Description / Additional Information / Reviews)
  var openTab = function (name) {
    $$('[data-tab-btn]').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-tab-btn') === name); });
    $$('[data-tab-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-tab-pane') !== name; });
  };
  $$('[data-tab-btn]').forEach(function (b) { b.addEventListener('click', function () { openTab(b.getAttribute('data-tab-btn')); }); });
  $$('[data-open-tab]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault(); openTab(a.getAttribute('data-open-tab'));
      var t = $('#product-tabs'); if (t) t.scrollIntoView({ behavior: 'smooth' });
    });
  });
  if (location.hash === '#tab-reviews' && $('[data-tab-btn=reviews]')) openTab('reviews');

  // Review form: "save my name and email in this browser"
  var rf = $('#review-form');
  if (rf) {
    try {
      var saved = JSON.parse(localStorage.getItem('review_author') || 'null');
      if (saved) { $('[data-remember=name]', rf).value = saved.name || ''; $('[data-remember=email]', rf).value = saved.email || ''; $('[data-remember-me]', rf).checked = true; }
    } catch (e) {}
    rf.addEventListener('submit', function () {
      try {
        if ($('[data-remember-me]', rf).checked) localStorage.setItem('review_author', JSON.stringify({ name: $('[data-remember=name]', rf).value, email: $('[data-remember=email]', rf).value }));
        else localStorage.removeItem('review_author');
      } catch (e) {}
    });
  }

  // Back to top button
  var toTop = $('#to-top');
  if (toTop) {
    var onScroll = function () { toTop.classList.toggle('show', window.scrollY > 500); };
    window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
    toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
  }

  // Subscribe popup
  var popup = $('#promo-popup');
  if (popup) {
    var key = 'promo_popup_' + popup.getAttribute('data-version');
    var days = parseFloat(popup.getAttribute('data-days')) || 0;
    var seen = 0;
    try { seen = parseInt(localStorage.getItem(key) || '0', 10); } catch (e) {}
    var closePopup = function () {
      popup.classList.remove('open');
      try { localStorage.setItem(key, String(Date.now())); } catch (e) {}
    };
    if (popup.hasAttribute('data-force') || !seen || (days > 0 && Date.now() - seen > days * 864e5)) {
      setTimeout(function () { popup.classList.add('open'); }, (parseFloat(popup.getAttribute('data-delay')) || 0) * 1000);
    }
    $$('[data-popup-close]', popup).forEach(function (b) { b.addEventListener('click', closePopup); });
    var pform = $('form', popup);
    if (pform) pform.addEventListener('submit', function () { try { localStorage.setItem(key, String(Date.now())); } catch (e) {} });
    popup.addEventListener('click', function (e) { if (e.target === popup) closePopup(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && popup.classList.contains('open')) closePopup(); });
    var copyBtn = $('[data-copy]', popup);
    if (copyBtn) copyBtn.addEventListener('click', function () {
      var code = copyBtn.getAttribute('data-copy');
      var done = function () { copyBtn.querySelector('span').textContent = 'COPIED!'; setTimeout(function () { copyBtn.querySelector('span').textContent = 'COPY CODE'; }, 2000); };
      if (navigator.clipboard) navigator.clipboard.writeText(code).then(done, done); else done();
    });
  }

  // Prevent double submit on checkout
  var co = $('#checkout-form');
  if (co) co.addEventListener('submit', function () {
    var b = $('button[type=submit]', co);
    if (b) { b.disabled = true; b.textContent = b.getAttribute('data-loading-text') || '...'; }
  });
})();
