import LanguageGrid from './LanguageGrid.vue.js';
import StrongsGrid from './StrongsGrid.vue.js';
import CrossRefGrid from './CrossReferenceGrid.vue.js';

export default {
    data() {
    return { count: 1 }
    },
    components: {
        LanguageGrid,
        StrongsGrid,
        CrossRefGrid
    },
    template: `<div>
        Count is: {{ count }}
            <LanguageGrid />
            <StrongsGrid />
            <CrossRefGrid />
      </div>
  `
}