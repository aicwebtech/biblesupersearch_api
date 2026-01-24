const template = `
    <v-dialog 
        v-model='showing'
        max-width='600' 
        class='error-dialog'
    >
        <template v-slot:default="{ isActive }">
            <v-card >
                <v-card-title color='error'>{{title}}</v-card-title>

                <v-card-text class='vue_editdialog_body' ref='body'>
                    <ErrorPane :errors='errors' title='' />
                </v-card-text>

                <v-card-actions color='error'>
                    <v-spacer></v-spacer>

                    <v-btn
                        text='Close'
                        @click='showing = false'
                    ></v-btn>                    
                </v-card-actions>
            </v-card>
        </template>
    </v-dialog>
`;

import ErrorPane from '../components/ErrorPane.vue.js';

export default {
    props: {
        errors: {
            type: Object || Array,
            default: {}
        },
        title: {
            type: String,
            default: 'Error'
        }
    },
    template: template,
    components: {
        ErrorPane
    },
    data() {
        return { 
            showing: false
        }
    },
    watch: {
        errors(newValue, oldValue) {
            if(newValue && (newValue.length > 0 || Object.keys(newValue).length > 0)) {
                this.showing = true;
            }
        },
    }
}
