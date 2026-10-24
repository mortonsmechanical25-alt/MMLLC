<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/private/google-lib.php';
$count=0;
function check(bool $ok,string $message):void {global $count;if(!$ok)throw new RuntimeException($message);$count++;}
function rejects(callable $fn,string $message):void {try{$fn();}catch(InvalidArgumentException $e){check(true,$message);return;}throw new RuntimeException($message);}
$full=['openDay'=>'MONDAY','openTime'=>'00:00','closeDay'=>'MONDAY','closeTime'=>'24:00'];
check(gb_regular([$full])['periods'][0]['closeTime']['hours']===24,'24-hour opening period');
$overnight=['openDay'=>'SUNDAY','openTime'=>'22:00','closeDay'=>'MONDAY','closeTime'=>'02:00'];
check(count(gb_regular([$overnight])['periods'])===1,'Sunday wrapping into Monday');
$overlap=['openDay'=>'MONDAY','openTime'=>'01:00','closeDay'=>'MONDAY','closeTime'=>'03:00'];
rejects(fn()=>gb_regular([$overnight,$overlap]),'Cross-week overlap rejected');
rejects(fn()=>gb_regular([$full,$full]),'Duplicate periods rejected');
rejects(fn()=>gb_regular([]),'Empty regular schedule rejected');
rejects(fn()=>gb_time('24:01',true),'Invalid time rejected');
rejects(fn()=>gb_date('2026-02-29'),'Invalid leap day rejected');
check(gb_date('2028-02-29')['day']===29,'Leap day allowed');
$holiday=['startDate'=>gb_date('2026-12-25'),'closed'=>true];
$existing=['specialHourPeriods'=>[$holiday]];
$next=gb_special($existing,'2026-11-26','closed',[]);
check($next['specialHourPeriods'][0]===$holiday,'Unrelated holiday preserved exactly');
check(count($next['specialHourPeriods'])===2,'One-day closure appended');
$restored=gb_special($next,'2026-11-26','regular',[]);
check($restored===$existing,'Removing exception restores regular hours only for that date');
$split=gb_special($existing,'2026-11-26','hours',[['open'=>'08:00','close'=>'12:00'],['open'=>'13:00','close'=>'17:00']]);
check(count($split['specialHourPeriods'])===3,'Split holiday periods');
rejects(fn()=>gb_special($existing,'2026-11-26','hours',[['open'=>'08:00','close'=>'12:00'],['open'=>'11:00','close'=>'17:00']]),'Overlapping special periods rejected');
$overnightSpecial=['specialHourPeriods'=>[['startDate'=>gb_date('2026-11-25'),'endDate'=>gb_date('2026-11-26'),'openTime'=>['hours'=>22],'closeTime'=>['hours'=>2]]]];
rejects(fn()=>gb_special($overnightSpecial,'2026-11-26','closed',[]),'Earlier overnight special hours are not silently lost');
check(gb_revision(['a'=>1,'b'=>2])===gb_revision(['b'=>2,'a'=>1]),'Object order does not cause stale-data rejection');
rejects(fn()=>gb_unchanged(['a'=>2],gb_revision(['a'=>1])),'Stale edit rejected');
rejects(fn()=>gb_name('locations/123/../../other','locations'),'Resource traversal rejected');
check(gb_summary(' Hello ')==='Hello','Summary validation');
rejects(fn()=>gb_summary(str_repeat('x',1501)),'Post length limit');
echo "Passed ".$count." Google validation checks.\n";
