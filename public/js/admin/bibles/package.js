$( function() {
    $( "#grid" ).jqGrid({
        url: '/admin/bibles/grid',
        datatype: 'json',
        idPrefix: 'bible_',
        colModel: [
            {name: 'name', index: 'name', label: 'Name', width:'300'},
            {name: 'shortname', index: 'shortname', label: 'Short Name', width:'100'},
            {name: 'module', index: 'module', label: 'Module', width:'100'},
            {name: 'lang', index: 'lang', label: 'Language', width:'100'},
            {name: 'year', index: 'year', label: 'Year', width:'100'},

            {name: 'installed', index: 'installed', label: 'Installed', width:'70'},
            {name: 'enabled', index: 'enabled', label: 'Enabled', width:'70'},
            {name: 'rank', index: 'rank', label: 'Display Order', width:'100'},
            {name: 'actions', index: 'actions', label: '&nbsp', width:'100'},
        ],
        jsonReader: {
            repeatitems: false,
            id: 'id'
        },
//        pager: '#grid_footer',
        sortname: 'name',
        sortorder: 'asc',
        height: null,
        width: 'auto'
    }); //.navGrid('#grid_footer');
});

