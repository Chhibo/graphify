<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => mb_substr(post('name'), 0, 150),
        'email' => mb_substr(post('email'), 0, 190),
        'phone' => mb_substr(post('phone'), 0, 60),
        'subject' => mb_substr(post('subject'), 0, 200),
        'message' => mb_substr(post('message'), 0, 5000),
    ];
    $error = null;
    if (!verify_csrf()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($err = spam_check('contact', 20)) {
        $error = $err;
    } elseif ($data['name'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($data['message']) < 5) {
        $error = 'Please enter your name, a valid email and a message.';
    }
    if ($error) {
        remember_input($data);
        flash('error', $error);
        redirect('contact.php');
    }
    db_insert('messages', $data + ['is_read' => 0, 'ip_address' => client_ip(), 'created_at' => now()]);
    $adminEmail = setting('admin_notify_email', setting('contact_email'));
    if ($adminEmail) {
        send_mail($adminEmail, 'New contact message: ' . ($data['subject'] ?: $data['name']),
            '<p><b>' . e($data['name']) . '</b> (' . e($data['email']) . ', ' . e($data['phone']) . ') wrote:</p><p>' . nl2br(e($data['message'])) . '</p>');
    }
    flash('success', 'Thanks! Your message has been sent. We will get back to you shortly.');
    redirect('contact.php');
}

$pageTitle = 'Contact Us';
$activeNav = 'contact';
require APP_ROOT . '/includes/header.php';
page_banner('Contact Our Travel Desk', 'Questions about a trip or a custom itinerary? We are here 24/7.', 'Get In Touch');
?>
<section class="py-14 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-5 gap-10">
    <div class="lg:col-span-2 space-y-4">
        <?php
        $items = [
            ['fa-solid fa-envelope', 'Email', setting('contact_email'), 'mailto:' . setting('contact_email')],
            ['fa-solid fa-phone', 'Phone', setting('contact_phone'), 'tel:' . preg_replace('/[^0-9+]/', '', setting('contact_phone'))],
            ['fa-brands fa-whatsapp', 'WhatsApp', setting('contact_whatsapp'), 'https://wa.me/' . preg_replace('/\D/', '', setting('contact_whatsapp'))],
            ['fa-solid fa-location-dot', 'Office', setting('contact_address'), ''],
        ];
        foreach ($items as [$icon, $label, $value, $href]): if (!$value) continue; ?>
            <div class="flex items-start space-x-4 bg-white rounded-2xl p-5 shadow border border-slate-100">
                <div class="w-12 h-12 rounded-xl bg-brand-100 text-brand-600 flex items-center justify-center text-xl shrink-0"><i class="<?= $icon ?>"></i></div>
                <div>
                    <span class="text-xs uppercase tracking-wider text-slate-400 font-bold"><?= e($label) ?></span>
                    <?php if ($href): ?><a href="<?= e($href) ?>" class="block font-semibold text-slate-800 hover:text-brand-600"><?= e($value) ?></a>
                    <?php else: ?><p class="font-semibold text-slate-800"><?= e($value) ?></p><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (setting('map_embed_url')): ?>
            <iframe src="<?= e(setting('map_embed_url')) ?>" class="w-full h-64 rounded-2xl border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        <?php endif; ?>
    </div>
    <form method="post" class="lg:col-span-3 bg-white rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-100 space-y-4">
        <?= csrf_field() ?>
        <input type="text" name="website" value="" class="hidden" tabindex="-1" autocomplete="off">
        <h2 class="font-serif text-2xl font-bold text-slate-900">Send us a message</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Full Name</label>
                <input type="text" name="name" required value="<?= e(old('name')) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Email</label>
                <input type="email" name="email" required value="<?= e(old('email')) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Phone (optional)</label>
                <input type="tel" name="phone" value="<?= e(old('phone')) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Subject</label>
                <input type="text" name="subject" value="<?= e(old('subject')) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
            </div>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Message</label>
            <textarea name="message" rows="6" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none"><?= e(old('message')) ?></textarea>
        </div>
        <button class="w-full sm:w-auto px-8 bg-accent-500 hover:bg-accent-600 text-white font-bold py-3 rounded-xl shadow-lg shadow-accent-500/30 text-sm"><i class="fa-solid fa-paper-plane mr-1"></i> Send Message</button>
    </form>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
