<?php

namespace App\Rules;

use App\Helpers\FileUploadHelper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * A result document, in whichever shape the client chose to send it.
 *
 * The API accepts the same document three ways, because the clients that post
 * to it differ: a web form sends a multipart upload, a mobile client sends
 * base64 (with or without the `data:` prefix), and a re-submitted record sends
 * back the URL it was already stored under. All three are valid here, so the
 * rule identifies which one it is holding and judges it on its own terms
 * rather than rejecting everything that is not an upload.
 *
 * Base64 is judged on the bytes it decodes to, not on the type it claims, so a
 * payload that says `image/png` while carrying something else is rejected.
 */
class ResultFile implements ValidationRule
{
    /**
     * Extensions an uploaded file may have, kept in step with the mime types
     * accepted for base64 payloads.
     */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

    /**
     * The largest document accepted, in kilobytes.
     */
    private int $maxKilobytes;

    public function __construct(int $maxKilobytes = 10240)
    {
        $this->maxKilobytes = $maxKilobytes;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof UploadedFile) {
            $this->validateUpload($value, $fail);
            return;
        }

        if (is_string($value) && trim($value) !== '') {
            $this->validateString(trim($value), $fail);
            return;
        }

        $fail('The result file must be an uploaded file, a base64 encoded file, or a file URL.');
    }

    private function validateUpload(UploadedFile $file, Closure $fail): void
    {
        if (!$file->isValid()) {
            $fail('The result file failed to upload.');
            return;
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $fail('The result file must be a JPG, PNG, GIF or PDF.');
            return;
        }

        if ($file->getSize() > $this->maxKilobytes * 1024) {
            $fail($this->tooLargeMessage());
        }
    }

    private function validateString(string $value, Closure $fail): void
    {
        // A document that is already hosted is stored as given, so there is
        // nothing to decode or measure.
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return;
        }

        $decoded = FileUploadHelper::decodeBase64File($value);
        if (!$decoded) {
            $fail('The result file must be a valid base64 encoded JPG, PNG, GIF or PDF, or a file URL.');
            return;
        }

        if (strlen($decoded['data']) > $this->maxKilobytes * 1024) {
            $fail($this->tooLargeMessage());
        }
    }

    private function tooLargeMessage(): string
    {
        return 'The result file may not be larger than ' . (int) ($this->maxKilobytes / 1024) . 'MB.';
    }
}
