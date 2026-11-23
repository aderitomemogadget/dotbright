<?php
if(realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__){http_response_code(404);exit;}
$page_titulo='RAM e armazenamento';
$mm_core=dirname(__DIR__,2).'/core/';
$mm_boot_stage='Verificacao dos ficheiros do modulo';
$mm_missing_files=array();
foreach(array('core_files.php','admin_globals.php','product_memory_management.php','product_memory_audit.php') as $file){
    if(!is_file($mm_core.$file) || !is_readable($mm_core.$file))$mm_missing_files[]='core/'.$file;
}
foreach(array('view.inc.php','../layout/header.inc.php','../layout/body.inc.php','../layout/bottom.inc.php') as $file){
    if(!is_file(__DIR__.'/'.$file) || !is_readable(__DIR__.'/'.$file))$mm_missing_files[]='admin/product_memory/'.$file;
}
if($mm_missing_files)throw new RuntimeException('Ficheiros em falta ou sem permissao de leitura.');
// Load this module's pure helpers before the legacy storefront bootstrap.
$mm_boot_stage='Carregamento de core/product_memory_management.php';
require_once $mm_core.'product_memory_management.php';
$mm_missing_functions=array();
foreach(array('db_memory_manage_allowed','db_memory_manage_db','db_memory_manage_ready','db_memory_manage_scan','db_memory_manage_action','db_memory_analyse') as $function){
    if(!function_exists($function))$mm_missing_functions[]=$function;
}
if($mm_missing_functions)throw new RuntimeException('Ficheiros do modulo incompletos ou de versoes diferentes.');
$mm_boot_stage='Carregamento do BO (core/core_files.php)';
$path=$mm_core;
require_once $mm_core.'core_files.php';
$mm_boot_stage='Validacao da sessao do BO (core/admin_globals.php)';
require_once $mm_core.'admin_globals.php';
$mm_boot_stage='Leitura das permissoes do administrador';
$dados=getDadosAdministrador($admin_id ?? 0);
if(!db_memory_manage_allowed($admin_id ?? 0,$dados['Permissoes'] ?? array())){http_response_code(403);exit('Sem permissoes.');}
$mm_boot_stage='Arranque concluido';
