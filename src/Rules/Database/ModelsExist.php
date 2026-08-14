<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Database;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Every value in the submitted array names an existing record.
 *
 * `exists` applied to `tags.*` runs one query per item. This runs one query
 * for the whole array — a `whereIn` and a count — which is the difference
 * between 1 and N round trips on a multi-select with fifty options.
 *
 *     'tag_ids' => new ModelsExist(Tag::class),
 *     'slugs'   => new ModelsExist(Tag::class, 'slug'),
 *
 * It reports the missing values, because "one of these does not exist" is not
 * something a user can act on when they submitted fifty.
 *
 * Duplicates in the input are counted once. Submitting `[1, 1, 2]` where both
 * exist passes: a repeated selection is a UI artefact, not a missing record,
 * and comparing raw counts would fail it for the wrong reason. That is the
 * bug the obvious `count($values) === $query->count()` implementation has.
 *
 * **Database tier.** One indexed read per validated attribute.
 */
final readonly class ModelsExist implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model   Model to look the values up against.
     * @param  string|null          $column  Defaults to the model's route key.
     */
    public function __construct(
        private string $model,
        private ?string $column = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('laranail-validation::validation.models_exist.array')->translate();

            return;
        }

        if ($value === []) {
            return;
        }

        // Deduplicate through string keys: a repeated selection is a UI
        // artefact, and comparing raw counts would report a missing record
        // that is not missing. Building the list here also gives the query a
        // concretely typed argument rather than array<mixed>.
        $wanted = [];

        foreach ($value as $item) {
            if (! is_string($item) && ! is_int($item)) {
                $fail('laranail-validation::validation.models_exist.array')->translate();

                return;
            }

            $wanted[(string) $item] = true;
        }

        /** @var list<string> $identifiers */
        $identifiers = array_keys($wanted);

        $model = new $this->model();
        $column = $this->column ?? $model->getRouteKeyName();

        // Query builder rather than the Eloquent one, matching
        // BatchPresenceQuery: hydrating models to read one column back is
        // waste, and this is the one indexed read the rule is allowed.
        $query = DB::connection($model->getConnectionName())->table($model->getTable());
        $query->whereIn($column, $identifiers);

        $found = [];

        foreach ($query->get([$column]) as $row) {
            $item = ((array) $row)[$column] ?? null;

            if (is_string($item) || is_int($item)) {
                $found[] = (string) $item;
            }
        }

        $missing = array_values(array_diff($identifiers, $found));

        if ($missing !== []) {
            $fail('laranail-validation::validation.models_exist.missing')
                ->translate(['values' => implode(', ', $missing)]);
        }
    }
}
