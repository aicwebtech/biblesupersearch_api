import VolumeGrid from './VolumeGrid.vue.js';
import DefaultProps from '../../bin/custom_vue/components/DefaultProps.vue.js';
import Confirm from '../../bin/custom_vue/dialogs/ConfirmDialog.vue.js';

export default {
    components: {
        VolumeGrid,
        Confirm,
    },
    setup() {
        const AlertDialog = Vue.ref(null);

        Vue.provide('defaultProps', DefaultProps);

        // Shared alert for code with no template of its own (the grid composable)
        Vue.provide('alertDialog', message => AlertDialog.value.alert(message));

        return { AlertDialog };
    },
    template: `
        <v-app>
            <VolumeGrid />
            <Confirm ref='AlertDialog' title='Error'></Confirm>
        </v-app>
    `
}
