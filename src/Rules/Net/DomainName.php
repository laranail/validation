<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Net;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A fully-qualified domain name, per RFC 1035 as amended by RFC 5891 for
 * internationalised names.
 *
 * `filter_var($v, FILTER_VALIDATE_DOMAIN)` is not enough. It accepts a bare
 * `localhost`, ignores the total-length limit, and has nothing to say about
 * internationalised labels — so `münchen.de` and its `xn--mnchen-3ya.de`
 * A-label form get different answers from a check that should treat them as
 * the same name.
 *
 * The name is converted to ASCII first, then each label is required to be
 * either an A-label (`xn--` and resolvable back through Punycode) or an
 * NR-LDH label (letters, digits and hyphens, not leading or trailing). The
 * final label additionally may not be all-numeric, which is what keeps
 * `192.168.0.1` from validating as a domain.
 *
 * Requires `ext-intl` for internationalised input. Without it, ASCII names
 * validate normally and non-ASCII input is rejected rather than silently
 * mishandled — see {@see supportsInternationalNames()}.
 *
 * Pure tier — no IO. Nothing here asks whether the domain resolves.
 */
final readonly class DomainName implements ValidationRule
{
    /** RFC 1035: 253 characters in ASCII form, excluding the root dot. */
    private const int MAX_LENGTH = 253;

    private const int MAX_LABEL_LENGTH = 63;

    private const string NR_LDH_LABEL = '/^(?!-)[a-z0-9-]{1,63}(?<!-)$/iD';

    /**
     * @param  bool  $requireTld  Reject single-label names such as `localhost`.
     */
    public function __construct(private bool $requireTld = true) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value, $this->requireTld)) {
            $fail('laranail/validation::validation.domain_name')->translate();
        }
    }

    public static function passes(mixed $value, bool $requireTld = true): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        // A trailing dot is the explicit root and is legal; drop it before
        // splitting so it does not read as a final empty label.
        $domain = rtrim($value, '.');

        $ascii = self::toAscii($domain);

        if ($ascii === null || strlen($ascii) > self::MAX_LENGTH) {
            return false;
        }

        $labels = explode('.', $ascii);

        if ($requireTld && count($labels) < 2) {
            return false;
        }

        foreach ($labels as $label) {
            if (! self::labelIsValid($label)) {
                return false;
            }
        }

        // An all-numeric final label means this is an IP address wearing a
        // domain's shape. `1.2.3.4` must not validate as a domain name.
        return ! ctype_digit($labels[count($labels) - 1]);
    }

    public static function supportsInternationalNames(): bool
    {
        return function_exists('idn_to_ascii');
    }

    /**
     * Returns null when the value cannot be represented as ASCII at all.
     */
    private static function toAscii(string $domain): ?string
    {
        if (self::isAscii($domain)) {
            return $domain;
        }

        if (! self::supportsInternationalNames()) {
            return null;
        }

        $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

        // idn_to_ascii returns false on failure, which is why this is not
        // typed as returning a plain string.
        return $ascii === false ? null : $ascii;
    }

    private static function labelIsValid(string $label): bool
    {
        if ($label === '' || strlen($label) > self::MAX_LABEL_LENGTH) {
            return false;
        }

        if (str_starts_with(strtolower($label), 'xn--')) {
            return self::isValidALabel($label);
        }

        return preg_match(self::NR_LDH_LABEL, $label) === 1;
    }

    /**
     * An A-label is only valid if Punycode can actually decode it. `xn--`
     * followed by arbitrary text is a shape, not an encoded label.
     */
    private static function isValidALabel(string $label): bool
    {
        if (! self::supportsInternationalNames()) {
            return false;
        }

        return idn_to_utf8($label, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) !== false;
    }

    private static function isAscii(string $value): bool
    {
        return preg_match('/^[\x00-\x7F]*$/D', $value) === 1;
    }
}
