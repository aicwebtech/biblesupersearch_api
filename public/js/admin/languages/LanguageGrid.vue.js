import EditDialog from './LanguageEditDialog.vue.js';

const template = `<div>
            <h2>Languages</h2>
            
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

                <template v-slot:item.actions={item}>
                    <v-chip
                        text='Edit'
                        @click='clickEdit(item)'
                    ></v-chip>
                </template>

            </v-data-table-server>

        <EditDialog 
            :showing='editing'
            :recordId='editingId'
            loadRecord
            @onClose='closeEdit'
            url='/admin/bibles/languages'
        ></EditDialog>
        </div>`;

export default {
    
    components: {
        EditDialog
    },
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
            sortDefault: {
                sidx: 'name',
                sord: 'ASC',
            },
            loading: false,
            editing: false,
            editingId: null,
            gridRows: [],
            itemsPerPageOptions: [5, 10, 25, 50, 100, {value: -1, title: '$vuetify.dataFooter.itemsPerPageAll'}],
        }
    },
    template: template, 
    computed: {
        headers() {
            return [
                {title: 'Code', key: 'code'},
                {title: 'Name', key: 'native_name'},
                {title: 'English Name', key: 'name'},
                {title: 'Family', key: 'family'},
                // todo
                {title: '# Bibles', key: 'bibles'},
                {title: 'Book List', key: 'book_list'},
                // {title: 'Strong\s', key: 'strongs'},
                {title: 'Actions', key: 'actions'},
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
                key: this.sortDefault.sidx,
                order: this.sortDefault.sord,
            };

            this.gridData.sidx = sorting.key;
            this.gridData.sord = sorting.order;

            this.fetchGridRows();
        },
        clickEdit(item) {
            console.log('clickEdit', item);
            this.editingId = item.id;
            this.editing = true;
        },
        closeEdit() {
            console.log('closeEdit');
            this.editing = false;
        }
    }
}

// vue-demi ?? for ??
// vuedate (validation)