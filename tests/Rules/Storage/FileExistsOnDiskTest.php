<?php declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Simtabi\Laranail\Validation\Rules\Storage\FileExistsOnDisk;

beforeEach(function (): void {
    Storage::fake('uploads');
    Storage::disk('uploads')->put('avatars/alice.png', 'png-bytes');
    Storage::disk('uploads')->put('top-level.txt', 'text');
});

it('finds files on the named disk, with and without a directory scope', function (): void {
    expect(ruleAccepts(new FileExistsOnDisk('uploads', 'avatars'), 'alice.png'))->toBeTrue()
        ->and(ruleAccepts(new FileExistsOnDisk('uploads'), 'top-level.txt'))->toBeTrue()
        ->and(ruleAccepts(new FileExistsOnDisk('uploads'), 'avatars/alice.png'))->toBeTrue()
        ->and(ruleAccepts(new FileExistsOnDisk('uploads', 'avatars'), 'bob.png'))->toBeFalse()
        ->and(ruleAccepts(new FileExistsOnDisk('uploads'), 'missing.txt'))->toBeFalse();
});

it('refuses traversal out of the scoped directory', function (mixed $value): void {
    // The scope is a security boundary: '../top-level.txt' EXISTS on the
    // disk, and the legacy rule would have found it.
    expect(ruleAccepts(new FileExistsOnDisk('uploads', 'avatars'), $value))->toBeFalse();
})->with([
    '../top-level.txt',
    'x/../../top-level.txt',
    '..',
    '/etc/passwd',
    '\\windows\\path',
    "alice.png\0.txt",
    12,
    null,
]);
