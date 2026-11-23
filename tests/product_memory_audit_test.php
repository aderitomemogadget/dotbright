<?php
require_once dirname(__DIR__).'/core/product_memory_audit.php';
function memory_check($condition,$message) { if(!$condition)throw new RuntimeException($message); }
$fixtures=json_decode(file_get_contents(__DIR__.'/fixtures/product_memory.json'),true,512,JSON_THROW_ON_ERROR);
$results=array();foreach($fixtures as $fixture)$results[$fixture['ID']]=db_memory_analyse($fixture);
memory_check($results[143644]['detected']===array('ram_gb'=>12.0,'storage_gb'=>512.0),'Samsung RAM/storage extraction');
memory_check(strpos($results[143644]['proposed']['name'],'12GB RAM/512GB')!==false,'Samsung name labels RAM');
memory_check($results[143644]['current']['capacity_display']==='12GB' && $results[143644]['proposed']['capacity_display']==='512GB','Before/after capacity');
memory_check($results[143033]['status']==='rever' && $results[143033]['proposed']===null,'Contradictory virtual RAM must not produce proposal');
memory_check($results[3]['status']==='sem_alteracao','Explicit adjacent labels must not overlap');
memory_check($results[4]['status']==='rever','Title/description conflict');
memory_check($results[5]['detected']['ram_gb']===8.0 && strpos($results[5]['proposed']['name'],'8GB RAM /256GB')!==false,'Compact pair');
memory_check($results[6]['proposed']===null,'Accessory must not inherit device capacity');
memory_check($results[7]['detected']['storage_gb']===1024.0,'TB conversion');
memory_check($results[8]['status']==='sem_dados','No memory data');
memory_check($results[9]['status']==='rever','Unlabelled GB cannot be assumed to be storage');
memory_check(strpos(db_memory_html_row($results[10]),'<img')===false && strpos(db_memory_html_row($results[10]),'&amp; teste')!==false,'HTML escaping');
foreach(array(
    array('name'=>'Galaxy 12GB/512GB','description'=>'RAM: 8GB'),
    array('name'=>'Galaxy 12GB/512GB','description'=>'','ram_gb'=>8),
    array('name'=>'Galaxy 8GB RAM / 128GB ROM','description'=>'Memória virtual: +4GB'),
    array('name'=>'Galaxy 8GB/128GB/256GB','description'=>''),
    array('name'=>'Galaxy 128GB/8GB','description'=>''),
    array('name'=>'Galaxy 8GB RAM','description'=>''),
    array('name'=>'','description'=>'RAM: 8GB Armazenamento: 256GB')
) as $case){$case['ID']=20;memory_check(db_memory_analyse($case)['status']==='rever','Ambiguity must be review-only: '.$case['name']);}
$preserved=db_memory_analyse(array('ID'=>30,'name'=>'SSD 512GB','description'=>'','ram_gb'=>16,'storage_gb'=>null));
memory_check($preserved['proposed']['ram_gb']===16,'Never propose clearing existing physical RAM');
$safe=db_memory_analyse(array('ID'=>31,'name'=>'Galaxy 8gb/256gb','description'=>"<table><tr><td>RAM</td><td>8 GB</td></tr><tr><td>ROM</td><td>256 GB</td></tr></table>"));
memory_check($safe['detected']['storage_gb']===256.0,'Lowercase units');
$table=db_memory_analyse(array('ID'=>32,'name'=>'Dispositivo de teste','description'=>"<table><tr><td>RAM</td><td>8 GB</td></tr><tr><td>ROM</td><td>256 GB</td></tr></table>"));
memory_check($table['detected']===array('ram_gb'=>8.0,'storage_gb'=>256.0),'Separate HTML table cells');
echo "PASS: memory extraction, proposals, conflicts, virtual RAM, accessories, TB, current fields and HTML escaping.\n";
