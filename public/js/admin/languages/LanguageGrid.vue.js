import LanguageForm from './LanguageEditForm.vue.js';
import BooksDialog from './BookListDialog.vue.js';
import EditDialog from '/js/bin/custom_vue/dialogs/EditDialog.vue.js';
import { useGrid } from '/js/bin/custom_vue/composables/Grid.vue.js';

const template = `<v-sheet>
            <h2>Languages</h2>
            
            <v-switch
                label='Include Languages Without Bibles'
                v-model='gridData.all_languages'
                @update:modelValue='gridRefetch'
                true-value='1'
                false-value='0'
            </v-switch>

            <v-data-table-server
                :items="gridRows"
                :items-length='totalRows'
                @update:options="gridPaginate"
                v-model:items-per-page="gridData.rows"
                :search='gridSearchDate'
                :items-per-page-options='itemsPerPageOptions'
                :page='gridData.page'
                

                :headers="headers"
                show-current-page
                :loading='loading ? "primary-darken-1" : false'
                fixed-header
                single-select
                hover
                density='compact'
                color='#333333'
            >
                <template v-slot:header.actions={column}>
                    <span>{{column.title}}</span>
                    
                    <!--
                        <v-chip
                            text='New'
                            @click='clickEdit()'
                            class='ml-4'
                        ></v-chip>
                    -->
                </template>

                <template v-slot:thead>
                    <tr>
                        <td>
                            <v-text-field 
                                v-model="gridData.code" class="ma-2" 
                                density="compact" 
                                placeholder="Search code..." 
                                hide-details
                            >
                            </v-text-field>
                        </td>                        
                        <td>
                            <v-text-field 
                                v-model="gridData.native_name" class="ma-2" 
                                density="compact" 
                                placeholder="Search Name..." 
                                hide-details
                            >
                            </v-text-field>
                        </td>                        
                        <td>
                            <v-text-field 
                                v-model="gridData.name" class="ma-2" 
                                density="compact" 
                                placeholder="Search English Name..." 
                                hide-details
                            >
                            </v-text-field>
                        </td>                        
                        <td>
                            <v-text-field 
                                v-model="gridData.family" class="ma-2" 
                                density="compact" 
                                placeholder="Search Family..." 
                                hide-details
                            >
                            </v-text-field>
                        </td>                          
                        <td>
                            <v-text-field 
                                v-model="gridData.bibles_min" class="ma-2" 
                                density="compact" 
                                placeholder="Min Bibles.." 
                                hide-details
                            >
                            </v-text-field>
                        </td>                         
                        <td>
                            <v-text-field 
                                v-model="gridData.bibles_max" class="ma-2" 
                                density="compact" placeholder="Max Bibles.." 
                                hide-details
                            >
                            </v-text-field>
                        </td>                        
                  </tr>
                </template>

                <template v-slot:item.book_list={item}>
                    <v-chip
                        :text="item.book_list == '1' ? 'Yes' : 'No'"
                        :color='bookListColor(item)'
                        @click='clickBookList(item)'
                    ></v-chip>
                </template>            

                <template v-slot:item.actions={item}>
                    <v-chip
                        text='Edit'
                        @click='clickEdit(item)'
                    ></v-chip>                    
                  
                    <!--
                        <v-chip
                            text='Edit PRE'
                            @click='clickEditPre(item)'
                        ></v-chip>
                    -->
                </template>

            </v-data-table-server>     

        <EditDialog
            :recordId='editingId'
            loadRecord
            recordType='Language'
            @onClose='closeEdit'
            @afterLeave='closeEdit'
            @onSave='gridRefresh'
            url='/admin/bibles/languages'
            v-slot='{data}'
        >
            <LanguageForm :record='data'></LanguageForm>
        </EditDialog>        

        <EditDialog
            :recordId='editingIdPre'
            :record='editingRecord'
            recordType='Language'
            @onClose='closeEdit'
            @afterLeave='closeEdit'
            @onSave='gridRefresh'
            url='/admin/bibles/languages'
            v-slot='{data}'
        >
            <LanguageForm :record='data'></LanguageForm>
        </EditDialog>

        <BooksDialog
            :language = 'blLanguage'
            :languageName = 'selectedLanguage.name'
            @onClose='closeBookList'
            @afterLeave='closeBookList'
        ></BooksDialog>

        </v-sheet>`;

