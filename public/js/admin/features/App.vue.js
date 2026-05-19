import FeatureGrid from './FeatureGrid.vue.js';
import DefaultProps from '../../bin/custom_vue/components/DefaultProps.vue.js';

export default {
    data() {
        return { }
    },
    provide() {
        return {
            defaultProps: DefaultProps
        }
    },
    components: {
        FeatureGrid,
    },
    template: `
        <v-app>
            <FeatureGrid />
        </v-app>
    `
}
