<?php
namespace IspModsecurity;
require_once __DIR__.'/I18n.php';
final class Config {
    public static function defaults(): array {
        return ['mode'=>'inherit','body_limit'=>null,'nofiles_limit'=>null,'exceptions'=>[]];
    }
    public static function validate(array $c, array $policy, bool $admin = false): array {
        if (!$admin && empty($policy['enabled'])) throw new \InvalidArgumentException(\IspModsecurity\I18n::t('management_denied'));
        $c = array_merge(self::defaults(), $c);
        if (!in_array($c['mode'], ['inherit','On','Off','DetectionOnly'], true)) throw new \InvalidArgumentException(\IspModsecurity\I18n::t('invalid_mode'));
        if (!$admin && empty($policy['allow_disable']) && in_array($c['mode'], ['Off','DetectionOnly'], true)) throw new \InvalidArgumentException(\IspModsecurity\I18n::t('disable_denied'));
        foreach (['body_limit','nofiles_limit'] as $key) {
            if ($c[$key] === '' || $c[$key] === null) { $c[$key] = null; continue; }
            if (!is_int($c[$key]) || $c[$key] < 1 || $c[$key] > 1073741824) throw new \InvalidArgumentException(\IspModsecurity\I18n::t('invalid_limit'));
            if (!$admin && $c[$key] > (int)($policy['max_'.$key] ?? 0)) throw new \InvalidArgumentException(\IspModsecurity\I18n::t('maximum_exceeded'));
        }
        if ($c['body_limit'] !== null && $c['nofiles_limit'] !== null && $c['nofiles_limit'] > $c['body_limit']) throw new \InvalidArgumentException(\IspModsecurity\I18n::t('nofiles_exceeded'));
        if (!is_array($c['exceptions']) || count($c['exceptions']) > 100) throw new \InvalidArgumentException(\IspModsecurity\I18n::t('too_many_exceptions'));
        $rows=[];
        foreach ($c['exceptions'] as $r) {
            if (!is_array($r) || !in_array($r['scope'] ?? '', ['site','exact','prefix'], true)) throw new \InvalidArgumentException(\IspModsecurity\I18n::t('invalid_scope'));
            $path = (string)($r['path'] ?? '');
            if ($r['scope'] !== 'site' && (!preg_match('#^/[A-Za-z0-9_./~+@=-]*$#D', $path) || strlen($path)>1024 || str_contains($path,'..') || str_contains($path,'%'))) throw new \InvalidArgumentException(\IspModsecurity\I18n::t('invalid_path'));
            if ($r['scope']==='prefix' && !str_ends_with($path,'/')) throw new \InvalidArgumentException(\IspModsecurity\I18n::t('invalid_prefix'));
            $off = !empty($r['off']);
            if ($off && (!$admin && empty($policy['allow_disable']))) throw new \InvalidArgumentException(\IspModsecurity\I18n::t('url_disable_denied'));
            if ($off && $r['scope']==='site') throw new \InvalidArgumentException(\IspModsecurity\I18n::t('use_mode'));
            $ids = $r['ids'] ?? [];
            if (!is_array($ids) || count($ids)>100) throw new \InvalidArgumentException(\IspModsecurity\I18n::t('invalid_ids'));
            foreach ($ids as $id) if (!is_int($id) || $id<1 || $id>2147483647) throw new \InvalidArgumentException(\IspModsecurity\I18n::t('invalid_id'));
            if (!$off && !$ids) throw new \InvalidArgumentException(\IspModsecurity\I18n::t('missing_ids'));
            $rows[]=['scope'=>$r['scope'],'path'=>$r['scope']==='site'?'':$path,'off'=>$off,'ids'=>$off?[]:array_values(array_unique($ids)), 'note'=>mb_substr((string)($r['note']??''),0,500)];
        }
        return ['mode'=>$c['mode'],'body_limit'=>$c['body_limit'],'nofiles_limit'=>$c['nofiles_limit'],'exceptions'=>$rows];
    }
    public static function render(array $c): string {
        $c=self::validate($c,[],true);
        $out="# Managed by ISPConfig ModSecurity extension\n<IfModule security2_module>\n";
        if($c['mode']!=='inherit') $out.='    SecRuleEngine '.$c['mode']."\n";
        foreach(['body_limit'=>'SecRequestBodyLimit','nofiles_limit'=>'SecRequestBodyNoFilesLimit'] as $k=>$directive) if($c[$k]!==null) $out.="    $directive ".$c[$k]."\n";
        // Site exclusions precede narrower scopes; exclusions are additive.
        usort($c['exceptions'],fn($a,$b)=>($a['scope']!=='site')<=>($b['scope']!=='site'));
        foreach($c['exceptions'] as $r) {
            $indent='    ';
            if($r['scope']!=='site') {
                $pattern='^'.preg_quote($r['path'],'#').($r['scope']==='exact'?'$':'');
                $out.='    <LocationMatch "'.$pattern.'">'."\n"; $indent.='    ';
            }
            $out.=$indent.($r['off']?'SecRuleEngine Off':'SecRuleRemoveById '.implode(' ',$r['ids']))."\n";
            if($r['scope']!=='site') $out.="    </LocationMatch>\n";
        }
        return $out."</IfModule>\n";
    }
}
