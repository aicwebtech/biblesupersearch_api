const tpl = `
    <v-dialog 
        v-model='showing'
        max-width='600' 
    >
        <template v-slot:default="{ isActive }">
            <v-card>
                <v-card-title>{{title}}</v-card-title>
                <v-card-text>
                    <v-data-table-server
                        :items-length='totalRows'
                        :items="gridRows"
                        :page='gridData.page'
                        :headers="headers"
                        :items-per-page="gridData.rows"
                        :items-per-page-options='itemsPerPageOptions'
                        @update:options="paginateGrid"
                        density='compact'
                        :loading='loading ? "primary-darken-1" : false'
                        fixed-header
                        hover
                    ></v-data-table-server>  
                </v-card-text>

                <v-card-actions>
                    <v-spacer></v-spacer>

                    <v-btn
                        text='Close'
                        @click='handleCancel()'
                    ></v-btn>                    
                </v-card-actions>
            </v-card>

        </template>

    </v-dialog>
`;

export default {
    template: tpl,
    props: {
        language: {
            type: String,
            default: null,
        },        
        languageName: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            totalRows: 1,
            gridRows: [],
            loading: false,
            showing: false,
            gridData: {
                page: 1,
                rows: 11,
                sidx: 'id',
                sord: 'ASC',
                start: null
            },
            sortDefault: {
                sidx: 'id',
                sord: 'ASC',
            },
            headers: [
                {title: 'ID', key: 'id'},
                {title: 'English Name', key: 'name_en'},
                {title: 'Name', key: 'name'},
                {title: 'Short Name', key: 'shortname'},
                // todo
                // {title: 'Actions', key: 'actions', sortable: false},
            ],
            itemsPerPageOptions: [5, 11, 22, 33, {value: -1, title: '$vuetify.dataFooter.itemsPerPageAll'}],
        }
    },
    computed: {
        title() {
            return 'Bible Book List: ' + this.languageName
        }
    },
    watch: {
        language(newValue, oldValue) {
            console.log('language', newValue);

            if(newValue === false || newValue === null) {
                this.showing = false;
                return;
            }

            this.showing = true;
            this.refetchGrid();
        }
    },
    methods: {
        fetchGridRows() {
            this.loading = true;
            var t = this;

            axios.request({
                url: '/admin/biblebooks/grid/' + this.language,
                method: 'GET',
                params: this.gridData
            }).then(function(response) {
                t.gridRows = response.data.rows;
                t.totalRows = response.data.records;
                t.loading = false;
            }).catch(function(error) {
                t.gridRows = [];
                t.totalRows = 0;
                t.loading = false;

                if(error.response.data.message) {
                    alert(error.response.data.message);
                } else {
                    alert('An unknown error has occurred');
                }
            });
        },
        refetchGrid() {
            this.gridData.page = 1;
            this.fetchGridRows();
        },
        refreshGrid() {
            console.log('refreshGrid');
            this.fetchGridRows();
        },
        paginateGrid(options) {
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
        handleCancel() {
            this.closeDialog();
        },
        closeDialog() {
            this.showing = false;
            this.$emit('onClose');
        }
    }
};