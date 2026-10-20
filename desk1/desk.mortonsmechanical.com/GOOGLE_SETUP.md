# Google Business Profile for Mortons Mechanical

## Upgrade your existing Service Desk

This is an addition to your existing PHP/MySQL installation. Your customers,
estimates, invoices, payments, inventory, timers, messaging, and settings use
their original code and tables.

1. Back up your current website files, private/config.php, and MySQL database.
2. From desk1/desk.mortonsmechanical.com/ in this download, upload:
   - index.php
   - google.php, google-api.php, google-oauth.php
   - assets/google.css and assets/google.js
   Leave the existing app.js, app.css, print files, API, and other files in place.
   This folder's contents belong directly in your existing subdomain document root.
3. From desk1/private/, upload google-lib.php and google.example.php to your
   existing private folder beside config.php, one level ABOVE the document root.
   Do not replace your existing config.php or move the private folder into public access.
4. In Plesk/phpMyAdmin, select your EXISTING Service Desk database and import
   only private/google-schema.sql. It adds google_connections and
   google_post_operations using CREATE TABLE IF NOT EXISTS. It does not alter,
   delete, or reimport your existing data. Do NOT import deskpri.sql, schema.sql,
   or run setup.php as part of this upgrade.
5. Keep PHP 8.3+ enabled with PDO MySQL, cURL, and OpenSSL. Use HTTPS. The app
   uses your existing database credentials from private/config.php.
6. Follow the Google connection steps below, then sign in to Service Desk and
   click the new Google Business link beside Sign out.

The repository includes a full copy of the original app for reference; the
six public files above plus the two private files are the only runtime changes.
The new SQL migration is a separate, one-time import.

## Google connection

Google API approval is separate from signing in with a Google account.

1. In Google Cloud Console, select or create a project for your business:
   https://console.cloud.google.com/
2. Follow Google's Business Profile API access application:
   https://developers.google.com/my-business/content/prereqs
   Google currently requires an eligible, verified profile, a business website,
   and approval for the project. A zero API quota means access is not approved.
3. After approval, enable My Business Account Management API, My Business
   Business Information API, and Google My Business API (for local posts).
   Google's setup guide: https://developers.google.com/my-business/content/basic-setup
4. Configure the Google Auth Platform consent screen (app name, support email,
   audience and contact details). Add the scope:
   https://www.googleapis.com/auth/business.manage
   If the app is in Testing, add the Google account that owns/manages your profile
   as a test user. External apps in Testing can have short-lived refresh tokens
   (typically seven days for this scope); reconnect if required. For ongoing use,
   follow Google's publishing/verification requirements for your app.
5. Create an OAuth client of type Web application. Add this exact authorized
   redirect URI:
   https://desk.mortonsmechanical.com/google-oauth.php
   It must match base_url in private/config.php plus /google-oauth.php.
6. On the HOSTING SERVER, copy private/google.example.php to private/google.php.
   Fill in client_id and client_secret. Do not post these credentials in chat
   or commit google.php to GitHub.
7. Generate the encryption key once using PHP in a terminal:
   php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
   Paste that value into encryption_key in private/google.php. Keep a secure
   backup: changing the key makes stored Google tokens unreadable.
8. allowed_users contains local Service Desk user IDs, NOT Google account IDs.
   The supplied database has owner ID 1, so the example allows [1].
   Add another ID only if that local user should manage a Google profile.
9. Open Google Business in the desk, click Connect / reconnect Google, sign in
   with the profile's owner/manager account, and grant the management permission.
10. Select the Google account and business profile and click Use this profile.

Credentials stay server-side. Refresh tokens are encrypted with AES-256-GCM in
the new database table. Access tokens exist only for the current server request.
Each local user has a separate Google connection and selected profile.

## Using the controls

- Regular hours: each row is one opening period. Days with no rows are closed.
  Add several rows for split shifts. Overnight hours use the following closing
  day. For a 24-hour day use 00:00 to 24:00 on the same day.
- Holiday hours: choose a date, choose Special opening hours, and enter one or
  more same-day periods. Each period must be shorter than 24 hours; use two
  adjacent periods for a full-day exception. Other special dates are preserved.
- Close for a day: choose the date and Closed all day. Google applies that
  exception to that date; your regular schedule remains.
