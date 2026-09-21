<?php

namespace App\Exceptions;

use Exception;

/**
 * A render stage reported failure by returning falsy rather than by throwing.
 *
 * Internal to App\Renderers\RenderAbstract::render(), which raises it so the falsy return
 * takes the same cleanup path as a throw and then catches it again, answering its caller with
 * FALSE as it always has. It is never allowed to escape render().
 */
class RenderStageFailedException extends Exception
{
    /**
     * @param string $stage the hook that refused, e.g. '_renderStart'
     */
    public function __construct(protected string $stage)
    {
        parent::__construct($stage . '() reported failure');
    }

    public function getStage(): string
    {
        return $this->stage;
    }
}
