const tpl = `
    <div class="audio-upload-dialog">
        <v-dialog v-model="isOpen" max-width="800">
            <template v-slot:default="{ isActive }">
        
                <v-card>
                    <v-card-title>Upload Audio Files</v-card-title>
                    <v-card-text>
                        <v-select
                            v-model="matching"
                            :items="fileMatchOptions"
                            label="Filename Matching"
                            iitem-value="key"
                            iitem-text="label"
                            @update:modelValue="previewFiles"
                            density="comfortable"
                        />

                        <v-sheet class='mt-2 text-body-2' outlined>
                            NOTE: Books are matched based on a 2-digit book number (01-66) (1 for Genesis, 2 for Exodus ... 66 for Revelation).
                            For example, the filename <strong>01_001_001.mp3</strong> would correspond to Genesis 1:1.
                            If the filename contains only the book name and not number, such as <strong>Genesis_1_1.mp3</strong>, it will not be matched.
                        </v-sheet>

                        <v-switch
                            v-model='overwriteExisting'
                            label='Overwrite Existing Files'
                            color='primary'
                            class='mt-4'
                            density="compact"
                        />
                    
                        <v-file-input
                            v-model="files"
                            multiple
                            accept="audio/mp3"
                            label="Select MP3 files"
                            prepend-icon="mdi-audio"
                            @change="onFilesSelected"
                            density="comfortable"
                            :hint="'Maximum upload size of ' + bootstrap.maxUploadSize.fmt + 'B'"

                            :rules="[ v => fileSizeValid || 'Total file size exceeds maximum of ' + bootstrap.maxUploadSize.fmt + 'B' ]"
                        />

                        <div v-if="uploadProgress > 0" class="mt-4">
                            <v-progress-linear
                                :value="uploadProgress"
                                striped
                            />
                            <p class="text-center mt-2">{{ uploadProgress }}%</p>
                        </div>

                        <table v-if="matchingPreview.length > 0" class="mt-4 w-100 zebra_table" dense>
                            <thead>
                                <tr>    
                                    <th class="border-e">Filename</th>
                                    <th class="border-e text-center">Matched</th>
                                    <th class="border-e">Type</th>
                                    <th class="border-e">Reference</th>
                                    <th>Correct?</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr 
                                    v-for="(preview, idx) in matchingPreview" 
                                    :key="preview.filename"
                                    :class="idx % 2 == 0 ? 'grey lighten-4' : ''"
                                >
                                    <td class='border-e'>{{ preview.filename }}</td>
                                    <td class="border-e text-center">{{ preview.success ? 'Yes' : 'No' }}</td>
                                    <td class='border-e'>{{ preview.parsed ? preview.parsed.type : 'N/A' }}</td>
                                    <td class='border-e'>
                                        <span v-if="!preview.parsed">No Match</span>
                                        <span v-else>
                                            <span v-if="preview.parsed.type == 'verse'">
                                                {{preview.parsed.book}}-{{bootstrap.book_lists.en[preview.parsed.book - 1].name || 'Unknown'}}
                                                {{preview.parsed.chapter}}:{{preview.parsed.verse}}
                                            </span>
                                            <span v-else-if="preview.parsed.type == 'chapter'">
                                                {{preview.parsed.book}}-{{bootstrap.book_lists.en[preview.parsed.book - 1].name || 'Unknown'}}
                                                {{preview.parsed.chapter}}
                                            </span>
                                            <span v-else>No Match</span>
                                        </span>
                                     </td>
                                    <td class='text-center'>
                                        <v-checkbox-btn
                                            v-model="preview.correct"
                                            :disabled="!preview.success"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <v-sheet v-if="hasMismatch" class='mt-4 pa-4' color='error' outlined>
                            Some files could not be matched. Please adjust your matching selection or change selected files.
                        </v-sheet>
                        <v-sheet v-else-if='!allCorrect' class='mt-4 pa-4' color='warning' outlined>
                            Please confirm that all matched files are mapped to the correct reference before uploading.
                            If any are incorrrect, please adjust your matching selection or change selected files.
                        </v-sheet>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer />
                        <v-btn text @click="closeDialog">Cancel</v-btn>
                        <v-btn
                            :loading="isUploading"
                            @click="uploadFiles"
                        >
                            Upload
                        </v-btn>
                    </v-card-actions>
                </v-card>

            </template>
        </v-dialog>
    </div>
`;

