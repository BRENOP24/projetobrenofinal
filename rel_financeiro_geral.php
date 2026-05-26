<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// 1. Filtros de Data e Hora (Padrao: mes atual completo)
$data_inicial = $_GET['data_inicial'] ?? date('Y-m-01');
$data_final   = $_GET['data_final']   ?? date('Y-m-t');
$hora_inicial = $_GET['hora_inicial'] ?? '00:00';
$hora_final   = $_GET['hora_final']   ?? '23:59';

// Montagem dos timestamps completos para busca precisa no banco de dados
$timestamp_inicial = $data_inicial . ' ' . $hora_inicial . ':00';
$timestamp_final   = $data_final . ' ' . $hora_final . ':59';

// 2. Parametros da Query Unificada
$params = [
    ':inicio' => $timestamp_inicial,
    ':fim'    => $timestamp_final
];

// 3. Query Consolidada (UNION ALL) que unifica Pedidos Locais e Pedidos Online
$sql = "
    SELECT 
        id,
        data_pedido,
        origem_canal,
        origem_tipo,
        cliente_nome,
        forma_pagamento,
        valor_total
    FROM (
        SELECT 
            p.id,
            p.data_pedido,
            'PRESENCIAL' AS origem_canal,
            p.origem_tipo AS origem_tipo,
            COALESCE(c.nome, 'Consumidor Final') AS cliente_nome,
            COALESCE(fp.descricao, 'Nao Informado') AS forma_pagamento,
            p.valor_total,
            p.situacao
        FROM pedidos p
        LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE p.situacao = 'finalizado'

        UNION ALL

        SELECT 
            po.id,
            po.data_pedido,
            'ONLINE' AS origem_canal,
            'delivery' AS origem_tipo,
            COALESCE(c.nome, 'Consumidor Online') AS cliente_nome,
            COALESCE(fp.descricao, 'Nao Informado') AS forma_pagamento,
            po.valor_total,
            'finalizado' AS situacao
        FROM pedidos_online po
        LEFT JOIN formas_pagamento fp ON po.forma_pagamento_id = fp.id
        LEFT JOIN clientes c ON po.cliente_id = c.id
    ) AS todas_vendas
    WHERE data_pedido BETWEEN :inicio AND :fim
    ORDER BY data_pedido DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Inicializacao dos Acumuladores de Receita para o DRE
$receita_mesas          = 0;
$receita_comandas       = 0;
$receita_balcao         = 0;
$receita_delivery_man   = 0;
$receita_online         = 0;

foreach ($vendas as $v) {
    $valor = (float)$v['valor_total'];
    
    if ($v['origem_canal'] === 'ONLINE') {
        $receita_online += $valor;
    } else {
        switch ($v['origem_tipo']) {
            case 'mesa':
                $receita_mesas += $valor;
                break;
            case 'comanda':
                $receita_comandas += $valor;
                break;
            case 'balcao':
                $receita_balcao += $valor;
                break;
            case 'delivery':
                $receita_delivery_man += $valor;
                break;
            default:
                $receita_balcao += $valor;
                break;
        }
    }
}

