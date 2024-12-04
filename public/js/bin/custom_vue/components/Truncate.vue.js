
const template = `
        <span>
            {{truncatedText}}
            {{needsToTruncate}}
            <v-tooltip 
                activator='parent' 
                location='bottom' 
                v-if='needsToTruncate'
            >{{text}}</v-tooltip>
        </span>
`;

export default {
    props: {
        text: {
            type: String,
            required: true
        },
        maxLen: {
            type: Number,
            default: 30
        },
    },
    template: template,
    computed: {
        truncatedText() {
            return this.needsToTruncate ? this.text.substring(0, this.maxLen) : this.text;
        },
        needsToTruncate() {
            return this.text.length > this.maxLen;
        }
    }
}
