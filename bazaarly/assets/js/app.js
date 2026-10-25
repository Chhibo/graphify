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
  var L = function (s) { return (cfg.i18n && cfg.i18n[s]) || s; };
  var post = function (url, data) {
    var body = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
    if (!body.has('_token')) body.append('_token', cfg.csrf);
    return fetch(url, { method: 'POST', body: body, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
  };

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
          if (lbl) lbl.textContent = d.saved ? (b.dataset.labelOn || L('Saved')) : (b.dataset.labelOff || L('Save'));
        });
        btn.classList.remove('pulse'); void btn.offsetWidth; btn.classList.add('pulse');
      })
      .catch(function () {});
  });

  /* gallery */
  $$('[data-gallery]').forEach(function (g) {
    var imgs = $$('.gallery-stage .g-slide', g), thumbs = $$('[data-g-thumb]', g), cur = 0, counter = $('[data-g-current]', g);
    if (imgs.length < 2) return;
    function show(i) {
      cur = (i + imgs.length) % imgs.length;
      imgs.forEach(function (im, k) {
        im.classList.toggle('on', k === cur);
        var f = k !== cur && im.querySelector('iframe');
        if (f) { f.remove(); im.classList.remove('playing'); }
      });
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
      if (b.dataset.listing) post(cfg.base + '/ajax.php', { action: 'phone', id: b.dataset.listing }).catch(function () {});
    });
  });

  /* share */
  $$('[data-share]').forEach(function (b) {
    on(b, 'click', function () {
      var url = b.dataset.url, title = b.dataset.title;
      if (navigator.share) { navigator.share({ title: title, url: url }).catch(function () {}); return; }
      if (navigator.clipboard) navigator.clipboard.writeText(url).then(function () {
        var old = b.innerHTML; b.textContent = b.dataset.copied || L('Copied!'); setTimeout(function () { b.innerHTML = old; }, 1800);
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
      if (!preview) {
        var label = $('strong', dz);
        if (label && input.files.length) label.textContent = Array.prototype.map.call(input.files, function (f) { return f.name; }).join(', ');
        return;
      }
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
        var photo = t.form.querySelector('[data-chat-photo]');
        if (t.value.trim() || (photo && photo.files.length)) {
          if (t.form.requestSubmit) t.form.requestSubmit(); else t.form.submit();
        }
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

  /* ---------- v1.1 additions ---------- */

  /* generic "please wait" on submit */
  $$('[data-loading]').forEach(function (b) {
    var f = b.form;
    if (!f) return;
    on(f, 'submit', function () { setTimeout(function () { b.disabled = true; b.textContent = b.dataset.loading; }, 0); });
  });
  $$('[data-confirm-click]').forEach(function (b) {
    on(b, 'click', function (e) { if (!confirm(b.dataset.confirmClick)) e.preventDefault(); });
  });

  /* listing form: show the custom fields of the chosen category (and its parent) */
  var cfWrap = $('[data-cf-wrap]'), catSel = $('#lf-cat');
  function syncFields() {
    if (!cfWrap || !catSel) return;
    var opt = catSel.options[catSel.selectedIndex], id = catSel.value, parent = opt ? opt.getAttribute('data-parent') : '';
    $$('[data-cf-cat]', cfWrap).forEach(function (g) {
      var show = id !== '' && (g.dataset.cfCat === id || g.dataset.cfCat === parent);
      g.hidden = !show;
      $$('input, select, textarea', g).forEach(function (i) {
        i.disabled = !show;
        if (i.hasAttribute('data-required')) i.required = show;
      });
    });
  }
  on(catSel, 'change', syncFields);
  syncFields();

  /* search suggestions */
  $$('[data-suggest]').forEach(function (input) {
    var box = document.createElement('div'), timer = null, items = [], active = -1, last = '';
    box.className = 'suggest-box'; box.hidden = true; box.setAttribute('role', 'listbox');
    (input.closest('.suggest-wrap') || input.parentNode).appendChild(box);
    input.setAttribute('aria-autocomplete', 'list');
    function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    function close() { box.hidden = true; active = -1; }
    function highlight(i) {
      items.forEach(function (el, k) { el.classList.toggle('on', k === i); });
      active = i;
    }
    function render(d) {
      var html = '';
      (d.categories || []).forEach(function (c) {
        html += '<a class="sg-item sg-cat" href="' + esc(c.url) + '"><span class="sg-ico">#</span><span><strong>' + esc(c.name) + '</strong>' + (c.parent ? '<small>in ' + esc(c.parent) + '</small>' : '<small>Category</small>') + '</span></a>';
      });
      (d.ads || []).forEach(function (a) {
        html += '<a class="sg-item" href="' + esc(a.url) + '">' + (a.thumb ? '<img src="' + esc(a.thumb) + '" alt="">' : '<span class="sg-ico"></span>') +
          '<span><strong>' + esc(a.title) + '</strong><small>' + esc(a.price) + (a.category ? ' · ' + esc(a.category) : '') + '</small></span></a>';
      });
      html += '<a class="sg-item sg-all" href="' + esc(d.search_url) + '">Search for “' + esc(input.value.trim()) + '”</a>';
      box.innerHTML = html;
      items = $$('.sg-item', box);
      box.hidden = false;
      active = -1;
    }
    on(input, 'input', function () {
      var q = input.value.trim();
      clearTimeout(timer);
      if (q.length < 2) { close(); return; }
      timer = setTimeout(function () {
        if (q === last && !box.hidden) return;
        last = q;
        fetch(cfg.base + '/ajax.php?action=suggest&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (d) { if (input.value.trim() === q) render(d); })
          .catch(close);
      }, 180);
    });
    on(input, 'keydown', function (e) {
      if (box.hidden) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); highlight(Math.min(items.length - 1, active + 1)); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(Math.max(-1, active - 1)); }
      else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); location.href = items[active].href; }
      else if (e.key === 'Escape') close();
    });
    on(input, 'blur', function () { setTimeout(close, 150); });
  });

  /* installable app */
  if (cfg.pwa && 'serviceWorker' in navigator && (location.protocol === 'https:' || /^(localhost|127\.0\.0\.1)$/.test(location.hostname))) {
    window.addEventListener('load', function () { navigator.serviceWorker.register(cfg.base + '/sw.js').catch(function () {}); });
  }
  var installEvt = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault(); installEvt = e;
    $$('[data-install-app]').forEach(function (el) { el.hidden = false; });
  });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-install-btn]');
    if (!b || !installEvt) return;
    e.preventDefault();
    installEvt.prompt();
    installEvt.userChoice.finally(function () { installEvt = null; $$('[data-install-app]').forEach(function (el) { el.hidden = true; }); });
  });

  /* admin: .htaccess repair + check that URL rewriting works before enabling clean URLs */
  function testRewrite(url) {
    return fetch(url + '?t=' + Date.now(), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.text() : ''; })
      .then(function (t) { return t.trim() === 'ok'; })
      .catch(function () { return false; });
  }
  function fixHtaccess(endpoint) {
    var body = new FormData();
    body.append('action', 'htaccess'); body.append('_token', cfg.csrf);
    return fetch(endpoint, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        Object.keys(d.report || {}).forEach(function (file) {
          var li = $('[data-ht-file="' + file + '"]');
          if (!li) return;
          var st = d.report[file], good = st !== 'failed';
          li.querySelector('span').textContent = st === 'ok' ? 'present' : st;
          li.querySelector('svg use').setAttribute('href', good ? '#i-check-circle' : '#i-alert');
          li.querySelector('svg').setAttribute('class', 'ic ' + (good ? 'text-success' : 'text-warning'));
        });
        return d;
      });
  }
  function showHtResult(ok, d, msg) {
    var p = $('[data-ht-result]');
    if (!p) return;
    p.hidden = false;
    p.className = 'ht-result ' + (ok ? 'good' : 'bad');
    p.textContent = msg;
  }
  var failMsg = function (d) {
    if (d && !d.ok) return 'We could not write the .htaccess file (the main folder is not writable). Set the main folder permission to 755 in your File Manager, or create the file by hand — see README.';
    if (d && !d.apache) return 'Your server (' + d.server + ') does not read .htaccess files. On Nginx add the rule from the README, then try again.';
    return 'The .htaccess file is in place, but the server is not applying it yet. Ask your host to allow “AllowOverride All” (or FileInfo) for your site, then try again. On LiteSpeed it can take a minute to notice a new .htaccess.';
  };
  $$('[data-rewrite-test]').forEach(function (cb) {
    on(cb, 'change', function () {
      if (!cb.checked) return;
      var url = cb.dataset.rewriteTest, btn = $('[data-fix-htaccess]');
      testRewrite(url).then(function (ok) {
        if (ok) return;
        // not working yet: create / repair .htaccess, then test again
        return fixHtaccess(btn ? btn.dataset.endpoint : location.pathname).then(function (d) {
          return testRewrite(url).then(function (ok2) {
            if (ok2) { showHtResult(true, d, 'Created the .htaccess file — clean URLs work. Click “Save settings” to switch them on.'); return; }
            cb.checked = false;
            showHtResult(false, d, failMsg(d));
            alert('Clean URLs could not be switched on yet.\n\n' + failMsg(d));
          });
        });
      });
    });
  });
  $$('[data-fix-htaccess]').forEach(function (btn) {
    on(btn, 'click', function () {
      btn.disabled = true;
      fixHtaccess(btn.dataset.endpoint).then(function (d) {
        return testRewrite(btn.dataset.test).then(function (ok) {
          showHtResult(ok, d, ok ? 'All .htaccess files are in place and URL rewriting works. You can switch on Clean URLs.' : failMsg(d));
        });
      }).catch(function () { showHtResult(false, null, 'Something went wrong. Reload the page and try again.'); })
        .then(function () { btn.disabled = false; });
    });
  });

  /* admin: custom field type → show matching options */
  $$('[data-field-type]').forEach(function (sel) {
    function sync() {
      $$('[data-show-for]').forEach(function (el) { el.hidden = el.dataset.showFor.split(' ').indexOf(sel.value) < 0; });
    }
    on(sel, 'change', sync); sync();
  });

  /* admin: email delivery method */
  $$('[data-mail-driver]').forEach(function (r) {
    on(r, 'change', function () { var f = $('[data-smtp-fields]'); if (f) f.hidden = r.value !== 'smtp'; });
  });
  $$('[data-insert]').forEach(function (c) {
    c.style.cursor = 'pointer';
    on(c, 'click', function () {
      var ta = $('#t-body'); if (!ta) return;
      ta.setRangeText(c.dataset.insert, ta.selectionStart, ta.selectionEnd, 'end'); ta.focus();
    });
  });

  /* saved searches: rename inline */
  $$('[data-rename]').forEach(function (i) {
    var start = i.value;
    on(i, 'change', function () { if (i.value.trim() && i.value !== start) i.form.submit(); });
  });

  /* ---------- v1.2 additions ---------- */

  /* video in ads: load the player only when asked (keeps pages fast and private) */
  function playVideo(slide) {
    if (!slide || slide.querySelector('iframe')) return;
    var f = document.createElement('iframe');
    f.src = slide.dataset.embed + (slide.dataset.embed.indexOf('?') < 0 ? '?' : '&') + 'autoplay=1';
    f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
    f.allowFullscreen = true; f.title = L('Play video'); f.referrerPolicy = 'strict-origin-when-cross-origin';
    slide.appendChild(f);
    slide.classList.add('playing');
  }
  $$('[data-play-video]').forEach(function (b) { on(b, 'click', function () { playVideo(b.closest('.g-video')); }); });
  $$('.thumb-video').forEach(function (b) {
    on(b, 'click', function () { setTimeout(function () { playVideo($('.g-video', b.closest('[data-gallery]'))); }, 0); });
  });

  /* make an offer: quick percentage buttons */
  $$('[data-offer-quick]').forEach(function (box) {
    var price = parseFloat(box.dataset.price) || 0, input = box.closest('form').querySelector('[name=amount]');
    if (!price) { box.hidden = true; return; }
    $$('[data-pct]', box).forEach(function (b) {
      on(b, 'click', function () {
        var v = price * (+b.dataset.pct) / 100;
        input.value = v >= 100 ? Math.round(v) : v.toFixed(2).replace(/\.00$/, '');
        $$('[data-pct]', box).forEach(function (x) { x.classList.toggle('on', x === b); });
        input.focus();
      });
    });
  });

  /* chat: quick replies */
  $$('[data-quick-replies]').forEach(function (box) {
    var ta = box.parentNode.querySelector('[data-chat-form] textarea');
    $$('.pill', box).forEach(function (p) {
      on(p, 'click', function () {
        if (!ta) return;
        ta.value = ta.value.trim() ? ta.value.replace(/\s*$/, ' ') + p.textContent : p.textContent;
        ta.dispatchEvent(new Event('input'));
        ta.focus();
      });
    });
  });

  /* chat: photo attachment preview */
  $$('[data-chat-photo]').forEach(function (input) {
    var form = input.form, prev = $('[data-attach-preview]', form);
    function clear() { input.value = ''; prev.innerHTML = ''; prev.hidden = true; }
    on(input, 'change', function () {
      var f = input.files[0];
      prev.innerHTML = '';
      if (!f) { prev.hidden = true; return; }
      if (!/^image\/(jpeg|png|webp|gif)$/.test(f.type)) { alert(L('Please choose a photo (JPG, PNG, WEBP or GIF).')); clear(); return; }
      if (f.size > 12 * 1024 * 1024) { alert(L('That photo is too large.')); clear(); return; }
      var img = document.createElement('img'), x = document.createElement('button');
      img.src = URL.createObjectURL(f); img.alt = '';
      x.type = 'button'; x.className = 'attach-remove'; x.setAttribute('aria-label', L('Remove photo')); x.innerHTML = '&times;';
      on(x, 'click', clear);
      prev.appendChild(img); prev.appendChild(x); prev.hidden = false;
    });
    form.addEventListener('bzr:sent', clear);
  });

  /* chat: send without reloading, then poll for new messages, "seen" ticks and offer changes */
  $$('[data-thread]').forEach(function (thread) {
    var conv = thread.dataset.thread, endpoint = thread.dataset.endpoint || (cfg.base + '/ajax.php');
    var last = +thread.dataset.last || 0, day = thread.dataset.day || '', busy = false, timer = null, form = $('[data-chat-form]');
    var anchor = $('#bottom', thread);
    function nearBottom() { return thread.scrollHeight - thread.scrollTop - thread.clientHeight < 120; }
    function setSeen(ids) {
      ids.forEach(function (id) {
        var t = $('.bubble.mine[data-msg="' + id + '"] .tick', thread);
        if (t && !t.classList.contains('seen')) {
          t.classList.add('seen'); t.title = L('Seen');
          var u = t.querySelector('use'); if (u) u.setAttribute('href', '#i-check-check');
        }
      });
    }
    function setBadges(n) {
      $$('.icon-btn .dot-badge, .dash-nav .badge').forEach(function (b) { if (n) b.textContent = n > 9 && b.classList.contains('dot-badge') ? '9+' : n; else b.remove(); });
    }
    function poll() {
      if (busy || document.hidden) return Promise.resolve();
      busy = true;
      return fetch(endpoint + '?action=thread&c=' + conv + '&after=' + last + '&day=' + encodeURIComponent(day), { credentials: 'same-origin', cache: 'no-store' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d || !d.ok) return;
          if (d.offer_changed) { location.reload(); return; }
          if (d.html) {
            var stick = nearBottom(), tmp = document.createElement('div');
            tmp.innerHTML = d.html;
            while (tmp.firstChild) thread.insertBefore(tmp.firstChild, anchor);
            if (stick) thread.scrollTop = thread.scrollHeight;
          }
          last = d.last; day = d.day;
          setSeen(d.seen || []);
          setBadges(d.unread);
        })
        .catch(function () {})
        .then(function () { busy = false; });
    }
    function schedule() { clearTimeout(timer); timer = setTimeout(function () { poll().then(schedule); }, document.hidden ? 30000 : 6000); }
    schedule();
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll().then(schedule); });

    if (form && window.fetch && window.FormData) {
      on(form, 'submit', function (e) {
        var ta = form.querySelector('textarea'), photo = form.querySelector('[data-chat-photo]'), btn = form.querySelector('[type=submit]');
        if (!ta.value.trim() && !(photo && photo.files.length)) { e.preventDefault(); ta.focus(); return; }
        e.preventDefault();
        btn.disabled = true;
        post(form.action || location.href, new FormData(form))
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (!d.ok) { alert(d.error || L('Something went wrong. Please try again.')); return; }
            ta.value = ''; ta.style.height = '';
            form.dispatchEvent(new Event('bzr:sent'));
            return poll().then(function () { thread.scrollTop = thread.scrollHeight; });
          })
          .catch(function () { form.submit(); })
          .then(function () { btn.disabled = false; ta.focus(); });
      });
    }
  });

  /* copy-to-clipboard buttons */
  $$('[data-copy-text]').forEach(function (b) {
    on(b, 'click', function () {
      var done = function () { var old = b.innerHTML; b.textContent = L('Copied!'); setTimeout(function () { b.innerHTML = old; }, 1600); };
      if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.copyText).then(done);
      else { var ta = document.createElement('textarea'); ta.value = b.dataset.copyText; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); done(); }
    });
  });

  /* two-factor setup: draw the QR code locally (the secret never leaves the page) */
  $$('[data-qr]').forEach(function (box) {
    if (!window.qrcode) return;
    var qr = window.qrcode(0, 'M');
    qr.addData(box.dataset.qr);
    qr.make();
    box.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 4, scalable: true });
  });

  /* shop opening hours */
  $$('[data-hours-row]').forEach(function (row) {
    var cb = $('[data-closed-toggle]', row);
    on(cb, 'change', function () { $$('input[type=time]', row).forEach(function (i) { i.disabled = cb.checked; }); });
  });
  $$('[data-copy-hours]').forEach(function (b) {
    on(b, 'click', function () {
      var rows = $$('[data-hours-row]'), src = rows[0];
      if (!src) return;
      var t = $$('input[type=time]', src), closed = $('[data-closed-toggle]', src).checked;
      rows.slice(1, 5).forEach(function (r) {
        var ti = $$('input[type=time]', r);
        ti[0].value = t[0].value; ti[1].value = t[1].value;
        var cb = $('[data-closed-toggle]', r); cb.checked = closed; cb.dispatchEvent(new Event('change'));
      });
    });
  });

  /* admin newsletter: send in small batches while the page is open */
  $$('[data-newsletter-progress]').forEach(function (box) {
    var total = +box.dataset.total || 0, bar = $('[data-progress-bar]', box), done = $('[data-progress-done]', box),
      failed = $('[data-progress-failed]', box), status = $('[data-progress-status]', box), errors = 0;
    function step() {
      post(box.dataset.endpoint, { action: 'process', id: box.dataset.id })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          errors = 0;
          done.textContent = d.done; failed.textContent = d.failed;
          bar.style.width = (total ? Math.min(100, Math.round((d.done + d.failed) / total * 100)) : 100) + '%';
          if (d.left > 0) { setTimeout(step, d.sent_now ? 400 : 3000); return; }
          status.textContent = d.failed ? 'Finished. Some emails could not be delivered — check your email settings.' : 'All done! 🎉';
          box.classList.add('finished');
        })
        .catch(function () {
          if (++errors < 5) { setTimeout(step, 5000); return; }
          status.textContent = 'Paused because of a connection problem. Reload the page to continue (the rest is also sent in the background).';
        });
    }
    if (!box.classList.contains('finished')) step();
  });

  /* stats chart: keyboard / touch tooltips are handled with CSS (:hover, :focus); tap shows the value on phones */
  $$('.sc-col').forEach(function (c) { on(c, 'click', function () { c.focus(); }); });
})();
