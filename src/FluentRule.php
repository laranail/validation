<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Validation\Rules\AnyOf;
use Simtabi\Laranail\Validation\Builder\Nodes\AcceptedRule;
use Simtabi\Laranail\Validation\Builder\Nodes\ArrayRule;
use Simtabi\Laranail\Validation\Builder\Nodes\BooleanRule;
use Simtabi\Laranail\Validation\Builder\Nodes\DateRule;
use Simtabi\Laranail\Validation\Builder\Nodes\DeclinedRule;
use Simtabi\Laranail\Validation\Builder\Nodes\EmailRule;
use Simtabi\Laranail\Validation\Builder\Nodes\FieldRule;
use Simtabi\Laranail\Validation\Builder\Nodes\FileRule;
use Simtabi\Laranail\Validation\Builder\Nodes\ImageRule;
use Simtabi\Laranail\Validation\Builder\Nodes\IpAddressRule;
use Simtabi\Laranail\Validation\Builder\Nodes\MacAddressRule;
use Simtabi\Laranail\Validation\Builder\Nodes\NumericRule;
use Simtabi\Laranail\Validation\Builder\Nodes\PasswordRule;
use Simtabi\Laranail\Validation\Builder\Nodes\PhoneRule;
use Simtabi\Laranail\Validation\Builder\Nodes\StringRule;
use Simtabi\Laranail\Validation\Builder\Nodes\UrlRule;
use Simtabi\Laranail\Validation\Builder\Nodes\UsernameRule;
use Simtabi\Laranail\Validation\Rules\Net\DomainName;
use Simtabi\Laranail\Validation\Rules\Net\Subdomain;
use Simtabi\Laranail\Validation\Rules\Text\Username;

class FluentRule
{
    use Macroable;

    public static function string(?string $label = null, ?string $message = null): StringRule
    {
        $stringRule = new StringRule;
        if ($label !== null) {
            $stringRule->label($label);
        }

        if ($message !== null) {
            $stringRule->message($message);
        }

        return $stringRule;
    }

    public static function numeric(?string $label = null, ?string $message = null): NumericRule
    {
        $numericRule = new NumericRule;
        if ($label !== null) {
            $numericRule->label($label);
        }

        if ($message !== null) {
            $numericRule->message($message);
        }

        return $numericRule;
    }

    public static function integer(?string $label = null, ?string $message = null, bool $strict = false): NumericRule
    {
        return self::numeric($label)->integer(strict: $strict, message: $message);
    }

    /**
     * `message:` binds to the type-check key — `'date'` by default, or
     * `'date_format'` if `->format()` is later called (the pinned message
     * migrates automatically). Mirrors `::string(message:)` ergonomics:
     * a chained `->message()` after the factory re-targets the same key.
     * Plain `FluentRule::date()->message(...)` (no factory message) still
     * throws `LogicException` — the fail-fast guard is preserved.
     */
    public static function date(?string $label = null, ?string $message = null): DateRule
    {
        $dateRule = new DateRule($message);
        if ($label !== null) {
            $dateRule->label($label);
        }

        return $dateRule;
    }

    public static function dateTime(?string $label = null, ?string $message = null): DateRule
    {
        return self::date($label, $message)->format('Y-m-d H:i:s');
    }

    public static function boolean(?string $label = null, ?string $message = null): BooleanRule
    {
        $booleanRule = new BooleanRule;
        if ($label !== null) {
            $booleanRule->label($label);
        }

        if ($message !== null) {
            $booleanRule->message($message);
        }

        return $booleanRule;
    }

    public static function accepted(?string $label = null, ?string $message = null): AcceptedRule
    {
        $acceptedRule = new AcceptedRule;
        if ($label !== null) {
            $acceptedRule->label($label);
        }

        if ($message !== null) {
            $acceptedRule->message($message);
        }

        return $acceptedRule;
    }

