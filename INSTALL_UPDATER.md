# Install the Service Desk updater and styling fix

This package adds the Site updates page and matches the Google Business page to
your existing green Service Desk design. Your current database and config.php
are not included or changed. No SQL import is needed for this update.

## One-time installation in Plesk

1. Back up the current website files and database.
2. Upload the CONTENTS of desk1/desk.mortonsmechanical.com/ from this ZIP into
   your existing subdomain document root. Replace the matching files only.
   Keep your existing app.js, app.css, Google API files, print files and other
   files. This package does not contain a complete reinstall of the app.
3. Upload the CONTENTS of desk1/private/ into the private directory beside your
   current config.php, outside the document root. Replace bootstrap.php and add
   update-lib.php and tools-layout.php. Do not overwrite config.php.
   If you previously customized bootstrap.php, merge its update-lock gate and
   conditional workflows.php include instead of discarding your changes.
4. Enable PHP ZIP (ZipArchive) in Plesk. Keep PHP 8.3+ and PDO MySQL enabled.
   Set upload_max_filesize to at least 20M and post_max_size to at least 24M
   if you want the maximum supported package size.
5. Confirm PHP owns or can write your website files and private directory.
   Use normal Plesk ownership; do not make them world-writable.
6. Sign into the desk as the existing owner and open Site updates, or visit
   https://desk.mortonsmechanical.com/updates.php.
7. To test the real updater, upload Service-Desk-Styling-Update.zip, review its
   files, enter your Service Desk password and install. That ZIP is supplied
   separately and uses the required manifest format.

The existing database owner is local user ID 1. Only that user is allowed by
default. To explicitly allow another owner, add update_owner_ids as an integer
array to the existing config.php return array. Google account IDs are unrelated.

## Future updates

Upload compatible Service Desk update ZIPs through Site updates. The page checks
the manifest and payload hashes, shows the file changes, asks for your password,
backs up replaced files, pauses other app requests, and installs the new files.
Only install packages from a source you trust: PHP/JS updates are executable
website code. Manifest hashes verify integrity, not publisher identity.

Your data stays in the existing database. This updater does not run SQL, delete
existing files, replace credentials, update itself, or change hosting settings.
Database/schema changes and updater/bootstrap changes require a separate manual
installation. Old generic ZIPs and the full GitHub repository ZIP are not
compatible update packages.

## Backups and recovery

File backups and journals are stored under private/update-state, outside the
web root. Update history shows the latest ten records; older backups remain
available in that folder. Keep an off-server database backup as usual: file
backups do not include the database.

Use Restore backup to restore the exact files replaced by that update. New files
introduced by it are removed. Restoration refuses to overwrite a later manual
edit or later update; restore newest first or handle the conflict in Plesk.

If PHP stops halfway through installation, the desk stays paused and updates.php
remains reachable for Recover. The updater, bootstrap, credentials and updater
layout cannot be overwritten by uploaded packages. On-disk backup metadata maps
each numbered backup file to its destination path and original SHA-256.

If automatic recovery cannot complete (for example, disk permissions changed),
restore each original file using its numbered backup and record.json. Remove
new files with before:null only when they still match the installed sha256.
After ALL files are restored, remove private/update-state/active.json to leave
maintenance mode. Do not remove that marker to continue using partial updates.

Backups and staged uploads are retained until you remove them in Plesk. Clean up
old stage-* directories and obsolete backup-* directories periodically, only
when no update is active. Keep the lock file and active update's backup.

Atomic per-file renames and a persistent recovery journal protect against normal
request failures. An abrupt server or disk failure can still require manual
recovery. This is a single-server installer; it does not coordinate multiple
web servers sharing independent filesystems.

## Creating compatible update ZIPs

Run tools/build-update.py with a source directory containing public/ and/or
private/ payload files, a version, a title, and an output ZIP path. It generates
update.json with format mortons-desk-update-v1 and SHA-256 for each payload.
ZIPs must contain files only, without directory entries or enclosing folders.

Public payloads may contain PHP, JS, CSS, SVG, PNG, JPG/JPEG, WebP, ICO, HTML,
JSON, and Markdown. Protected public updates.php, setup.php, webhook.php, upload
folders, hidden files, traversal paths and symbolic links are rejected. Private
payloads are limited to google-lib.php and workflows.php. Configuration, SQL,
bootstrap, updater code and its recovery layout remain protected.

## Preview

The hosted visual preview uses sample data. Its install and restore actions are
simulated and never change the actual hosting or Google listing. The installable
PHP page above operates on your real hosting after the one-time installation.
