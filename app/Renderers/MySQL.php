<?php

namespace App\Renderers;
use \DB;

class MySQL extends TextAbstract 
{
    static public $name = 'MySQL';
    static public $description = 'MySQL Database Dump';
    protected $file_extension = 'sql';
    protected $include_book_name = FALSE;

    // Maximum number of Bibles to render with the given format before detatched process is required.   Set to TRUE to never require detatched process.
    static protected $render_bibles_limit = TRUE; 

    // All render classes must have this - indicates the version number of the file.  Must be changed if the file is changed, to trigger re-rendering.
    static protected $render_version = 0.1;     

    // Estimated time to render a Bible of the given format, in seconds.
    static protected $render_est_time = 1;  
       
    // Estimated size to render a Bible of the given format, in MB. 
    static protected $render_est_size = 5;      

    static public $extras_class = Extras\MySQL::class;

    /**
     * This initializes the file, and does other pre-rendering work
     */
    protected function _renderStart() 
    {
        $this->_openFile();
        $this->mysql_table = 'bible_verses_' . $this->Bible->module;
        $copyright_statement = $this->_getCopyrightStatement(TRUE, "\n-- ");

        $header = <<<EOT

-- {$this->Bible->name}
--
-- MySQL Database Table
--
-- {$copyright_statement}


SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for {$this->mysql_table}
-- ----------------------------
DROP TABLE IF EXISTS `{$this->mysql_table}`;

CREATE TABLE `{$this->mysql_table}` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `book` tinyint(3) unsigned NOT NULL,
  `chapter` tinyint(3) unsigned NOT NULL,
  `verse` tinyint(3) unsigned NOT NULL,
  `text` text CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ixb` (`book`),
  KEY `ixc` (`chapter`),
  KEY `ixv` (`verse`),
  KEY `ixbcv` (`book`,`chapter`,`verse`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


-- ----------------------------
-- Table data for {$this->mysql_table}
-- ----------------------------

EOT;

        fwrite($this->handle, $header);
        return TRUE;
    }

    protected function _renderSingleVerse($verse) 
    {
        $i = (int) $verse->id;
        $b = (int) $verse->book;
        $c = (int) $verse->chapter;
        $v = (int) $verse->verse;

        $text = DB::connection()->getPdo()->quote($verse->text);
        $sql  = "INSERT INTO `{$this->mysql_table}` VALUES ('{$i}', '{$b}', '{$c}', '{$v}', {$text});\n";

        fwrite($this->handle, $sql);

        $this->current_book    = $verse->book;
        $this->current_chapter = $verse->chapter;
    }

    protected function _renderFinish() 
    {
        fwrite($this->handle, "\n\n");
        $this->_closeFile();
        return TRUE;
    }
}
