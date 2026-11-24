/**
 * Items for a default Strong's dictionary v-select: the empty choice, then the installed and
 * enabled dictionaries (bootstrap.strongs_dictionaries) grouped by language, under a heading per
 * language.
 *
 * v-select (Vuetify 3.7) has no subheader items of its own, so the headings are items flagged
 * 'header' and disabled, which the select's item slot draws as a non-selectable subheader.
 *
 * @param {Object} bootstrap Page bootstrap, with strongs_dictionaries and languages
 * @param {String} emptyTitle Title of the empty (null) choice
 * @returns {Array<Object>}
 */
export function strongsDictionaryOptions(bootstrap, emptyTitle) {
    var list = [{value: null, title: emptyTitle}];
    var groups = {};

    for(var dict of (bootstrap.strongs_dictionaries || [])) {
        var language = (bootstrap.languages || []).find(lang => lang.code === dict.language);
        var name = language ? language.name : dict.language;

        groups[name] = groups[name] || [];
        groups[name].push({value: dict.module, title: dict.name});
    }

    for(var name of Object.keys(groups).sort((a, b) => a.localeCompare(b))) {
        list.push({value: '__header__' + name, title: name, header: true, disabled: true});
        list.push(...groups[name]);
    }

    return list;
}
