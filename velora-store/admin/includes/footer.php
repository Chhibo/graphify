    </div>
  </div>
</div>
<script>
document.querySelectorAll('[data-confirm]').forEach(function (el) {
  el.addEventListener('submit', function (e) { if (!confirm(el.getAttribute('data-confirm'))) e.preventDefault(); });
});
document.querySelectorAll('[data-tab]').forEach(function (t) {
  t.addEventListener('click', function () {
    var id = t.getAttribute('data-tab');
    document.querySelectorAll('[data-tab]').forEach(function (x) { x.classList.toggle('active', x === t); });
    document.querySelectorAll('.tab-panel').forEach(function (p) { p.hidden = p.id !== id; });
    var f = document.querySelector('input[name=tab]'); if (f) f.value = id;
    history.replaceState(null, '', '?tab=' + id);
  });
});
</script>
</body>
</html>
