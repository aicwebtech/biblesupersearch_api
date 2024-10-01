const template = `<div>
            <h1>Language Grid</h1>
            Total rows: {{totalRows}}
            <v-switch
                label='Include Languages Without Bibles'
                v-model='gridData.all_languages'
                @update:modelValue='refetchGrid'
                true-value='1'
                false-value='0'
            </v-switch>

            <v-data-table-server
                :headers="headers"
                :items="gridRows"
                :items-length='totalRows'
                :page='gridData.page'
                show-current-page
                v-model:items-per-page="gridData.rows"
                :items-per-page-options='itemsPerPageOptions'
                :loading='loading'
                @update:options="paginateGrid"
                fixed-header
                single-select
                hover
                density='compact'
            >

            <template v-slot:item.book_list={item}>
                <v-chip
                    :text="item.book_list == '1' ? 'Yes' : 'No'"
                ></v-chip>
            </template>

            </v-data-table-server>
        </div>`;

export default {
    data() {
        return { 
            totalRows: 1,
            gridData: {
                page: 1,
                rows: 10,
                sidx: 'name',
                sord: 'ASC',
                start: null,
                all_languages: 0
            },
            loading: false,
            gridRows: [],
            itemsPerPageOptions: [5, 10, 25, 50, 100, {value: -1, title: '$vuetify.dataFooter.itemsPerPageAll'}],
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
                {title: '# Bibles', key: 'bibles'},
                {title: 'Book List', key: 'book_list'},
                // {title: 'Strong\s', key: 'strongs'},
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
        refetchGrid() {
            this.gridData.page = 1;
            this.fetchGridRows();
        },
        paginateGrid(options) {
            // do something
            console.log('paginateGrid', options);

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