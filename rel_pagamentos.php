<?php 

require_once 'config/sessao.php'; 
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

AND p.caixa_id IS NOT NULL

AND COALESCE(p.status, '') NOT ILIKE 'cancelado'
AND COALESCE(p.situacao, '') NOT ILIKE 'cancelado'

AND (
    p.status ILIKE 'finalizado'
    OR p.situacao ILIKE 'finalizado'
)
";


// filtro forma pagamento
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

    AND po.id_caixa IS NOT NULL

    AND COALESCE(po.status, '') NOT ILIKE 'cancelado'

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

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">
    <title>Relatório de Vendas - Geral</title>

    <style>

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background-color: #f0f2f5;
            padding: 20px;
            color: #2d3748;
        }

        .container {
            max-width: 1150px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .btn-voltar {
            text-decoration: none;
            background: #edf2f7;
            padding: 10px 20px;
            border-radius: 8px;
            color: #4a5568;
            font-weight: bold;
            transition: 0.2s;
        }

        .btn-voltar:hover {
            background: #e2e8f0;
        }

        .filtros {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .filtros label {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 5px;
            display: block;
        }

        .filtros input,
        .filtros select {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #cbd5e0;
            outline: none;
        }

        .resumo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .card-resumo {
            background: #fff;
            border: 1px solid #e2e8f0;
            padding: 20px;
            border-radius: 10px;
            border-top: 4px solid #3182ce;
        }

        .card-total {
            border-top-color: #38a169;
            background: #f0fff4;
        }

        .card-resumo h4 {
            margin: 0;
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
        }

        .card-resumo p {
            margin: 10px 0 0;
            font-size: 18px;
            font-weight: bold;
            color: #2d3748;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            padding: 15px;
            text-align: left;
            font-size: 12px;
            color: #64748b;
            border-bottom: 2px solid #edf2f7;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
        }

        .badge-origem {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: bold;
            display: inline-block;
        }

        .origem-balcao {
            background: #ebf8ff;
            color: #3182ce;
        }

        .origem-delivery-manual {
            background: #edf2f7;
            color: #4a5568;
        }

        .origem-local {
            background: #e2e8f0;
            color: #1a202c;
        }

        .origem-online-entrega {
            background: #fefcbf;
            color: #b7791f;
        }

        .origem-online-retirada {
            background: #e6fffa;
            color: #319795;
        }

        .right {
            text-align: right;
        }

        @media print {
            .filtros,
            .btn-voltar {
                display: none;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <div class="header-flex">

        <h2>💳 Relatório de Vendas Detalhado</h2>

        <a href="dashboard.php" class="btn-voltar">← Dashboard</a>

    </div>

    <form method="GET" class="filtros">

        <div>
            <label>Data Inicial</label>
            <input type="date" name="data_inicial" value="<?= $data_inicial ?>">
        </div>

        <div>
            <label>Data Final</label>
            <input type="date" name="data_final" value="<?= $data_final ?>">
        </div>

        <div>
            <label>Forma de Pagamento</label>

            <select name="forma_id">

                <option value="">Todas as Formas</option>

                <?php foreach($todas_formas as $tf): ?>

                    <option value="<?= $tf['id'] ?>" <?= $forma_id == $tf['id'] ? 'selected' : '' ?>>
                        <?= $tf['descricao'] ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div>

            <label>Incluir Online?</label>

            <select name="online">

                <option value="sim" <?= $considerar_online == 'sim' ? 'selected' : '' ?>>Sim</option>
                <option value="nao" <?= $considerar_online == 'nao' ? 'selected' : '' ?>>Não</option>

            </select>

        </div>

        <div style="display: flex; align-items: flex-end; gap: 5px;">

            <button type="submit"
                style="background:#3182ce; color:white; border:none; padding:10px; border-radius:8px; width:100%; font-weight:bold; cursor:pointer;">
                Filtrar
            </button>

            <button type="button"
                onclick="window.print()"
                style="background:#4a5568; color:white; border:none; padding:10px; border-radius:8px; cursor:pointer;">
                🖨️
            </button>

        </div>

    </form>


    <!-- RESUMO -->

    <div class="resumo-grid">

        <?php foreach($resumo as $nome => $valor): ?>

            <div class="card-resumo">

                <h4><?= htmlspecialchars($nome) ?></h4>

                <p>
                    R$ <?= number_format($valor, 2, ',', '.') ?>
                </p>

            </div>

        <?php endforeach; ?>

        <div class="card-resumo card-total">

            <h4>Faturamento Geral</h4>

            <p>
                R$ <?= number_format($total_geral, 2, ',', '.') ?>
            </p>

        </div>

    </div>


    <!-- TABELA -->

    <table>

        <thead>

            <tr>

                <th>Data/Hora</th>
                <th>Origem / Tipo</th>
                <th>Tipo Cliente</th>
                <th>Pedido</th>
                <th>Cliente</th>
                <th>Forma de Pagamento</th>
                <th class="right">Valor Total</th>

            </tr>

        </thead>

        <tbody>

            <?php foreach($vendas as $v): ?>

            <tr>

                <td style="color: #718096;">
                    <?= date('d/m/Y H:i', strtotime($v['data_pedido'])) ?>
                </td>

                <td>

                    <?php 

                        $classe_badge = 'origem-balcao';

                        if ($v['origem'] == 'Presencial (Delivery Manual)')
                            $classe_badge = 'origem-delivery-manual';

                        if ($v['origem'] == 'Presencial (Consumo Local)')
                            $classe_badge = 'origem-local';

                        if ($v['origem'] == 'Online (Entrega)')
                            $classe_badge = 'origem-online-entrega';

                        if ($v['origem'] == 'Online (Retirada)')
                            $classe_badge = 'origem-online-retirada';

                    ?>

                    <span class="badge-origem <?= $classe_badge ?>">
                        <?= strtoupper($v['origem']) ?>
                    </span>

                </td>

                <td>

                    <?= $v['tipo_cliente'] == 'cliente_online'
                        ? 'Cliente Online'
                        : 'Cliente Presencial' ?>

                </td>

                <td style="font-weight: bold;">
                    #<?= $v['id'] ?>
                </td>

                <td>
                    <?= htmlspecialchars($v['nome_cliente'] ?: 'Consumidor Final') ?>
                </td>

                <td>

                    <span style="color: #3182ce; font-weight: 500;">

                        <?= htmlspecialchars($v['forma_nome']) ?>

                    </span>

                </td>

                <td class="right" style="font-weight: bold;">

                    R$ <?= number_format($v['valor_total'], 2, ',', '.') ?>

                </td>

            </tr>

            <?php endforeach; ?>


            <?php if(empty($vendas)): ?>

                <tr>

                    <td colspan="7"
                        style="text-align:center; padding: 50px; color: #a0aec0;">

                        Nenhuma venda encontrada no período.

                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>

</body>
</html>
