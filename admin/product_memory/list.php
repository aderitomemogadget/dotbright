<?php
// Keep legacy request-to-global handling away from identity and mutation inputs.
$mm_query=array_intersect_key($_GET,array_flip(array('id','p','q','status')));
$mm_input=array_intersect_key($_POST,array_flip(array('_csrf','action','id','version','name','ram_gb','storage_gb','note','ids','versions','cursor','until')));
$_GET=array();$_POST=array();$_REQUEST=array();$_FILES=array();
$_COOKIE=array_intersect_key($_COOKIE,array_flip(array(session_name())));
$mm_boot_buffer=ob_get_level();ob_start();
try{
    require __DIR__.'/settings.inc.php';
    // Legacy includes must not prepend HTML/warnings to JSON or prevent redirects.
    while(ob_get_level()>$mm_boot_buffer)ob_end_clean();
}catch(Throwable $error){
    while(ob_get_level()>$mm_boot_buffer)ob_end_clean();
    $reference=substr(hash('sha256',microtime(true).'|'.getmypid()),0,12);
    $stage=$mm_boot_stage ?? 'Carregamento de admin/product_memory/settings.inc.php';
    error_log('Product memory BO bootstrap: ['.$reference.'] '.$stage.' | '.get_class($error).' | '.$error->getMessage().' | '.$error->getFile().':'.$error->getLine());
    http_response_code(500);header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store, private');
    $escape=function($value){return htmlspecialchars((string)$value,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');};
    echo '<!doctype html><html lang="pt"><meta charset="utf-8"><title>RAM e armazenamento</title><style>body{font:16px system-ui;max-width:950px;margin:40px auto;padding:20px;color:#243447}code,pre{background:#f3f4f5;padding:8px;white-space:pre-wrap}li{margin:10px 0}</style><h1>RAM e armazenamento</h1><p>Nao foi possivel iniciar esta area do BO.</p><p><strong>Etapa:</strong> '.$escape($stage).'</p>';
    if(!empty($mm_missing_files)){
        echo '<h2>Ficheiros em falta ou sem permissao de leitura</h2><ul>';
        foreach($mm_missing_files as $missing)echo '<li><code>'.$escape($missing).'</code></li>';
        echo '</ul><p>Envie estes ficheiros por FTP para as pastas indicadas e confirme as permissoes de leitura.</p>';
    }elseif(!empty($mm_missing_functions)){
        echo '<h2>Ficheiros do modulo de versoes diferentes</h2><p>Atualize core/product_memory_management.php e core/product_memory_audit.php em conjunto.</p><p>Funcoes em falta:</p><ul>';
        foreach($mm_missing_functions as $missing)echo '<li><code>'.$escape($missing).'</code></li>';
        echo '</ul>';
    }else{
        $root=str_replace('\\','/',dirname(__DIR__,2)).'/';$file=str_replace('\\','/',$error->getFile());
        $file=strpos($file,$root)===0?substr($file,strlen($root)):basename($file);
        echo '<p><strong>Tipo:</strong> '.$escape(get_class($error)).' · <strong>Ficheiro:</strong> <code>'.$escape($file).':'.(int)$error->getLine().'</code></p>';
        if($error instanceof Error && preg_match('/^Call to undefined function ([a-zA-Z0-9_\\\\]+)\(\)$/',$error->getMessage(),$missingFunction))echo '<p>Funcao em falta: <code>'.$escape($missingFunction[1]).'</code>. Confirme que os ficheiros enviados pertencem a mesma versao.</p>';
        elseif($error instanceof ParseError)echo '<p>O ficheiro indicado tem um erro de sintaxe ou usa uma instrucao nao suportada pela versao PHP do alojamento.</p>';
        else echo '<p>Consulte a entrada do log PHP com a referencia abaixo para identificar a causa. Credenciais e consultas SQL nao sao apresentadas nesta pagina.</p>';
    }
    echo '<p><strong>Referencia no log PHP:</strong> <code>'.$escape($reference).'</code></p><p>Esta tentativa nao aplicou correcoes aos produtos.</p><p><a href="list.php">Tentar novamente</a> · <a href="../index.php">Voltar ao BO</a></p></html>';
    exit;
}
$_GET=$mm_query;$_POST=$mm_input;
require_once __DIR__.'/../../core/product_memory_management.php';
if(!db_memory_manage_allowed($admin_id ?? 0,$dados['Permissoes'] ?? array())){http_response_code(403);exit('Sem permissoes.');}
header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
function mm_escape($value){return htmlspecialchars((string)$value,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');}
function mm_text($input,$key,$default=''){return is_string($input[$key] ?? null)?$input[$key]:$default;}
function mm_number($input,$key){return is_scalar($input[$key] ?? null)?max(0,(int)$input[$key]):0;}
if(empty($_SESSION['product_memory_csrf']))$_SESSION['product_memory_csrf']=bin2hex(random_bytes(32));
$mm_csrf=$_SESSION['product_memory_csrf'];
$mm_labels=array('pending'=>'Por aprovar','review'=>'Para revisao','applied'=>'Aplicada','rejected'=>'Rejeitada','reverted'=>'Revertida','stale'=>'Desatualizada');
$mm_error='';$mm_ready=false;$mm_can_apply=false;$mm_write_blockers=array();$mm_detail=null;$mm_live=null;$mm_events=array();$mm_rows=array();$mm_total=0;$mm_counts=array();
$mm_notices=$_SESSION['product_memory_flash'] ?? array();unset($_SESSION['product_memory_flash']);
$mm_id=mm_number($_GET,'id');$mm_page=max(1,min(100000,mm_number($_GET,'p')));
$mm_status=mm_text($_GET,'status','pending');$mm_search=mb_substr(trim(mm_text($_GET,'q')),0,150);
$mm_action=mm_text($_POST,'action');$mm_scan=$_SERVER['REQUEST_METHOD']==='POST' && $mm_action==='scan';
$mm_scan_state=$_SESSION['product_memory_scan'] ?? null;
try{
    $mm_db=db_memory_manage_db();db_memory_manage_ready($mm_db);$mm_ready=true;
    $mm_write_blockers=db_memory_manage_write_blockers($mm_db);$mm_can_apply=!$mm_write_blockers;
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!is_string($_POST['_csrf'] ?? null) || !hash_equals($mm_csrf,$_POST['_csrf']))throw new InvalidArgumentException('Sessao expirada. Recarregue a pagina.');
        if($mm_scan || $mm_action==='scan_browser'){
            $cursor=mm_number($_POST,'cursor');$until=mm_number($_POST,'until');
            $result=db_memory_manage_scan($mm_db,$cursor,$until,(int)$admin_id);
            $previous=is_array($mm_scan_state) && (int)$mm_scan_state['cursor']===$cursor && (int)$mm_scan_state['until']===$until?$mm_scan_state:array('examined'=>0,'queued'=>0);
            $mm_scan_state=array('cursor'=>$result['cursor'],'until'=>$result['until'],'examined'=>$previous['examined']+$result['examined'],'queued'=>$previous['queued']+$result['queued'],'total'=>$result['total'] ?? ($previous['total'] ?? 0),'done'=>$result['done'],
                'failed'=>($previous['failed'] ?? 0)+count($result['errors']),'errors'=>array_slice(array_merge($previous['errors'] ?? array(),$result['errors']),0,20));
            $_SESSION['product_memory_scan']=$mm_scan_state;
            $result['progress']=$mm_scan_state;
            if($result['done'] || $result['errors']){
                $message=($result['done']?'Analise concluida':'Analise em curso').': '.$mm_scan_state['examined'].' produtos analisados, '.$mm_scan_state['queued'].' propostas novas.';
                if($mm_scan_state['failed'])$message.=' Produtos com erro: '.$mm_scan_state['failed'].'. Os restantes foram processados; detalhes dos primeiros 20 erros abaixo.';
                $_SESSION['product_memory_flash']=array_merge(array($message),$mm_scan_state['errors']);
            }
            if($mm_scan){header('Content-Type: application/json; charset=utf-8');echo db_memory_manage_json($result);exit;}
            header('Location: list.php?status=',true,303);exit;
        }
        $mm_id=mm_number($_POST,'id');$messages=array();
        if(in_array($mm_action,array('bulk_apply','bulk_reject'),true)){
            $ids=$_POST['ids'] ?? array();$versions=$_POST['versions'] ?? array();
            if(!is_array($ids) || !is_array($versions) || !$ids || count($ids)>20)throw new InvalidArgumentException('Selecione entre 1 e 20 propostas.');
            $ids=array_unique(array_map(function($id){if(!is_scalar($id) || (int)$id<=0)throw new InvalidArgumentException('Selecao invalida.');return (int)$id;},$ids));
            foreach($ids as $id){
                try{
                    if(!is_scalar($versions[$id] ?? null))throw new InvalidArgumentException('Versao em falta.');
                    db_memory_manage_action($mm_db,$id,(int)$versions[$id],$mm_action==='bulk_apply'?'apply':'reject',array(),(int)$admin_id);
                    $messages[]='Proposta '.$id.': '.($mm_action==='bulk_apply'?'aplicada.':'rejeitada.');
                }catch(Throwable $error){$messages[]='Proposta '.$id.': '.($error instanceof PDOException?'Operacao nao concluida na base de dados.':$error->getMessage());}
            }
            $mm_id=0;
        }else{
            db_memory_manage_action($mm_db,$mm_id,mm_number($_POST,'version'),$mm_action,$_POST,(int)$admin_id);
            $messages[]=array('save'=>'Proposta guardada. O produto ainda nao foi alterado.','reject'=>'Proposta rejeitada. Produto inalterado.','apply'=>'Correcao aplicada e registada no historico.','revert'=>'Correcao revertida e registada no historico.')[$mm_action];
        }
        $_SESSION['product_memory_flash']=$messages;
        header('Location: list.php'.($mm_id?'?id='.$mm_id:''),true,303);exit;
    }
    if($mm_id){
        $q=$mm_db->prepare('SELECT m.*,p.Ref FROM product_memory_proposals m LEFT JOIN products p ON p.ID=m.product_id WHERE m.id=?');$q->execute(array($mm_id));$mm_detail=$q->fetch(PDO::FETCH_ASSOC);
        if(!$mm_detail)throw new InvalidArgumentException('Proposta nao encontrada.');
        try{$mm_live=db_memory_manage_product($mm_db,(int)$mm_detail['product_id']);}catch(RuntimeException $error){$mm_error=$error->getMessage();}
        $q=$mm_db->prepare('SELECT * FROM product_memory_events WHERE proposal_id=? ORDER BY id DESC LIMIT 100');$q->execute(array($mm_id));$mm_events=$q->fetchAll(PDO::FETCH_ASSOC);
    }else{
        if($mm_status!=='' && !isset($mm_labels[$mm_status]))throw new InvalidArgumentException('Estado invalido.');
        $where=array('1=1');$params=array();
        if($mm_status!==''){$where[]='m.status=?';$params[]=$mm_status;}
        if($mm_search!==''){
            $term='%'.strtr($mm_search,array('!'=>'!!','%'=>'!%','_'=>'!_')).'%';
            $where[]="(p.Ref LIKE ? ESCAPE '!' OR m.proposed_name LIKE ? ESCAPE '!' OR m.snapshot_json LIKE ? ESCAPE '!' OR m.product_id=?)";
            array_push($params,$term,$term,$term,ctype_digit($mm_search)?(int)$mm_search:0);
        }
        $where=implode(' AND ',$where);
        $q=$mm_db->prepare('SELECT COUNT(*) FROM product_memory_proposals m LEFT JOIN products p ON p.ID=m.product_id WHERE '.$where);$q->execute($params);$mm_total=(int)$q->fetchColumn();
        $mm_page=min($mm_page,max(1,(int)ceil($mm_total/20)));
        $q=$mm_db->prepare('SELECT m.*,p.Ref FROM product_memory_proposals m LEFT JOIN products p ON p.ID=m.product_id WHERE '.$where.' ORDER BY m.updated_at DESC,m.id DESC LIMIT 20 OFFSET '.(($mm_page-1)*20));$q->execute($params);$mm_rows=$q->fetchAll(PDO::FETCH_ASSOC);
        $mm_counts=$mm_db->query('SELECT status,COUNT(*) AS total FROM product_memory_proposals GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}catch(Throwable $error){
    $mm_error=$error instanceof PDOException?'Nao foi possivel aceder ao modulo. Verifique a migracao SQL e a configuracao da DB.':$error->getMessage();
    if($mm_scan){http_response_code(400);header('Content-Type: application/json; charset=utf-8');echo db_memory_manage_json(array('error'=>$mm_error));exit;}
}
$page_titulo='RAM e armazenamento';
include __DIR__.'/../layout/header.inc.php';include __DIR__.'/../layout/body.inc.php';
include __DIR__.'/view.inc.php';
include __DIR__.'/../layout/bottom.inc.php';
