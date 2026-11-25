import FeatureGrid from './FeatureGrid.vue.js';
import DefaultProps from '../../bin/custom_vue/components/DefaultProps.vue.js';
import Confirm from '../../bin/custom_vue/dialogs/ConfirmDialog.vue.js';

export default {
    data() {
        return { }
    },
    provide() {
        return {
            defaultProps: DefaultProps,
            // Shared alert for code with no template of its own (the grid composable)
            alertDialog: message => this.$refs.AlertDialog.alert(message),
        }
    },
    components: {
        FeatureGrid,
        Confirm,
    },
    template: `
        <v-app>
            <FeatureGrid />
            <Confirm ref='AlertDialog' title='Error'></Confirm>
        </v-app>
    `
}
