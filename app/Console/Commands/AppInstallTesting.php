<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Books\BookAbstract;
use App\Models\Feature;
use App\Models\Language;
use App\Models\LanguageAttr;

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

        if(!$this->option('skip-bibles')) {
            $this->_installBibles();
        }

        if(!$this->option('skip-books')) {
            $this->_installBookLists();
        }

        if(!$this->option('skip-features')) {
            $this->_installFeatures();
        }

        $this->newLine();
        $this->info('Testing data installed.');

        return static::SUCCESS;
    }

    /**
     * The Bible modules listed in config('bible.testing'), installed and enabled.
     */
    protected function _installBibles(): void
    {
        $this->info('Installing testing Bibles ...');
        $this->call('bible:install', ['--testing' => TRUE]);
    }

    /**
     * Every book list shipped as a CSV, not just the handful the testing Bibles cover.
     *
     * Tests that walk a language's book names skip themselves when the language reports no
     * book support, so each language needs its table populated *and* its 'book_list'
     * attribute set.
     */
    protected function _installBookLists(): void
    {
        $this->info('Installing book lists ...');

        $languages = $this->_getBookListLanguages();
        $installed = $unavailable = [];

        $Bar = $this->output->createProgressBar(count($languages));

        foreach($languages as $language) {
            // Returns TRUE without re-importing when the table is already there, so running
            // this command repeatedly is cheap.
            BookAbstract::createTableAndMigrateFromCsv($language);

            // The book model class is generated from the table, so it can only be resolved
            // after the import. A language that still has no class cannot be queried, and must
            // not be advertised as having book support.
            if(!BookAbstract::getClassNameByLanguageStrict($language)) {
                $unavailable[] = $language;
                $Bar->advance();
                continue;
            }

            $this->_setBookListSupported($language);
            $installed[] = $language;
            $Bar->advance();
        }

        $Bar->finish();
        $this->newLine();
        $this->line('  Book lists installed (' . count($installed) . '): ' . implode(', ', $installed));

        if($unavailable) {
            $this->warn('  No book model class, skipped (' . count($unavailable) . '): ' . implode(', ', $unavailable));
        }
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

            // Shipped as a starting point for new translations, not a language.
            if($language === 'template') {
                continue;
            }

            $languages[] = $language;
        }

        sort($languages);

        return $languages;
    }

    /**
     * Flags a language as having a book list.
     */
    protected function _setBookListSupported(string $language): void
    {
        $Language = Language::findByCode($language);

        if($Language) {
            $Language->setAttr('book_list', 1);
            return;
        }

        // Regional codes such as 'zh_tw' ship a book list and a permanent model class but have
        // no row in `languages`, so the attribute has to be written directly.
        LanguageAttr::updateOrCreate(
            ['code' => $language, 'attribute' => 'book_list'],
            ['value' => 1]
        );
    }

    /**
     * Every defined feature, installed and enabled - cross references among them.
     */
    protected function _installFeatures(): void
    {
        $this->info('Installing features ...');

        Feature::syncFeatures();

        $Features = Feature::orderBy('identifier')->orderBy('language')->get();
        $Bar = $this->output->createProgressBar(count($Features));

        foreach($Features as $Feature) {
            $label = $Feature->identifier . ($Feature->language ? ' (' . $Feature->language . ')' : '');

            // install() re-runs the feature's own installer, which is written to be repeatable,
            // and enable() is a no-op on a feature that is already enabled.
            if(!$Feature->install(TRUE)) {
                $this->newLine();
                $this->warn('  Could not install feature: ' . $label);
            }

            $Bar->advance();
        }

        $Bar->finish();
        $this->newLine();

        $enabled = Feature::where('enabled', 1)->count();
        $this->line('  Features enabled: ' . $enabled . ' of ' . count($Features));
    }
}
