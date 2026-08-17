# Release

Releases are tag-driven. Publishing a GitHub release is the single trigger; three workflows
react to it, and `CHANGELOG.md` is written by CI rather than by hand.

## Versioning

The package is pre-1.0, on the `0.1` line. Consumers constrain on `^0.1`.

While pre-stable, the laranail convention is a **single moving `v0.1.0` tag**: amend the tag onto
the current commit rather than cutting a new SemVer version for every change. Consumers on
`^0.1` pick the move up on their next `composer update`. A `branch-alias` of
`dev-main → 0.1.x-dev` keeps a path or dev checkout satisfying `^0.1` too.

That changes at 1.0, which is when the public surface is declared stable and ordinary semantic
versioning starts:

| Change | Bump (post-1.0) |
|---|---|
| A new rule type, builder method, or opt-in behaviour | minor |
| A fix that leaves the public surface intact | patch |
| Removing or renaming anything public, or raising the PHP/Laravel floor | major |

The public surface is `FluentRule`, the `Rules\*` classes, `RuleSet`, `HasFluentRules`,
`HasFluentValidation`, and `Testing\*`. Everything under `Internal/` is marked `@internal` and
may change at any time — see [Architecture](architecture.md).

Composer resolves versions from the git tag, so the tag is the source of truth. There is no
version constant in the source to keep in step.

## Before tagging

Run the gates locally and read the output; CI runs the same ones.

```bash
composer test              # Pest suite
composer phpstan           # level max, plus type coverage
vendor/bin/pint --test     # style, reported not applied
composer tag-currency      # is the tag consumers resolve still on main?
```

Then check that anything user-facing that changed is reflected in `docs/`.

### The tag check is not optional, and it is not only for release day

`composer tag-currency` answers the question the moving-tag convention creates: is the tag a
consumer on `^0.1` resolves actually pointing at `main`? It checks three things — every tag is
reachable from `main`, the tag on the line `extra.branch-alias` declares live is current, and the
highest tag overall (what an unconstrained `composer require` installs) is too.

**The failure it exists for is silent.** A commit lands on `main`, the moving tag stays where it
was, and every consumer keeps resolving the code from before. Nothing errors. The package looks
released and is not.

It runs in CI on every push to `main` — which is exactly when a tag falls behind — on pull
requests, and weekly, since a tag can also be moved on the remote without any push here. See
`.github/workflows/tag-currency.yml`.

> It used to call GitHub's `compare` API to test ancestry, and that endpoint 404s under a token
> without the right scope — for every repository, `laravel/framework` included. The script read
> the 404 as "this tag points at abandoned history", so the healthy state produced a red error
> about discarded history. A check that cries wolf on the correct case is worse than no check; it
> teaches you to skip it. It now uses `git merge-base --is-ancestor` against a freshly fetched
> remote, which answers the same question with no API scope and no rate limit.

## Cutting the release

1. Tag the release commit with the bare version — `0.1.0`, no `v` prefix. Composer reads the
   tag. Pre-1.0 this means moving the existing tag; use `git tag -f 0.1.0` and force-push it.
2. Create the GitHub release against that tag. Title it with the `v` prefix (`v0.1.0`); that
   part is cosmetic.
3. Write a real description in the release body. Summarise what changed and why, in prose — an
   empty body or a bare "see CHANGELOG" is not a release description. This body becomes the
   changelog entry, so it is the version's permanent record.

## What CI does afterwards

Publishing the release (`release: released`) fires three workflows:

- **Update Changelog** prepends the release body to `CHANGELOG.md` and commits it back to the
  release's target branch. This is why `CHANGELOG.md` is never hand-edited as part of a
  release — a manual entry will be duplicated.
- **Release Benchmark** re-runs the benchmark suite against the tagged commit and injects the
  results table into the release body, between the `<!-- benchmark-start -->` and
  `<!-- benchmark-end -->` markers. Those markers must already be present in the body for the
  table to land, and benchmark numbers are never pasted in by hand.
- **Benchmark** publishes the same measurements for the branch.

## Distribution

laranail packages resolve through git VCS repositories rather than Packagist, so a consumer adds
this repository to its own root `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/laranail/validation" }
]
```

`.gitattributes` marks the development-only paths (`tests/`, `workbench/`, `.github/`, `docs/`,
the benchmark harness) `export-ignore`, so they stay out of the distributed archive — it ships
`composer.json`, `LICENSE`, `README.md`, `resources/` and `src/` only. When adding a top-level
development directory, run `vendor/bin/package-boost-php gitattributes` and add its
`export-ignore` line in the same change.

---

[← Docs index](../README.md#documentation)
