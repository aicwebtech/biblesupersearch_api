import LanguageGrid from './LanguageGrid.vue.js';
import StrongsGrid from './StrongsGrid.vue.js';
import CrossRefGrid from './CrossReferenceGrid.vue.js';

export default {
    data() {
        return {  }
    },
    components: {
        LanguageGrid,
        StrongsGrid,
        CrossRefGrid
    },
    template: `
            <v-app>
                <LanguageGrid />
                <StrongsGrid />
                <CrossRefGrid />
            </v-app>
        `
}