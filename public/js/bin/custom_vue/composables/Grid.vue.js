/**
 * Grid composable
 * 
 * Includes code to actually do a grid query (which v-data-table-server lacks)
 * 
 */ 

export function useGrid(data, props) {
    let grid = {
        // Settings (with defaults)

        url: Vue.ref(data.url || null),

        gridSortDefault: Vue.ref(data.gridSortDefault || {
            sidx: 'name',
            sord: 'ASC'
        }),

        gridData: Vue.ref(data.gridData || {
            page: 1,
            itemsPerPage: 25,
            sidx: 'name',
            sord: 'ASC',
            rows: 10,
            start: null,
        }),

        itemsPerPageOptions: Vue.ref(data.itemsPerPageOptions || [
            5, 10, 20, 25, 50, 100 // :todo page all option?, {value: -1, title: '$vuetify.dataFooter.itemsPerPageAll'}
        ]),

        // Internal properties
        gridRows: Vue.ref([]),
        totalRows: Vue.ref(0),
        loading: Vue.ref(false),
        gridSearchDate: Vue.ref(null),

        // Methods
        gridRefresh() {
            grid.loading.value = true;

            axios.request({
                url: grid.url.value,
                method: 'GET',
                params: grid.gridData.value
            })
            .then(function(response) {
                grid.gridRows.value = response.data.rows;
                grid.totalRows.value = response.data.records;
                grid.loading.value = false;
            }.bind(grid))   
            .catch(function(response) {
                // grid.gridRows = response.data.rows;
                // grid.totalRows = response.data.records;
                // grid.loading = false;
            }.bind(grid));
        },
        gridPaginate(options) { 
            grid.gridData.value.page = options.page || 1;
            grid.gridData.value.rows = options.itemsPerPage || 25;
            grid.gridData.value.start = grid.gridData.value.page * grid.gridData.value.rows - grid.gridData.value.rows;

            var sorting = (options.sortBy[0]) ? options.sortBy[0] : {
                key: grid.gridSortDefault.value.sidx,
                order: grid.gridSortDefault.value.sord,
            };

            grid.gridData.value.sidx = sorting.key;
            grid.gridData.value.sord = sorting.order;

            grid.gridRefresh();
        },
        gridReset() {
            grid.gridData.value.page = 1;
            grid.gridRefresh();
        },
        // Triggers grid to do search
        gridSearch() {
            grid.gridSearchDate.value = String(Date.now());
        }
    };

    // Set up watchers for the searchable fields
    if(data.searchFields) {
        var watch = [];

        for(const i in data.searchFields) {
            // console.log('grid Watch', data.searchFields[i]);

            // :todo allow for objects with field / default value pair

            // Auto init field in gridData
            if(!grid.gridData.value[ data.searchFields[i] ]) {
               grid.gridData.value[ data.searchFields[i] ] = ''; 
            }

            Vue.watch(
                () => grid.gridData.value[ data.searchFields[i] ], // Using a getter func to watch obj prop per docs
                () => grid.gridSearch()
            );
        }
    }

    return grid;
}