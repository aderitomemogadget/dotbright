# Analise horaria de RAM e armazenamento

Este cron analisa **todos** os registos de `products`, incluindo inativos e sem stock,
e o nome/descricao de `products_lang` em portugues. Nao altera produtos, nomes,
campos de memoria ou filtros. Nao existe opcao `--apply`. A ligacao PDO usa uma
transacao MySQL READ ONLY e a configuracao habitual `conf/conf.php` do servidor,
sem carregar o bootstrap da loja. Nao cria colunas; se `ram_gb` e `storage_gb`
ainda nao existirem, o relatorio indica essa ausencia e considera os valores NULL.

## Agendamento no servidor

Instalar **uma** entrada no crontab do utilizador da loja, substituindo os caminhos
pelo PHP CLI e pela pasta reais do alojamento:

```cron
0 * * * * /usr/bin/php /CAMINHO/DO/SITE/core/crons/audit_product_memory.php >> /CAMINHO/PRIVADO/product-memory-audit-cron.log 2>&1
```

Executa ao minuto zero de cada hora. Um lock impede execucoes simultaneas.
O agendamento nao e instalado automaticamente ao copiar os ficheiros.

## Relatorios

Por omissao: `../private/product-memory-audit/`, fora da raiz publica do site.
Pode configurar outra pasta privada com `--output-dir=/caminho/privado`.
Cada execucao guarda `memory-DATA-HORA-PID.html`, `.jsonl` (um produto por linha)
e `.summary.json`. HTML inclui todos os produtos e filtro por estado, nomes
atuais/propostos, campos atuais, valores detetados, capacidade que a regra atual
mostraria, capacidade proposta, evidencias e motivos de revisao. O nome analisado e
`products_lang.Cabecalho` em PT, que alimenta o nome mostrado na ficha.
Os ficheiros `.part` identificam uma execucao que ainda nao terminou ou falhou.
O resumo so e escrito apos finalizar os relatorios. Nao existe limpeza automatica:
definir retencao no alojamento segundo o periodo de validacao pretendido.

Estados: `proposta`, `rever`, `sem_dados`, `sem_alteracao`.
Valores explicitos RAM/armazenamento/ROM/SSD/HDD sao comparados entre nome e
descricao. Pares convencionais como `12GB/512GB` e `12/512GB` sao inferidos apenas
em nomes de dispositivos reconhecidos e identificados como tal nas evidencias.
Nao se escolhe simplesmente o primeiro ou maior valor em GB. Capacidades sem
classificacao, variantes, acessorios, RAM virtual, somas e contradicoes sao remetidas
para revisao, sem nome proposto. 1 TB e normalizado para 1024 GB; MB nao e inferido.
Os campos atuais nunca sao apagados nas propostas quando nao ha nova RAM detetada.
Uma proposta ainda exige validacao humana nesta fase; nao constitui publicacao.

## Validacao local sem base de dados

```sh
php tests/product_memory_audit_test.php
php core/crons/audit_product_memory.php --fixture=tests/fixtures/product_memory.json --output-dir=/PASTA/PRIVADA/DE/TESTE
```

As fixtures sao exemplos sinteticos; nao representam uma auditoria do catalogo real.
O modo fixture permite verificar o mesmo analisador e os relatorios sem credenciais.
Opcoes desconhecidas, incluindo `--apply`, sao rejeitadas.
