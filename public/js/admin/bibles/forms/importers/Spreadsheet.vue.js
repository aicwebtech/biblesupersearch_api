import Base from './Base.vue.js';
import Roles from '/js/bin/custom_vue/components/SpreadsheetColumnRoles.vue.js';

var tpl = `
    <Roles
        :roles='columnRoles'
        :formData='settings'
        :initColumns='6'
    ></Roles>
`;

export default {
    template: tpl,
    extends: Base,

    components: {
        Roles
    },

    data() {
        return {
            name: 'Spreadsheet',
            hasForm: true,
            hasContent: true,

            columnRoles: [                        
                {value: 'none',     title: '-- None --'},
                {value: 'id',       title: 'ID - Unique (currently ignored)'},
                {value: 'bn',       title: 'Book Name'},
                {value: 'b',        title: 'Book Number'},
                {value: 'c',        title: 'Chapter'},
                {value: 'v',        title: 'Verse'},
                {value: 't',        title: 'Text'},
                {value: 'bn c:v ',  title: 'Book Name Chapter:Verse'},
                {value: 'b c:v ',   title: 'Book Number Chapter:Verse'},
                {value: 'c:v',      title: 'Chapter:Verse'}
            ],
        }
    },

    methods: {
        reset() {
            // ...Base.reset();
            Base.reset();
            console.log('Spreadsheet.reset');
        }
    }

};