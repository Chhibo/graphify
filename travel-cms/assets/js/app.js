/* WanderLuxe Travel CMS - front-end interactions */
function t(key) {
    return (window.TCMS && window.TCMS.i18n && window.TCMS.i18n[key]) || key;
}

(function () {
    /* ---------- Dark / light mode ---------- */
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var dark = !document.documentElement.classList.contains('dark');
            document.documentElement.classList.toggle('dark', dark);
            try { localStorage.setItem('tcms_theme', dark ? 'dark' : 'light'); } catch (e) {}
        });
    });

    // Close the language menu when clicking elsewhere
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.lang-menu[open]').forEach(function (d) {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });
    });

    var header = document.getElementById('mainHeader');
    function onScroll() {
        if (!header) return;
        var scrolled = window.scrollY > 50;
        header.classList.toggle('glass-panel', scrolled);
        header.classList.toggle('shadow-lg', scrolled);
        header.classList.toggle('scrolled', scrolled);
    }
    window.addEventListener('scroll', onScroll);
    onScroll();

    var menuBtn = document.getElementById('mobileMenuBtn');
    if (menuBtn) {
        menuBtn.addEventListener('click', function () {
            document.getElementById('mobileMenu').classList.toggle('hidden');
        });
    }

    // Close modals on backdrop click / Escape
    document.querySelectorAll('[data-modal]').forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) modal.id === 'newsletterPopup' ? closeNewsletterPopup() : closeModal(modal.id);
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('[data-modal]').forEach(function (m) {
                m.id === 'newsletterPopup' ? closeNewsletterPopup() : closeModal(m.id);
            });
        }
        if (document.getElementById('lightbox') && !document.getElementById('lightbox').classList.contains('hidden')) {
            if (e.key === 'ArrowRight') lightboxStep(1);
            if (e.key === 'ArrowLeft') lightboxStep(-1);
        }
    });

    /* ---------- Booking form: guests per category & live estimate ---------- */
    var select = document.getElementById('bookItemSelect');
    var estimate = document.getElementById('bookEstimate');
    var fields = {
        adults: document.getElementById('bookAdults'),
        children: document.getElementById('bookChildren'),
        infants: document.getElementById('bookInfants')
    };
    function fmt(amount) {
        var formatted = amount.toLocaleString(undefined, { maximumFractionDigits: 2 });
        var sym = estimate.getAttribute('data-symbol');
        return estimate.getAttribute('data-position') === 'after' ? formatted + ' ' + sym : sym + formatted;
    }
    function num(el) { return el ? Math.max(0, parseInt(el.value || '0', 10) || 0) : 0; }
    function updateEstimate() {
        if (!select || !estimate || !fields.adults) return;
        var opt = select.options[select.selectedIndex];
        if (!opt) return;
        ['children', 'infants'].forEach(function (key) {
            var allowed = opt.getAttribute('data-' + key) !== '0';
            var wrap = document.querySelector('[data-guest-field="' + key + '"]');
            if (wrap) wrap.classList.toggle('opacity-40', !allowed);
            fields[key].disabled = !allowed;
            if (!allowed) fields[key].value = 0;
        });
        var max = parseInt(opt.getAttribute('data-max') || '50', 10);
        var count = num(fields.adults) + num(fields.children) + num(fields.infants);
        var err = document.getElementById('bookGuestsError');
        if (err) {
            err.textContent = count > max ? t('maxGuests').replace('%d', max) : '';
            err.classList.toggle('hidden', count <= max);
        }
        var hidden = opt.getAttribute('data-price') === '';
        var prices = { adult: parseFloat(opt.getAttribute('data-price') || '0'), child: parseFloat(opt.getAttribute('data-child') || '0'), infant: parseFloat(opt.getAttribute('data-infant') || '0') };
        document.querySelectorAll('[data-price-label]').forEach(function (el) {
            var p = prices[el.getAttribute('data-price-label')];
            el.textContent = hidden ? '' : (p > 0 ? fmt(p) + ' ' + t('each') : t('free'));
        });
        if (hidden) {
            estimate.textContent = estimate.getAttribute('data-hidden-text');
            return;
        }
        estimate.textContent = fmt(num(fields.adults) * prices.adult + num(fields.children) * prices.child + num(fields.infants) * prices.infant);
    }
    if (select && fields.adults) {
        select.addEventListener('change', updateEstimate);
        Object.keys(fields).forEach(function (k) { fields[k].addEventListener('input', updateEstimate); });
        updateEstimate();
        document.getElementById('bookingForm').addEventListener('submit', function (e) {
            var opt = select.options[select.selectedIndex];
            var max = parseInt(opt.getAttribute('data-max') || '50', 10);
            if (num(fields.adults) + num(fields.children) + num(fields.infants) > max) {
                e.preventDefault();
                updateEstimate();
            }
        });
    }

    /* ---------- Flash toasts ---------- */
    document.querySelectorAll('.flash-toast').forEach(function (t) {
        setTimeout(function () { t.style.transition = 'opacity .4s'; t.style.opacity = '0'; }, 4500);
        setTimeout(function () { t.remove(); }, 5000);
    });

    /* ---------- Homepage category tabs ---------- */
    document.querySelectorAll('[data-filter-btn]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var cat = btn.getAttribute('data-filter-btn');
            document.querySelectorAll('[data-filter-btn]').forEach(function (b) {
                var active = b === btn;
                b.classList.toggle('bg-brand-600', active);
                b.classList.toggle('text-white', active);
                b.classList.toggle('shadow-md', active);
                b.classList.toggle('bg-slate-200', !active);
                b.classList.toggle('text-slate-700', !active);
            });
            var shown = 0;
            document.querySelectorAll('[data-category]').forEach(function (card) {
                var match = cat === 'all' || card.getAttribute('data-category') === cat;
                card.classList.toggle('hidden', !match);
                if (match) shown++;
            });
            var empty = document.getElementById('tripsEmpty');
            if (empty) empty.classList.toggle('hidden', shown > 0);
        });
    });

    /* ---------- Wishlist (cookie "tcms_wishlist" = comma separated ids) ---------- */
    function readWishlist() {
        var m = document.cookie.match(/(?:^|;\s*)tcms_wishlist=([^;]*)/);
        return m ? decodeURIComponent(m[1]).split(',').filter(Boolean) : [];
    }
    function writeWishlist(ids) {
        var base = (window.TCMS && window.TCMS.base) || '/';
        document.cookie = 'tcms_wishlist=' + encodeURIComponent(ids.join(',')) + ';path=' + base + ';max-age=31536000;samesite=lax';
        document.querySelectorAll('[data-wishlist-count]').forEach(function (el) {
            el.textContent = ids.length;
            el.classList.toggle('hidden', ids.length === 0);
        });
    }
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-wishlist]');
        if (!btn) return;
        e.preventDefault();
        var id = btn.getAttribute('data-wishlist');
        var ids = readWishlist();
        var on = ids.indexOf(id) === -1;
        ids = on ? ids.concat([id]).slice(-50) : ids.filter(function (x) { return x !== id; });
        writeWishlist(ids);
        document.querySelectorAll('[data-wishlist="' + id + '"]').forEach(function (b) {
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
            b.classList.toggle('text-rose-500', on);
            b.classList.toggle('text-slate-400', !on);
            var label = b.querySelector('[data-wishlist-label]');
            if (label) label.textContent = on ? t('saved') : t('save');
        });
        showToast(on ? t('savedToast') : t('removedToast'));
        if (!on && btn.hasAttribute('data-wishlist-remove-card')) {
            var card = btn.closest('[data-wishlist-card]');
            if (card) card.remove();
            if (!document.querySelector('[data-wishlist-card]')) location.reload();
        }
    });

    /* ---------- Share buttons ---------- */
    document.querySelectorAll('[data-share-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var link = btn.getAttribute('data-share-copy');
            var done = function () { showToast(t('copied')); };
            if (navigator.clipboard) {
                navigator.clipboard.writeText(link).then(done, function () { window.prompt(t('copyPrompt'), link); });
            } else {
                window.prompt(t('copyPrompt'), link);
            }
        });
    });
    document.querySelectorAll('[data-share-native]').forEach(function (btn) {
        if (!navigator.share) { btn.classList.add('hidden'); return; }
        btn.addEventListener('click', function () {
            navigator.share({ title: btn.getAttribute('data-title'), url: btn.getAttribute('data-share-native') }).catch(function () {});
        });
    });

    /* ---------- Newsletter forms (footer + popup) ---------- */
    document.querySelectorAll('form[data-subscribe]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.fetch || !window.FormData) return; // normal POST fallback
            e.preventDefault();
            var msg = form.querySelector('[data-subscribe-msg]');
            var btn = form.querySelector('button');
            btn.disabled = true;
            fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (msg) { msg.textContent = data.message; msg.classList.remove('hidden'); }
                    if (data.ok) {
                        form.querySelectorAll('input[type=email], input[name=name], button').forEach(function (el) { el.classList.add('hidden'); });
                        try { localStorage.setItem('tcms_nl_subscribed', '1'); } catch (err) {}
                        if (form.closest('#newsletterPopup')) setTimeout(closeNewsletterPopup, 2500);
                    }
                })
                .catch(function () { form.submit(); })
                .finally(function () { btn.disabled = false; });
        });
    });

    /* ---------- FAQ search (faq.php) ---------- */
    var faqSearch = document.getElementById('faqSearch');
    if (faqSearch) {
        faqSearch.addEventListener('input', function () {
            var q = faqSearch.value.trim().toLowerCase();
            var shown = 0;
            document.querySelectorAll('[data-faq]').forEach(function (item) {
                var match = !q || item.textContent.toLowerCase().indexOf(q) !== -1;
                item.classList.toggle('hidden', !match);
                if (match) { shown++; if (q) item.setAttribute('open', ''); }
            });
            document.querySelectorAll('[data-faq-group]').forEach(function (g) {
                g.classList.toggle('hidden', !g.querySelector('[data-faq]:not(.hidden)'));
            });
            var empty = document.getElementById('faqEmpty');
            if (empty) empty.classList.toggle('hidden', shown > 0);
        });
    }

    /* ---------- Newsletter popup ---------- */
    var popup = document.getElementById('newsletterPopup');
    if (popup) {
        var hiddenUntil = 0, subscribed = false;
        try {
            hiddenUntil = parseInt(localStorage.getItem('tcms_nl_hidden_until') || '0', 10);
            subscribed = localStorage.getItem('tcms_nl_subscribed') === '1';
        } catch (err) {}
        if (!subscribed && Date.now() > hiddenUntil) {
            setTimeout(function () {
                if (document.querySelector('[data-modal].flex')) return; // don't cover another open dialog
                openModal('newsletterPopup');
            }, Math.max(0, parseInt(popup.getAttribute('data-delay') || '8', 10)) * 1000);
        }
    }
})();

