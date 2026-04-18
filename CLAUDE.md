# SemanticACL

## Running tests

From the MediaWiki root directory:

```bash
cd /home/antoine/Projects/Wikimedica/development/MediaWiki
PHPUNIT_USE_NORMAL_TABLES=1 composer phpunit -- --testdox --configuration extensions/SemanticACL/phpunit.xml.dist
```

**Do not** use `php tests/phpunit/phpunit.php` — MediaWiki's own runner uses a strict deprecation handler that converts SMW's `wfDeprecated()` calls into test failures. Our `phpunit.xml.dist` uses MediaWiki's bootstrap but avoids that strict handler.

To run a single test:

```bash
PHPUNIT_USE_NORMAL_TABLES=1 composer phpunit -- --testdox --configuration extensions/SemanticACL/phpunit.xml.dist --filter testName
```

## After any change

Run the test suite after every code change to catch regressions:

```bash
cd /home/antoine/Projects/Wikimedica/development/MediaWiki
PHPUNIT_USE_NORMAL_TABLES=1 composer phpunit -- --testdox --configuration extensions/SemanticACL/phpunit.xml.dist
```

## Changelog

When making user-facing changes (features, bug fixes, deprecations), update `CHANGELOG.md` under the current unreleased version heading. The version number lives in `extension.json`.
