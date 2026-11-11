<?php
namespace IspModsecurity;
require_once __DIR__.'/I18n.php';
final class Diagnostics {
    public const MAX_BYTES=2097152;
    public const MAX_EVENTS=100;
    public static function logPath(string $domain, string $base='/var/log/ispconfig/httpd'): string {
        if(strlen($domain)>253 || !preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/iD',$domain) || str_contains($domain,'..')) throw new \RuntimeException(\IspModsecurity\I18n::t('invalid_domain'));
        $path=$base.'/'.$domain.'/error.log';
        // Never follow a client-created link to another site's log or an arbitrary root-readable file.
        if(realpath($path)!==$path || is_link(dirname($path)) || is_link($path)) throw new \RuntimeException(\IspModsecurity\I18n::t('log_unavailable'));
        return $path;
    }
    public static function read(string $path): array {
        $f=@fopen($path,'rb');
        if(!$f) throw new \RuntimeException(\IspModsecurity\I18n::t('log_unreadable'));
        try {
            $st=fstat($f);
            if(!$st || ($st['mode'] & 0170000)!==0100000 || $st['uid']!==0) throw new \RuntimeException(\IspModsecurity\I18n::t('log_owner'));
            $start=max(0,$st['size']-self::MAX_BYTES);
            if(fseek($f,$start)!==0) throw new \RuntimeException(\IspModsecurity\I18n::t('log_seek'));
            $text=stream_get_contents($f,self::MAX_BYTES);
            if($text===false) throw new \RuntimeException(\IspModsecurity\I18n::t('log_read'));
            if($start>0) { $nl=strpos($text,"\n"); $text=$nl===false?'':substr($text,$nl+1); }
            // Ignore an incomplete last write; the next refresh will collect the completed line.
            if($text!=='' && !str_ends_with($text,"\n")) { $nl=strrpos($text,"\n"); $text=$nl===false?'':substr($text,0,$nl+1); }
            return ['events'=>self::parse($text), 'truncated'=>$start>0, 'scanned_bytes'=>strlen($text)];
        } finally { fclose($f); }
    }
    private static function field(string $line,string $key,int $max=1024): string {
        if(!preg_match('/\['.preg_quote($key,'/').' "((?:\\\\.|[^"\\\\])*)"\]/',$line,$m)) return '';
        return substr(preg_replace('/\\\\(["\\\\])/', '$1', $m[1]),0,$max);
    }
    public static function parse(string $text): array {
        $events=[];
        // Reverse scan retains the latest 100 requests, even when unrelated logs dominate.
        foreach(array_reverse(explode("\n",$text)) as $index=>$line) {
            if(strlen($line)>65536 || !str_contains($line,'ModSecurity:')) continue;
            $id=self::field($line,'id',16);
            if(!ctype_digit($id)) continue;
            $unique=self::field($line,'unique_id',128);
            $key=$unique!==''?'request:'.$unique:'line:'.$index;
            if(!isset($events[$key])) {
                if(count($events)>=self::MAX_EVENTS) continue;
                preg_match('/^\[([^\]]+)\]/',$line,$time);
                $uri=self::field($line,'uri');
                $events[$key]=['unique_id'=>$unique,'time'=>$time[1]??'','hostname'=>self::field($line,'hostname',253),'uri'=>explode('?', $uri,2)[0],'blocked'=>false,'rules'=>[],'detection_ids'=>[],'summary_only'=>true];
            }
            $summary=(bool)preg_match('/^(949|959|980)[0-9]{3}$/D',$id);
            $events[$key]['blocked']=$events[$key]['blocked'] || str_contains($line,'Access denied');
            $rule=['id'=>(int)$id,'message'=>self::field($line,'msg'),'severity'=>self::field($line,'severity',32),'summary'=>$summary];
            $events[$key]['rules'][$id]=$rule;
            if(!$summary) { $events[$key]['detection_ids'][$id]=(int)$id; $events[$key]['summary_only']=false; }
        }
        foreach($events as &$event) { $event['rules']=array_values($event['rules']); $event['detection_ids']=array_values($event['detection_ids']); }
        return array_values($events);
    }
}
