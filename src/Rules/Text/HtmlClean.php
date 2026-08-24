<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Text;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A value that contains no HTML markup.
 *
 * **This is not an XSS defence, and must never be relied on as one.** Output
 * escaping is the defence: `{{ $value }}` in Blade, or an explicit
 * `e()`/`htmlspecialchars()` everywhere the value is rendered. A field that
 * passes this rule is not "safe" — it is merely not markup-shaped.
 *
 * What it is genuinely for is data shape: a display name, a job title, a
 * product name are plain text, and a `<b>` in one is a mistake worth telling
 * the user about at the point of entry rather than silently escaping forever.
 *
 * Two things it deliberately does not do:
 *
 *   - It does not reject encoded markup. `&lt;script&gt;` is text that renders
 *     as the literal characters `<script>`; it is not a tag, and rejecting it
 *     would fail anyone legitimately writing about HTML.
 *   - It does not reject a bare `<` or `>`. `5 < 10` and `a -> b` are ordinary
 *     prose, and a rule that rejected them would be wrong far more often than
 *     it was useful.
 *
 * The test is whether `strip_tags()` changes the value, which is exactly the
 * question "does this contain something a browser would parse as a tag".
 *
 * Pure tier — no IO.
 */
final readonly class HtmlClean implements ValidationRule
{
    /**
     * `mustContainHtml:` inverts the rule: the value must contain at least
     * one real tag — a rich-text field whose empty-looking submission means
     * the editor failed to load. The same subtleties hold mirrored: encoded
     * markup and a bare `<` are prose, so they do not satisfy it.
     */
    public function __construct(private bool $mustContainHtml = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->mustContainHtml) {
            if (! is_string($value) || self::passes($value)) {
                $fail('laranail-validation::validation.contains_html')->translate();
            }

            return;
        }

        if (! self::passes($value)) {
            $fail('laranail-validation::validation.html_clean')->translate();
        }
    }

    public static function passes(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return strip_tags($value) === $value;
    }
}
