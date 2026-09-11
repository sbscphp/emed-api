<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;


class FileUploadHelper
{

    public static function multipleBinaryFileUpload($requestFiles, $fileKey)
    {
        $images = [];
        if (isset($requestFiles)) {
            $files = $requestFiles;
            foreach ($files as $file) {
                $uniqueId = rand(10, 100000);
                $name               = $uniqueId . '_' . date("Y-m-d") . '_' . time();
                $fileName = $file->storeOnCloudinaryAs($fileKey, $name)->getSecurePath();
                $images[]           = $fileName;
            }
        }
        return $images;
    }

    public static function singleBinaryFileUpload($requestFile, $fileKey)
    {
        $imageUrl = "";
        if (isset($requestFile)) {
            $file = $requestFile;

            $uniqueId = rand(10, 100000);
            $name = $uniqueId . '_' . date("Y-m-d") . '_' . time();
            $fileName = $file->storeOnCloudinaryAs($fileKey, $name)->getSecurePath();
            $imageUrl = $fileName;
        }
        return $imageUrl;
    }

    /**
     * The mime types a base64 payload may carry, mapped to the extension the
     * decoded file is written with.
     */
    public const ALLOWED_BASE64_TYPES = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
    ];

    /**
     * Decode a base64 file payload into its mime type, extension and bytes.
     *
     * Clients send these two ways and both are accepted: a full data URI
     * (`data:image/png;base64,iVBOR...`) and the bare payload on its own. The
     * mime type of a bare payload is sniffed from the decoded bytes, so the
     * result is trustworthy either way - a data URI that claims one type while
     * carrying another is judged on what it actually carries.
     *
     * Returns null when the value is not base64 at all or decodes to a type
     * that is not accepted, which lets callers decide whether that is a
     * validation failure or simply a value of some other shape.
     *
     * @return array{mime: string, extension: string, data: string}|null
     */
    public static function decodeBase64File(?string $value): ?array
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        // Strip the data URI wrapper when there is one. The declared mime type
        // is deliberately discarded in favour of sniffing the decoded bytes.
        if (preg_match('/^data:([a-zA-Z0-9\/\-\+\.]+);base64,(.*)$/s', $value, $matches)) {
            $value = $matches[2];
        }

        // Whitespace and newlines are legal in transported base64 but not in
        // PHP's strict decoder.
        $payload = preg_replace('/\s+/', '', $value);
        if ($payload === '' || $payload === null) {
            return null;
        }

        $data = base64_decode($payload, true);
        if ($data === false || $data === '') {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data);
        if (!$mime || !isset(self::ALLOWED_BASE64_TYPES[$mime])) {
            return null;
        }

        return [
            'mime' => $mime,
            'extension' => self::ALLOWED_BASE64_TYPES[$mime],
            'data' => $data,
        ];
    }

    public static function singleStringFileUpload($requestFile, $fileKey)
    {
        $decoded = self::decodeBase64File($requestFile);

        if (!$decoded) {
            throw new \InvalidArgumentException('The file must be a valid base64 encoded JPG, PNG, GIF or PDF.');
        }

        // Save to temp file with correct extension
        $uniqueId = rand(10, 100000);
        $tmpFilePath = sys_get_temp_dir() . '/' . $uniqueId . '_' . time() . '.' . $decoded['extension'];
        file_put_contents($tmpFilePath, $decoded['data']);

        try {
            // Get file info
            $tmpFile = new \Illuminate\Http\File($tmpFilePath);

            // Create UploadedFile instance
            $file = new \Illuminate\Http\UploadedFile(
                $tmpFile->getPathname(),
                $tmpFile->getFilename(),
                $decoded['mime'],
                null,
                true
            );

            // Generate a clean public ID for Cloudinary (no file paths)
            $publicId = $fileKey . '/upload_' . $uniqueId . '_' . time();

            // Upload file to Cloudinary with the given public ID
            $uploadedFile = $file->storeOnCloudinaryAs($publicId);

            // Return the secure URL of the uploaded file
            return $uploadedFile->getSecurePath();
        } finally {
            // Removed even when the upload throws, so a failed release does not
            // leave the decoded file behind on disk.
            if (is_file($tmpFilePath)) {
                unlink($tmpFilePath);
            }
        }
    }

    public static function multipleStringFileUpload($requestFiles, $fileKey)
    {

        $fileUrl = [];
        if (isset($requestFiles)) {
            $files = $requestFiles;
            foreach ($files as $file) {
                // decode the base64 file
                $fileData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $file));

                // save it to temporary dir first.
                $uniqueId = rand(10, 100000);
                $tmpFilePath = sys_get_temp_dir() . '/' . $uniqueId . '_' . date("Y-m-d") . '_' . time();
                file_put_contents($tmpFilePath, $fileData);

                // this just to help us get file info before we use on cloudinary.
                $tmpFile = new File($tmpFilePath);

                $file = new UploadedFile(
                    $tmpFile->getPathname(),
                    $tmpFile->getFilename(),
                    $tmpFile->getMimeType(),
                    0,
                    true
                );

                $fileName = $file->storeOnCloudinaryAs($fileKey, $tmpFilePath)->getSecurePath();

                $fileUrl[] = $fileName;
            }
        }

        return $fileUrl;
    }


    /**
     * Resolve a file that may arrive in any of the three shapes the API
     * accepts, and return the URL it is stored under.
     *
     * A multipart upload and a base64 data URI are both sent to Cloudinary; a
     * value that is already a URL is passed through untouched so that
     * re-submitting a record does not upload the same file twice.
     *
     * Returns null when the request carries no file at all, which lets the
     * caller leave an existing file in place rather than clearing it.
     */
    public static function resolveUploadedFile($request, string $fileKey, array $inputKeys = ['file', 'file_url']): ?string
    {
        foreach ($inputKeys as $key) {
            // Either field may carry either shape - clients differ on which
            // name they post a base64 payload under - so each is checked for
            // an upload first and a string second.
            if ($request->hasFile($key)) {
                return self::singleBinaryFileUpload($request->file($key), $fileKey);
            }

            $value = $request->input($key);
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $value = trim($value);

            // Already hosted, so it is stored as given rather than uploaded again.
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                return $value;
            }

            return self::singleStringFileUpload($value, $fileKey);
        }

        return null;
    }

    /**
     * The name a resolved file should be stored under - the name the client
     * uploaded it with when there is one, otherwise the name it ended up with
     * on the storage host.
     */
    public static function resolveUploadedFileName($request, ?string $storedUrl, array $inputKeys = ['file', 'file_url']): ?string
    {
        if ($request->filled('file_name')) {
            return $request->input('file_name');
        }

        foreach ($inputKeys as $key) {
            if ($request->hasFile($key)) {
                return $request->file($key)->getClientOriginalName();
            }
        }

        if (empty($storedUrl)) {
            return null;
        }

        $path = parse_url($storedUrl, PHP_URL_PATH);

        return $path ? basename($path) : null;
    }

    public static function getFileExtension($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return strtolower($extension);
    }
}
