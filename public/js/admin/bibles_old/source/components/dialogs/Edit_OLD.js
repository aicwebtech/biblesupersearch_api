enyo.kind({
    name: 'BibleManager.Components.Dialogs.Edit',
    kind: 'AICWEBTECH.Enyo.jQuery.Dialog',
    pk: null,
    formData: {},

    components: [
        {tag: 'table', components: [
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Name: '},
                {tag: 'td', classes: 'form_label right', components: [
                    {kind: 'enyo.Input', name: 'name'}
                ]}
            ]},
            {tag: 'tr', components: [
                {tag: 'td', classes: 'form_label right', content: 'Short Name: '},
                {tag: 'td', classes: 'form_label right', components: [
                    {kind: 'enyo.Input', name: 'shortname'}
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
        {from: 'formData.year', to: '$.year.value', oneWay: false, transform: function(value, dir) {
            this.log('year', value, dir);
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

        this.setDialogOptions({
            height: 'auto',
            width: 'auto',
            modal: true,
            autoOpen: false,
            buttons: [
                {
                    text: 'Save',
                    icon: 'ui-icon-check',
                    click: enyo.bind(this, this.save)
                },
                {
                    text: 'Cancel',
                    icon: 'ui-icon-cancel',
                    click: enyo.bind(this, this.close)
                },
            ]
        });
    },

    openLoad: function() {
        this.inherited(arguments);
        this.app.set('ajaxLoading', true);
        this.log('pk', this.pk);

        var ajax = new enyo.Ajax({
            url: baseAppUrl + '/admin/bibles/' + this.pk,
            method: 'GET',
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
            this.set('title', 'Editing: ' + inResponse.Bible.name);
        });

        ajax.error(this, function(inSender, inResponse) {
            console.log('ERROR', inSender, inResponse);
            this.app.set('ajaxLoading', false);
            var msg = 'An Error has occurred';
            this.app.alert(msg);
            this.close();
        });

        ajax.go();
    },

    save: function() {
        var postData = enyo.clone(this.formData);
        postData._token = laravelCsrfToken;

        this.log(postData);

        var ajax = new enyo.Ajax({
            url: baseAppUrl + '/admin/bibles/' + this.pk,
            method: 'PUT',
            postBody: postData
        });

        ajax.response(this, function(inSender, inResponse) {
            this.app.set('ajaxLoading', false);

            if(!inResponse.success) {
                return this._errorHandler(inSender, inResponse)
            }

            this.app.refreshGrid();
            this.close();
        });

        ajax.error(this, function(inSender, inResponse) {
            console.log('ERROR', inSender, inResponse);
            this.app.set('ajaxLoading', false);
            var response = JSON.parse(inSender.xhrResponse.body);
            this._errorHandler(inSender, response);
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
    }
});
