/**
 * Number of spreadsheet columns a settings object maps: the highest col_X key, as a 1-based
 * column number (col_a => 1, col_AA => 27).  0 when there are none.
 *
 * @param {Object} settings
 * @return {Number}
 */
export function columnsInSettings(settings) {
    let max = 0;

    Object.keys(settings || {}).forEach(key => {
        const match = /^col_([a-z]+)$/i.exec(key);

        if(!match) {
            return;
        }

        let number = 0;

        match[1].toUpperCase().split('').forEach(char => {
            number = number * 26 + (char.charCodeAt(0) - 64);
        });

        max = Math.max(max, number);
    });

    return max;
}
