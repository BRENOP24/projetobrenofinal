<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// 1. Filtros recebidos via URL
$data_inicial = $_GET['data_inicial'] ?? date('Y-m-d');
$data_final   = $_GET['data_final']   ?? date('Y-m-d');
$forma_id     = $_GET['forma_id']     ?? '';
$considerar_online = $_GET['online']  ?? 'sim';

$data_ini_completa = $data_inicial . ' 00:00:00';
$data_fim_completa = $data_final . ' 23:59:59';

// Inicializamos o array de bind com os parâmetros fixos de data
$params = [
    ':data_ini' => $data_ini_completa,
    ':data_fim' => $data_fim_completa
];

// ==============================================================
// 🔥 PARTE 1: APENAS VENDAS PRESENCIAIS REAIS (Ignora tudo do Site)
// ==============================================================
$sql = "SELECT 
            p.id, 
            p.criado_em as data_pedido, 
            p.valor_total, 
            fp.descricao as forma_nome, 
            c.nome as nome_cliente, 
            CASE 
                WHEN p.tipo_venda ILIKE 'balcao' THEN 'Presencial (Balcão)'
                WHEN p.tipo_venda ILIKE 'delivery' THEN 'Presencial (Delivery Manual)'
                WHEN p.tipo_venda ILIKE 'local' THEN 'Presencial (Consumo Local)'
                ELSE 'Presencial'
            END as origem
        FROM pedidos p
        JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE p.criado_em BETWEEN :data_ini AND :data_fim 
        -- Filtros rigorosos para remover cancelados
        AND LOWER(TRIM(p.status)) <> 'cancelado'
        AND LOWER(TRIM(COALESCE(p.situacao, ''))) <> 'cancelado'
        -- Filtro para não deixar misturar pedidos do site aqui na busca local
        AND (p.origem_tipo NOT ILIKE 'Online' OR p.origem_tipo IS NULL)";

if ($forma_id) {
    $sql .= " AND p.forma_pagamento_id = :forma_id";
    $params[':forma_id'] = $forma_id;
}

// ==============================================================
// 🔥 PARTE 2: APENAS VENDAS DO SITE (Buscando em clientes_online)
// ==============================================================
if ($considerar_online === 'sim') {
    $sql .= " UNION ALL 
              SELECT 
                  po.id, 
                  po.data_pedido, 
                  po.valor_total, 
                  fp2.descricao as forma_nome, 
                  co.nome as nome_cliente, 
                  CASE 
                     WHEN po.tipo_entrega ILIKE 'retirada' THEN 'Online (Retirada)'
                     ELSE 'Online (Entrega)'
                  END as origem
              FROM pedidos_online po
              JOIN formas_pagamento fp2 ON po.forma_pagamento_id = fp2.id
              LEFT JOIN clientes_online co ON po.cliente_id = co.id
              WHERE po.data_pedido BETWEEN :data_ini_online AND :data_fim_online 
              -- Filtro estrito anti-cancelados do site
              AND LOWER(TRIM(po.status)) <> 'cancelado'";

    $params[':data_ini_online'] = $data_ini_completa;
    $params[':data_fim_online'] = $data_fim_completa;

    if ($forma_id) {
        $sql .= " AND po.forma_pagamento_id = :forma_id_online";
        $params[':forma_id_online'] = $forma_id;
    }
}

// Ordenação final do bloco unificado
$sql .= " ORDER BY data_pedido DESC";

try {
    $stmt = $pdo->prepare($sql);
    // O execute vincula as chaves nomeadas perfeitamente, sem quebras
    $stmt->execute($params);
    $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro na consulta do relatório: " . $e->getMessage());
}

// ==============================================================
// 3. PROCESSAMENTO E SOMA DOS VALORES (FECHAMENTO)
// ==============================================================
$total_geral = 0;
$resumo = [];

foreach($vendas as $v) {
    $total_geral += (float)$v['valor_total'];
    $nome_f = $v['forma_nome'] ?: 'Não Informado';
    $resumo[$nome_f] = ($resumo[$nome_f] ?? 0) + (float)$v['valor_total'];
}

// Busca as formas ativas para montar o formulário select
$todas_formas = $pdo->query("
    SELECT id, descricao 
    FROM formas_pagamento 
    WHERE status = 'ativo' 
    ORDER BY descricao ASC
")->fetchAll();
?>
