<?php
require dirname(__DIR__).'/private/bootstrap.php'; user();
$notice=$_SESSION['gb_notice']??''; unset($_SESSION['gb_notice']);
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Google Business | Mortons Mechanical</title>
<link rel="icon" href="/favicon.svg"><link rel="stylesheet" href="/assets/google.css">
<meta name="csrf-token" content="<?=h($_SESSION['csrf'])?>">
</head><body>
<header><a href="/">← Service Desk</a><strong>Mortons Mechanical</strong></header>
<main>
<h1>Google Business Profile</h1>
<p>Manage the posts and hours customers see on Google.</p>
<div id="notice" role="status" aria-live="polite"><?=h($notice)?></div>
<section>
<h2>Connection</h2>
<p id="connection-status">Checking connection…</p>
<div class="actions">
<form action="/google-oauth.php" method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><button>Connect / reconnect Google</button></form>
<button id="disconnect" class="secondary" hidden>Disconnect from this desk</button>
<button id="reload" class="secondary">Reload Google data</button>
</div>
<p class="muted">First-time setup: follow GOOGLE_SETUP.md in your download. Google API approval and OAuth credentials are required.</p>
<div id="selection" hidden>
<div class="grid">
<label>Google account<select id="account"><option value="">Choose account</option></select></label>
<label>Business profile<select id="location"><option value="">Choose profile</option></select></label>
</div><button id="select-profile">Use this profile</button>
</div>
</section>
<div id="business" hidden>
<section><h2 id="business-title">Business</h2>
<p>All times use your business location’s local time.</p>
<p id="business-status"></p>
<div class="actions"><button id="temporary" class="danger">Temporarily close business</button><button id="reopen">Reopen business</button></div>
<p class="muted">Temporary closure remains until you reopen. For one day or a holiday, use Special hours below. Reopening does not remove existing holiday closures.</p>
</section>
<section>
<h2>Regular hours</h2>
<p>Add one row for each opening period. A day with no row is closed. Use multiple rows for split shifts, the next closing day for overnight hours, or 00:00–24:00 on the same day for 24 hours.</p>
<div class="table-wrap"><table><thead><tr><th>Opens on</th><th>Time</th><th>Closes on</th><th>Time</th><th></th></tr></thead><tbody id="regular-rows"></tbody></table></div>
<div class="actions"><button id="add-regular" class="secondary">Add opening period</button><button id="save-regular">Save regular hours to Google</button></div>
</section>
<section>
<h2>Special hours &amp; one-day closures</h2>
<p>Change one date at a time. Other dates are preserved.</p>
<div id="special-list"></div>
<label>Date<input id="special-date" type="date" required></label>
<label>Schedule<select id="special-mode"><option value="closed">Closed all day</option><option value="hours">Special opening hours</option><option value="regular">Remove exception — use regular hours</option></select></label>
<div id="special-editor" hidden>
<p>Use same-day opening periods; 24:00 means midnight. Multiple periods are supported.</p>
<div id="special-rows"></div>
<button id="add-special" class="secondary">Add opening period</button>
</div>
<button id="save-special">Save date to Google</button>
<p class="muted">Existing overnight special hours are displayed and preserved. Adjust those periods directly in Google before changing either affected date here.</p>
</section>
<section>
<h2>Posts</h2>
<p>Edit post text below. Existing pictures, buttons, event details, and offer details stay attached.</p>
<div id="post-list"></div><button id="more-posts" class="secondary" hidden>Load more posts</button>
<details><summary>Create a new update</summary>
<label>Post text<textarea id="new-summary" rows="5" maxlength="1500"></textarea></label>
<div class="actions"><button id="create-post">Publish update to Google</button><button id="reset-post" class="secondary">Start another post</button></div>
<p class="muted">New updates are text-only. If a request is unconfirmed, reload posts and check Google before starting another post to avoid duplicates.</p>
</details>
</section>
</div>
</main><script src="/assets/google.js" defer></script></body></html>
