<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * Make a multipart/form-data body readable on PUT and PATCH.
 *
 * PHP only fills $_POST and $_FILES for POST requests. A PUT carrying
 * multipart/form-data therefore arrives with an empty body: $request->all() is
 * [], every `sometimes` rule passes because nothing is present, and an update
 * writes nothing while answering 200. The failure is silent, which is the worst
 * shape a failure can take — the client sees "updated successfully" next to the
 * value it just tried to change.
 *
 * Symfony already rescues the urlencoded case for these verbs; nothing rescues
 * multipart. This does, by parsing the raw body once and putting the fields and
 * files where the framework expects them.
 *
 * Deliberately narrow. It runs only when all three are true — the method is PUT
 * or PATCH, the content type is multipart, and the body has not already been
 * parsed by something else — so a JSON or urlencoded request never touches this
 * code, and neither does an ordinary POST upload.
 */
class ParseFormDataForPutRequests
{
    /**
     * How much of a body we are willing to parse, as a guard against a request
     * that would otherwise be read into memory whole.
     */
    protected const MAX_BYTES = 20 * 1024 * 1024;

    public function handle(Request $request, Closure $next)
    {
        if ($this->shouldParse($request)) {
            $this->parse($request);
        }

        return $next($request);
    }

    protected function shouldParse(Request $request): bool
    {
        if (!in_array($request->getRealMethod(), ['PUT', 'PATCH'], true)) {
            return false;
        }

        if (!str_contains((string) $request->header('Content-Type'), 'multipart/form-data')) {
            return false;
        }

        // Already populated — nothing to do, and re-parsing would discard it.
        return count($request->request->all()) === 0 && count($request->files->all()) === 0;
    }

    /**
     * Read the body and split it into fields and uploaded files.
     */
    protected function parse(Request $request): void
    {
        $boundary = $this->boundary((string) $request->header('Content-Type'));

        if ($boundary === null) {
            return;
        }

        $body = $request->getContent();

        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            return;
        }

        $fields = [];
        $files = [];

        foreach ($this->parts($body, $boundary) as $part) {
            [$rawHeaders, $content] = $part;

            $disposition = $this->headerValue($rawHeaders, 'content-disposition');

            if ($disposition === null || !preg_match('/\bname="([^"]*)"/i', $disposition, $m)) {
                continue;
            }

            $name = $m[1];

            if (preg_match('/\bfilename="([^"]*)"/i', $disposition, $f)) {
                $file = $this->toUploadedFile($f[1], $content, $this->headerValue($rawHeaders, 'content-type'));

                if ($file) {
                    $files[$name] = $file;
                }

                continue;
            }

            $fields[$name] = $content;
        }

        // parse_str rather than direct assignment, so bracketed names arrive as
        // the arrays the validator expects — test[0][id] and the like.
        if (!empty($fields)) {
            parse_str(http_build_query($fields), $parsed);
            $request->request = new InputBag($parsed);
        }

        if (!empty($files)) {
            $request->files = new \Symfony\Component\HttpFoundation\FileBag($files);
        }
    }

    /**
     * The boundary token the body is split on.
     */
    protected function boundary(string $contentType): ?string
    {
        if (!preg_match('/boundary="?([^";,]+)"?/i', $contentType, $m)) {
            return null;
        }

        return trim($m[1]);
    }

    /**
     * Split the body into its parts, each as [raw headers, content].
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function parts(string $body, string $boundary): array
    {
        $chunks = preg_split('/\R?--' . preg_quote($boundary, '/') . '(--)?\R?/', $body) ?: [];

        $parts = [];

        foreach ($chunks as $chunk) {
            if (trim($chunk) === '') {
                continue;
            }

            // Headers and content are separated by one blank line.
            $split = preg_split('/\R\R/', $chunk, 2);

            if (count($split) !== 2) {
                continue;
            }

            // A trailing CRLF belongs to the delimiter, not to the value.
            $parts[] = [$split[0], preg_replace('/\R$/', '', $split[1])];
        }

        return $parts;
    }

    /**
     * One header out of a part's header block.
     */
    protected function headerValue(string $rawHeaders, string $wanted): ?string
    {
        foreach (preg_split('/\R/', $rawHeaders) ?: [] as $line) {
            [$name, $value] = array_pad(explode(':', $line, 2), 2, null);

            if ($value !== null && strtolower(trim($name)) === $wanted) {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * Spool a file part to disk so it behaves like any other upload.
     */
    protected function toUploadedFile(string $filename, string $content, ?string $mime): ?UploadedFile
    {
        if ($filename === '') {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'put_upload_');

        if ($path === false) {
            return null;
        }

        file_put_contents($path, $content);

        // test: true — the file was written by us rather than by PHP's upload
        // handler, so is_uploaded_file() would reject it otherwise.
        return new UploadedFile($path, $filename, $mime ?: null, null, true);
    }
}