export default {
    setup(props) {
        let data = {
            url: '/admin/languages/grid',
            gridData: {
                rows: 10,
                sidx: 'name',
                sord: 'ASC',
                start: null,
                all_languages: 0,
            },

            // Grid searchable fields (will be added to gridData as strings if don't exist)
            searchFields: ['code', 'name', 'native_name', 'family', 'bibles_min', 'bibles_max'],
        };

        return useGrid(data, props);
    },
    components: {
        EditDialog,
        LanguageForm,
        BooksDialog
    },
    data() {
        return { 
            // totalRows: 1,
            // gridRows: [],
            // gridData: {
            //     page: 1,
            //     rows: 10,
            //     sidx: 'name',
            //     sord: 'ASC',
            //     start: null,
            //     all_languages: 0,
                
            //     // Searchable
            //     code: '',
            //     name: '',
            //     native_name: '',
            //     family: '',
            //     bibles_min: '',
            //     bibles_max: '',
            // },
            sortDefault: {
                sidx: 'name',
                sord: 'ASC',
            },
            // loading: false,
            editing: false,
            editingId: null,
            editingIdExt: null,
            editingIdPre: null,
            editingRecord: {},
            selectedLanguage: {},
            blLanguage: null,
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
                {title: 'Actions', key: 'actions', sortable: false},
            ];
        },
    },
    watch: {
        // 'gridData.code'(newValue, oldValue) {
        //     this.gridSearch();
        // },        
        // 'gridData.name'(newValue, oldValue) {
        //     this.gridSearch();
        // },        
        // 'gridData.native_name'(newValue, oldValue) {
        //     this.gridSearch();
        // },        
        // 'gridData.family'(newValue, oldValue) {
        //     this.gridSearch();
        // },        
        // 'gridData.bibles_min'(newValue, oldValue) {
        //     this.gridSearch();
        // },        
        // 'gridData.bibles_max'(newValue, oldValue) {
        //     this.gridSearch();
        // }
    },
    methods: {
        /* Start Grid Methods */
        // gridFetchRows() {
        //     this.loading = true;
        //     var t = this;

        //     axios.request({
        //         url: '/admin/languages/grid',
        //         method: 'GET',
        //         params: this.gridData
        //     }).then(function(response) {
        //         t.gridRows = response.data.rows;
        //         t.totalRows = response.data.records;
        //         t.loading = false;
        //     });
        // },
        // gridRefetch() {
        //     this.gridData.page = 1;
        //     this.gridFetchRows();
        // },
        // gridRefresh() {
        //     this.gridFetchRows();
        // },
        // gridPaginate(options) {
        //     this.gridData.page = options.page;
        //     this.gridData.rows = options.itemsPerPage;
        //     this.gridData.start = this.gridData.page * this.gridData.rows - this.gridData.rows;

        //     var sorting = (options.sortBy[0]) ? options.sortBy[0] : {
        //         key: this.sortDefault.sidx,
        //         order: this.sortDefault.sord,
        //     };

        //     this.gridData.sidx = sorting.key;
        //     this.gridData.sord = sorting.order;

        //     this.gridFetchRows();
        // },
        /* End Grid Methods */
        
        clickEdit(item) {
            if(item) {
                this.editingId = item.id;
            } else {
                this.editingId = -1;
            }
        },             
        clickEditPre(item) {
            this.editingIdPre = item.id;
            this.editingRecord = item;
        },
        closeEdit() {
            this.editingId = null;
            this.editingIdExt = null;
            this.editingIdPre = null;
            this.editingRecord = {};
        },
        clickBookList(item) {
            if(item.book_list == '0') {
                return;
            }

            this.blLanguage = item.code;
            this.selectedLanguage = item;
        },
        closeBookList() {
            this.blLanguage = null;
            this.selectedLanguage = {};
        },
        bookListColor(item) {
            if(item.bibles == '0') {
                return 'grey';
            } else {
                return item.book_list == '1' ? 'green' : 'red';
            }
        }
    }
}

// vue-demi ?? for ??
// vuedate (validation)