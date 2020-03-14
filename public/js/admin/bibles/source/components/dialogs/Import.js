enyo.kind({
    name: 'BibleManager.Components.Dialogs.Import',
    kind: 'AICWEBTECH.Enyo.jQuery.Dialog',
    pk: null,
    fileValidated: null,
    formData: {},
    importerData: {},
    fileSanitized: null,

    components: [
        {tag: 'table', classes: 'import_form', _attributes: {border: 1}, components: [
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Importer: ', style: 'width: 100px'},
                {tag: 'td', classes: 'form_label right', components: [
                    {kind: 'AICWEBTECH.Enyo.Select', name: 'type', components: [
                        {value: 0, content: 'Select One ...'}
                    ]},
                    {tag: 'span', classes: 'required', content: '*'}
                ]},
            ]},            
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Details: '},
                {tag: 'td', classes: 'form_label right import_desc_container', components: [
                    {name: 'ImportDesc', allowHtml: true, content: '(Please select an importer.)'}, 
                    {kind: 'enyo.Anchor', name: 'ImportUrl', attributes: {target: '_NEW'}}
                ]}
            ]},
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'File: '},
                {tag: 'td', classes: 'form_label right', components: [
                    {kind: 'enyo.Input', type: 'file', name: 'file', onChange: 'formChanged'},
                    {tag: 'span', classes: 'required', content: '*'}
                ]},
            ]}
        ]},

        {name: 'ConfigContainer', components: [
            {name: 'ConfigView', kind: 'enyo.ViewController'},
        ]},
        {name: 'EditContainer', showing: false, components: [
            {name: 'EditView', kind: 'enyo.ViewController'}
        ]}
    ],

    bindings: [
        {from: 'formData.type', to: '$.type.value', oneWay: false, transform: function(value, dir) {
            this.log('type', value, dir);
            value = (value && value != '0') ? value : null;
            this._populateImportInfo(value);
            
            if(dir == 2) {
                this.set('fileValidated', false);
            }
            
            return value;
        }},
        {from: 'formData.file', to: '$.file.value', oneWay: true, transform: function(value, dir) {
            this.log('file', value, dir);
            
            if(dir == 2) {
                this.set('fileValidated', false);
            }

            return value || '';
        }},          
        {from: 'importerData.desc', to: '$.ImportDesc.content', oneWay: true, transform: function(value, dir) {
            this.log('import desc', value, dir);
            return value || '';
        }},        
        {from: 'importerData.url', to: '$.ImportUrl.content', oneWay: true, transform: function(value, dir) {
            this.log('import url content', value, dir);
            return value || '';
        }},        
        {from: 'importerData.url', to: '$.ImportUrl.href', oneWay: true, transform: function(value, dir) {
            this.log('import url link', value, dir);
            return value || '';
        }}
    ],

    create: function() {
        this.inherited(arguments);
        this.set('fileValidated', false);

        bootstrap.importers.forEach(function(item) {
            this.$.type.createComponent({
                value: item.type,
                content: item.name + ' (.' + item.ext.join(', .') + ')'
            });
        }, this);
    },

    fileValidatedChanged: function(was, is) {
        var dialogOptions = {
            height: 'auto',
            width: 'auto',
            modal: true,
            autoOpen: false,
            buttons: [
                {
                    text: 'Cancel',
                    icon: 'ui-icon-cancel',
                    click: enyo.bind(this, this.close)
                },
            ]
        };

        this.$.EditView.set('view', null);

        if(is) {
            dialogOptions.buttons.unshift({
                text: 'Import File',
                icon: 'ui-icon-check',
                click: enyo.bind(this, this.save)
            });

            var title = 'Bible Importer: Import File';
            var cr = this.get('importerData');

            if(BibleManager.Components.Forms.Import.Edit[cr.kind]) {
                this.$.EditView.set('view', BibleManager.Components.Forms.Import.Edit[cr.kind]).render();
            }
        }
        else {
            dialogOptions.buttons.unshift({
                text: 'Check File',
                icon: 'ui-icon-check',
                click: enyo.bind(this, this.validate)
            });
            
            var title = 'Bible Importer: Select File';
            this.set('fileSanitized', null);
        }

        this.$.file.set('disabled', is);
        this.$.type.setAttribute('disabled', is);

        this.$.EditContainer.set('showing', is);
        this.setDialogOptions(dialogOptions);
        this.set('title', title);
    },

    validate: function() {
        var postData = enyo.clone(this.formData);
        var errors = [];

        var file = this.$.file.hasNode().files[0];

        if(!postData.type) {
            errors.push('Importer is required');
        }

        if(!file) {
            errors.push('File is required');
        }

        if(errors.length > 0) {
            this.app.alert(errors.join('<br />'));
            return;
        }

        var formData = new FormData();
        // var formData = new enyo.FormData();
        file && formData.append('file', file, file.name); 

        formData.append('importer', postData.type);
        formData.append('_token', laravelCsrfToken);

        var ajax = new enyo.Ajax({
            url: '/admin/bibles/importcheck',
            method: 'POST',
            contentType: 'multipart/form-data',
            headers: this.app.defaultAjaxHeaders,
            cacheBust: false,
            postBody: formData
        });

        ajax.response(this, function(inSender, inResponse) {
            this.app.set('ajaxLoading', false);

            if(!inResponse.success) {
                return this.app._errorHandler(inSender, inResponse)
            }

            this.set('fileValidated', true);

            this.$.EditView.get('view') && this.$.EditView.get('view').set('formData', inResponse.bible);
            this.set('fileSanitized', inResponse.file);
        });

        ajax.error(this, function(inSender, inResponse) {
            console.log('ERROR', inSender, inResponse);
            this.app.set('ajaxLoading', false);
            var response = JSON.parse(inSender.xhrResponse.body);
            this.app._errorHandler(inSender, response);
        });

        ajax.go();

        // this._saveHelper('importcheck', formData);
    },

    save: function() {
        if(!this.$.EditView.get('view')) {
            return;
        }

        var postData = enyo.clone( this.$.EditView.get('view').get('formData') );
        postData._token = laravelCsrfToken;
        postData._file = this.get('fileSanitized');
        postData._importer = this.$.type.get('value');

        this.log(postData);

        // return;
        
        this.app.set('ajaxLoading', true);

        var ajax = new enyo.Ajax({
            url: '/admin/bibles/import',
            method: 'POST',
            // contentType: 'multipart/form-data',
            headers: this.app.defaultAjaxHeaders,
            postBody: postData
        });

        ajax.response(this, function(inSender, inResponse) {
            this.app.set('ajaxLoading', false);

            if(!inResponse.success) {
                return this.app._errorHandler(inSender, inResponse)
            }

            this.app.refreshGrid();
            this.close();
        });

        ajax.error(this, function(inSender, inResponse) {
            console.log('ERROR', inSender, inResponse);
            this.app.set('ajaxLoading', false);
            var response = JSON.parse(inSender.xhrResponse.body);
            this.app._errorHandler(inSender, response);
        });

        ajax.go();
    },

    _errorHandler: function(inSender, inResponse) {
        var msg = 'An Error has occurred';

        if(inResponse.errors) {
            msg += '<br /><br />';

            for(field in inResponse.errors) {
                var err = inResponse.errors[field];

                err.forEach(function(e) {
                    msg += e + '<br />';
                });
            }
        }

        this.app.alert(msg);
    }, 
    openLoad: function() {
        this.set('fileValidated', false);
        this._resetFormData();
        this._resetBibleData();
        this.set('showing', true);
    },
    _resetFormData: function() {
        this.set('formData', {});
        
        if(this.$.file.hasNode()) {
            this.$.file.hasNode().value = '';
        }
    },
    _resetBibleData: function() {

    },
    _populateImportInfo: function(type) {
        var cr = bootstrap.importers.find(element => element.type == type);

        this.log(cr);
        this.$.ConfigView.set('view', null);

        if(cr) {
            this.set('importerData', enyo.clone(cr));
            
            if(BibleManager.Components.Forms.Import.Config[cr.kind]) {
                this.$.ConfigView.set('view', BibleManager.Components.Forms.Import.Config[cr.kind]).render();
            }
        }
        else {
            this.set('importerData', {desc: '(Please select an importer.)'});
        }
    }, 
    formChanged: function() {
        this.log();
        this.set('fileValidated', false);
    }
});
