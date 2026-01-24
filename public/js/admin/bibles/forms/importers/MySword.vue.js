import Base from './Base.vue.js';

// Just a placeholder, nothing in template here
// This importer doesn't show or do anything special when selected.
var tpl = `
    <template></template>
`;

export default {
    template: tpl,
    extends: Base,

    data() {
        return {
            name: 'MySword',
            hasForm: false,
            hasContent: false
        }
    },
};