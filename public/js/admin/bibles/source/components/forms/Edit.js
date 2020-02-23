enyo.kind({
    name: 'BibleManager.Components.Forms.Edit',
    kind: 'BibleManager.Components.Forms.EditBasic',

    classes: 'edit_form',

    pk: null,
    formData: {},
    copyrightData: {},
    $description: null,

    components: [
        {tag: 'table', attributes: {border: '0'}, components: [
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', style: 'width: 180px', content: 'Full Display Name: '},
                {tag: 'td', attributes: {colspan: 3}, classes: 'form_label right', components: [
                    {kind: 'enyo.Input', name: 'name', classes: 'wide'},
                    {tag: 'span', classes: 'required', content: '* unique'}
                ]}
            ]},
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Short Display Name: '},
                {tag: 'td', attributes: {colspan: 2}, classes: 'form_label right', components: [
                    {kind: 'enyo.Input', name: 'shortname'},
                    {tag: 'span', classes: 'required', content: '* unique'}
                ]}
            ]},            
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Module: '},
                {tag: 'td', attributes: {colspan: 2}, classes: 'form_label right', components: [
                    {kind: 'enyo.Input', name: 'module'},
                    {tag: 'span', classes: 'required', content: '* unique'}
                ]}
            ]},
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Publication Year: '},
                {tag: 'td', attributes: {colspan: 2}, classes: 'form_label right', components: [
                    {kind: 'enyo.Input', name: 'year'}
                ]}
            ]},
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Enabled: '},
                {tag: 'td', classes: 'form_label right', components: [
                    {kind: 'enyo.Checkbox', name: 'enabled'}, 
                ]},
                {tag: 'td', classes: 'sublabel', attributes: {colspan: 2}, components: [
                    {
                        tag: 'span', 
                        // classes: 'sublabel', 
                        content: 'Whether or not the Bible is enabled for use via the API'
                    }
                ]}
            ]},            
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Research Only: '},
                {tag: 'td', classes: 'form_label right', components: [
                    {kind: 'enyo.Checkbox', name: 'research'},
                ]}, 
                {tag: 'td', classes: 'sublabel', attributes: {colspan: 2}, components: [
                    {
                        tag: 'span', 
                        // classes: 'sublabel', 
                        content: 'If a Bible translation isn\'t up to the highest standards, or for other reasons you don\'t reccommend it, you can mark it as "For Research Only."'
                    }
                ]}
            ]},
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Restrict: '},
                {tag: 'td', classes: 'form_label right', components: [
                    {kind: 'enyo.Checkbox', name: 'restrict'},
                ]}, 
                {tag: 'td', classes: 'sublabel', attributes: {colspan: 2}, components: [
                    {
                        tag: 'span', 
                        // classes: 'sublabel', 
                        content: 'Restrict access to only local domains. No outside API Access.'
                    }
                ]}
            ]},
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Sort Order: '},
                {tag: 'td', attributes: {colspan: 2}, classes: 'form_label right', components: [
                    {kind: 'enyo.Input', name: 'rank'}
                ]}
            ]},            
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Language: '},
                {tag: 'td', attributes: {colspan: 2}, classes: 'form_label right', components: [
                    {kind: 'AICWEBTECH.Enyo.Select', classes: 'wide', name: 'lang_short', components: [
                        {value: null, content: 'Select One ...'}
                    ]},
                    {tag: 'span', classes: 'required', content: '*'}
                ]}
            ]},
        ]},
        {tag: 'table', components: [        
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', style: 'width: 180px', content: 'Copyright: '},
                {tag: 'td', attributes: {colspan: 3}, classes: 'form_label right', style: 'width: 618px', components: [
                    {kind: 'AICWEBTECH.Enyo.Select', name: 'copyright_id', classes: 'wide', components: [
                        {value: null, content: 'Select One ...'}
                    ]},
                    {tag: 'span', classes: 'required', content: '*'}
                ]},
            ]},            
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Copyright Owner: '},
                {tag: 'td', classes: 'form_label right', components: [
                    {kind: 'enyo.Input', name: 'owner'}
                ]},
            ]},            
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Publisher Name: '},
                {tag: 'td', classes: 'form_label right', components: [
                    {kind: 'enyo.Input', name: 'publisher'}
                ]},
            ]},
            {tag: 'tr', components: [
                {tag: 'td', attributes: {colspan: 2}, _style: 'width: 49%', content: 'Copyright Statement' },
                {tag: 'td', attributes: {colspan: 2}, _style: 'width: 49%', content: 'Default Copyright Statement' }
            ]},            
            {tag: 'tr', components: [
                {tag: 'td', attributes: {colspan: 2}, components: [
                    {
                        name: 'copyright_statement', 
                        kind: 'AICWEBTECH.Enyo.CKEDITOR.Editor', 
                        editorSettings: {
                            height: 300,
                            width: 400,
                        }
                    }
                ]},
                {tag: 'td', attributes: {colspan: 2}, components: [
                    {name: 'copyright_statement_default', _kind: 'enyo.TextArea', allowHtml: true, disabled: true, classes: 'copyright_statement'}
                ]}
            ]},
        ]},
        {tag: 'table', components: [
            {tag: 'tr', ontap: 'toggleDescription', components: [
                {tag: 'th', content: '&nbsp', allowHtml: true},
                {tag: 'th', attributes: {colspan: 4}, content: 'Description'},
                {tag: 'th', name: 'descriptionPointer', content: '&#x25bc;', allowHtml: true, style: 'text-align: right'}
            ]},            
            {tag: 'tr', name: 'DescriptionContainer', showing: false, components: [
                {tag: 'td', attributes: {colspan: 4}, components: [
                    {kind: 'enyo.TextArea', name: 'description', id:'description'}
                ]}
            ]}
        ]}
    ],

    bindings: [
        {from: 'formData.name', to: '$.name.value', oneWay: false, transform: function(value, dir) {
            this.log('name', value, dir);

            if(dir == 2) {
                this._checkUnique('name', value, 'Full Display Name');
            }

            return value || '';
        }},
        {from: 'formData.shortname', to: '$.shortname.value', oneWay: false, transform: function(value, dir) {
            this.log('shortname', value, dir);

            if(dir == 2) {
                this._checkUnique('shortname', value, 'Short Display Name');
            }

            return value || '';
        }},       
        {from: 'formData.module', to: '$.module.value', oneWay: false, transform: function(value, dir) {
            this.log('module', value, dir);

            if(dir == 2) {
                this._checkUnique('module', value, 'Module');
            }
            
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
        {from: 'formData.research', to: '$.research.checked', oneWay: false, transform: function(value, dir) {
            this.log('research', value, dir);

            if(dir == 1) {
                return (value) ? true : false;
            }
            else {
                return (value) ? 1 : 0;
            }
        }},
        {from: 'formData.enabled', to: '$.enabled.checked', oneWay: false, transform: function(value, dir) {
            this.log('enabled', value, dir);

            if(dir == 1) {
                return (value) ? true : false;
            }
            else {
                return value ? 1 : 0;
            }
        }},        
        {from: 'formData.restrict', to: '$.restrict.checked', oneWay: false, transform: function(value, dir) {
            this.log('restrict', value, dir);

            if(dir == 1) {
                return (value) ? true : false;
            }
            else {
                return value ? 1 : 0;
            }
        }},
        {from: 'formData.copyright_id', to: '$.copyright_id.value', oneWay: false, transform: function(value, dir) {
            this.log('copyright_id', value, dir);

            value = (value && value != '0') ? value : null;
            this._populateCopyrightInfo(value);
            return value;
        }},
        {from: 'formData.lang_short', to: '$.lang_short.value', oneWay: false, transform: function(value, dir) {
            this.log('lang_short', value, dir);
            return (value && value != '0') ? value : null;
        }},        


        // Copyright data bindings
        {from: 'copyrightData.copyright_statement_processed', to: '$.copyright_statement_default.content', oneWay: true, transform: function(value, dir) {
            this.log('copyright_statement_default', value, dir);
            return value || null;
        }},


    ],

    create: function() {
        this.inherited(arguments);

        bootstrap.copyrights.forEach(function(item) {
            this.$.copyright_id.createComponent({
                value: item.id,
                content: item.name
            });
        }, this);        

        bootstrap.languages.forEach(function(item) {
            var displayName = item.name + ' (' + item.code.toUpperCase() + ')';

            this.$.lang_short.createComponent({
                value: item.code,
                content: displayName
            });
        }, this);
    },

    toggleDescription: function() {
        this.$.DescriptionContainer.set('showing', !this.$.DescriptionContainer.get('showing'));

        var pointer = this.$.DescriptionContainer.get('showing') ? '&#x25b2;' : '&#x25bc;'

        this.$.descriptionPointer.set('content', pointer);
    },

    rendered: function() {
        this.$description = CKEDITOR.replace('description', {
            height: 300,
            width: 750,
        });

        this.$description.on('change', enyo.bind(this, function() {
            this.$.description.set('value', this.$description.getData());
        }));
    }, 

    _checkUnique: function(field, value, label) {
        var postData = {
            id: this.pk,
            field: field,
            value: value
        };

        var ajax = new enyo.Ajax({
            url: '/admin/bibles/unique',
            method: 'GET',
            headers: this.app.defaultAjaxHeaders
        });

        ajax.response(this, function(inSender, inResponse) {
            this.app.set('ajaxLoading', false);

            if(!inResponse.success) {
                var msg = 'An Error has occurred';
                this.app.alert(msg);
                this.close();
                return;
            }

            this.open();
            this.set('formData', enyo.clone(inResponse.Bible));
            this.parent.set('title', 'Editing: ' + inResponse.Bible.name);
        });

        ajax.error(this, function(inSender, inResponse) {
            console.log('ERROR', inSender, inResponse);
            this.app.set('ajaxLoading', false);
            var response = JSON.parse(inSender.xhrResponse.body);
            this.app._errorHandler(inSender, response);
            this.close();
        });

        ajax.go();
    },

    _populateCopyrightInfo: function(copyrightId) {
        var cr = bootstrap.copyrights.find(element => element.id == copyrightId);

        this.log(cr);

        if(cr) {
            this.set('copyrightData', enyo.clone(cr));
        }
        else {
            this.set('copyrightData', {});
        }
    }
});
