enyo.depends(
    //'/js/bin/ckeditor/ckeditor.js',  // v4
    '../../bin/ckeditor5/build/ckeditor.js',
    '../../bin/custom/form',
    '../../bin/custom/dialog',
    'source',
    'assets/style.css',
    'assets/dialogs.css'
);

$( function() {
    var App = new BibleManager.Application();
});
