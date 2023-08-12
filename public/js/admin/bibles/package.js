enyo.depends(
    '/js/bin/ckeditor/ckeditor.js',
    //'//cdn.ckeditor.com/4.22.1/standard/ckeditor.js',
    // '/js/bin/ckeditor_5_39/ckeditor.js',
    '/js/bin/custom/form',
    '/js/bin/custom/dialog',
    'source',
    'assets/style.css',
    'assets/dialogs.css'
);

$( function() {
    var App = new BibleManager.Application();
});
