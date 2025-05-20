const tpl = `
    <v-chip
        :color="value ? trueColor : falseColor"
        :variant='value ? trueVariant : falseVariant'
        :text="value ? trueText : falseText"
        @click="emitClick($event)"
    />
`;

export default {
    template: tpl,

    emits: ['click-true', 'click-false'],

    props: {
        value: {
            type: Boolean,
        },
        trueColor: {
            type: String,
            default: 'success',
        },
        falseColor: {
            type: String,
            default: 'error',
        },
        trueVariant: {
            type: String,
            default: 'flat',
        },
        falseVariant: {
            type: String,
            default: 'tonal',
        },
        trueText: {
            type: String,
            default: 'Yes',
        },
        falseText: {
            type: String,
            default: 'No',
        },
    },
    methods: {
        emitClick(event) {
            var emitEvent = this.value ? 'click-true' : 'click-false';
            this.$emit(emitEvent, event);
        },
    }
}

