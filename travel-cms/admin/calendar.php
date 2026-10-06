<?php
require __DIR__ . '/includes/auth.php';

$month = preg_match('/^\d{4}-\d{2}$/', $_GET['m'] ?? '') ? $_GET['m'] : date('Y-m');
$first = strtotime($month . '-01');
$daysInMonth = (int) date('t', $first);
$startWeekday = (int) date('N', $first); // 1 = Monday
$last = date('Y-m-d', strtotime($month . '-' . $daysInMonth));

$byDay = [];
foreach (db_all('SELECT id, reference, customer_name, tour_title, guests, status, travel_date FROM ' . tbl('bookings') . " WHERE travel_date BETWEEN ? AND ? AND status <> 'cancelled' ORDER BY travel_date, id", [date('Y-m-d', $first), $last]) as $r) {
    $byDay[$r['travel_date']][] = $r;
}
$colors = ['pending' => 'bg-amber-100 text-amber-800', 'confirmed' => 'bg-emerald-100 text-emerald-800', 'completed' => 'bg-sky-100 text-sky-800'];

admin_header('Booking calendar', 'calendar');
?>
<div class="flex items-center justify-between mb-4">
    <a href="?m=<?= date('Y-m', strtotime('-1 month', $first)) ?>" class="btn btn-light"><i class="fa-solid fa-chevron-left"></i> <?= date('M', strtotime('-1 month', $first)) ?></a>
    <h2 class="font-serif text-2xl font-bold"><?= date('F Y', $first) ?></h2>
    <a href="?m=<?= date('Y-m', strtotime('+1 month', $first)) ?>" class="btn btn-light"><?= date('M', strtotime('+1 month', $first)) ?> <i class="fa-solid fa-chevron-right"></i></a>
</div>
<div class="card overflow-x-auto">
    <div class="grid grid-cols-7 min-w-[760px] text-xs">
        <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?>
            <div class="p-2 text-center font-bold uppercase text-slate-500 bg-slate-50 border-b border-slate-100"><?= $d ?></div>
        <?php endforeach; ?>
        <?php for ($i = 1; $i < $startWeekday; $i++): ?><div class="border-b border-r border-slate-100 bg-slate-50/50"></div><?php endfor; ?>
        <?php for ($day = 1; $day <= $daysInMonth; $day++):
            $date = sprintf('%s-%02d', $month, $day);
            $items = $byDay[$date] ?? [];
            $guests = array_sum(array_column($items, 'guests')); ?>
            <div class="min-h-[110px] p-1.5 border-b border-r border-slate-100 <?= $date === date('Y-m-d') ? 'bg-brand-50' : '' ?>">
                <div class="flex justify-between items-center mb-1">
                    <span class="font-bold <?= $date === date('Y-m-d') ? 'text-brand-700' : 'text-slate-600' ?>"><?= $day ?></span>
                    <?php if ($guests): ?><span class="text-[10px] text-slate-400"><i class="fa-solid fa-user"></i> <?= $guests ?></span><?php endif; ?>
                </div>
                <?php foreach (array_slice($items, 0, 4) as $it): ?>
                    <a href="<?= e(url('admin/booking.php?id=' . $it['id'])) ?>" title="<?= e($it['tour_title']) ?>" class="block truncate rounded px-1.5 py-0.5 mb-0.5 <?= $colors[$it['status']] ?? 'bg-slate-100' ?>"><?= e($it['customer_name']) ?> (<?= (int) $it['guests'] ?>)</a>
                <?php endforeach; ?>
                <?php if (count($items) > 4): ?>
                    <a href="<?= e(url('admin/bookings.php?from=' . $date . '&to=' . $date)) ?>" class="text-[10px] font-bold text-brand-600">+<?= count($items) - 4 ?> more</a>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>
<p class="text-xs text-slate-400 mt-3"><span class="inline-block w-3 h-3 rounded bg-amber-100 align-middle"></span> Pending &nbsp; <span class="inline-block w-3 h-3 rounded bg-emerald-100 align-middle"></span> Confirmed &nbsp; <span class="inline-block w-3 h-3 rounded bg-sky-100 align-middle"></span> Completed</p>
<?php admin_footer(); ?>
