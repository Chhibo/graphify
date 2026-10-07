/* WanderLuxe Travel CMS - front-end interactions */
(function () {
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
            if (e.target === modal) closeModal(modal.id);
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('[data-modal]').forEach(function (m) { closeModal(m.id); });
        }
    });

    // Live price estimate in the booking form
    var select = document.getElementById('bookItemSelect');
    var guests = document.getElementById('bookGuests');
    var estimate = document.getElementById('bookEstimate');
    function updateEstimate() {
        if (!select || !guests || !estimate) return;
        var opt = select.options[select.selectedIndex];
        if (!opt) return;
        var max = parseInt(opt.getAttribute('data-max') || '50', 10);
        guests.max = max;
        var n = Math.max(1, Math.min(max, parseInt(guests.value || '1', 10)));
        if (opt.getAttribute('data-price') === '') {
            estimate.textContent = estimate.getAttribute('data-hidden-text');
            return;
        }
        var total = parseFloat(opt.getAttribute('data-price') || '0') * n;
        var formatted = total.toLocaleString(undefined, { maximumFractionDigits: 2 });
        var sym = estimate.getAttribute('data-symbol');
        estimate.textContent = estimate.getAttribute('data-position') === 'after' ? formatted + ' ' + sym : sym + formatted;
    }
    if (select) {
        select.addEventListener('change', updateEstimate);
        guests.addEventListener('input', updateEstimate);
        updateEstimate();
    }

    // Auto-hide flash toasts
    document.querySelectorAll('.flash-toast').forEach(function (t) {
        setTimeout(function () { t.style.transition = 'opacity .4s'; t.style.opacity = '0'; }, 4500);
        setTimeout(function () { t.remove(); }, 5000);
    });

    // Category filter tabs on the home page (client-side, like the original design)
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

function openBookingModal(tourId) {
    var mobile = document.getElementById('mobileMenu');
    if (mobile) mobile.classList.add('hidden');
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
