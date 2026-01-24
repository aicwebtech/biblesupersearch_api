enyo.kind({
    name: 'BibleManager.Components.Forms.EditBasic',

    pk: null,
    pkPending: null,

    formData: {},
    standalone: false,
    quick: false,

    dirty: false,
    initialLoad: false,

    debugBindings: false,
    classes: 'dialog_form edit_form edit_form_basic',

    events: {
        onClose: '',
        onOpen: ''
    },

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
            this.debugBindings && this.log('name', value, dir);
            return value || '';
        }},
        {from: 'formData.shortname', to: '$.shortname.value', oneWay: false, transform: function(value, dir) {
            this.debugBindings &&this.log('shortname', value, dir);
            return value || '';
        }},
        {from: 'formData.year', to: '$.year.value', oneWay: false, transform: function(value, dir) {
            this.debugBindings &&this.log('year', value, dir);
            return value || '';
        }},
        {from: 'formData.rank', to: '$.rank.value', oneWay: false, transform: function(value, dir) {
            this.debugBindings &&this.log('rank', value, dir);
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

    openByPk: function(pk) {
        if(this.dirty && this.initialLoad) {
            this.app.alert('Please save your changes or cancel first!');
            this.set('pkPending', pk);
            return;
        }

        this.set('pk', pk);
        this.set('pkPending', null);
        this.openLoad();
    },

    openByPendingPk: function() {
        if(this.hasPendingPk()) {
            this.openByPk(this.get('pkPending'));
        }
    },

    hasPendingPk: function() {
        return !!this.get('pkPending');
    },

    openLoad: function() {
        // this.inherited(arguments);
        this.app.set('ajaxLoading', true);
        this.pk = this.pk || null;

        this.log('pk', this.pk);

        this.waterfall('onViewForm', {pk: this.pk});

        var q = this.quick ? 'Quick ' : '';

        if(!this.pk) {
            this.open();
            this.set('formData', {});
            this.parent.set('title', q + 'Editing: <new Bible>');
            return;
        }

        var ajax = new enyo.Ajax({
            url: baseAppUrl + '/admin/bibles/' + this.pk,
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
            this.parent.set('title', q + 'Editing: ' + inResponse.Bible.name);
            this.initialLoad = true;
            this.dirty = false;
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
        var postData = enyo.clone(this.formData);
        postData._token = laravelCsrfToken;

        this.log(postData);

        var ajax = new enyo.Ajax({
            url: baseAppUrl + '/admin/bibles/' + this.pk,
            method: 'PUT',
            postBody: postData,
            headers: this.app.defaultAjaxHeaders
        });

        ajax.response(this, function(inSender, inResponse) {
            this.app.set('ajaxLoading', false);

            if(!inResponse.success) {
                return this.app._errorHandler(inSender, inResponse)
            } else {
                this.app.alert('Bible information saved!');
                this.dirty = false;
            }

            this.app.refreshGrid && this.app.refreshGrid();
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

    close: function() {
        if(this.standalone) {
            // ??
        } else {
            this.doClose();
        }
    },
    open: function() {
        if(this.standalone) {
            // ??
        } else {
            this.doOpen();
        }
    },
    _errorHandler: function(inSender, inResponse) {
        var msg = inResponse.message || 'An Error has occurred';

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
    handleBindingsGeneric: function(value, dir) {
        if(dir == 2) {
            this.dirty = true;
        }

        return value;
    }
});
