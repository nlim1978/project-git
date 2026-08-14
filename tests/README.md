# Application Test Suite

The application test suite uses PHPUnit through CodeIgniter 4.

## Why SQL Server is required

Authorization and routing tests deliberately run against SQL Server, not an
SQLite approximation. The production workflow depends on SQL Server-specific
behavior including `UPDLOCK`, `HOLDLOCK`, `UNIQUEIDENTIFIER`, database triggers,
and append-only routing/audit rules. A green SQLite test cannot prove those
paths are safe.

CI starts a disposable SQL Server 2022 instance and creates a dedicated
`idoctrack_test` database automatically. Application migrations and the safe
reference seeder build the schema used by the integration tests.

## Local requirements

- PHP 8.2 or newer
- Composer dependencies installed with `composer install`
- `sqlsrv` and `pdo_sqlsrv` PHP extensions
- A disposable SQL Server database reserved for automated tests

Configure the CodeIgniter `database.tests.*` values in your local `.env`. Never
point the test group at a development, staging, or production database because
database tests refresh their application migrations.

Example:

```ini
database.tests.hostname = 127.0.0.1
database.tests.database = idoctrack_test
database.tests.username = sa
database.tests.password = "your-local-test-password"
database.tests.DBDriver = SQLSRV
database.tests.DBPrefix = ""
database.tests.port = 1433
database.tests.encrypt = false
```

For a local named instance, omit `database.tests.hostname`, username, password,
and port to inherit those connection details from `database.default.*`. Always
set `database.tests.database` to a dedicated disposable database.

Run the full suite:

```console
php vendor/bin/phpunit
```

Run only the authorization/routing regression suite:

```console
php vendor/bin/phpunit tests/integration/AuthorizationRoutingRegressionTest.php
```

Run the upload-policy regression suite:

```console
php vendor/bin/phpunit tests/unit/DocumentFilePolicyTest.php
```

The upload-policy tests verify that a filename extension is not sufficient by
itself: mismatched server-detected content is rejected, and routing evidence
keeps its narrower format allow-list.

## Regression matrix

`AuthorizationRoutingRegressionTest` protects these rules:

- assigned users can see their document engagement state;
- unrelated users in the same office cannot use engagement polling as a side channel;
- peers cannot see engagement for a personally assigned document merely because they share a section;
- cross-office document access is denied;
- current Section Heads and office-scoped Monitoring Officers retain intended visibility;
- data scope explicitly distinguishes global, office-wide, and section-restricted access;
- explicit section heads and role-based section heads resolve through the same section policy;
- section queue members can see unassigned queue work, but only the Section Head can confirm it;
- unauthorized heartbeat requests expose no engagement state;
- assigned Personnel can perform a permitted route and the movement is both recorded and audited;
- assignment alone does not grant routing when the actor lacks the ROUTE permission;
- same-section peers cannot route another person's assignment;
- cross-office users cannot route a document;
- routing destination options and submitted destination validation come from the same resolver;
- broader routing destination scope does not expand a Section Head's document-visibility scope;
- a stale routing submission cannot append a second movement after the document version changes;
- stale Receiving corrections cannot overwrite a newer correction;
- SQL Server enforces one un-ended engagement per document;
- an active work lock blocks a Section Head from reassigning work another user is handling;
- the receiving facade still registers and corrects documents with its required audit trail;
- Section Head receiving remains limited to managed sections after command extraction;
- public client tracking returns only the client-safe projection.

Each test runs its fixture and mutations inside a transaction that is rolled
back after the test. Seeded reference data is created once per test class.
