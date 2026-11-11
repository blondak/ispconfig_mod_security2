<?php
// Shared HTTP test helpers for the isolated lab only.
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli('db','root','lab-db-root','ispc_master');
$count=0;
function expect($value,$label) { global $count; if(!$value) throw new RuntimeException($label); echo 'PASS '.(++$count).' '.$label."\n"; }
final class Browser {
    private $curl; private $jar;
    public function __construct() {
        $this->jar=tempnam('/tmp','lab-cookie-');$this->curl=curl_init();
        curl_setopt_array($this->curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$this->jar,CURLOPT_COOKIEFILE=>$this->jar,CURLOPT_CONNECT_TO=>['127.0.0.1:80:server1:80'],CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['X-Requested-With: XMLHttpRequest']]);
    }
    public function request(string $path,?array $data=null): array {
        curl_setopt($this->curl,CURLOPT_URL,'http://127.0.0.1'.$path);
        curl_setopt($this->curl,CURLOPT_POST,$data!==null);
        if($data!==null) curl_setopt($this->curl,CURLOPT_POSTFIELDS,http_build_query($data));
        $body=curl_exec($this->curl);
        if($body===false) throw new RuntimeException(curl_error($this->curl));
        return [curl_getinfo($this->curl,CURLINFO_RESPONSE_CODE),$body];
    }
    public function login(string $name,string $pass): void {
        $this->request('/login/');
        [$code,$body]=$this->request('/login/index.php',['username'=>$name,'password'=>$pass,'s_mod'=>'login','s_pg'=>'index']);
        expect(str_contains($body,'LOGIN_REDIRECT:') || $code===302,'real ISPConfig login '.$name);
    }
    public function __destruct() { curl_close($this->curl);@unlink($this->jar); }
}
function token(string $html): array {
    $result=[];
    foreach(['_csrf_id','_csrf_key'] as $key) {
        if(!preg_match('/name="'.preg_quote($key,'/').'" value="([^"]*)"/',$html,$m)) throw new RuntimeException('Missing CSRF token');
        $result[$key]=html_entity_decode($m[1],ENT_QUOTES,'UTF-8');
    }
    return $result;
}
function nativeFields(string $html): array {
    $doc=new DOMDocument();libxml_use_internal_errors(true);$doc->loadHTML('<html><body>'.$html.'</body></html>');libxml_clear_errors();
    $fields=[];
    foreach((new DOMXPath($doc))->query('//input[@name and not(@disabled)] | //select[@name and not(@disabled)] | //textarea[@name and not(@disabled)]') as $node) {
        $name=$node->getAttribute('name');$value=$node->getAttribute('value');
        if($node->tagName==='input' && in_array($node->getAttribute('type'),['checkbox','radio'],true) && !$node->hasAttribute('checked')) continue;
        if($node->tagName==='input' && in_array($node->getAttribute('type'),['submit','button','file'],true)) continue;
        if($node->tagName==='textarea') $value=$node->textContent;
        if($node->tagName==='select') {
            $value='';foreach($node->getElementsByTagName('option') as $i=>$option) if($i===0 || $option->hasAttribute('selected')) { $value=$option->getAttribute('value');if($option->hasAttribute('selected'))break; }
        }
        $fields[]=urlencode($name).'='.urlencode($value);
    }
    parse_str(implode('&',$fields),$out);return $out;
}
