#!/usr/bin/env bash
#
# While pre-1.0, laranail packages keep one tag per line and move it, and
# consumers resolve `^0.1` to whatever it currently points at. That makes "the
# tag is on main" an invariant rather than a preference: a tag left behind does
# not mean consumers get older features, it means they get code without the
# fixes.
#
# Three checks, because this package has now shipped every one of these failures.
#
#   1. REACHABILITY — every tag must be an ancestor of the default branch.
#
#      Composer's VCS driver reads tags, not reachability. This package
#      advertised v0.2.0, v0.2.1 and v0.3.0 pointing at abandoned history, so
#      anyone writing `^0.2` got code that had been discarded months earlier,
#      with nothing anywhere saying so.
#
#   2. CURRENCY — the tag constrained consumers resolve must be on the branch.
#
#      Anchored on `extra.branch-alias`, NOT on the highest tag. That
#      distinction is the whole point. laranail/enumerator carried v0.1.0
#      through v0.4.0; v0.4.0 pointed at main HEAD, so a check that took the
#      highest tag reported everything healthy — while all nine consumers, every
#      one of them on `^0.1`, resolved v0.1.0 two commits behind and silently
#      missed a preset and an ordering bugfix. `^0.1` on a 0.x package means
#      `>=0.1.0 <0.2.0`; a v0.4.0 they cannot reach says nothing about what they
#      get.
#
#      The package's own `branch-alias` declares which line is live —
#      `0.1.x-dev` here, `0.7.x-dev` in db-tools — so the check reads it rather
#      than guessing.
#
#   3. THE HIGHEST TAG — what an unconstrained `composer require` resolves.
#
#      Anchoring on branch-alias alone traded one blind spot for another. This
#      package kept v0.4.0 three commits behind main while v0.1.0 was current,
#      and a check that only knew about the live line called that healthy — yet
#      v0.4.0 is exactly what a new consumer who names no constraint installs.
#      Both are checked. The third only reports when it names a different tag,
#      so an ordinary single-tag package does not hear it twice.
#
# WHY GIT AND NOT THE GITHUB API
#
# This used `gh api .../compare/base...head`, which is the obvious way to ask
# whether one commit is an ancestor of another and does not work. That endpoint
# 404s under a token without the right scope — for every repository, including
# `laravel/framework` — and the script read the failure as "this tag points at
# abandoned history". So the healthy state, a tag sitting exactly on HEAD,
# reported a red error about discarded history. A check that cries wolf on the
# correct case is worse than no check: it teaches you to skip it, which is
# precisely what happened.
#
# `git merge-base --is-ancestor` answers the same question exactly, offline,
# with no API scope, no rate limit and no network round trip per tag. The reason
# the API was reached for in the first place — that a stale local tag could make
# it pass — is handled by fetching with `--prune-tags` first, which makes the
# local refs mirror the remote.
#
# Only meaningful pre-1.0. From 1.0 the tag is immutable by design and this
# exits without an opinion.

set -euo pipefail

BRANCH="${1:-main}"
REMOTE="${2:-origin}"

fail() { printf '\033[31m  x %s\033[0m\n' "$1"; FAILED=1; }
ok()   { printf '\033[32m  ok %s\033[0m\n' "$1"; }
FAILED=0

if ! git rev-parse --git-dir >/dev/null 2>&1; then
  echo "Not a git repository." >&2
  exit 2
fi

echo "  Release currency for $(basename "$(git rev-parse --show-toplevel)")"
echo

# Mirror the remote before reading anything. --prune-tags is the load-bearing
# flag: without it a tag deleted or moved on the remote survives locally and the
# check passes on a ref nobody else can see.
git fetch --quiet --prune --prune-tags --tags "${REMOTE}" 2>/dev/null || {
  fail "Could not fetch from ${REMOTE}."
  exit 1
}

if ! head=$(git rev-parse --verify --quiet "${REMOTE}/${BRANCH}^{commit}"); then
  ok "No ${REMOTE}/${BRANCH} yet, so there is nothing to be behind."
  exit 0
fi

tags=$(git tag --list 'v*' --sort=v:refname)

if [ -z "${tags}" ]; then
  ok "No v* tag, so nothing is pinned."
  exit 0
fi

# ---------------------------------------------------------------------------
# 1. Reachability
# ---------------------------------------------------------------------------
echo "  Tags"

for tag in ${tags}; do
  # ^{commit} dereferences an annotated tag to the commit it wraps; a
  # lightweight tag is already one and is unaffected.
  if ! commit=$(git rev-parse --verify --quiet "${tag}^{commit}"); then
    fail "${tag} could not be resolved."
    continue
  fi

  if git merge-base --is-ancestor "${commit}" "${head}"; then
    printf '    %-10s %s  on %s\n' "${tag}" "${commit:0:12}" "${BRANCH}"
  else
    fail "${tag} (${commit:0:12}) is not an ancestor of ${BRANCH} — it points at abandoned history, and Composer will still offer it."
  fi
done
echo

# ---------------------------------------------------------------------------
# 2. Currency, on the line this package declares as live
# ---------------------------------------------------------------------------
line=$(git show "${REMOTE}/${BRANCH}:composer.json" 2>/dev/null \
        | jq -r '.extra["branch-alias"]["dev-'"${BRANCH}"'"] // empty' \
        | sed 's/\.x-dev$//')

if [ -z "${line}" ]; then
  ok "No branch-alias for dev-${BRANCH}, so no line is declared live."
  exit "${FAILED}"
fi

current=$(printf '%s\n' "${tags}" | { grep -E "^v${line}\." || true; } | sort -V | tail -1)

echo "  Live line: ${line}.x (from extra.branch-alias)"

if [ -z "${current}" ]; then
  fail "branch-alias declares ${line}.x-dev but no v${line}.* tag exists, so \`^${line}\` resolves nothing."
  exit 1
fi

commit=$(git rev-parse "${current}^{commit}")

echo "    ${current} -> ${commit:0:12}"
echo "    ${BRANCH}  -> ${head:0:12}"
echo

if [ "${commit}" = "${head}" ]; then
  ok "${current} is on ${BRANCH}; consumers on ^${line} resolve current code."

  # ---------------------------------------------------------------------
  # 3. The highest tag overall, for consumers who named no constraint
  # ---------------------------------------------------------------------
  highest=$(printf '%s\n' "${tags}" | sort -V | tail -1)

  if [ "${highest}" != "${current}" ]; then
    hcommit=$(git rev-parse "${highest}^{commit}")

    if [ "${hcommit}" = "${head}" ]; then
      ok "${highest} is also on ${BRANCH}."
    else
      hbehind=$(git rev-list --count "${hcommit}..${head}")
      fail "${highest} is the highest tag and is ${hbehind} commit(s) behind ${BRANCH}. An unconstrained \`composer require\` resolves it, so it must be current or it must not exist."
    fi
  fi
else
  behind=$(git rev-list --count "${commit}..${head}")

  printf '  Commits on %s not in %s: %s\n' "${BRANCH}" "${current}" "${behind}"
  git log --oneline --no-decorate "${commit}..${head}" | sed 's/^/    /'
  echo

  fail "${current} is behind ${BRANCH}. Move it (git tag -f ${current} ${BRANCH} && git push --force ${REMOTE} ${current}) or cut a new one."
fi

exit "${FAILED}"
