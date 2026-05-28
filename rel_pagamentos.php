<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// ===========================
// FILTROS
// ===========================

$data_inicial = $_GET['data_inicial'] ?? date('Y-m-01');
$data_final   = $_GET['data_final'] ?? date('Y-m-t');

$incluir_online      = isset($_GET['incluir_online']) ? 1 : 0;
$modo_detalhado      = isset($_GET['detalhado']) ? 1 : 0;
$incluir_cancelados  = isset($_GET['incluir_cancelados']) ? 1 : 0;
$somente_finalizados = isset($_GET['somente_finalizados']) ? 1 : 0;

$inicio = $data_inicial . ' 00:00:00';
$fim    = $data_final . ' 23:59:59';

$dados_pagamentos = [];
$pedidos_detalhados = [];

$total_geral = 0;
$total_quantidade = 0;

// ===========================
// QUERY BASE PEDIDOS
// ===========================

$where = "
WHERE p.data_pedido BETWEEN ? AND ?
AND p.origem_tipo IN ('balcao', 'delivery')
";

$params = [$inicio, $fim];

if($somente_finalizados){
    $where .= " AND LOWER(p.situacao) = 'finalizado'";
}

if(!$incluir_cancelados){
    $where .= " AND LOWER(p.situacao) != 'cancelado'";
}

$sql = "
SELECT 
    p.id,
    p.data_pedido,
    p.valor_total,
    p.origem_tipo,
    p.situacao,
    c.nome as cliente_nome,
    COALESCE(fp.descricao, 'Não Informado') as forma_pagamento
FROM pedidos p
LEFT JOIN clientes c 
    ON p.cliente_id = c.id
LEFT JOIN formas_pagamento fp 
    ON p.forma_pagamento_id = fp.id
$where
ORDER BY fp.descricao, p.data_pedido DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===========================
// PROCESSA PEDIDOS
// ===========================

foreach($pedidos as $p){

    $forma = $p['forma_pagamento'];

    if(!isset($dados_pagamentos[$forma])){
        $dados_pagamentos[$forma] = [
            'quantidade' => 0,
            'total' => 0
        ];
    }

    $dados_pagamentos[$forma]['quantidade']++;
    $dados_pagamentos[$forma]['total'] += (float)$p['valor_total'];

    $pedidos_detalhados[$forma][] = $p;

    $total_geral += (float)$p['valor_total'];
    $total_quantidade++;
}

// ===========================
// PEDIDOS ONLINE
// ===========================

if($incluir_online){

    $where_online = "
    WHERE p.data_pedido BETWEEN ? AND ?
    ";

    $params_online = [$inicio, $fim];

    if($somente_finalizados){
        $where_online .= " AND LOWER(p.status) = 'finalizado'";
    }

    if(!$incluir_cancelados){
        $where_online .= " AND LOWER(p.status) != 'cancelado'";
    }

    $sql_online = "
    SELECT 
        p.id,
        p.data_pedido,
        p.valor_total,
        p.status as situacao,
        'online' as origem_tipo,
        c.nome as cliente_nome,
        COALESCE(fp.descricao, 'Não Informado') as forma_pagamento
    FROM pedidos_online p
    LEFT JOIN clientes_online c 
        ON p.cliente_id = c.id
    LEFT JOIN formas_pagamento fp 
        ON p.forma_pagamento_id = fp.id
    $where_online
    ORDER BY fp.descricao, p.data_pedido DESC
    ";

    $stmt_online = $pdo->prepare($sql_online);
    $stmt_online->execute($params_online);

    $pedidos_online = $stmt_online->fetchAll(PDO::FETCH_ASSOC);

    foreach($pedidos_online as $p){

        $forma = $p['forma_pagamento'];

        if(!isset($dados_pagamentos[$forma])){
            $dados_pagamentos[$forma] = [
                'quantidade' => 0,
                'total' => 0
            ];
        }

        $dados_pagamentos[$forma]['quantidade']++;
        $dados_pagamentos[$forma]['total'] += (float)$p['valor_total'];

        $pedidos_detalhados[$forma][] = $p;

        $total_geral += (float)$p['valor_total'];
        $total_quantidade++;
    }
}

