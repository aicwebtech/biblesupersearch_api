import Roles from '../../../../bin/custom_vue/components/SpreadsheetColumnRoles.vue.js';
import Presets from '../../../../bin/custom_vue/components/ImportPresets.vue.js';
import { columnsInSettings } from '../../../../bin/custom_vue/components/SpreadsheetColumnCount.js';

/**
 * Column mapping for the spreadsheet importers (CSV, Excel).  The roles are the volume type's
 * import fields, from the bootstrap.
 */
const template = `
    <div>
        <Presets :type='contentType' :importer='importer' :settings='settings'></Presets>

        <Roles
            :roles='columnRoles'
            :formData='settings'
            :initColumns='initColumns'
        ></Roles>
    </div>
`;

export default {
    template: template,
    components: {
        Roles,
        Presets,
    },
    props: {
        settings: {
            type: Object,
            default: () => ({}),
        },
        contentType: {
            type: String,
            default: null,
        },
        importer: {
            type: String,
            default: null,
        },
    },
    setup(props) {
        const bootstrap = Vue.inject('bootstrap');

        const columnRoles = Vue.computed(() => {
            const type = (bootstrap.volume_types || {})[props.contentType];
            const fields = type ? type.import_fields || [] : [];

            return [{value: 'none', title: '-- None --'}].concat(fields.map(field => ({
                value: field.value,
                title: field.title + (field.required ? ' (required)' : ''),
            })));
        });

        const initColumns = Vue.computed(() => Math.max(6, columnsInSettings(props.settings)));

        return {
            columnRoles,
            initColumns,
        };
    }
};
