enyo.kind({
    name: 'BibleManager.View',
    gridHandle: null,

    components: [
        {name: 'BulkActionsContainer', classes: 'buik_actions_container', components: [
            {name: 'BulkActions', style: 'float: left', showing: false, components: [
                {tag: 'span', content: 'With Selected: '},
                {tag: 'button', classes: 'button bulk', content: 'Install'},
                {tag: 'button', classes: 'button bulk', content: 'Uninstall'},
                {tag: 'button', classes: 'button bulk', content: 'Enable'},
                {tag: 'button', classes: 'button bulk', content: 'Disable'},
                {tag: 'button', classes: 'button bulk', content: 'Export Module File'},
            ]},
            {name: 'SortOptions', style: 'float: right', components: [
                {tag: 'button', classes: 'button bulk', content: 'Auto Sort'},
            ]},
            {style: 'clear: both'},
        ]},
        {name: 'Grid', tag: 'table'},
        {name: 'GridFooter'},
        {kind: 'enyo.Signals', onBibleInstall: 'bibleInstall', onBibleUninstall: 'bibleUninstall'}
    ],

    selectedIds: [],

    rendered: function() {
        this.inherited(arguments);

        if(this.$.Grid.hasNode() && this.gridHandle == null) {
            var pagerId = '#' + this.$.GridFooter.get('id');

            this.gridHandle = $(this.$.Grid.hasNode()).jqGrid({
                url: '/admin/bibles/grid',
                datatype: 'json',
                idPrefix: 'bible_',
                colModel: [
                    {name: 'name', index: 'name', label: 'Name', width:'300'},
                    {name: 'shortname', index: 'shortname', label: 'Short Name', width:'100'},
                    {name: 'module', index: 'module', label: 'Module', width:'100'},
                    {name: 'has_module_file', index: 'has_module_file', label: 'Has File', width:'140', title: false, sortable: false, formatter: enyo.bind(this, this._formatHasFile)}, // will be sortable when grid is using local data
                    {name: 'lang', index: 'lang', label: 'Language', width:'100'},
                    {name: 'year', index: 'year', label: 'Year', width:'100'},

                    {name: 'installed', index: 'installed', label: 'Installed', width:'80', title: false, formatter: enyo.bind(this, this._formatInstalled)},
                    {name: 'enabled', index: 'enabled', label: 'Enabled', width:'80', title: false, formatter: enyo.bind(this, this._formatEnabled)},
                    {name: 'official', index: 'official', label: 'Official', width:'60', title: false, formatter: enyo.bind(this, this._formatSinpleBoolean)},
                    {name: 'rank', index: 'rank', label: 'Display Order', width:'100'},
                    {name: 'actions', index: 'actions', label: '&nbsp', width:'100'},
                ],
                jsonReader: {
                    repeatitems: false,
                    id: 'id'
                },
                pager: pagerId,
                sortname: 'name',
                sortorder: 'asc',
                viewrecords: true,
                height: 'auto',
                width: 'auto',
                multiselect: true,
                rowNum: 15,
                rowList: [10, 15, 20, 30],
                onSelectRow: enyo.bind(this, this._selectRow),
                onSelectAll: enyo.bind(this, this._selectRow)
            });

            this.gridHandle.navGrid(pagerId, {search: false, edit: false, view: false, del: false, add: false, refresh: true, nav: {

            }}, {}, {}, {}, {}, {});

            $( ".button" ).button();
        }
    },

    _selectRow: function(rowId, status, e) {
        this.log('rowId', rowId);
        this.log('status', status);
        this.log('e', e);
        this.set('selectedIds', enyo.clone(this.gridHandle.getGridParam('selarrrow')));
    },

    selectedIdsChanged: function(was, is) {
        this.log(is);
        this.$.BulkActions.set('showing', (is.length) ? true : false);
    },

    __makeSignalUrl: function(signal, props) {
        var propsJson = JSON.stringify(props);
        var url = 'enyo.Signals.send("' + signal + '",' + propsJson + ')';
        return url;
    },

    __setCellColor: function(rowId, cellIndex, color) {
        // this.gridHandle && this.gridHandle.
    },

    _formatSinpleBoolean: function(cellvalue, options, rowObject) {
        return (cellvalue == '1') ? 'Yes' : 'No&nbsp;';
    },
    _formatInstalled: function(cellvalue, options, rowObject) {
        var fmt = (cellvalue == '1') ? 'Yes' : 'No&nbsp;';
        options.colModel.classes = (cellvalue == '1') ? 'on' : 'off';
        
        if(cellvalue == '1' || rowObject.has_module_file == '1') {        
            var action = (cellvalue == '1') ? 'Uninstall' : 'Install';
            var signal = (cellvalue == '1') ? 'onBibleUninstall' : 'onBibleInstall';

            var props = {id: options.rowId};
            var url = this.__makeSignalUrl(signal, props);
            fmt += " &nbsp; &nbsp;<a href='javascript:" + url + "'>" + action + "</a>";
        }
        return fmt;
    },     
    _formatHasFile: function(cellvalue, options, rowObject) {
        var fmt = (cellvalue == '1') ? 'Yes' : 'No&nbsp;';
        options.colModel.classes = (cellvalue == '1') ? '' : 'alert';
        
        if(rowObject.installed == '1') {        
            var props = {id: options.rowId};
            var url = this.__makeSignalUrl('onBibleExport', props);
            fmt += " &nbsp; &nbsp;<a href='javascript:" + url + "'>Export Module File</a>";
        }

        return fmt;
    },    
    _formatEnabled: function(cellvalue, options, rowObject) {
        console.log('cellvalue', cellvalue);
        console.log('options', options);
        console.log('rowObject', rowObject);

        var fmt = (cellvalue == '1') ? 'Yes' : 'No&nbsp;';
        options.colModel.classes = (cellvalue == '1') ? 'on' : 'off';

        if(rowObject.installed == '1') {        
            var action = (cellvalue == '1') ? 'Disable' : 'Enable';
            var signal = (cellvalue == '1') ? 'onBibleDisable' : 'onBibleEnable';
            var props = {id: options.rowId};
            var url = this.__makeSignalUrl(signal, props);
            fmt += " &nbsp; &nbsp;<a href='javascript:" + url + "'>" + action + "</a>";
        }

        return fmt;
    },

    bibleInstall: function(inSender, inEvent) {
        this.log(inEvent);
    },    
    bibleUninstall: function(inSender, inEvent) {
        this.log(inEvent);
    }
});
