<?php
declare(strict_types=1);
require dirname(__DIR__).'/private/bootstrap.php';
require dirname(__DIR__).'/private/google-lib.php';
$owner=user(true);
$lock=null; $result=null; $status=200;
try {
    gb_allowed($owner); gb_ready();
    $method=$_SERVER['REQUEST_METHOD'];
    if (!in_array($method,['GET','POST'],true)) {jsonout(['error'=>'Method not allowed'],405);}
    $action=$_GET['action']??'status';
    $r=[];
    if ($method==='POST') {
        csrf();
        $raw=file_get_contents('php://input');
        if (strlen($raw)>50000) throw new InvalidArgumentException('Request too large.');
        $r=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if (!is_array($r)) throw new InvalidArgumentException('Invalid request.');
        // Serialize changes from all browser sessions for this desk owner.
        $lock='mortons-google-'.$owner;
        $q=db()->prepare('SELECT GET_LOCK(?, 5)'); $q->execute([$lock]);
        if ((int)$q->fetchColumn()!==1) {$lock=null;throw new InvalidArgumentException('Another Google change is running. Wait and reload.');}
    }
    if ($method==='GET' && $action==='status') {
        $c=gb_connection($owner);
        $result=['connected'=>(bool)$c,'selected'=>$c?['account'=>$c['account_name'],'location'=>$c['location_name']]:null];
    } elseif ($method==='GET' && $action==='accounts') {
        $result=['accounts'=>gb_list($owner,'accounts','accounts','accounts',['pageSize'=>20])];
    } elseif ($method==='GET' && $action==='locations') {
        $account=gb_name($_GET['account']??'','accounts');
        $result=['locations'=>gb_list($owner,'info',$account.'/locations','locations',['readMask'=>'name,title,storefrontAddress','pageSize'=>100])];
    } elseif ($method==='POST' && $action==='select') {
        $account=gb_name($r['account']??'','accounts'); $location=gb_name($r['location']??'','locations');
        // Prove membership; do not bind arbitrary caller-supplied account/location pairs.
        $locations=gb_list($owner,'info',$account.'/locations','locations',['readMask'=>'name,title','pageSize'=>100]);
        if (!in_array($location,array_column($locations,'name'),true)) throw new InvalidArgumentException('That location is not in the selected Google account.');
        $q=db()->prepare('UPDATE google_connections SET account_name=?,location_name=? WHERE owner=?');
        $q->execute([$account,$location,$owner]); $result=['saved'=>true];
    } elseif ($method==='POST' && $action==='disconnect') {
        // Remove locally; Google's account permissions page can revoke the app grant.
        $q=db()->prepare('DELETE FROM google_connections WHERE owner=?'); $q->execute([$owner]);
        unset($_SESSION['gb_oauth']); $result=['disconnected'=>true];
    } elseif ($method==='GET' && $action==='profile') {
        $c=gb_selected($owner); $p=gb_profile($owner,$c);
        $result=['profile'=>$p,'revisions'=>[
            'regular'=>gb_revision($p['regularHours']??[]),
            'special'=>gb_revision($p['specialHours']??[]),
            'status'=>gb_revision($p['openInfo']['status']??'')
        ]];
    } elseif ($method==='GET' && $action==='posts') {
        $c=gb_selected($owner);
        $query=['pageSize'=>50];
        if(!empty($_GET['pageToken'])) {
            if(!is_string($_GET['pageToken'])||strlen($_GET['pageToken'])>4096) throw new InvalidArgumentException('Invalid page token.');
            $query['pageToken']=$_GET['pageToken'];
        }
        $data=gb_api($owner,'posts',$c['account_name'].'/'.$c['location_name'].'/localPosts','GET',null,$query);
        $result=['posts'=>array_map(fn($p)=>['name'=>$p['name'],'summary'=>$p['summary']??'','topicType'=>$p['topicType']??'','state'=>$p['state']??'','revision'=>gb_revision($p)],$data['localPosts']??[]),'nextPageToken'=>$data['nextPageToken']??''];
    } elseif ($method==='POST' && in_array($action,['regular','special','status','edit_post','create_post'],true)) {
        $c=gb_selected($owner,is_string($r['location']??null)?$r['location']:'');
        if (in_array($action,['regular','special','status'],true)) {
            $p=gb_profile($owner,$c);
            if ($action==='regular') {
                gb_unchanged($p['regularHours']??[],$r['revision']??null);
                $hours=gb_regular($r['periods']??[]);
                $result=gb_api($owner,'info',$c['location_name'],'PATCH',['regularHours'=>$hours],['updateMask'=>'regularHours']);
            } elseif ($action==='special') {
                gb_unchanged($p['specialHours']??[],$r['revision']??null);
                if (empty($p['regularHours']['periods'])) throw new InvalidArgumentException('Set regular opening hours before adding special hours.');
                $hours=gb_special($p['specialHours']??[],$r['date']??'',$r['mode']??'',$r['periods']??[]);
                $result=gb_api($owner,'info',$c['location_name'],'PATCH',['specialHours'=>$hours],['updateMask'=>'specialHours']);
            } else {
                gb_unchanged($p['openInfo']['status']??'',$r['revision']??null);
                if (!in_array($r['status']??'',['OPEN','CLOSED_TEMPORARILY'],true)) throw new InvalidArgumentException('Choose open or temporarily closed.');
                $result=gb_api($owner,'info',$c['location_name'],'PATCH',['openInfo'=>['status'=>$r['status']]],['updateMask'=>'openInfo.status']);
            }
            $result=['saved'=>true,'message'=>'Google accepted the change. Public display may take time to update.'];
        } else {
            $parent=$c['account_name'].'/'.$c['location_name'].'/localPosts';
            $summary=gb_summary($r['summary']??null);
            if ($action==='edit_post') {
                $name=$r['name']??'';
                if (!is_string($name)||!preg_match('~^'.preg_quote($parent,'~').'/[A-Za-z0-9_-]+$~D',$name)) throw new InvalidArgumentException('Invalid post for the selected business.');
                $old=gb_api($owner,'posts',$name);
                gb_unchanged($old,$r['revision']??null);
                // Only summary changes: preserve media, offers, events, and call-to-action.
                $post=gb_api($owner,'posts',$name,'PATCH',['summary'=>$summary],['updateMask'=>'summary']);
            } else {
                $id=$r['operationId']??'';
                if(!is_string($id)||!preg_match('/^[a-f0-9-]{36}$/Di',$id)) throw new InvalidArgumentException('Invalid post operation.');
                $q=db()->prepare('SELECT state,post_name FROM google_post_operations WHERE owner=? AND operation_id=?');
                $q->execute([$owner,$id]); $prior=$q->fetch(PDO::FETCH_ASSOC);
                if($prior) {
                    if($prior['state']==='accepted') $post=['name'=>$prior['post_name']];
                    else throw new InvalidArgumentException('This post submission is unconfirmed. Reload posts and check Google before starting another post.');
                } else {
                    // Persist before contacting Google. Never automatically retry creation after an ambiguous response.
                    $q=db()->prepare("INSERT INTO google_post_operations(owner,operation_id,state) VALUES(?,?,'unconfirmed')");
                    $q->execute([$owner,$id]);
                    $post=gb_api($owner,'posts',$parent,'POST',['languageCode'=>'en-US','topicType'=>'STANDARD','summary'=>$summary]);
                    $q=db()->prepare("UPDATE google_post_operations SET state='accepted',post_name=? WHERE owner=? AND operation_id=?");
                    $q->execute([$post['name']??'',$owner,$id]);
                }
            }
            $result=['saved'=>true,'message'=>'Google accepted the post. It may be reviewed before appearing publicly.','post'=>$post];
        }
    } else {
        $status=405; $result=['error'=>'Unknown action or incorrect method.'];
    }
} catch (Throwable $e) {
    if($e instanceof InvalidArgumentException || $e instanceof JsonException || $e instanceof TypeError) {
        $status=400;
        $result=['error'=>$e instanceof TypeError ? 'Invalid field format. Reload the page and try again.' : $e->getMessage()];
    } else {
        error_log('Mortons Google: '.$e->getMessage());
        $status=500;
        $result=['error'=>'Unable to complete Google request. Check hosting logs and confirm google-schema.sql has been imported.'];
    }
} finally {
    if ($lock!==null) {try {$q=db()->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}catch(Throwable $ignored){}}
}
jsonout($result,$status);