export default {
    name: 'AudioUploadDialog',
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
            isOpen: false,
            files: [],
            matching: 'auto',
            matchingPreview: [],
            overwriteExisting: false,
            isUploading: false,
            uploadProgress: 0,
        };
    },
    computed: {
        fileMatchOptions() {
            return this.bootstrap.tts_filename_matches.map((match) => {
                return { value: match.key, title: match.label };
            });
        },
        hasMismatch() {
            return this.matchingPreview.some(preview => !preview.success) ? true : false;
        },
        allCorrect() {
            if(this.hasMismatch) return false;
            
            return this.matchingPreview.every(preview => preview.success && preview.correct) ? true : false;
        },
        formValid() {
            return this.files.length > 0 && !this.hasMismatch && this.allCorrect && this.matching !== null && this.fileSizeValid;
        },
        fileSizeValid() {
            if(this.files.length === 0) {
                return true;
            }
            
            var totalSize = 0;
            this.files.forEach(file => {
                totalSize += file.size;
            });

            return totalSize <= this.bootstrap.maxUploadSize.raw;
        }
    },
    methods: {
        openDialog() {
            this.clearForm();
            this.isOpen = true;
        },
        closeDialog() {
            this.isOpen = false;
            this.clearForm();
        },
        clearForm() {
            this.files = []
            this.matchingPreview = [];
            this.uploadProgress = 0;
        },
        onFilesSelected(files) {
            this.previewFiles();
        },
        uploadFiles() {
            if (!this.formValid) {
                return;
            }

            this.isUploading = true;
            const formData = new FormData();
            formData.append('matching', this.matching);
            formData.append('module', this.bible.module);
            formData.append('overwrite_existing', this.overwriteExisting ? '1' : '0');

            this.files.forEach(file => {
                formData.append('files[]', file);
            });

            axios.post('/admin/bibles/audio/upload', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                },
                onUploadProgress: progressEvent => {
                    this.uploadProgress = Math.round((progressEvent.loaded * 100) / progressEvent.total);   
                }
            })
            .then(response => {
                this.isUploading = false;

                if(response.data.success) {
                    this.$emit('uploadSuccess', response.data);
                } else {
                    this.$emit('uploadError', response.data.message || 'Upload failed');
                }

                this.closeDialog();
            })
            .catch(error => {
                this.isUploading = false;
                this.$emit('uploadError', error.message);
            });
        },
        previewFiles() {
            this.matchingPreview = [];
            
            if (!this.files.length) return;

            const formData = new FormData();
            formData.append('matching', this.matching);
            formData.append('module', this.bible.module);

            this.files.forEach(file => {
                formData.append('filenames[]', file.name);
            });

            axios.post('/admin/bibles/audio/preview', formData)
                .then(response => {
                    this.isUploading = false;

                    if(response.data.success) {
                        this.matchingPreview = response.data.results || [];
                    } else {
                        // do nothing?
                    }
                })
                .catch(error => {
                    // do nothing
                });
        },
        parseFilename(filename) {
            console.log('Parsing filename:', filename);
            var auto = this.matching == 'auto';
            var has_match = false;
            var matchPattern = null;

            if(auto) {
                for (let matchPattern of this.bootstrap.tts_filename_matches) {
                    if(!matchPattern.auto) continue;
                    
                    let regex = new RegExp(matchPattern.pattern);
                    let match = filename.match(regex);

                    if (match) {
                        console.log('Using selected match pattern:', matchPattern);
                        has_match = true;
                        break;
                    }
                }

                // /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/
                // /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/

                if (!has_match) return null; // No matching pattern found
            } else {
                let matchPattern = this.bootstrap.tts_filename_matches.find(match => match.key === this.matching);
                console.log('Using selected match pattern:', matchPattern);
                if (!matchPattern) return null; // No matching pattern found

                let regex = new RegExp(matchPattern.pattern);
                console.log('Using regex:', regex);
                let match = filename.match(regex);
                if (!match) return null; // Filename does not match pattern
            }

            if(!matchPattern) return null;
            
            let result = {};
            
            if(matchPattern.type === 'verse') {
                result.type = 'verse';
                result.book = parseInt(match[1], 10);
                result.chapter = parseInt(match[2], 10);
                result.verse = parseInt(match[3], 10);
            } else if(matchPattern.type === 'chapter') {
                result.type = 'chapter';
                result.book = parseInt(match[1], 10);
                result.chapter = parseInt(match[2], 10);
                result.verse = null;
            }

            console.log('Parsed filename:', filename, 'Result:', result);

            return result;
        },
    },
};
