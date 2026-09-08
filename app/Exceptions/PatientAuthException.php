<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a patient app request fails for a reason the patient can act on
 * (wrong hospital, expired verification, inactive membership, ...).
 *
 * The message is written to be shown in the app, and the code carries the HTTP
 * status the controller should answer with. Unexpected failures are not raised
 * this way: they are logged and reported as a generic server error.
 */
class PatientAuthException extends Exception
{
    /**
     * HTTP status to answer with, falling back to 400 for anything unusual.
     */
    public function status(): int
    {
        $status = $this->getCode();

        return ($status >= 400 && $status <= 599) ? (int) $status : 400;
    }
}
