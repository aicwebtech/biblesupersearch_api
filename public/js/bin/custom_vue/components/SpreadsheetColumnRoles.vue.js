var tpl = `
    <v-sheet>
        <v-text-field 
            :label='firstRowLabel' 
            :hint='firstRowHint'
            v-model='formData.first_row_data'
            density='compact'
        ></v-text-field>

        <h4>Please select the role for each column</h4>

        <v-select
            v-for='n in showingColumns'
            :label="'Column ' + intToLetter(n, true)"
            :items='roles'
            density='compact'
            v-model = 'formData["col_" + intToLetter(n)]'
            hide-details='auto'
        </v-select>

        <v-chip
            @click='showingColumns ++'
            text='Add Column'
        ></v-chip>
    </v-sheet>
`;

export default {
    template: tpl,
    props: {
        roles: {
            type: Array,
            default: []
        },
        initColumns: {
            type: Number,
            default: 4,
        },        
        formData: {
            type: Object,
            default: {},
        },        
        firstRowLabel: {
            type: String,
            default: 'First Row of Data',
        },        
        firstRowHint: {
            type: String,
            default: 'Typically, Row 1 contains column headers and Row 2 is the first row of data.',
        },
    },
    data() {
        return {
            showingColumns: 0
        }
    },
    methods: {
        reset() {
            this.showingColumns = this.initColumns;
        },
        intToLetter(int, upperCase) {
            var charCode = int + 96,
            letter = String.fromCharCode(charCode);
            return upperCase ? letter.toUpperCase() : letter;
        }
    },
    watch: {
        initColumns: {
            handler(is, was) {
                console.log('init showing Columns');
                this.showingColumns = is;
                this.formData.firstRow = 2;
            },
            immediate: true
        }
    }
};
