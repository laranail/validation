<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation;

use Closure;
use InvalidArgumentException;
use LogicException;
use Stringable;

/**
 * A regex that is safe by construction — and never required.
 *
 * Hand-written patterns are the most error-prone corner of validation: the
 * P6/P7 bug class was nothing but anchored patterns missing `D`, quietly
 * matching before a trailing newline. This builder makes the common
 * vocabulary readable and emits the safety a hand-written pattern forgets:
 * anchored by default, literals escaped, `D` always on, and the
 * catastrophic-backtracking shape — an unbounded quantifier nested inside
 * another — refused unless {@see dangerouslyUnbounded()} is called.
 *
 * A full raw pattern stays a first-class input everywhere a `Regex` is
 * accepted. `Regex::of()` takes one: undelimited, it gains delimiters and
 * `D`; delimited, it is used verbatim with its own flags — your pattern,
 * your responsibility, the same contract as Laravel's `regex:`. Neither
 * spelling is second-class; the builder exists for patterns you would
 * rather read than count backslashes in.
 *
 *     FluentRule::string()->matches('^\d{3}-[A-Za-z]{2}$');
 *     FluentRule::string()->matches(fn (Regex $r) => $r->digits(3)->literal('-')->letters(2));
 */
final class Regex implements Stringable
{
    /** Delimiters tried in order when wrapping an undelimited raw pattern. */
    private const array DELIMITERS = ['/', '#', '~', '%'];

    /** @var list<string> Compiled pattern fragments, in order. */
    private array $fragments = [];

    /** A pre-compiled raw pattern; set only through of(). */
    private ?string $raw = null;

    private bool $anchored = true;

    private bool $caseInsensitive = false;

    private bool $allowUnbounded = false;

    /** Whether the built body carries a top-level unbounded quantifier. */
    private bool $hasUnbounded = false;

    private function __construct() {}

    /**
     * Wrap a complete raw pattern. Undelimited input gains delimiters and
     * the `D` modifier (the end-of-string safety that prevents the
     * trailing-newline bug class); delimited input is used verbatim with
     * its own flags.
     */
    public static function of(string $pattern): self
    {
        $regex = new self();
        $regex->raw = self::normalizeRaw($pattern);

        return $regex;
    }

    /** Start a fluent build. Anchored by default; see {@see unanchored()}. */
    public static function build(): self
    {
        return new self();
    }

    public function digits(?int $count = null): self
    {
        return $this->quantified('\d', $count);
    }

    public function letters(?int $count = null): self
    {
        return $this->quantified('[A-Za-z]', $count);
    }

    /** The exact characters, escaped — metacharacters mean themselves. */
    public function literal(string $text): self
    {
        $this->fragments[] = preg_quote($text, '/');

        return $this;
    }

    /** Exactly one of the given literals. */
    public function oneOf(string ...$alternatives): self
    {
        $escaped = array_map(static fn (string $alt): string => preg_quote($alt, '/'), $alternatives);
        $this->fragments[] = '(?:' . implode('|', $escaped) . ')';

        return $this;
    }

    /** A group that may be absent. */
    public function optional(Closure|string $part): self
    {
        $this->fragments[] = $this->subPattern($part) . '?';

        return $this;
    }

    /**
     * A group repeated one or more times — an UNBOUNDED quantifier. A part
     * that itself carries an unbounded quantifier makes the catastrophic
     * `(a+)+` shape and is refused; {@see dangerouslyUnbounded()} is the
     * deliberate opt-in.
     */
    public function oneOrMore(Closure|string $part): self
    {
        $sub = $this->subPattern($part, forbidUnbounded: ! $this->allowUnbounded);
        $this->fragments[] = $sub . '+';
        $this->hasUnbounded = true;

        return $this;
    }

    /** A plain non-capturing group. */
    public function group(Closure|string $part): self
    {
        $this->fragments[] = $this->subPattern($part);

        return $this;
    }

    /** Alternation with the pattern built so far: (everything so far)|(part). */
    public function or(Closure|string $part): self
    {
        $left = implode('', $this->fragments);
        $this->fragments = ['(?:' . $left . '|' . $this->innerPattern($part) . ')'];

        return $this;
    }

