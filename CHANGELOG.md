# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Add
- `bb pipeline list` shows the pipelines defined in the repository's
  `bitbucket-pipelines.yml` (default, branches, tags, pull-requests and
  custom), read from the main branch or from `--branch`. It reads only the
  section and pattern names, and stops with an explanation on YAML it can't
  read reliably (flow style, aliases, merge keys, tabs).
  Usage: bb pipeline list [--branch <name>]
- `bb pipeline recent` lists recent pipeline runs, newest first, optionally
  filtered with `--branch`; `--limit` sets how many (default 20, max 100).
  Usage: bb pipeline recent [--branch <name>] [--limit <n>]
- `bb pipeline view` shows a pipeline run's steps (name, result and duration)
  as well as its details.
- `bb pr show` command for viewing PR comments with inline code comment support
  Usage: bb pr show <pr_id> [unresolved]
- `bb pr show` now starts with a summary of the pull request: title, state
  (flagging drafts), author, branches, link, reviewers with their approval
  state, and the description.
- `--reviewers` is now honoured by `bb pr create`, accepting comma-separated
  nicknames and/or UUIDs. When it is omitted, the PR still falls back to the
  repository's default reviewers.
  Usage: bb pr create <from> [<to>] --reviewers alice,bob
- `--draft` flag for `bb pr create`, opening the pull request as a draft.
  Usage: bb pr create <from> [<to>] --draft
- `bb pr ready` command for marking a draft pull request ready for review.
  Usage: bb pr ready <pr_id>
- `bb pr comment` command for commenting on a pull request. The message is
  the rest of the arguments, or `-` to read it from stdin. `--file` and
  `--line` comment inline on a line of the new version of a file, and
  `--reply-to` replies to an existing comment.
  Usage: bb pr comment <pr_id> <message> [--file <path> --line <n> | --reply-to <comment_id>]
- `bb pr show` now shows each comment's id, and which comment a reply
  answers, so they can be passed to `bb pr comment --reply-to`.
- A PHPUnit test suite covering the helpers, `Base`, every action class, the
  `bin/bb` command line, the curl transport and the phar build, plus a
  `Tests` GitHub Actions workflow running it on PHP 8.2, 8.3, 8.4 and 8.5.
  Run it with `composer install && composer test`.

### Fix
- `create-phar.php` no longer walks the whole build checkout. It listed the
  files to ship with a directory walk filtered by a regex that matched any
  path containing "src", "config" or "phar-index.php", which pulled
  `.git/config` — and with it the build machine's remote url — into the
  released binary, and made the build fail outright if git touched its object
  store while the walk was in progress. The files are now listed explicitly
  from `src/` and `config/`, so nothing outside them is read or shipped.

### Change
- `bb pipeline get` is now `bb pipeline view`, in line with `gh`. `get` still
  works as an alias.
- `Base::makeRequest()` now performs its HTTP call through a protected
  `executeRequest()` method, and `Upgrade` fetches releases through protected
  `fetchLatestRelease()`/`fetchRemote()` methods, so the network layer can be
  replaced in tests. Behaviour is unchanged.
- Default reviewers are now read from `/effective-default-reviewers`, so
  reviewers inherited from the repository's project are included alongside
  repository-level ones. Falls back to `/default-reviewers` if unavailable.

### Remove
- Legacy Bitbucket App Password authentication support, now that Bitbucket
  has fully retired app passwords (July 28, 2026). A config containing only
  `username`/`appPassword` now fails with a clear error directing users to
  run `bb auth` and configure an API token, instead of sending a bogus
  auth header. Any other incomplete auth config (e.g. an `email` set
  without a matching `apiToken`, or vice versa) now fails the same way,
  naming the missing field, rather than silently sending an empty-credential
  Basic auth header.

---

## [1.0.2] - 2024-02-21
### Add
- run command for pipeline
    - For more info see: [Link](https://developer.atlassian.com/cloud/bitbucket/rest/api-group-pipelines/#api-repositories-workspace-repo-slug-pipelines-post)

## [1.0.1] - 2023-02-16
### Fix
- upgrade command folder check fix

## [1.0.0] - 2023-02-16
### Add
- upgrade command

## [0.3.0] - 2022-10-07
### Fix
- fix missing extension list

## [0.2.0] - 2022-10-07
### Add
- add version argument

## [0.1.0] - 2022-09-27
- Initial release
