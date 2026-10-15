<?php

namespace App;

use App\ContentImporters\ContentImportAbstract;
use App\Models\Volume;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;

/**
 * Imports volume content (a Strong's dictionary ...) from third party files.
 *
 * The volume counterpart of ImportManager.  Two-dimensional: an import source (csv, excel ...)
 * is registered here, and each volume type lists the sources it supports, with the class that
 * imports them, in its IMPORT_CLASSES.  Adding a source is an entry here plus an import class
 * for each type that supports it; nothing else switches on the source.
 *
 * Checking a file (checkImportFile) takes request keys importer, content_type, file and
 * volume_id, with the importer's settings alongside them.  Importing (importFile) takes the
 * volume attributes plus _importer, _content_type, _file and _settings (JSON).
 */
class ContentImportManager
{
    use Traits\Error;

    public $test_mode = FALSE;

    public ?string $content_type = NULL; // Volume type, ie 'strongs'
    public ?string $import_source = NULL; // ie 'csv', 'excel'
    public ?string $import_class = NULL;
    public array $parsed_attributes = [];
    public ?string $sanitized_filename = NULL;

    /**
     * Content rows imported by the last importFile()
     *
     * @var int
     */
    public int $rows_imported = 0;

    /**
     * Registry of import sources accessible via HTTP request
     *
     * kind - the UI component holding the source's settings form; a kind with no component
     *        has no settings
     *
     * @var array<string, array{name: string, desc: string|array, url: string|null, ext: array<int, string>, kind: string}>
     */
    protected static $import_source_map = [
        'csv' => [
            'name'  => 'CSV',
            'desc'  => 'Imports from a .csv file.',
            'url'   => NULL,
            'ext'   => ['csv'],
            'kind'  => 'Spreadsheet',
        ],
        'excel' => [
            'name'  => 'Excel',
            'desc'  => 'Imports from an Excel spreadsheet.',
            'url'   => NULL,
            'ext'   => ['xls', 'xlsx'],
            'kind'  => 'Spreadsheet',
        ],
    ];

    protected static $import_rules = [
        '_importer'     => 'required|string',
        '_content_type' => 'required|string',
        '_file'         => 'required|string',
        '_settings'     => 'nullable|string',
    ];

    /**
     * Request keys of the check step that are not importer settings
     */
    protected const CHECK_KEYS = ['importer', 'content_type', 'file', 'volume_id', '_token'];

    /**
     * Every registered import source
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getImportSources(): array
    {
        $sources = [];

        foreach(static::$import_source_map as $key => $info) {
            if(is_array($info['desc'])) {
                $info['desc'] = implode(PHP_EOL, $info['desc']);
            }

            $info['type'] = $key;
            $sources[] = $info;
        }

        return $sources;
    }

    /**
     * Import sources available for a volume type: those it has an import class for
     *
     * @param string $content_type
     * @return array<int, array<string, mixed>>
     */
    public static function getImportersList(string $content_type): array
    {
        return array_values(array_filter(
            static::getImportSources(),
            fn($source) => static::getImportClass($source['type'], $content_type) !== NULL
        ));
    }

    /**
     * Validation rules for the import step
     *
     * @param int|null $volume_id Volume being replaced, if any
     * @param string|null $content_type
     * @return array<string, mixed>
     */
    public static function getImportRules(?int $volume_id, ?string $content_type): array
    {
        return array_replace(Volume::getUpdateRules($volume_id, $content_type), static::$import_rules);
    }

    /**
     * Import classes of a volume type: source => class
     *
     * @param string $content_type
     * @return array<string, string>
     */
    protected static function getTypeImportClasses(string $content_type): array
    {
        $VolumeTypeClass = Volume::getVolumeTypeClass($content_type);

        return $VolumeTypeClass ? $VolumeTypeClass::IMPORT_CLASSES : [];
    }

    /**
     * The import class for a source and volume type, or NULL if there is none
     *
     * @param string $import_source
     * @param string $content_type
     * @return string|null
     */
    public static function getImportClass(string $import_source, string $content_type): ?string
    {
        if(!array_key_exists($import_source, static::$import_source_map)) {
            return NULL;
        }

        $class_name = static::getTypeImportClasses($content_type)[$import_source] ?? NULL;

        if(!$class_name || !class_exists($class_name) || !is_subclass_of($class_name, ContentImportAbstract::class)) {
            return NULL;
        }

        return $class_name;
    }

    public function setImportSource(string $source): bool
    {
        $source = trim($source);

        if(!array_key_exists($source, static::$import_source_map)) {
            return $this->addError('Importer does not exist: ' . $source, 4);
        }

        $this->import_source = $source;
        return TRUE;
    }

    public function setContentType(string $type): bool
    {
        $type = trim($type);

        if(!Volume::getType($type)) {
            return $this->addError('Volume type does not exist: ' . $type, 4);
        }

        $this->content_type = $type;
        return TRUE;
    }

    /**
     * The importer for a source and volume type, with errors added if there is none
     *
     * @param mixed $import_source
     * @param mixed $content_type
     * @return ContentImportAbstract|null
     */
    public function makeImporter(mixed $import_source, mixed $content_type): ?ContentImportAbstract
    {
        if(!is_string($import_source) || $import_source === '') {
            $this->addError('Importer is required', 4);
            return NULL;
        }

        if(!is_string($content_type) || $content_type === '') {
            $this->addError('Volume type is required', 4);
            return NULL;
        }

        if(!$this->setImportSource($import_source) || !$this->setContentType($content_type)) {
            return NULL;
        }

        $this->import_class = static::getImportClass($this->import_source, $this->content_type);

        if(!$this->import_class) {
            $this->addError('This importer cannot import this volume type', 4);
            return NULL;
        }

        $Importer = new $this->import_class($this->content_type);
        $Importer->test_mode = (bool) $this->test_mode;

        return $Importer;
    }

