const tpl = `
    <v-sheet v-if='showing' class='pa-2 mb-3' ref='error'>
        <h3 v-if='title'>{{title}}</h3>
        <ul class='pt-2 pl-6'>
            <template v-for='(error, index) in errors' :key='index'>
                <li v-if='typeof error === "string"' v-html='error'></li>
                <li v-else v-for='(e, i) in error' :key='i' v-html='e'></li>
            </template>
        </ul>
    </v-sheet>
`;

export default {
    props: {
        errors: {
            type: Object || Array,
            default: {}
        },
        title: {
            type: String,
            default: 'Errors:'
        }
    },
    template: tpl,
    computed: {
        showing() {
            return this.errors && (this.errors.length > 0 || Object.keys(this.errors).length > 0);
        }
    }
}
