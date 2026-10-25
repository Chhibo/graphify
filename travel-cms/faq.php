<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/cards.php';

$faqs = faqs_for(null);
$groups = [];
foreach ($faqs as $f) {
    $groups[$f['category'] !== '' ? $f['category'] : __('General')][] = $f;
}

$pageTitle = setting('faq_title', __('Frequently Asked Questions'));
$metaDescription = setting('faq_subtitle', __('Answers about booking, payment on arrival, cancellations and travelling with us.'));
$canonical = url('faq.php');
$jsonLd = $faqs ? [faq_json_ld($faqs)] : [];
require APP_ROOT . '/includes/header.php';
page_banner($pageTitle, $metaDescription, __('Help Center'));
?>
<section class="py-14 max-w-3xl mx-auto px-4">
    <?php if ($faqs): ?>
        <div class="relative mb-8 -mt-24 z-10">
            <i class="fa-solid fa-magnifying-glass absolute top-1/2 -translate-y-1/2 start-5 text-slate-400"></i>
            <input id="faqSearch" type="search" placeholder="<?= e(__('Search the questions...')) ?>" class="w-full bg-white rounded-2xl shadow-xl border border-slate-100 ps-12 pe-4 py-4 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <?php if (count($groups) > 1): ?>
            <div class="flex flex-wrap gap-2 mb-8">
                <?php foreach (array_keys($groups) as $i => $name): ?>
                    <a href="#faq-<?= $i ?>" class="px-4 py-2 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-semibold"><?= e($name) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="space-y-10">
            <?php $i = 0; foreach ($groups as $name => $items): ?>
                <div id="faq-<?= $i++ ?>" data-faq-group class="scroll-mt-28">
                    <?php if (count($groups) > 1): ?><h2 class="font-serif text-2xl font-bold text-slate-900 mb-4"><?= e($name) ?></h2><?php endif; ?>
                    <?php faq_list($items); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p id="faqEmpty" class="hidden text-center text-slate-500 py-10"><?= e(__('No question matches your search.')) ?></p>
    <?php else: ?>
        <p class="text-center text-slate-500 py-10"><?= e(__('No questions have been added yet.')) ?></p>
    <?php endif; ?>

    <div class="mt-12 p-6 rounded-3xl bg-gradient-to-r from-brand-700 to-brand-500 text-white flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
            <h3 class="font-serif text-xl font-bold"><?= e(__('Still have a question?')) ?></h3>
            <p class="text-sm text-brand-100"><?= e(__('Our travel desk answers within a few hours.')) ?></p>
        </div>
        <div class="flex gap-2 shrink-0">
            <a href="<?= e(url('contact.php')) ?>" class="px-5 py-3 bg-white text-brand-700 rounded-xl font-bold text-sm"><?= e(__('Contact us')) ?></a>
            <?php if (setting('contact_whatsapp')): ?>
                <a href="https://wa.me/<?= e(preg_replace('/\D/', '', setting('contact_whatsapp'))) ?>" target="_blank" rel="noopener" class="px-5 py-3 bg-emerald-500 hover:bg-emerald-600 rounded-xl font-bold text-sm"><i class="fa-brands fa-whatsapp me-1"></i> WhatsApp</a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
