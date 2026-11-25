import BibleGrid from './BibleGrid.vue.js';
import DefaultProps from '../../bin/custom_vue/components/DefaultProps.vue.js';
import Confirm from '../../bin/custom_vue/dialogs/ConfirmDialog.vue.js';

export default {
    data() {
        return {  }
    },
    components: {
        BibleGrid,
        Confirm,
    },
    provide() {
        return {
            defaultProps: DefaultProps,
            // Shared alert for code with no template of its own (the grid composable)
            alertDialog: message => this.$refs.AlertDialog.alert(message),
        }
    },
    template: `
        <v-app>
            <BibleGrid />
            <Confirm ref='AlertDialog' title='Error'></Confirm>
        </v-app>
    `
}