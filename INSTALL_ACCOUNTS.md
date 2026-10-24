# Accounts tab installation

This adds staff logins to the existing Service Desk. The original owner and all
existing records stay in place. Additional accounts share the creating owner's
workspace; existing independent users keep their own workspaces.

1. Back up your website files and database.
2. Extract Service-Desk-Accounts-Install.zip. Upload the contents of
   desk1/desk.mortonsmechanical.com/ to your existing document root, replacing
   matching files only. Preserve other files, including API/payment/print files.
3. Upload desk1/private/ to the private directory beside config.php, outside the
   web root. Do not overwrite or recreate config.php. All included PHP files
   must be uploaded together: bootstrap.php now loads accounts-lib.php.
4. In phpMyAdmin, select the EXISTING desk database and import ONLY
   private/accounts-schema.sql. This creates desk_memberships; it does not alter
   users, delete records, or change your password. Do not import deskpri.sql.
5. Sign in with your current owner login and open Accounts. Set an email, a
   password of 14–72 bytes, and a role; enter your own password to authorize
   creation. No email is automatically sent. The new login uses the same login.php.

Choose Read-only for a reviewer: this account can view shared records and print
documents, while the server blocks write requests. Staff can work with the
business records but cannot manage accounts, Google connection, or file updates.
No new owners can be created through the Accounts page. Your current owner login
retains owner access. If other independent owners existed before this upgrade,
they remain owners of their separate workspaces.

The interface may show normal editing controls to a Read-only user; any attempt
to save is rejected by the backend. Read-only users can see and export business
records, so choose accounts accordingly.

Use Manage to change Staff/Read-only access, disable a login, or set a replacement
password. The current password is never shown. Role, password, and enabled-state
changes invalidate existing account sessions on the next request. There is no
delete button, self-owner demotion, self-service password change or email reset
workflow in this release.

If the membership table has not been imported, existing logins still work and
the Accounts page explains the missing setup. If a database error prevents
checking membership, authentication fails closed rather than granting access.

This update changes core login and authorization files and adds a database table.
It requires a one-time manual installation and cannot be installed by the
restricted Site updates ZIP uploader. The package includes the latest updater
and styling files too; see INSTALL_UPDATER.md for its first-time hosting setup.

The hosted preview has sample account controls only. It does not create real
Service Desk credentials. Set real passwords on your installed site; do not
send them in chat.

Rollback: before restoring older login code, invalidate password hashes for ALL
added member accounts in users (after backing them up), and clear existing PHP
sessions through hosting. Disabling them only in desk_memberships is not enough:
older login code does not enforce that table. Restore the complete previous
website/private-file backup together, including bootstrap.php, login.php,
google-lib.php and app.js. Retain desk_memberships and account data until you
have planned the rollback. Existing accounts and records are never deleted by
this installer.
