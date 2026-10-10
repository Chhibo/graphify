    </div>
  </div>
</div>
<script>
document.querySelectorAll('[data-confirm]').forEach(function (el) {
  el.addEventListener('submit', function (e) { if (!confirm(el.getAttribute('data-confirm'))) e.preventDefault(); });
});
// Repeating rows (custom options, menus...): "Add" copies the last row with empty fields
document.addEventListener('click', function (e) {
  var add = e.target.closest('[data-add-row]');
  if (add) {
    var box = document.querySelector(add.getAttribute('data-add-row'));
    var tpl = box && box.querySelector('.row-item:last-of-type');
    if (!tpl) return;
    var row = tpl.cloneNode(true);
    row.querySelectorAll('input, textarea').forEach(function (i) { if (i.type === 'checkbox') i.checked = false; else i.value = ''; });
    row.querySelectorAll('select').forEach(function (sel) { sel.selectedIndex = 0; });
    row.querySelectorAll('.rows .row-item:not(:first-child)').forEach(function (r) { r.remove(); });
    box.appendChild(row);
    var first = row.querySelector('input'); if (first) first.focus();
  }
  var rm = e.target.closest('[data-remove-row]');
  if (rm) {
    var item = rm.closest('.row-item');
    var siblings = item.parentNode.querySelectorAll(':scope > .row-item');
    if (siblings.length > 1) item.remove();
    else item.querySelectorAll('input, textarea').forEach(function (i) { i.value = ''; });
  }
  var up = e.target.closest('[data-move]');
  if (up) {
    var it = up.closest('.row-item');
    if (up.getAttribute('data-move') === 'up' && it.previousElementSibling) it.parentNode.insertBefore(it, it.previousElementSibling);
    if (up.getAttribute('data-move') === 'down' && it.nextElementSibling) it.parentNode.insertBefore(it.nextElementSibling, it);
  }
});
document.querySelectorAll('[data-toggle-target]').forEach(function (cb) {
  cb.addEventListener('change', function () { var t = document.querySelector(cb.getAttribute('data-toggle-target')); if (t) t.hidden = !cb.checked; });
});
document.querySelectorAll('[data-tab]').forEach(function (t) {
  t.addEventListener('click', function () {
    var id = t.getAttribute('data-tab');
    // Repeating rows (custom options, menus...): "Add" copies the last row with empty fields
document.addEventListener('click', function (e) {
  var add = e.target.closest('[data-add-row]');
  if (add) {
    var box = document.querySelector(add.getAttribute('data-add-row'));
    var tpl = box && box.querySelector('.row-item:last-of-type');
    if (!tpl) return;
    var row = tpl.cloneNode(true);
    row.querySelectorAll('input, textarea').forEach(function (i) { if (i.type === 'checkbox') i.checked = false; else i.value = ''; });
    row.querySelectorAll('select').forEach(function (sel) { sel.selectedIndex = 0; });
    row.querySelectorAll('.rows .row-item:not(:first-child)').forEach(function (r) { r.remove(); });
    box.appendChild(row);
    var first = row.querySelector('input'); if (first) first.focus();
  }
  var rm = e.target.closest('[data-remove-row]');
  if (rm) {
    var item = rm.closest('.row-item');
    var siblings = item.parentNode.querySelectorAll(':scope > .row-item');
    if (siblings.length > 1) item.remove();
    else item.querySelectorAll('input, textarea').forEach(function (i) { i.value = ''; });
  }
  var up = e.target.closest('[data-move]');
  if (up) {
    var it = up.closest('.row-item');
    if (up.getAttribute('data-move') === 'up' && it.previousElementSibling) it.parentNode.insertBefore(it, it.previousElementSibling);
    if (up.getAttribute('data-move') === 'down' && it.nextElementSibling) it.parentNode.insertBefore(it.nextElementSibling, it);
  }
});
document.querySelectorAll('[data-toggle-target]').forEach(function (cb) {
  cb.addEventListener('change', function () { var t = document.querySelector(cb.getAttribute('data-toggle-target')); if (t) t.hidden = !cb.checked; });
});
document.querySelectorAll('[data-tab]').forEach(function (x) { x.classList.toggle('active', x === t); });
    document.querySelectorAll('.tab-panel').forEach(function (p) { p.hidden = p.id !== id; });
    var f = document.querySelector('input[name=tab]'); if (f) f.value = id;
    history.replaceState(null, '', '?tab=' + id);
  });
});
</script>
</body>
</html>