// ===========================
// ORDENA
// ===========================

uasort($dados_pagamentos, function($a, $b){
    return $b['total'] <=> $a['total'];
});

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Relatório Financeiro</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    background:#f4f7fb;
    font-family:'Inter',sans-serif;
    color:#1e293b;
    padding:20px;
}

.container{
    max-width:1400px;
    margin:auto;
}

.topo{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:15px;
    margin-bottom:25px;
}

.topo h1{
    font-size:28px;
}

.btn{
    border:none;
    border-radius:10px;
    padding:12px 18px;
    cursor:pointer;
    font-weight:600;
    text-decoration:none;
    transition:0.2s;
}

.btn:hover{
    transform:translateY(-2px);
}

.btn-primary{
    background:#2563eb;
    color:white;
}

.btn-dark{
    background:#0f172a;
    color:white;
}

.btn-light{
    background:white;
    color:#334155;
    border:1px solid #dbe2ea;
}

.filtros{
    background:white;
    border-radius:20px;
    padding:25px;
    margin-bottom:25px;
    box-shadow:0 10px 30px rgba(15,23,42,0.05);

    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:18px;
}

.campo{
    display:flex;
    flex-direction:column;
}

.campo label{
    font-size:12px;
    font-weight:700;
    margin-bottom:8px;
    color:#64748b;
    text-transform:uppercase;
}

.campo input{
    height:46px;
    border-radius:12px;
    border:1px solid #dbe2ea;
    padding:0 14px;
    background:#f8fafc;
}

.check{
    display:flex;
    align-items:end;
}

.check label{
    display:flex;
    align-items:center;
    gap:10px;
    font-weight:600;
    color:#334155;
}

.cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
    margin-bottom:25px;
}

.card{
    background:white;
    border-radius:18px;
    padding:24px;
    box-shadow:0 10px 25px rgba(15,23,42,0.05);
}

.card h3{
    font-size:12px;
    text-transform:uppercase;
    color:#64748b;
    margin-bottom:10px;
}

.card .valor{
    font-size:30px;
    font-weight:700;
}

.tabela{
    background:white;
    border-radius:20px;
    overflow:hidden;
    box-shadow:0 10px 25px rgba(15,23,42,0.05);
}

.tabela-scroll{
    overflow:auto;
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:900px;
}

th{
    background:#f8fafc;
    padding:18px;
    text-align:left;
    font-size:12px;
    color:#64748b;
    border-bottom:1px solid #e2e8f0;
}

td{
    padding:18px;
    border-bottom:1px solid #edf2f7;
}

tbody tr:hover{
    background:#f8fbff;
}

.badge{
    background:#dbeafe;
    color:#1d4ed8;
    padding:7px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
}

.valor-verde{
    color:#16a34a;
    font-weight:700;
}

.detalhes{
    background:#f8fafc;
}

.total-geral{
    margin-top:25px;
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    color:white;
    padding:30px;
    border-radius:20px;
}

.total-geral h2{
    font-size:16px;
    margin-bottom:10px;
}

.total-geral .numero{
    font-size:38px;
    font-weight:700;
}

@media(max-width:768px){

    body{
        padding:12px;
    }

    .topo h1{
        font-size:22px;
    }

    .card .valor{
        font-size:24px;
    }

}

@media print{

    .no-print{
        display:none !important;
    }

    body{
        background:white;
        padding:0;
    }

}

</style>

</head>
<body>

