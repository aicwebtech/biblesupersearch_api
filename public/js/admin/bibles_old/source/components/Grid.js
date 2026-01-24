enyo.kind({
    name: 'BibleManager.Components.Grid',

    components: [
        {name: 'Grid', tag: 'table', style: 'width: 100%; max-width: 1024px'},
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
    colModel: null,
    searchDialogOptions: {multipleSearch: true, closeOnEscape: true, closeAfterSearch: false, closeAfterReset: true, multipleGroup:false },

    rendered: function() {
        this.inherited(arguments);

        if(this.$.Grid.hasNode() && this.gridHandle == null) {
            var pagerId = '#' + this.$.GridFooter.get('id');
            // var hasFileWidth = (bootstrap.devToolsEnabled) ? '140' : '60';
            var hasFileWidth = (bootstrap.devToolsEnabled) ? '70' : '60';
            var hasFileAlign = (bootstrap.devToolsEnabled) ? 'left' : 'center';
            var boolNullOptions = {value: '1:Yes;0:No;_no_rest_:No Restriction', sopt: ['eq']};
            var intOptions = {sopt: ['eq','ne','lt','le','gt','ge','bw','bn','in','ni']};
            var strOptions = {sopt: ['eq','ne','bw','bn','ew','en','cn','nc']};
            var vpWidth = Math.min(document.documentElement.clientWidth || 9999, window.innerWidth || 9999);
            var reduceWidth = (vpWidth < 1530) ? true : false;

            var width = (reduceWidth) ? vpWidth - 135 : '1400';
            width = Math.max(width, 900);

            this.colModel = [
                {
                    name: 'name', 
                    index: 'name', 
                    label: 'Name', 
                    width:'200', 
                    editable: true, 
                    searchoptions: strOptions,
                },
                {
                    name: 'shortname', 
                    index: 'shortname', 
                    label: 'Short Name', 
                    width:'100', 
                    editable: true, 
                    hidden: reduceWidth,
                    searchoptions: strOptions
                },
                {
                    name: 'module', 
                    index: 'module', 
                    label: 'Module', 
                    width:'100',
                    searchoptions: strOptions,
                    formatter: enyo.bind(this, this._formatName)
                },
                {
                    name: 'has_module_file', 
                    index: 'has_module_file', 
                    label: 'Has File', 
                    width: hasFileWidth, 
                    title: false, 
                    sortable: false, 
                    align: hasFileAlign, 
                    // search: false,
                    stype: 'select',
                    searchoptions: boolNullOptions,
                    formatter: enyo.bind(this, this._formatHasFile) // will be sortable when grid is using local data
                }, 
                {
                    name: 'lang', 
                    index: 'lang', 
                    label: 'Language', 
                    width: '80',
                    stype: 'select',

                    searchoptions: {
                        dataUrl: '/admin/bibles/languages',
                        sopt: ['eq','ne'],
                        buildSelect: enyo.bind(this, this._formatLanguagesOptions)
                    }
                },                
                {
                    name: 'copy', 
                    index: 'copy', 
                    label: 'Copyright', 
                    width: '100',
                    stype: 'select',

                    searchoptions: {
                        dataUrl: '/admin/bibles/copyrights',
                        sopt: ['eq','ne'],
                        buildSelect: enyo.bind(this, this._formatCopyrightsOptions)
                    }
                },
                {
                    name: 'year', 
                    index: 'year', 
                    label: 'Year', 
                    width: '60', 
                    hidden: reduceWidth,
                    searchoptions: strOptions
                },
                {
                    name: 'installed', 
                    index: 'installed', 
                    align: 'center', 
                    label: 'Installed', 
                    width:'80', 
                    title: false, 
                    stype: 'select',
                    searchoptions: boolNullOptions,
                    formatter: enyo.bind(this, this._formatInstalled)
                },
                {
                    name: 'enabled', 
                    index: 'enabled', 
                    align: 'center', 
                    label: 'Enabled', 
                    width:'80', 
                    title: false, 
                    stype: 'select',
                    searchoptions: boolNullOptions,
                    formatter: enyo.bind(this, this._formatEnabled)
                },
                {
                    name: 'official', 
                    index: 'official', 
                    align: 'center', 
                    label: 'Official *', 
                    width:'60', 
                    title: false, 
                    stype: 'select',
                    searchoptions: boolNullOptions,
                    formatter: enyo.bind(this, this._formatSinpleBoolean)
                },
                {
                    name: 'research', 
                    index: 'research', 
                    align: 'center', 
                    label: 'Research **', 
                    width:'80', 
                    title: false,
                    stype: 'select',
                    searchoptions: boolNullOptions,
                    formatter: enyo.bind(this, this._formatResearch)
                },
                {
                    name: 'updated_at', 
                    index: 'updated_at', 
                    align: 'center', 
                    label: 'Updated', 
                    width:'100', 
                    title: false, 
                    search: false,
                    formatter: 'date', 
                    formatoptions: {srcformat: 'Y-m-d H:i:s', newformat: 'd M Y, H:i'}
                },
                {
                    name: 'rank', 
                    index: 'rank', 
                    label: 'Rank', 
                    width: '60',
                    searchoptions: intOptions
                },
                {
                    name: 'actions', 
                    index: 'actions', 
                    label: '&nbsp', 
                    width: '80', 
                    title: false,
                    formatter: enyo.bind(this, this._formatActions), 
                    search: false 
                },
                {name: 'id', index: 'id', hidden: true, hiddlg: true}
            ];

            this.gridHandle = $(this.$.Grid.hasNode()).jqGrid({
                url: baseAppUrl + '/admin/bibles/grid',
                datatype: 'json',
                idPrefix: this.idPrefix,
                colModel: this.colModel,
                jsonReader: {
                    repeatitems: false,
                    id: 'id'
                },
                pager: pagerId,
                sortname: 'rank',
                sortorder: 'asc',
                viewrecords: true,
                height: 'auto',
                width: width,
                multiselect: true,
                rowNum: 15,
                rowList: [10, 15, 20, 30, 50, 100],
                onSelectRow: enyo.bind(this, this._selectRow),
                onSelectAll: enyo.bind(this, this._selectRow),
                loadComplete: enyo.bind(this, this._loadComplete),
                loadError: enyo.bind(this, this._loadError)
            });

            this.gridHandle.navGrid(pagerId, {search: true, edit: false, view: false, del: false, add: false, refresh: true, nav: {

            }}, {}, {}, {}, this.searchDialogOptions, {});

            $( ".button" ).button();
        }
    },

    refreshGrid: function() {
        this.gridHandle && this.gridHandle.trigger('reloadGrid');
    },
    _loadComplete: function() {
        this.doSelectionsChanged({length: 0});

        if(this.gridHandle) {
            var userData = this.gridHandle.getGridParam('userData');
        }
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
    __makeHtmlLink: function(text, url, target) {
        var html = "<a href='" + baseAppUrl + url + "' target='" + target + "'>" + text + "</a>";
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
                fmt += " &nbsp; &nbsp;<a href='javascript:" + url + "' title='Export Module File'>Export</a>";
            }
        }

        return fmt;
    },    
    _formatEnabled: function(cellvalue, options, rowObject) {
        var fmt = (cellvalue == '1') ? 'Yes' : 'No&nbsp;';
        options.colModel.classes = (cellvalue == '1') ? 'on' : 'off';
        
        if(rowObject.installed == '1') {     
            if(rowObject.needs_update == '1') {
                var action = 'update';
                var text = 'Update';
                var signal = 'onBibleUpdate';
                options.colModel.classes = 'alert';
            }
            else {
                var text = (cellvalue == '1') ? 'Disable' : 'Enable';
                var action = (cellvalue == '1') ? 'disable' : 'enable';
                var signal = (cellvalue == '1') ? 'onBibleDisable' : 'onBibleEnable';
            }

            var props = {id: options.rowId, action: action};
            var url = this.__makeSignalUrl('onConfirmAction', props);
            fmt += " &nbsp; &nbsp;<a href='javascript:" + url + "'>" + text + "</a>";
        }

        return fmt;
    },
     _formatResearch: function(cellvalue, options, rowObject) {
        var fmt = (cellvalue == '1') ? 'Yes' : 'No&nbsp;';
        options.colModel.classes = (cellvalue == '1') ? 'research on' : 'research off';
        // return fmt;
        
        var action = (cellvalue == '1') ? 'Unmark' : 'Mark';
        var signal = (cellvalue == '1') ? 'onConfirmAction' : 'onConfirmAction';
        // var props  = (cellvalue == '1') ? {id: options.rowId, action: 'unresearch', displayAction: 'unflag'} : {id: options.rowId, action: 'research', displayAction: 'flag'};

        var props = {
            id: options.rowId,
            action: (cellvalue == '1') ? 'unresearch' : 'research',
            displayAction: (cellvalue == '1') ? 'unmark this Bible as "research":' : 'mark this Bible as "research":',
            title: (cellvalue == '1') ? 'Unmark as "For Research Only"' : 'Mark as "For Research Only"'
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

        // html += this.__makeSignalLink('Info', 'onViewDescription', props);
        // html += ' &nbsp; ';
        html += this.__makeSignalLink('Quick Edit', 'onEdit', props);
        html += ' &nbsp; ';
        // html += this.__makeHtmlLink('Edit', '/admin/bibles/' + rowObject.module + '/edit', '_NEW');
        html += this.__makeHtmlLink('Edit', '/admin/bibles/edit#' + options.rowId, '_NEW');
        return html;
    },    
    _formatName: function(cellvalue, options, rowObject) {
        var props = {id: options.rowId};

        return this.__makeSignalLink(cellvalue, 'onViewDescription', props);
    },
    _formatLanguagesOptions: function(response) {
        this.log(response);

        if(typeof response == 'string') {
            response = JSON.parse(response);
        }

        var html = '<select>';

        response.languages.forEach(function(item) {
            if(item.code == null) {
                html += "<option value='null'>(None)</option>";
            }
            else {
                html += "<option value='" + item.code + "'>" + item.name + "</option>";
            }
        });

        html += '</select>';

        return html;
    },    
    _formatCopyrightsOptions: function(response) {
        this.log(response);

        if(typeof response == 'string') {
            response = JSON.parse(response);
        }

        var html = '<select>';

        response.copyrights.forEach(function(item) {
            if(item.id == null) {
                html += "<option value='null'>(None)</option>";
            }
            else {
                html += "<option value='" + item.id + "'>" + item.name + "</option>";
            }
        });

        html += '</select>';

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
    },
    openSearchDialog: function() {
        this.gridHandle && this.gridHandle.jqGrid('searchGrid', this.searchDialogOptions);
    }
});