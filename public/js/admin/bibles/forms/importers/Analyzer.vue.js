import Base from './Base.vue.js';

var tpl = `
    ANALYZER NEEDS CONTENT


    <h2>{{name}}</h2>
    <h3>{{msg}}</h3>
`;

export default {
    template: tpl,
    extends: Base,

    data() {
        return {
            name: 'Analyzer',
            hasForm: false,
            hasContent: true
        }
    },
};