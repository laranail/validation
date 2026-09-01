<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Support\Encoding;

use Illuminate\Http\UploadedFile;
use Simtabi\Laranail\Validation\Rules\Encoding\Base64Image;

/**
 * Bridges a base64 payload into an {@see UploadedFile}, so Laravel's own
 * file rules — `dimensions`, `File::image()->max()`, `mimes` — can run
 * against what a JavaScript cropper posted as text.
 *
 * Lives in `Support\`, not `Rules\`, because it writes a temp file and the
 * Pure tier forbids rules from touching disk. The file lands in the system
 * temp directory and follows PHP's temp-file lifecycle; validate first
 * (e.g. {@see Base64Image}) if the payload is untrusted, and mind the
 * decoded size — this materialises the whole payload.
 */
final class Base64File
{
    /**
     * @param  string  $name  The client filename the rules will see.
     */
    public static function toUploadedFile(mixed $value, string $name = 'upload'): ?UploadedFile
    {
        $bytes = Base64Image::decode($value);

        if ($bytes === null) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'laranail-b64');

        if ($path === false || file_put_contents($path, $bytes) === false) {
            return null;
        }

        return new UploadedFile($path, $name, test: true);
    }
}
