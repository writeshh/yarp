# Security policy

## Supported versions

| Version | Supported |
|---|---|
| 2.x | ✅ |
| 1.x | ❌ — see [UPGRADE.md](UPGRADE.md) |

1.x contains a path traversal and a world-writable directory issue in the
generator, both fixed in 2.0.0. It receives no further patches.

## Reporting a vulnerability

Please **do not** open a public issue.

Use [GitHub's private vulnerability reporting](https://github.com/writeshh/yarp/security/advisories/new),
or email <shrestha.ritesh@hotmail.com>.

Include, as far as you can:

- affected version(s)
- a description of the issue and its impact
- reproduction steps or a proof of concept
- any suggested fix

You can expect an acknowledgement within a few days. Confirmed issues are fixed
and released before the advisory is published, and you will be credited unless
you prefer otherwise.

## Scope

This is a developer tool: `make:repo` runs from the command line, and the
repository classes it generates run inside your application. The parts most
worth scrutiny are:

- **Generator input handling.** The `name` argument becomes a filesystem path.
  It is validated in `Writeshh\Yarp\Support\ClassName` and covered by
  `tests/Feature/GeneratorSecurityTest.php`.
- **Generated file permissions.** Directories are created `0755`.
- **Provider rewriting.** `make:repo` modifies an existing
  `RepositoryServiceProvider` in place.
- **Query construction.** Column names and sort directions reaching the query
  builder — see `Criteria\OrderBy`, which validates direction rather than
  interpolating it.

Out of scope: findings that require an attacker to already be able to run
arbitrary Artisan commands or edit your application's config, since either
implies control of the application.

## Keeping dependencies clean

`composer audit` runs on every push and weekly on a schedule
(`.github/workflows/security.yml`), and Dependabot batches dependency updates
weekly. To check locally:

```bash
composer audit
```
