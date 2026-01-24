var tpl = `
    <template>

    </template>
`;

export default {
    template: tpl,

    data() {
        return {
            msg: 'Base Message',
            name: 'Base',
            hasForm: false,
            hasContent: false
        }
    },
    props: {
        settings: {
            type: Object,
            default: {},
        },        
    },
    methods: {
        reset() {
            console.log('Base.reset');
        },
        getSettings() {
            return {bacon: true};
        }
    }
};