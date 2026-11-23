<?php
/** Hourly catalogue memory audit. Analysis only; intentionally has no apply mode. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/product_memory_audit.php';

function db_memory_audit_main(array $arguments) {
    date_default_timezone_set('Europe/Lisbon');
    $siteRoot=dirname(__DIR__,2);
    $options=array('output-dir'=>dirname($siteRoot).'/private/product-memory-audit','fixture'=>null);
    foreach($arguments as $argument){
        if($argument==='--help'){
            echo "Analise de TODOS os produtos, sem alterar a DB.\nUso: php core/crons/audit_product_memory.php [--output-dir=/caminho/privado] [--fixture=/ficheiro.json]\n";
            return 0;
        }
        if(!preg_match('/^--(output-dir|fixture)=(.+)$/',$argument,$match))throw new InvalidArgumentException('Opcao desconhecida: '.$argument.'. Nao existe modo de aplicacao.');
        $options[$match[1]]=$match[2];
    }
    $output=rtrim($options['output-dir'],'/\\');
    if(!is_dir($output) && !mkdir($output,0700,true))throw new RuntimeException('Nao foi possivel criar a pasta do relatorio.');
    $resolved=realpath($output);$site=realpath($siteRoot);
    $normalise=function($value){return strtolower(str_replace('\\','/',rtrim($value,'/\\')));};
    if($normalise($resolved)===$normalise($site) || strpos($normalise($resolved),$normalise($site).'/')===0)throw new RuntimeException('Os relatorios devem ficar fora da pasta publica do site.');
    $lock=fopen($output.'/audit.lock','c');
    if(!$lock)throw new RuntimeException('Nao foi possivel abrir o lock.');
    if(!flock($lock,LOCK_EX | LOCK_NB)){fclose($lock);fwrite(STDERR,"Ja existe uma analise em curso.\n");return 4;}
    $db=null;$html=null;$json=null;
    $run=(new DateTimeImmutable('now',new DateTimeZone('Europe/Lisbon')))->format('Ymd-His').'-'.getmypid();
    $base=$output.'/memory-'.$run;
    try{
        if($options['fixture']!==null){
            $fixture=json_decode(file_get_contents($options['fixture']),true,512,JSON_THROW_ON_ERROR);
            if(!is_array($fixture) || array_values($fixture)!==$fixture)throw new RuntimeException('Fixture deve ser uma lista de produtos.');
            $products=(function() use($fixture){foreach($fixture as $row)yield $row;})();
            $missing=array();$source='fixture';
        }else{
            // Load credentials only, avoiding the storefront/session bootstrap and its side effects.
            $config=$siteRoot.'/conf/conf.php';
            if(!is_file($config))throw new RuntimeException('Configuracao conf/conf.php indisponivel. Executar no servidor ou usar --fixture.');
            require $config;
            $host=$DB_host ?? ($GLOBALS['DB_host'] ?? '');$database=$DB_name ?? ($GLOBALS['DB_name'] ?? '');
            $user=$DB_user ?? ($GLOBALS['DB_user'] ?? '');$password=$DB_pass ?? ($GLOBALS['DB_pass'] ?? '');
            if(!$host || !$database)throw new RuntimeException('Configuracao da base de dados incompleta.');
            $db=new PDO('mysql:host='.$host.';dbname='.$database.';charset=utf8mb4',$user,$password,array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false));
            $db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
            $columns=$db->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_COLUMN);
            $missing=array_values(array_diff(array('ram_gb','storage_gb'),$columns));
            $fields='';foreach(array('ram_gb','storage_gb') as $column)$fields.=in_array($column,$missing,true)?',NULL AS '.$column:',p.'.$column;
            $products=(function() use($db,$fields){
                $last=0;
                do{
                    // Keyset batches: includes inactive/out-of-stock products and products without Portuguese text.
                    $sql="SELECT p.ID,p.Ref,p.Aproved $fields,
                        (SELECT MAX(pl.Cabecalho) FROM products_lang pl WHERE pl.ID_PAI=p.ID AND pl.Lang='pt') AS name,
                        (SELECT MAX(pl.Texto1) FROM products_lang pl WHERE pl.ID_PAI=p.ID AND pl.Lang='pt') AS description
                        FROM products p WHERE p.ID>:last ORDER BY p.ID LIMIT 500";
                    $statement=$db->prepare($sql);$statement->bindValue(':last',$last,PDO::PARAM_INT);$statement->execute();
                    $rows=$statement->fetchAll(PDO::FETCH_ASSOC);$statement->closeCursor();
                    foreach($rows as $row){$last=(int)$row['ID'];yield $row;}
                }while(count($rows)===500);
            })();$source='database';
        }
        $summary=array('mode'=>'analysis_only','source'=>$source,'started_at'=>date(DATE_ATOM),'missing_columns'=>$missing,'examined'=>0,'statuses'=>array('proposta'=>0,'rever'=>0,'sem_dados'=>0,'sem_alteracao'=>0));
        $html=fopen($base.'.html.part','xb');$json=fopen($base.'.jsonl.part','xb');
        if(!$html || !$json)throw new RuntimeException('Nao foi possivel criar os relatorios.');
        db_memory_audit_write($html,'<!doctype html><html lang="pt"><meta charset="utf-8"><title>Analise de memorias</title><style>body{font:14px system-ui;margin:24px;color:#17202a}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ddd;padding:8px;text-align:left;vertical-align:top}th{background:#eee;position:sticky;top:0}tr[data-status="rever"]{background:#fff0d5}tr[data-status="proposta"]{background:#e8f7ed}td{min-width:90px}td:nth-child(4),td:nth-child(5){min-width:260px}</style><h1>Analise de RAM e armazenamento</h1><p>Apenas simulacao. Nenhum produto foi alterado. Valores em GB; 1 TB = 1024 GB. A capacidade atual reproduz a regra da ficha. RAM detetada significa RAM fisica.</p><p>Origem: '.$source.'. Execucao: '.$run.'</p><label>Estado: <select id="status"><option value="">Todos</option><option>proposta</option><option>rever</option><option>sem_dados</option><option>sem_alteracao</option></select></label><table><thead><tr>');
        foreach(array('ID','Referencia','Estado','Nome atual (PT)','Nome proposto','RAM atual','RAM detetada','Armazenamento atual','Armazenamento detetado','Capacidade atual na ficha','Capacidade proposta','Motivos / revisao','Evidencias') as $heading)db_memory_audit_write($html,'<th>'.$heading.'</th>');
        db_memory_audit_write($html,'</tr></thead><tbody>');
        foreach($products as $product){
            $row=db_memory_analyse($product);$summary['examined']++;$summary['statuses'][$row['status']]++;
            db_memory_audit_write($json,json_encode($row,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)."\n");
            db_memory_audit_write($html,db_memory_html_row($row));
        }
        if($db)$db->rollBack();
        $summary['finished_at']=date(DATE_ATOM);$summary['reports']=array('html'=>$base.'.html','jsonl'=>$base.'.jsonl');
        $encoded=json_encode($summary,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        db_memory_audit_write($html,'</tbody></table><h2>Resumo</h2><pre>'.htmlspecialchars($encoded,ENT_QUOTES,'UTF-8').'</pre><script>document.getElementById("status").addEventListener("change",function(){document.querySelectorAll("tbody tr").forEach(row=>row.hidden=this.value!==""&&row.dataset.status!==this.value);});</script></html>');
        fclose($html);$html=null;fclose($json);$json=null;
        foreach(array('html','jsonl') as $extension)if(!rename($base.'.'.$extension.'.part',$base.'.'.$extension))throw new RuntimeException('Nao foi possivel finalizar o relatorio.');
        if(file_put_contents($base.'.summary.json',$encoded."\n",LOCK_EX)===false)throw new RuntimeException('Nao foi possivel guardar o resumo.');
        echo $encoded."\n";return 0;
    }finally{
        if($db && $db->inTransaction())$db->rollBack();
        if(is_resource($html))fclose($html);if(is_resource($json))fclose($json);
        flock($lock,LOCK_UN);fclose($lock);
    }
}
function db_memory_audit_write($handle,$text) {
    $length=strlen($text);$offset=0;
    while($offset<$length){$written=fwrite($handle,substr($text,$offset));if($written===false || $written===0)throw new RuntimeException('Falha ao escrever relatorio.');$offset+=$written;}
}
try{exit(db_memory_audit_main(array_slice($argv,1)));}
catch(Throwable $error){fwrite(STDERR,'Analise falhou (nenhum produto alterado). '.($error instanceof PDOException?'Falha na leitura da base de dados; verificar configuracao e permissoes.':$error->getMessage())."\n");exit(1);}
