<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a hospital registration cannot be completed for a reason the
 * caller can act on (duplicate hospital, invalid name, ...).
 *
 * These are safe to show to the client, unlike unexpected failures which are
 * logged and reported as a generic server error.
 */
class RegistrationException extends Exception
{
    //
}
