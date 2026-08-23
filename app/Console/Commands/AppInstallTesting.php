<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible;
use App\Models\Books\BookAbstract;
use App\Models\Feature;
use App\Models\Language;

/**
 * Installs everything the PHPUnit suite expects to find in the database.
 *
 * The suite skips whole test classes when their data is missing, so a partially populated
 * database reports green while covering less than it appears to. This command is the single
 * entry point that fills every such gap: the testing Bibles, every book list, and every
 * feature.
 */
class AppInstallTesting extends Command
{
    /**
     * CSVs in dumps/bible_books that are not languages to install.
     *
     * 'template' ships as a starting point for new translations. 'art' is the throwaway
     * fixture BookAbstractTest creates and drops a table for; its content is a copy of another
     * language's list and no Bible uses the code. 'zh_cn' and 'zh_tw' are regional variants
     * with no row in `languages` - Language::getAllCodes() installs their tables from the
     * parent 'zh' language, and flagging them here would advertise book support for codes the
     * rest of the application cannot resolve.
     *
     * @var string[]
     */
    protected const EXCLUDED_BOOK_LIST_FILES = ['template', 'art', 'zh_cn', 'zh_tw'];

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:install-testing
                            {--skip-bibles : Do not install the testing Bible modules}
                            {--skip-books : Do not install the book list tables}
                            {--skip-features : Do not install the features}';

