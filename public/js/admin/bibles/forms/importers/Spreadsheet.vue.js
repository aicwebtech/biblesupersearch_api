import Base from './Base.vue.js';
import Roles from '../../../../bin/custom_vue/components/SpreadsheetColumnRoles.vue.js';
import Presets from '../../../../bin/custom_vue/components/ImportPresets.vue.js';
import { columnsInSettings } from '../../../../bin/custom_vue/components/SpreadsheetColumnCount.js';

var tpl = `
    <div>
        <Presets type='bible' :importer='importer' :settings='settings'></Presets>

        <Roles
            :roles='columnRoles'
            :formData='settings'
            :initColumns='initColumns'
        ></Roles>
    </div>
`;

export default {
    template: tpl,
    extends: Base,

    components: {
        Roles,
        Presets
    },

    computed: {
        initColumns() {
            return Math.max(6, columnsInSettings(this.settings));
        }
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