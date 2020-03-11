<?php

namespace aicwebtech\BibleSuperSearch\Http\Responses;

use Illuminate\Http\Response as ParentResponse;

/**
 * Custom extension of Laravel's Reponse Class
 * To allow proper JSON handling of \stdClass objects
 *
 * @author Computer
 */
class Response extends ParentResponse {
    /**
     * Determine if the given content should be turned into JSON.
     *
     * @param  mixed  $content
     * @return bool
     */
    protected function shouldBeJson($content) {
        if($content instanceof \stdClass) {
            return TRUE;
        }

        return parent::shouldBeJson($content);
    }
}
