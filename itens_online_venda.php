<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

// Filtros de Data (Padrão: mês atual para dar uma visão melhor de média)
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim    = $_GET['data_fim']    ?? date('Y-m-t');

// Parâmetros para a query vinculando com a tabela mãe de pedidos online
$params = [
    ':inicio' => $data_inicio . ' 00:00:00',
    ':fim'    => $data_fim . ' 23:59:59'
];

// Query Magnífica: Agrupa os itens vendidos, calcula quantidade, média de preço e faturamento total
$sql = "
    SELECT 
        pr.id AS produto_id,
        pr.nome AS produto_nome,
        SUM(poi.quantidade) AS total_vendido,
        AVG(poi.preco_unitario) AS preco_medio_venda,
        SUM(poi.quantidade * poi.preco_unitario) AS faturamento_total
    FROM pedidos_online_itens poi
    INNER JOIN produtos pr ON poi.produto_id = pr.id
    INNER JOIN pedidos_online po ON poi.pedido_id = po.id
    WHERE po.data_pedido BETWEEN :inicio AND :fim
      AND po.status = 'Finalizado' -- Apenas computa o que realmente foi vendido/entregue
    GROUP BY pr.id, pr.nome
    ORDER BY total_vendido DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ranking_produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cálculos de Totais para os Cards Informativos
$total_itens_geral = 0;
$faturamento_total_geral = 0;
$maior_quantidade = 0;

foreach ($ranking_produtos as $prod) {
    $total_itens_geral += $prod['total_vendido'];
    $faturamento_total_geral += $prod['faturamento_total'];
    if ($prod['total_vendido'] > $maior_quantidade) {
        $maior_quantidade = $prod['total_vendido'];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Produtos Online - Gestão Breno</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-chart-line text-success"></i> Desempenho de Vendas Online</h2>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-home"></i> Voltar ao Painel</a>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Data Inicial</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($data_inicio) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Data Final</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($data_fim) ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-success w-100"><i class="fas fa-sync-alt"></i> Atualizar Indicadores</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-white p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Volume Total de Itens Despachados</h6>
                        <h3 class="fw-bold mb-0 text-dark"><?= number_format($total_itens_geral, 0, ',', '.') ?> un.</h3>
                    </div>
                    <div class="bg-light-success p-3 rounded-circle text-success fs-3">
                        <i class="fas fa-box-open"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-white p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Faturamento Bruto do Canal Digital</h6>
                        <h3 class="fw-bold mb-0 text-success">R$ <?= number_format($faturamento_total_geral, 2, ',', '.') ?></h3>
                    </div>
                    <div class="bg-light-primary p-3 rounded-circle text-primary fs-3">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white fw-bold py-3">
            <i class="fas fa-trophy text-warning me-2"></i> Ranking de Produtos Mais Vendidos no Site
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 8%">Posição</th>
                        <th style="width: 35%">Produto</th>
                        <th style="width: 25%">Relevância Visual / Saída</th>
                        <th class="text-center" style="width: 12%">Qtd. Vendida</th>
                        <th class="text-end" style="width: 10%">Preço Médio</th>
                        <th class="text-end" style="width: 10%">Total Gerado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($ranking_produtos) > 0): ?>
                        <?php 
                        $posicao = 1; 
                        foreach ($ranking_produtos as $item): 
                            // Calcula a porcentagem para preencher a barra de progresso visual
                            $porcentagem = $maior_quantidade > 0 ? ($item['total_vendido'] / $maior_quantidade) * 100 : 0;
                        ?>
                        <tr>
                            <td class="text-center">
                                <?php if($posicao === 1): ?>
                                    <span class="badge bg-warning text-dark px-2.5 py-1.5 rounded-circle"><i class="fas fa-crown"></i> 1º</span>
                                <?php elseif($posicao === 2): ?>
                                    <span class="badge bg-secondary text-white px-2.5 py-1.5 rounded-circle">2º</span>
                                <?php elseif($posicao === 3): ?>
                                    <span class="badge bg-danger text-white px-2.5 py-1.5 rounded-circle">3º</span>
                                <?php else: ?>
                                    <span class="text-muted fw-bold"><?= $posicao ?>º</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong class="text-dark"><?= htmlspecialchars($item['produto_nome']) ?></strong>
                                <small class="text-muted d-block">ID do Produto: #<?= $item['produto_id'] ?></small>
                            </td>
                            <td>
                                <div class="progress" style="height: 10px; background-color: #e9ecef;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $porcentagem ?>%" aria-valuenow="<?= $porcentagem ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </td>
                            <td class="text-center fw-bold text-dark"><?= $item['total_vendido'] ?></td>
                            <td class="text-end text-muted">R$ <?= number_format($item['preco_medio_venda'], 2, ',', '.') ?></td>
                            <td class="text-end fw-bold text-success">R$ <?= number_format($item['faturamento_total'], 2, ',', '.') ?></td>
                        </tr>
                        <?php 
                        $posicao++;
                        endforeach; 
                        ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-comment-slash fs-2 mb-2 d-block"></i>
                                Nenhum item foi vendido através da plataforma online neste intervalo de datas.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .bg-light-success { background-color: #e8f5e9; }
    .bg-light-primary { background-color: #e3f2fd; }
    .progress-bar { transition: width 0.6s ease; }
</style>

</body>
</html>