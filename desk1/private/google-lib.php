<?php
declare(strict_types=1);

/** No bootstrap side effects: pure validators can be checked from the CLI. */
function gb_config(): array {
    static $config;
    if ($config !== null) return $config;
    $path = __DIR__.'/google.php';
    if (!is_file($path)) throw new InvalidArgumentException('Google setup required. Follow GOOGLE_SETUP.md and create private/google.php.');
    $config = require $path;
    if (!is_array($config)) throw new InvalidArgumentException('Invalid Google configuration.');
    return $config;
}
function gb_allowed(int $owner): void {
    desk_require_owner(true);
    if (!in_array($owner, gb_config()['allowed_users'] ?? [], true))
        throw new InvalidArgumentException('This Service Desk user is not allowed to manage Google. Ask the owner to configure allowed_users.');
}
function gb_ready(): void {
    $c = gb_config();
    if (empty($c['client_id']) || empty($c['client_secret'])) throw new InvalidArgumentException('Add your Google OAuth client ID and secret to private/google.php.');
    gb_key();
    if (!extension_loaded('curl') || !extension_loaded('openssl')) throw new InvalidArgumentException('Enable PHP cURL and OpenSSL in hosting settings.');
    global $cfg;
    if (parse_url($cfg['base_url'], PHP_URL_SCHEME) !== 'https') throw new InvalidArgumentException('Google connection requires an HTTPS base_url.');
}
function gb_key(): string {
    $key = base64_decode(gb_config()['encryption_key'] ?? '', true);
    if ($key === false || strlen($key) !== 32) throw new InvalidArgumentException('Set encryption_key to a base64-encoded 32-byte random key in private/google.php.');
    return $key;
}
function gb_encrypt(string $value, int $owner): string {
    $iv = random_bytes(12); $tag = '';
    $cipher = openssl_encrypt($value, 'aes-256-gcm', gb_key(), OPENSSL_RAW_DATA, $iv, $tag, 'mortons-google-'.$owner);
    if ($cipher === false) throw new RuntimeException('Token encryption failed.');
    return base64_encode($iv.$tag.$cipher);
}
function gb_decrypt(string $value, int $owner): string {
    $raw = base64_decode($value, true);
    if ($raw === false || strlen($raw) < 29) throw new RuntimeException('Invalid stored Google token.');
    $plain = openssl_decrypt(substr($raw,28), 'aes-256-gcm', gb_key(), OPENSSL_RAW_DATA, substr($raw,0,12), substr($raw,12,16), 'mortons-google-'.$owner);
    if ($plain === false) throw new InvalidArgumentException('Google token cannot be decrypted. Restore the original encryption key or reconnect Google.');
    return $plain;
}
function gb_connection(int $owner): ?array {
    $q = db()->prepare('SELECT * FROM google_connections WHERE owner=?');
    $q->execute([$owner]); return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}
