const template = `
    <span @click='show = !show' :class='classes'>
        {{truncatedText}}
        <v-tooltip 
            activator='parent' 
            location='bottom' 
            v-if='needsToTruncate'
            v-model='show'
            :open-delay='300'
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
        cssTruncate: {
            type: Boolean,
            default: false
        },
    },
    template: template,
    data() {
        return {
            show: false
        }
    },
    computed: {
        classes() {
            return {
                'truncate': this.cssTruncate,
            }
        },
        truncatedText() {
            if(!this.needsToTruncate || this.cssTruncate) {
                return this.text;
            }

            var text = this.text,
                words = text.split(' ');

            return this.text.substring(0, this.maxLen - 4) + ' ...';
        },
        needsToTruncate() {
            return this.text ? this.cssTruncate || this.text.length > this.maxLen : false;
        },
        numberOfWords() {
            return this.text ? this.text.split().length : 0;
        }
    }
}
