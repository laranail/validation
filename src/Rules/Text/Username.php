<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Text;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;

/**
 * A username: letters, digits, and single internal separators.
 *
 * Separators are allowed between characters but never at the start, at the
 * end, or doubled. That rules out the shapes people use to impersonate one
 * another — `admin.` and `admin..` and `_admin` all read as `admin` at a
 * glance, and a doubled separator is invisible in most fonts.
 *
 * ASCII only, by design. A username is an identifier people type, read aloud
 * and compare visually; allowing Unicode invites homograph impersonation
 * (`аdmin` with a Cyrillic а), which is a much worse problem here than the
 * inconvenience of an ASCII-only handle.
 *
 * ## Reserved names
 *
 * A short list ships and is on by default. Every one of them is a name that
 * causes a concrete problem rather than merely being undesirable: `admin` and
 * `support` are impersonation, `api` and `assets` collide with routes, `me`
 * and `new` collide with the conventional sub-paths of a profile URL. It is a
 * floor, not a policy — pass `reserved:` to replace it, and keep a product's
 * own list in the application where it can change without a release here.
 *
 * Matching is case-insensitive and runs against the value with its separators
 * stripped, because `a-d-m-i-n` and `ad.min` are the same claim.
 *
 * Pure tier — no IO. Availability is `unique`'s job.
 */
final readonly class Username implements ClientCheckable, ValidationRule
{
    /**
     * Names that break something rather than merely being unwanted.
     *
     * @var list<string>
     */
    public const array DEFAULT_RESERVED = [
        'admin', 'administrator', 'root', 'system', 'support', 'help', 'staff',
        'security', 'moderator', 'official', 'billing', 'postmaster', 'webmaster',
        'api', 'www', 'mail', 'ftp', 'cdn', 'assets', 'static',
        'login', 'logout', 'signin', 'signup', 'register', 'settings', 'account',
        'me', 'new', 'edit', 'delete', 'search', 'null', 'undefined', 'anonymous',
    ];

    private const string DEFAULT_SEPARATORS = '._-';

    /**
     * @param  int  $min  Fewest characters, counting separators.
     * @param  int  $max  Most characters, counting separators.
     * @param  string  $separators  Which characters may appear between others.
     * @param  bool  $lowercase  Reject uppercase rather than accepting and folding it.
     * @param  list<string>|null  $reserved  Replaces the default list; `[]` disables the check.
     */
    public function __construct(
        private int $min = 3,
        private int $max = 32,
        private string $separators = self::DEFAULT_SEPARATORS,
        private bool $lowercase = false,
        private ?array $reserved = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::pattern($this->min, $this->max, $this->separators, $this->lowercase), $value) !== 1) {
            // One message for the shape, because the shape is one idea and
            // splitting it would produce "must not start with a separator",
            // "must not contain two separators in a row" and three more, each
            // reachable only by fixing the previous one.
            $fail('laranail-validation::validation.username')->translate();

            return;
        }

        if (self::isReserved($value, $this->reserved ?? self::DEFAULT_RESERVED, $this->separators)) {
            $fail('laranail-validation::validation.username_reserved')->translate();
        }
    }

    /** @param  list<string>|null  $reserved */
    public static function passes(
        mixed $value,
        int $min = 3,
        int $max = 32,
        string $separators = self::DEFAULT_SEPARATORS,
        bool $lowercase = false,
        ?array $reserved = null,
    ): bool {
        if (! is_string($value)) {
            return false;
        }

        if (preg_match(self::pattern($min, $max, $separators, $lowercase), $value) !== 1) {
            return false;
        }

        return ! self::isReserved($value, $reserved ?? self::DEFAULT_RESERVED, $separators);
    }

    /**
     * Shape and length in one pattern, so the rule and the form advertised to
     * a browser cannot disagree.
     *
     * The length bound was once a `strlen()` check, which counts BYTES; the
     * lookahead counts characters. They cannot differ here, because the
     * character class is ASCII-only — anything that could pass has one byte
     * per character. That is what makes this rule expressible as a pattern at
     * all, and why a rule with a Unicode class could not do the same.
     *
     * @param  string  $separators  Escaped for a character class before use.
     */
    public static function pattern(
        int $min = 3,
        int $max = 32,
        string $separators = self::DEFAULT_SEPARATORS,
        bool $lowercase = false,
    ): string {
        $alphabet = $lowercase ? 'a-z0-9' : 'a-zA-Z0-9';

        if ($separators === '') {
            // No separators at all — a flat alphanumeric handle. The general
            // pattern below would still work, but this reads as what it is.
            return '/^(?=.{' . $min . ',' . $max . '}$)[' . $alphabet . ']+$/';
        }

        $class = self::escapeForCharacterClass($separators);

        // Alphanumeric at both ends; separators only between, never doubled.
        return '/^(?=.{' . $min . ',' . $max . '}$)[' . $alphabet . ']+(?:[' . $class . '][' . $alphabet . ']+)*$/';
    }

    /**
     * Whether the name claims a reserved one once its separators are removed.
     *
     * Stripping first is the point. A list containing `admin` that only
     * compared the literal value would let `a.d.m.i.n` and `ad-min` through,
     * and those are the same claim to anyone reading a profile page.
     *
     * @param  list<string>  $reserved
     */
    public static function isReserved(string $value, array $reserved, string $separators = self::DEFAULT_SEPARATORS): bool
    {
        if ($reserved === []) {
            return false;
        }

        $stripped = strtolower($separators === ''
            ? $value
            : (string) preg_replace('/[' . self::escapeForCharacterClass($separators) . ']/', '', $value));

        $literal = strtolower($value);

        foreach ($reserved as $name) {
            $name = strtolower(trim($name));

            if ($name !== '' && ($literal === $name || $stripped === $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * `preg_quote` is not enough inside a character class: it leaves `-`
     * alone, and a stray `-` between two characters there is a RANGE. A
     * separator list of `.-_` would compile to "dot through underscore",
     * silently admitting `/`, `0-9`, `:` and every other codepoint between.
     */
    private static function escapeForCharacterClass(string $characters): string
    {
        return str_replace(
            ['\\', ']', '^', '-'],
            ['\\\\', '\\]', '\\^', '\\-'],
            $characters,
        );
    }

    /**
     * The shape travels; the reserved list does not.
     *
     * A browser could check a name against a list, and it would be exporting
     * the list — which is a small disclosure with no upside, since the server
     * checks it anyway and the field is already undetermined the moment a
     * `unique` sits beside it.
     *
     * @return list<array{rule: string, params: array<array-key, string>}>
     */
    public function clientRules(): array
    {
        return [[
            'rule' => 'regex',
            'params' => ['pattern' => self::pattern($this->min, $this->max, $this->separators, $this->lowercase)],
        ]];
    }
}
