<?php

namespace App\ContentImporters;

use Illuminate\Http\UploadedFile;
use App\Models\Volume;
use App\Models\Language;
use \DB;

abstract class ContentImportAbstract
{
    public $test_mode = false;

    /**
     * The import source type (e.g., 'csv', 'excel', etc.)
     *
     * @var string
     */
    protected $import_source;

    /**
     * The content type (e.g., 'strongs', 'bibles', etc.)
     *
     * @var string
     */
    protected $content_type;

    /**
     * The import class name for the specified import source
     *
     * @var string|null
     */
    protected $import_class;

    protected $overwrite = false;
    protected $insert_into_volume_table = true;
    protected $module = null;
    protected $settings = [];

    protected $volume_attributes = [];
    protected $default_dir;
    protected $debug = false;
    protected $file; // File name (no dir)
    protected $enable = TRUE; // Whether to enable the Bible for use after it has been imported
    protected $_table = NULL;
    protected $has_cli = TRUE; // Whether there is a command-line interface access to this importer
    protected $has_gui = FALSE; // Whether there is a user interface access (via the Bible manager) to this importer
    
    // todo - preserve files or no? If no, just import from tmp files and delete them. If yes, then import from a dedicated directory and leave the files there.  This is a per-importer setting.
    protected $path_short = 'misc';  // Path (inside /bibles) to where import files are located
    protected $upload_dir_short = 'uploads'; // Subdirectory of $path_short holding HTTP uploads, and nothing else.  Empty means uploads go into $path_short itself
    protected $has_dedicated_dir = NULL; // Whether or not $path_short is dedicated to this specific importer.  Defaults to TRUE if $path_short is 'misc' and FALSE otherwise

    protected $_existing = null; // true if the volume already exists, false if not, null if unknown

    /**
     *   Checks the uploaded file to make sure it works with the specific importer.
     *   Also, must parse any and all Bible metadata from the file and map them to Bible model attributes 
     *   @param Illuminate\Http\UploadedFile $File - the file to import
     *   @return bool $success
     */
    abstract public function checkUploadedFile(UploadedFile $File): bool;

    /**
     *   Imports the Volume based on the current set of settings
     *   @return bool $success
     */
    public function import() : bool
    {
        try {
            // Init transaction

            DB::beginTransaction();

            $Volume = $this->_getVolume($this->module, $this->content_type);

            if(!$this->overwrite && $this->_existing && $this->insert_into_volume_table) {
                return $this->addError('Module already exists: \'' . $this->module . '\' Use --overwrite to overwrite it.', 4);
            }

            // 1 Open the file
            $this->openFile($this->file);

            if(is_callable($this->beforeImportVolume)) {
                call_user_func($this->beforeImportVolume, $Volume);
            }

            // 2 Read the file into memory (batched)
            // 3 Map the file's data to the Volume's attributes and insert into the volume's content table
            if(!$this->_importHelper($Volume)) {
                return FALSE;
            }

            // 4 Insert the Volume into the volumes table (if $this->insert_into_volume_table)
            if($this->insert_into_volume_table) {
                $Volume->save();
            }

            if(is_callable($this->afterImportVolume)) {
                call_user_func($this->afterImportVolume, $Volume);
            }

            // 5 Close the file
            $this->closeFile();

            if($this->enable) {
                $Volume->enable();
            }

            $Language = Language::findByCode($Volume->language_id, true);
            $Language->initLanguage();

            // Commit transaction
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->addError('Import failed: ' . $e->getMessage(), 4);
        }

        return TRUE;
    }

    protected function openFile(UploadedFile $File): bool
    {
        if(!is_readable($File->getRealPath())) {
            return $this->addError('Uploaded file is not readable: ' . $File->getRealPath());
        }

        return TRUE;
    }

    protected function closeFile(): bool
    {
        return TRUE;
    }

    /**
     * Sets the user specified settings.  These are specific to each importer and some won't use this.
     * 
     * @param array $settings
     * @return bool TRUE if successful, FALSE if not
     */
    public function setSettings(array $settings) : bool
    {
        $this->settings = $settings;

        return $this->setSettingsHelper();
    }

    /** 
     * Hook for post-processing and validating custom settings
     * 
     * @return bool TRUE if valid, FALSE if not
     */
    protected function setSettingsHelper() : bool
    {
        return TRUE;
    }

    protected function _getVolume(string $module, string $content_type)
    {
        $Volume = Volume::findByTypeAndModule($content_type, $module);
        
        if($this->_existing === null) {
            $this->_existing = (bool) $Volume;
        }

        if(!$Volume) {
            $Volume = new Volume;
            $Volume->module = $module;
            $Volume->type = $content_type;
        }

        $Verses = $Volume->verses();
        $this->_table = $Verses->getTable();
        return $Volume;
    }
}