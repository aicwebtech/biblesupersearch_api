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
                <v-card-title>{{title}}</v-card-title>
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

                    <v-btn 
                        prepend-icon="mdi-upload" 
                        class="mb-2" 
                        color="primary" 
                        @click="$refs.uploadDialog.openDialog()"
                    >Upload Audio Files</v-btn>

                    <v-data-table-server
                        ` + gridTemplateProps + `

                        :headers="headers"
                    >
                        
                        <template v-slot:thead>
                            <tr class='grid-thead-search'>
 
                                <td v-for='col in headers'>
                                    <component 
                                        :is="col.searchComponent || 'v-text-field'" 
                                        v-if='col.searchable != false'
                                        v-model="gridData[col.searchField || col.key]" 
                                        class="ma-0 mr-1 pa-0 text-caption" 
                                        density="compact" 
                                        :placeholder="col.searchLabel === false ? null : 'Search ' + col.title + ' ...'" 
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
                                :value="item.id !== null"
                                v-bind='chipProps'
                                @click-false="openUploadSingle(item)"
                            />
                        </template>
                        <template v-slot:item.type={item}>
                            <span v-if='item.verse === null'>Chapter</span>
                            <span v-else>Verse</span>
                        </template>
                        <template v-slot:item.verse={item}>
                            <span v-if='item.verse === null'>(all)</span>
                            <span v-else>{{item.verse}}</span>
                        </template>
                    </v-data-table-server>  
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
            },

            // Grid searchable fields (will be added to gridData as strings if don't exist)
            searchFields: [
                'name', 'book', 'chapter', 'verse', 'has_audio',
            ],
        };

        return useGrid(data, props);
    },
    data() {
        return {
            showing: false,
            headers: [
                {title: 'Book Name', key: 'book_name'},
                {title: 'Chapter', key: 'chapter'},
                {title: 'Verse', key: 'verse'},
                {title: 'Has Audio', key: 'has_audio', searchComponent: 'YesNoSel', searchField: 'has_audio', searchProps: {label: 'Has Audio'}},
                // todo
                // {title: 'Actions', key: 'actions', sortable: false},
            ],
            itemsPerPageOptions: [5, 11, 22, 33, {value: -1, title: '$vuetify.dataFooter.itemsPerPageAll'}],
            chipProps: {
                size: 'small',
                density: 'comfortable'
            },
        }
    },
    computed: {
        title() {
            return 'Audio Bible Manager: ' + this.record?.name;
        }
    },
    watch: {
        recordId(newValue, oldValue) {
            console.log('recordId', newValue);

            if(newValue === false || newValue === null) {
                this.showing = false;
                return;
            }

            // reset grid BEFORE changing URL
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
        }
    }
};