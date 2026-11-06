<?php

namespace App\Features;

/**
 * A feature action that could not be carried out, with a message for the admin.
 *
 * Feature callbacks return bool; this is how one says why it refused or failed.  Feature catches
 * it and reports the message; anything else a callback throws is a bug and propagates.
 */
class FeatureException extends \RuntimeException
{
}
