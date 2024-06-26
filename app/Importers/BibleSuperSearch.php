<?php

/*
 * Allows import of Bibles in the 'Bible SuperSearch' format from 3rd party sources
 */

namespace App\Importers;
use App\Models\Bible;
use ZipArchive;
use \DB; //Todo - something is wrong with namespaces here, shouldn't this be automatically avaliable??
use Illuminate\Http\UploadedFile;

class BibleSuperSearch extends ImporterAbstract 
{
    // protected $required = ['module', 'lang', 'lang_short']; // Array of required fields

    protected $italics_st   = '[';
    protected $italics_en   = ']';
    protected $redletter_st = NULL;
    protected $redletter_en = NULL;
    protected $strongs_st   = NULL;
    protected $strongs_en   = NULL;
    protected $paragraph    = '¶ ';
    protected $path_short   = 'unofficial';
    protected $file_extensions = ['.zip'];
    protected $source = ""; // Where did you get this Bible?

    // Completely replaces parent 
    public function import() 
    {
        ini_set("memory_limit", "50M");

        $Bible = Bible::createFromModuleFile($this->module);

        if(!$Bible) {
            $Bible = $this->_getBible($this->module);
        }

        if($this->_existing) {
            $Bible->uninstall();
        }

        if($this->insert_into_bible_table) {
            // Do nothing special here - Bible SuperSearch files are always imported into the bible table
        }

        $Bible->install();

        if($this->enable) {
            $Bible->enable();
        }

        return TRUE;
    }

    protected function _importHelper(Bible &$Bible): bool 
    { 
        return TRUE; 
    } 

    public function checkUploadedFile(UploadedFile $File): bool  
    {
        $zipfile    = $File->getPathname();
        $file       = static::sanitizeFileName( $File->getClientOriginalName() );
        $Zip        = new ZipArchive();

        if($Zip->open($zipfile) === TRUE) {
            try {
                $json  = $Zip->getFromName('info.json');

                if(!$json) {
                    return $this->addError('Could not open file info.json inside of Zip file.<br />Is this a valid Bible SuperSearch file?');
                }

                $attr  = json_decode($json, TRUE);
                $this->bible_attributes = $attr;

                // Todo: figure out how to handle collision of module names in files of the Bible SuperSearch format
                // For now, bailing if the module already exists.
                $Bible = Bible::findByModule($attr['module']);

                if($Bible) {
                    return $this->addError('Cannot add Bible module \'' . $attr['module'] . '\', because it already exists.  <br />Please refresh your Bible list to find it.');
                }
                
                $this->path_short = $attr['official'] ? 'modules' : 'unofficial';
            }
            catch(\Exception $e) {
                return $this->addError('Could not read zip file: ' . $file);
            }
        }
        else {
            return $this->addError('Unable to open .zip file: ' . $file, 4);
        }

        return TRUE;
    }
}
