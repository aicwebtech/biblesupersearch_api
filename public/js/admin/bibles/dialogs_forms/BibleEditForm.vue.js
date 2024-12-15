// import { Ckeditor } from '@ckeditor/ckeditor5-vue';
import '/js/bin/ckeditor5/build/ckeditor.js';
// import '/js/bin/ckeditor5/ckeditor5.css';
// import { ClassicEditor } from 'ckeditor5';
import Ckeditor from '/js/bin/custom_vue/components/Ckeditor.vue.js';
import '/js/bin/ckeditor5/build/ckeditor.js';

const template = `
    <div 
        max-width='600' 
    >
        <v-text-field 
            label='Name' 
            v-model='record.name'
            density='compact'
            hide-details='auto'
        ></v-text-field>

        <v-text-field 
            label='Short Name' 
            v-model='record.shortname'
            readonly
            density='compact'
            hide-details='auto'
        ></v-text-field>

        <v-text-field 
            label='Module' 
            v-model='record.module'
            density='compact'
            hide-details='auto'
            :disabled='record.id > 0'
        ></v-text-field>      

        <v-switch
            v-model='record.enabled'
            label='Enabled'
            hide-details='auto'
            hint='Whether the Bible is enabled for use'
            :false-value="0"
            :true-value="1"
        ></v-switch>              
        
        <v-switch
            v-model='record.research'
            hide-details='auto'
            label='Research'
            :false-value="0"
            :true-value="1"
        ></v-switch>    

        <v-text-field 
            label='Rank' 
            v-model='record.rank'
            density='compact'
            hide-details='auto'
            hint='Customizable sort order.'
        ></v-text-field>             

        <v-autocomplete
            :items='bootstrap.languages'
            label='Language'
            v-model='record.lang_short'
            :item-props='languageItemProps'
            clearable
            density='compact'
            hide-details='auto'
        ></v-autocomplete>        

        <v-autocomplete
            :items='bootstrap.languages'
            label='Language Code'
            v-model='record.lang_short'
            :item-props='languageCodeProps'
            clearable
            density='compact'
            hide-details='auto'
        ></v-autocomplete>

        <v-divider class='mt-2 mb-2 border-opacity-50'></v-divider>

        <v-autocomplete
            :items='bootstrap.copyrights'
            label='Copyright'
            v-model='record.copyright_id'
            item-title='name'
            item-value='id'
            clearable
            density='compact'
            hide-details='auto'
            @click:clear='eventTest("cl:clear", $event)'
            @update:focused='eventTest("u:focused", $event)'
            @update:menu='eventTest("u:menu", $event)'
            @update:modelValue='copyRightChanged'
        ></v-autocomplete>

        <v-text-field 
            label='Copyright Owner' 
            v-model='record.owner'
            density='compact'
            hide-details='auto'
        ></v-text-field>          

        <v-text-field 
            label='Publisher' 
            v-model='record.publisher'
            density='compact'
            hide-details='auto'
        ></v-text-field>          

        <v-text-field 
            label='Publication Year' 
            v-model='record.year'
            density='compact'
            hide-details='auto'
        ></v-text-field>    

        <v-divider class='mt-2 mb-2 border-opacity-50'></v-divider>

        <v-textarea id='description'
            label='Description' 
            v-model='record.description'
            density='compact'
            hide-details='auto'
        ></v-textarea>

        <Ckeditor
            v-model='record.description'
        ></Ckeditor>
    </div>
`;

export default {
    template: template,
    inject: ['bootstrap'],

    components: {
        Ckeditor
    },

    props: {
        record: {
            type: Object,
            default: {}
        }
    },
    data() {
        return {
            prevCopyrightId: null,
        }
    },
    watch: {
        'record.copyright_id'(is, was) {
            this.prevCopyrightId = was || is;

            console.log('copyright_id', is, was);

            // if(!window.confirm('Please verify this is the correct copyright for this Bible')) {
            //     this.record.copyright_id = was;
            // }
        }
    },
    methods: {
        languageItemProps(item) {
            return {
                title: item.code.toUpperCase() + ' ' + item.name,
                value: item.code
            }
        },        
        languageCodeProps(item) {
            return {
                title: item.code.toUpperCase(),
                value: item.code
            }
        },
        copyRightChanged(event) {
            console.log('new', event);
            console.log('prev', this.prevCopyrightId);
            var prev = this.prevCopyrightId;
            var cr = bootstrap.copyrights.find((item) => item.id == event);

            var msg = 'Please verify this is the correct copyright for this Bible\n\n';

            msg += cr.name;

            msg += '\n\nWarning: Selecting the wrong copyright may put you at risk of civil or criminal penalties!';

            if(!window.confirm(msg)) {
                this.record.copyright_id = prev;
                this.prevCopyrightId = prev;
            }
        },

        eventTest(type, event) {
            // console.log(type, event);

            // if(type == 'u:modelValue') {
            // }
        }
    }
}
