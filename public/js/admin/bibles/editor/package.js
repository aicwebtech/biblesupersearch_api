enyo.depends(
    '../../../bin/ckeditor5/build/ckeditor.js',
    '../../../bin/custom/form',
    '../../../bin/custom/dialog',
    '../../../admin/bibles/assets/style.css',
    '../../../admin/bibles/assets/dialogs.css',
    '../../../admin/bibles/source',
    'view.js',
    'app.js'
);

$( function() {
    var App = new BibleEditor.Application();

    $('.button').button();
});
