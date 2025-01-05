import Base from './Base.vue.js';

var tpl = `
    <br />
    Bibles in this format can be downloaded from ebible.com
    however, please make sure to select the USFM format option.<br /><br />
    Note: we only support the following markup features, everything else will be ignored:

    <ol class='normal_list'>
        <li>Italiced (added in translation) words</li>
        <li>Words of Christ in Red</li>
        <li>Strong\'s numbers</li>
    </ol>
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