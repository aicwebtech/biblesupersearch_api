enyo.kind({
   name: 'BibleManager.Components.Forms.Import.Config.Analyzer',
   kind: 'BibleManager.Components.Forms.Import.Config.Base',

   components: [
        {tag: 'h3', content: 'How to download Bible Analyzer .bib files:'},
        {tag: 'ol', components: [
            {
                tag: 'li', 
                allowHtml: true, 
                content: [
                    'Install the Bible Analyzer software.',
                    '&nbsp; &nbsp;<a href="http://www.bibleanalyzer.com/download.htm" target="_NEW">http://www.bibleanalyzer.com/download.htm</a>',
                ].join('<br \>')
            },
            {
                tag: 'li', 
                allowHtml: true, 
                content: [
                    'Install Bible modules.',
                    '&nbsp; &nbsp;(Bible Analyzer => Modules => Module Download Manager)'
                ].join('<br \>')
            },
            {
                tag: 'li', 
                allowHtml: true, 
                content: [
                    'Locate the downloaded module files.',
                    '&nbsp; &nbsp;(Bible Analyzer => Tools => Display Primary Modules Folder)'
                ].join('<br \>')
            },
            {
                tag: 'li', 
                allowHtml: true, 
                content: [
                    'Navigate to the Bibles folder.',
                    '&nbsp; &nbsp;(On Windows PCs, this will be located at', 
                    '&nbsp; &nbsp; &nbsp; &nbsp;C:\\ProgramData\\Bible Analyzer\\Modules\\Bible)',
                ].join('<br \>')
            },            
            {
                tag: 'li', 
                allowHtml: true, 
                content: [
                    'Here you\'ll find the .bib files, which can be imported above.',
                ].join('<br \>')
            },
        ]},
        {tag: 'small', content: 'Disclaimer: Bible Analyzer is not affilated with Bible SuperSearch in any way.'},
   ]
});
