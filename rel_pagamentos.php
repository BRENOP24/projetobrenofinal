<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// ===========================
// FILTROS
// ===========================

$data_inicial = $_GET['data_inicial'] ?? date('Y-m-01\T00:00');
$data_final   = $_GET['data_final']   ?? date('Y-m-t\T23:59');

$incluir_online      = ($_GET['incluir_online'] ?? 'sim') === 'sim' ? 1 : 0;
$modo_detalhado      = isset($_GET['detalhado']) ? 1 : 0;
$incluir_cancelados  = isset($_GET['incluir_cancelados']) ? 1 : 0;
$somente_finalizados = isset($_GET['somente_finalizados']) ? 1 : 0;

$inicio = date('Y-m-d H:i:s', strtotime($data_inicial));
$fim    = date('Y-m-d H:i:s', strtotime($data_final));

$dados_pagamentos = [];
$pedidos_detalhados = [];

$total_geral = 0;
$total_quantidade = 0;

// ===========================
// QUERY BASE PEDIDOS (LOCAL)
// ===========================

// Incluído 'mesa' e 'comanda' no IN para computar no faturamento local
$where = "
WHERE p.data_pedido BETWEEN ? AND ?
AND p.origem_tipo IN ('balcao', 'delivery', 'mesa', 'comanda')
";

$params = [$inicio, $fim];

if($somente_finalizados){
    $where .= " AND LOWER(p.situacao) = 'finalizado'";
}

if(!$incluir_cancelados){
    $where .= " AND LOWER(p.situacao) != 'cancelado'";
}

// Adicionado p.origem_id na consulta
$sql = "
SELECT 
    p.id,
    p.data_pedido,
    p.valor_total,
    p.origem_tipo,
    p.origem_id,
    p.situacao,
    c.nome as cliente_nome,
    COALESCE(fp.descricao, 'Não Informado') as forma_pagamento
FROM pedidos p
LEFT JOIN clientes c ON p.cliente_id = c.id
LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
$where
ORDER BY fp.descricao, p.data_pedido DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// PROCESSA PEDIDOS LOCAL
foreach($pedidos as $p){
    $forma = $p['forma_pagamento'];

    if(!isset($dados_pagamentos[$forma])){
        $dados_pagamentos[$forma] = ['quantidade' => 0, 'total' => 0];
    }

    $dados_pagamentos[$forma]['quantidade']++;
    $dados_pagamentos[$forma]['total'] += (float)$p['valor_total'];
    $pedidos_detalhados[$forma][] = $p;

    $total_geral += (float)$p['valor_total'];
    $total_quantidade++;
}

// ===========================
// PEDIDOS ONLINE (CORRIGIDO CONFORME O BANCO)
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
        p.origem as origem_tipo, 
        NULL as origem_id,
        co.nome as cliente_nome,
        COALESCE(fp.descricao, 'Não Informado') as forma_pagamento
    FROM pedidos_online p
    LEFT JOIN clientes_online co ON p.cliente_id = co.id
    LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
    $where_online
    ORDER BY fp.descricao, p.data_pedido DESC
    ";

    $stmt_online = $pdo->prepare($sql_online);
    $stmt_online->execute($params_online);
    $pedidos_online = $stmt_online->fetchAll(PDO::FETCH_ASSOC);

    foreach($pedidos_online as $p){
        $forma = $p['forma_pagamento'];

        if(!isset($dados_pagamentos[$forma])){
            $dados_pagamentos[$forma] = ['quantidade' => 0, 'total' => 0];
        }

        $dados_pagamentos[$forma]['quantidade']++;
        $dados_pagamentos[$forma]['total'] += (float)$p['valor_total'];
        $pedidos_detalhados[$forma][] = $p;

        $total_geral += (float)$p['valor_total'];
        $total_quantidade++;
    }
}

