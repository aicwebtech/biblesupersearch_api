/**
 * Settings form for an import source with no settings.  A source's `kind` names its form
 * component; any kind without one falls back to this.
 */
export default {
    template: `<div></div>`,
    props: {
        settings: {
            type: Object,
            default: () => ({}),
        },
        contentType: {
            type: String,
            default: null,
        },
    },
};
