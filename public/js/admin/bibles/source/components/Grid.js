enyo.kind({
    name: 'BibleManager.Components.Grid',

    components: [
        {name: 'Grid', tag: 'table'},
        {name: 'GridFooter'},
        {name: 'Legend', style: 'text-align: center; font-size: 0.8em', components: [
            {tag: 'span', content: '* Bible is officially supported.'},
            {tag: 'span', content: '&nbsp; &nbsp;', allowHtml: true},
            {tag: 'span', content: '** Bible is marked as for research purposes only'}
        ]}
    ],

    events: {
        onSelectionsChanged: ''
    },

    selectedIds: [],
    gridHandle: null,
    idPrefix: 'bible_',

    rendered: function() {
        this.inherited(arguments);

        if(this.$.Grid.hasNode() && this.gridHandle == null) {
            var pagerId = '#' + this.$.GridFooter.get('id');
            var hasFileWidth = (bootstrap.devToolsEnabled) ? '140' : '60';
            var hasFileAlign = (bootstrap.devToolsEnabled) ? 'left' : 'center';

            this.gridHandle = $(this.$.Grid.hasNode()).jqGrid({
                url: '/admin/bibles/grid',
                datatype: 'json',
                idPrefix: this.idPrefix,
                colModel: [
                    {name: 'name', index: 'name', label: 'Name', width:'200', editable: true},
                    {name: 'shortname', index: 'shortname', label: 'Short Name', width:'100', editable: true},
                    {name: 'module', index: 'module', label: 'Module', width:'150'},
                    {
                        name: 'has_module_file', 
                        index: 'has_module_file', 
                        label: 'Has File', 
                        width: hasFileWidth, 
                        title: false, 
                        sortable: false, 
                        align: hasFileAlign, 
                        formatter: enyo.bind(this, this._formatHasFile) // will be sortable when grid is using local data
                    }, 
                    {name: 'lang', index: 'lang', label: 'Language', width:'100'},
                    {name: 'year', index: 'year', label: 'Year', width:'100'},
                    {name: 'installed', index: 'installed', align: 'center', label: 'Installed', width:'80', title: false, formatter: enyo.bind(this, this._formatInstalled)},
                    {name: 'enabled', index: 'enabled', align: 'center', label: 'Enabled', width:'80', title: false, formatter: enyo.bind(this, this._formatEnabled)},
                    {name: 'official', index: 'official', align: 'center', label: 'Official *', width:'60', title: false, formatter: enyo.bind(this, this._formatSinpleBoolean)},
                    {name: 'research', index: 'research', align: 'center', label: 'Research **', width:'80', title: false, formatter: enyo.bind(this, this._formatResearch)},
                    {name: 'rank', index: 'rank', label: 'Sort Order', width:'100'},
                    {name: 'actions', index: 'actions', label: '&nbsp', width:'120', title: false, formatter: enyo.bind(this, this._formatActions)},
                    {name: 'id', index: 'id', hidden: true}
                ],
                jsonReader: {
                    repeatitems: false,
                    id: 'id'
                },
                pager: pagerId,
                sortname: 'rank',
                sortorder: 'asc',
                viewrecords: true,
                height: 'auto',
                width: 'auto',
                multiselect: true,
                rowNum: 15,
                rowList: [10, 15, 20, 30, 50, 100],
                onSelectRow: enyo.bind(this, this._selectRow),
                onSelectAll: enyo.bind(this, this._selectRow),
                loadComplete: enyo.bind(this, this._loadComplete),
                loadError: enyo.bind(this, this._loadError)
            });

            this.gridHandle.navGrid(pagerId, {search: false, edit: false, view: false, del: false, add: false, refresh: true, nav: {

            }}, {}, {}, {}, {}, {});

            $( ".button" ).button();
        }
    },

    refreshGrid: function() {
        this.gridHandle && this.gridHandle.trigger('reloadGrid');
    },
    _loadComplete: function() {
        this.doSelectionsChanged({length: 0});
    },
    _loadError: function(xhr, status, error) {
        console.log('loadError', xhr, status, error);

        // var response = JSON.parse(xhr.xhrResponse.body);
        this.app._errorHandler(xhr, xhr.responseJSON);
    },
    getRowByPk: function(pk) {
        var id = this.idPrefix + pk.toString();
        return this.getRowById(id);
    },
    getRowById: function(id) {
        return this.gridHandle ? this.gridHandle.jqGrid('getRowData', id) : null;
    },
    _selectRow: function(rowId, status, e) {
        this.doSelectionsChanged({length: this.gridHandle.getGridParam('selarrrow').length});
    },
    selectedIdsChanged: function(was, is) {
        this.log(is);
        // this.$.BulkActions.set('showing', (is.length) ? true : false);
    },
    __makeSignalUrl: function(signal, props) {
        var propsJson = JSON.stringify(props);
        var url = 'enyo.Signals.send("' + signal + '",' + propsJson + ')';
        return url;
    },
    __makeSignalLink: function(text, signal, props) {
        var url = this.__makeSignalUrl(signal, props);
        var html = "<a href='javascript:" + url + "'>" + text + "</a>";
        return html;
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
            var signal = (cellvalue == '1') ? 'onConfirmAction' : 'onBibleInstall';
            var props  = (cellvalue == '1') ? {id: options.rowId, action: 'uninstall'} : {id: options.rowId};
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

            if(bootstrap.devToolsEnabled) {
                fmt += " &nbsp; &nbsp;<a href='javascript:" + url + "'>Export Module File</a>";
            }
        }

        return fmt;
    },    
    _formatEnabled: function(cellvalue, options, rowObject) {
        var fmt = (cellvalue == '1') ? 'Yes' : 'No&nbsp;';
        options.colModel.classes = (cellvalue == '1') ? 'on' : 'off';

        if(rowObject.installed == '1') {        
            var text = (cellvalue == '1') ? 'Disable' : 'Enable';
            var action = (cellvalue == '1') ? 'disable' : 'enable';
            var signal = (cellvalue == '1') ? 'onBibleDisable' : 'onBibleEnable';
            var props = {id: options.rowId, action: action};
            var url = this.__makeSignalUrl('onConfirmAction', props);
            fmt += " &nbsp; &nbsp;<a href='javascript:" + url + "'>" + text + "</a>";
        }

        return fmt;
    },
     _formatResearch: function(cellvalue, options, rowObject) {
        var fmt = (cellvalue == '1') ? 'Yes' : 'No&nbsp;';
        options.colModel.classes = (cellvalue == '1') ? 'research on' : 'research off';
        return fmt;
        
        var action = (cellvalue == '1') ? 'Unflag' : 'Flag';
        var signal = (cellvalue == '1') ? 'onConfirmAction' : 'onConfirmAction';
        // var props  = (cellvalue == '1') ? {id: options.rowId, action: 'unresearch', displayAction: 'unflag'} : {id: options.rowId, action: 'research', displayAction: 'flag'};

        var props = {
            id: options.rowId,
            action: (cellvalue == '1') ? 'unresearch' : 'research',
            displayAction: (cellvalue == '1') ? 'unflag' : 'flag',
            title: (cellvalue == '1') ? 'Unflag as "For Research Only"' : 'Flag as "For Research Only"'
        };

        var url = this.__makeSignalUrl(signal, props);
        fmt += " &nbsp; &nbsp;<a href='javascript:" + url + "'>" + action + "</a>";
        return fmt;
    },     
    _formatActions: function(cellvalue, options, rowObject) {
        var props = {id: options.rowId};
        var html = '';

        // if(rowObject.has_module_file == 0 && rowObject.official == 0) {
        //     html += this.__makeSignalLink('Delete', 'onDelete', props);
        //     html += ' &nbsp; ';
        // }

        html += this.__makeSignalLink('View Description', 'onViewDescription', props);
        html += ' &nbsp; ';
        html += this.__makeSignalLink('Edit', 'onEdit', props);
        return html;
    },
    getSelectionsWithName: function() {
        var selArr = enyo.clone(this.gridHandle.getGridParam('selarrrow'));
        var selections = [];

        for(i in selArr) {
            var data = this.getRowById(selArr[i]);

            selections.push({
                id: data.id,
                name: data.name
            });
        }

        return selections;
    }
});