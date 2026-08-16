# Contributing

Contributions are **welcome** and will be fully **credited**.

## Getting set up

```bash
git clone https://github.com/writeshh/yarp.git
cd yarp
composer install
composer test
```

You need PHP 8.3 or newer. See [TESTING.md](TESTING.md) for how the suite is
arranged and how to run it against other supported Laravel versions.

## Before you open a pull request

Run the same checks CI does:

```bash
composer check
```

That is `format-check` (Pint), `analyse` (PHPStan level 6) and `test` (PHPUnit).
All three must pass.

| Command | What it does |
|---|---|
| `composer format` | Applies Pint fixes |
| `composer analyse` | PHPStan level 6 over `src` and `config` |
| `composer test` | The full suite |
| `composer audit` | Checks dependencies for known advisories |

## What we ask for

- **Tests.** A patch without tests will not be merged. Bug fixes should include a
  test that fails before the fix.
- **Both repository flavours stay in step.** New repository methods go in
  `src/Concerns/InteractsWithRepository.php` and on `RepositoryInterface`. Add
  coverage to `tests/Unit/RepositoryBehaviourTestCase.php`, which runs against
  both `BaseRepository` and the standalone trait.
- **No new PHPStan ignores** without an explanation. The existing entries in
  `phpstan.neon.dist` each document why the report is a limitation of generic
  inference over Eloquent rather than a defect. If you add one, say why in the
  same style, and scope it to a single identifier and file.
- **Documentation.** Update the README's method tables and add a `CHANGELOG.md`
  entry under `Unreleased`.
- **SemVer.** Breaking changes need a major release and an `UPGRADE.md` entry
  showing before and after.
- **One pull request per feature**, with a coherent commit history.

## Code style

Laravel Pint with the `laravel` preset, plus `declare(strict_types=1)` in every
file. `composer format` handles it — do not hand-format.

## Reporting bugs

Include the PHP and Laravel versions, the YARP version, what you expected, what
happened, and a minimal reproduction. Check existing issues and open pull
requests first.

For **security** issues, do not open a public issue — see
[SECURITY.md](SECURITY.md).

## Etiquette

Maintainers give their free time to this project and make it freely available in
the hope it is useful. Please be considerate when raising issues or presenting
pull requests, and respect a maintainer's decision if a submission is not used.

When proposing a feature, consider whether it is likely to be useful to other
users of the package, not only in your own application.

**Happy coding!**
