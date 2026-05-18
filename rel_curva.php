<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// 1. Filtros de Data
$data_inicial = $_GET['data_inicial'] ?? date('Y-m-01');
$data_final   = $_GET['data_final']   ?? date('Y-m-t');

// 2. Query Curva ABC (Ajustada para pedidos_itens)
// 2. Query Curva ABC Ajustada com base nas imagens
$sql = "WITH VendasProdutos AS (
            SELECT 
                p.id,
                p.nome as produto,
                SUM(pi.valor_total) as total_gerado, -- Usando a coluna que vi no seu print
                SUM(pi.quantidade) as qtd_vendida
            FROM pedidos_itens pi
            JOIN produtos p ON pi.produto_id = p.id
            JOIN pedidos ped ON pi.pedido_id = ped.id
            WHERE ped.data_pedido BETWEEN ? AND ?
            GROUP BY p.id, p.nome
        ),
        TotalGeral AS (
            SELECT COALESCE(SUM(total_gerado), 0) as grand_total FROM VendasProdutos
        ),
        CalculoAcumulado AS (
            SELECT 
                *,
                CASE 
                    WHEN (SELECT grand_total FROM TotalGeral) > 0 
                    THEN (total_gerado / (SELECT grand_total FROM TotalGeral) * 100) 
                    ELSE 0 
                END as percentual,
                CASE 
                    WHEN (SELECT grand_total FROM TotalGeral) > 0 
                    THEN SUM(total_gerado) OVER (ORDER BY total_gerado DESC) / (SELECT grand_total FROM TotalGeral) * 100 
                    ELSE 0 
                END as acumulado
            FROM VendasProdutos
        )
        SELECT * FROM CalculoAcumulado ORDER BY total_gerado DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$data_inicial, $data_final]);
    $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro no banco de dados: " . $e->getMessage());
}

$total_faturado = 0;
foreach($ranking as $r) { $total_faturado += $r['total_gerado']; }
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curva ABC - Sistema Financeiro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #4f46e5; --classeA: #10b981; --classeB: #f59e0b; --classeC: #6b7280; --bg: #f3f4f6; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); margin: 0; padding: 20px; color: #1f2937; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid #f3f4f6; padding-bottom: 20px; }
        h1 { margin: 0; font-size: 22px; display: flex; align-items: center; gap: 10px; }

        .filtro-area { background: #f9fafb; padding: 20px; border-radius: 8px; margin-bottom: 30px; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        label { font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6b7280; }
        input { padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; outline: none; }
        
        .btn { padding: 10px 20px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; font-size: 14px; text-decoration: none; transition: 0.2s; }
        .btn-filter { background: var(--primary); color: white; }
        .btn-back { background: #e5e7eb; color: #374151; }
        .btn:hover { opacity: 0.9; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 15px; background: #f8fafc; border-bottom: 2px solid #edf2f7; font-size: 12px; color: #64748b; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 11px; color: white; text-transform: uppercase; }
        .bg-a { background: var(--classeA); }
        .bg-b { background: var(--classeB); }
        .bg-c { background: var(--classeC); }

        .summary-card { background: #4f46e5; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; text-align: center; }

        @media print { .no-print { display: none; } body { padding: 0; } .container { box-shadow: none; width: 100%; } }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1>📊 Curva ABC de Vendas</h1>
            <p style="margin: 5px 0 0; color: #6b7280;">Análise de relevância de produtos por faturamento</p>
        </div>
        <div class="no-print">
            <a href="dashboard.php" class="btn btn-back">← Voltar</a>
        </div>
    </div>

    <form method="GET" class="no-print filtro-area">
        <div class="form-group">
            <label>Início</label>
            <input type="date" name="data_inicial" value="<?php echo $data_inicial; ?>">
        </div>
        <div class="form-group">
            <label>Término</label>
            <input type="date" name="data_final" value="<?php echo $data_final; ?>">
        </div>
        <button type="submit" class="btn btn-filter">Atualizar Relatório</button>
        <button type="button" onclick="window.print()" class="btn btn-back">Imprimir PDF</button>
    </form>

    <div class="summary-card">
        <small style="text-transform: uppercase; letter-spacing: 1px;">Faturamento Total no Período</small>
        <div style="font-size: 28px; font-weight: 600;">R$ <?php echo number_format($total_faturado, 2, ',', '.'); ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Produto</th>
                <th>Qtd Vendida</th>
                <th>Vlr. Total</th>
                <th>% Partic.</th>
                <th style="text-align: center;">Classe</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($ranking)): ?>
                <tr><td colspan="5" style="text-align: center; padding: 40px; color: #94a3b8;">Nenhuma venda encontrada para este período.</td></tr>
            <?php else: ?>
                <?php foreach($ranking as $item): 
                    $classe = 'C'; $cor = 'bg-c';
                    if($item['acumulado'] <= 80.01) { $classe = 'A'; $cor = 'bg-a'; }
                    elseif($item['acumulado'] <= 95.01) { $classe = 'B'; $cor = 'bg-b'; }
                ?>
                <tr>
                    <td style="font-weight: 600;"><?php echo $item['produto']; ?></td>
                    <td><?php echo (int)$item['qtd_vendida']; ?></td>
                    <td>R$ <?php echo number_format($item['total_gerado'], 2, ',', '.'); ?></td>
                    <td><?php echo number_format($item['percentual'], 2, ',', '.'); ?>%</td>
                    <td style="text-align: center;">
                        <span class="badge <?php echo $cor; ?>">Classe <?php echo $classe; ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>