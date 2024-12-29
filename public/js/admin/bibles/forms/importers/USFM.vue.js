import Base from './Base.vue.js';

var tpl = `
    <h2>{{name}}</h2>
    <h3>{{msg}}</h3>
`;

export default {
    template: tpl,
    extends: Base,

    data() {
        return {
            name: 'USFM',
            hasForm: false,
            hasContent: true
        }
    },
};