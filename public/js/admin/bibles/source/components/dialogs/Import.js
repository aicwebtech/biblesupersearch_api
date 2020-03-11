enyo.kind({
    name: 'BibleManager.Components.Dialogs.Import',
    kind: 'AICWEBTECH.Enyo.jQuery.Dialog',
    pk: null,
    fileValidated: null,
    formData: {},
    importerData: {},

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
                    {kind: 'enyo.Input', type: 'file', name: 'file'},
                    {tag: 'span', classes: 'required', content: '*'}
                ]},
            ]}
        ]},

        {name: 'FormContainer', kind: 'enyo.ViewController'}
    ],

    bindings: [
        {from: 'formData.type', to: '$.type.value', oneWay: false, transform: function(value, dir) {
            this.log('type', value, dir);
            value = (value && value != '0') ? value : null;
            this._populateImportInfo(value);
            return value;
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
                content: item.name + ' (' + item.ext + ')'
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

        if(is) {
            dialogOptions.buttons.unshift({
                text: 'Import File',
                icon: 'ui-icon-check',
                click: enyo.bind(this, this.save)
            });

            var title = 'Bible Importer: Import File';
        }
        else {
            dialogOptions.buttons.unshift({
                text: 'Check File',
                icon: 'ui-icon-check',
                click: enyo.bind(this, this.validate)
            });
            
            var title = 'Bible Importer: Select File';
        }

        this.setDialogOptions(dialogOptions);
        this.set('title', title);
    },

    validate: function() {
        // this.set('fileValidated', true);
        var postData = enyo.clone(this.formData);
        var errors = [];

        // this.log('files', this.$.file.hasNode().files);

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
        file && formData.append('file', file, file.name); // Rest of request works when file append is commented out!

        // Possibly an issue with tmp dir on backend??

        formData.append('importer', postData.type);
        formData.append('_token', laravelCsrfToken);

        // for(var pair of formData.entries()) {
        //    this.log(pair[0] , pair[1]); 
        // }

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

            this.close();
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
        this.set('fileValidated', false);
        var postData = enyo.clone(this.formData);
        postData._token = laravelCsrfToken;

        // this._saveHelper('import', postData);
    },

    _saveHelper: function(action, postData) {

        this.log(postData);

        var ajax = new enyo.Ajax({
            url: '/admin/bibles/' + action,
            method: 'POST',
            contentType: 'multipart/form-data',
            postBody: postData
        });

        ajax.response(this, function(inSender, inResponse) {
            this.app.set('ajaxLoading', false);

            if(!inResponse.success) {
                return this.app._errorHandler(inSender, inResponse)
            }

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

        if(cr) {
            this.set('importerData', enyo.clone(cr));
        }
        else {
            this.set('importerData', {desc: '(Please select an importer.)'});
        }
    }
});
