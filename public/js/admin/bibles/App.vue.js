import BibleGrid from './BibleGrid.vue.js';
import DefaultProps from '../../bin/custom_vue/components/DefaultProps.vue.js';

export default {
    data() {
        return {  }
    },
    components: {
        BibleGrid,
    },
    provide() {
        return {
            defaultProps: DefaultProps
        }
    },
    template: `
        <v-app>
            <BibleGrid />
        </v-app>
    `
}