- Undo a one-day closure: choose that date and Remove exception — use regular
  hours. This does not reopen a temporarily closed business.
- Temporarily close business: marks the listing CLOSED_TEMPORARILY until you
  explicitly use Reopen business. No scheduled automatic reopening is included.
- Reopen business: marks the listing OPEN. Existing regular and special hours
  still apply.
- Posts: edit the text of existing posts. Photos, buttons, offer details and
  event details are preserved because only summary is patched. Their editing
  is not included. New posts are text-only standard updates.
- Every public change requires a confirmation showing the selected business.
  Saving a connection or selecting a profile does not change your listing.
- Disconnect removes the desk's stored token. To revoke the app's Google grant
  too, remove the connection at https://myaccount.google.com/connections.

Existing overnight special periods are displayed and kept. The one-date editor
refuses to change dates touched by such a period; use Google directly for that
specific overnight exception. Regular overnight hours are fully editable.

## Troubleshooting

- Missing tables / HTTP 500: import ONLY google-schema.sql into the same database
  configured in private/config.php. Inspect the hosting log for details.
- redirect_uri_mismatch: compare the OAuth redirect URI character for character;
  use HTTPS and the same domain as base_url.
- API 403: check project approval, enabled APIs, granted business.manage scope,
  profile eligibility, and owner/manager rights. An empty or zero-quota project
  cannot manage your profile merely because OAuth sign-in worked.
- No accounts/profiles: reconnect with the Google account that manages the
  business. Locations are loaded for the selected account, including pagination.
- Token expired/revoked: reconnect Google. Do not replace the encryption key
  to troubleshoot an expired token.
- Data changed: reload before editing. The server checks fresh Google data
  against your loaded revision and uses narrow update masks.
- Google accepted but nothing is public yet: Google may review changes/posts.
- Unconfirmed post submission: reload posts and check Google before choosing
  Start another post. Creation is never retried automatically; the database
  reserves each operation before sending it to Google.
- Network timeout during hours/status/post edits: reload Google data to check
  the current value before retrying.
- Special hours unavailable: Google requires regular hours first.
- Reopening rejected: Google may restrict reopening for the profile; follow
  the Google error and profile verification guidance.

Edits from other apps can still occur between Google's read and patch requests;
Google does not provide a compare-and-swap operation here. Avoid editing the same
hours in multiple tools simultaneously.

## Validation and rollout

The GitHub workflow "Google integration checks" checks PHP syntax, browser-script
syntax, and focused date/hour/overlap/preservation/stale-edit validation. Check
its actual result in Actions; this document does not assert a run passed.

Local PHP execution was unavailable while preparing this change. Before relying
on it in production, verify in hosting:
- Existing login, invoices, inventory, and clock in/out still work.
- Google OAuth cancellation, successful connection, expired access, disconnect.
- Correct account/profile selection and lack of access for an unallowed user.
- Reading your real posts, regular hours, and holidays without changing them.
- A deliberate real edit and restoration of a future special date, preserving
  all unrelated dates; a post-text edit and restoration.
- Temporary closure/reopening only when those statuses actually match your
  business. There is no API sandbox for these live profile changes.
- CSRF rejection and stale-edit rejection across two browser sessions.

Pure checks can be run without credentials or MySQL:
php desk1/desk.mortonsmechanical.com/checks/google.php

## Rollback

Restore the previous index.php and remove the three new public google*.php files
and assets/google.css and assets/google.js. The additive tables may remain; no
original tables need to be restored or deleted. Revoke the Google app connection
if you no longer want access. Rolling back site files does not undo public Google
changes already accepted.

## Security of the source upload

The original repository was public and contained a database password and customer
data. Make it private, rotate the exposed database password in Plesk and your
hosted private/config.php, and review removal of sensitive Git history. Deleting
a file in a new commit does not remove it from history. This upgrade does not
change existing database credentials.

## API references

- Location updates:
  https://developers.google.com/my-business/reference/businessinformation/rest/v1/locations/patch
- Hours and business status:
  https://developers.google.com/my-business/reference/businessinformation/rest/v1/accounts.locations
- Post text updates:
  https://developers.google.com/my-business/reference/rest/v4/accounts.locations.localPosts/patch
- OAuth:
  https://developers.google.com/identity/protocols/oauth2/web-server
