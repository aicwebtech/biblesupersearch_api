import LanguageGrid from './LanguageGrid.vue.js';
import DefaultProps from '../../bin/custom_vue/components/DefaultProps.vue.js';
import Confirm from '../../bin/custom_vue/dialogs/ConfirmDialog.vue.js';
// :todo future - add these (paid)
// import StrongsGrid from './StrongsGrid.vue.js';
// import CrossRefGrid from './CrossReferenceGrid.vue.js';

export default {
    data() {
        return {  }
    },
    provide() {
        return {
            defaultProps: DefaultProps,
            // Shared alert for code with no template of its own (the grid composable)
            alertDialog: message => this.$refs.AlertDialog.alert(message),
        }
    },
    components: {
        LanguageGrid,
        Confirm,
        // StrongsGrid,
        // CrossRefGrid
    },
    template: `
        <v-app>
            <LanguageGrid />
            <Confirm ref='AlertDialog' title='Error'></Confirm>
        </v-app>
    `
}