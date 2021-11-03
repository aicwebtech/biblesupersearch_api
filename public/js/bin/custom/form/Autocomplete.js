enyo.kind({
    name: 'AICWEBTECH.Enyo.Autocomplete',
    autoSetSelectedByValue: true,
    filter: '',
    classes: 'aicwebtech_autocomplete',
    debugBindings: true,
    value: null,

    components: [
        {
            kind: 'enyo.Input', 
            name: 'Input', 
            onfocus: 'handleFocus', 
            // onblur: 'handleBlur', 
            classes: 'aicwebtech_autocomplete_input', 
            onkeydown: 'handleInputChange',
            ontap: 'handleInputTap'
        }, 
        {name: 'Options', classes: 'aicwebtech_autocomplete_options', showing: false}
    ],

    handlers: {
        onOptionTap: 'handleOptionTap',
        onblur: 'handleBlur'
    },

    bindings: [
        // {from: '$.Input.value', to: 'filter', oneWay: true, transform: function(value, dir) {
        //     this.debugBindings && this.log('filter', value, dir);
        //     return value || '';
        // }},
    ],

    valueChanged: function() {
        // this.inherited(arguments);

        if(this.autoSetSelectedByValue) {
            this.selectByValue();
        }
    },
    optionsChanged: function(was, is) {
        this.$.Options.destroyClientControls();

        is.forEach(function(opt) {
            this.$.Options.createComponent({
                kind: 'AICWEBTECH.Enyo.Autocomplete_Option',
                content: opt.label,
                value: opt.value,
            });
        }, this);

        this.$.Options.render();
    },
    selectByValue: function() {
        var value = this.value;
        var opt = this.$.Options.getClientControls().find( function(item) {
            return item.get('value') == value;
        });

        if(opt) {
            this.$.Input.set('value', opt.get('content'));
        }
        else {
            this.value = null;
            this.$.Input.set('value', '');
        }

        this.$.Options.set('showing', false);
    }, 
    filterChanged: function(was, is) {
        this.log(was, is);
        this.filterOptions(is);
    },
    filterOptions: function(filter) {
        if(!filter || filter == '') {
            return this.resetFilter();
        }
        
        filter = filter.toLowerCase();

        this.$.Options.getClientControls().forEach(function(item) {
            if(item.get('content').toLowerCase().indexOf(filter) != -1) {
                item.set('showing', true)
            }
            else {
                item.set('showing', false);
            }
        }, this);
    },
    resetFilter: function() {
        this.$.Options.getClientControls().forEach(function(item) {
            item.set('showing', true)
        }, this);
    },
    handleFocus: function() {
        this.set('filter', '');
        // this.$.Options.set('showing', true);
    },
    handleBlur: function() {
        // this.$.Options.set('showing', false);
    },
    handleOptionTap: function(inSender, inEvent) {
        this.set('value', inEvent.value);
    },
    handleInputChange: function() {
        var val = this.$.Input.get('value');
        this.set('filter', val);
    },
    handleInputTap: function() {
        this.$.Options.set('showing', !this.$.Options.get('showing'));
    }
});

enyo.kind({
    name: 'AICWEBTECH.Enyo.Autocomplete_Option',
    value: null,
    classes: 'aicwebtech_autocomplete_options_option',
    handlers: {
        ontap: 'handleOptionTap',
    },

    ontap: 'handleOptionTap',

    events: {
        onOptionTap: ''
    },

    handleOptionTap: function() {
        this.log();
        this.doOptionTap({value: this.get('value')});
    }
});