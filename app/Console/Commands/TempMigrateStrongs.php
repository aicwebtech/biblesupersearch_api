<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StrongsDefinition as Def;
use \DB;

class TempMigrateStrongs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'temp:migrate-strongs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Temp command: Migrate strongs definitions from V3';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Def::truncate();

        $strongs = DB::select("SELECT * FROM `bible_strongs`");

        foreach($strongs as $key => $row) {
            $attr = $this->_parseStrongs($row);

            $Def = new Def();
            $Def->forceFill($attr);
            $Def->save();

            if($key < 10) {
//                print_r($row);
//                print_r($attr);
                // die();
            }
        }
    }

    protected function _parseStrongs($row) {
        $attr  = [ 'number' => $row->number ];
        $entry = trim($row->entry, "^~");
	$entry = str_replace(array("^","~"), "", $entry);
        $entry = preg_replace("/(<br>)+/", '<br>', $entry);
	$ent   = explode("<br>", $entry);

	if(strpos($ent[0], "</b>") !== FALSE || strpos($ent[0], "##") !== FALSE || $ent[0] == 'Qal' || $ent[0] == 'No Tense or Voice Stated') {
            // $title = "".trim($ent[0])."<br>".trim($ent[1])."<br>".trim($ent[2])."<br>";
            // $text = "";
            $attr['tvm']   = $entry;
	}
	else {
            $attr['root_word']       = isset($ent[0]) ? $this->_parseWord($ent[0]) : NULL;
            $attr['transliteration'] = isset($ent[1]) ? $this->_parseWord($ent[1]) : NULL;
            $attr['pronunciation']   = isset($ent[2]) ? $this->_parseWord($ent[2]) : NULL;
            $attr['entry']           = isset($ent[3]) ? trim($ent[3]) : NULL;
            // $attr['entry']           = isset($ent[4]) ? trim($ent[4]) : NULL; // original parsing, with no removal of duplicate <br>
            // $title = "<span style='font-size:$ft;font-weight:bold;'>".trim($ent[0])."</span> &nbsp; ($str)<br>".trim($ent[1])." (".trim($ent[2]).")";
            // $text  = "<div style='background-color:#702424;padding:10px;color:white;text-align:justify'>".trim($ent[4])."</div>";
	}

        return $attr;
    }

    private function _parseWord($ent) {
        $ent = trim($ent);
        $ent = strip_tags($ent);
        return $ent;
    }

    private function _parseTVMC($ent) {
//        $ent = preg_replace("/<b>.*<\/b>/", '', $ent);
        return $this->_parseWord($ent);
    }
}
