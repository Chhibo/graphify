<?php
/**
 * Visual text editor (Jodit, MIT licence, bundled in assets/vendor/jodit - no internet needed).
 * Add data-editor="full" | "basic" | "mini" to a <textarea> and include this file once at the end of the page.
 */
?>
<link rel="stylesheet" href="<?= asset('vendor/jodit/jodit.min.css') ?>">
<script src="<?= asset('vendor/jodit/jodit.min.js') ?>"></script>
<script>
(function () {
  if (!window.Jodit) return;
  var uploadUrl = <?= json_encode(url('admin/upload.php')) ?>;
  var csrf = <?= json_encode(csrf_token()) ?>;
  var toolbars = {
    mini: ['bold', 'italic', 'underline', '|', 'brush', 'fontsize', '|', 'ul', 'ol', '|', 'link', '|', 'eraser'],
    basic: ['paragraph', 'fontsize', '|', 'bold', 'italic', 'underline', 'strikethrough', '|', 'brush', '|', 'ul', 'ol', '|', 'align', 'indent', 'outdent', '|', 'image', 'link', 'table', 'hr', '|', 'eraser', 'undo', 'redo', 'source', 'fullsize'],
    full: ['paragraph', 'font', 'fontsize', 'lineHeight', '|', 'bold', 'italic', 'underline', 'strikethrough', 'superscript', 'subscript', '|', 'brush', '|',
           'ul', 'ol', '|', 'align', 'indent', 'outdent', '|', 'image', 'video', 'link', 'table', 'hr', 'symbols', '|',
           'cut', 'copy', 'paste', 'selectall', 'eraser', 'copyformat', '|', 'undo', 'redo', 'find', '|', 'source', 'preview', 'print', 'fullsize']
  };
  document.querySelectorAll('textarea[data-editor]').forEach(function (ta) {
    var type = ta.getAttribute('data-editor');
    var buttons = toolbars[type] || toolbars.basic;
    Jodit.make(ta, {
      height: type === 'mini' ? 200 : (type === 'full' ? 520 : 380),
      toolbarAdaptive: false,
      buttons: buttons, buttonsMD: buttons, buttonsSM: buttons, buttonsXS: buttons,
      showCharsCounter: false, showWordsCounter: false, showXPathInStatusbar: false,
      askBeforePasteHTML: false, askBeforePasteFromWord: false, defaultActionOnPaste: 'insert_clear_html',
      uploader: {
        url: uploadUrl,
        insertImageAsBase64URI: false,
        data: { _csrf: csrf },
        isSuccess: function (resp) { return resp && resp.success; },
        getMessage: function (resp) { return (resp && resp.message) || 'Upload failed'; },
        process: function (resp) {
          var files = resp.files || [];
          return { files: files, isImages: files.map(function () { return true; }), path: '', baseurl: '', error: 0, msg: '' };
        }
      }
    });
  });
})();
</script>
