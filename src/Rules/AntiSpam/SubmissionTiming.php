<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\AntiSpam;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * The form was not submitted impossibly fast.
 *
 * Render a signed timestamp into the form and validate it back:
 *
 *     // in the view
 *     <input type="hidden" name="_ts" value="{{ SubmissionTiming::token() }}">
 *
 *     // in the request
 *     '_ts' => ['required', new SubmissionTiming(minimumSeconds: 3)],
 *
 * A person cannot read a form and fill it in under a few seconds; a bot posts
 * in milliseconds. Unlike a honeypot this catches bots that do render CSS.
 *
 * **The timestamp is encrypted, not plain.** A plain one is attacker-supplied
 * data: the bot posts whatever value passes, and the check becomes decoration.
 * Laravel's encrypter signs as well as encrypts, so a tampered value fails to
 * decrypt rather than decrypting to something useful.
 *
 * There is a maximum too. A token that is hours old is either a stale tab or a
 * replayed one, and both should be re-rendered rather than accepted — though
 * note this rule alone does not prevent replay WITHIN the window; that needs a
 * nonce the application records as spent.
 *
 * Pure tier — no IO. Encryption is local; the clock is not a network call.
 */
final readonly class SubmissionTiming implements ValidationRule
{
    public function __construct(
        private int $minimumSeconds = 3,
        private int $maximumSeconds = 7200,
    ) {}

    /** A token to render into the form. */
    public static function token(): string
    {
        return Crypt::encryptString((string) Date::now()
            ->getTimestamp());
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $elapsed = self::elapsed($value);

        if ($elapsed === null) {
            // Undecryptable means tampered or from another key. Same message
            // as too-fast: distinguishing them tells an attacker which lever
            // they pulled.
            $fail('laranail-validation::validation.submission_timing.too_fast')->translate();

            return;
        }

        if ($elapsed < $this->minimumSeconds) {
            $fail('laranail-validation::validation.submission_timing.too_fast')->translate();

            return;
        }

        if ($elapsed > $this->maximumSeconds) {
            $fail('laranail-validation::validation.submission_timing.expired')->translate();
        }
    }

    /** Seconds since the token was issued, or null if it is not a token we made. */
    public static function elapsed(mixed $value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $issued = Crypt::decryptString($value);
        } catch (Throwable) {
            return null;
        }

        if (preg_match('/^\d+$/', $issued) !== 1) {
            return null;
        }

        $elapsed = Date::now()
            ->getTimestamp() - (int) $issued;

        // A token from the future is a clock skew or a forgery; either way it
        // is not a measurement.
        return $elapsed < 0 ? null : $elapsed;
    }
}
