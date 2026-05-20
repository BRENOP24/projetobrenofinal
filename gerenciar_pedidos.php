<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// 1. Definição dos Filtros Comuns
$data_inicial  = $_GET['data_inicial'] ?? date('Y-m-01');
$data_final    = $_GET['data_final']   ?? date('Y-m-t');
$origem_filtro = $_GET['origem']       ?? 'PRESENCIAL'; // Padrão: PRESENCIAL ou ONLINE
$busca         = $_GET['busca']        ?? '';

// Variáveis de listagem e totais
$pedidos = [];
$total_faturamento = 0;
$total_taxas = 0;
$qtd_validos = 0;

// Parâmetros base de data
$params = [$data_inicial . ' 00:00:00', $data_final . ' 23:59:59'];

if ($origem_filtro === 'ONLINE') {
    // -------------------------------------------------------------
    // REGRA DE NEGÓCIO: CARDÁPIO ONLINE (Tabela: pedidos_online)
    // -------------------------------------------------------------
    $status_filtro = $_GET['status_online'] ?? '';
    $where = "WHERE p.data_pedido BETWEEN ? AND ?";
    
    if (!empty($status_filtro)) {
        $where .= " AND p.status = ?";
        $params[] = $status_filtro;
    }
    
    if (!empty($busca)) {
        $where .= " AND (c.nome ILIKE ? OR c.telefone LIKE ?)";
        $params[] = "%$busca%";
        $params[] = "%$busca%";
    }

    $sql = "SELECT 
                p.id, 
                p.data_pedido, 
                p.valor_total,
                p.status,
                p.taxa_entrega,
                p.tipo_entrega AS tipo_movimento,
                p.bairro_entrega,
                c.nome AS nome_cliente, 
                c.telefone AS documento_ou_tel, 
                fp.descricao AS nome_pagamento
            FROM pedidos_online p
            INNER JOIN clientes_online c ON p.cliente_id = c.id
            LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
            $where ORDER BY p.data_pedido DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cálculos específicos do Online
    foreach ($pedidos as $p) { 
        if ($p['status'] !== 'Cancelado') {
            $total_faturamento += (float)$p['valor_total']; 
            $total_taxas += (float)$p['taxa_entrega'];
            $qtd_validos++;
        }
    }

} else {
    // -------------------------------------------------------------
    // REGRA DE NEGÓCIO: PRESENCIAL / LOJA (Tabela: pedidos)
    // -------------------------------------------------------------
    $where = "WHERE p.data_pedido BETWEEN ? AND ? 
              AND (p.status IN ('finalizado', 'cancelado') OR p.situacao IN ('finalizado', 'cancelado'))";
    
    if (!empty($busca)) {
        $where .= " AND (c.nome ILIKE ? OR c.cpf_cnpj LIKE ?)";
        $params[] = "%$busca%";
        $params[] = "%$busca%";
    }

    $sql = "SELECT 
                p.id, 
                p.data_pedido, 
                p.valor_total,
                COALESCE(p.status, p.situacao) AS status,
                0 AS taxa_entrega,
                p.tipo_venda AS tipo_movimento,
                'Balcão' AS bairro_entrega,
                COALESCE(c.nome, 'Consumidor Final') AS nome_cliente, 
                COALESCE(c.cpf_cnpj, '-') AS documento_ou_tel, 
                'Não informado' AS nome_pagamento
            FROM pedidos p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            $where ORDER BY p.data_pedido DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cálculos específicos do Presencial
    foreach ($pedidos as $p) { 
        if (in_array(strtolower($p['status']), ['finalizado'])) {
            $total_faturamento += (float)$p['valor_total']; 
            $qtd_validos++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciador de Pedidos Unificado - Gestão Breno</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5; }
        .container { max-width: 1250px; margin-top: 25px; background: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        
        .badge-status { padding: 6px 12px; border-radius: 15px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        /* Cores estendidas dos status */
        .status-pendente { background: #feebc8; color: #9c4221; }
        .status-confirmado { background: #bee3f8; color: #2a4365; }
        .status-empreparo { background: #e9d8fd; color: #553c9a; }
        .status-saiuparaentrega { background: #fefcbf; color: #744210; }
        .status-finalizado, .status-venda-finalizado { background: #c6f6d5; color: #22543d; }
        .status-cancelado, .status-venda-cancelado { background: #fed7d7; color: #822727; }

        .resumo-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .card-info { background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center; }
        .card-info h3 { margin: 0; font-size: 12px; color: #718096; text-transform: uppercase; font-weight: bold; }
        .card-info p { margin: 8px 0 0; font-size: 22px; font-weight: bold; color: #1a202c; }

        @media print {
            form, .no-print, .btn-voltar { display: none !important; }
            .container { box-shadow: none; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="container mb-5">
    
    <!-- Cabeçalho Dinâmico -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom <?= $origem_filtro === 'ONLINE' ? 'border-success' : 'border-primary' ?>">
        <h2 class="m-0 text-dark">
            <?= $origem_filtro === 'ONLINE' ? '🌐 Gerenciador de Pedidos Online' : '📈 Gerenciador de Vendas Presenciais' ?>
        </h2>
        <div class="no-print">
            <button onclick="exportarExcel()" class="btn btn-success btn-sm me-2"><i class="fas fa-file-excel"></i> Exportar Excel</button>
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Painel</a>
        </div>
    </div>

    <!-- Cards de Resumo Calculados Dinamicamente -->
    <div class="resumo-cards">
        <div class="card-info border-start border-4 <?= $origem_filtro === 'ONLINE' ? 'border-success' : 'border-primary' ?>">
            <h3>Faturamento (Pedidos Válidos)</h3>
            <p class="text-success">R$ <?= number_format($total_faturamento, 2, ',', '.') ?></p>
        </div>
        <?php if ($origem_filtro === 'ONLINE'): ?>
        <div class="card-info border-start border-4 border-warning">
            <h3>Total de Taxas de Entrega</h3>
            <p>R$ <?= number_format($total_taxas, 2, ',', '.') ?></p>
        </div>
        <?php endif; ?>
        <div class="card-info border-start border-4 border-dark">
            <h3>Qtd de Vendas/Pedidos</h3>
            <p><?= $qtd_validos ?> <span class="fs-6 text-muted fw-normal">(Total: <?= count($pedidos) ?>)</span></p>
        </div>
    </div>

    <!-- Painel de Filtros Inteligente -->
    <div class="card bg-light border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" id="formFiltro" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary">CANAL DE VENDA</label>
                    <select name="origem" class="form-select fw-bold" onchange="document.getElementById('formFiltro').submit();">
                        <option value="PRESENCIAL" <?= $origem_filtro === 'PRESENCIAL' ? 'selected' : '' ?>>🏬 Loja / Presencial</option>
                        <option value="ONLINE" <?= $origem_filtro === 'ONLINE' ? 'selected' : '' ?>>🌐 Site / Cardápio Online</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary">DATA INICIAL</label>
                    <input type="date" name="data_inicial" class="form-control" value="<?= $data_inicial ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary">DATA FINAL</label>
                    <input type="date" name="data_final" class="form-control" value="<?= $data_final ?>">
                </div>

                <!-- Filtro de Status exclusivo para pedidos Online -->
                <?php if ($origem_filtro === 'ONLINE'): ?>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary">STATUS ONLINE</label>
                    <select name="status_online" class="form-select">
                        <option value="">Todos</option>
                        <option value="Pendente" <?= ($_GET['status_online'] ?? '') === 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="Confirmado" <?= ($_GET['status_online'] ?? '') === 'Confirmado' ? 'selected' : '' ?>>Confirmado</option>
                        <option value="Em Preparo" <?= ($_GET['status_online'] ?? '') === 'Em Preparo' ? 'selected' : '' ?>>Em Preparo</option>
                        <option value="Saiu para Entrega" <?= ($_GET['status_online'] ?? '') === 'Saiu para Entrega' ? 'selected' : '' ?>>Saiu para Entrega</option>
                        <option value="Finalizado" <?= ($_GET['status_online'] ?? '') === 'Finalizado' ? 'selected' : '' ?>>Finalizado</option>
                        <option value="Cancelado" <?= ($_GET['status_online'] ?? '') === 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="<?= $origem_filtro === 'ONLINE' ? 'col-md-3' : 'col-md-4' ?>">
                    <label class="form-label small fw-bold text-secondary">BUSCAR CLIENTE</label>
                    <input type="text" name="busca" class="form-control" placeholder="Procurar nome ou doc..." value="<?= htmlspecialchars($busca) ?>">
                </div>
                
                <div class="col-md-1 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100 h-75" title="Filtrar"><i class="fas fa-search"></i></button>
                    <button type="button" onclick="window.print()" class="btn btn-secondary w-100 h-75" title="Imprimir"><i class="fas fa-print"></i></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de Resultados -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tabelaDados">
                <thead class="table-dark">
                    <tr>
                        <th>Data/Hora</th>
                        <th>Pedido</th>
                        <th>Cliente / Contato</th>
                        <th>Tipo / Local</th>
                        <th>Forma Pagamento</th>
                        <?php if ($origem_filtro === 'ONLINE'): ?><th>Taxa</th><?php endif; ?>
                        <th>Valor Total</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pedidos)): ?>
                        <tr>
                            <td colspan="<?= $origem_filtro === 'ONLINE' ? '8' : '7' ?>" class="text-center py-4 text-muted">
                                Nenhum registro encontrado para os filtros selecionados.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($pedidos as $p): 
                        // Formatação de classes CSS de acordo com o status retornado
                        $status_limpo = strtolower(str_replace(' ', '', $p['status']));
                        $classe_status = 'status-' . $status_limpo;
                    ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($p['data_pedido'])) ?></td>
                        <td><strong>#<?= str_pad($p['id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                        <td>
                            <span class="fw-bold text-secondary"><?= htmlspecialchars($p['nome_cliente']) ?></span><br>
                            <small class="text-muted"><?= htmlspecialchars($p['documento_ou_tel']) ?></small>
                        </td>
                        <td>
                            <?php if ($origem_filtro === 'ONLINE'): ?>
                                <?php if (strtolower($p['tipo_movimento']) === 'retirada'): ?>
                                    <span class="text-danger fw-bold"><i class="fas fa-store-alt"></i> Retirada</span>
                                <?php else: ?>
                                    <span class="text-primary"><i class="fas fa-motorcycle"></i> <?= htmlspecialchars($p['bairro_entrega']) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-capitalize text-success"><i class="fas fa-shopping-basket"></i> <?= htmlspecialchars($p['tipo_movimento']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($p['nome_pagamento']) ?></td>
                        <?php if ($origem_filtro === 'ONLINE'): ?>
                            <td class="text-muted">R$ <?= number_format($p['taxa_entrega'], 2, ',', '.') ?></td>
                        <?php endif; ?>
                        <td class="fw-bold text-dark">R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></td>
                        <td class="text-center">
                            <span class="badge-status <?= $classe_status ?>"><?= htmlspecialchars($p['status']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Script de Exportação corrigido -->
<script>
function exportarExcel() {
    let csv = [];
    let rows = document.querySelectorAll("#tabelaDados tr");
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) {
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/,/g, ".");
            row.push(data);
        }
        csv.push(row.join(";"));
    }

    let csv_string = csv.join("\n");
    let filename = 'gerenciador_pedidos_' + new Date().toLocaleDateString() + '.csv';
    let link = document.createElement("a");
    link.style.display = 'none';
    link.setAttribute('target', '_blank');
    link.setAttribute('href', 'data:text/csv;charset=utf-8,%EF%BB%BF' + encodeURIComponent(csv_string));
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>
</body>
</html>