    /**
     * The console command description.
     */
    protected $description = 'Install every Bible, book list and feature the PHPUnit suite needs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if(!config('app.installed')) {
            $this->error('Application is not installed. Run `php artisan app:install` first.');
            return static::FAILURE;
        }

        $incomplete = [];

        if(!$this->option('skip-bibles') && !$this->_installBibles()) {
            $incomplete[] = 'Bibles';
        }

        if(!$this->option('skip-books') && !$this->_installBookLists()) {
            $incomplete[] = 'book lists';
        }

        if(!$this->option('skip-features') && !$this->_installFeatures()) {
            $incomplete[] = 'features';
        }

        $this->newLine();

        if($incomplete) {
            // A partial install must not read as a green CI run. The suite skips whatever is
            // missing rather than failing on it, so an exit code is the only place a gap in the
            // testing data can surface before the coverage silently disappears.
            $this->error('Testing data is incomplete: ' . implode(', ', $incomplete) . '. See the errors above.');

            return static::FAILURE;
        }

        $this->info('Testing data installed.');

        return static::SUCCESS;
    }

    /**
     * The Bible modules listed in config('bible.testing'), installed and enabled.
     *
     * bible:install has no failure exit code to report - its handle() returns NULL on every
     * path - so the outcome is verified against the modules the config asked for instead. A
     * test whose Bible is missing skips itself, which is the coverage loss this command exists
     * to prevent.
     *
     * @return bool FALSE when any configured testing Bible is not installed and enabled
     */
    protected function _installBibles(): bool
    {
        $this->info('Installing testing Bibles ...');

        $this->call('bible:install', ['--testing' => TRUE]);

        $expected = config('bible.testing') ?: [];

        $ready = Bible::whereIn('module', $expected)
                -> where('installed', 1)
                -> where('enabled', 1)
                -> pluck('module')
                -> all();

        $missing = array_diff($expected, $ready);

        if($missing) {
            $this->error('  Testing Bibles not installed and enabled (' . count($missing) . '): ' . implode(', ', $missing));

            return FALSE;
        }

        return TRUE;
    }

    /**
     * Every book list shipped as a CSV, not just the handful the testing Bibles cover.
     *
     * Tests that walk a language's book names skip themselves when the language reports no
     * book support, so each language needs its table populated *and* its 'book_list'
     * attribute set.
     *
     * @return bool FALSE when any language could not be installed
     */
    protected function _installBookLists(): bool
    {
        $this->info('Installing book lists ...');

        $languages = $this->_getBookListLanguages();
        $installed = $unavailable = [];

        $Bar = $this->output->createProgressBar(count($languages));

        $failed = [];

        foreach($languages as $language) {
            try {
                // Returns TRUE without re-importing when the table is already there, so running
                // this command repeatedly is cheap.
                BookAbstract::createTableAndMigrateFromCsv($language);

                // The book model class is generated from the table, so it can only be resolved
                // after the import. A language that still has no class cannot be queried, and
                // must not be advertised as having book support. Same for a code with no
                // `languages` row - see _setBookListSupported().
                if(!BookAbstract::getClassNameByLanguageStrict($language)) {
                    $unavailable[] = $language;
                    continue;
                }

                if(!$this->_setBookListSupported($language)) {
                    $unavailable[] = $language;
                    continue;
                }

                $installed[] = $language;
            }
            catch(\Throwable $e) {
                // One unusable language must not cost the rest of the install; the summary
                // below reports it so it cannot pass unnoticed.
                $failed[$language] = $e->getMessage();
            }
            finally {
                $Bar->advance();
            }
        }

        $Bar->finish();
        $this->newLine();
        $this->line('  Book lists installed (' . count($installed) . '): ' . implode(', ', $installed));

        if($unavailable) {
            $this->warn('  Not installable, skipped (' . count($unavailable) . '): ' . implode(', ', $unavailable));
        }

        foreach($failed as $language => $message) {
            $this->error('  Failed to install the \'' . $language . '\' book list: ' . $message);
        }

        // A skipped language counts as a failure too: its CSV ships, so something is expected to
        // install it, and every test that walks its book names skips itself instead of failing.
        // Anything deliberately not a language belongs in EXCLUDED_BOOK_LIST_FILES.
        return !$failed && !$unavailable;
    }

    /**
     * Language codes with a book list CSV to import.
     *
     * @return string[]
     */
    protected function _getBookListLanguages(): array
    {
        $files = glob(database_path('dumps/bible_books/*.csv'));
        $languages = [];

        foreach($files as $file) {
            $language = basename($file, '.csv');

            if(in_array($language, static::EXCLUDED_BOOK_LIST_FILES)) {
                continue;
            }

            $languages[] = $language;
        }

        sort($languages);

        return $languages;
    }

    /**
     * Flags a language as having a book list.
     *
     * Only a code with a row in `languages` may be flagged. The attribute is keyed by language
     * code, and Language::getAllCodes() already folds regional variants into their parent
     * language, so writing one directly would advertise support the application cannot resolve.
     *
     * @return bool FALSE when there is no language to flag
     */
    protected function _setBookListSupported(string $language): bool
    {
        $Language = Language::findByCode($language);

        if(!$Language) {
            return FALSE;
        }

        $Language->setAttr('book_list', 1);

        return TRUE;
    }

    /**
     * Every defined feature, installed and enabled - cross references among them.
     *
     * @return bool FALSE when any feature could not be installed
     */
    protected function _installFeatures(): bool
    {
        $this->info('Installing features ...');

        Feature::syncFeatures();

        $Features = Feature::orderBy('identifier')->orderBy('language')->get();
        $Bar = $this->output->createProgressBar(count($Features));

        $failed = [];

        foreach($Features as $Feature) {
            $label = $Feature->identifier . ($Feature->language ? ' (' . $Feature->language . ')' : '');

            try {
                // install() re-runs the feature's own installer, which is written to be
                // repeatable, and enabling an already-enabled feature is a no-op.
                if(!$Feature->install(TRUE)) {
                    $this->newLine();
                    $this->warn('  Could not install feature: ' . $label);
                    $failed[] = $label;
                }
            }
            catch(\Throwable $e) {
                $this->newLine();
                $this->error('  Failed to install feature ' . $label . ': ' . $e->getMessage());
                $failed[] = $label;
            }
            finally {
                $Bar->advance();
            }
        }

        $Bar->finish();
        $this->newLine();

        $enabled = Feature::where('enabled', 1)->count();
        $this->line('  Features enabled: ' . $enabled . ' of ' . count($Features));

        return !$failed;
    }
}
