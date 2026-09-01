<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Database;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * The submitted identifier names a record the current user may act on.
 *
 * Laravel ships `Rule::can()`, and it is not the same thing. `Can` hands the
 * raw submitted value straight to `Gate::allows()`, so the policy receives the
 * string `"42"` where it declared `Post $post` — the ability is evaluated
 * against an identifier rather than a record. This rule resolves the value to
 * a model first, by its route key, and only then asks the Gate.
 *
 *     'post_id' => new Authorized('update', Post::class),
 *
 * A value that resolves to nothing fails. That is deliberate and slightly
 * subtle: "no such record" and "not yours" are the same answer to the user on
 * purpose, because distinguishing them turns the field into an oracle for
 * which ids exist.
 *
 * **Database tier.** One indexed read per validated value. It does not
 * implement PrecognitionSkippable — live feedback on ownership is exactly what
 * precognition is for, and the query is the same one `exists` would run.
 */
final readonly class Authorized implements ValidationRule
{
    /**
     * @param  string  $ability  Ability name, as the policy declares it.
     * @param  class-string<Model>  $model  Model to resolve the value against.
     * @param  string|null  $guard  Auth guard, or null for the default.
     * @param  list<mixed>  $arguments  Extra arguments passed after the model.
     */
    public function __construct(
        private string $ability,
        private string $model,
        private ?string $guard = null,
        private array $arguments = [],
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail('laranail/validation::validation.authorized')->translate();

            return;
        }

        $model = new $this->model;

        $record = $model->newQuery()
            ->where($model->getRouteKeyName(), $value)
            ->first();

        // Same message for "does not exist" and "not permitted": separating
        // them would let a caller enumerate valid ids by watching which
        // message comes back.
        if (! $record instanceof Model) {
            $fail('laranail/validation::validation.authorized')->translate();

            return;
        }

        $gate = resolve(Gate::class);

        if ($this->guard !== null) {
            $gate = $gate->forUser(auth()->guard($this->guard)->user());
        }

        if (! $gate->allows($this->ability, [$record, ...$this->arguments])) {
            $fail('laranail/validation::validation.authorized')->translate();
        }
    }
}
