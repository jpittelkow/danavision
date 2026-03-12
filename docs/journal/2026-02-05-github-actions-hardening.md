# GitHub Actions Hardening - 2026-02-05

## Overview

Hardened both CI and Release GitHub Actions workflows per the GitHub Actions audit. Fixed the critical release pipeline bug where Docker images were never built after `workflow_dispatch`, addressed security vulnerabilities (script injection, action pinning), and applied reliability best practices.

## Implementation Approach

### Release Workflow (release.yml)

- **Critical fix:** Restructured so the `release` job depends on `bump-and-release` via `needs:` instead of relying on a tag-push trigger. Tag pushes from `GITHUB_TOKEN` do not trigger new workflow runs, so the Docker build never ran previously.
- **Script injection fix:** Moved `${{ inputs.version_type }}` and `${{ inputs.custom_version }}` into an `env:` block to prevent shell injection.
- **Job outputs:** Added `version`, `tag`, and `sha` outputs from `bump-and-release` for the release job to consume.
- **Provenance and SBOM:** Enabled `provenance: true` and `sbom: true` on the Docker build for supply chain attestation.
- **Action pinning:** Pinned third-party actions (softprops/action-gh-release, docker/*) to commit SHAs.
- **Timeouts:** Added `timeout-minutes: 10` and `timeout-minutes: 30` to jobs.

### CI Workflow (ci.yml)

- **Permissions:** Added top-level `permissions: {}` and per-job `contents: read`.
- **Semgrep:** Replaced deprecated `returntocorp/semgrep-action` with `semgrep/semgrep-action`; kept `continue-on-error: true` as advisory-only.
- **npm ci:** Changed `npm install` to `npm ci` for deterministic frontend builds.
- **Concurrency:** Added `concurrency: group: ci-${{ github.ref }}, cancel-in-progress: true` to avoid redundant runs on rapid pushes.
- **BUILD_TIME fix:** Replaced `${{ github.event.head_commit.timestamp }}` (null on PR events) with a shell step that generates a timestamp.
- **Action pinning:** Pinned shivammathur/setup-php and semgrep-action to SHAs; docker/* actions pinned.
- **Timeouts:** Added `timeout-minutes` to all jobs (15, 10, 10, 20).

## Challenges Encountered

- Determining the correct job output flow: the release job must checkout the tag that `bump-and-release` creates. Using `ref: ${{ needs.bump-and-release.outputs.tag }}` in checkout ensures the correct commit is built.
- Fetching current commit SHAs for action pinning required querying the GitHub API for each action's tag ref.

## Observations

- The release workflow supports two trigger methods: manual dispatch (Actions > Release > Run workflow) and tag push (`v*` tags). Both produce the same result.
- Manual dispatch path: bump-and-release commits, tags, and pushes; the release job then checks out that tag, builds, and pushes the Docker image.
- Tag push path: sync-and-release extracts the version from the tag, runs bump-version.sh to sync `VERSION` and `frontend/package.json` on master, then creates a GitHub Release. The release job builds and pushes the Docker image.
- Explicit permission blocks improve least-privilege posture.
- SHA-pinning third-party actions reduces supply chain risk from compromised tags.

## Testing Notes

- CI workflow: push or open a PR to master; verify all four jobs run and pass.
- Release workflow (manual): trigger via Actions > Release > Run workflow; verify version bump, tag creation, GitHub Release, and Docker image push to GHCR.
- Release workflow (tag push): run `git tag v0.2.0 && git push --tags`; verify sync-and-release updates version files on master, GitHub Release is created, and Docker image is pushed to GHCR.
