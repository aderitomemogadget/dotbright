<?php if(!isset($mm_csrf))exit; ?>
<style>
.mm-admin{background:#fff;color:#25364a;margin:0;padding:28px;border:1px solid #e3e8ef;border-radius:12px;font-size:14px;line-height:1.5;min-width:0}
.mm-admin h1{font-size:26px;font-weight:700;margin:0 0 6px}.mm-admin h2{font-size:21px}.mm-admin h3{font-size:17px;margin:24px 0 12px}.mm-admin p{margin:0 0 14px}.mm-admin .mm-subtitle{color:#627287;max-width:900px}
.mm-admin .mm-tools{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin:20px 0}.mm-admin label{display:block;font-weight:600;color:#425369;margin:0}
.mm-admin input:not([type=checkbox]):not([type=hidden]),.mm-admin select,.mm-admin textarea{display:block;box-sizing:border-box;padding:10px 12px;border:1px solid #cdd6e1;border-radius:6px;max-width:100%;min-height:42px;background:#fff;color:#25364a;font-weight:400;margin-top:6px}
.mm-admin input[type=checkbox]{width:17px;height:17px;margin:2px;accent-color:#173f69;cursor:pointer}.mm-admin textarea{width:100%;min-height:85px}.mm-admin .mm-search{flex:1;max-width:420px;min-width:200px}.mm-admin .mm-search input{width:100%}
.mm-admin button,.mm-admin .mm-button{box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;border:1px solid #173f69;border-radius:6px;padding:10px 16px;min-height:42px;background:#173f69;color:#fff;margin:0;cursor:pointer;font-weight:600;text-decoration:none;line-height:20px}
.mm-admin button:hover,.mm-admin .mm-button:hover{background:#235581;color:white}.mm-admin .mm-secondary{background:#fff;color:#173f69;border-color:#cdd6e1}.mm-admin .mm-secondary:hover{background:#f1f5fa;color:#173f69}.mm-admin button:disabled{opacity:.45;cursor:not-allowed}.mm-admin .mm-danger{background:#a34525;border-color:#a34525}
.mm-admin .mm-table{overflow-x:auto;border:1px solid #e1e7ef;border-radius:8px}.mm-admin table{border-collapse:collapse;width:100%;font-size:13px;margin:0}.mm-admin th,.mm-admin td{padding:12px;border:0;border-bottom:1px solid #e8edf3;vertical-align:top;text-align:left}.mm-admin th{background:#f5f7fa;color:#4a5b70;font-size:12px;font-weight:700;white-space:nowrap}.mm-admin tbody tr:last-child td{border-bottom:0}.mm-admin tbody tr:hover{background:#f8fbfe}
.mm-admin .mm-note,.mm-admin .mm-error{padding:14px 16px;background:#edf5fc;border:1px solid #d9e8f6;border-radius:7px;margin:16px 0}.mm-admin .mm-error{background:#fff5e6;border-color:#f0d8ad;color:#805116}.mm-admin details{margin:12px 0}.mm-admin summary{cursor:pointer;font-weight:600}.mm-admin pre{white-space:pre-wrap;overflow-wrap:anywhere;max-height:250px;overflow:auto;background:#f5f7fa;border:1px solid #e1e7ef;border-radius:6px;padding:12px}.mm-admin .mm-name{min-width:190px;max-width:320px;overflow-wrap:anywhere}
.mm-admin .mm-scan-panel{background:#f7f9fc;border:1px solid #e1e7ef;border-radius:9px;padding:20px;margin:24px 0}.mm-admin .mm-scan-panel .mm-tools{margin:0 0 14px}.mm-admin .mm-scan-panel p{color:#627287;margin:8px 0 0}.mm-admin .mm-progress-track{height:8px;background:#e0e7ef;border-radius:5px;overflow:hidden;margin-top:16px}.mm-admin .mm-progress-fill{height:100%;width:0;background:#1592bb;transition:width .2s}.mm-admin #mm-progress{display:block;margin-top:10px;font-weight:600}
.mm-admin .mm-stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin:24px 0}.mm-admin .mm-stat{display:block;padding:15px;border:1px solid #e1e7ef;border-radius:8px;color:#627287;text-decoration:none;background:#fff}.mm-admin .mm-stat strong{display:block;color:#243d59;font-size:25px;line-height:1.3;margin-top:5px}.mm-admin .mm-stat[aria-current=page]{border-color:#1592bb;background:#f0f9fc}.mm-admin .mm-empty{padding:42px 20px;text-align:center;background:#fafbfd;border:1px dashed #cdd6e1;border-radius:8px}.mm-admin .mm-empty strong{display:block;font-size:18px;margin-bottom:8px}
.mm-admin .mm-pagination{display:flex;position:static;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;background:#fff;color:#627287;min-height:58px;height:auto;padding:16px 0 0;margin:0;overflow:visible;border-top:1px solid #e8edf3;margin-top:20px}.mm-admin .mm-pagination-actions{display:flex;gap:10px}.mm-admin .mm-selection{color:#627287;margin-left:auto}.mm-admin .mm-status{display:inline-block;border-radius:20px;background:#edf3f9;color:#334f70;padding:4px 9px;font-size:12px;white-space:nowrap}.mm-admin :focus-visible{outline:3px solid #ed8b24;outline-offset:2px}.mm-admin [hidden]{display:none!important}
@media(max-width:1100px){.mm-admin .mm-stats{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:600px){.mm-admin{padding:16px}.mm-admin .mm-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.mm-admin .mm-tools>label{width:100%;max-width:none}.mm-admin .mm-pagination{align-items:flex-start;flex-direction:column}.mm-admin .mm-selection{margin-left:0}}
</style>
<section class="mm-admin">
<h1>RAM e armazenamento</h1>
<p class="mm-subtitle">Analisa as memórias do catálogo, compara os valores e revê as correções propostas. Os produtos só mudam quando aprovas e aplicas uma proposta.</p>
<?php foreach($mm_notices as $message){ ?><p class="mm-note" role="status"><?=mm_escape($message)?></p><?php } ?>
<?php if($mm_error){ ?><p class="mm-error" role="alert"><?=mm_escape($mm_error)?></p><?php } ?>
<?php if($mm_ready && !$mm_can_apply){ ?><div class="mm-note"><strong>Análise e revisão disponíveis.</strong> A aplicação das correções aguarda a preparação da base de dados.<details><summary>Ver requisito para aplicar correções</summary><p><?=mm_escape(implode(', ',$mm_write_blockers))?> precisa de InnoDB para permitir a aplicação e reversão em transação.</p></details></div><?php } ?>
<?php if($mm_ready && !$mm_detail){ ?>
<?php $resume=is_array($mm_scan_state) && empty($mm_scan_state['done']) && $mm_scan_state['cursor']>0; ?>
<div class="mm-scan-panel">
<form method="post" id="mm-scan-form"><input type="hidden" name="_csrf" value="<?=mm_escape($mm_csrf)?>"><input type="hidden" name="action" value="scan_browser"><input type="hidden" name="cursor" value="<?=$resume?(int)$mm_scan_state['cursor']:0?>"><input type="hidden" name="until" value="<?=$resume?(int)$mm_scan_state['until']:0?>">
<div class="mm-tools"><button type="submit" id="mm-scan"><?=$resume?'Continuar análise':'Analisar todo o catálogo'?></button><button type="button" id="mm-stop" class="mm-secondary" disabled>Parar análise</button><a href="list.php?status=" id="mm-results">Ver propostas guardadas</a></div></form>
<p>O catálogo é analisado em lotes de 100. Esta operação guarda propostas e mantém os produtos inalterados.</p>
<div class="mm-progress-track" id="mm-track" role="progressbar" aria-label="Progresso da análise" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" hidden><div class="mm-progress-fill" id="mm-progress-fill"></div></div>
<span id="mm-progress" role="status" aria-live="polite"><?=$resume?mm_escape($mm_scan_state['examined'].' produtos já analisados. Podes continuar de onde paraste.'):'Pronto para iniciar a análise.'?></span>
</div>
<div class="mm-stats"><?php foreach($mm_labels as $key=>$label){ ?><a class="mm-stat" href="list.php?status=<?=mm_escape($key)?>" <?=$mm_status===$key?'aria-current="page"':''?>><?=mm_escape($label)?><strong><?=(int)($mm_counts[$key] ?? 0)?></strong></a><?php } ?></div>
<form method="get" class="mm-tools"><label>Estado<select name="status"><option value="">Todos</option><?php foreach($mm_labels as $key=>$label){ ?><option value="<?=mm_escape($key)?>" <?=$mm_status===$key?'selected':''?>><?=mm_escape($label)?></option><?php } ?></select></label><label class="mm-search">Produto, referência ou nome<input name="q" value="<?=mm_escape($mm_search)?>" placeholder="Pesquisar por ID, referência ou nome"></label><button>Filtrar</button><a class="mm-button mm-secondary" href="list.php?status=">Limpar</a></form>
<form method="post" id="mm-bulk"><input type="hidden" name="_csrf" value="<?=mm_escape($mm_csrf)?>"></form>
<div class="mm-tools"><button form="mm-bulk" name="action" value="bulk_apply" id="mm-bulk-apply" class="mm-confirm" data-can-apply="<?=$mm_can_apply?'1':'0'?>" disabled data-confirm="Aplicar as propostas selecionadas aos produtos? Apenas propostas por aprovar serao aplicadas.">Aprovar e aplicar selecionadas</button><button form="mm-bulk" name="action" value="bulk_reject" id="mm-bulk-reject" class="mm-secondary" disabled>Rejeitar selecionadas</button><span class="mm-selection" id="mm-selection">0 selecionadas</span></div>
<?php if($mm_rows){ ?>
<div class="mm-table"><table><thead><tr><th><input type="checkbox" id="mm-all" aria-label="Selecionar propostas desta pagina"></th><th>Produto</th><th>Estado</th><th>Nome atual na analise</th><th>Nome proposto</th><th>RAM atual → proposta</th><th>Armazenamento atual → proposta</th><th>Atualizacao (UTC)</th></tr></thead><tbody>
<?php foreach($mm_rows as $row){$snapshot=json_decode($row['snapshot_json'],true); ?>
<tr><td><?php if(in_array($row['status'],array('pending','review'),true)){ ?><input type="checkbox" form="mm-bulk" name="ids[]" value="<?=(int)$row['id']?>" aria-label="Selecionar proposta <?=(int)$row['id']?>"><input form="mm-bulk" type="hidden" name="versions[<?=(int)$row['id']?>]" value="<?=(int)$row['version']?>"><?php } ?></td><td><a href="list.php?id=<?=(int)$row['id']?>">#<?=(int)$row['product_id']?> · <?=mm_escape($row['Ref'] ?? 'Produto removido')?></a></td><td><span class="mm-status"><?=mm_escape($mm_labels[$row['status']] ?? $row['status'])?></span></td><td class="mm-name"><?=mm_escape($snapshot['name'] ?? '')?></td><td class="mm-name"><?=mm_escape($row['proposed_name'] ?? 'Necessita revisao')?></td><td><?=mm_escape($snapshot['ram_gb'] ?? '—')?> → <?=mm_escape($row['ram_gb'] ?? '—')?></td><td><?=mm_escape($snapshot['storage_gb'] ?? '—')?> → <?=mm_escape($row['storage_gb'] ?? '—')?></td><td><?=mm_escape($row['updated_at'])?></td></tr>
<?php } ?>
<?php if(!$mm_rows){ ?><tr><td colspan="8">Sem propostas neste filtro. Pode executar a analise para procurar produtos.</td></tr><?php } ?>
</tbody></table></div>
<?php }else{ ?><div class="mm-empty"><strong><?=array_sum($mm_counts)?'Sem propostas neste filtro':'Ainda não há propostas'?></strong><p><?=array_sum($mm_counts)?'Escolhe outro estado ou limpa a pesquisa.':'Inicia a análise do catálogo para gerar as primeiras propostas de correção.'?></p></div><?php } ?>
<div class="mm-pagination" role="navigation" aria-label="Paginação"><span><?php $pages=max(1,(int)ceil($mm_total/20));$base='list.php?'.http_build_query(array('status'=>$mm_status,'q'=>$mm_search)); ?>Página <?=$mm_page?> de <?=$pages?> · <?=$mm_total?> propostas · 20 por página</span><div class="mm-pagination-actions"><?php if($mm_page>1){ ?><a class="mm-button mm-secondary" href="<?=mm_escape($base.'&p='.($mm_page-1))?>">Anterior</a><?php } ?><?php if($mm_page<$pages){ ?><a class="mm-button mm-secondary" href="<?=mm_escape($base.'&p='.($mm_page+1))?>">Seguinte</a><?php } ?></div></div>
<?php } ?>
<?php if($mm_detail){$snapshot=json_decode($mm_detail['snapshot_json'],true);$analysis=json_decode($mm_detail['analysis_json'],true);$active=in_array($mm_detail['status'],array('pending','review'),true); ?>
<a href="list.php">← Voltar a listagem</a>
<h2>Produto #<?=(int)$mm_detail['product_id']?> · <?=mm_escape($mm_detail['Ref'] ?? '')?></h2>
<p>Estado: <strong><?=mm_escape($mm_labels[$mm_detail['status']] ?? $mm_detail['status'])?></strong> · <a href="../products/products.php?ID=<?=(int)$mm_detail['product_id']?>">Abrir produto no BO</a></p>
<?php if($mm_live && $active && !hash_equals($mm_detail['source_hash'],db_memory_manage_hash($mm_live))){ ?><p class="mm-error">O produto mudou desde esta analise. Execute nova analise; esta proposta nao pode ser aplicada.</p><?php } ?>
<div class="mm-table"><table><tr><th>Campo</th><th>Na analise</th><th>Produto agora</th></tr><?php foreach(array('name'=>'Nome PT','ram_gb'=>'RAM (GB)','storage_gb'=>'Armazenamento (GB)') as $field=>$label){ ?><tr><th><?=mm_escape($label)?></th><td><?=mm_escape($snapshot[$field] ?? '—')?></td><td><?=mm_escape($mm_live[$field] ?? '—')?></td></tr><?php } ?></table></div>
<?php if(!empty($analysis['issues'])){ ?><h3>Motivos para revisao</h3><ul><?php foreach($analysis['issues'] as $issue){ ?><li><?=mm_escape($issue)?></li><?php } ?></ul><?php } ?>
<details><summary>Evidencias da analise</summary><pre><?=mm_escape(json_encode($analysis['evidence'] ?? array(),JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))?></pre></details>
<?php if($active){ ?>
<h3>Editar proposta</h3>
<form method="post"><input type="hidden" name="_csrf" value="<?=mm_escape($mm_csrf)?>"><input type="hidden" name="id" value="<?=(int)$mm_detail['id']?>"><input type="hidden" name="version" value="<?=(int)$mm_detail['version']?>">
<label>Nome proposto<textarea name="name" required><?=mm_escape($mm_detail['proposed_name'] ?? $snapshot['name'] ?? '')?></textarea></label>
<div class="mm-tools"><label>RAM fisica em GB (vazio se desconhecida)<input name="ram_gb" inputmode="decimal" value="<?=mm_escape($mm_detail['ram_gb'] ?? '')?>"></label><label>Armazenamento em GB<input name="storage_gb" inputmode="decimal" required value="<?=mm_escape($mm_detail['storage_gb'] ?? '')?>"></label></div>
<label>Nota<textarea name="note" maxlength="2000"></textarea></label>
<button name="action" value="save">Guardar proposta sem aplicar</button>
</form>
<form method="post"><input type="hidden" name="_csrf" value="<?=mm_escape($mm_csrf)?>"><input type="hidden" name="id" value="<?=(int)$mm_detail['id']?>"><input type="hidden" name="version" value="<?=(int)$mm_detail['version']?>">
<p>Aplicar utiliza a proposta guardada. Guarde primeiro qualquer edicao acima.</p>
<?php if($mm_detail['status']==='pending'){ ?><button name="action" value="apply" class="mm-confirm" <?=$mm_can_apply?'':'disabled'?> data-confirm="Aplicar a proposta guardada ao nome, RAM e armazenamento deste produto?">Aprovar e aplicar proposta guardada</button><?php } ?>
<button name="action" value="reject" class="mm-danger">Rejeitar</button></form>
<?php }elseif($mm_detail['status']==='applied'){ ?>
<form method="post"><input type="hidden" name="_csrf" value="<?=mm_escape($mm_csrf)?>"><input type="hidden" name="id" value="<?=(int)$mm_detail['id']?>"><input type="hidden" name="version" value="<?=(int)$mm_detail['version']?>"><button name="action" value="revert" class="mm-confirm mm-danger" <?=$mm_can_apply?'':'disabled'?> data-confirm="Repor os valores anteriores? A reversao sera bloqueada se estes campos mudaram entretanto.">Reverter correcao</button></form>
<?php } ?>
<h3>Historico (ultimos 100 eventos, UTC)</h3><div class="mm-table"><table><thead><tr><th>Data</th><th>Acao</th><th>Administrador</th><th>Valores anteriores</th><th>Valores posteriores</th><th>Nota</th></tr></thead><tbody><?php $actions=array('detected'=>'Detetada','reopened'=>'Reaberta','save'=>'Editada','apply'=>'Aplicada','reject'=>'Rejeitada','revert'=>'Revertida','stale'=>'Desatualizada');foreach($mm_events as $event){ ?><tr><td><?=mm_escape($event['created_at'])?></td><td><?=mm_escape($actions[$event['action']] ?? $event['action'])?></td><td><?=mm_escape($event['admin_id'] ?? 'Cron')?></td><td><pre><?=mm_escape($event['before_json'] ?? '')?></pre></td><td><pre><?=mm_escape($event['after_json'] ?? '')?></pre></td><td><?=mm_escape($event['note'])?></td></tr><?php } ?></tbody></table></div>
<?php } ?>
</section>
<script>
(function(){
const root=document.querySelector('.mm-admin');if(!root)return;
root.querySelectorAll('.mm-confirm').forEach(button=>button.addEventListener('click',event=>{if(!confirm(button.dataset.confirm))event.preventDefault();}));
const all=root.querySelector('#mm-all');const choices=Array.from(root.querySelectorAll('input[name="ids[]"]'));
function selection(){
    const selected=choices.filter(input=>input.checked).length;
    const apply=root.querySelector('#mm-bulk-apply');const reject=root.querySelector('#mm-bulk-reject');
    if(apply)apply.disabled=selected===0||apply.dataset.canApply!=='1';
    if(reject)reject.disabled=selected===0;
    const label=root.querySelector('#mm-selection');if(label)label.textContent=selected+' selecionadas';
    if(all){all.disabled=choices.length===0;all.checked=selected>0&&selected===choices.length;all.indeterminate=selected>0&&selected<choices.length;}
}
if(all)all.addEventListener('change',()=>{choices.forEach(input=>input.checked=all.checked);selection();});
choices.forEach(input=>input.addEventListener('change',selection));selection();
const form=root.querySelector('#mm-scan-form');if(!form)return;
const start=root.querySelector('#mm-scan');const stop=root.querySelector('#mm-stop');
const progress=root.querySelector('#mm-progress');const track=root.querySelector('#mm-track');const fill=root.querySelector('#mm-progress-fill');let stopped=false;
stop.addEventListener('click',()=>{stopped=true;stop.disabled=true;progress.textContent='A terminar o lote atual…';});
form.addEventListener('submit',async function(event){
    if(!window.fetch||!window.URLSearchParams)return; // The native POST still analyses a batch.
    event.preventDefault();if(start.disabled)return;
    start.disabled=true;stop.disabled=false;stopped=false;track.hidden=false;let completed=false;
    let cursor=Number(form.elements.cursor.value)||0,until=Number(form.elements.until.value)||0;
    try{
        do{
            start.textContent='A analisar…';progress.textContent='A analisar produtos. Aguarda a conclusão do lote…';
            const response=await fetch('list.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},body:new URLSearchParams({_csrf:form.elements._csrf.value,action:'scan',cursor:String(cursor),until:String(until)})});
            let result;try{result=await response.json();}catch(error){throw new Error('A sessão expirou ou o servidor devolveu uma resposta inesperada. Recarrega a página e tenta novamente.');}
            if(!response.ok||result.error)throw new Error(result.error||'Nao foi possivel analisar.');
            cursor=result.cursor;until=result.until;form.elements.cursor.value=cursor;form.elements.until.value=until;
            const state=result.progress;const percent=state.total?Math.min(100,Math.round(state.examined/state.total*100)):100;
            fill.style.width=percent+'%';track.setAttribute('aria-valuenow',String(percent));
            progress.textContent=state.examined+' de '+state.total+' produtos analisados · '+state.queued+' propostas novas.';
            if(state.failed)progress.textContent+=' Produtos com erro: '+state.failed+'. A análise continua nos restantes.';
            if(result.done){completed=true;progress.textContent='Análise concluída. A atualizar as propostas…';window.location.assign('list.php?status=');break;}
        }while(!stopped);
        if(stopped&&!completed)progress.textContent+=' Análise parada. Podes continuar ou consultar as propostas guardadas.';
    }catch(error){progress.textContent='Análise interrompida: '+error.message+' As propostas já guardadas estão disponíveis em “Ver propostas guardadas”.';form.elements.cursor.value=0;form.elements.until.value=0;}
    finally{if(!completed){start.disabled=false;start.textContent=Number(form.elements.cursor.value)>0?'Continuar análise':'Analisar todo o catálogo';}stop.disabled=true;}
});
})();
</script>
