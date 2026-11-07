<?php

namespace App\ContentImporters;

use App\Helpers;
use App\Models\Volume;
use App\Models\VolumeContent\ContentBase;
use App\Traits\Error;
use App\Traits\ImportFileHandling;
use Illuminate\Http\UploadedFile;

/**
 * Base class for importing volume content (a Strong's dictionary ...) from a third party file.
 *
 * Owns the lifecycle; a subclass supplies only the file I/O (openFile, readBatches, closeFile)
 * and the mapping of a raw row to content fields (mapRow).  Each content type's formatter
 * trait supplies formatRow().
 *
 * Checking a file:  openFile → readMeta → first batch: mapRow → formatRow → validate → closeFile
 * Importing a file: install structure → openFile → readMeta → each batch: mapRow → formatRow →
 *                   validate → insert → closeFile → enable
 *
 * Nothing here assumes a spreadsheet: column mapping lives in SpreadsheetAbstract, and an
 * importer with no user settings keeps the no-op setSettingsHelper().
 *
 * Importing creates the content table, which MySQL commits implicitly, so a transaction cannot
 * undo a failed import.  A failure uninstalls the volume instead, dropping the table again.
 */
abstract class ContentImportAbstract
{
    use Error;
    use ImportFileHandling;

    /**
     * Rows checked by check()
     */
    public const CHECK_ROW_LIMIT = 200;

    /**
     * Rows read per batch when importing
     */
    public const BATCH_SIZE = 500;

    /**
     * Seconds check() and import() may run for.  A web request's default (30s) is far too
     * short to import a full dictionary: reading a 14k row .xlsx alone takes about 25s.
     */
    public const TIME_LIMIT = 300;

    public $test_mode = FALSE;

    /**
     * Volume type key, ie 'strongs'
     *
     * @var string
     */
    protected $content_type;

    /**
     * The volume being imported into
     *
     * @var Volume|null
     */
    protected $Volume = NULL;

    /**
     * Whether to enable the volume once imported
     *
     * @var bool
     */
    protected $enable = TRUE;

    /**
     * Skip rows that fail validation rather than failing the check or import.  Set per request
     * (the skip_invalid_rows setting); never kept in a preset.
     *
     * @var bool
     */
    protected $skip_invalid_rows = FALSE;

    /**
     * Rows skipped by the last check() or import(): position => reasons
     *
     * @var array<int|string, array<int, string>>
     */
    protected $skipped_rows = [];

    /**
     * Reasons the row being processed cannot be imported, from mapRow(); see addRowError()
     *
     * @var array<int, string>
     */
    protected $_row_errors = [];

    /**
     * User-selectable settings, specific to each importer
     *
     * @var array<string, mixed>
     */
    protected $settings = [];

    /**
     * Volume attributes read from the file by readMeta(), to pre-populate the volume record
     *
     * @var array<string, mixed>
     */
    protected $volume_attributes = [];

    /**
     * Content rows imported by the last import()
     *
     * @var int
     */
    protected $rows_imported = 0;

    protected $file; // File name (no dir)
    protected $path_short = NULL; // Directory inside content/; the content type
    protected $upload_dir_short = 'uploads'; // Subdirectory of $path_short holding HTTP uploads
    protected $upload_disk = 'content'; // Filesystem disk uploads are stored on; see ImportFileHandling

    /**
     * Values seen so far for each of the content type's unique fields: field => value => row
     *
     * @var array<string, array<string, int|string>>
     */
    protected $_unique_seen = [];

    /**
     * @param string $content_type Volume type key
     * @throws \InvalidArgumentException For an unregistered type
     */
    public function __construct(string $content_type)
    {
        if(!Volume::getType($content_type) || !Volume::getContentClassName($content_type)) {
            throw new \InvalidArgumentException('Unknown volume type: ' . $content_type);
        }

        $this->content_type = $content_type;
        $this->path_short   = $content_type;
    }

    /**
     * Opens the file for reading
     *
     * @param string $path Full path
     * @return bool
     */
    abstract protected function openFile(string $path): bool;

    /**
     * Reads the content rows, in batches
     *
     * Each batch is keyed by the row's position in the file, for messages.
     *
     * @param int $size Rows per batch
     * @return \Generator<int, array<int|string, array>>
     */
    abstract protected function readBatches(int $size): \Generator;

    abstract protected function closeFile(): void;

    /**
     * Maps one raw row from the file to content fields
     *
     * @param array $raw
     * @param int|string $row Row position, for messages
     * @return array<string, mixed>
     */
    abstract protected function mapRow(array $raw, int|string $row): array;