    public static function declined(?string $label = null, ?string $message = null): DeclinedRule
    {
        $declinedRule = new DeclinedRule;
        if ($label !== null) {
            $declinedRule->label($label);
        }

        if ($message !== null) {
            $declinedRule->message($message);
        }

        return $declinedRule;
    }

    /** @param Arrayable<array-key, string|BackedEnum>|list<string|BackedEnum>|null $keys */
    public static function array(Arrayable|array|null $keys = null, ?string $label = null, ?string $message = null): ArrayRule
    {
        $arrayRule = new ArrayRule($keys);
        if ($label !== null) {
            $arrayRule->label($label);
        }

        if ($message !== null) {
            $arrayRule->message($message);
        }

        return $arrayRule;
    }

    public static function file(?string $label = null, ?string $message = null): FileRule
    {
        $fileRule = new FileRule;
        if ($label !== null) {
            $fileRule->label($label);
        }

        if ($message !== null) {
            $fileRule->message($message);
        }

        return $fileRule;
    }

    public static function email(?string $label = null, bool $defaults = true, ?string $message = null): EmailRule
    {
        $emailRule = new EmailRule($defaults);
        if ($label !== null) {
            $emailRule->label($label);
        }

        if ($message !== null) {
            $emailRule->message($message);
        }

        return $emailRule;
    }

    /**
     * A phone number, checked against Google's numbering-plan metadata.
     *
     * Accepts any country unless narrowed — see {@see PhoneRule::country()} and
     * {@see PhoneRule::countryFrom()}. Requires `laranail/phone`, which is suggested rather than
     * required because it carries libphonenumber's metadata.
     */
    public static function phone(?string $label = null, ?string $message = null): PhoneRule
    {
        $phoneRule = new PhoneRule;
        if ($label !== null) {
            $phoneRule->label($label);
        }

        if ($message !== null) {
            $phoneRule->message($message);
        }

        return $phoneRule;
    }

    public static function image(?string $label = null, ?string $message = null): ImageRule
    {
        $imageRule = new ImageRule;
        if ($label !== null) {
            $imageRule->label($label);
        }

        if ($message !== null) {
            $imageRule->message($message);
        }

        return $imageRule;
    }

    /**
     * `message:` is not accepted — PasswordRule emits failures under sub-keys
     * (`password.mixed`, `password.letters`, `password.numbers`, etc.) rather
     * than a bare `password` key. Target a specific Password strength rule
     * via `->messageFor('password.letters', '...')`, or use a separate
     * Laravel `messages(): array` entry.
     */
    public static function password(?int $min = null, ?string $label = null, bool $defaults = true): PasswordRule
    {
        $passwordRule = new PasswordRule($min, $defaults);

        return $label !== null ? $passwordRule->label($label) : $passwordRule;
    }

    /**
     * A URL, with the parts Laravel's `url` rule does not look at.
     *
     * Defaults to `http`/`https`, a real host, and no `user:password@`.
     * Narrow it with {@see UrlRule::secure()}, {@see UrlRule::hostIs()},
     * {@see UrlRule::publicHost()} and the rest.
     *
     * Returned a {@see StringRule} before, which meant a URL field
     * autocompleted `hexColor()` and `dateFormat()` while offering nothing
     * about schemes or hosts. A chain that only called `->url()->max(255)`
     * still compiles the same way.
     */
    public static function url(?string $label = null, ?string $message = null): UrlRule
    {
        $urlRule = new UrlRule;
        if ($label !== null) {
            $urlRule->label($label);
        }

        if ($message !== null) {
            $urlRule->message($message);
        }

        return $urlRule;
    }

    public static function uuid(?string $label = null, ?string $message = null): StringRule
    {
        return self::string($label)->uuid($message);
    }

    public static function ulid(?string $label = null, ?string $message = null): StringRule
    {
        return self::string($label)->ulid($message);
    }

