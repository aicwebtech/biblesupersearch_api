import Base from './Base.vue.js';

var tpl = `
    <br /><br />
    How to download Bible Analyzer .bib files: <br /><br />

    <ul class='normal_list'>
        <li>
            Install the Bible Analyzer software.<br />
            &nbsp; &nbsp; http://www.bibleanalyzer.com/download.htm
        </li>        
        <li>
            Install Bible modules.<br />
            &nbsp; &nbsp; (Bible Analyzer => Modules => Module Download Manager)
        </li>        
        <li>
            Locate the downloaded module files.<br />
            &nbsp; &nbsp; (Bible Analyzer => Tools => Display Primary Modules Folder)
        </li>
        <li>
            Navigate to the Bibles folder.<br />
            &nbsp; &nbsp; (On Windows PCs, this will be located at<br />
            &nbsp; &nbsp; &nbsp; &nbsp; C:\ProgramData\Bible Analyzer\Modules\Bible)
        </li>
        <li>
            Here you'll find the .bib files, which can be imported above.
        </li>

    </ul>
    <small>Disclaimer: Bible Analyzer is not affilated with Bible SuperSearch in any way.</small>
`;

export default {
    template: tpl,
    extends: Base,

    data() {
        return {
            name: 'Analyzer',
            hasForm: false,
            hasContent: true
        }
    },
};