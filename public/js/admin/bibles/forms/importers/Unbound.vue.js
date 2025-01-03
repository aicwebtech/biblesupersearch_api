import Base from './Base.vue.js';

var tpl = ``;

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