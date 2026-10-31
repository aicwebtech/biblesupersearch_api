/**
 * Names for the volumes on this page.  On a page scoped to one type (/admin/volumes/{type}) they
 * are the type's labels; otherwise the generic 'Volume'.
 *
 *  type         - the scoped type, or null
 *  singular     - one volume, for titles
 *  plural       - several volumes, for titles
 *  short        - short name for one, for buttons and dialog titles
 *  shortPlural  - short name for several, for buttons and dialog titles
 *  singularText - one volume, within a sentence
 *  pluralText   - several volumes, within a sentence
 *
 * @param {Object} bootstrap
 * @returns {Object}
 */
export function volumeLabels(bootstrap) {
    const type = bootstrap.volume_type || null;
    const settings = type ? (bootstrap.volume_types || {})[type] : null;

    if(!settings) {
        return {
            type: null,
            singular: 'Volume',
            plural: 'Volumes',
            short: 'Volume',
            shortPlural: 'Volumes',
            singularText: 'volume',
            pluralText: 'volumes',
        };
    }

    return {
        type: type,
        singular: settings.label,
        plural: settings.label_plural,
        short: settings.label_short,
        shortPlural: settings.label_short,
        singularText: settings.label,
        pluralText: settings.label_plural,
    };
}
