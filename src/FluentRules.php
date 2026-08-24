<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation;

use Attribute;

/**
 * Marks a method as containing Laravel validation rules so that static
 * tooling — codegen, migration, or analysis — can find it.
 *
 * Such tooling conventionally scans methods named `rules()`. A class that
 * defines rules in a differently-named method (e.g. `rulesWithoutPrefix()`
 * on a custom `FluentValidator` subclass used for JSON import) can mark
 * the method with this attribute so it is picked up too:
 *
 *     class JsonImportValidator extends FluentValidator
 *     {
 *         #[FluentRules]
 *         public function rulesWithoutPrefix(): array
 *         {
 *             return [
 *                 'name' => ['required', 'string', 'max:255'],
 *             ];
 *         }
 *     }
 *
 * The attribute has no runtime effect, and nothing in this package reads
 * it — it exists purely as a marker for external tooling.
 *
 * DECISION (1.0 planning, §14.13): KEPT. The inertness is the design, not
 * an accident — dropping a documented external-tooling contract at 1.0
 * would be a silent break for exactly the tools it exists to serve, and it
 * costs nothing to keep. Removing it later is a major-version decision
 * carried through UPGRADING.md, not a cleanup.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class FluentRules {}