// ORDENA POR FATURAMENTO
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
*{ margin:0; padding:0; box-sizing:border-box; }
body{ background:#f4f7fb; font-family:'Inter',sans-serif; color:#1e293b; padding:20px; }
.container{ max-width:1400px; margin:auto; }
.topo{ display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:25px; }
.topo h1{ font-size:26px; font-weight: 700; color:#0f172a; }

.btn{ border:none; border-radius:10px; padding:12px 20px; cursor:pointer; font-weight:600; text-decoration:none; transition:0.2s; display: inline-flex; align-items: center; gap: 8px; }
.btn:hover{ transform:translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.btn-primary{ background:#2563eb; color:white; }
.btn-dark{ background:#0f172a; color:white; }
.btn-light{ background:white; color:#334155; border:1px solid #dbe2ea; }

.filtros{ background:white; border-radius:16px; padding:25px; margin-bottom:25px; box-shadow:0 10px 30px rgba(15,23,42,0.04); display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:18px; border: 1px solid #e2e8f0; }
.campo{ display:flex; flex-direction:column; }
.campo label{ font-size:11px; font-weight:700; margin-bottom:8px; color:#64748b; text-transform:uppercase; letter-spacing: 0.5px; }
.campo input, .campo select{ height:46px; border-radius:10px; border:1px solid #cbd5e1; padding:0 14px; background:#f8fafc; font-family: inherit; color: #334155; outline: none; transition: 0.2s; font-size: 14px; }
.campo input:focus, .campo select:focus{ border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

.checkbox-group { display: flex; flex-wrap: wrap; gap: 20px; grid-column: 1 / -1; background: #f8fafc; padding: 15px; border-radius: 10px; border: 1px solid #e2e8f0; }
.check-item { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; color: #334155; cursor: pointer; }
.check-item input { width: 18px; height: 18px; cursor: pointer; accent-color: #2563eb; }

.cards{ display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:20px; margin-bottom:25px; }
.card{ background:white; border-radius:16px; padding:24px; box-shadow:0 10px 25px rgba(15,23,42,0.03); border: 1px solid #e2e8f0; position: relative; overflow: hidden; }
.card::before { content: ''; position: absolute; top:0; left:0; width:4px; height:100%; background:#2563eb; }
.card-faturamento::before { background: #16a34a; }
.card h3{ font-size:11px; text-transform:uppercase; color:#64748b; margin-bottom:8px; letter-spacing: 0.5px; }
.card .valor{ font-size:28px; font-weight:700; color: #0f172a; }

.tabela{ background:white; border-radius:16px; overflow:hidden; box-shadow:0 10px 25px rgba(15,23,42,0.03); border: 1px solid #e2e8f0; }
.tabela-scroll{ overflow:auto; }
table{ width:100%; border-collapse:collapse; min-width:900px; }
th{ background:#f8fafc; padding:16px 18px; text-align:left; font-size:11px; color:#64748b; border-bottom:2px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.5px; }
td{ padding:16px 18px; border-bottom:1px solid #edf2f7; font-size: 14px; color: #334155; }
tbody tr:hover{ background:#f8fbff; }

.badge{ background:#e0f2fe; color:#0369a1; padding:6px 12px; border-radius:6px; font-size:12px; font-weight:600; }
.badge-origem { background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
.origem-site { background: #fef9c3; color: #854d0e; }
.bg-mesa { background: #ebf8ff; color: #2b6cb0; }
.bg-comanda { background: #faf5ff; color: #6b46c1; }

.valor-verde{ color:#16a34a; font-weight:700; }
.detalhes{ background:#f8fafc; }
.subtabela th { background: #e2e8f0; color: #475569; padding: 10px; font-size: 11px; }
.subtabela td { padding: 12px 10px; border-bottom: 1px solid #cbd5e1; font-size: 13px; }

.total-geral{ margin-top:25px; background:linear-gradient(135deg,#2563eb,#1d4ed8); color:white; padding:25px 30px; border-radius:16px; box-shadow: 0 10px 25px rgba(37,99,235,0.2); display: flex; justify-content: space-between; align-items: center; }
.total-geral h2{ font-size:14px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; }
.total-geral .numero{ font-size:32px; font-weight:700; }

@media(max-width:768px){
    body{ padding:12px; }
    .topo h1{ font-size:20px; }
    .card .valor{ font-size:22px; }
    .total-geral { flex-direction: column; align-items: flex-start; gap: 10px; }
}
</style>
</head>
<body>

<div class="container">

    <div class="topo">
        <h1>💳 Relatório de Meios de Pagamento</h1>
        <div style="display:flex;gap:10px;" class="no-print">
            <button onclick="window.print()" class="btn btn-dark">🖨️ Imprimir</button>
            <a href="dashboard.php" class="btn btn-light">← Voltar</a>
        </div>
    </div>

    <form method="GET" class="filtros no-print">
        <div class="campo">
            <label>Início (Data e Hora)</label>
            <input type="datetime-local" name="data_inicial" value="<?= htmlspecialchars($data_inicial) ?>">
        </div>

        <div class="campo">
            <label>Fim (Data e Hora)</label>
            <input type="datetime-local" name="data_final" value="<?= htmlspecialchars($data_final) ?>">
        </div>

        <div class="campo">
            <label>Incluir Pedidos do Site?</label>
            <select name="incluir_online">
                <option value="sim" <?= ($_GET['incluir_online'] ?? 'sim') === 'sim' ? 'selected' : '' ?>>Sim, incluir Site</option>
                <option value="nao" <?= ($_GET['incluir_online'] ?? '') === 'nao' ? 'selected' : '' ?>>Não, apenas Local</option>
            </select>
        </div>

        <div class="campo" style="justify-content: flex-end;">
            <button type="submit" class="btn btn-primary" style="width:100%; height:46px;">🔍 Filtrar Relatório</button>
        </div>

        <div class="checkbox-group">
            <label class="check-item">
                <input type="checkbox" name="detalhado" value="1" <?= $modo_detalhado ? 'checked' : '' ?>>
                Expandir Detalhes dos Pedidos
            </label>
            <label class="check-item">
                <input type="checkbox" name="somente_finalizados" value="1" <?= $somente_finalizados ? 'checked' : '' ?>>
                Apenas Finalizados
            </label>
            <label class="check-item">
                <input type="checkbox" name="incluir_cancelados" value="1" <?= $incluir_cancelados ? 'checked' : '' ?>>
                Contabilizar Cancelados
            </label>
        </div>
    </form>

    <div class="cards">
        <div class="card card-faturamento">
            <h3>Faturamento Período</h3>
            <div class="valor">R$ <?= number_format($total_geral, 2, ',', '.') ?></div>
        </div>
        <div class="card">
            <h3>Volume de Pedidos</h3>
            <div class="valor"><?= $total_quantidade ?></div>
        </div>
        <div class="card">
            <h3>Métodos Utilizados</h3>
            <div class="valor"><?= count($dados_pagamentos) ?></div>
        </div>
    </div>

    <div class="tabela">
        <div class="tabela-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Forma Pagamento</th>
                        <th>Quantidade</th>
                        <th>Total Geral</th>
                        <th>Representação (%)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($dados_pagamentos as $forma => $dados): 
                    $percentual = $total_geral > 0 ? ($dados['total'] / $total_geral) * 100 : 0;
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($forma) ?></strong></td>
                        <td><span class="badge"><?= $dados['quantidade'] ?> vendas</span></td>
                        <td class="valor-verde">R$ <?= number_format($dados['total'], 2, ',', '.') ?></td>
                        <td><strong><?= number_format($percentual, 2, ',', '.') ?>%</strong></td>
                    </tr>

                    <?php if($modo_detalhado && isset($pedidos_detalhados[$forma])): ?>
                    <tr class="detalhes">
                        <td colspan="4" style="padding: 15px 30px;">
                            <table class="subtabela" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Pedido</th>
                                        <th>Data/Hora</th>
                                        <th>Origem</th>
                                        <th>Identificação</th>
                                        <th>Cliente</th>
                                        <th>Situação</th>
                                        <th style="text-align: right;">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach($pedidos_detalhados[$forma] as $pedido): 
                                    $origem_nome = $pedido['origem_tipo'] ?? 'Não Informado';
                                    $origem_id = $pedido['origem_id'] ?? null;
                                    
                                    // Define a classe CSS de acordo com a origem
                                    $classe_badge = 'badge-origem';
                                    if (strtolower($origem_nome) === 'site') $classe_badge .= ' origem-site';
                                    if (strtolower($origem_nome) === 'mesa') $classe_badge .= ' bg-mesa';
                                    if (strtolower($origem_nome) === 'comanda') $classe_badge .= ' bg-comanda';
                                ?>
                                    <tr>
                                        <td>#<?= str_pad($pedido['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?></td>
                                        <td>
                                            <span class="<?= $classe_badge ?>">
                                                <?= htmlspecialchars($origem_nome) ?>
                                            </span>
                                        </td>
                                        <td style="font-weight: bold;">
                                            <?= $origem_id ? '# ' . $origem_id : '-' ?>
                                        </td>
                                        <td><?= htmlspecialchars($pedido['cliente_nome'] ?: 'Consumidor Final') ?></td>
                                        <td><?= htmlspecialchars($pedido['situacao'] ?? 'Misto') ?></td>
                                        <td class="valor-verde" style="text-align: right;">R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></td>
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
        <h2>Faturamento Total Consolidado</h2>
        <div class="numero">R$ <?= number_format($total_geral, 2, ',', '.') ?></div>
    </div>
</div>

</body>
</html>
