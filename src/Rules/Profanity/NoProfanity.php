<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Profanity;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Normalizer;
use Simtabi\Laranail\Validation\Contracts\TermList;

/**
 * The value contains none of a supplied list of terms.
 *
 *     new NoProfanity(['badword'], allowed: ['scunthorpe'])
 *     new NoProfanity($myTermList)          // any Contracts\TermList
 *
 * **No word list ships with this package.** See {@see TermList} for why —
 * briefly: the usable sources are LGPL or unlicensed, and what counts as
 * unacceptable is a product decision that differs by audience and changes over
 * time. What ships is the matching, which is the part naive implementations
 * get wrong.
 *
 * The matching does three things a `str_contains` loop does not:
 *
 * 1. **Folds character substitution.** `b4dger`, `b@dger` and `ｂａｄｇｅｒ` are
 *    the same word to a reader and must be to the rule, or the list is
 *    decorative. Handled by normalising the VALUE.
 * 2. **Tolerates separators and repeats, without losing word boundaries.**
 *    `b.a.d.g.e.r` and `baaaadger` must match; `assess` and `class` must NOT
 *    match a term of `ass`. Those pull in opposite directions — stripping
 *    separators to catch the first destroys the boundary that protects the
 *    second — so this is handled in the PATTERN rather than by rewriting the
 *    value: each character may repeat, separators may appear between
 *    characters, and the whole thing is anchored on word boundaries.
 * 3. **Honours an allow-list**, applied BEFORE the terms, for the real words
 *    that genuinely contain one — the Scunthorpe problem, and the single most
 *    common way these rules become a bug report from a real user.
 *
 * It is a filter, not a moderation system. Anyone determined to get a word
 * past it will; the point is to catch the careless case without insulting the
 * innocent one.
 *
 * Pure tier — no IO.
 */
final class NoProfanity implements ValidationRule
{
    /** @var list<string>|null */
    private ?array $normalisedTerms = null;

    /** @var list<string>|null */
    private ?array $normalisedAllowed = null;

    /**
     * @param  TermList|list<string>  $terms
     * @param  list<string>  $allowed  Ignored when $terms is a TermList, which carries its own.
     */
    public function __construct(
        private readonly TermList|array $terms,
        private readonly array $allowed = [],
    ) {}

    /**
     * Fold a value to the form the terms are compared against.
     *
     * Lowercase, decomposed to strip accents, and common character
     * substitutions undone. Separators and repeated characters are left
     * ALONE — {@see pattern()} absorbs those, and rewriting them here would
     * destroy the word boundaries that stop `ass` matching inside `assess`.
     */
    public static function normalise(string $value): string
    {
        $value = mb_strtolower(trim($value));

        // Full-width and other compatibility forms first: ｓｈｉｔ is not
        // otherwise the same string as shit.
        if (class_exists(Normalizer::class)) {
            $decomposed = Normalizer::normalize($value, Normalizer::FORM_KD);

            if (is_string($decomposed) && $decomposed !== '') {
                $value = $decomposed;
            }
        }

        // Strip combining marks left by the decomposition, so shít folds to shit.
        $stripped = preg_replace('/\p{M}+/u', '', $value);

        if (is_string($stripped)) {
            $value = $stripped;
        }

        $value = strtr($value, [
            '4' => 'a', '@' => 'a', '3' => 'e', '1' => 'i', '!' => 'i',
            '0' => 'o', '5' => 's', '$' => 's', '7' => 't', '+' => 't',
        ]);

        return trim($value);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('laranail/validation::validation.no_profanity')->translate();

            return;
        }

        if ($this->containsTerm($value)) {
            // The matched term is deliberately not echoed: repeating it back
            // prints the word on the user's screen, and naming it tells
            // someone probing the filter exactly what to obfuscate next.
            $fail('laranail/validation::validation.no_profanity')->translate();
        }
    }

    public function containsTerm(string $value): bool
    {
        $terms = $this->terms();

        if ($terms === []) {
            return false;
        }

        $normalised = self::normalise($value);

        if ($normalised === '') {
            return false;
        }

        // Allow-list first: a value whose only match is a legitimate word must
        // not be rejected, and checking terms first would reject it before the
        // allow-list was ever consulted.
        foreach ($this->allowed() as $allowed) {
            $normalised = preg_replace($this->pattern($allowed), ' ', $normalised) ?? $normalised;
        }

        return array_any($terms, fn (string $term) => preg_match($this->pattern($term), $normalised) === 1);
    }

    /**
     * A pattern that matches the term however it has been spaced out or
     * stretched, but only as a whole word.
     *
     * `badger` becomes, in effect, `\bb+ *a+ *d+ *g+ *e+ *r+\b`: each
     * character may repeat, and anything that is not a letter or digit may
     * appear between them. That catches `b.a.d.g.e.r` and `baaaadger` while
     * `\b` still keeps `ass` from matching inside `assess`, because the
     * character after the term is a word character and the boundary fails.
     *
     * The SEPARATOR is possessive; the character repeats are not, and the
     * asymmetry is load-bearing. A possessive repeat breaks any term with a
     * doubled letter — for `ass`, the first `s++` swallows both and the second
     * has nothing left to match. The separator has no such problem because it
     * matches a disjoint character class, and making it possessive removes the
     * one place where two adjacent quantifiers could compete for the same
     * characters.
     *
     * Separator characters in the TERM are dropped rather than emitted: they
     * are already covered by the separator element, and requiring them would
     * mean an allow-list phrase only matched with exactly its own spacing.
     */
    private function pattern(string $term): string
    {
        $separator = '[^\p{L}\p{N}]*+';

        $split = preg_split('//u', $term, -1, PREG_SPLIT_NO_EMPTY);

        $characters = array_values(array_filter(
            is_array($split) ? $split : [],
            static fn (string $c): bool => preg_match('/[\p{L}\p{N}]/u', $c) === 1,
        ));

        if ($characters === []) {
            // Nothing matchable. A pattern of just \b\b would match every
            // boundary in the subject and reject everything.
            return '/(?!)/u';
        }

        $body = implode($separator, array_map(
            static fn (string $c): string => preg_quote($c, '/').'+',
            $characters,
        ));

        return '/\b'.$body.'\b/u';
    }

    /** @return list<string> */
    private function terms(): array
    {
        return $this->normalisedTerms ??= $this->normaliseAll($this->terms instanceof TermList ? $this->terms->terms() : $this->terms);
    }

    /** @return list<string> */
    private function allowed(): array
    {
        return $this->normalisedAllowed ??= $this->normaliseAll($this->terms instanceof TermList ? $this->terms->allowed() : $this->allowed);
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function normaliseAll(array $values): array
    {
        $normalised = [];

        foreach ($values as $value) {
            $folded = self::normalise($value);

            if ($folded !== '') {
                $normalised[] = $folded;
            }
        }

        // Longest first: an allow-list entry must be removed before a shorter
        // term inside it can match.
        usort($normalised, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return array_values(array_unique($normalised));
    }
}