    /**
     * Splice an un-audited fragment into a built pattern — the one escape
     * hatch past the safe vocabulary. The fragment is used verbatim; its
     * quantifiers count toward the nested-unbounded refusal.
     */
    public function raw(string $fragment): self
    {
        $this->fragments[] = $fragment;
        $this->hasUnbounded = $this->hasUnbounded || self::looksUnbounded($fragment);

        return $this;
    }

    /** Match anywhere in the value instead of anchoring both ends. */
    public function unanchored(): self
    {
        $this->anchored = false;

        return $this;
    }

    public function caseInsensitive(): self
    {
        $this->caseInsensitive = true;

        return $this;
    }

    /**
     * Permit unbounded quantifiers nested inside unbounded groups. The name
     * is the documentation: this is the ReDoS shape, and the server-side
     * fast path fails closed on a backtracking blowup, but every vanilla
     * validator running the pattern pays for it.
     */
    public function dangerouslyUnbounded(): self
    {
        $this->allowUnbounded = true;

        return $this;
    }

    /**
     * The complete PCRE pattern, delimiters and flags included — what
     * `regex:` receives.
     */
    public function compile(): string
    {
        if ($this->raw !== null) {
            return $this->raw;
        }

        $body = implode('', $this->fragments);

        if ($this->anchored) {
            $body = '^(?:' . $body . ')$';
        }

        $flags = 'D' . ($this->caseInsensitive ? 'i' : '');
        $pattern = self::wrap($body) . $flags;

        self::assertCompiles($pattern);

        return $pattern;
    }

    public function __toString(): string
    {
        return $this->compile();
    }

    private function quantified(string $atom, ?int $count): self
    {
        if ($count !== null && $count < 1) {
            throw new InvalidArgumentException('A count must be at least 1.');
        }

        $this->fragments[] = $atom . ($count === null ? '+' : ($count === 1 ? '' : '{' . $count . '}'));
        $this->hasUnbounded = $this->hasUnbounded || $count === null;

        return $this;
    }

    /** Build a nested part into a non-capturing group. */
    private function subPattern(Closure|string $part, bool $forbidUnbounded = false): string
    {
        $inner = $this->innerPattern($part);

        if ($forbidUnbounded && self::looksUnbounded($inner)) {
            throw new LogicException(
                'An unbounded quantifier inside an unbounded group is the catastrophic-backtracking shape. '
                . 'Bound the inner part, or opt in explicitly with dangerouslyUnbounded().',
            );
        }

        return '(?:' . $inner . ')';
    }

    private function innerPattern(Closure|string $part): string
    {
        if (is_string($part)) {
            return preg_quote($part, '/');
        }

        $sub = new self();
        $sub->allowUnbounded = $this->allowUnbounded;
        $built = $part($sub);

        if (! $built instanceof self) {
            throw new InvalidArgumentException('A builder closure must return the Regex it received.');
        }

        return implode('', $built->fragments);
    }

    /** Whether a fragment carries an unbounded quantifier (`+`, `*`, `{n,}`). */
    private static function looksUnbounded(string $fragment): bool
    {
        return preg_match('/(?<!\\\\)[+*]|\{\d+,\}/', $fragment) === 1;
    }

    /** Wrap an undelimited body with the first delimiter it does not contain. */
    private static function wrap(string $body): string
    {
        foreach (self::DELIMITERS as $delimiter) {
            if (! str_contains($body, $delimiter)) {
                return $delimiter . $body . $delimiter;
            }
        }

        throw new InvalidArgumentException(
            'The pattern contains every supported delimiter (/ # ~ %) — pass it pre-delimited instead.',
        );
    }

    /** Detect an already-delimited raw pattern: same shape the JS runner reads. */
    private static function normalizeRaw(string $pattern): string
    {
        $delimited = preg_match('/^([\/#~%])(.*)\1([a-zA-Z]*)$/s', $pattern) === 1;

        $normalized = $delimited ? $pattern : self::wrap($pattern) . 'D';

        self::assertCompiles($normalized);

        return $normalized;
    }

    /**
     * The compile-time probe: a pattern that PCRE refuses must fail at
     * DEFINITION time with the author present, not at validation time with
     * a user in front of the form.
     */
    private static function assertCompiles(string $pattern): void
    {
        if (@preg_match($pattern, '') === false) {
            throw new InvalidArgumentException(
                'The pattern does not compile: ' . $pattern . ' (' . preg_last_error_msg() . ')',
            );
        }
    }
}
