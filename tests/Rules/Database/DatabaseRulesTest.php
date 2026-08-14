<?php declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\Rules\Database\Authorized;
use Simtabi\Laranail\Validation\Rules\Database\ModelsExist;

final class Post extends Model
{
    protected $table = 'posts';

    public $timestamps = false;

    protected $guarded = [];
}

final class Tag extends Model
{
    protected $table = 'tags';

    public $timestamps = false;

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}

beforeEach(function (): void {
    config(['database.default' => 'testing']);
    config(['database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:']]);

    Schema::connection('testing')->create('posts', function (Blueprint $table): void {
        $table->id();
        $table->integer('owner_id');
    });

    Schema::connection('testing')->create('tags', function (Blueprint $table): void {
        $table->id();
        $table->string('slug');
    });

    DB::connection('testing')->table('posts')->insert([
        ['id' => 1, 'owner_id' => 10],
        ['id' => 2, 'owner_id' => 99],
    ]);

    DB::connection('testing')->table('tags')->insert([
        ['id' => 1, 'slug' => 'php'],
        ['id' => 2, 'slug' => 'laravel'],
    ]);
});

// =========================================================================
// Authorized
// =========================================================================

it('resolves the value to a model before asking the Gate', function (): void {
    // This is the whole difference from Rule::can(), which hands the raw
    // submitted value to the policy. A policy declaring `Post $post` must
    // receive a Post, not the string "1".
    $received = null;

    Gate::define('update-post', function (mixed $user, mixed $subject) use (&$received): bool {
        $received = $subject;

        return true;
    });

    Validator::make(['post_id' => '1'], ['post_id' => new Authorized('update-post', Post::class)])->passes();

    expect($received)->toBeInstanceOf(Post::class)
        ->and($received->id)->toBe(1);
});

it('passes when the gate allows and fails when it denies', function (): void {
    Gate::define('touch-post', static fn (mixed $user, Post $post): bool => $post->owner_id === 10);

    $rule = new Authorized('touch-post', Post::class);

    expect(Validator::make(['id' => 1], ['id' => $rule])->passes())->toBeTrue()
        ->and(Validator::make(['id' => 2], ['id' => $rule])->passes())->toBeFalse();
});

it('gives the same answer for a missing record as for a denied one', function (): void {
    // Distinguishing them would turn the field into an oracle for which ids
    // exist, so both must produce the identical message.
    Gate::define('touch-post', static fn (mixed $user, Post $post): bool => $post->owner_id === 10);

    $rule = new Authorized('touch-post', Post::class);

    $denied = Validator::make(['id' => 2], ['id' => $rule])->errors()->first('id');
    $missing = Validator::make(['id' => 9999], ['id' => $rule])->errors()->first('id');

    expect($denied)->toBe($missing)->not->toBeEmpty();
});

it('rejects a value that is not an identifier', function (mixed $value): void {
    Gate::define('touch-post', static fn (): bool => true);

    expect(Validator::make(['id' => $value], ['id' => new Authorized('touch-post', Post::class)])->passes())
        ->toBeFalse();
})->with([
    'array' => [[1]],
    'float' => [1.5],
]);

// =========================================================================
// ModelsExist
// =========================================================================

it('accepts an array whose values all exist', function (): void {
    expect(Validator::make(['tags' => [1, 2]], ['tags' => new ModelsExist(Tag::class)])->passes())->toBeTrue();
});

it('rejects an array containing a value that does not', function (): void {
    expect(Validator::make(['tags' => [1, 999]], ['tags' => new ModelsExist(Tag::class)])->passes())->toBeFalse();
});

it('names the missing values, since fifty selections is not actionable otherwise', function (): void {
    $error = Validator::make(['tags' => [1, 998, 999]], ['tags' => new ModelsExist(Tag::class)])
        ->errors()
        ->first('tags');

    expect($error)->toContain('998')->toContain('999');
});

it('counts duplicates once', function (): void {
    // The obvious implementation compares count($values) to the query count
    // and fails [1, 1, 2] for the wrong reason: a repeated selection is a UI
    // artefact, not a missing record.
    expect(Validator::make(['tags' => [1, 1, 2]], ['tags' => new ModelsExist(Tag::class)])->passes())->toBeTrue();
});

it('can look up a column other than the route key', function (): void {
    $rule = new ModelsExist(Tag::class, 'slug');

    expect(Validator::make(['tags' => ['php', 'laravel']], ['tags' => $rule])->passes())->toBeTrue()
        ->and(Validator::make(['tags' => ['php', 'nope']], ['tags' => $rule])->passes())->toBeFalse();
});

it('accepts an empty array and rejects a non-array', function (): void {
    $rule = new ModelsExist(Tag::class);

    expect(Validator::make(['tags' => []], ['tags' => $rule])->passes())->toBeTrue()
        ->and(Validator::make(['tags' => 'php'], ['tags' => $rule])->passes())->toBeFalse()
        ->and(Validator::make(['tags' => [['nested']]], ['tags' => $rule])->passes())->toBeFalse();
});

it('runs one query for the whole array, not one per item', function (): void {
    // The reason to prefer this over `exists` on tags.*: fifty options means
    // fifty round trips there and one here.
    DB::connection('testing')->enableQueryLog();

    Validator::make(['tags' => [1, 2, 999]], ['tags' => new ModelsExist(Tag::class)])->passes();

    expect(DB::connection('testing')->getQueryLog())->toHaveCount(1);
});
