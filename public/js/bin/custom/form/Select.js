enyo.kind({
    name: 'AICWEBTECH.Enyo.Select',
    kind: 'enyo.Select',
    autoSetSelectedByValue: true,
    filter: '',

    valueChanged: function() {
        // this.inherited(arguments);

        if(this.autoSetSelectedByValue) {
            this.selectByValue();
        }
    },
    selectByValue: function() {
        var value = this.value;
        var idx = this.getClientControls().findIndex( function(item) {
            return item.get('value') == value;
        });

        if(idx == -1) {
            if(value !== null) {
                this.set('value', null);
            }

            this.setSelected(0);
            return;
        }

        this.setSelected(idx);
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

        this.getClientControls().forEach(function(item) {
            if(item.get('content').toLowerCase().indexOf(filter) != -1) {
                item.set('showing', true)
            }
            else {
                item.set('showing', false);
            }
        }, this);

        // $(this.hasNode()).trigger('open');
        this.setStyle('height', '100px');
        this.setAttribute('size', 3);
        // this.blur();
    },
    resetFilter: function() {
        this.getClientControls().forEach(function(item) {
            item.set('showing', true)
        }, this);

        this.setAttribute('size', 1);
    }
});
