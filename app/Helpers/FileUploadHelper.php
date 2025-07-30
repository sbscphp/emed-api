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

    public static function singleStringFileUpload($requestFile, $fileKey)
    {
        // Extract base64 data and mime type from the input string
        // if (!preg_match('/^data:(.*);base64,(.*)$/', $requestFile, $matches)) {
        //     throw new \Exception('Invalid base64 file format.');
        // }

        if (!preg_match('/^data:([a-zA-Z0-9\/\-\+\.]+);base64,(.+)$/', $requestFile, $matches)) {
            throw new \Exception('Invalid base64 file format.');
        }

        $mimeType = $matches[1];
        $fileData = base64_decode($matches[2]);

        // Supported MIME types and corresponding extensions
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
        ];

        if (!isset($allowedTypes[$mimeType])) {
            throw new \Exception("Unsupported file type: $mimeType");
        }

        $extension = $allowedTypes[$mimeType];

        // Save to temp file with correct extension
        $uniqueId = rand(10, 100000);
        $tmpFilePath = sys_get_temp_dir() . '/' . $uniqueId . '_' . time() . '.' . $extension;
        file_put_contents($tmpFilePath, $fileData);

        // Get file info
        $tmpFile = new \Illuminate\Http\File($tmpFilePath);

        // Create UploadedFile instance
        $file = new \Illuminate\Http\UploadedFile(
            $tmpFile->getPathname(),
            $tmpFile->getFilename(),
            $mimeType,
            null,
            true
        );

        // Generate a clean public ID for Cloudinary (no file paths)
        $publicId = $fileKey . '/upload_' . $uniqueId . '_' . time();

        // Upload file to Cloudinary with the given public ID
        $uploadedFile = $file->storeOnCloudinaryAs($publicId);

        // Remove temporary file
        unlink($tmpFilePath);

        // Return the secure URL of the uploaded file
        return $uploadedFile->getSecurePath();
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


    public static function getFileExtension($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return strtolower($extension);
    }
}
