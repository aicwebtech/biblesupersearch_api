<?php

namespace App\Renderers\Extras;

class MySQL extends ExtrasAbstract {
    
    protected function _renderBibleBookListSingle($lang_code) {
        $filename = 'bible_books_' . $lang_code . '.sql';
        $src_file = $this->_getDBDumpDir()    . $filename;
        $dst_file = $this->getRenderFileDir() . $filename;

        if(!file_exists($dst_file) || $this->overwrite) {
            $find = '`%sbooks_' . $lang_code . '`';
            $repl = '`bible_books_' . $lang_code . '`';
            $contents = file_get_contents($src_file);
            $contents = str_replace($find, $repl, $contents);
            file_put_contents($dst_file, $contents);
        }

        return $dst_file;
    }

    protected function _renderBibleShortcutsSingle($lang_code) {
        $filename = 'shortcuts_' . $lang_code . '.sql';
        $src_file = $this->_getDBDumpDir()    . $filename;
        $dst_file = $this->getRenderFileDir() . $filename;

        if(!file_exists($dst_file) || $this->overwrite) {
            $find = '`%sshortcuts_' . $lang_code . '`';
            $repl = '`bible_shortcuts_' . $lang_code . '`';
            $contents = file_get_contents($src_file);
            $contents = str_replace($find, $repl, $contents);
            file_put_contents($dst_file, $contents);
        }

        return $dst_file;
    }

    // todo - dump strongs (from csv?)
}