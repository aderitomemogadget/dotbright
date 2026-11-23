<?php
// No bootstrap or mutations on include. Product writes require explicit BO actions.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__) { http_response_code(404); exit; }
require_once __DIR__.'/product_memory_audit.php';

function db_memory_manage_db() {
    return new PDO('mysql:host='.$GLOBALS['DB_host'].';dbname='.$GLOBALS['DB_name'].';charset=utf8mb4', $GLOBALS['DB_user'],$GLOBALS['DB_pass'],array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false));
}
function db_memory_manage_allowed($adminId,$permissions) {
    return (int)$adminId>0 && (int)($permissions[8] ?? 0)===1;
}
function db_memory_manage_ready(PDO $db) {
    foreach(array('products','products_lang','product_memory_proposals','product_memory_events') as $table){
        $q=$db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute(array($table));
        $engine=$q->fetchColumn();
        if($engine===false)throw new RuntimeException('Tabela em falta: '.$table.'. Execute a migracao SQL do modulo.');
        if(in_array($table,array('product_memory_proposals','product_memory_events'),true) && strtoupper((string)$engine)!=='INNODB')throw new RuntimeException('A tabela de propostas/historico '.$table.' necessita de InnoDB.');
    }
    $db->query('SELECT ram_gb,storage_gb FROM products LIMIT 0');
    $db->query('SELECT ID_PAI,Lang,Cabecalho,Texto1 FROM products_lang LIMIT 0');
}
function db_memory_manage_write_blockers(PDO $db) {
    $blockers=array();
    foreach(array('products','products_lang') as $table){
        $q=$db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute(array($table));
        $engine=$q->fetchColumn();
        if(strtoupper((string)$engine)!=='INNODB')$blockers[]=$table.' ('.($engine ?: 'indisponivel').')';
    }
    return $blockers;
}
function db_memory_manage_json($value) {
    return json_encode($value,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
function db_memory_manage_fields(array $product) {
    $normalise=function($value){return $value===null || $value===''?null:number_format((float)$value,2,'.','');};
    return array('name'=>$product['name'] ?? null,'ram_gb'=>$normalise($product['ram_gb'] ?? null),'storage_gb'=>$normalise($product['storage_gb'] ?? null));
}
function db_memory_manage_hash(array $product) {
    return hash('sha256',db_memory_manage_json(array('fields'=>db_memory_manage_fields($product),'description'=>$product['description'] ?? null,'ref'=>$product['Ref'],'pt_rows'=>$product['_pt_rows'])));
}
function db_memory_manage_product(PDO $db,$id,$lock=false) {
    $q=$db->prepare('SELECT ID,Ref,Aproved,ram_gb,storage_gb FROM products WHERE ID=?'.($lock?' FOR UPDATE':''));$q->execute(array((int)$id));$product=$q->fetch(PDO::FETCH_ASSOC);
    if(!$product)throw new RuntimeException('Produto nao encontrado.');
    $q=$db->prepare("SELECT Cabecalho,Texto1 FROM products_lang WHERE ID_PAI=? AND Lang='pt'".($lock?' FOR UPDATE':''));$q->execute(array((int)$id));$texts=$q->fetchAll(PDO::FETCH_ASSOC);
    $product['_pt_rows']=count($texts);$product['name']=$texts[0]['Cabecalho'] ?? null;$product['description']=$texts[0]['Texto1'] ?? null;
    return $product;
}
function db_memory_manage_event(PDO $db,array $proposal,$action,$admin,$before,$after,$note='') {
    $q=$db->prepare('INSERT INTO product_memory_events(proposal_id,product_id,action,admin_id,before_json,after_json,note,created_at) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP())');
    $q->execute(array($proposal['id'],$proposal['product_id'],$action,$admin ?: null,$before===null?null:db_memory_manage_json($before),$after===null?null:db_memory_manage_json($after),$note));
}
function db_memory_manage_queue(PDO $db,$id,$admin=0) {
    $db->beginTransaction();
    try{
        $product=db_memory_manage_product($db,$id,true);$hash=db_memory_manage_hash($product);$analysis=db_memory_analyse($product);
        if($product['_pt_rows']!==1){$analysis['status']='rever';$analysis['proposed']=null;$analysis['issues'][]='Necessario exatamente um registo de nome/descricao em portugues.';}
        $q=$db->prepare("SELECT * FROM product_memory_proposals WHERE product_id=? AND status IN ('pending','review') AND source_hash<>? FOR UPDATE");$q->execute(array($id,$hash));
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $old){
            $db->prepare("UPDATE product_memory_proposals SET status='stale',version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?")->execute(array($old['id']));
            db_memory_manage_event($db,$old,'stale',$admin,null,null,'Produto alterado desde a analise anterior.');
        }
        $q=$db->prepare('SELECT * FROM product_memory_proposals WHERE product_id=? AND source_hash=?');$q->execute(array($id,$hash));$existing=$q->fetch(PDO::FETCH_ASSOC);
        if(($existing && !in_array($existing['status'],array('stale','applied'),true)) || !in_array($analysis['status'],array('proposta','rever'),true)){$db->commit();return false;}
        $proposal=$analysis['proposed'];
        $values=array(db_memory_manage_json(db_memory_manage_fields($product)),db_memory_manage_json($analysis),$proposal['name'] ?? null,$proposal['ram_gb'] ?? null,$proposal['storage_gb'] ?? null,$proposal?'pending':'review');
        if($existing){
            $q=$db->prepare('UPDATE product_memory_proposals SET snapshot_json=?,analysis_json=?,proposed_name=?,ram_gb=?,storage_gb=?,status=?,version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?');
            $q->execute(array_merge($values,array($existing['id'])));$proposalId=$existing['id'];
        }else{
            $q=$db->prepare('INSERT INTO product_memory_proposals(product_id,source_hash,snapshot_json,analysis_json,proposed_name,ram_gb,storage_gb,status,version,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
            $q->execute(array_merge(array($id,$hash),$values));$proposalId=$db->lastInsertId();
        }
        db_memory_manage_event($db,array('id'=>$proposalId,'product_id'=>$id),$existing?'reopened':'detected',$admin,null,$proposal,$existing?'Produto voltou a necessitar desta correcao; proposta reaberta.':'Proposta criada; produto inalterado.');
        $db->commit();return true;
    }catch(Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
}
function db_memory_manage_scan(PDO $db,$cursor=0,$until=0,$admin=0) {
    $cursor=max(0,(int)$cursor);$until=max(0,(int)$until);
    if(!$until)$until=(int)$db->query('SELECT COALESCE(MAX(ID),0) FROM products')->fetchColumn();
    $q=$db->prepare('SELECT ID FROM products WHERE ID>? AND ID<=? ORDER BY ID LIMIT 100');$q->execute(array($cursor,$until));$ids=$q->fetchAll(PDO::FETCH_COLUMN);
    $result=array('cursor'=>$cursor,'until'=>$until,'examined'=>0,'queued'=>0,'errors'=>array());
    $result['total']=null;
    if($cursor===0){$q=$db->prepare('SELECT COUNT(*) FROM products WHERE ID<=?');$q->execute(array($until));$result['total']=(int)$q->fetchColumn();}
    foreach($ids as $id){
        try{if(db_memory_manage_queue($db,(int)$id,$admin))$result['queued']++;}
        catch(Throwable $error){$result['errors'][]='Produto '.(int)$id.': '.($error instanceof PDOException?'Falha na base de dados.':$error->getMessage());}
        $result['cursor']=(int)$id;$result['examined']++;
    }
    $result['done']=count($ids)<100 || $result['cursor']>=$until;
    return $result;
}
function db_memory_manage_validate(PDO $db,array $input) {
    $name=$input['name'] ?? null;
    if(!is_string($name) || !mb_check_encoding($name,'UTF-8') || trim($name)==='' || strip_tags($name)!==$name || preg_match('/[\x00-\x1f\x7f]/u',$name))throw new InvalidArgumentException('Nome invalido.');
    $limit=$db->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products_lang' AND COLUMN_NAME='Cabecalho'")->fetchColumn();
    if(mb_strlen(trim($name),'UTF-8')>min(1000,(int)($limit ?: 1000)))throw new InvalidArgumentException('O nome ultrapassa o tamanho permitido.');
    $values=array('name'=>trim($name));
    foreach(array('ram_gb'=>999999.99,'storage_gb'=>99999999.99) as $key=>$maximum){
        $value=$input[$key] ?? '';
        if($key==='ram_gb' && ($value===null || $value==='')){$values[$key]=null;continue;}
        if(!is_scalar($value) || !preg_match('/^\d+(?:[.,]\d{1,2})?$/',(string)$value))throw new InvalidArgumentException('Indique '.$key.' em GB, com ate duas casas decimais.');
        $value=(float)str_replace(',','.',(string)$value);
        if($value<=0 || $value>$maximum)throw new InvalidArgumentException('Valor fora do intervalo: '.$key.'.');
        $values[$key]=number_format($value,2,'.','');
    }
    return $values;
}
function db_memory_manage_invalidate($id) {
    $root=dirname(__DIR__);$path=$root.'/files/cache/products/p'.(int)$id.'_pt.bin';
    if(is_file($path) && !@unlink($path))error_log('Memory management: could not invalidate product cache '.(int)$id);
    if(function_exists('apcu_delete')){
        foreach(array_unique(array($_SERVER['HTTP_HOST'] ?? '', 'www.dotbright.pt','dotbright.pt')) as $host){
            $identity=$host.'|'.($GLOBALS['DB_name'] ?? '').'|pt|'.(int)$id;
            apcu_delete('dotbright:discovery:v1:card:'.hash('sha256',$identity));
        }
    }
}
function db_memory_manage_action(PDO $db,$id,$version,$action,array $input,$admin) {
    if((int)$admin<=0)throw new InvalidArgumentException('Administrador invalido.');
    if(!in_array($action,array('save','reject','apply','revert'),true))throw new InvalidArgumentException('Acao invalida.');
    if(in_array($action,array('apply','revert'),true)){
        $blockers=db_memory_manage_write_blockers($db);
        if($blockers)throw new RuntimeException('Aplicacao e reversao bloqueadas: '.implode(', ',$blockers).'. Estas tabelas necessitam de InnoDB para garantir a reversao em caso de falha. A analise e revisao de propostas continuam disponiveis.');
    }
    $note=$input['note'] ?? '';
    if(!is_string($note) || !mb_check_encoding($note,'UTF-8') || mb_strlen($note)>2000)throw new InvalidArgumentException('Nota invalida (maximo 2000 caracteres).');
    $changedProduct=false;$db->beginTransaction();
    try{
        // Read the immutable product ID, then lock in the same order as the scanner.
        $q=$db->prepare('SELECT product_id FROM product_memory_proposals WHERE id=?');$q->execute(array($id));$productId=$q->fetchColumn();
        if(!$productId)throw new InvalidArgumentException('Proposta nao encontrada.');
        $product=db_memory_manage_product($db,(int)$productId,true);
        $q=$db->prepare('SELECT * FROM product_memory_proposals WHERE id=? FOR UPDATE');$q->execute(array($id));$proposal=$q->fetch(PDO::FETCH_ASSOC);
        if((int)$proposal['version']!==(int)$version)throw new RuntimeException('A proposta mudou entretanto. Recarregue a pagina.');
        $before=null;$after=null;$status=$proposal['status'];
        if($action==='revert'){
            if($status!=='applied')throw new RuntimeException('Apenas uma correcao aplicada pode ser revertida.');
            $q=$db->prepare("SELECT before_json,after_json FROM product_memory_events WHERE proposal_id=? AND action='apply' ORDER BY id DESC LIMIT 1");$q->execute(array($id));$event=$q->fetch(PDO::FETCH_ASSOC);
            if(!$event || db_memory_manage_fields($product)!==json_decode($event['after_json'],true,512,JSON_THROW_ON_ERROR))throw new RuntimeException('Produto alterado depois da aplicacao. A reversao foi bloqueada.');
            $before=db_memory_manage_fields($product);$after=json_decode($event['before_json'],true,512,JSON_THROW_ON_ERROR);$status='reverted';$changedProduct=true;
        }else{
            if(!in_array($status,array('pending','review'),true))throw new RuntimeException('A proposta ja foi tratada.');
            if($action==='reject')$status='rejected';
            else{
                if(!hash_equals($proposal['source_hash'],db_memory_manage_hash($product)))throw new RuntimeException('Nome, descricao ou memorias alterados desde a analise. Execute nova analise antes de continuar.');
                if($product['_pt_rows']!==1)throw new RuntimeException('O produto necessita de um unico registo PT antes da correcao.');
                if($action==='save'){
                    $after=db_memory_manage_validate($db,$input);$before=array('name'=>$proposal['proposed_name'],'ram_gb'=>$proposal['ram_gb'],'storage_gb'=>$proposal['storage_gb']);$status='pending';
                    $db->prepare('UPDATE product_memory_proposals SET proposed_name=?,ram_gb=?,storage_gb=? WHERE id=?')->execute(array($after['name'],$after['ram_gb'],$after['storage_gb'],$id));
                }else{
                    if($status!=='pending')throw new RuntimeException('Edite e valide primeiro esta proposta para revisao.');
                    $after=db_memory_manage_validate($db,array('name'=>$proposal['proposed_name'],'ram_gb'=>$proposal['ram_gb'],'storage_gb'=>$proposal['storage_gb']));
                    $before=db_memory_manage_fields($product);$status='applied';$changedProduct=true;
                }
            }
        }
        if($changedProduct){
            if($product['_pt_rows']!==1)throw new RuntimeException('Numero de registos PT invalido; nenhuma alteracao efetuada.');
            $db->prepare('UPDATE products SET ram_gb=?,storage_gb=? WHERE ID=?')->execute(array($after['ram_gb'],$after['storage_gb'],$productId));
            $db->prepare("UPDATE products_lang SET Cabecalho=? WHERE ID_PAI=? AND Lang='pt'")->execute(array($after['name'],$productId));
            // Pending proposals from other source revisions must not survive an apply/revert.
            $q=$db->prepare("SELECT * FROM product_memory_proposals WHERE product_id=? AND id<>? AND status IN ('pending','review') FOR UPDATE");$q->execute(array($productId,$id));
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $other){
                $db->prepare("UPDATE product_memory_proposals SET status='stale',version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?")->execute(array($other['id']));
                db_memory_manage_event($db,$other,'stale',$admin,null,null,'Outra correcao deste produto foi aplicada ou revertida.');
            }
        }
        $db->prepare('UPDATE product_memory_proposals SET status=?,version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute(array($status,$id));
        db_memory_manage_event($db,$proposal,$action,$admin,$before,$after,$note);
        $db->commit();
        if($changedProduct)db_memory_manage_invalidate($productId);
        return $status;
    }catch(Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
}
function db_memory_storage_for_product($id) {
    static $db=null,$cache=array(),$unavailable=false;
    $id=(int)$id;if($id<=0 || $unavailable)return null;
    if(array_key_exists($id,$cache))return $cache[$id];
    try{
        if(!$db)$db=db_memory_manage_db();
        $q=$db->prepare('SELECT storage_gb FROM products WHERE ID=?');$q->execute(array($id));$storage=$q->fetchColumn();
        return $cache[$id]=$storage!==false && (float)$storage>0?db_memory_label($storage):null;
    }catch(Throwable $error){$unavailable=true;return null;}
}
