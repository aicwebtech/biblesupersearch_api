enyo.depends(
    '/js/bin/ckeditor/ckeditor.js',
    '/js/bin/custom/form',
    '/js/bin/custom/dialog',
    'source',
    'assets/style.css',
    'assets/dialogs.css'
);

$( function() {
    var App = new BibleManager.Application();
});
