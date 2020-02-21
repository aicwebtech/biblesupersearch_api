enyo.kind({
    name: 'BibleManager.Components.Forms.Edit',
    kind: 'BibleManager.Components.Forms.EditBasic',

    classes: 'edit_form',

    pk: null,
    formData: {},
    $description: null,

    components: [
        {tag: 'table', components: [
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Name: '},
                {tag: 'td', attributes: {colspan: 3}, classes: 'form_label right', components: [
                    {kind: 'enyo.Input', name: 'name'}
                ]}
            ]},
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Short Name: '},
                {tag: 'td', classes: 'form_label right', components: [
                    {kind: 'enyo.Input', name: 'shortname'}
                ]},
                {tag: 'td', classes: 'form_label right', content: 'Module: '},
                {tag: 'td', classes: 'form_label right', components: [
                    {kind: 'enyo.Input', name: 'module'}
                ]}
            ]},
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Year: '},
                {tag: 'td', classes: 'form_label right', components: [
                    {kind: 'enyo.Input', name: 'year'}
                ]}
            ]},
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Rank: '},
                {tag: 'td', classes: 'form_label right', components: [
                    {kind: 'enyo.Input', name: 'rank'}
                ]}
            ]},
            {tag: 'tr', components: [
                {tag: 'th', attributes: {colspan: 4}, content: 'Description'}
            ]},            
            {tag: 'tr', components: [
                {tag: 'td', attributes: {colspan: 4}, components: [
                    {kind: 'enyo.TextArea', name: 'description', id:'description'}
                ]}
            ]}
        ]}
    ],

    bindings: [
        {from: 'formData.name', to: '$.name.value', oneWay: false, transform: function(value, dir) {
            this.log('name', value, dir);
            return value || '';
        }},
        {from: 'formData.shortname', to: '$.shortname.value', oneWay: false, transform: function(value, dir) {
            this.log('shortname', value, dir);
            return value || '';
        }},       
        {from: 'formData.module', to: '$.module.value', oneWay: false, transform: function(value, dir) {
            this.log('module', value, dir);
            return value || '';
        }},
        {from: 'formData.year', to: '$.year.value', oneWay: false, transform: function(value, dir) {
            this.log('year', value, dir);
            return value || '';
        }},        
        {from: 'formData.description', to: '$.description.value', oneWay: false, transform: function(value, dir) {
            this.log('description', value, dir);
            
            if(dir == 1 && this.$description) {
                this.$description.setData(value); // feed it to the CKEDITOR
            }
            
            return value || '';
        }},
        {from: 'formData.rank', to: '$.rank.value', oneWay: false, transform: function(value, dir) {
            this.log('rank', value, dir);
            return (value || value === 0) ? value : null;
        }},
        // {from: 'props.enable', to: '$.enable.checked', oneWay: false, transform: function(value, dir) {
        //     this.log('enable', value, dir);

        //     if(dir == 1) {
        //         return (value) ? true : false;
        //     }
        //     else {
        //         return value ? 1 : 0;
        //     }
        // }}
    ],

    create: function() {
        this.inherited(arguments);

    },

    rendered: function() {
        this.$description = CKEDITOR.replace('description', {
            height: 400,
            width: 800,
            // change: enyo.bind(this, function() {

            // })
        });

        this.$description.on('change', enyo.bind(this, function() {
            this.$.description.set('value', this.$description.getData());
        }));
    }
});
