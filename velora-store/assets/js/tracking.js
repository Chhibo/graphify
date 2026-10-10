/* Velora Store - ads & analytics pixels (Meta, TikTok, Google Analytics 4) with cookie consent */
(function () {
  'use strict';
  var cfg = window.SHOP_TRACK_CFG || {};
  var queue = [], loaded = false;

  var consent = function () {
    try { return localStorage.getItem('cookie_consent'); } catch (e) { return null; }
  };

  function loadPixels() {
    if (loaded) return;
    loaded = true;
    if (cfg.fb) {
      /* Meta Pixel base code */
      !function (f, b, e, v, n, t, s) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
        if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0;
        t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s); }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
      window.fbq('init', cfg.fb);
      window.fbq('track', 'PageView');
    }
    if (cfg.tt) {
      /* TikTok Pixel base code */
      !function (w, d, t) { w.TiktokAnalyticsObject = t; var ttq = w[t] = w[t] || []; ttq.methods = ['page', 'track', 'identify', 'instances', 'debug', 'on', 'off', 'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie'];
        ttq.setAndDefer = function (t, e) { t[e] = function () { t.push([e].concat(Array.prototype.slice.call(arguments, 0))); }; };
        for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
        ttq.instance = function (t) { for (var e = ttq._i[t] || [], n = 0; n < ttq.methods.length; n++) ttq.setAndDefer(e, ttq.methods[n]); return e; };
        ttq.load = function (e, n) { var i = 'https://analytics.tiktok.com/i18n/pixel/events.js'; ttq._i = ttq._i || {}; ttq._i[e] = []; ttq._i[e]._u = i; ttq._t = ttq._t || {}; ttq._t[e] = +new Date(); ttq._o = ttq._o || {}; ttq._o[e] = n || {};
          var o = document.createElement('script'); o.type = 'text/javascript'; o.async = !0; o.src = i + '?sdkid=' + e + '&lib=' + t; var a = document.getElementsByTagName('script')[0]; a.parentNode.insertBefore(o, a); };
      }(window, document, 'ttq');
      window.ttq.load(cfg.tt);
      window.ttq.page();
    }
    if (cfg.ga) {
      var s = document.createElement('script'); s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(cfg.ga);
      document.head.appendChild(s);
      window.dataLayer = window.dataLayer || [];
      window.gtag = function () { window.dataLayer.push(arguments); };
      window.gtag('js', new Date());
      window.gtag('config', cfg.ga);
    }
    queue.forEach(fire);
    queue = [];
  }

  // Send one shop event to each pixel, using each platform's standard event names.
  function fire(ev) {
    var d = ev.data || {}, items = d.items || [];
    var ids = items.map(function (i) { return i.id; });
    var count = items.reduce(function (n, i) { return n + (i.qty || 1); }, 0);
    var names = {
      ViewContent: ['ViewContent', 'ViewContent', 'view_item'],
      AddToCart: ['AddToCart', 'AddToCart', 'add_to_cart'],
      InitiateCheckout: ['InitiateCheckout', 'InitiateCheckout', 'begin_checkout'],
      Purchase: ['Purchase', 'CompletePayment', 'purchase']
    }[ev.name];
    if (!names) return;
    if (cfg.fb && window.fbq) {
      window.fbq('track', names[0], { value: d.value, currency: d.currency, content_ids: ids, content_type: 'product', num_items: count,
        contents: items.map(function (i) { return { id: i.id, quantity: i.qty }; }) }, d.order_id ? { eventID: d.order_id } : {});
    }
    if (cfg.tt && window.ttq) {
      window.ttq.track(names[1], { value: d.value, currency: d.currency, content_type: 'product',
        contents: items.map(function (i) { return { content_id: i.id, content_name: i.name, price: i.price, quantity: i.qty }; }) });
    }
    if (cfg.ga && window.gtag) {
      var p = { currency: d.currency, value: d.value, items: items.map(function (i) { return { item_id: i.id, item_name: i.name, price: i.price, quantity: i.qty }; }) };
      if (d.order_id) p.transaction_id = d.order_id;
      if (d.shipping !== undefined) p.shipping = d.shipping;
      window.gtag('event', names[2], p);
    }
  }

  window.SHOP_TRACK = {
    push: function (ev) { if (loaded) fire(ev); else queue.push(ev); },
    accept: function () { try { localStorage.setItem('cookie_consent', 'yes'); } catch (e) {} loadPixels(); },
    decline: function () { try { localStorage.setItem('cookie_consent', 'no'); } catch (e) {} queue = []; }
  };

  if (!cfg.consent || consent() === 'yes') loadPixels();
})();