    /**
     * Checks an uploaded file with the selected importer and settings, and stores it for import.
     *
     * @param array $data Request data; see the class description
     * @return bool
     */
    public function checkImportFile(array $data): bool
    {
        ini_set('memory_limit', '256M');

        $Importer = $this->makeImporter($data['importer'] ?? NULL, $data['content_type'] ?? NULL);

        if(!$Importer) {
            return FALSE;
        }

        if(!$Importer->setSettings(array_diff_key($data, array_flip(static::CHECK_KEYS)))) {
            return $this->addErrors($Importer->getErrors(), $Importer->getErrorLevel());
        }

        $File = $data['file'] ?? NULL;

        if(!$File instanceof UploadedFile || !$File->isValid()) {
            return $this->addError('File missing or invalid.  Please note, the maximum upload filesize is ' . Helpers::maxUploadSize(), 4);
        }

        if(!$this->fileMatchesExtension($File->getClientOriginalName())) {
            return FALSE;
        }

        if(!$Importer->acceptUploadedFile($File)) {
            return $this->addErrors($Importer->getErrors(), $Importer->getErrorLevel());
        }

        $this->sanitized_filename = $Importer->getFile();
        $this->parsed_attributes  = $this->cleanAttributes($Importer->getVolumeAttributes());

        if(!empty($data['volume_id'])) {
            $Volume = Volume::find((int) $data['volume_id']);

            if($Volume) {
                $this->parsed_attributes = array_replace($this->parsed_attributes, $Volume->attributesToArray());
            }
        }

        $this->parsed_attributes['type'] = $this->content_type;

        return !$this->hasErrors();
    }

    /**
     * Imports a checked file into a new volume, or into $Volume, replacing its content.
     *
     * A new volume is deleted again if the import fails.  A replaced volume is left
     * uninstalled: its old content is dropped before the new is read.
     *
     * @param array $data Validated request data; see the class description
     * @param Volume|null $Volume Unofficial volume to replace the content of
     * @return bool
     */
    public function importFile(array $data, ?Volume $Volume = NULL): bool
    {
        ini_set('memory_limit', '256M');

        $Importer = $this->makeImporter($data['_importer'] ?? NULL, $data['_content_type'] ?? NULL);

        if(!$Importer) {
            return FALSE;
        }

        $settings = json_decode((string) ($data['_settings'] ?? ''), TRUE);

        // `_file` is echoed back by the client and is not trusted.  Validate it before
        // creating the volume so a rejected import leaves nothing behind.
        $safe_file = $Importer->safeImportFileName($data['_file'] ?? NULL);

        if($safe_file === NULL) {
            return $this->addError('Import file is missing or invalid.  Please upload the file again.', 4);
        }

        if(!$Importer->setSettings(is_array($settings) ? $settings : [])) {
            return $this->addErrors($Importer->getErrors(), $Importer->getErrorLevel());
        }

        $attributes = array_diff_key($data, array_flip(array_merge(array_keys(static::$import_rules), ['type', 'official'])));
        $created = !$Volume;

        if($Volume) {
            if($Volume->type !== $this->content_type) {
                return $this->addError('Volume type does not match', 4);
            }

            if($Volume->official) {
                return $this->addError('Cannot overwrite official volumes', 4);
            }

            foreach(Volume::IMMUTABLE_FIELDS as $field) {
                unset($attributes[$field]);
            }
        }
        else {
            $VolumeClass = Volume::getVolumeTypeClass($this->content_type);
            $Volume = new $VolumeClass();
        }

        try {
            $Volume->fill($attributes);
            $Volume->type        = $this->content_type;
            $Volume->official    = 0;
            $Volume->importer    = $this->import_source;
            $Volume->import_file = $safe_file;
            $Volume->save();
        }
        catch(QueryException $e) {
            report($e);
            return $this->addError('Could not save the volume: ' . $e->getMessage(), 4);
        }

        if($Volume->installed) {
            $Volume->uninstall();
        }

        $Importer->setVolume($Volume);
        $Importer->setFile($safe_file);

        if(!$Importer->import()) {
            if($created) {
                $Volume->delete();
            }

            return $this->addErrors($Importer->getErrors(), $Importer->getErrorLevel());
        }

        $this->rows_imported = $Importer->getRowsImported();
        $this->parsed_attributes = $Volume->fresh()->attributesToArray();
        return TRUE;
    }

    /**
     * Whether a file name has one of the selected source's extensions, adding an error if not
     *
     * @param string $file_name
     * @return bool
     */
    protected function fileMatchesExtension(string $file_name): bool
    {
        $extensions = static::$import_source_map[$this->import_source]['ext'] ?? [];

        if(!$extensions) {
            return TRUE;
        }

        $file_name = strtolower(trim($file_name));

        foreach($extensions as $extension) {
            if(str_ends_with($file_name, '.' . strtolower($extension))) {
                return TRUE;
            }
        }

        if(count($extensions) > 1) {
            return $this->addError('Extension must be one of the following: .' . implode(', .', $extensions), 4);
        }

        return $this->addError('This importer requires an extension of .' . $extensions[0], 4);
    }

    /**
     * Volume attributes read from a file: only the type's settings fields, as valid UTF-8
     *
     * @param array $attributes
     * @return array
     */
    protected function cleanAttributes(array $attributes): array
    {
        $attributes = array_intersect_key($attributes, array_flip(Volume::getSettingsFields($this->content_type)));

        foreach($attributes as &$value) {
            if(is_string($value)) {
                $value = iconv('UTF-8', 'UTF-8//IGNORE', $value);
            }
        }

        unset($value);
        return $attributes;
    }
}
