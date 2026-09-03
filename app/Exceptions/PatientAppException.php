<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a patient app request fails for a reason the patient can act on
 * (an appointment that is too close to move, a slot somebody else has just
 * taken, a check in tapped a day early, ...).
 *
 * The message is written to be shown in the app as it is, and the code carries
 * the HTTP status the controller should answer with. Unexpected failures are
 * not raised this way: they are logged and reported as a generic server error.
 *
 * @see \App\Exceptions\PatientAuthException for the sign in equivalent.
 */
class PatientAppException extends Exception
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
