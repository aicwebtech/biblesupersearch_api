enyo.kind({
    name: 'AICWEBTECH.Enyo.Select',
    kind: 'enyo.Select',
    autoSetSelectedByValue: true,

    create: function() {
        this.inherited(arguments);

        if(typeof this.setSelectedByValue != 'function') {
            this.setSelectedByValue = enyo.bind(this, function(value) {

            })
        }
    }, 
    valueChanged: function() {
        this.inherited(arguments);

        if(this.autoSetSelectedByValue) {
            this.selectByValue();
        }
    },
    selectByValue: function() {
        var value = this.value;
        var idx = this.getClientControls().findIndex( function(item) {
            return item.get('value') == value;
        });

        this.setSelected(idx);
    }
});
