enyo.kind({
    name: 'BibleManager.Components.Forms.Edit',
    kind: 'BibleManager.Components.Forms.EditBasic',
    classes: 'edit_form edit_form_full',

    pk: null,
    formData: {},
    copyrightData: {},
    $description: null,
    formPk: null, // binding use only
    debugBindings: false,
    copyrightConfirmed: false,

    components: [
        {classes: 'form_section', components: [
            {tag: 'table', attributes: {border: '0'}, components: [
                {tag: 'tr', components: [
                    {tag: 'td', classes: 'form_label right', style: 'width: 180px', content: 'Full Display Name: '},
                    {tag: 'td', attributes: {colspan: 3}, classes: 'form_label right', components: [
                        {kind: 'AICWEBTECH.Enyo.UniqueText', name: 'name', classes: 'wide', apiUrl: '/admin/bibles/unique'},
                        // {kind: 'enyo.Input', name: 'name', classes: 'wide', onfocus: 'handleNameFocus',  onchange: 'handleNameChange'},
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
                        {name: 'ModuleRequired', tag: 'span', classes: 'required', content: '* unique'},
                        {name: 'ModuleDisabled', tag: 'span', classes: '', content: ' (Module cannot be changed once the module file exists)'}
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
                            content: 'Whether the Bible is enabled for use'
                        }
                    ]}
                ]},            
                {tag: 'tr', components: [
                    {tag: 'td', classes: 'form_label right', content: 'Restrict: '},
                    {tag: 'td', classes: 'form_label right', components: [
                        {kind: 'enyo.Checkbox', name: 'restict'},
                    ]}, 
                    {tag: 'td', classes: 'sublabel', attributes: {colspan: 2}, components: [
                        {
                            tag: 'span', 
                            content: 'Restrict access to only local domains. No outside API Access.'
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
                            content: 'Mark Bible as "For Research Only," if you don\'t reccommend the tranlation.'
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
                ]}
            ]}
        ]},
        {classes: 'form_section', components: [
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
                    {tag: 'td', classes: 'form_label right', content: 'Publication Year: '},
                    {tag: 'td', attributes: {colspan: 2}, classes: 'form_label right', components: [
                        {kind: 'enyo.Input', name: 'year'}
                    ]}
                ]},
            ]},
            {tag: 'hr'},
            {tag: 'table', components: [
                {tag: 'tr', components: [
                    {tag: 'td', attributes: {colspan: 2}, style: 'width: 49%', components: [
                        {content: 'Copyright Statement' },
                        {tag:'small', classes: 'sublabel', content: 'Will be displayed with Bible on search results page.' }
                    ]},
                    {tag: 'td', attributes: {colspan: 2}, style: 'width: 49%', components: [
                        {content: 'Default Copyright Statement' },
                        {tag:'small', classes: 'sublabel', content: 'Will be used if copyright statement is left blank.' }
                    ]}
                ]},            
                {tag: 'tr', components: [
                    {tag: 'td', attributes: {colspan: 2}, components: [
                        {
                            name: 'copyright_statement', 
                            kind: 'AICWEBTECH.Enyo.CKEDITOR.Editor', 
                            editorSettings: {
                                height: 100,
                                width: 400,
                            }
                        }
                    ]},
                    {tag: 'td', classes: 'align_top', attributes: {colspan: 2}, components: [
                        {name: 'copyright_statement_default', allowHtml: true, classes: 'copyright_statement_default pseudo_input'}
                    ]}
                ]},
            ]},
        ]},
        {classes: 'form_section', components: [
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
        ]}
    ],

    bindings: [
        {from: 'formData.name', to: '$.name.value', oneWay: false, transform: function(value, dir) {
            this.debugBindings && this.log('name', value, dir);

            if(dir == 2) {
                // this._checkUnique('name', value, 'Full Display Name');
            }

            return value || '';
        }},
        {from: 'formData.shortname', to: '$.shortname.value', oneWay: false, transform: function(value, dir) {
            this.debugBindings && this.log('shortname', value, dir);

            if(dir == 2) {
                // this._checkUnique('shortname', value, 'Short Display Name');
            }

            return value || '';
        }},       
        {from: 'formData.module', to: '$.module.value', oneWay: false, transform: function(value, dir) {
            this.debugBindings && this.log('module', value, dir);

            if(dir == 2) {
                // this._checkUnique('module', value, 'Module');
            }
            
            return value || '';
        }},
        {from: 'formData.year', to: '$.year.value', oneWay: false, transform: function(value, dir) {
            this.debugBindings && this.log('year', value, dir);
            return value || '';
        }},        
        {from: 'formData.description', to: '$.description.value', oneWay: false, transform: function(value, dir) {
            this.debugBindings && this.log('description', value, dir);
            value = value || '';
            
            if(dir == 1 && this.$description) {
                this.$description.setData(value); // feed it to the CKEDITOR
            }
            
            return value;
        }},
        {from: 'formData.rank', to: '$.rank.value', oneWay: false, transform: function(value, dir) {
            this.debugBindings && this.log('rank', value, dir);
            return (value || value === 0) ? value : null;
        }},        
        {from: 'formData.research', to: '$.research.checked', oneWay: false, transform: function(value, dir) {
            this.debugBindings && this.log('research', value, dir);

            if(dir == 1) {
                return (value) ? true : false;
            }
            else {
                return (value) ? 1 : 0;
            }
        }},
        {from: 'formData.enabled', to: '$.enabled.checked', oneWay: false, transform: function(value, dir) {
            this.debugBindings && this.log('enabled', value, dir);

            if(dir == 1) {
                return (value) ? true : false;
            }
            else {
                return value ? 1 : 0;
            }
        }},        
        {from: 'formData.restict', to: '$.restict.checked', oneWay: false, transform: function(value, dir) {
            this.debugBindings && this.log('restict', value, dir);

            if(dir == 1) {
                return (value) ? true : false;
            }
            else {
                return value ? 1 : 0;
            }
        }},
        {from: 'formData.copyright_id', to: '$.copyright_id.value', oneWay: false, transform: function(value, dir) {
            this.debugBindings && this.log('copyright_id', value, dir);

            value = (value && value != '0') ? value : null;

            if(dir == 2) {
                var oldValue = this.formData.copyright_id;
                this._confirmCopyright(value, oldValue);
            }
            else if(value == null) {
                this.set('copyrightConfirmed', true);
            }

            this._populateCopyrightInfo(value);
            return value;
        }},
        {from: 'formData.lang_short', to: '$.lang_short.value', oneWay: false, transform: function(value, dir) {
            this.debugBindings && this.log('lang_short', value, dir);
            return (value && value != '0') ? value : null;
        }},    
        {from: 'formData.copyright_statement', to: '$.copyright_statement.value', oneWay: false, transform: function(value, dir) {
            this.debugBindings && this.log('copyright_statement', value, dir);
            
            return value || '';
        }},       
        {from: 'formData.id', to: 'formPk', oneWay: false, transform: function(value, dir) {
            this.debugBindings && this.log('formPk', value, dir);
            value = (value && value != '0') ? value : null;

            var disableModule = (value && this.formData.has_module_file == '1') ? true : false;

            if(dir == 1) {
                this.$.module.set('disabled', disableModule);
                this.$.ModuleRequired.set('showing', !disableModule);
                this.$.ModuleDisabled.set('showing', disableModule);
            }

            return value
        }},        

        // Copyright data bindings
        {from: 'copyrightData.copyright_statement_processed', to: '$.copyright_statement_default.content', oneWay: true, transform: function(value, dir) {
            this.debugBindings && this.log('copyright_statement_default', value, dir);
            return value || '';
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
        this.inherited(arguments);

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

    save: function() {
        if(!this.get('copyrightConfirmed')) {
            this._confirmCopyright(this.formData.copyright_id, null);
            return;
        }

        this.inherited(arguments);
    },

    _populateCopyrightInfo: function(copyrightId) {
        var cr = bootstrap.copyrights.find(element => element.id == copyrightId);

        if(cr) {
            this.set('copyrightData', enyo.clone(cr));
        }
        else {
            this.set('copyrightData', {});
        }
    },
    _confirmCopyright: function(copyrightId, oldCopyrightId) {
        if(copyrightId == oldCopyrightId) {
            return;
        }

        var cr = bootstrap.copyrights.find(element => element.id == copyrightId);
        var old = oldCopyrightId;

        if(!cr) {
            return;
        }

        var msg = 'Please verify that this is the correct copyright for this Bible: <br /><br /><b>' + cr.name + '</b>';
            msg += '<br /><br /><span class="warning">Warning: Selecting the wrong copyright may put you at risk of civil or criminal penalties.</span>';

        this.app.confirm(msg, enyo.bind(this, function(confirmed) {
            this.set('copyrightConfirmed', confirmed);
            // this.log('copyright confirmed', confirmed);

            // if(!confirmed) {
            //     this.$.copyright_id.set('value', old);
            //     this.log('reverting copyright', old);
            //     // this.formData.copyright_id = oldCopyrightId;
            // }
            // else {
            //     this.log('retaining copyright');
            // }
        }));
    },
    handleNameChange: function(inSender, inEvent) {
        this.log();
    },
    handleNameFocus: function(inSender, inEvent) {
        this.log();

    }
});
