// Offer locker: loads offers, then polls until the postback unlocks the download.
(function () {
  var btn = document.getElementById('unlock-btn');
  if (!btn) return;

  var base = window.STORE_BASE || '';
  var locker = document.getElementById('locker');
  var offersBox = document.getElementById('locker-offers');
  var statusBox = document.getElementById('locker-status');
  var simulateBtn = document.getElementById('simulate-btn');
  var productId = btn.getAttribute('data-product');
  var token = null;
  var pollTimer = null;

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text) n.textContent = text;
    return n;
  }

  function showDownload(url) {
    stopPolling();
    statusBox.hidden = true;
    offersBox.innerHTML = '';
    var done = el('div', 'unlocked');
    done.appendChild(el('p', 'unlocked-title', 'Unlocked! Your download is ready.'));
    var a = el('a', 'btn btn-big', 'Download now');
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
      if (o.image && /^https?:\/\//i.test(o.image)) {
        var img = el('img');
        img.src = o.image;
        img.alt = '';
        img.loading = 'lazy';
        a.appendChild(img);
      }
      var text = el('div', 'offer-text');
      text.appendChild(el('strong', '', o.title || 'Complete this offer'));
      if (o.description) text.appendChild(el('span', '', o.description));
      a.appendChild(text);
      a.appendChild(el('span', 'offer-go', 'Start'));
      a.addEventListener('click', startPolling);
      offersBox.appendChild(a);
    });
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

  function open() {
    locker.hidden = false;
    document.body.classList.add('no-scroll');
    offersBox.innerHTML = '<p class="loading">Loading offers…</p>';
    fetch(base + 'api/offers.php?product=' + encodeURIComponent(productId), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        token = d.token || null;
        if (d.completed) return showDownload(d.download);
        if (!d.offers || !d.offers.length) {
          offersBox.innerHTML = '';
          offersBox.appendChild(el('p', 'locker-error', d.error || 'No offers available right now.'));
          return;
        }
        renderOffers(d.offers);
        // Visitors often come back after finishing an offer in another tab.
        startPolling();
      })
      .catch(function () {
        offersBox.innerHTML = '';
        offersBox.appendChild(el('p', 'locker-error', 'Could not load offers. Check your connection and try again.'));
      });
  }

  function close() {
    locker.hidden = true;
    document.body.classList.remove('no-scroll');
    stopPolling();
  }

  btn.addEventListener('click', open);
  document.getElementById('locker-close').addEventListener('click', close);
  locker.addEventListener('click', function (e) { if (e.target === locker) close(); });
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
