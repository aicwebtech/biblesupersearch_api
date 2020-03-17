enyo.kind({
    name: 'BibleManager.Components.Forms.Import.Config.Spreadsheet',
    kind: 'BibleManager.Components.Forms.Import.Config.Base',
    classes: 'spreadsheet',
    colNum: 0,

    components: [
        {tag: 'br'},
        {content: 'Please select the role of each column: '},
        {tag: 'table', name: 'ColSettings', _classes: 'import_form', components: [
            {tag: 'tr', components: [
                {tag: 'th', content: 'Column'},
                {tag: 'th', content: 'Role'}
            ]}
        ]}, 
        {tag: 'button', content: 'Add Column', ontap: 'addColumnTab'}
    ],

    // handlers: {
    //     colSettingChanged: 'colSettingChanged'
    // },

    create: function() {
        this.inherited(arguments);

        for(var i = 1; i <= 6; i ++) {
            this._addColumn();
        }
    },

    _addColumn: function() {
        if(this.colNum == 26) {
            return;
        }

        this.colNum ++;
        var charCode = this.colNum + 64,
            letter = String.fromCharCode(charCode);

        var comp = this.$.ColSettings.createComponent({
            tag: 'tr',
            owner: this,
            components: [
                {tag: 'td', content: letter + ': '},
                {tag: 'td', components: [
                    {kind: 'enyo.Select', _kind: 'AICWEBTECH.Enyo.Select', name: 'sp_col_' + letter, _letter: letter, onchange: 'colSettingChanged', components: [
                        {value: 'none', content: '-- None --'},
                        {value: 'bn', content: 'Book Name'},
                        {value: 'b', content: 'Book Number'},
                        {value: 'bn c:v ', content: 'Book Name Chapter:Verse'},
                        {value: 'b c:v ', content: 'Book Number Chapter:Verse'},
                        {value: 'c', content: 'Chapter'},
                        {value: 'v', content: 'Verse'},
                        {value: 'c:v', content: 'Chapter:Verse'},
                        {value: 't', content: 'Text'}
                    ]}
                ]}
            ]
        });

        return comp;
    },
    addColumnTab: function(inSender, inEvent) {
        this.log(inSender, inEvent);
        this._addColumn().render();
    },
    colSettingChanged: function(inSender, inEvent) {
        this.log(inSender, inEvent);

        var val = inSender.getValue();
            val = (val == 'none') ? null : val,
            prop = 'col_' + inSender._letter;

        if(!val) {
            delete this.configProps[prop];
        }
        else {
            this.configProps[prop] = val;
        }
    }
});
