enyo.depends(
    '../../../bin/ckeditor5/build/ckeditor.js',
    '../../../bin/custom/form',
    '../../../bin/custom/dialog',
    '../../../admin/bibles_old/assets/style.css',
    '../../../admin/bibles_old/assets/dialogs.css',
    '../../../admin/bibles_old/source',
    'view.js',
    'app.js'
);

$( function() {
    var App = new BibleEditor.Application();

    $('.button').button();
});
