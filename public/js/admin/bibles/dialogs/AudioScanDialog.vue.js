const tpl = `
    <v-dialog 
        v-model='showing'
        max-width='800' 
    >
        <template v-slot:default="{ isActive }">
            <v-card>
                <v-card-title>Audio Bible Scan: {{bible?.name}}</v-card-title>
                <v-card-text>
                    This will scan the server for audio files that have been 
                    manually uploaded for this Bible and register them for use.<br /><br />

                    This will attempt to auto-detect the book number, chapter, and verse from each file. 
                    Files must have at least book and chapter information in the filename to be detected.
                    Any files that cannot be matched will be skipped.<br /><br />

                    Uusing FTP, SFTP, or other file transfer method, please place audio files in :<br />
                    <b>__path_to_BibleSuperSearch_API__/bibles/audio/{{bible.module}}</b> <br />
                    
                    directory on the server, then click the Scan button to have the system detect 
                    and register the audio files.<br /><br />

                    NOTE: Books are matched based on a 2-digit book number (01-66) (1 for Genesis, 2 for Exodus ... 66 for Revelation).
                    For example, the filename <strong>01_001_001.mp3</strong> would correspond to Genesis 1:1.
                    If the filename contains only the book name and not number, such as <strong>Genesis_1_1.mp3</strong>, 
                    it will not be matched.<br /><br />

                    If your files are not being detected, you can use the Audio Upload dialog to manually upload and specifiy the file name format.
                </v-card-text>

                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn
                        text='Close'
                        @click='handleCancel()'
                    ></v-btn>     

                    <v-btn
                        text='Scan'
                        :loading='scanLoading'
                        @click='scan()'
                    ></v-btn>
                </v-card-actions>
            </v-card>

        </template>

    </v-dialog>
`;

export default {
    template: tpl,
    inject: ['bootstrap', 'defaultProps'],
    props: {
        bible: {
            type: Object,   
            default: null,
        },
    },
    data() {
        return {
            scanLoading: false,
            showing: false
        }
    },
    methods: {
        handleCancel() {
            this.closeDialog();
        },
        closeDialog() {
            this.showing = false;
            this.$emit('onClose');
        },
        openDialog() {
            this.showing = true;
        },
        scan() {
            this.scanLoading = true;
            
            axios.post('/admin/bibles/audio/scan', {module: this.bible.module, bible_id: this.bible.id})
                .then(response => {
                    this.scanLoading = false;
                    this.$emit('scanSuccess');
                    this.closeDialog();
                })
                .catch(error => {
                    console.error('Error scanning audio:', error);
                    this.scanLoading = false;
                });
        },
    }
};