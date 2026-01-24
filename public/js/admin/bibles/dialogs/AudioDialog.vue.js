import { gridTemplateProps, useGrid } from '../../../bin/custom_vue/composables/grid/Grid.vue.js';
import ChipBool from '../../../bin/custom_vue/components/ChipBool.vue.js';
import YesNoSel from '../../../bin/custom_vue/components/YesNoSelector.vue.js';
import AudioUploadDialog from './AudioUploadDialog.vue.js';

const tpl = `
    <v-dialog 
        v-model='showing'
        max-width='600' 
    >
        <template v-slot:default="{ isActive }">
            <v-card>
                <v-card-title>Audio Bible Manager: {{record?.name}}</v-card-title>
                <v-card-text>
                    <AudioUploadDialog
                        ref='uploadDialog'
                        :bible="record"
                        @upload-success='gridRefresh()'
                    >
                        <template v-slot:activator="{ props: activatorProps }">
                            <v-btn
                                color="primary"
                                dark
                                v-bind="activatorProps"
                            >
                                Upload Audio Files
                            </v-btn>
                        </template>
                    </AudioUploadDialog>

                    <v-sheet v-if='rowSelections.length > 0' class='mt-3 mb-12'>
                        <v-btn 
                            prepend-icon="mdi-delete" 
                            size='small'
                            class="mb-2 float-left" 
                            @click="deleteSelectedRows()"
                        >Delete Audio Files</v-btn>
                        <span class='clear-both'></span>
                    </v-sheet>
                    <v-sheet v-else class='mt-3 mb-12'>
                        <span class='float-right'>&nbsp;</span>
                        <v-btn 
                            prepend-icon="mdi-upload" 
                            size='small'
                            class="mb-2 float-right" 
                            @click="$refs.uploadDialog.openDialog()"
                        >Upload Audio Files</v-btn>
                        <span class='clear-both'></span>
                    </v-sheet>

                    <v-data-table-server
                        ` + gridTemplateProps + `
                        show-select
                        :item-value="item => rowId(item)"
                        v-model='rowSelections'
                        :headers="headers"
                    >
                        <template v-slot:thead>
                            <tr class='grid-thead-search'>
                                <td class='text-center pa-0 ma-0' style='width:40px;'>
                                    <v-btn 
                                        icon='mdi-filter-remove' 
                                        size='x-small' 
                                        @click='gridResetSearch()'
                                        title='Reset Filters'
                                    ></v-btn>
                                </td>
                                <td v-for='col in headers'>
                                    <component 
                                        :is="col.searchComponent || 'v-text-field'" 
                                        v-if='col.searchable != false'
                                        v-model="gridData[col.searchField || col.key]" 
                                        class="ma-0 mr-1 pa-0 text-caption" 
                                        density="compact" 
                                        :placeholder="col.searchLabel === false ? null : 'Search ...'" 
                                        hide-details
                                        clearable
                                        v-bind='col.searchProps || null'
                                    >
                                    </component>
                                </td>                     
                            </tr>
                        </template>
                    
                        <template v-slot:item.has_audio={item}>
                            <ChipBool
                                :value="item.has_audio == 1"
                                v-bind='chipProps'
                                @click-false="openUploadSingle(item)"
                            />
                        </template>
                        <template v-slot:item.type={item}>
                            <span v-if='item.verse === null'>Chapter</span>
                            <span v-else>Verse</span>
                        </template>
                        <template v-slot:item.verse={item}>
                            <span v-if='item.verse === null'>(whole chapter)</span>
                            <span v-else>{{item.verse}}</span>
                        </template>
                    </v-data-table-server>  
                </v-card-text>

                <v-card-actions>
                    <v-spacer></v-spacer>

                    <v-btn
                        text='scan'
                        :loading='scanLoading'
                        @click='scan()'
                    ></v-btn>

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
        recordId: {
            type: Number,
            default: null,
        },        
        record: {
            type: Object,
            default: null,
        },
    },
    components: {
        ChipBool,
        YesNoSel,
        AudioUploadDialog
    },
    setup(props) {
        let data = {
            url: '/admin/bibles/audio/grid/',
            gridData: {
                rows_per_page: 10,
                sidx: 'id',
                sord: 'ASC',
                has_audio: null,
                type: null,
            },

            // Grid searchable fields (will be added to gridData as strings if don't exist)
            searchFields: [
                'book_name', 'chapter', 'verse', 'has_audio', 'type'
            ],
        };

        return useGrid(data, props);
    },
    data() {
        return {
            showing: false,
            headers: [
                {title: 'Type', key: 'type', searchComponent: 'v-select', searchField: 'type', searchProps: { 
                    items: [ 
                        { title: 'Chapter', value: 0 }, 
                        { title: 'Verse', value: 1 } 
                    ]
                }},
                {title: 'Book Name', key: 'book_name'},
                {title: 'Chapter', key: 'chapter'},
                {title: 'Verse', key: 'verse'},
                {title: 'Has Audio', key: 'has_audio', searchComponent: 'YesNoSel', searchField: 'has_audio'},
                // todo
                // {title: 'Actions', key: 'actions', sortable: false},
            ],
            itemsPerPageOptions: [5, 11, 22, 33, {value: -1, title: '$vuetify.dataFooter.itemsPerPageAll'}],
            chipProps: {
                size: 'small',
                density: 'comfortable'
            },
            scanLoading: false,
            rowSelections: [],
        }
    },
    watch: {
        recordId(newValue, oldValue) {
            if(newValue === false || newValue === null) {
                this.showing = false;
                return;
            }

            // reset grid BEFORE changing URL
            this.gridClearSearch();
            this.gridResetData();
            // Changing URL will cause grid refresh (ie reactive)
            this.url = '/admin/bibles/audio/grid/' + newValue;
            this.showing = true;
        }
    },
    methods: {
        openUploadSingle(item) {
            this.$emit('onOpenUploadSingle', item);
        },
        handleCancel() {
            this.closeDialog();
        },
        closeDialog() {
            this.showing = false;
            this.$emit('onClose');
        },
        scan() {
            this.scanLoading = true;
            
            axios.post('/admin/bibles/audio/scan', {module: this.record.module, bible_id: this.record.id})
                .then(response => {
                    this.gridRefresh();
                    this.scanLoading = false;
                })
                .catch(error => {
                    console.error('Error scanning audio:', error);
                    this.scanLoading = false;
                });
        },
        rowId(item) {
            if(item.id) {
                return item.id;
            }

            return 'a_' + item.book + '_' + item.chapter + '_' + (item.verse || '0');
        },
        deleteSelectedRows() {
            if(this.rowSelections.length === 0) {
                alert('No audio files selected for deletion.');
                return;
            }

            if(!confirm('Are you sure you want to delete the selected audio files? This action cannot be undone.')) {
                return;
            }

            var idsToDelete = this.rowSelections.filter(ids => typeof ids === 'number');

            axios.post('/admin/bibles/audio/delete', {
                ids: idsToDelete,
                module: this.record.module,
                bible_id: this.record.id,
            })
            .then(response => {
                this.rowSelections = [];
                this.gridRefresh();
            })
            .catch(error => {
                console.error('Error deleting audio files:', error);
            });
        }
    }
};