<?php 

require_once 'config/sessao.php'; 

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/conexao.php';
require_once 'config/funcoes.php';


// ===============================
// 1. FILTROS
// ===============================

$data_inicial = $_GET['data_inicial'] ?? date('Y-m-d');
$data_final   = $_GET['data_final']   ?? date('Y-m-d');
$forma_id     = $_GET['forma_id']     ?? '';
$considerar_online = $_GET['online']  ?? 'sim';


// ===============================
// 2. QUERY
// ===============================

$params = [
    $data_inicial . ' 00:00:00',
    $data_final . ' 23:59:59'
];


// ===============================
// 🔥 VENDAS PRESENCIAIS
// ===============================

$sql = "

SELECT 
    p.id,
    p.criado_em as data_pedido,
    p.valor_total,

    fp.descricao as forma_nome,

    c.nome as nome_cliente,

    'cliente_presencial' as tipo_cliente,

    CASE 

        WHEN p.tipo_venda ILIKE 'balcao'
            THEN 'Presencial (Balcão)'

        WHEN p.tipo_venda ILIKE 'delivery'
            THEN 'Presencial (Delivery Manual)'

        WHEN p.tipo_venda ILIKE 'local'
            THEN 'Presencial (Consumo Local)'

        ELSE 'Presencial'

    END as origem

FROM pedidos p

JOIN formas_pagamento fp 
    ON p.forma_pagamento_id = fp.id

LEFT JOIN clientes c 
    ON p.cliente_id = c.id

WHERE p.criado_em BETWEEN ? AND ?

AND p.status ILIKE 'finalizado'
";


// ===============================
// FILTRO FORMA PAGAMENTO
// ===============================

if ($forma_id) {

    $sql .= " AND p.forma_pagamento_id = ?";
    $params[] = $forma_id;
}


// ===============================
// 🔥 VENDAS ONLINE
// ===============================

if ($considerar_online === 'sim') {

    $sql .= "

    UNION ALL

    SELECT 

        po.id,
        po.data_pedido,
        po.valor_total,

        fp2.descricao as forma_nome,

        co.nome as nome_cliente,

        'cliente_online' as tipo_cliente,

        CASE 

            WHEN po.tipo_entrega ILIKE 'retirada'
                THEN 'Online (Retirada)'

            ELSE 'Online (Entrega)'

        END as origem

    FROM pedidos_online po

    JOIN formas_pagamento fp2 
        ON po.forma_pagamento_id = fp2.id

    LEFT JOIN clientes_online co 
        ON po.cliente_id = co.id

    WHERE po.data_pedido BETWEEN ? AND ?

    AND po.status ILIKE 'finalizado'
    ";

    $params[] = $data_inicial . ' 00:00:00';
    $params[] = $data_final . ' 23:59:59';


    if ($forma_id) {

        $sql .= " AND po.forma_pagamento_id = ?";
        $params[] = $forma_id;
    }
}


// ===============================
// ORDER
// ===============================

$sql .= " ORDER BY data_pedido DESC";


// ===============================
// EXECUÇÃO
// ===============================

try {

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die("Erro na consulta do relatório: " . $e->getMessage());
}


// ===============================
// 3. TOTAIS
// ===============================

$total_geral = 0;
$resumo = [];

foreach($vendas as $v) {

    $total_geral += (float)$v['valor_total'];

    $nome_f = $v['forma_nome'] ?: 'Não Informado';

    $resumo[$nome_f] = ($resumo[$nome_f] ?? 0) + (float)$v['valor_total'];
}


// ===============================
// FORMAS PAGAMENTO
// ===============================

$todas_formas = $pdo->query("
    SELECT id, descricao 
    FROM formas_pagamento 
    WHERE status = 'ativo' 
    ORDER BY descricao ASC
")->fetchAll();

?>
