# Mortons Mechanical Service Desk — PHP 8.3 / MySQL

This is the complete latest BreezeHost package. Twilio is disconnected. All public
JavaScript and CSS are prebuilt; you do not need Node.js on your web server.

## Your exact folder layout

Your screenshot shows this public folder:
`domains/desk.mortonsmechanical.com/public_html`

The correct layout is:

- `domains/desk.mortonsmechanical.com/public_html/index.php`
- `domains/desk.mortonsmechanical.com/public_html/api.php`
- `domains/desk.mortonsmechanical.com/public_html/assets/`
- `domains/desk.mortonsmechanical.com/private/bootstrap.php`
- `domains/desk.mortonsmechanical.com/private/config.php`

Upload the ZIP into `domains/desk.mortonsmechanical.com` (one level ABOVE
`public_html`) and extract it there. The ZIP already contains `public_html/` and
`private/` folders. Do not extract it inside `public_html`.

The `private/` directory MUST remain outside `public_html`.

## Install using Plesk

1. Preserve the source files you already uploaded by renaming that `public_html`
   folder to `public_html-source-backup`. If you have an existing working PHP
   installation instead, back up its files and database before upgrading.
2. Upload and extract this ZIP one level above the old public folder as described
   above. The new `public_html/index.php` must be directly in the document root.
3. In Hosting Settings, set the document root to
   `domains/desk.mortonsmechanical.com/public_html` relative to your subscription
   home (use the equivalent path displayed by Plesk). Enable PHP 8.3, PDO MySQL
   and cURL. Enable an SSL certificate and use HTTPS.
4. Create an empty MySQL/MariaDB database and a database user in Plesk. Do NOT
   delete an existing database to do this.
5. In the sibling `private` folder, copy `config.example.php` to `config.php`.
   Enter the exact database name, username and password. Confirm `base_url` is
   `https://desk.mortonsmechanical.com`. Set `install_token` to your own random
   secret code of at least 24 characters. Never paste passwords or tokens in chat.
   If upgrading an installed PHP version, keep its existing `config.php` and
   owner account. Add optional messaging keys only when you connect those services.
6. Open `https://desk.mortonsmechanical.com/setup.php`. Enter your installation
   code, email, and a password of at least 14 characters. This creates the database
   tables and owner account. Do not run setup again for an existing installation.
7. Clear `install_token` from config.php and remove `public_html/setup.php` after
   successful setup. Setup locks automatically after an account exists.
8. Sign in at `https://desk.mortonsmechanical.com/login.php`.

The schema is also included at `private/schema.sql` for manual imports. The setup
page creates the same tables automatically, so normally no SQL import is needed.
If PHP open_basedir prevents access to the private directory, add the sibling
private path to this subdomain's permitted PHP paths in Plesk.

## Bring over your saved records

In the hosted Service Desk, open Settings and select Export records. Clock out
first, and cancel or settle any active Stripe links before exporting. In the PHP desk's Settings, select Import records backup and choose that
JSON file. Import into an empty desk BEFORE saving business settings or creating
records. Old Stripe checkout links are removed during import; historical cash and
Stripe payment ledgers and stopped work sessions are retained. Back up both the
MySQL database and private/config.php regularly.

## Everyday use

- Add/select customers for appointments, estimates and invoices.
- Scan part numbers/barcodes to add or remove inventory.
- Open an estimate/invoice, enter a labor rate, then Clock In / Clock Out.
- Clock Out automatically updates one cumulative tracked-labor line.
- Convert an estimate to an invoice once; afterward the button opens that invoice.
- Estimate edits and tracked labor update the linked eligible invoice.
- Invoices with payments, active Stripe links or running timers remain unchanged
  during synchronization, with a visible notice.
- Record partial cash payments or create partial Stripe checkout links.
- Choose repair progress and select Send update from the document.
- Print/PDF opens a dedicated document page. Use Print / Save PDF, turn off browser
  Headers and footers, and enable background graphics for the blue header/logo.

## Optional Stripe connection

Enter `stripe_key` and `stripe_webhook_secret` in private/config.php. Start with
Stripe sandbox credentials. Use an account you control and a restricted key with
access to create/read/expire Checkout Sessions. Do not use the website to collect
card details; Checkout stays on Stripe.

In Stripe Workbench, add this webhook destination:
`https://desk.mortonsmechanical.com/webhook.php`

Events: `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
and `checkout.session.expired`. Put the resulting signing secret in config.php.
Replace the last eight letters of `integration_identifier` with eight random
letters for this installation. Test a partial payment and final cash balance
before using live credentials. No card is charged during installation.

## Optional messaging connection — currently paused

Leave these config values empty until your Twilio sender is ready:
`twilio_account_sid`, `twilio_auth_token`, `twilio_messaging_service_sid`.

For optional email delivery set `resend_api_key` and `message_from_email` using
a verified sender. Draft/copy-message works with no provider connected.
Submitted means the sending provider accepted the message; it does not confirm
delivery. Unknown/pending sends must be checked at the provider before composing
another message. Replies go to the provider's sending number/email; this package
has no inbound customer-message inbox.

## Troubleshooting

- 403 Forbidden: confirm that index.php is directly in the configured document
  root and that you extracted the PHP package, not the TypeScript source export.
  Inspect Plesk Logs for the exact server error.
- Setup required: create private/config.php in the sibling private folder.
- Database error: verify database credentials, database grants and PDO MySQL.
- Blank page: check Plesk Logs. Detailed errors are logged rather than displayed.
- Login does not stay signed in: use HTTPS and confirm base_url matches the domain.
- Failed scan: barcode/part number must match exactly one part; a keyboard scanner
  should end each scan with Enter.
- Stripe reservation pending: retry Create Stripe payment link to recover the
  existing operation. Do not receive cash while a link/reservation is active.

## Source and validation

`developer-source/` contains React/TypeScript screen sources, library helpers,
build script and package.json. To rebuild after extracting the ZIP, run npm install
and npm run build inside developer-source/source/. PHP backend source is in private/ and public_html/.
The browser bundles are already supplied under public_html/assets/.

PHP syntax and focused timer, labor, sync, payment and messaging-input checks
passed locally. MySQL connections, Plesk login, HTTPS, hardware barcode scanning,
print preview and live Stripe/Twilio/Resend delivery still need verification on
BreezeHost. No database passwords or provider API secrets are included.
