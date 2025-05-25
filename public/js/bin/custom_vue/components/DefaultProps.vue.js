var overall = {
    density: 'compact',
    'hide-details': 'auto',
};

var itemProps = {
    density: 'compact',
};

export default {
    vrows: {
        dense: true,
    },
    dividers: {
        class: 'my-4 border-opacity-50'
    },
    selects: {...overall},

    // Note: Item props need to be mixed in with the item via a function
    // Providing an object will trigger an error
    items: itemProps,
    itemPropsFunction: (item) => item ? {...itemProps, ...item} : {},

    texts: {...overall},
    textareas: {...overall},
    switches: {
        ...overall,
        ...{
            'false-value': 0,
            'true-value': 1,
            color: 'primary',
        }
    },
    ckeditor: {
        settings: {
            height: 300,
            width: 600,
            link: {
                decorators: {
                    openInNewTab: {
                        mode: 'manual',
                        label: 'Open in a new tab',
                        attributes: {
                            target: '_blank',
                            rel: 'noopener noreferrer'
                        }
                    }
                }
            },
            toolbar: {
                items: [
                    'undo', 'redo', 'findAndReplace', 'selectAll', '|',
                    'heading', 'alignment', '|',
                    // 'bold', 'italic', 'strikethrough', 'underline', 'subscript', 'superscript', 'removeFormat', '|',
                    'bold', 'italic', 'underline', 'removeFormat', '|',
                    'outdent', 'indent', '|',
                    '-',
                    'bulletedList', 'numberedList', '|',
                    
                    //'-',
                    // 'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor', 'highlight', '|', // removed hightlight as doutb it will be used
                    'specialCharacters', 'horizontalLine', 'pageBreak', '|',
                    'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor', '|',
                    // 'alignment', '|',
                    'link', 'blockQuote', '|',
                    // 'link', 'blockQuote', 'insertTable', '|',
                    'sourceEditing'

                    // Won't be used: 'insertTable', 'highlight'
                ],
                shouldNotGroupWhenFull: true // :todo - disable this for collapsing toolbar (rearrange toolbar)
            },
        }
    }
};
