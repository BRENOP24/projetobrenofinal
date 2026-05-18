<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// 1. Filtros de Data e Tipo
$data_inicial = $_GET['data_inicial'] ?? date('Y-m-d');
$data_final   = $_GET['data_final']   ?? date('Y-m-d');
$tipo_origem  = $_GET['tipo_origem']  ?? ''; // 'mesa' ou 'comanda'

$params = [$data_inicial . ' 00:00:00', $data_final . ' 23:59:59'];

// 2. Query focada em Mesa e Comanda baseada na estrutura da tabela 'pedidos'
$sql = "SELECT 
            p.id, 
            p.data_pedido, 
            p.valor_total, 
            p.origem_tipo, 
            p.origem_id,
            fp.descricao as forma_nome,
            c.nome as nome_cliente
        FROM pedidos p
        JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE p.data_pedido BETWEEN ? AND ? 
          AND p.situacao = 'finalizado'
          AND p.origem_tipo IN ('mesa', 'comanda')";

// Filtro dinâmico por tipo
if ($tipo_origem) {
    $sql .= " AND p.origem_tipo = ?";
    $params[] = $tipo_origem;
}

$sql .= " ORDER BY p.data_pedido DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Cálculo de Totais para os Cards
$total_mesas = 0;
$total_comandas = 0;

foreach($vendas as $v) {
    if($v['origem_tipo'] == 'mesa') $total_mesas += (float)$v['valor_total'];
    if($v['origem_tipo'] == 'comanda') $total_comandas += (float)$v['valor_total'];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório Mesa/Comanda - Gestão Breno</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; padding: 20px; color: #1a202c; }
        .container { max-width: 1100px; margin: auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        
        /* Cabeçalho */
        .header-relatorio { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 2px solid #f7fafc; padding-bottom: 15px; }
        .btn-voltar { 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            background: #edf2f7; 
            color: #4a5568; 
            padding: 10px 18px; 
            border-radius: 8px; 
            font-weight: 600; 
            font-size: 14px;
            transition: all 0.2s;
        }
        .btn-voltar:hover { background: #e2e8f0; color: #2d3748; transform: translateX(-3px); }

        /* Filtros */
        .filtros { display: flex; gap: 15px; background: #f8fafc; padding: 20px; border-radius: 10px; margin-bottom: 25px; align-items: flex-end; border: 1px solid #edf2f7; }
        .filtros label { display: block; font-size: 11px; font-weight: bold; color: #718096; margin-bottom: 5px; text-transform: uppercase; }
        .filtros input, .filtros select { padding: 10px; border-radius: 6px; border: 1px solid #cbd5e0; outline: none; font-size: 14px; }
        .btn-filtrar { padding: 10px 25px; background: #3182ce; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; transition: background 0.2s; }
        .btn-filtrar:hover { background: #2b6cb0; }

        /* Cards de Resumo */
        .resumo-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .card { padding: 20px; border-radius: 10px; background: #fff; border: 1px solid #e2e8f0; border-left: 6px solid #3182ce; }
        .card h3 { margin: 0; font-size: 12px; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; }
        .card p { margin: 8px 0 0; font-size: 24px; font-weight: 800; color: #2d3748; }
        
        /* Tabela */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; background: #f7fafc; color: #4a5568; font-size: 13px; font-weight: 700; text-transform: uppercase; border-bottom: 2px solid #edf2f7; }
        td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        tr:hover { background-color: #fcfcfc; }

        /* Badges de Identificação */
        .badge { padding: 5px 10px; border-radius: 6px; font-weight: bold; font-size: 11px; text-transform: uppercase; }
        .bg-mesa { background: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; }
        .bg-comanda { background: #faf5ff; color: #6b46c1; border: 1px solid #e9d8fd; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-relatorio">
        <h2 style="margin:0; display:flex; align-items:center; gap:10px;">
            <span style="font-size:24px;">📊</span> Consumo Local: Mesas e Comandas
        </h2>
        <a href="dashboard.php" class="btn-voltar">
            ⬅️ Voltar
        </a>
    </div>

    <form method="GET" class="filtros">
        <div>
            <label>Início</label>
            <input type="date" name="data_inicial" value="<?= $data_inicial ?>">
        </div>
        <div>
            <label>Fim</label>
            <input type="date" name="data_final" value="<?= $data_final ?>">
        </div>
        <div>
            <label>Filtrar por</label>
            <select name="tipo_origem">
                <option value="">Todos (Mesa/Comanda)</option>
                <option value="mesa" <?= $tipo_origem == 'mesa' ? 'selected' : '' ?>>Somente Mesas</option>
                <option value="comanda" <?= $tipo_origem == 'comanda' ? 'selected' : '' ?>>Somente Comandas</option>
            </select>
        </div>
        <button type="submit" class="btn-filtrar">Aplicar Filtro</button>
    </form>

    <div class="resumo-cards">
        <div class="card" style="border-left-color: #4299e1;">
            <h3>Total Mesas</h3>
            <p>R$ <?= number_format($total_mesas, 2, ',', '.') ?></p>
        </div>
        <div class="card" style="border-left-color: #9f7aea;">
            <h3>Total Comandas</h3>
            <p>R$ <?= number_format($total_comandas, 2, ',', '.') ?></p>
        </div>
        <div class="card" style="border-left-color: #48bb78;">
            <h3>Total Geral</h3>
            <p>R$ <?= number_format($total_mesas + $total_comandas, 2, ',', '.') ?></p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Data / Hora</th>
                <th>Origem</th>
                <th>Identificação</th>
                <th>Cliente</th>
                <th>Pagamento</th>
                <th style="text-align:right;">Valor Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($vendas) > 0): ?>
                <?php foreach($vendas as $v): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($v['data_pedido'])) ?></td>
                    <td>
                        <span class="badge <?= $v['origem_tipo'] == 'mesa' ? 'bg-mesa' : 'bg-comanda' ?>">
                            <?= strtoupper($v['origem_tipo']) ?>
                        </span>
                    </td>
                    <td style="font-weight:bold; color: #2d3748;"># <?= $v['origem_id'] ?></td>
                    <td style="color: #718096;"><?= htmlspecialchars($v['nome_cliente'] ?: 'Consumidor Final') ?></td>
                    <td><?= $v['forma_nome'] ?></td>
                    <td style="text-align:right; font-weight:800; color: #2d3748;">
                        R$ <?= number_format($v['valor_total'], 2, ',', '.') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding: 40px; color: #a0aec0;">
                        Nenhuma venda encontrada para o período selecionado.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>