$receita_bruta_total = $receita_mesas + $receita_comandas + $receita_balcao + $receita_delivery_man + $receita_online;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>DRE - Receitas Consolidadas - Gestao Breno</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-dre { border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .table-dre tbody tr td { vertical-align: middle; padding: 12px 16px; }
        .indent-1 { padding-left: 35px !important; color: #495057; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; padding: 0; }
            .card-dre { box-shadow: none; }
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2><i class="fas fa-chart-line text-success"></i> DRE - Consolidacao de Receitas</h2>
        <div>
            <button onclick="window.print()" class="btn btn-dark me-2"><i class="fas fa-print"></i> Imprimir</button>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Painel Geral</a>
        </div>
    </div>

    <div class="card card-dre mb-4 no-print">
        <div class="card-body bg-white border-0 rounded-3">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Data Inicial</label>
                    <input type="date" name="data_inicial" class="form-control" value="<?= htmlspecialchars($data_inicial) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">Hora Inicial</label>
                    <input type="time" name="hora_inicial" class="form-control" value="<?= htmlspecialchars($hora_inicial) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Data Final</label>
                    <input type="date" name="data_final" class="form-control" value="<?= htmlspecialchars($data_final) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">Hora Final</label>
                    <input type="time" name="hora_final" class="form-control" value="<?= htmlspecialchars($hora_final) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100 fw-bold"><i class="fas fa-sync-alt"></i> Atualizar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-dre mb-4">
        <div class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center py-3">
            <span><i class="fas fa-wallet me-2"></i> DEMONSTRATIVO DE RESULTADO DO EXERCICIO (RECEITAS)</span>
            <span class="badge bg-white text-success font-monospace"><?= date('d/m/Y', strtotime($timestamp_inicial)) ?> ate <?= date('d/m/Y', strtotime($timestamp_final)) ?></span>
        </div>
        <div class="card-body p-0 bg-white rounded-bottom">
            <div class="table-responsive">
                <table class="table table-hover table-dre mb-0">
                    <thead>
                        <tr class="table-light border-bottom text-uppercase fs-7 text-muted">
                            <th>Estrutura Operacional</th>
                            <th class="text-end" style="width: 250px;">Faturamento Bruto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="table-success fw-bold fs-5 border-bottom">
                            <td>1. RECEITA OPERACIONAL BRUTA (FATURAMENTO)</td>
                            <td class="text-end text-success">R$ <?= number_format($receita_bruta_total, 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td class="indent-1"><i class="fas fa-utensils text-muted me-2"></i> 1.1 Consumo Local - Mesas</td>
                            <td class="text-end fw-semibold text-secondary">R$ <?= number_format($receita_mesas, 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td class="indent-1"><i class="fas fa-clipboard-list text-muted me-2"></i> 1.2 Consumo Local - Comandas</td>
                            <td class="text-end fw-semibold text-secondary">R$ <?= number_format($receita_comandas, 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td class="indent-1"><i class="fas fa-cash-register text-muted me-2"></i> 1.3 Vendas Rapidas - Balcao</td>
                            <td class="text-end fw-semibold text-secondary">R$ <?= number_format($receita_balcao, 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td class="indent-1"><i class="fas fa-motorcycle text-muted me-2"></i> 1.4 Delivery - Lancamento Manual</td>
                            <td class="text-end fw-semibold text-secondary">R$ <?= number_format($receita_delivery_man, 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td class="indent-1"><i class="fas fa-globe text-muted me-2"></i> 1.5 Delivery Online - Cardapio Plataforma Web</td>
                            <td class="text-end fw-semibold text-secondary">R$ <?= number_format($receita_online, 2, ',', '.') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card card-dre">
        <div class="card-header bg-dark text-white fw-bold py-3">
            <i class="fas fa-list-ul me-2"></i> Trilhas de Auditoria: Vendas Computadas na Baixa
        </div>
        <div class="card-body p-0 bg-white rounded-bottom">
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-striped table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light sticky-top">
                        <tr class="small fw-bold text-muted">
                            <th>ID do Pedido</th>
                            <th>Data/Hora Baixa</th>
                            <th>Canal Origem</th>
                            <th>Tipo Operacao</th>
                            <th>Cliente</th>
                            <th>Forma de Pagamento</th>
                            <th class="text-end">Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($vendas) > 0): ?>
                            <?php foreach ($vendas as $v): ?>
                                <tr>
                                    <td><strong>#<?= str_pad($v['id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                                    <td><?= date('d/m/Y H:i', strtotime($v['data_pedido'])) ?></td>
                                    <td>
                                        <?php if ($v['origem_canal'] === 'ONLINE'): ?>
                                            <span class="badge bg-success"><i class="fas fa-globe"></i> ONLINE</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="fas fa-store"></i> PRESENCIAL</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="text-capitalize text-muted font-monospace small bg-light px-2 py-1 rounded">
                                            <?= htmlspecialchars($v['origem_tipo']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($v['cliente_nome']) ?></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($v['forma_pagamento']) ?></small></td>
                                    <td class="text-end fw-bold text-dark">R$ <?= number_format($v['valor_total'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Nenhum lancamento finalizado encontrado neste periodo.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
