<?php
// Hourly background discovery: writes proposals/history only, never products.
// Schedule: 0 * * * * php /path/to/site/core/crons/sync_product_memory_proposals.php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/product_memory_management.php';
$lock=fopen(sys_get_temp_dir().'/dotbright-memory-proposals-'.hash('sha256',dirname(__DIR__,2)).'.lock','c');
if(!$lock || !flock($lock,LOCK_EX | LOCK_NB)){fwrite(STDERR,"Ja existe uma analise de propostas em curso.\n");exit(4);}
try{
    $config=dirname(__DIR__,2).'/conf/conf.php';
    if(!is_file($config))throw new RuntimeException('Configuracao conf/conf.php indisponivel.');
    require $config;
    $db=db_memory_manage_db();db_memory_manage_ready($db);
    $cursor=0;$until=0;$summary=array('examined'=>0,'queued'=>0,'errors'=>array(),'products_changed'=>0);
    do{
        $batch=db_memory_manage_scan($db,$cursor,$until);
        $cursor=$batch['cursor'];$until=$batch['until'];
        $summary['examined']+=$batch['examined'];$summary['queued']+=$batch['queued'];
        foreach($batch['errors'] as $error){$summary['errors'][]=$error;fwrite(STDERR,$error."\n");}
    }while(!$batch['done']);
    $summary['finished_at']=(new DateTimeImmutable('now',new DateTimeZone('Europe/Lisbon')))->format(DATE_ATOM);
    echo db_memory_manage_json($summary)."\n";
    $code=$summary['errors']?1:0;
}catch(Throwable $error){fwrite(STDERR,($error instanceof PDOException?'Falha na DB. Verificar migracao SQL e configuracao.':$error->getMessage())."\n");$code=1;}
finally{flock($lock,LOCK_UN);fclose($lock);}
exit($code);
