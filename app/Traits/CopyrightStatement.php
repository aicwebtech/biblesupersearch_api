<?php

namespace App\Traits;

use App\Helpers;
use App\Models\Copyright;

/**
 * The generated copyright statement of a module - a Bible or a volume.
 *
 * The using model needs the copyright_statement, copyright_id, year, owner and description
 * columns, and a copyrightInfo() relation to Copyright.
 */
trait CopyrightStatement
{
    /**
     * What kind of text this is, as named in a generated statement: 'This Bible is in the
     * Public Domain.'  See Copyright::replaceSubject().
     *
     * @return string Plain text
     */
    abstract public function getCopyrightSubject(): string;

    /**
     * Generate the copyright statement for this module, sanitized.
     *
     * The year and owner belong to the text rather than to the licence, so they are prepended
     * here rather than by Copyright::getProcessedCopyrightStatement() - but only under a
     * Creative Commons licence, which is the type that requires the holder to be named and
     * the one whose statement is generated rather than stored. A public domain text has no
     * copyright holder, and leading its statement with 'Copyright (c) 1901' would contradict
     * the 'This Bible is in the Public Domain' that follows it.
     *
     * @return string
     */
    public function getCopyrightStatement(): string
    {
        // Read from the attribute array, not $this->copyright_statement: the accessor would
        // purify the column a second time
        $attributes = $this->getAttributes();

        if(!empty($attributes['copyright_statement'])) {
            return Helpers::sanitizeEditorHtmlStrict($attributes['copyright_statement']) ?? '';
        }

        if($this->copyright_id && $this->copyrightInfo) {
            $html = Copyright::replaceSubject(
                (string) $this->copyrightInfo->getProcessedCopyrightStatement(null, true),
                $this->getCopyrightSubject()
            );

            if($this->copyrightInfo->type == 'creative_commons') {
                $yp_text = null;

                $yr = $this->year;
                $ow = $this->owner;

                if(!$yr && !$ow) {
                    $yp_text = null;
                } else {
                    $yp_text = __('basic.copyright') . ' &copy;';
                    $yp_text .= ($yr) ? ' ' . $yr : '';
                    $yp_text .= ($ow) ? ' ' . $ow : '';
                }
                
                if($yp_text) {
                    $html =  $yp_text . '<br />' . $html;
                }
            }

            // Default CR statement doesn't need editor sanitization 
            return Helpers::sanitizeHtml($html) ?? '';
        }

        return Helpers::sanitizeEditorHtmlStrict($attributes['description'] ?? NULL) ?? '';
    }
}
