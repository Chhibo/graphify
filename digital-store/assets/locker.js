// Offer locker: loads offers, then polls until the postback unlocks the download.
(function () {
  // "Copy link" share button.
  var copy = document.querySelector('.copy-link');
  if (copy) {
    copy.addEventListener('click', function () {
      var label = copy.querySelector('span');
      var done = function () { label.textContent = 'Copied!'; setTimeout(function () { label.textContent = 'Copy link'; }, 2000); };
      if (navigator.clipboard) navigator.clipboard.writeText(copy.dataset.url).then(done);
      else { window.prompt('Copy this link:', copy.dataset.url); }
    });
  }

  // Picture gallery thumbnails.
  var main = document.getElementById('main-img');
  document.querySelectorAll('.thumbs button').forEach(function (t) {
    t.addEventListener('click', function () {
      if (main) main.src = t.dataset.src;
      document.querySelectorAll('.thumbs button').forEach(function (o) { o.classList.toggle('active', o === t); });
    });
  });

  // Like button.
  var like = document.getElementById('like-btn');
  if (like) {
    like.addEventListener('click', function () {
      var body = new FormData();
      body.append('id', like.dataset.id);
      body.append('csrf', like.dataset.csrf);
      fetch((window.STORE_BASE || '') + 'api/like.php', { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok) return;
          like.classList.toggle('liked', d.liked);
          like.setAttribute('aria-pressed', d.liked ? 'true' : 'false');
          like.querySelector('span').textContent = d.likes;
        });
    });
  }

  var btn = document.getElementById('unlock-btn');
  if (!btn) return;

  var base = window.STORE_BASE || '';
  var locker = document.getElementById('locker');
  var offersBox = document.getElementById('locker-offers');
  var statusBox = document.getElementById('locker-status');
  var steps = document.querySelectorAll('#locker-steps li');
  var simulateBtn = document.getElementById('simulate-btn');
  // Either a product or a locked link.
  var lockQuery = btn.getAttribute('data-link') ? 'link=' + encodeURIComponent(btn.getAttribute('data-link'))
                                             : 'product=' + encodeURIComponent(btn.getAttribute('data-product'));
  var token = null;
  var pollTimer = null;
  var skeleton = offersBox.innerHTML;

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text) n.textContent = text;
    return n;
  }

  function setStep(n) {
    steps.forEach(function (s) {
      var i = +s.getAttribute('data-step');
      s.classList.toggle('active', i === n);
      s.classList.toggle('done', i < n);
    });
  }

  function showDownload(url) {
    stopPolling();
    setStep(3);
    statusBox.hidden = true;
    offersBox.innerHTML = '';
    var done = el('div', 'unlocked');
    done.innerHTML = '<svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>';
    done.appendChild(el('p', 'unlocked-title', 'Unlocked! Your download is ready.'));
    var a = el('a', 'btn btn-big btn-glow', 'Download now');
    a.href = url;
    done.appendChild(a);
    offersBox.appendChild(done);
    if (simulateBtn) simulateBtn.parentNode.hidden = true;
  }

  function renderOffers(offers) {
    offersBox.innerHTML = '';
    offers.forEach(function (o) {
      var a = el('a', 'offer');
      a.href = o.url;
      a.target = '_blank';
      a.rel = 'noopener';
      var pic = el('span', 'offer-pic');
      if (o.image && /^https?:\/\//i.test(o.image)) {
        var img = el('img');
        img.src = o.image;
        img.alt = '';
        img.loading = 'lazy';
        img.onerror = function () { img.remove(); };
        pic.appendChild(img);
      }
      a.appendChild(pic);
      var text = el('div', 'offer-text');
      text.appendChild(el('strong', '', o.title || 'Complete this offer'));
      if (o.description) text.appendChild(el('span', '', o.description));
      a.appendChild(text);
      a.appendChild(el('span', 'offer-go', 'Start'));
      a.addEventListener('click', function () {
        setStep(2);
        startPolling();
        trackClick(o);
      });
      offersBox.appendChild(a);
    });
  }

  // Tell the store which offer was clicked (for the offer reports).
  function trackClick(o) {
    if (!token) return;
    var body = new FormData();
    body.append('token', token);
    body.append('offer_id', o.id || '');
    body.append('offer_name', o.title || '');
    if (navigator.sendBeacon) navigator.sendBeacon(base + 'api/click.php', body);
    else fetch(base + 'api/click.php', { method: 'POST', body: body, credentials: 'same-origin', keepalive: true });
  }

  function checkStatus() {
    if (!token) return;
    fetch(base + 'api/status.php?token=' + encodeURIComponent(token), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.completed) showDownload(d.download); })
      .catch(function () {});
  }

  function startPolling() {
    statusBox.hidden = false;
    if (!pollTimer) pollTimer = setInterval(checkStatus, 5000);
  }

  function stopPolling() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = null;
  }

  function showError(msg) {
    offersBox.innerHTML = '';
    offersBox.appendChild(el('p', 'locker-error', msg));
  }

  function open() {
    locker.hidden = false;
    requestAnimationFrame(function () { locker.classList.add('open'); });
    document.body.classList.add('no-scroll');
    offersBox.innerHTML = skeleton;
    setStep(1);
    fetch(base + 'api/offers.php?' + lockQuery, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        token = d.token || null;
        if (d.completed) return showDownload(d.download);
        if (!d.offers || !d.offers.length) return showError(d.error || 'No offers available right now.');
        renderOffers(d.offers);
        // Visitors often come back after finishing an offer in another tab.
        if (!pollTimer) pollTimer = setInterval(checkStatus, 5000);
      })
      .catch(function () { showError('Could not load offers. Check your connection and try again.'); });
  }

  function close() {
    locker.classList.remove('open');
    document.body.classList.remove('no-scroll');
    stopPolling();
    setTimeout(function () { locker.hidden = true; }, 200);
  }

  btn.addEventListener('click', open);
  document.getElementById('locker-close').addEventListener('click', close);
  locker.addEventListener('click', function (e) { if (e.target === locker) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !locker.hidden) close(); });
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && !locker.hidden) checkStatus();
  });

  if (simulateBtn) {
    simulateBtn.addEventListener('click', function () {
      if (!token) return;
      var body = new FormData();
      body.append('token', token);
      body.append('csrf', simulateBtn.getAttribute('data-csrf'));
      fetch(base + 'api/simulate.php', { method: 'POST', body: body, credentials: 'same-origin' })
        .then(checkStatus);
    });
  }
})();
