const template = `
    <v-select
        :items='items'
    ></v-select>
`;

export default {
    template: template,
    data() {
        return {
            items: [
                // {title: 'Any', value: ''},
                {title: 'Yes', value: 1},
                {title: 'No', value: 0}
            ]
        }
    },
    computed: {

    }
}