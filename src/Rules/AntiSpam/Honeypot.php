<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\AntiSpam;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A decoy field that must arrive empty.
 *
 *     'website' => ['nullable', new Honeypot()],
 *
 * Put a field on the form that a person never sees — hidden with CSS, not
 * `type="hidden"`, which many bots skip — and give it a name a bot will want
 * to fill, like `website` or `url`. A person leaves it empty; a form-filling
 * bot does not.
 *
 * Whitespace counts as empty. A bot that types a space is still a bot, but so
 * is a browser autofill that leaves one behind, and rejecting a real person
 * for that is the expensive error.
 *
 * `"0"` counts as filled, which `empty()` would get wrong — that is exactly
 * the value a lazy bot posts.
 *
 * Two things this is not:
 *
 * - **Not a CAPTCHA.** Any bot that renders CSS defeats it. It is a cheap
 *   first filter, and `laranail/captcha` is the real one.
 * - **Not an accessibility hazard, if you hide it properly.** A screen reader
 *   follows the accessibility tree, not the visual one, so the field needs
 *   `aria-hidden="true"` and `tabindex="-1"` as well as being visually hidden.
 *   Without those a screen-reader user fills it in and is treated as a bot.
 *
 * Pure tier — no IO.
 */
final readonly class Honeypot implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value)) {
            // Deliberately vague. Telling the sender which field gave them
            // away is telling the bot's author how to pass next time.
            $fail('laranail-validation::validation.honeypot')->translate();
        }
    }

    public static function passes(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            // Not empty(): "0" is a filled honeypot and empty() calls it empty.
            return trim($value) === '';
        }

        // An array or an object in a honeypot field is a malformed submission,
        // never a person.
        return false;
    }
}
