<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Vendor;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;

/**
 * An identifier issued by a third-party service, in that service's format.
 *
 *     new VendorIdentifier(VendorIdentifier::GOOGLE_ANALYTICS)
 *     'laranail_vendor_identifier:aws_region'
 *
 * These are the values a settings screen collects and pastes into a script
 * tag or an SDK config, where a typo surfaces days later as "why is there no
 * data". Each is a fixed, published shape, so a wrong one can be caught at the
 * point of entry rather than in a support ticket.
 *
 * One parameterised rule rather than six classes: they share everything except
 * a pattern, and a `Rules\Vendor\` directory of one-line regex classes is
 * harder to scan than one table.
 *
 * **These are format checks, not existence checks.** A well-formed Google
 * Analytics id for a property you do not own passes. Verifying ownership means
 * calling the vendor, which is Network tier and a different rule.
 *
 * Every pattern here is anchored and uses only bounded quantifiers, so none
 * can backtrack catastrophically.
 *
 * Pure tier — no IO.
 */
final readonly class VendorIdentifier implements ClientCheckable, ValidationRule
{
    /** GA4 measurement id, `G-XXXXXXXXXX`. */
    public const string GOOGLE_ANALYTICS = 'google_analytics';

    /** Google Tag Manager container, `GTM-XXXXXXX`. */
    public const string GOOGLE_TAG_MANAGER = 'google_tag_manager';

    /** Meta Pixel id: 15 or 16 digits. */
    public const string FACEBOOK_PIXEL = 'facebook_pixel';

    /** Entra / Azure AD tenant id — a UUID, or one of the named aliases. */
    public const string MICROSOFT_TENANT = 'microsoft_tenant';

    /** An AWS region code, `us-east-1`, `eu-west-3`, `ap-southeast-4`. */
    public const string AWS_REGION = 'aws_region';

    /** A Discord username under the 2023 scheme: lowercase, 2–32 chars. */
    public const string DISCORD_USERNAME = 'discord_username';

    /** @var array<string, string> */
    private const array PATTERNS = [
        self::GOOGLE_ANALYTICS => '/^G-[A-Z0-9]{10}$/',
        self::GOOGLE_TAG_MANAGER => '/^GTM-[A-Z0-9]{6,8}$/',
        self::FACEBOOK_PIXEL => '/^\d{15,16}$/',
        self::AWS_REGION => '/^[a-z]{2}(?:-gov)?-[a-z]{4,9}-\d$/',
        // No consecutive periods, and cannot start or end with one.
        self::DISCORD_USERNAME => '/^(?!.*\.\.)[a-z0-9_.]{2,32}$/',
    ];

    public function __construct(private string $vendor) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value, $this->vendor)) {
            $fail('laranail-validation::validation.vendor_identifier')
                ->translate(['vendor' => str_replace('_', ' ', mb_strtolower(trim($this->vendor)))]);
        }
    }

    public static function passes(mixed $value, string $vendor): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $value = trim($value);
        $vendor = mb_strtolower(trim($vendor));

        if ($vendor === self::MICROSOFT_TENANT) {
            return self::microsoftTenant($value);
        }

        if (! isset(self::PATTERNS[$vendor])) {
            return false;
        }

        // Case handling is per vendor, not global. Google ids are uppercase by
        // convention but pasted in any case, so they are folded up; AWS region
        // codes are lowercase BY SPECIFICATION and folding them up rejects
        // every valid one; a Discord username is lowercase by rule, so folding
        // either way would admit a value Discord itself refuses.
        $candidate = match ($vendor) {
            self::GOOGLE_ANALYTICS, self::GOOGLE_TAG_MANAGER => mb_strtoupper($value),
            default => $value,
        };

        return preg_match(self::PATTERNS[$vendor], $candidate) === 1;
    }

    /**
     * A tenant id is a UUID, but Microsoft also accepts three well-known
     * aliases wherever one is expected, and a settings field that rejected
     * `common` would reject a working configuration.
     */
    private static function microsoftTenant(string $value): bool
    {
        if (in_array(mb_strtolower($value), ['common', 'organizations', 'consumers'], true)) {
            return true;
        }

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    /**
     * The vendor's pattern, carrying the same case handling the rule applies.
     *
     * Google ids are matched case-insensitively because the rule folds them up
     * before matching; AWS and Discord are not, because folding either would
     * admit a value the vendor itself refuses. The Microsoft tenant needs an
     * alternation rather than a lookup, since three named aliases are valid
     * wherever a UUID is.
     */
    /**
     * @return list<array{rule: string, params: array<array-key, string>}>
     */
    public function clientRules(): array
    {
        $vendor = mb_strtolower(trim($this->vendor));

        if ($vendor === self::MICROSOFT_TENANT) {
            return [['rule' => 'regex', 'params' => [
                'pattern' => '/^(?:common|organizations|consumers|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i',
            ]]];
        }

        $pattern = self::PATTERNS[$vendor] ?? null;

        if ($pattern === null) {
            return [];
        }

        $foldsCase = in_array($vendor, [self::GOOGLE_ANALYTICS, self::GOOGLE_TAG_MANAGER], true);

        return [['rule' => 'regex', 'params' => ['pattern' => $foldsCase ? $pattern . 'i' : $pattern]]];
    }
}
