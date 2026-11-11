<?php
namespace IspModsecurity;
require_once __DIR__.'/I18n.php';
final class View {
    private static function escape($value) {
        if(is_array($value)) return array_map([self::class,'escape'],$value);
        return is_string($value)?htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'):$value;
    }
    public static function type(array $web): string {
        return ['vhostsubdomain'=>'subdomain','vhostalias'=>'aliasdomain'][$web['type']]??'domain';
    }
    public static function render(string $section,?array $web,array $webs,array $vars): void {
        global $app;
        $app->uses('tpl,tform');
        $tabs=[];
        if($web) {
            $_SESSION['s']['var']['vhostdomain_type']=self::type($web);
            $app->tform->loadFormDef('form/web_vhost_domain.tform.php');
            foreach($app->tform->formDef['tabs'] as $name=>$tab) {
                if($name==='modsecurity') continue;
                $tabs[]=['title'=>$app->tform->lng($tab['title']),'url'=>'sites/web_vhost_domain_edit.php?id='.(int)$web['domain_id'].'&type='.self::type($web).'&next_tab='.$name];
            }
        }
        $id=(int)($web['domain_id']??0);
        $vars+=['domain_id'=>$id,'has_web'=>(bool)$web,'domain'=>$web['domain']??'','site_tabs'=>$tabs,'settings_active'=>$section==='settings','diagnostics_active'=>$section==='diagnostics','settings_url'=>'sites/modsecurity.php?domain_id='.$id,'diagnostics_url'=>'sites/modsecurity_diagnostics.php?domain_id='.$id];
        if(!$web) {
            $vars['website_list']=array_map(fn($w)=>['domain_id'=>(int)$w['domain_id'],'domain'=>$w['domain'],'url'=>'sites/'.($section==='settings'?'modsecurity.php':'modsecurity_diagnostics.php').'?domain_id='.(int)$w['domain_id']],$webs);
        }
        $vars=self::escape($vars+I18n::templateVars());
        // New templates belong to the extension; the ISPConfig theme supplies all styling.
        $app->tpl->newTemplate('templates/modsecurity_shell.htm');
        $app->tpl->setInclude('ms_content','templates/modsecurity_'.$section.'.htm');
        foreach($vars as $key=>$value) {
            if(is_array($value)) $app->tpl->setLoop($key,$value);
            else $app->tpl->setVar($key,$value);
        }
        $app->tpl->pparse();
    }
}
