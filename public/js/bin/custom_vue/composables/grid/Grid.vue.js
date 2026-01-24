/**
 * Grid composable
 * 
 * Includes code to actually do a grid query (which v-data-table-server lacks)
 * 
 */ 

export const gridTemplateProps = `
                :items="gridRows"
                :items-length='totalRows'
                :items-per-page="gridData.rows_per_page"
                :search='gridSearchDate'
                :items-per-page-options='itemsPerPageOptions'
                :page='gridData.page'
                @update:options="gridPaginate"

                show-current-page
                :loading='loading ? "primary-darken-1" : false'
                fixed-header
                single-select
                hover
                density="compact"
                color="#333333"
                :cell-props='gridCellProps'
                :header-props='gridCellProps'
                `;

export function useGrid(data, props) {
    var gridDataDefaults = {
        page: 1,
        rows_per_page: 25,
        sidx: 'name',
        sord: 'ASC',
        start: null,
    };

    var gridDataProps = data.gridData || {};

    var gridData = {...gridDataDefaults, ...gridDataProps};

    gridData.rows = gridData.rows_per_page; // legacy, depricated

    let grid = {
        // Settings (with defaults)

        url: Vue.ref(data.url || null),

        gridSortDefault: Vue.ref(data.gridSortDefault || {
            sidx: gridData.sidx,
            sord: gridData.sord
        }),

        gridData: Vue.ref(gridData),

        itemsPerPageOptions: Vue.ref(data.itemsPerPageOptions || [
            5, 10, 15, 20, 25, 50, 100 // :todo page all option?, {value: -1, title: '$vuetify.dataFooter.itemsPerPageAll'}
        ]),


        // Internal properties
        gridCellProps: Vue.ref({
            class: 'pa-1'
        }),
        gridRows: Vue.ref([]),
        totalRows: Vue.ref(0),
        loading: Vue.ref(false),
        gridSearchDate: Vue.ref(null),

        // Non-reactive properties
        gridSearchDefaults: {},
        gridPreventSearch: false,
        gridSearchTimeout: null,

        // Methods
        gridRefresh() {
            if(grid.loading.value) {
                // return;
            }
            
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
            .catch(function(error) {
                if(error.response.data.message) {
                    alert(error.response.data.message);
                } else {
                    alert('An unknown error has occurred');
                }
                
                grid.gridRows.value = [];
                grid.totalRows.value = 0;
                grid.loading.value = false;
            }.bind(grid));
        },
        gridPaginate(options) { 
            grid.gridData.value.page = options.page || 1;
            grid.gridData.value.rows_per_page = options.itemsPerPage || 25;
            grid.gridData.value.rows = options.itemsPerPage || 25;  // 'rows' is rows_per_page alias (legacy)
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
            grid.gridResetData();
            grid.gridRefresh();
        },
        gridResetData() {
            grid.gridData.value.page = 1;
            grid.totalRows.value = 0;
            grid.gridRows.value = [];
        },
        gridResetRows() {
            grid.gridRows.value = [];
            grid.gridRefresh();
        },
        gridResetSearch() {
            grid.gridClearSearch();
            grid.gridReset();
        },
        // Triggers grid to do search
        gridSearch() {
            if(!grid.gridPreventSearch) {
                clearTimeout(grid.gridSearchTimeout);

                grid.gridSearchTimeout = setTimeout(() => {
                    grid.gridSearchDate.value = String(Date.now());
                }, 300);
            }
        },
        gridClearSearch() {
            grid.gridPreventSearch = true;

            for(const i in grid.gridSearchDefaults) {
                grid.gridData.value[i] = grid.gridSearchDefaults[i];
            }
            
            grid.gridPreventSearch = false;
        }
    };

    // Set up watchers for the searchable fields
    if(data.searchFields) {
        for(const i in data.searchFields) {
            var f = data.searchFields[i];

            // Auto init field in gridData
            if(typeof grid.gridData.value[ data.searchFields[i] ] == 'undefined') {
               grid.gridData.value[ data.searchFields[i] ]  = ''; 
            }

            grid.gridSearchDefaults[f] = grid.gridData.value[f];

            Vue.watch(
                () => grid.gridData.value[ data.searchFields[i] ], // Using a getter func to watch obj prop per docs
                () => grid.gridSearch()
            );
        }

        // grid.gridClearSearch();
    }

    return grid;
}