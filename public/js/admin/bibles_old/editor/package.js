enyo.depends(
    '/js/bin/ckeditor5/build/ckeditor.js',
    '/js/bin/custom/form',
    '/js/bin/custom/dialog',
    '/js/admin/bibles/assets/style.css',
    '/js/admin/bibles/assets/dialogs.css',
    '/js/admin/bibles/source',
    'view.js',
    'app.js'
);

$( function() {
    var App = new BibleEditor.Application();

    $('.button').button();
});
