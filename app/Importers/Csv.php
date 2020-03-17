<?php

/**
 * Importer for CSV files
 */

namespace App\Importers;
use App\Models\Bible;
use ZipArchive;
use \DB; //Todo - something is wrong with namespaces here, shouldn't this be automatically avaliable??
use Illuminate\Http\UploadedFile;

class Csv extends SpreadsheetAbstract {

}