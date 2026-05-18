<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

// Filtros de data (Padrão: mês atual)
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');
$tipo_relatorio = $_GET['tipo_relatorio'] ?? 'resumo';

if ($tipo_relatorio === 'detalhado') {
    // SQL Detalhado: Ordenando primeiro por Motoboy e depois por Data
    $sql = "SELECT 
                p.id AS pedido_id,
                p.criado_em,
                m.nome AS motoboy_nome,
                c.nome AS cliente_nome,
                p.taxa_entrega,
                p.valor_total
            FROM pedidos p
            INNER JOIN motoboys m ON p.motoboy_id = m.id
            LEFT JOIN clientes c ON p.cliente_id = c.id
            WHERE p.status = 'finalizado'
            AND p.criado_em::date BETWEEN :inicio AND :fim
            ORDER BY m.nome ASC, p.criado_em DESC"; // Ajuste na ordenação aqui
} else {
    // SQL Resumo: Mantém o agrupamento original
    $sql = "SELECT 
                m.nome AS motoboy_nome,
                COUNT(p.id) AS qtd_entregas,
                SUM(p.taxa_entrega) AS total_taxas,
                SUM(p.valor_total) AS valor_bruto
            FROM pedidos p
            INNER JOIN motoboys m ON p.motoboy_id = m.id
            WHERE p.status = 'finalizado'
            AND p.criado_em::date BETWEEN :inicio AND :fim
            GROUP BY m.nome
            ORDER BY total_taxas DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
$relatorio = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totais gerais para o rodapé da tabela
$geral_entregas = 0;
$geral_taxas = 0;
$geral_valor = 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Motoboys - SAY NOW</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Estilo opcional para separar visualmente os blocos de motoboy */
        .quebra-motoboy { background-color: #f8f9fa; font-weight: bold; }
    </style>
</head>
<body class="bg-light">
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="fas fa-chart-line text-primary"></i> Acerto de Motoboys</h4>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Início</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= $data_inicio ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Fim</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= $data_fim ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Visualização</label>
                    <select name="tipo_relatorio" class="form-select">
                        <option value="resumo" <?= $tipo_relatorio == 'resumo' ? 'selected' : '' ?>>Resumo por Motoboy</option>
                        <option value="detalhado" <?= $tipo_relatorio == 'detalhado' ? 'selected' : '' ?>>Detalhado (Pedido a Pedido)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <?php if ($tipo_relatorio === 'detalhado'): ?>
                            <tr>
                                <th>Pedido</th>
                                <th>Data</th>
                                <th>Motoboy</th>
                                <th>Cliente</th>
                                <th class="text-end">Taxa</th>
                                <th class="text-end">Total</th>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <th>Motoboy</th>
                                <th class="text-center">Qtd. Entregas</th>
                                <th class="text-end">Total Taxas (Comissão)</th>
                                <th class="text-end">Valor Total Pedidos</th>
                            </tr>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                        <?php 
                        $ultimo_motoboy = null;
                        foreach($relatorio as $row): 
                            if ($tipo_relatorio === 'detalhado') {
                                $geral_entregas++;
                                $geral_taxas += $row['taxa_entrega'];
                                $geral_valor += $row['valor_total'];
                            } else {
                                $geral_entregas += $row['qtd_entregas'];
                                $geral_taxas += $row['total_taxas'];
                                $geral_valor += $row['valor_bruto'];
                            }
                        ?>
                        <tr>
                            <?php if ($tipo_relatorio === 'detalhado'): ?>
                                <td><strong>#<?= $row['pedido_id'] ?></strong></td>
                                <td><small><?= date('d/m/Y H:i', strtotime($row['criado_em'])) ?></small></td>
                                <td><span class="badge bg-info text-dark"><?= $row['motoboy_nome'] ?></span></td>
                                <td><?= $row['cliente_nome'] ?? 'Não informado' ?></td>
                                <td class="text-end">R$ <?= number_format($row['taxa_entrega'], 2, ',', '.') ?></td>
                                <td class="text-end">R$ <?= number_format($row['valor_total'], 2, ',', '.') ?></td>
                            <?php else: ?>
                                <td><strong><?= $row['motoboy_nome'] ?></strong></td>
                                <td class="text-center"><?= $row['qtd_entregas'] ?></td>
                                <td class="text-end text-success fw-bold">R$ <?= number_format($row['total_taxas'], 2, ',', '.') ?></td>
                                <td class="text-end">R$ <?= number_format($row['valor_bruto'], 2, ',', '.') ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>

                        <?php if(empty($relatorio)): ?>
                        <tr>
                            <td colspan="<?= $tipo_relatorio === 'detalhado' ? 6 : 4 ?>" class="text-center text-muted py-5">
                                Nenhuma entrega finalizada no período selecionado.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="<?= $tipo_relatorio === 'detalhado' ? 4 : 1 ?>">TOTAIS GERAIS</td>
                            <td class="text-center"><?= $tipo_relatorio === 'detalhado' ? '' : $geral_entregas ?></td>
                            <td class="text-end text-success">R$ <?= number_format($geral_taxas, 2, ',', '.') ?></td>
                            <td class="text-end">R$ <?= number_format($geral_valor, 2, ',', '.') ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3 small text-muted">
        <i class="fas fa-info-circle"></i> Este relatório considera apenas pedidos com status <strong>finalizado</strong> e que possuem um motoboy vinculado.
    </div>
</div>
</body>
</html>