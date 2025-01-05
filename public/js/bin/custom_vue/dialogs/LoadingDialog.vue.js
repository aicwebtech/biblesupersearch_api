const template = `
    <v-dialog 
        v-model='showing'
        min-width='100' 
        max-width='400' 
        color='transparent'
    >
        <template v-slot:default="{ isActive }">
            <v-sheet style='margin: auto; padding: 10px'>
                {{text}}
                <br /><br />
                <img src='/images/Spinner.gif'></img>
            </v-sheet>
        </template>
    </v-dialog>
`;

export default {
    props: {
        showing: {
            type: Boolean,
            default: false
        },
        text: {
            type: String,
            default: 'Loading ...'
        }
    },
    template: template
}
