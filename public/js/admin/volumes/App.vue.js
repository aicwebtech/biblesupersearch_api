import VolumeGrid from './VolumeGrid.vue.js';
import DefaultProps from '../../bin/custom_vue/components/DefaultProps.vue.js';

export default {
    components: {
        VolumeGrid,
    },
    setup() {
        Vue.provide('defaultProps', DefaultProps);
    },
    template: `
        <v-app>
            <VolumeGrid />
        </v-app>
    `
}