function gb_http(string $url, string $method='GET', ?array $body=null, ?string $token=null, bool $form=false): array {
    // Callers construct URLs; no arbitrary upstream URL or redirect is accepted.
    $host = parse_url($url, PHP_URL_HOST);
    if (!in_array($host, ['oauth2.googleapis.com','mybusinessaccountmanagement.googleapis.com','mybusinessbusinessinformation.googleapis.com','mybusiness.googleapis.com'], true))
        throw new RuntimeException('Invalid Google endpoint.');
    $headers = ['Accept: application/json'];
    if ($token !== null) $headers[] = 'Authorization: Bearer '.$token;
    if ($body !== null) $headers[] = $form ? 'Content-Type: application/x-www-form-urlencoded' : 'Content-Type: application/json';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>30, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_HTTPHEADER=>$headers]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $form ? http_build_query($body) : json_encode($body, JSON_THROW_ON_ERROR));
    $raw = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    if ($raw === false) throw new InvalidArgumentException('Google did not confirm the request. Reload the profile or posts to check before retrying a change.');
    $data = json_decode($raw, true);
    if ($status < 200 || $status >= 300) {
        $reason = is_array($data) ? ($data['error'] ?? '') : '';
        if ($reason === 'invalid_grant') throw new InvalidArgumentException('Google authorization expired or was revoked. Reconnect Google.');
        $hint = match($status) {
            401 => 'Reconnect Google; authorization has expired.',
            403 => 'Check Google API approval, enabled APIs, business.manage permission, and your profile owner/manager access.',
            404 => 'This profile or post is unavailable. Reload accounts and select the profile again.',
            429 => 'Google quota was reached. Wait before trying again; a zero quota requires API approval.',
            default => 'Reload Google data to check the result before retrying.'
        };
        $detail = is_array($reason) ? ($reason['message'] ?? '') : '';
        throw new InvalidArgumentException('Google HTTP '.$status.'. '.$hint.($detail ? ' '.substr($detail,0,700) : ''));
    }
    if (!is_array($data)) throw new InvalidArgumentException('Unexpected Google response. Reload to check the result.');
    return $data;
}
function gb_token(int $owner): string {
    static $tokens = [];
    if (isset($tokens[$owner])) return $tokens[$owner];
    $row = gb_connection($owner);
    if (!$row) throw new InvalidArgumentException('Connect your Google account first.');
    $c = gb_config();
    $data = gb_http('https://oauth2.googleapis.com/token', 'POST', [
        'client_id'=>$c['client_id'], 'client_secret'=>$c['client_secret'],
        'refresh_token'=>gb_decrypt($row['refresh_token'], $owner), 'grant_type'=>'refresh_token'
    ], null, true);
    if (empty($data['access_token'])) throw new InvalidArgumentException('Google did not return an access token. Reconnect Google.');
    return $tokens[$owner] = $data['access_token'];
}
function gb_api(int $owner, string $service, string $path, string $method='GET', ?array $body=null, array $query=[]): array {
    $base = match($service) {
        'accounts'=>'https://mybusinessaccountmanagement.googleapis.com/v1/',
        'info'=>'https://mybusinessbusinessinformation.googleapis.com/v1/',
        'posts'=>'https://mybusiness.googleapis.com/v4/',
        default=>throw new RuntimeException('Invalid Google service.')
    };
    return gb_http($base.$path.($query ? '?'.http_build_query($query) : ''), $method, $body, gb_token($owner));
}
function gb_list(int $owner, string $service, string $path, string $key, array $query=[]): array {
    $items = []; $seen = [];
    do {
        $data = gb_api($owner,$service,$path,'GET',null,$query);
        $items = array_merge($items,$data[$key] ?? []);
        $next = $data['nextPageToken'] ?? '';
        if ($next && isset($seen[$next])) throw new InvalidArgumentException('Google repeated a pagination token. Try again.');
        $seen[$next] = true; $query['pageToken'] = $next;
        if (count($seen)>100) throw new InvalidArgumentException('Too many Google results. Use a smaller account.');
    } while ($next);
    return $items;
}
function gb_name(mixed $name, string $kind): string {
    if (!is_string($name) || !preg_match('~^'.$kind.'/[A-Za-z0-9_-]+$~D',$name)) throw new InvalidArgumentException('Invalid Google resource.');
    return $name;
}
function gb_selected(int $owner, ?string $expected=null): array {
    $c = gb_connection($owner);
    if (!$c || !$c['account_name'] || !$c['location_name']) throw new InvalidArgumentException('Select a Google business profile first.');
    gb_name($c['account_name'],'accounts'); gb_name($c['location_name'],'locations');
    if ($expected !== null && $expected !== $c['location_name']) throw new InvalidArgumentException('Selected profile changed. Reload this page before saving.');
    return $c;
}
function gb_profile(int $owner, array $c): array {
    return gb_api($owner,'info',$c['location_name'],'GET',null,['readMask'=>'name,title,regularHours,specialHours,openInfo,metadata']);
}
function gb_revision(mixed $value): string {
    $normalize = function($v) use (&$normalize) {
        if (!is_array($v)) return $v;
        if (!array_is_list($v)) ksort($v);
        return array_map($normalize,$v);
    };
    return hash('sha256',json_encode($normalize($value),JSON_THROW_ON_ERROR));
}
function gb_unchanged(mixed $value, mixed $revision): void {
    if (!is_string($revision) || !hash_equals(gb_revision($value),$revision))
        throw new InvalidArgumentException('Google data changed since you loaded it. Reload and review before saving.');
}
function gb_time(string $value, bool $closing=false): array {
    if ($closing && $value === '24:00') return ['hours'=>24,'minutes'=>0];
    if (!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/D',$value,$m)) throw new InvalidArgumentException('Use 24-hour time HH:MM. Only a closing time may be 24:00.');
    return ['hours'=>(int)$m[1],'minutes'=>(int)$m[2]];
}
function gb_minutes(array $t): int {return ($t['hours'] ?? 0)*60+($t['minutes'] ?? 0);}
function gb_no_overlap(array $ranges): void {
    usort($ranges,fn($a,$b)=>$a[0]<=>$b[0]);
    for ($i=1;$i<count($ranges);$i++) if ($ranges[$i][0]<$ranges[$i-1][1]) throw new InvalidArgumentException('Opening periods overlap.');
}
function gb_regular(array $rows): array {
    $days = ['MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY','SUNDAY'];
    if (!$rows || count($rows)>100) throw new InvalidArgumentException('Add at least one opening period. Use temporary closure to close the business.');
    $periods=[]; $ranges=[];
    foreach ($rows as $r) {
        if (!is_array($r)) throw new InvalidArgumentException('Invalid opening period.');
        $start=array_search($r['openDay']??'', $days,true); $end=array_search($r['closeDay']??'', $days,true);
        if ($start===false||$end===false) throw new InvalidArgumentException('Choose opening and closing days.');
        $ot=gb_time($r['openTime']??''); $ct=gb_time($r['closeTime']??'',true);
        $a=$start*1440+gb_minutes($ot); $b=$end*1440+gb_minutes($ct);
        if ($b<$a) $b+=10080;
        if ($b<=$a||$b-$a>1440) throw new InvalidArgumentException('Each opening period must be greater than zero and no longer than 24 hours.');
        $periods[]=['openDay'=>$days[$start],'openTime'=>$ot,'closeDay'=>$days[$end],'closeTime'=>$ct];
        $ranges[]=[$a,$b]; $ranges[]=[$a+10080,$b+10080];
    }
    gb_no_overlap($ranges);
    return ['periods'=>$periods];
}
function gb_date(string $date): array {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$date,$m) || !checkdate((int)$m[2],(int)$m[3],(int)$m[1]))
        throw new InvalidArgumentException('Choose a valid date.');
    return ['year'=>(int)$m[1],'month'=>(int)$m[2],'day'=>(int)$m[3]];
}
function gb_date_string(array $d): string {return sprintf('%04d-%02d-%02d',$d['year']??0,$d['month']??0,$d['day']??0);}
function gb_special(array $existing, string $date, string $mode, array $rows): array {
    $d=gb_date($date);
    if (!in_array($mode,['closed','hours','regular'],true)) throw new InvalidArgumentException('Invalid special-hours action.');
    $periods=$existing['specialHourPeriods']??[]; $keep=[];
    foreach ($periods as $p) {
        $start=gb_date_string($p['startDate']); $end=gb_date_string($p['endDate']??$p['startDate']);
        if ($start===$date) {
            if ($end!==$date) throw new InvalidArgumentException('This date has an overnight special period. Adjust that period in Google first to avoid changing the following day.');
            continue;
        }
        if ($start<$date && $end>=$date) throw new InvalidArgumentException('An earlier special period extends into this date. Adjust it in Google first.');
        $keep[]=$p;
    }
    if ($mode==='closed') $keep[]=['startDate'=>$d,'endDate'=>$d,'closed'=>true];
    if ($mode==='hours') {
        if (!$rows||count($rows)>20) throw new InvalidArgumentException('Add opening times for this date.');
        $ranges=[];
        foreach($rows as $r) {
            $ot=gb_time($r['open']??''); $ct=gb_time($r['close']??'',true);
            if(gb_minutes($ct)<=gb_minutes($ot)||gb_minutes($ct)-gb_minutes($ot)>=1440) throw new InvalidArgumentException('Special hours must end later on the same date and each period must be shorter than 24 hours. Use two adjacent periods for a full day.');
            $ranges[]=[gb_minutes($ot),gb_minutes($ct)];
            $keep[]=['startDate'=>$d,'endDate'=>$d,'openTime'=>$ot,'closeTime'=>$ct,'closed'=>false];
        }
        gb_no_overlap($ranges);
    }
    return ['specialHourPeriods'=>$keep];
}
function gb_summary(mixed $summary): string {
    if (!is_string($summary) || !preg_match('//u',$summary) || trim($summary)==='') throw new InvalidArgumentException('Enter post text.');
    if (preg_match_all('/./us',$summary)>1500) throw new InvalidArgumentException('Posts may contain at most 1,500 characters.');
    return trim($summary);
}

