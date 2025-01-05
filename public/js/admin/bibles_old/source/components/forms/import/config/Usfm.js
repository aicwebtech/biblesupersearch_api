enyo.kind({
   name: 'BibleManager.Components.Forms.Import.Config.Usfm',
   kind: 'BibleManager.Components.Forms.Import.Config.Base',

   components: [
        {tag: 'h4', content: 'How to download USFM .zip files:'},
        {tag: 'h4', content: 'Bibles in this format can be downloaded from ebible.com, however, please make sure to select the USFM format option.'},
        {tag: 'h4', content: 'Note: we only support the following markup features, everything else will be ignored:'},
        {tag: 'ol', components: [
            {
                tag: 'li', 
                allowHtml: true, 
                content: 'Italiced (added in translation) words'
            },
            {
                tag: 'li', 
                allowHtml: true, 
                content: "Words of Christ in Red"
            },
            {
                tag: 'li', 
                allowHtml: true, 
                content: 'Strong\'s numbers'
            },            
        ]}
   ]
});