function openModal(id) {
    var m = document.getElementById(id);
    if (!m) return;
    m.classList.remove('hidden');
    m.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    var m = document.getElementById(id);
    if (!m || m.classList.contains('hidden')) return;
    m.classList.add('hidden');
    m.classList.remove('flex');
    document.body.style.overflow = '';
}

function closeNewsletterPopup() {
    var popup = document.getElementById('newsletterPopup');
    if (!popup || popup.classList.contains('hidden')) return;
    var days = parseInt(popup.getAttribute('data-days') || '7', 10);
    try { localStorage.setItem('tcms_nl_hidden_until', String(Date.now() + days * 86400000)); } catch (err) {}
    closeModal('newsletterPopup');
}

function openBookingModal(tourId) {
    var mobile = document.getElementById('mobileMenu');
    if (mobile) mobile.classList.add('hidden');
    closeNewsletterPopup();
    var select = document.getElementById('bookItemSelect');
    if (select && tourId) {
        select.value = String(tourId);
        select.dispatchEvent(new Event('change'));
    }
    openModal('bookingModal');
}

function quickBook(tourId) {
    openBookingModal(tourId);
}

function showToast(text) {
    var t = document.getElementById('toast');
    if (!t) return;
    t.querySelector('[data-toast-text]').textContent = text;
    t.classList.remove('hidden');
    t.classList.add('flex');
    clearTimeout(window.__toastTimer);
    window.__toastTimer = setTimeout(function () { t.classList.add('hidden'); t.classList.remove('flex'); }, 2500);
}

/* ---------- Gallery lightbox (tour pages) ---------- */
var lightboxIndex = 0;
function lightboxImages() {
    return Array.prototype.slice.call(document.querySelectorAll('[data-gallery-item]'));
}
function openLightbox(index) {
    var box = document.getElementById('lightbox');
    if (!box) return;
    lightboxIndex = index;
    lightboxStep(0);
    openModal('lightbox');
}
function lightboxStep(delta) {
    var items = lightboxImages();
    if (!items.length) return;
    lightboxIndex = (lightboxIndex + delta + items.length) % items.length;
    var item = items[lightboxIndex];
    document.getElementById('lightboxImg').src = item.getAttribute('data-full');
    document.getElementById('lightboxCaption').textContent = item.getAttribute('data-caption') || '';
    document.getElementById('lightboxCounter').textContent = (lightboxIndex + 1) + ' / ' + items.length;
}
