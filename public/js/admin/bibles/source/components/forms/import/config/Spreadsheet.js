enyo.kind({
    name: 'BibleManager.Components.Forms.Import.Config.Spreadsheet',
    kind: 'BibleManager.Components.Forms.Import.Config.Base',
    classes: 'spreadsheet',
    colNum: 0,

    components: [
        {tag: 'hr'},
        {style: 'width: 250px', classes: 'center-element', components: [
            {   components: [
                {tag: 'label', content: 'First Row of Verse Data: '},
                {kind: 'enyo.Input', name: 'first_row_data', value: '2', style: 'width: 50px'},
                {tag: 'span', classes: 'required', content: '*'}
            ]},
            {tag: 'br'},
            {
                classes: 'center-align', components: [
                    {tag: 'span', content: 'Please select the role of each column: '},
                    {tag: 'span', classes: 'info', components: [
                        {tag: 'span', content: 'i'},
                        {style: 'text-align: justify', classes: 'container', components: [
                            {content: 'If multiple columns have the same role, the leftmost column will be used for the role.'},            
                            {tag: 'br'},
                            // {tag: 'br'},
                            // {tag: 'br'},
                            {content: 'If you do not wish to use a given column in the import, please select --None-- as it\'s role.'},
                        ]}
                    ]}

                ]
            },
            {tag: 'br'},
        ]},
        {style: 'width: 250px', classes: 'center-element', components: [
            {tag: 'table', name: 'ColSettings', components: [
                {tag: 'tr', components: [
                    {tag: 'th', content: 'Column'},
                    {tag: 'th', content: 'Role'}
                ]}
            ]}, 
            {classes: 'center-align', components: [
                {tag: 'button', content: 'Add Column', ontap: 'addColumnTap', name: 'AddColumn'}
            ]}
        ]}
    ],

    bindings: [
        {from: 'configProps.first_row_data', to: '$.first_row_data.value', oneWay: false, transform: function(value, dir) {
            return value || '';
        }}
    ],

    create: function() {
        this.inherited(arguments);
        this.configProps.first_row_data = '2';

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
            letter = String.fromCharCode(charCode),
            letterLC = letter.toLowerCase();
            
        this.configProps['col_' + letterLC] = null;

        var comp = this.$.ColSettings.createComponent({
            tag: 'tr',
            owner: this,
            components: [
                {tag: 'td', classes: 'right-align', content: letter + ': '},
                {tag: 'td', components: [
                    {kind: 'enyo.Select', _kind: 'AICWEBTECH.Enyo.Select', name:'col_' + letterLC, _letter: letterLC, onchange: 'colSettingChanged', components: [
                        {value: 'none', content: '-- None --'},
                        {value: 'id', content: 'ID - Unique (currently ignored)'},
                        {value: 'bn', content: 'Book Name'},
                        {value: 'b', content: 'Book Number'},
                        {value: 'c', content: 'Chapter'},
                        {value: 'v', content: 'Verse'},
                        {value: 't', content: 'Text'},
                        {value: 'bn c:v ', content: 'Book Name Chapter:Verse'},
                        {value: 'b c:v ', content: 'Book Number Chapter:Verse'},
                        {value: 'c:v', content: 'Chapter:Verse'}
                    ]}
                ]}
            ]
        });

        if(this.colNum == 26) {
            this.$.AddColumn.set('showing', false);
        }

        return comp;
    },
    addColumnTap: function(inSender, inEvent) {
        this._addColumn().render();
    },
    colSettingChanged: function(inSender, inEvent) {
        var val = inSender.getValue();
            val = (!val || val == 'none') ? null : val,
            prop = 'col_' + inSender._letter;

        this.configProps[prop] = val;
    },
    disabledChanged: function(was, is) {
        this.inherited(arguments);
        this.log();

        var addColumnShowing = (!is && this.colNum < 26) ? true : false;
        this.$.AddColumn.set('showing', (!is && this.colNum < 26));

        this.$.first_row_data.set('disabled', is);

        for(var i = 1; i <= this.colNum; i ++) {
            var charCode = i + 96,
                letter = String.fromCharCode(charCode);
                elemName = 'col_' + letter;

            this.$[elemName] && this.$[elemName].setAttribute('disabled', true);
        }
    }
});
