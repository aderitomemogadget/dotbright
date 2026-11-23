<?php
/** Pure analysis helpers: no database or catalogue writes. */
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

function db_memory_text($value) {
    $value=preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is',' ',(string)$value);
    $value=preg_replace('~</?(?:td|th)\b[^>]*>~i',' ',$value);
    $value=preg_replace('~</?(?:p|div|li|tr|br|h[1-6])\b[^>]*>~i',"\n",$value);
    return trim(str_replace("\xc2\xa0",' ',html_entity_decode(strip_tags($value),ENT_QUOTES | ENT_HTML5,'UTF-8')));
}
function db_memory_gb($number,$unit) {
    return (float)str_replace(',','.',$number)*(strtoupper($unit)==='TB'?1024:1);
}
function db_memory_label($number) {
    return rtrim(rtrim(number_format((float)$number,2,'.',''),'0'),'.').'GB';
}
function db_memory_extract($text,$source) {
    $evidence=array();
    $amount='(\d+(?:[.,]\d+)?)\s*(GB|TB)';
    $labels=array('ram'=>'(?:mem[oó]ria[ \t]+)?RAM', 'storage'=>'(?:armazenamento(?:[ \t]+interno)?|ROM|mem[oó]ria[ \t]+interna|capacidade[ \t]+de[ \t]+armazenamento|SSD|HDD)');
    $claimed=array();
    $directions=$source==='nome'?array('explicit_prefix','suffix','prefix'):array('prefix','suffix');
    foreach($directions as $direction){
        foreach($labels as $type=>$label){
            if($direction==='explicit_prefix')$pattern='~\b'.$label.'\b[ \t]*[:=][ \t]*'.$amount.'\b~iu';
            elseif($direction==='prefix')$pattern='~\b'.$label.'\b[ \t:=-]{0,12}'.$amount.'\b~iu';
            else $pattern='~\b'.$amount.'[ \t:=-]{0,8}(?:de[ \t]+)?'.$label.'\b~iu';
            preg_match_all($pattern,$text,$matches,PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach($matches as $match){
                $start=$match[0][1];$end=$start+strlen($match[0][0]);$overlap=false;
                foreach($claimed as $range)if($start<$range[1] && $end>$range[0])$overlap=true;
                if($overlap)continue;
                $claimed[]=array($start,$end);
                $evidence[]=array('type'=>$type,'gb'=>db_memory_gb($match[1][0],$match[2][0]),'source'=>$source,'text'=>$match[0][0]);
            }
        }
    }
    return $evidence;
}
function db_memory_analyse(array $product) {
    $name=db_memory_text($product['name'] ?? '');
    $description=db_memory_text($product['description'] ?? '');
    $evidence=array_merge(db_memory_extract($name,'nome'),db_memory_extract($description,'descricao'));
    $issues=array();
    $tokens=array();preg_match_all('~\b\d+(?:[.,]\d+)?\s*(?:GB|TB)\b~iu',$name,$tokens);
    $mobile=preg_match('~\b(?:IPHONE|IPAD|GALAXY|MOTO|REDMI|POCO|PIXEL|SMARTPHONE|TABLET|MACBOOK|LAPTOP|PORT[AÁ]TIL)\b~iu',$name);
    $accessory=preg_match('~\b(?:CAPA|BOLSA|PEL[IÍ]CULA|BATERIA|REPARA[ÇC][AÃ]O|DISPLAY|ECR[AÃ]|CARREGADOR|CABO|CART[AÃ]O|MICROSD|PENDRIVE)\b~iu',$name);
    $virtual=preg_match('~\b\d+\s*\+\s*\d+\b|\+\s*\d+\s*GB\b|\b(?:RAM\s+(?:virtual|expandida|estendida)|(?:virtual|expandida|estendida)\s+RAM|mem[oó]ria\s+virtual)\b~iu',$name.' '.$description);
    if($virtual)$issues[]='RAM virtual/expandida ou soma de memorias: validar RAM fisica e total anunciado.';
    // Infer an unlabelled RAM/storage pair only in device titles, never by choosing the largest GB value.
    if($mobile && !$accessory && !$virtual && preg_match('~\b(\d+(?:[.,]\d+)?)\s*(GB|TB)?\s*/\s*(\d+(?:[.,]\d+)?)\s*(GB|TB)\b~iu',$name,$pair)){
        $ram=db_memory_gb($pair[1],$pair[2] ?: 'GB');$storage=db_memory_gb($pair[3],$pair[4]);
        if($ram>0 && $ram<=64 && $storage>=32 && $storage>$ram){
            $evidence[]=array('type'=>'ram','gb'=>$ram,'source'=>'nome_par_convencional','text'=>$pair[0]);
            $evidence[]=array('type'=>'storage','gb'=>$storage,'source'=>'nome_par_convencional','text'=>$pair[0]);
        }else $issues[]='Par de memorias fora da convencao RAM/armazenamento; validar ordem e valores.';
    }
    // A single unlabelled capacity in a phone title is only a suggestion, not sufficient for automatic correction.
    $values=array('ram'=>array(),'storage'=>array());
    foreach($evidence as $item)$values[$item['type']][(string)$item['gb']]=$item['gb'];
    foreach($values as $type=>$items)if(count($items)>1)$issues[]='Valores contraditorios de '.$type.' no nome/descricao.';
    $ram=count($values['ram'])===1?reset($values['ram']):null;
    $storage=count($values['storage'])===1?reset($values['storage']):null;
    if($accessory && $evidence)$issues[]='Titulo pode identificar um acessorio ou servico; confirmar capacidades do proprio produto.';
    foreach(array('ram_gb'=>$ram,'storage_gb'=>$storage) as $field=>$detected){
        $current=$product[$field] ?? null;
        if($current!==null && $current!=='' && $detected!==null && abs((float)$current-$detected)>0.001)$issues[]='Campo atual '.$field.' diverge do valor detetado.';
    }
    // All title capacities must be explained, including variants and expansion limits.
    foreach($tokens[0] as $token){
        preg_match('~(\d+(?:[.,]\d+)?)\s*(GB|TB)~iu',$token,$m);$gb=db_memory_gb($m[1],$m[2]);
        if(($ram===null || $gb!=$ram) && ($storage===null || $gb!=$storage))$issues[]='Capacidade no nome sem classificacao inequivoca: '.$token.'.';
    }
    $proposed=$name;
    if($ram!==null && !$issues){
        // Label only the RAM token, preserving the rest of the product title.
        $proposed=preg_replace_callback('~\b(\d+(?:[.,]\d+)?)\s*(GB|TB)\b~iu',function($match) use($ram,$name){
            if(db_memory_gb($match[1],$match[2])!=$ram)return $match[0];
            // Existing explicit RAM labels already make the name unambiguous.
            foreach(db_memory_extract($name,'nome') as $item)if($item['type']==='ram')return $match[0];
            return $match[0].' RAM';
        },$name);
        // Handle a compact title such as 12/512GB.
        if(!preg_match('~\bRAM\b~iu',$proposed))$proposed=preg_replace('~\b\d+(?:[.,]\d+)?\s*/(?=\s*\d+\s*(?:GB|TB)\b)~iu',db_memory_label($ram).' RAM /',$proposed,1);
    }
    if(!$name)$issues[]='Nome em portugues ausente.';
    if($issues)$status='rever';
    elseif(!$evidence)$status='sem_dados';
    elseif($storage===null)$status='rever';
    else{
        $changed=$proposed!==$name;
        foreach(array('ram_gb'=>$ram,'storage_gb'=>$storage) as $field=>$value)if($value!==null && (($product[$field] ?? null)===null || (float)$product[$field]!=$value))$changed=true;
        $status=$changed?'proposta':'sem_alteracao';
    }
    if($status==='rever' && !$issues)$issues[]='Armazenamento nao identificado com seguranca.';
    preg_match('/\b(\d+GB)\b/',$name,$legacy);
    return array(
        'id'=>(int)$product['ID'],'ref'=>(string)($product['Ref'] ?? ''),'approved'=>$product['Aproved'] ?? null,
        'status'=>$status,'current'=>array('name'=>$name,'ram_gb'=>$product['ram_gb'] ?? null,'storage_gb'=>$product['storage_gb'] ?? null,'capacity_display'=>$legacy[0] ?? null),
        'detected'=>array('ram_gb'=>$ram,'storage_gb'=>$storage),
        'proposed'=>$status==='rever'||$status==='sem_dados'?null:array('name'=>$proposed,'ram_gb'=>$ram ?? ($product['ram_gb'] ?? null),'storage_gb'=>$storage,'capacity_display'=>$storage!==null?db_memory_label($storage):null),
        'issues'=>array_values(array_unique($issues)),'evidence'=>$evidence
    );
}

function db_memory_html_row(array $row) {
    $escape=function($value){return htmlspecialchars((string)$value,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');};
    $current=$row['current'];$proposed=$row['proposed'];
    $cells=array($row['id'],$row['ref'],$row['status'],$current['name'],$proposed['name'] ?? 'Sem proposta segura',
        $current['ram_gb'],$row['detected']['ram_gb'],$current['storage_gb'],$row['detected']['storage_gb'],
        $current['capacity_display'],$proposed['capacity_display'] ?? null,implode(' ', $row['issues']),
        implode(' | ',array_map(function($item){return $item['source'].': '.$item['text'];},$row['evidence'])));
    return '<tr data-status="'.$escape($row['status']).'">'.implode('',array_map(function($value) use($escape){return '<td>'.$escape($value===null?'—':$value).'</td>';},$cells))."</tr>\n";
}
