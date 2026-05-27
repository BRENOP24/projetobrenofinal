<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// 1. Filtros
$data_inicial = $_GET['data_inicial'] ?? date('Y-m-d');
$data_final   = $_GET['data_final']   ?? date('Y-m-d');
$forma_id     = $_GET['forma_id']     ?? '';
$considerar_online = $_GET['online']  ?? 'sim';

// Preparação de parâmetros para as datas (Início e Fim do dia)
$data_ini_completa = $data_inicial . ' 00:00:00';
$data_fim_completa = $data_final . ' 23:59:59';

$params = [$data_ini_completa, $data_fim_completa];

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
        WHERE p.criado_em BETWEEN ? AND ? 
        -- TRAVA ANTI-CANCELADOS: Remove espaços e valida em minúsculo
        AND LOWER(TRIM(p.status)) <> 'cancelado'
        AND LOWER(TRIM(COALESCE(p.situacao, ''))) <> 'cancelado'
        -- TRAVA ANTI-DUPLICIDADE: Não deixa pedidos do site vazarem na tabela local
        AND (p.origem_tipo NOT ILIKE 'Online' OR p.origem_tipo IS NULL)";

// Filtro por forma de pagamento na consulta presencial
if ($forma_id) {
    $sql .= " AND p.forma_pagamento_id = ?";
    $params[] = $forma_id;
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
              WHERE po.data_pedido BETWEEN ? AND ? 
              -- TRAVA ANTI-CANCELADOS ONLINE: Remove sumariamente os cancelados do site
              AND LOWER(TRIM(po.status)) <> 'cancelado'";

    // Adiciona os parâmetros de data correspondentes ao UNION do ambiente online
    $params[] = $data_ini_completa;
    $params[] = $data_fim_completa;

    if ($forma_id) {
        $sql .= " AND po.forma_pagamento_id = ?";
        $params[] = $forma_id;
    }
}

// Ordenação final aplicada sobre todo o conjunto unificado
$sql .= " ORDER BY data_pedido DESC";

try {
    $stmt = $pdo->prepare($sql);
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

// Busca as formas ativas para alimentar o select do formulário
$todas_formas = $pdo->query("
    SELECT id, descricao 
    FROM formas_pagamento 
    WHERE status = 'ativo' 
    ORDER BY descricao ASC
")->fetchAll();
?>
