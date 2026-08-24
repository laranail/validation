<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Encoding;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Number;
use Simtabi\Laranail\Validation\Support\Encoding\Base64File;

/**
 * A base64-encoded image — the payload a JavaScript cropper or canvas
 * `toDataURL()` posts. Accepts the bare encoding or the full
 * `data:image/…;base64,` URI and validates the DECODED BYTES: canonical
 * base64 first, then the MIME type sniffed from the content (`finfo`), never
 * from the data-URI label, which is attacker-written.
 *
 * `$mimes` names the accepted image subtypes; `$maxBytes` caps the decoded
 * size, and its failure message states the limit in units a person reads
 * ("2 MB", not 2097152). Size is checked before the sniff so an oversized
 * payload is rejected on length alone.
 *
 * To run Laravel's own file rules (`dimensions`, `File::image()->max()`)
 * against the same payload, bridge it with
 * {@see Base64File::toUploadedFile()}
 * — that writes a temp file, which is why it lives outside `Rules\`.
 *
 * Pure tier — the sniff reads the in-memory buffer; nothing touches disk.
 */
final readonly class Base64Image implements ValidationRule
{
    /**
     * @param  list<string>  $mimes  Accepted `image/*` subtypes.
     */
    public function __construct(
        private array $mimes = ['jpeg', 'png', 'gif', 'webp', 'bmp'],
        private ?int $maxBytes = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $bytes = self::decode($value);

        if ($bytes === null) {
            $fail('laranail-validation::validation.base64_image')->translate();

            return;
        }

        if ($this->maxBytes !== null && strlen($bytes) > $this->maxBytes) {
            $fail('laranail-validation::validation.base64_image_size')
                ->translate(['max' => Number::fileSize($this->maxBytes)]);

            return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo === false ? false : finfo_buffer($finfo, $bytes);
        $subtype = is_string($mime) && str_starts_with($mime, 'image/')
            ? substr($mime, strlen('image/'))
            : null;

        if ($subtype === null || ! in_array($subtype, $this->mimes, true)) {
            $fail('laranail-validation::validation.base64_image')->translate();
        }
    }

    /**
     * The decoded bytes, from the bare or data-URI form — null when the
     * value is not canonical base64.
     */
    public static function decode(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        if (str_starts_with($value, 'data:')) {
            $comma = strpos($value, ',');

            if ($comma === false || ! str_contains(substr($value, 0, $comma), ';base64')) {
                return null;
            }

            $value = substr($value, $comma + 1);
        }

        if (! Base64::passes($value)) {
            return null;
        }

        return (string) base64_decode($value, true);
    }
}
