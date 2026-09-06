<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Builder\Nodes;

use Closure;
use Simtabi\Laranail\Validation\Regex;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Simtabi\Laranail\Validation\Contracts\FluentRuleContract;
use Simtabi\Laranail\Validation\Builder\Concerns\SelfValidates;
use Simtabi\Laranail\Validation\Builder\Concerns\HasEmbeddedRules;
use Simtabi\Laranail\Validation\Builder\Concerns\HasFieldModifiers;

class StringRule implements DataAwareRule, FluentRuleContract, ValidatorAwareRule
{
    use Conditionable;
    use HasEmbeddedRules;
    use HasFieldModifiers;
    use Macroable;
    use SelfValidates;

    /** @var list<string> */
    protected array $constraints = ['string'];

    public function __construct()
    {
        $this->seedLastConstraint('string');
    }

    public function alpha(bool $ascii = false, ?string $message = null): static
    {
        return $this->addRule($ascii ? 'alpha:ascii' : 'alpha', $message);
    }

    public function alphaDash(bool $ascii = false, ?string $message = null): static
    {
        return $this->addRule($ascii ? 'alpha_dash:ascii' : 'alpha_dash', $message);
    }

    public function alphaNumeric(bool $ascii = false, ?string $message = null): static
    {
        return $this->addRule($ascii ? 'alpha_num:ascii' : 'alpha_num', $message);
    }

    public function ascii(?string $message = null): static
    {
        return $this->addRule('ascii', $message);
    }

    public function encoding(string $encoding, ?string $message = null): static
    {
        return $this->addRule('encoding:' . $encoding, $message);
    }

    public function between(int $min, int $max, ?string $message = null): static
    {
        return $this->addRule('between:' . $min . ',' . $max, $message);
    }

    public function doesntEndWith(string ...$values): static
    {
        return $this->addRule('doesnt_end_with:' . implode(',', $values));
    }

    public function doesntStartWith(string ...$values): static
    {
        return $this->addRule('doesnt_start_with:' . implode(',', $values));
    }

    public function endsWith(string ...$values): static
    {
        return $this->addRule('ends_with:' . implode(',', $values));
    }

    public function exactly(int $value, ?string $message = null): static
    {
        return $this->addRule('size:' . $value, $message);
    }

    public function lowercase(?string $message = null): static
    {
        return $this->addRule('lowercase', $message);
    }

    public function max(int $value, ?string $message = null): static
    {
        return $this->addRule('max:' . $value, $message);
    }

    public function min(int $value, ?string $message = null): static
    {
        return $this->addRule('min:' . $value, $message);
    }

    public function startsWith(string ...$values): static
    {
        return $this->addRule('starts_with:' . implode(',', $values));
    }

    public function uppercase(?string $message = null): static
    {
        return $this->addRule('uppercase', $message);
    }

    public function url(?string $message = null): static
    {
        return $this->addRule('url', $message);
    }

    public function activeUrl(?string $message = null): static
    {
        return $this->addRule('active_url', $message);
    }

    public function uuid(?string $message = null): static
    {
        return $this->addRule('uuid', $message);
    }

    public function ulid(?string $message = null): static
    {
        return $this->addRule('ulid', $message);
    }

    public function json(?string $message = null): static
    {
        return $this->addRule('json', $message);
    }

    public function ip(?string $message = null): static
    {
        return $this->addRule('ip', $message);
    }

    public function ipv4(?string $message = null): static
    {
        return $this->addRule('ipv4', $message);
    }

    public function ipv6(?string $message = null): static
    {
        return $this->addRule('ipv6', $message);
    }

    public function macAddress(?string $message = null): static
    {
        return $this->addRule('mac_address', $message);
    }

    /**
     * The Laravel-style entry: a string pattern is used exactly as given
     * (delimiters, flags and all — your pattern, your responsibility). Also
     * accepts a {@see Regex} or a builder closure; see {@see matches()} for
     * the raw-string spelling that adds delimiters and `D` for you.
     *
     * @param string|Regex|Closure(Regex): Regex $pattern
     */
    public function regex(string|Regex|Closure $pattern, ?string $message = null): static
    {
        return $this->addRule('regex:' . (is_string($pattern) ? $pattern : $this->compileRegex($pattern)), $message);
    }

    /**
     * Match against a pattern in whichever spelling reads best — all of
     * these produce the same rule for `^\d{3}-[A-Za-z]{2}$`:
     *
     *     ->matches('^\d{3}-[A-Za-z]{2}$')              // raw, UNDELIMITED — delimiters + D added
     *     ->matches('/^\d{3}-[A-Za-z]{2}$/')            // raw, DELIMITED — used verbatim
     *     ->matches(Regex::of('^\d{3}-[A-Za-z]{2}$'))   // a pre-built Regex
     *     ->matches(fn (Regex $r) => $r->digits(3)->literal('-')->letters(2))
     *
     * The builder is never required — a team that already has a pattern
     * just uses it.
     *
     * @param string|Regex|Closure(Regex): Regex $pattern
     */
    public function matches(string|Regex|Closure $pattern, ?string $message = null): static
    {
        $compiled = is_string($pattern)
            ? Regex::of($pattern)->compile()
            : $this->compileRegex($pattern);

        return $this->addRule('regex:' . $compiled, $message);
    }

    public function notRegex(string $pattern, ?string $message = null): static
    {
        return $this->addRule('not_regex:' . $pattern, $message);
    }

    public function timezone(?string $message = null): static
    {
        return $this->addRule('timezone', $message);
    }

    public function hexColor(?string $message = null): static
    {
        return $this->addRule('hex_color', $message);
    }

    public function date(?string $message = null): static
    {
        return $this->addRule('date', $message);
    }

    public function email(string ...$modes): static
    {
        return $this->addRule($modes === [] ? 'email' : 'email:' . implode(',', $modes));
    }

    public function dateFormat(string $format, ?string $message = null): static
    {
        return $this->addRule('date_format:' . $format, $message);
    }

    public function confirmed(?string $message = null): static
    {
        return $this->addRule('confirmed', $message);
    }

    public function currentPassword(?string $guard = null, ?string $message = null): static
    {
        return $this->addRule($guard ? 'current_password:' . $guard : 'current_password', $message);
    }

    public function same(string $field, ?string $message = null): static
    {
        return $this->addRule('same:' . $field, $message);
    }

    public function different(string $field, ?string $message = null): static
    {
        return $this->addRule('different:' . $field, $message);
    }

    public function inArray(string $field, ?string $message = null): static
    {
        return $this->addRule('in_array:' . $field, $message);
    }

    public function inArrayKeys(string $field, ?string $message = null): static
    {
        return $this->addRule('in_array_keys:' . $field, $message);
    }

    public function distinct(?string $mode = null, ?string $message = null): static
    {
        return $this->addRule($mode ? 'distinct:' . $mode : 'distinct', $message);
    }

    /** @return list<string|object> */
    protected function buildValidationRules(): array
    {
        return [...$this->reorderConstraints($this->constraints), ...$this->rules];
    }

    /** @param  Regex|Closure(Regex): Regex  $pattern */
    private function compileRegex(Regex|Closure $pattern): string
    {
        if ($pattern instanceof Closure) {
            $built = $pattern(Regex::build());

            return $built->compile();
        }

        return $pattern->compile();
    }
}
