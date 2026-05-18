<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// 1. Definição dos Filtros
$data_inicial = $_GET['data_inicial'] ?? date('Y-m-01');
$data_final   = $_GET['data_final']   ?? date('Y-m-t');
$status_filtro = $_GET['status'] ?? '';

// 2. Construção da Query
$params = [$data_inicial . ' 00:00:00', $data_final . ' 23:59:59'];
$where = "WHERE p.data_pedido BETWEEN ? AND ?";

if (!empty($status_filtro)) {
    $where .= " AND p.status = ?";
    $params[] = $status_filtro;
}

// Fazendo JOIN com clientes_online (para nome/telefone) e formas_pagamento (para o nome do pagamento)
$sql = "SELECT 
            p.*, 
            c.nome as nome_cliente, 
            c.telefone as whatsapp, 
            fp.descricao as nome_pagamento
        FROM pedidos_online p
        INNER JOIN clientes_online c ON p.cliente_id = c.id
        LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
        $where ORDER BY p.data_pedido DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cálculos de Totais (Ignorando pedidos Cancelados)
$total_pedidos = 0;
$total_taxas = 0;
$qtd_pedidos_validos = 0;

foreach($pedidos as $p) { 
    if ($p['status'] !== 'Cancelado') {
        $total_pedidos += $p['valor_total']; 
        $total_taxas += $p['taxa_entrega'];
        $qtd_pedidos_validos++;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório Pedidos Online - Gestão</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #f7fafc; padding: 12px; text-align: left; color: #4a5568; border-bottom: 2px solid #edf2f7; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #edf2f7; color: #2d3748; font-size: 13px; }
        
        /* Badges de Status Dinâmicos */
        .badge { padding: 5px 10px; border-radius: 15px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block;}
        .status-pendente { background: #feebc8; color: #9c4221; }
        .status-confirmado { background: #bee3f8; color: #2a4365; }
        .status-empreparo { background: #e9d8fd; color: #553c9a; }
        .status-saiuparaentrega { background: #fefcbf; color: #744210; }
        .status-finalizado { background: #c6f6d5; color: #22543d; }
        .status-cancelado { background: #fed7d7; color: #822727; }

        .filtro-card { background: #f7fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 30px; }
        
        .resumo-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; }
        .card { background: #fff; padding: 15px; border-radius: 10px; border: 1px solid #e2e8f0; text-align: center; }
        .card h3 { margin: 0; font-size: 12px; color: #718096; text-transform: uppercase; }
        .card p { margin: 5px 0 0; font-size: 20px; font-weight: bold; color: #2d3748; }

        @media print {
            form, .btn-voltar, .no-print { display: none !important; }
            .container { box-shadow: none; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 2px solid #38a169; padding-bottom: 10px;">
        <h2 style="margin:0; color: #1a202c;">🌐 Relatório de Pedidos Online</h2>
        <a href="dashboard.php" class="btn-voltar" style="text-decoration:none; color:#718096; font-weight:500;">← Voltar ao Painel</a>
    </div>

    <div class="resumo-cards">
        <div class="card">
            <h3>Faturamento (Válidos)</h3>
            <p>R$ <?= number_format($total_pedidos, 2, ',', '.') ?></p>
        </div>
        <div class="card">
            <h3>Total Taxas de Entrega</h3>
            <p>R$ <?= number_format($total_taxas, 2, ',', '.') ?></p>
        </div>
        <div class="card">
            <h3>Qtd Pedidos (Válidos)</h3>
            <p><?= $qtd_pedidos_validos ?> <span style="font-size:12px; font-weight:normal; color:#a0aec0;">(Total: <?= count($pedidos) ?>)</span></p>
        </div>
    </div>

    <form method="GET" class="filtro-card" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
        <div>
            <label style="font-size:11px; font-weight:bold; color:#718096; display:block; margin-bottom:5px;">INÍCIO</label>
            <input type="date" name="data_inicial" value="<?= $data_inicial ?>" style="width:100%; padding:10px; border-radius:6px; border:1px solid #cbd5e0; box-sizing:border-box;">
        </div>
        <div>
            <label style="font-size:11px; font-weight:bold; color:#718096; display:block; margin-bottom:5px;">FIM</label>
            <input type="date" name="data_final" value="<?= $data_final ?>" style="width:100%; padding:10px; border-radius:6px; border:1px solid #cbd5e0; box-sizing:border-box;">
        </div>
        <div>
            <label style="font-size:11px; font-weight:bold; color:#718096; display:block; margin-bottom:5px;">STATUS</label>
            <select name="status" style="width:100%; padding:10px; border-radius:6px; border:1px solid #cbd5e0; box-sizing:border-box;">
                <option value="">Todos</option>
                <option value="Pendente" <?= $status_filtro == 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                <option value="Confirmado" <?= $status_filtro == 'Confirmado' ? 'selected' : '' ?>>Confirmado</option>
                <option value="Em Preparo" <?= $status_filtro == 'Em Preparo' ? 'selected' : '' ?>>Em Preparo</option>
                <option value="Saiu para Entrega" <?= $status_filtro == 'Saiu para Entrega' ? 'selected' : '' ?>>Saiu para Entrega</option>
                <option value="Finalizado" <?= $status_filtro == 'Finalizado' ? 'selected' : '' ?>>Finalizado</option>
                <option value="Cancelado" <?= $status_filtro == 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
            </select>
        </div>
        <div style="display: flex; align-items: flex-end; gap: 10px;">
            <button type="submit" style="flex:2; height:42px; background:#38a169; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">🔍 FILTRAR</button>
            <button type="button" onclick="window.print()" style="flex:1; height:42px; background:#4a5568; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;" title="Imprimir Relatório">🖨️</button>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>ID</th>
                <th>Cliente / Telefone</th>
                <th>Tipo/Bairro</th>
                <th>Pagamento</th>
                <th>Taxa</th>
                <th>Total</th>
                <th style="text-align:center;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($pedidos)): ?>
                <tr><td colspan="8" style="text-align:center; padding:30px; color:#718096;">Nenhum pedido online encontrado para este período com os filtros selecionados.</td></tr>
            <?php endif; ?>

            <?php foreach($pedidos as $p): 
                // Remove espaços e acentos para a classe CSS (Ex: 'Em Preparo' -> 'empreparo')
                $classe_status = 'status-' . strtolower(str_replace(' ', '', $p['status']));
            ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($p['data_pedido'])) ?></td>
                <td><strong>#<?= str_pad($p['id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                <td>
                    <?= htmlspecialchars($p['nome_cliente']) ?><br>
                    <small style="color:#718096;"><?= htmlspecialchars($p['whatsapp']) ?></small>
                </td>
                <td>
                    <?php if($p['tipo_entrega'] === 'retirada'): ?>
                        <span style="color:#c53030; font-weight:bold;">🏪 Retirada no Local</span>
                    <?php else: ?>
                        <span style="color:#2b6cb0;">🛵 <?= htmlspecialchars($p['bairro_entrega']) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($p['nome_pagamento'] ?? 'Não informado') ?></td>
                <td>R$ <?= number_format($p['taxa_entrega'], 2, ',', '.') ?></td>
                <td style="font-weight:bold;">R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></td>
                <td style="text-align:center;">
                    <span class="badge <?= $classe_status ?>"><?= $p['status'] ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>