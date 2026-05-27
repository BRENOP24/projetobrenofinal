<?php 

require_once 'config/sessao.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/conexao.php';
require_once 'config/funcoes.php';


// ===============================
// FILTROS
// ===============================

$data_inicial = $_GET['data_inicial'] ?? date('Y-m-d');
$data_final   = $_GET['data_final'] ?? date('Y-m-d');
$forma_id     = $_GET['forma_id'] ?? '';
$considerar_online = $_GET['online'] ?? 'sim';


// ===============================
// PARAMS
// ===============================

$params = [];


// ===============================
// QUERY BASE
// ===============================

$sql = "

SELECT 

    p.id,
    p.criado_em AS data_pedido,
    p.valor_total,

    fp.descricao AS forma_nome,

    c.nome AS nome_cliente,

    'cliente_presencial' AS tipo_cliente,

    CASE

        WHEN p.tipo_venda ILIKE 'balcao'
            THEN 'Presencial (Balcão)'

        WHEN p.tipo_venda ILIKE 'delivery'
            THEN 'Presencial (Delivery Manual)'

        WHEN p.tipo_venda ILIKE 'local'
            THEN 'Presencial (Consumo Local)'

        ELSE 'Presencial'

    END AS origem

FROM pedidos p

INNER JOIN formas_pagamento fp
    ON fp.id = p.forma_pagamento_id

LEFT JOIN clientes c
    ON c.id = p.cliente_id

WHERE p.criado_em BETWEEN ? AND ?
AND p.status = 'finalizado'

";

$params[] = $data_inicial . ' 00:00:00';
$params[] = $data_final . ' 23:59:59';


// ===============================
// FORMA PAGAMENTO PRESENCIAL
// ===============================

if (!empty($forma_id)) {

    $sql .= " AND p.forma_pagamento_id = ? ";
    $params[] = $forma_id;
}


// ===============================
// ONLINE
// ===============================

if ($considerar_online === 'sim') {

    $sql .= "

    UNION ALL

    SELECT 

        po.id,
        po.data_pedido AS data_pedido,
        po.valor_total,

        fp2.descricao AS forma_nome,

        co.nome AS nome_cliente,

        'cliente_online' AS tipo_cliente,

        CASE

            WHEN po.tipo_entrega ILIKE 'retirada'
                THEN 'Online (Retirada)'

            ELSE 'Online (Entrega)'

        END AS origem

    FROM pedidos_online po

    INNER JOIN formas_pagamento fp2
        ON fp2.id = po.forma_pagamento_id

    LEFT JOIN clientes_online co
        ON co.id = po.cliente_id

    WHERE po.data_pedido BETWEEN ? AND ?
    AND po.status = 'finalizado'

    ";

    $params[] = $data_inicial . ' 00:00:00';
    $params[] = $data_final . ' 23:59:59';


    if (!empty($forma_id)) {

        $sql .= " AND po.forma_pagamento_id = ? ";
        $params[] = $forma_id;
    }
}


// ===============================
// ORDER
// ===============================

$sql .= " ORDER BY data_pedido DESC ";


// ===============================
// EXECUÇÃO
// ===============================

try {

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die('Erro SQL: ' . $e->getMessage());
}


// ===============================
// TOTAIS
// ===============================

$total_geral = 0;
$resumo = [];

foreach ($vendas as $v) {

    $total_geral += (float)$v['valor_total'];

    $nome_f = $v['forma_nome'] ?: 'Não Informado';

    if (!isset($resumo[$nome_f])) {
        $resumo[$nome_f] = 0;
    }

    $resumo[$nome_f] += (float)$v['valor_total'];
}


// ===============================
// FORMAS PAGAMENTO
// ===============================

$todas_formas = $pdo->query("
    SELECT id, descricao
    FROM formas_pagamento
    WHERE status = 'ativo'
    ORDER BY descricao ASC
")->fetchAll(PDO::FETCH_ASSOC);

?>
