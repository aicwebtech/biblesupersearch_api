<?php

namespace App\Renderers\Extras;

class MySQL extends ExtrasAbstract {
    
    protected function _renderBibleBookListSingle($lang_code) {
        $header = <<<HEAD

DROP TABLE IF EXISTS `bible_books_{$lang_code}`;

CREATE TABLE `bible_books_{$lang_code}` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `shortname` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `matching1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `matching2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


HEAD;


        $filename = 'bible_books_' . $lang_code . '.sql';
        $src_file = $this->_getDBDumpDir()    . $filename;
        $dst_file = $this->getRenderFileDir() . $filename;

        if(!file_exists($dst_file) || $this->overwrite) {
            $find = '`%sbooks_' . $lang_code . '`';
            $repl = '`bible_books_' . $lang_code . '`';
            $contents = file_get_contents($src_file);
            $contents = str_replace($find, $repl, $contents);
            $contents = $header . $contents;
            file_put_contents($dst_file, $contents);
        }

        return $dst_file;
    }

    protected function _renderBibleShortcutsSingle($lang_code) {
        $header = <<<HEAD

DROP TABLE IF EXISTS `bible_shortcuts_{$lang_code}`;

CREATE TABLE `bible_shortcuts_{$lang_code}` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `short1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `short2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `short3` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `reference` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `display` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

HEAD;

        $filename = 'shortcuts_' . $lang_code . '.sql';
        $src_file = $this->_getDBDumpDir()    . $filename;
        $dst_file = $this->getRenderFileDir() . $filename;

        if(!file_exists($dst_file) || $this->overwrite) {
            $find = '`%sshortcuts_' . $lang_code . '`';
            $repl = '`bible_shortcuts_' . $lang_code . '`';
            $contents = file_get_contents($src_file);
            $contents = str_replace($find, $repl, $contents);
            $contents = $header . $contents;
            file_put_contents($dst_file, $contents);
        }

        return $dst_file;
    }

    protected function _renderStrongsDefinitionsHelper() {
        $filepath = $this->getRenderFileDir() . 'strongs_definitions.sql';
        $this->_dumpMysqlGeneric('strongs_definitions', 'bible_strongs_definitions', $filepath);
        return $filepath;
    }

    private function _dumpMysqlGeneric($db_table, $bk_table, $filepath) {
        file_put_contents($filepath, 'TODO - mysql dump');
    }
}