const template = `<div>
            <h1>Language Grid</h1>
            <v-data-table 
                :headers="headers"
                :items="gridRows"
                :items-length='totalRows'
                v-model:items-per-page="gridData.rows"
                :loading='loading'
                @update:options="paginateGrid"
                fixed-header
                single-select
                hover
                density='compact'
            ></v-data-table>
        </div>`;

export default {
    data() {
        return { 
            totalRows: 1,
            gridData: {
                page: 1,
                rows: 20,
                sidx: 'name',
                sord: 'ASC',
                start: null
            },
            loading: false,
            gridRows: []
        }
    },
    template: template, 
    computed: {
        headers() {
            return [
                {title: 'Code', key: 'code'},
                {title: 'English Name', key: 'name'},
                {title: 'Native Name', key: 'native_name'},
                {title: 'Family', key: 'family'},
                // todo
                {title: '# Bibles', key: 'num_bibles'},
                {title: 'Book List', key: 'book_list'},
                {title: 'Strong\s', key: 'strongs'},
            ];
        },
    },
    methods: {
        fetchGridRows() {
            this.loading = true;
            var t = this;

            axios.request({
                url: '/admin/languages/grid',
                method: 'GET',
                params: this.gridData
            }).then(function(response) {
                t.gridRows = response.data.rows;
                t.totalRows = response.data.records;
                t.loading = false;
            });
        },
        paginateGrid(options) {
            // do something
            this.gridData.page = options.page;
            this.gridData.rows = options.itemsPerPage;
            this.gridData.start = this.gridData.page * this.gridData.rows - this.gridData.rows;

            var sorting = (options.sortBy[0]) ? options.sortBy[0] : {
                key: this.gridData.sidx,
                order: this.gridData.sord,
            };

            this.gridData.sidx = sorting.key;
            this.gridData.sord = sorting.order;

            this.fetchGridRows();
        }
    }
}

// vue-demi ?? for ??
// vuedate (validation)