<div class="container">

    <div class="topo">

        <h1>💳 Relatório de Meios de Pagamento</h1>

        <div style="display:flex;gap:10px;" class="no-print">

            <button onclick="window.print()" class="btn btn-dark">
                🖨️ Imprimir
            </button>

            <a href="dashboard.php" class="btn btn-light">
                ← Voltar
            </a>

        </div>

    </div>

    <form method="GET" class="filtros no-print">

        <div class="campo">
            <label>Data Inicial</label>
            <input type="date"
                   name="data_inicial"
                   value="<?= $data_inicial ?>">
        </div>

        <div class="campo">
            <label>Data Final</label>
            <input type="date"
                   name="data_final"
                   value="<?= $data_final ?>">
        </div>

        <div class="check">
            <label>
                <input type="checkbox"
                       name="incluir_online"
                       <?= $incluir_online ? 'checked' : '' ?>>
                Incluir Online
            </label>
        </div>

        <div class="check">
            <label>
                <input type="checkbox"
                       name="detalhado"
                       <?= $modo_detalhado ? 'checked' : '' ?>>
                Mostrar Detalhes
            </label>
        </div>

        <div class="check">
            <label>
                <input type="checkbox"
                       name="somente_finalizados"
                       <?= $somente_finalizados ? 'checked' : '' ?>>
                Apenas Finalizados
            </label>
        </div>

        <div class="check">
            <label>
                <input type="checkbox"
                       name="incluir_cancelados"
                       <?= $incluir_cancelados ? 'checked' : '' ?>>
                Incluir Cancelados
            </label>
        </div>

        <div style="display:flex;align-items:end;">
            <button type="submit"
                    class="btn btn-primary"
                    style="width:100%;">
                🔍 Filtrar
            </button>
        </div>

    </form>

    <div class="cards">

        <div class="card">
            <h3>Total Faturado</h3>
            <div class="valor">
                R$ <?= number_format($total_geral, 2, ',', '.') ?>
            </div>
        </div>

        <div class="card">
            <h3>Quantidade Pedidos</h3>
            <div class="valor">
                <?= $total_quantidade ?>
            </div>
        </div>

        <div class="card">
            <h3>Meios Pagamento</h3>
            <div class="valor">
                <?= count($dados_pagamentos) ?>
            </div>
        </div>

    </div>

    <div class="tabela">

        <div class="tabela-scroll">

            <table>

                <thead>
                    <tr>
                        <th>Forma Pagamento</th>
                        <th>Quantidade</th>
                        <th>Total</th>
                        <th>%</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach($dados_pagamentos as $forma => $dados):

                    $percentual = $total_geral > 0
                        ? ($dados['total'] / $total_geral) * 100
                        : 0;

                ?>

                    <tr>

                        <td>
                            <strong><?= htmlspecialchars($forma) ?></strong>
                        </td>

                        <td>
                            <span class="badge">
                                <?= $dados['quantidade'] ?> vendas
                            </span>
                        </td>

                        <td class="valor-verde">
                            R$ <?= number_format($dados['total'], 2, ',', '.') ?>
                        </td>

                        <td>
                            <?= number_format($percentual, 2, ',', '.') ?>%
                        </td>

                    </tr>

                    <?php if($modo_detalhado && isset($pedidos_detalhados[$forma])): ?>

                    <tr class="detalhes">

                        <td colspan="4">

                            <table style="width:100%;">

                                <thead>
                                    <tr>
                                        <th>Pedido</th>
                                        <th>Data</th>
                                        <th>Cliente</th>
                                        <th>Origem</th>
                                        <th>Status</th>
                                        <th>Valor</th>
                                    </tr>
                                </thead>

                                <tbody>

                                <?php foreach($pedidos_detalhados[$forma] as $pedido): ?>

                                    <tr>

                                        <td>
                                            #<?= str_pad($pedido['id'], 5, '0', STR_PAD_LEFT) ?>
                                        </td>

                                        <td>
                                            <?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($pedido['cliente_nome'] ?: 'Consumidor Final') ?>
                                        </td>

                                        <td style="text-transform:capitalize;">
                                            <?= htmlspecialchars($pedido['origem_tipo']) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($pedido['situacao']) ?>
                                        </td>

                                        <td class="valor-verde">
                                            R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        </td>

                    </tr>

                    <?php endif; ?>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

    <div class="total-geral">

        <h2>FATURAMENTO TOTAL DO PERÍODO</h2>

        <div class="numero">
            R$ <?= number_format($total_geral, 2, ',', '.') ?>
        </div>

    </div>

</div>

</body>
</html>
