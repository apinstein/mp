# MP: Migrations for PHP

MP is a generic migrations architecture for managing migrations between versions of a web application.

It can be used to migration database schemas as well as perform arbitary code during upgrades and downgrades.

## INSTALLATION

```sh
composer require apinstein/mp
```

## HOW IT WORKS

MP keeps track of the current version of your application. You can then request to migrate to any version.

MP also has a "clean" function which allows you to reset your application to "version 0". There is a clean() callback
which allows you to programmatically return your application to a pristine state when migrating with the "clean" option.

By default you can implement your "clean" functionality in the MigrationClean::clean() method of migrations/clean.php:

```php
public function clean($migrator)
{
    $migrator->getDbCon()->exec("drop database foo;");
}
```

NOTE: If you prefer you can also create your baseline schema in clean. However, I usually set up the baseline schema in the first migration.

Each migration for your application is defined by a class in the migrations directory.

## EXAMPLE CLI USAGE

```sh
$ mp -f                         # Use file-based version tracking; If no args will just print version.
                                # NOTE: First run of MP will create the migrations directory,
                                        create a stub clean script, and set the version to 0.
$ mp -f -n                      # Create a new migration; will write a stub file in migrations dir
$ mp -f -m                      # Migrate to head (latest revision)
$ mp -f -m20090716_204830       # Migrate to revision 20090716_204830
$ mp -f -r                      # Reset to "clean" state (version 0)
```

If you need DB access in your migrations, you can bootstrap them yourself, or, if you supply a dsn like so:

```sh
$ mp -x'pgsql:dbname=mydb;user=mydbuser;host=localhost'
```

Then in your migrations you can do:

```php
$this->migrator->getDbCon()->exec($sql);
```

And in the clean() function, it's:

```php
$migrator->getDbCon()->exec($sql);
```

NOTE: If you use Migrator's db connection, it is configured to throw PDOException on error.

## EXAMPLE API USAGE

```php
$m = new Migrator();
$m->clean();
$m->upgradeToLatest();
$m->downgradeToVersion('20090716_204830');
$m->upgradeToVersion('20090716_205029');
$m->downgradeToVersion('20090716_212141');
```

### NOTE FOR SOURCE CONTROL

If you use the file-based version tracking (ie migrations/version.txt) then make sure to have your source control
system *ignore* that file. You definitely don't want your system to think it's been updated when you push new code
to production but before you run your migrations! Therefore it is recommended to use DB-based versioning wherever
possible.

### INTEGRATION

While MP can be operated purely via the migrate command line tool, it is also designed to be implemented into your
web application or with any framework. You can use the Migrator API to custom-configure MP's behavior for your
application or framework.

This is ideal for use with ORMs that may already have an API to manage schemas but don't have a migrations system.
It also works well with ORMs that don't have an API to manage schemas, as you can still integrate with them to use
their DB connection for executing SQL migrations.

## ROADMAP / TODO

- Add long-option support. See http://cliframework.com/, looks pretty interesting.
- Automatically walk up from pwd looking for migrations/ directory so you only have to be *under* your project root to run mp successfully.
- Addition of mutex protection to prevent multiple migrations from running at the same time.
- Addition of a generic schema manipulation API so you can use MP to manage your database without having to write SQL. Is this even necessary?
- This looks like an interesting project to integrate with: http://www.liquibase.org/
- Consider idea of adding the ability to link migrations to VCS/SCM tags -- sometimes a migration depends on a version of *code* as well as database structure!
