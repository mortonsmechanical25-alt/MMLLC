<?php
declare(strict_types=1);
function desk_tools_start(string $title,string $active): void { ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($title)?> | Mortons Mechanical</title><link rel="icon" href="/favicon.svg"><link rel="stylesheet" href="/assets/app.css"><link rel="stylesheet" href="/assets/service-tools.css?v=2"><meta name="csrf-token" content="<?=h($_SESSION['csrf'])?>"></head><body class="tools-page"><div class="shell">
<aside class="sidebar"><a class="brand" href="/"><span class="brand-icon" aria-hidden="true">M</span><span>MORTONS<small>MECHANICAL LLC</small></span></a><div class="workspace-label">SERVICE DESK <span>NH</span></div><nav aria-label="Workspace"><?php
$navItems=[['Overview','/index.php?section=Overview','▦'],['Appointments','/index.php?section=Appointments','▤'],['Customers','/index.php?section=Customers','♙'],['Parts inventory','/index.php?section=Parts%20inventory','◇'],['Estimates','/index.php?section=Estimates','▧'],['Invoices','/index.php?section=Invoices','▥'],['Messages','/index.php?section=Messages','▱'],['Settings','/index.php?section=Settings','⚙'],['Accounts','/accounts.php','♙'],['Google Business','/google.php','G'],['Site updates','/updates.php','↑']];
foreach($navItems as [$label,$url,$icon]){
 $isActive=($active==='accounts'&&$label==='Accounts')||($active==='google'&&$label==='Google Business')||($active==='updates'&&$label==='Site updates');
 echo '<a class="tool-nav'.($isActive?' active':'').'" href="'.h($url).'"><span aria-hidden="true">'.h($icon).'</span>'.h($label).'</a>';
}
?></nav><div class="side-bottom"><span class="status-dot"></span>Private workspace<p>Your business. All in one place.</p><a href="tel:6038565389">603-856-5389</a></div></aside>
<div class="main"><header class="topbar"><div>Workspace<span class="slash">/</span><span class="muted"><?=h($title)?></span></div><div class="top-right"><span class="private-pill">Owner workspace</span><form method="post" action="/logout.php"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><button class="secondary">Sign out</button></form></div></header><main><div class="page-heading"><div><p class="eyebrow">MORTONS MECHANICAL</p><h1><?=h($title)?></h1></div><a class="secondary" href="/">Back to Service Desk</a></div>
<?php }
function desk_tools_end(): void {echo '</main></div></div></body></html>';}
