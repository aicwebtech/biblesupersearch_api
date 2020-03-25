enyo.kind({
    name: 'BibleManager.Components.Dialogs.Import',
    kind: 'AICWEBTECH.Enyo.jQuery.Dialog',
    classes: 'dialog_form',
    pk: null,
    fileValidated: null,
    formData: {},
    importerData: {},
    fileSanitized: null,
    debugBindings: false,

    components: [
        {tag: 'table', classes: 'import_form', _attributes: {border: 1}, components: [
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Importer: ', style: 'width: 70px'},
                {tag: 'td', classes: 'form_label right', components: [
                    {kind: 'AICWEBTECH.Enyo.Select', name: 'type', components: [
                        {value: null, content: 'Select One ...'}
                    ]},
                    {tag: 'span', classes: 'required', content: '*'}
                ]},
            ]},            
            {tag: 'tr', components: [
                // {tag: 'td', classes: 'form_label right', content: 'Details: '},
                {tag: 'td', classes: 'form_label right import_desc_container', attributes: {colspan: 2}, components: [
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
            this.debugBindings && this.log('type', value, dir);
            value = (value && value != '0') ? value : null;
            this._populateImportInfo(value);
            
            if(dir == 2) {
                this.set('fileValidated', false);
            }
            
            return value;
        }},
        {from: 'formData.file', to: '$.file.value', oneWay: true, transform: function(value, dir) {
            this.debugBindings && this.log('file', value, dir);
            
            if(dir == 2) {
                this.set('fileValidated', false);
            }

            return value || '';
        }},          
        {from: 'importerData.desc', to: '$.ImportDesc.content', oneWay: true, transform: function(value, dir) {
            this.debugBindings && this.log('import desc', value, dir);
            return value || '';
        }},        
        {from: 'importerData.url', to: '$.ImportUrl.content', oneWay: true, transform: function(value, dir) {
            this.debugBindings && this.log('import url content', value, dir);
            return value || '';
        }},        
        {from: 'importerData.url', to: '$.ImportUrl.href', oneWay: true, transform: function(value, dir) {
            this.debugBindings && this.log('import url link', value, dir);
            return value || '';
        }}
    ],

    create: function() {
        this.inherited(arguments);
        this.set('fileValidated', false);

        // this.$.type.createComponent({value: null, content: 'Select One ...'});

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
            // this.setStyle('width', '800px');
            dialogOptions.width = 820;

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
            
            dialogOptions.width = 480;
            // this.setStyle('width', '400px');
            var title = 'Bible Importer: Select File';
            this.set('fileSanitized', null);
        }

        this.$.ConfigView.get('view') && this.$.ConfigView.get('view').set('disabled', is);

        this.$.file.set('disabled', is);
        this.$.type.setAttribute('disabled', is);

        this.$.EditContainer.set('showing', is);
        this.setDialogOptions(dialogOptions);
        this.set('title', title);
        // this.render();
    },

    validate: function() {
        var postData = enyo.clone(this.formData);
        var errors = [];
        var file = this.$.file.hasNode().files[0];
        var importer = this.get('importerData');

        // Frontend validation
        if(!postData.type) {
            errors.push('Importer is required');
        }

        if(!file) {
            errors.push('File is required');
        }
        else {
            var fnParts = file.name.split('.');
                ext = fnParts.pop();

            if(!importer.ext.includes(ext)) {
                if(importer.ext.length == 1) {
                    errors.push('Invalid file extension .' + ext + '; File must have .' + importer.ext[0] + ' extension');
                }
                else {
                    errors.push('Invalid file extension .' + ext + '; Extension must be one of the following: .' + importer.ext.join(', .'));
                }
            }
        }

        if(errors.length > 0) {
            this.app.alert(errors.join('<br />'));
            return;
        }

        if(!this.$.ConfigView.view.validate()) {
            return;
        }

        var formData = new FormData();
        // var formData = new enyo.FormData();
        file && formData.append('file', file, file.name); 

        formData.append('importer', postData.type);
        formData.append('_token', laravelCsrfToken);

        var configProps = this.$.ConfigView.view.get('configProps');

        this.log('configProps', configProps);

        for(var i in configProps) {
            formData.append(i, configProps[i]);
        }
        
        this.app.set('ajaxLoading', true);

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

            this.app.alert('This file is ready to import. &nbsp;Please fill out the rest of<br />the information for this Bible, then click \'Import File\'');
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
        var configProps = this.$.ConfigView.view.get('configProps');
        
        postData._token = laravelCsrfToken;
        postData._file = this.get('fileSanitized');
        postData._importer = this.$.type.get('value');
        postData._settings = JSON.stringify(configProps);

        this.log('postData', postData);
        
        this.app.set('ajaxLoading', true);

        var ajax = new enyo.Ajax({
            url: '/admin/bibles/import',
            method: 'POST',
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

            this.app.confirm('This Bible has imported successfully.  Would you like to test it?', function(confirm) {
                var inev = {selections: [ { id: inResponse.bible.id, name: inResponse.bible.name } ] };

                this.log('test signal inEvent', inev);

                if(confirm) {
                    enyo.Signals.send('onBibleTest', inev);
                }
            }, this);

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
        this.set('formData', {type: 0});
        
        if(this.$.file.hasNode()) {
            this.$.file.hasNode().value = '';
        }
    },
    _resetBibleData: function() {

    },
    _populateImportInfo: function(type) {
        var cr = bootstrap.importers.find(element => element.type == type);
        this.$.ConfigView.set('view', null);

        if(cr) {
            this.set('importerData', enyo.clone(cr));
            
            if(BibleManager.Components.Forms.Import.Config[cr.kind]) {
                this.$.ConfigView.set('view', BibleManager.Components.Forms.Import.Config[cr.kind]).render();
            }
        }
        else {
            this.set('importerData', {desc: '(Please select an importer to see it\'s description.)'});
            this.$.type.set('selected', 0);
        }
    }, 
    formChanged: function() {
        this.log();
        this.set('fileValidated', false);
    }
});
