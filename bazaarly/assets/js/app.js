/* Bazaarly front-end — tiny, dependency-free. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var on = function (el, ev, fn) { el && el.addEventListener(ev, fn); };
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  };
  var cfg = window.BZR || { base: '', csrf: '' };

  /* theme */
  $$('[data-theme-toggle]').forEach(function (b) {
    on(b, 'click', function () {
      var next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
      document.documentElement.dataset.theme = next;
      store.set('bzr-theme', next);
    });
  });

  /* mobile nav */
  var navToggle = $('.nav-toggle'), nav = $('#mainNav');
  on(navToggle, 'click', function () {
    var open = nav.classList.toggle('open');
    navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  /* dropdowns: close on outside click / escape */
  document.addEventListener('click', function (e) {
    $$('details.dropdown[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
    if (nav && nav.classList.contains('open') && !nav.contains(e.target) && !navToggle.contains(e.target)) nav.classList.remove('open');
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') $$('details.dropdown[open]').forEach(function (d) { d.removeAttribute('open'); });
  });

  /* flash messages */
  $$('.alert-close').forEach(function (b) { on(b, 'click', function () { b.closest('.alert').remove(); }); });
  $$('.flash-stack .alert-success, .flash-stack .alert-info').forEach(function (a) {
    setTimeout(function () { a.style.transition = 'opacity .4s'; a.style.opacity = '0'; setTimeout(function () { a.remove(); }, 400); }, 6000);
  });

  /* favourites */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-fav]');
    if (!btn) return;
    e.preventDefault();
    var body = new FormData();
    body.append('action', 'favorite');
    body.append('id', btn.dataset.fav);
    body.append('_token', cfg.csrf);
    fetch(cfg.base + '/ajax.php', { method: 'POST', body: body, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
      .then(function (r) {
        if (r.status === 401) { location.href = cfg.base + '/login.php'; throw 0; }
        return r.json();
      })
      .then(function (d) {
        if (!d.ok) return;
        $$('[data-fav="' + btn.dataset.fav + '"]').forEach(function (b) {
          b.classList.toggle('on', d.saved);
          b.setAttribute('aria-pressed', d.saved ? 'true' : 'false');
          var lbl = $('[data-fav-label]', b);
          if (lbl) lbl.textContent = d.saved ? 'Saved' : 'Save';
        });
        btn.classList.remove('pulse'); void btn.offsetWidth; btn.classList.add('pulse');
      })
      .catch(function () {});
  });

  /* gallery */
  $$('[data-gallery]').forEach(function (g) {
    var imgs = $$('.gallery-stage img', g), thumbs = $$('[data-g-thumb]', g), cur = 0, counter = $('[data-g-current]', g);
    if (imgs.length < 2) return;
    function show(i) {
      cur = (i + imgs.length) % imgs.length;
      imgs.forEach(function (im, k) { im.classList.toggle('on', k === cur); });
      thumbs.forEach(function (t, k) { t.classList.toggle('on', k === cur); });
      if (counter) counter.textContent = cur + 1;
      if (thumbs[cur]) thumbs[cur].scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
    }
    on($('[data-g-prev]', g), 'click', function () { show(cur - 1); });
    on($('[data-g-next]', g), 'click', function () { show(cur + 1); });
    thumbs.forEach(function (t) { on(t, 'click', function () { show(+t.dataset.gThumb); }); });
    document.addEventListener('keydown', function (e) {
      if (/input|textarea|select/i.test(document.activeElement.tagName) || $('dialog[open]')) return;
      if (e.key === 'ArrowLeft') show(cur - 1);
      if (e.key === 'ArrowRight') show(cur + 1);
    });
    var sx = null, stage = $('.gallery-stage', g);
    on(stage, 'touchstart', function (e) { sx = e.touches[0].clientX; });
    on(stage, 'touchend', function (e) {
      if (sx === null) return;
      var dx = e.changedTouches[0].clientX - sx;
      if (Math.abs(dx) > 40) show(cur + (dx < 0 ? 1 : -1));
      sx = null;
    });
  });

  /* phone reveal */
  $$('[data-phone]').forEach(function (b) {
    on(b, 'click', function () {
      var t = $('[data-phone-text]', b);
      if (b.classList.contains('revealed')) { location.href = 'tel:' + b.dataset.phone.replace(/[^\d+]/g, ''); return; }
      t.textContent = b.dataset.phone;
      b.classList.add('revealed');
    });
  });

  /* share */
  $$('[data-share]').forEach(function (b) {
    on(b, 'click', function () {
      var url = b.dataset.url, title = b.dataset.title;
      if (navigator.share) { navigator.share({ title: title, url: url }).catch(function () {}); return; }
      if (navigator.clipboard) navigator.clipboard.writeText(url).then(function () {
        var old = b.innerHTML; b.textContent = 'Link copied!'; setTimeout(function () { b.innerHTML = old; }, 1800);
      });
    });
  });

  /* modals */
  $$('[data-modal-open]').forEach(function (b) {
    on(b, 'click', function () { var m = document.getElementById(b.dataset.modalOpen); m && m.showModal && m.showModal(); });
  });
  $$('dialog.modal').forEach(function (m) {
    on(m, 'click', function (e) { if (e.target === m) m.close(); });
    $$('[data-modal-close]', m).forEach(function (b) { on(b, 'click', function () { m.close(); }); });
  });

  /* confirmations */
  $$('form[data-confirm]').forEach(function (f) {
    on(f, 'submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
  });
  $$('[data-bulk-form]').forEach(function (f) {
    on(f, 'submit', function (e) {
      var any = $$('input[name="ids[]"]:checked', f).length;
      if (!any) { e.preventDefault(); alert('Select at least one item first.'); return; }
      if (f.bulk.value === 'delete' && !confirm('Delete ' + any + ' item(s) permanently?')) e.preventDefault();
    });
    on($('[data-check-all]', f), 'change', function () {
      var c = this.checked;
      $$('input[name="ids[]"]', f).forEach(function (i) { i.checked = c; });
    });
  });

  /* auto-submit selects */
  $$('[data-autosubmit]').forEach(function (s) { on(s, 'change', function () { s.form.submit(); }); });

  /* filters drawer (mobile) */
  var filters = $('#filters');
  $$('[data-open-filters]').forEach(function (b) { on(b, 'click', function () { filters.classList.add('open'); document.body.style.overflow = 'hidden'; }); });
  $$('[data-close-filters]').forEach(function (b) { on(b, 'click', function () { filters.classList.remove('open'); document.body.style.overflow = ''; }); });

  /* long descriptions */
  $$('[data-collapsible]').forEach(function (d) {
    if (d.scrollHeight < 300) return;
    d.classList.add('collapsed');
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'btn btn-ghost btn-sm show-more'; b.textContent = 'Show more';
    on(b, 'click', function () {
      var c = d.classList.toggle('collapsed');
      b.textContent = c ? 'Show more' : 'Show less';
    });
    d.after(b);
  });

  /* lazy map */
  $$('[data-load-map]').forEach(function (b) {
    on(b, 'click', function () {
      var box = b.closest('[data-map]'), f = document.createElement('iframe');
      f.src = 'https://maps.google.com/maps?q=' + encodeURIComponent(box.dataset.map) + '&z=12&output=embed';
      f.loading = 'lazy'; f.title = 'Map'; f.referrerPolicy = 'no-referrer-when-downgrade';
      box.innerHTML = ''; box.appendChild(f);
    });
  });

  /* listing form: price type */
  var priceField = $('[data-price-field]');
  function syncPrice() {
    var c = $('[data-price-type]:checked');
    if (priceField && c) priceField.hidden = c.value === 'free' || c.value === 'contact';
  }
  $$('[data-price-type]').forEach(function (r) { on(r, 'change', syncPrice); });
  syncPrice();

  /* listing form: upload preview & drag-drop */
  $$('[data-dropzone]').forEach(function (dz) {
    var input = $('input[type=file]', dz), preview = dz.parentNode.querySelector('[data-preview]'), max = +input.dataset.max;
    ['dragenter', 'dragover'].forEach(function (ev) { on(dz, ev, function () { dz.classList.add('drag'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { on(dz, ev, function () { dz.classList.remove('drag'); }); });
    on(input, 'change', function () {
      preview.innerHTML = '';
      var files = Array.prototype.slice.call(input.files);
      if (max >= 0 && files.length > max) {
        alert('You can add ' + max + ' more photo(s). Only the first ' + max + ' will be uploaded.');
      }
      files.slice(0, Math.max(0, max)).forEach(function (f) {
        if (!/^image\//.test(f.type)) return;
        var fig = document.createElement('figure'), img = document.createElement('img');
        img.src = URL.createObjectURL(f); img.alt = '';
        img.onload = function () { URL.revokeObjectURL(img.src); };
        fig.appendChild(img); preview.appendChild(fig);
      });
    });
  });

  /* password visibility */
  $$('[data-pw-toggle]').forEach(function (b) {
    on(b, 'click', function () {
      var i = b.parentNode.querySelector('input');
      i.type = i.type === 'password' ? 'text' : 'password';
    });
  });

  /* chat box */
  $$('[data-autogrow]').forEach(function (t) {
    var grow = function () { t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight, 160) + 'px'; };
    on(t, 'input', grow);
  });
  $$('[data-enter-submit]').forEach(function (t) {
    on(t, 'keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && window.innerWidth > 640) {
        e.preventDefault();
        if (t.value.trim()) t.form.submit();
      }
    });
  });
  $$('[data-scroll-bottom]').forEach(function (b) { b.scrollTop = b.scrollHeight; });

  /* cookie notice */
  var cookie = $('#cookieBar');
  if (cookie && !store.get('bzr-cookie')) cookie.hidden = false;
  $$('[data-cookie-accept]').forEach(function (b) {
    on(b, 'click', function () { store.set('bzr-cookie', '1'); cookie.hidden = true; });
  });

  /* admin sidebar */
  $$('[data-admin-menu]').forEach(function (b) {
    on(b, 'click', function () {
      var open = $('#adminSide').classList.toggle('open');
      $('.admin-scrim').classList.toggle('show', open);
    });
  });

  /* settings tabs */
  $$('[data-tabs]').forEach(function (wrap) {
    var links = $$('[data-tab]', wrap), panels = $$('[data-panel]', wrap), input = $('[data-tab-input]', wrap);
    function show(key) {
      if (!panels.some(function (p) { return p.dataset.panel === key; })) key = panels[0].dataset.panel;
      links.forEach(function (l) { l.classList.toggle('on', l.dataset.tab === key); });
      panels.forEach(function (p) { p.hidden = p.dataset.panel !== key; });
      if (input) input.value = key;
    }
    links.forEach(function (l) {
      on(l, 'click', function (e) { e.preventDefault(); show(l.dataset.tab); history.replaceState(null, '', '#' + l.dataset.tab); });
    });
    var h = location.hash.replace('#', '');
    show(h === 'maintenance' ? 'advanced' : h);
  });

  /* colour inputs */
  $$('[data-color-sync]').forEach(function (c) {
    on(c, 'input', function () { c.nextElementSibling.textContent = c.value; });
  });

  /* tiny HTML helper for pages */
  $$('[data-editor-for]').forEach(function (bar) {
    var ta = document.getElementById(bar.dataset.editorFor);
    $$('[data-wrap]', bar).forEach(function (b) {
      on(b, 'click', function () {
        var tag = b.dataset.wrap, s = ta.selectionStart, e = ta.selectionEnd, sel = ta.value.slice(s, e) || 'Text', out;
        if (tag === 'ul') out = '<ul>\n  <li>' + sel.split('\n').join('</li>\n  <li>') + '</li>\n</ul>';
        else if (tag === 'a') { var url = prompt('Link URL', 'https://'); if (!url) return; out = '<a href="' + url + '">' + sel + '</a>'; }
        else out = '<' + tag + '>' + sel + '</' + tag + '>';
        ta.setRangeText(out, s, e, 'end'); ta.focus();
      });
    });
  });
})();
