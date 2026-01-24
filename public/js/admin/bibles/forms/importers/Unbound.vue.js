import Base from './Base.vue.js';

// Just a placeholdr, nothing in template here
// This importer doesn't show or do anything special when selected.
var tpl = `
    <template></template>
`;

export default {
    template: tpl,
    extends: Base,

    data() {
        return {
            name: 'Unbound',
            hasForm: false,
            hasContent: false
        }
    },
};