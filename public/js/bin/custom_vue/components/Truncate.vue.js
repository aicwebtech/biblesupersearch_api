
const template = `
        <span>
            {{truncatedText}}
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
        maxWords: {
            type: Number,
            default: 30
        },
    },
    template: template,
    computed: {
        truncatedText() {
            if(!this.needsToTruncate) {
                return this.text;
            }

            var text = this.text,
                words = text.split(' ');

            return this.text.substring(0, this.maxLen - 4) + ' ...';
        },
        needsToTruncate() {
            return this.text.length > this.maxLen;
        }
    }
}