    /**
     * Volume attributes held in the file itself, if the format has any
     *
     * @return array<string, mixed>
     */
    protected function readMeta(): array
    {
        return [];
    }

    /**
     * Cleans up a mapped row.  Supplied by the content type's formatter trait.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function formatRow(array $row): array
    {
        return $row;
    }

    public function getImportDir(): string
    {
        return Volume::getModulePath($this->content_type);
    }

    /**
     * Uploads only.  The importer's own directory is the type's official module directory
     * (content/<type>/), not a place an operator stages sources: a `_file` from the client must
     * not reach a shipped module file there.
     *
     * @return array<int, string>
     */
    protected function importFileSearchDirs(): array
    {
        return [$this->getUploadDir()];
    }

    /**
     * Always the upload directory, as importFileSearchDirs()
     */
    public function getImportFileDir(): string
    {
        return $this->getUploadDir();
    }

    /**
     * @return class-string<ContentBase>
     */
    public function getContentClass(): string
    {
        return Volume::getContentClassName($this->content_type);
    }

    public function getContentType(): string
    {
        return $this->content_type;
    }

    public function setVolume(Volume $Volume): void
    {
        $this->Volume = $Volume;
    }

    public function setFile(?string $file): void
    {
        $this->file = $file;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setEnable(bool $enable): void
    {
        $this->enable = $enable;
    }

    public function getVolumeAttributes(): array
    {
        return $this->volume_attributes;
    }

    public function getRowsImported(): int
    {
        return $this->rows_imported;
    }

    /**
     * Rows the last check() or import() skipped as invalid: position => reasons
     *
     * @return array<int|string, array<int, string>>
     */
    public function getSkippedRows(): array
    {
        return $this->skipped_rows;
    }

    /**
     * Sets the user-specified settings.  These are specific to each importer and some won't use them.
     *
     * @param array|null $settings
     * @return bool TRUE if valid
     */
    public function setSettings(?array $settings): bool
    {
        $this->settings = $settings ?? [];
        $this->skip_invalid_rows = filter_var($this->settings['skip_invalid_rows'] ?? FALSE, FILTER_VALIDATE_BOOLEAN);

        return $this->setSettingsHelper();
    }

    /**
     * Hook for post-processing and validating custom settings
     *
     * @return bool TRUE if valid
     */
    protected function setSettingsHelper(): bool
    {
        return TRUE;
    }

    /**
     * Checks an uploaded file; see check()
     */
    public function checkUploadedFile(UploadedFile $File): bool
    {
        return $this->check($File->getPathname());
    }

    /**
     * Raises the execution time limit to TIME_LIMIT.  Never lowers it: an unlimited (0) or
     * longer limit, as on the CLI, is left alone.
     *
     * @return void
     */
    protected static function extendTimeLimit(): void
    {
        $current = (int) ini_get('max_execution_time');

        if($current > 0 && $current < static::TIME_LIMIT) {
            set_time_limit(static::TIME_LIMIT);
        }
    }

    /**
     * Checks that the file can be imported with the current settings, from its first rows,
     * and reads any volume metadata it holds.
     *
     * @param string $path
     * @return bool
     */
    public function check(string $path): bool
    {
        static::extendTimeLimit();
        $this->_unique_seen = [];
        $this->skipped_rows = [];
        $this->volume_attributes = [];

        if(!$this->openFile($path)) {
            return FALSE;
        }

        $rows = 0;

        try {
            $this->volume_attributes = $this->readMeta();

            foreach($this->readBatches(static::CHECK_ROW_LIMIT) as $batch) {
                foreach($batch as $position => $raw) {
                    $rows += $this->processRow($raw, $position) === NULL ? 0 : 1;

                    if($this->hasErrors()) {
                        break 2;
                    }
                }

                break; // One batch is enough to check
            }
        }
        catch(\Throwable $e) {
            return $this->addError('Could not read file: ' . $e->getMessage(), 4);
        }
        finally {
            $this->closeFile();
        }

        if(!$this->hasErrors() && $rows == 0) {
            $this->addError($this->noContentMessage(), 4);
        }

        return !$this->hasErrors();
    }

    /**
     * Imports the file into the volume set with setVolume().
     *
     * The volume must be saved and not installed.  Its content table is created here and
     * dropped again (the volume uninstalled) if the import fails.
     *
     * @return bool
     */
    public function import(): bool
    {
        static::extendTimeLimit();
        $this->_unique_seen  = [];
        $this->skipped_rows  = [];
        $this->rows_imported = 0;

        if(!$this->Volume || !$this->Volume->exists) {
            return $this->addError('No volume to import into', 4);
        }

        if($this->Volume->type !== $this->content_type) {
            return $this->addError('Volume type does not match this importer', 4);
        }

        if($this->Volume->installed) {
            return $this->addError('Volume is already installed; uninstall it before importing', 4);
        }

        $path = $this->resolveImportFile($this->file);

        if($path === NULL) {
            return $this->addError('Import file is missing or invalid.  Please upload the file again.', 4);
        }

        if(!$this->Volume->install(TRUE)) {
            return $this->addErrors($this->Volume->getErrors(), 4);
        }

        try {
            $this->rows_imported = $this->importFromPath($path);
        }
        catch(\Throwable $e) {
            report($e);
            $this->addError('Import failed: ' . $e->getMessage(), 4);
        }

        if($this->hasErrors()) {
            $this->Volume->uninstall();
            return FALSE;
        }

        if($this->enable) {
            $this->Volume->enable();
        }

        return TRUE;
    }

    /**
     * @param string $path
     * @return int Rows inserted; 0 with an error added on failure
     */
    protected function importFromPath(string $path): int
    {
        if(!$this->openFile($path)) {
            return 0;
        }

        $content_class = $this->getContentClass();
        $Content = $this->Volume->content();
        $Table   = $Content->getConnection()->table($Content->getTable());
        $chunk   = Helpers::getInsertChunkSize(count($content_class::prepareImportRow([])), $Content->getConnectionName());
        $count   = 0;

        try {
            $this->volume_attributes = $this->readMeta();

            foreach($this->readBatches(static::BATCH_SIZE) as $batch) {
                $insertable = [];

                foreach($batch as $position => $raw) {
                    $row = $this->processRow($raw, $position);

                    if($this->hasErrors()) {
                        return 0;
                    }

                    if($row !== NULL) {
                        $insertable[] = $content_class::prepareImportRow($row);
                    }
                }

                foreach(array_chunk($insertable, $chunk) as $rows) {
                    $Table->insert($rows);
                }

                $count += count($insertable);
            }
        }
        finally {
            $this->closeFile();
        }

        if($count == 0) {
            $this->addError($this->noContentMessage(), 4);
        }

        return $count;
    }

    /**
     * Maps, formats and validates one raw row.  An invalid row is skipped when skipping is on,
     * otherwise its reasons are added as fatal errors.
     *
     * @param array $raw
     * @param int|string $position
     * @return array<string, mixed>|null The row, or NULL for a blank, invalid or skipped row
     */
    protected function processRow(array $raw, int|string $position): ?array
    {
        $this->_row_errors = [];
        $mapped = $this->mapRow($raw, $position);

        if($this->hasErrors()) {
            return NULL;
        }

        $errors = $this->_row_errors;

        if(!$errors && !array_filter($mapped, fn($value) => $value !== NULL && $value !== '')) {
            return NULL;
        }

        $row = NULL;
        $content_class = $this->getContentClass();
        $unique = [];

        if(!$errors) {
            $row = $this->formatRow($mapped);

            foreach($content_class::validateImportRow($row) as $error) {
                $errors[] = 'Row ' . $position . ': ' . $error;
            }

            foreach($content_class::IMPORT_UNIQUE_FIELDS as $field) {
                $value = $row[$field] ?? NULL;

                if($value === NULL || $value === '') {
                    continue;
                }

                if(isset($this->_unique_seen[$field][$value])) {
                    $errors[] = 'Row ' . $position . ': Duplicate ' . $field . ' \'' . $value . '\', also on row ' . $this->_unique_seen[$field][$value];
                }
                else {
                    $unique[$field] = $value;
                }
            }
        }

        if($errors) {
            if($this->skip_invalid_rows) {
                $this->skipped_rows[$position] = $errors;
            }
            else {
                $this->addErrors($errors, 4);
            }

            return NULL;
        }

        // Only a row that is imported claims its unique values
        foreach($unique as $field => $value) {
            $this->_unique_seen[$field][$value] = $position;
        }

        return $row;
    }

    /**
     * Records why the row being processed cannot be imported.  For mapRow(): processRow()
     * decides whether that skips the row or fails the import.
     */
    protected function addRowError(string $message): void
    {
        $this->_row_errors[] = $message;
    }

    protected function noContentMessage(): string
    {
        $message = 'No content found in this file with the given settings';

        return $this->skipped_rows ? $message . ' (' . count($this->skipped_rows) . ' invalid row(s) skipped)' : $message;
    }
}
