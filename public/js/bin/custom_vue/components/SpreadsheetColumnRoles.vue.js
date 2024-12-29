var tpl = `
    <v-sheet>
        <v-input label='First Row of Data' v-model='firstRow'></v-input>

        <h4>Please select the role for each column</h4>

        {{columnRoles}}

        <v-select
            v-for='n in showingColumns'
            :label="'Column ' + intToLetter(n)"
            :items='roles'
            density='compact'
            v-model = 'columnRoles[n]'
        </v-select>



        <v-chip
            @click='showingColumns ++'
            text='Add Column'
        ></v-chip>
    </v-sheet>
`;

/*
        <table>
            <tr>
                <td>Column</td>
                <td>Role</td>
            </tr>
            <tr
                v-for='n in showingColumns'

            >
                <td></td>
                <td>
                    <v-select
                        :items='roles'
                    ></v-select>
                </td>
            </tr>
        </table>

*/

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
    },
    data() {
        return {
            showingColumns: 0,
            firstRow: 2,
            columnRoles: {}
        }
    },
    methods: {
        reset() {
            this.showingColumns = this.initColumns;
        },
        intToLetter(int) {
            var charCode = int + 96,
            letter = String.fromCharCode(charCode);
            return letter.toUpperCase();
        }
    },
    watch: {
        initColumns: {
            handler(is, was) {
                this.showingColumns = is;
            },
            immediate: true
        }
    }
};

//var charCode = i + 96,
// letter = String.fromCharCode(charCode);