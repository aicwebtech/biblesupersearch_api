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
    methods: {
        reset() {
            console.log('Base.reset');
        }
    }
};