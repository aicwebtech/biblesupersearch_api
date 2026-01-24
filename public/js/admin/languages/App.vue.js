import LanguageGrid from './LanguageGrid.vue.js';
import DefaultProps from '../../bin/custom_vue/components/DefaultProps.vue.js';
// :todo future - add these (paid)
// import StrongsGrid from './StrongsGrid.vue.js';
// import CrossRefGrid from './CrossReferenceGrid.vue.js';

export default {
    data() {
        return {  }
    },
    provide() {
        return {
            defaultProps: DefaultProps
        }
    },
    components: {
        LanguageGrid,
        // StrongsGrid,
        // CrossRefGrid
    },
    template: `
        <v-app>
            <LanguageGrid />
        </v-app>
    `
}