    /**
     * An IP address of either family.
     *
     * {@see IpAddressRule::public()}, {@see IpAddressRule::private()} and
     * {@see IpAddressRule::inRange()} are the reason this is its own node: the
     * package already carried a careful classifier — including the
     * IPv4-mapped-v6 unwrapping most SSRF filters miss — and none of it was
     * reachable from a `StringRule`.
     */
    public static function ip(?string $label = null, ?string $message = null): IpAddressRule
    {
        $ipRule = new IpAddressRule;
        if ($label !== null) {
            $ipRule->label($label);
        }

        if ($message !== null) {
            $ipRule->message($message);
        }

        return $ipRule;
    }

    public static function ipv4(?string $label = null, ?string $message = null): IpAddressRule
    {
        return self::ip($label)->v4($message);
    }

    public static function ipv6(?string $label = null, ?string $message = null): IpAddressRule
    {
        return self::ip($label)->v6($message);
    }

    /**
     * A MAC address, with notation, scope and administration.
     *
     * See {@see MacAddressRule::universal()} for the one that matters most —
     * it is what tells a manufacturer's address from a phone's randomised one.
     */
    public static function macAddress(?string $label = null, ?string $message = null): MacAddressRule
    {
        $macRule = new MacAddressRule;
        if ($label !== null) {
            $macRule->label($label);
        }

        if ($message !== null) {
            $macRule->message($message);
        }

        return $macRule;
    }

    /**
     * A username: letters, digits and single internal separators, ASCII only.
     *
     * Carries a reserved-name list by default — see {@see UsernameRule} and
     * {@see Username::DEFAULT_RESERVED}.
     */
    public static function username(int $min = 3, int $max = 32, ?string $label = null, ?string $message = null): UsernameRule
    {
        $usernameRule = new UsernameRule($min, $max);
        if ($label !== null) {
            $usernameRule->label($label);
        }

        if ($message !== null) {
            $usernameRule->message($message);
        }

        return $usernameRule;
    }

    /**
     * A single DNS label, as used for a user-chosen subdomain.
     *
     * Rejects Punycode outright — accepting `xn--` from user input invites
     * homograph impersonation, and a subdomain someone picks for themselves is
     * exactly where that matters.
     */
    public static function subdomain(?string $label = null, ?string $message = null): StringRule
    {
        return self::string($label)->rule(new Subdomain, $message);
    }

    /** A fully-qualified domain name, internationalised names included. */
    public static function domainName(bool $requireTld = true, ?string $label = null, ?string $message = null): StringRule
    {
        return self::string($label)->rule(new DomainName($requireTld), $message);
    }

    public static function json(?string $label = null, ?string $message = null): StringRule
    {
        return self::string($label)->json($message);
    }

    public static function timezone(?string $label = null, ?string $message = null): StringRule
    {
        return self::string($label)->timezone($message);
    }

    public static function hexColor(?string $label = null, ?string $message = null): StringRule
    {
        return self::string($label)->hexColor($message);
    }

    public static function activeUrl(?string $label = null, ?string $message = null): StringRule
    {
        return self::string($label)->activeUrl($message);
    }

    public static function regex(string $pattern, ?string $label = null, ?string $message = null): StringRule
    {
        return self::string($label)->regex($pattern, $message);
    }

    public static function list(?string $label = null, ?string $message = null): ArrayRule
    {
        return self::array(label: $label)->list($message);
    }

    /** @param  class-string  $type */
    public static function enum(string $type, ?Closure $callback = null, ?string $label = null, ?string $message = null): FieldRule
    {
        return self::field($label)->enum($type, $callback, $message);
    }

    public static function field(?string $label = null): FieldRule
    {
        $fieldRule = new FieldRule;

        return $label !== null ? $fieldRule->label($label) : $fieldRule;
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    public static function anyOf(array $rules): AnyOf
    {
        return new AnyOf($rules);
    }
}
