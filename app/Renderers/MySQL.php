<?php

namespace App\Renderers;

class MySQL extends RenderAbstract {
    static public $name = 'MySQL';
    static public $description = 'MySQL Database Dump';

    // Maximum number of Bibles to render with the given format before detatched process is required.   Set to TRUE to never require detatched process.
    static protected $render_bibles_limit = TRUE; 

    // All render classes must have this - indicates the version number of the file.  Must be changed if the file is changed, to trigger re-rendering.
    static protected $render_version = 0.1;     

    // Estimated time to render a Bible of the given format, in seconds.
    static protected $render_est_time = 1;  
       
    // Estimated size to render a Bible of the given format, in MB. 
    static protected $render_est_size = 5;      

    protected $file_extension = 'sql';
    protected $include_book_name = FALSE;
    
    protected $handle;

    /**
     * This initializes the file, and does other pre-rendering work
     */
    protected function _renderStart() {
        $this->mysql_table = 'bible_' . $this->Bible->module;
        $metadata = $this->Bible->getMeta();
        $metadata['copyright_statement'] = $this->_getCopyrightStatement(TRUE, "\n-- ");
        $filepath = $this->getRenderFilePath(TRUE);
        $this->handle = fopen($filepath, 'w');

        fwrite($this->handle, '-- ' . $Bible->name . "\n");
        fwrite($this->handle, '-- ' . $metadata['copyright_statement'] . "\n");
        fwrite($this->handle, "\n\n");

        $create = "
            SET FOREIGN_KEY_CHECKS=0;

            -- ----------------------------
            -- Table structure for {$this->mysql_table}
            -- ----------------------------
            DROP TABLE IF EXISTS `{$this->mysql_table}`;

            CREATE TABLE `{$this->mysql_table}` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `book` tinyint(3) unsigned NOT NULL,
              `chapter` tinyint(3) unsigned NOT NULL,
              `verse` tinyint(3) unsigned NOT NULL,
              `chapter_verse` mediumint(8) unsigned NOT NULL,
              `text` text CHARACTER SET utf8 NOT NULL,
              `italics` text COLLATE utf8_unicode_ci,
              `strongs` text COLLATE utf8_unicode_ci,
              PRIMARY KEY (`id`),
              KEY `ixb` (`book`),
              KEY `ixc` (`chapter`),
              KEY `ixv` (`verse`),
              KEY `ixcv` (`book`,`chapter_verse`),
              KEY `ixbcv` (`book`,`chapter`,`verse`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ";

        fwrite($this->handle, $create);
        return TRUE;
    }

    protected function _renderSingleVerse($verse) {
        // Todo - render me!

        $this->current_book    = $verse->book;
        $this->current_chapter = $verse->chapter;
    }

    protected function _renderFinish() {
        // fwrite($this->handle, json_encode($this->data));
        fclose($this->handle);
        return TRUE;
    }
}
