/**
 * Asks for a single line of text: a Vuetify replacement for window.prompt().
 *
 * Use through a ref:  PromptDialog.value.prompt('Name for these settings:', '', value => { ... });
 * The callback only runs on OK, with the trimmed value.
 */
const template = `
    <v-dialog v-model='showing' max-width='500'>
        <v-card>
            <v-card-title v-if='title'>{{title}}</v-card-title>

            <v-card-text>
                <v-form ref='form' @submit.prevent='handleOk'>
                    <v-text-field
                        v-model='value'
                        :label='message'
                        :rules='rules'
                        :maxlength='maxLength'
                        density='compact'
                        autofocus
                    ></v-text-field>
                </v-form>
            </v-card-text>

            <v-card-actions>
                <v-spacer></v-spacer>
                <v-btn :text='cancelOption' @click='handleCancel()'></v-btn>
                <v-btn :text='okOption' @click='handleOk()'></v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
`;

export default {
    template: template,
    props: {
        title: {
            type: String,
            default: null,
        },
        okOption: {
            type: String,
            default: 'OK',
        },
        cancelOption: {
            type: String,
            default: 'Cancel',
        },
        maxLength: {
            type: Number,
            default: 255,
        },
    },
    setup(props) {
        const form = Vue.ref(null);
        const showing = Vue.ref(false);
        const message = Vue.ref('');
        const value = Vue.ref('');
        let callback = null;

        const rules = [
            v => !!(v && v.trim()) || 'This is required',
            v => !v || v.length <= props.maxLength || 'Maximum length is ' + props.maxLength,
        ];

        function prompt(text, defaultValue, onOk) {
            message.value = text;
            value.value = defaultValue || '';
            callback = onOk || null;
            showing.value = true;
        }

        async function handleOk() {
            const { valid } = await form.value.validate();

            if(!valid) {
                return;
            }

            showing.value = false;

            if(typeof callback == 'function') {
                callback(value.value.trim());
            }
        }

        function handleCancel() {
            showing.value = false;
        }

        return {
            form,
            showing,
            message,
            value,
            rules,
            prompt,
            handleOk,
            handleCancel,
        };
    }
};
