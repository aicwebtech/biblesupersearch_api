import { gridTemplateProps, useGrid } from '../../bin/custom_vue/composables/Grid.vue.js';

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
                        ` + gridTemplateProps + `

                        :headers="headers"
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
    setup(props) {
        let data = {
            url: '/admin/biblebooks/grid/',
            gridData: {
                rows_per_page: 10,
                sidx: 'id',
                sord: 'ASC',
            },
        };

        return useGrid(data, props);
    },
    data() {
        return {
            showing: false,
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

            // reset grid BEFORE changing URL
            this.gridResetData();
            // Changing URL will cause grid refresh (ie reactive)
            this.url = '/admin/biblebooks/grid/' + newValue;
            this.showing = true;
        }
    },
    methods: {
        handleCancel() {
            this.closeDialog();
        },
        closeDialog() {
            this.showing = false;
            this.$emit('onClose');
        }
    }
};