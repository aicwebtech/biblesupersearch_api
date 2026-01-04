import LanguageForm from './LanguageEditForm.vue.js';
import BooksDialog from './BookListDialog.vue.js';
import EditDialog from '../../bin/custom_vue/dialogs/EditDialog.vue.js';
import YesNoSel from '../../bin/custom_vue/components/YesNoSelector.vue.js';
import { gridTemplateProps, useGrid } from '../../bin/custom_vue/composables/grid/Grid.vue.js';

const template = `<v-sheet>
            <h2 class='app'>Languages</h2>
            
            <v-switch
                label='Include Languages Without Bibles'
                v-model='gridData.all_languages'
                @update:modelValue='gridReset'
                true-value='1'
                false-value='0'
                color='primary'
            </v-switch>

            <v-data-table-server
                ` + gridTemplateProps + `
                :headers="headers"
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
                        <td v-for='col in headers'>
                            <component 
                                :is="col.searchComponent || 'v-text-field'" 
                                v-if='col.searchable != false'
                                v-model="gridData[col.searchField || col.key]" 
                                class="ma-0 mr-1 pa-0" 
                                density="compact" 
                                :placeholder="'Search ' + col.title + ' ...'" 
                                hide-details
                                clearable
                                v-bind='col.searchProps || null'
                            >
                            </component>
                        </td>                     
                    </tr>
                </template>

                <template v-slot:item.book_list={item}>
                    <v-chip
                        :text="item.book_list == '1' ? 'Yes' : 'No'"
                        :color='bookListColor(item)'
                        :variant = "item.book_list == '1' ? 'flat' : 'tonal'"
                        @click='clickBookList(item)'
                        v-bind="chipProps"
                    ></v-chip>
                </template>            

                <template v-slot:item.actions={item}>
                    <v-chip
                        text='Edit'
                        @click='clickEdit(item)'
                        v-bind="chipProps"
                    ></v-chip>                    
                  
                    <!--
                        <v-chip
                            text='Edit PRE'
                            @click='clickEditPre(item)'
                            v-bind="chipProps"
                        ></v-chip>
                    -->
                </template>

            </v-data-table-server>     

        <EditDialog
            :recordId='editingId'
            loadRecord
            recordType='Language'
            recordIndex='Language'
            @onClose='closeEdit'
            @afterLeave='closeEdit'
            @onSave='gridRefresh'
            url='/admin/languages'
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

        /*
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
        */

export default {
    setup(props) {
        let data = {
            url: '/admin/languages/grid',
            gridData: {
                sidx: 'name',
                sord: 'ASC',
                rows_per_page: 20,
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
        BooksDialog,
        YesNoSel
    },
    template: template, 
    data() {
        return { 
            editing: false,
            editingId: null,
            editingIdExt: null,
            editingIdPre: null,
            editingRecord: {},
            selectedLanguage: {},
            blLanguage: null,
            chipProps: {
                size: 'small',
                density: 'comfortable'
            },
        }
    },
    computed: {
        headers() {
            return [
                {title: 'Code', key: 'code'},
                {title: 'Name', key: 'native_name'},
                {title: 'English Name', key: 'name'},
                {title: 'Family', key: 'family'},
                // todo
                {title: '# Bibles', key: 'bibles', align: 'end', searchable: false},
                {title: 'Book List', key: 'book_list', align: 'center', searchable: false, searchComponent: 'YesNoSel'},
                // {title: 'Strong\s', key: 'strongs', searchComponent: 'YesNoSel'},
                {title: 'Actions', key: 'actions', sortable: false, align: 'center', searchable: false},
            ];
        },
    },
    methods: {        
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