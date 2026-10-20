Latest package checks
=====================

PHP 8.3 syntax checks passed for every PHP file (local runtime 8.3.6; target 8.3.35).
The two production browser bundles compiled successfully.
Core checks passed for direct document timers, repeated/idempotent clock actions,
cumulative tracked labor, tax/discount totals, repair progress separate from
billing status, estimate/invoice synchronization, preserving invoice identity and
invoice-only tracked labor, partial cash and mixed Stripe payments, duplicate
payment events, mismatched amount/currency/session guards, checkout expiry,
reservation recovery and customer message draft/phone/length validation.

Writes serialize on the owner row in a MySQL transaction. This covers document
edits, timers, invoice synchronization, cash payments and Stripe webhooks. Checkout
reservations and message-send claims are committed before external HTTP calls.

Not deployed to BreezeHost. No live MySQL/Plesk/provider end-to-end tests were
possible here. SMTP is not used: optional email sending uses Resend over HTTPS.
Twilio remains disconnected. No test messages, real payments or phone-number
purchases were made.
