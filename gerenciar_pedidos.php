<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

// Filtros
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$data_fim    = $_GET['data_fim']    ?? date('Y-m-d');
$busca       = $_GET['busca']       ?? '';
$origem      = $_GET['origem']      ?? 'TODOS'; // Filtro de origem

// Parâmetros base da query
$params = [
    ':inicio' => $data_inicio . ' 00:00:00',
    ':fim'    => $data_fim . ' 23:59:59'
];

// Construção dinâmica das subqueries baseada no filtro de origem
$subqueries = [];

// Se o usuário quer TODOS ou PRESENCIAL
if ($origem === 'TODOS' || $origem === 'PRESENCIAL') {
    $sql_p = "
        SELECT 
            p.id,
            p.data_pedido,
            COALESCE(c.nome, 'Consumidor') AS cliente_nome,
            COALESCE(c.cpf_cnpj, '-') AS cpf_cnpj,
            f.descricao AS pagamento,
            p.valor_total,
            p.tipo_venda AS tipo,
            'PRESENCIAL' AS origem
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN formas_pagamento f ON p.forma_pagamento_id = f.id
        WHERE p.data_pedido BETWEEN :inicio AND :fim
    ";
    
    if (!empty($busca) && is_numeric($busca)) {
        $sql_p .= " AND p.id = :id_busca_p";
        $params[':id_busca_p'] = $busca;
    }
    
    $subqueries[] = $sql_p;
}

// Se o usuário quer TODOS ou ONLINE
if ($origem === 'TODOS' || $origem === 'ONLINE') {
    $sql_po = "
        SELECT 
            po.id,
            po.data_pedido,
            COALESCE(co.nome, 'Consumidor') AS cliente_nome,
            COALESCE(co.cpf, '-') AS cpf_cnpj, --  CORRIGIDO: lendo a coluna real 'cpf' do site
            f.descricao AS pagamento,
            po.valor_total,
            po.tipo_entrega AS tipo,
            'ONLINE' AS origem
        FROM pedidos_online po
        LEFT JOIN clientes_online co ON po.cliente_id = co.id -- Apontando para clientes_online
        LEFT JOIN formas_pagamento f ON po.forma_pagamento_id = f.id
        WHERE po.data_pedido BETWEEN :inicio AND :fim
    ";
    
    if (!empty($busca) && is_numeric($busca)) {
        $sql_po .= " AND po.id = :id_busca_po";
        $params[':id_busca_po'] = $busca;
    }
    
    $subqueries[] = $sql_po;
}

// Junta as queries que fazem sentido para o filtro atual
$sql_final = "SELECT * FROM ( " . implode(" UNION ALL ", $subqueries) . " ) AS pedidos_geral WHERE 1=1";

// Filtro de texto na barra de busca (Nome ou CPF)
if (!empty($busca)) {
    $sql_final .= " AND (
        cliente_nome LIKE :busca
        OR cpf_cnpj LIKE :busca
    )";
    $params[':busca'] = "%$busca%";
}

$sql_final .= " ORDER BY data_pedido DESC";

$stmt = $pdo->prepare($sql_final);
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Pedidos - Gestão Breno</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-file-invoice-dollar text-primary"></i> Gerenciador de Pedidos</h2>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-home"></i> Voltar ao Painel</a>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Data Inicial</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($data_inicio) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Data Final</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($data_fim) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Origem do Pedido</label>
                    <select name="origem" class="form-select">
                        <option value="TODOS" <?= $origem === 'TODOS' ? 'selected' : '' ?>>Todos os Pedidos</option>
                        <option value="PRESENCIAL" <?= $origem === 'PRESENCIAL' ? 'selected' : '' ?>>Presencial (Manual)</option>
                        <option value="ONLINE" <?= $origem === 'ONLINE' ? 'selected' : '' ?>>Online (Plataforma)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Buscar por Cliente, CPF ou Nº</label>
                    <input type="text" name="busca" class="form-control" placeholder="Ex: João, CPF ou número do pedido" value="<?= htmlspecialchars($busca) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Data/Hora</th>
                        <th>Cliente</th>
                        <th>Origem / Tipo</th>
                        <th>Pagamento</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pedidos) > 0): ?>
                        <?php foreach ($pedidos as $p): ?>
                        <tr>
                            <td><strong>#<?= $p['id'] ?></strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($p['data_pedido'])) ?></td>
                            <td>
                                <?= htmlspecialchars($p['cliente_nome']) ?><br>
                                <small class="text-muted"><?= htmlspecialchars($p['cpf_cnpj']) ?></small>
                            </td>
                            <td>
                                <?php if ($p['origem'] === 'ONLINE'): ?>
                                    <span class="badge bg-success text-white"><i class="fas fa-globe"></i> Online</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-white"><i class="fas fa-store"></i> Presencial</span>
                                <?php endif; ?>
                                <small class="text-muted d-block mt-1"><?= htmlspecialchars($p['tipo']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($p['pagamento'] ?? 'Não informado') ?></td>
                            <td class="text-end fw-bold text-success">R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></td>
                            <td class="text-center">
                                <button onclick="window.open('imprimir_pedido.php?id=<?= $p['id'] ?>&origem=<?= $p['origem'] ?>', '_blank')" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-print"></i> Reimprimir
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Nenhum pedido encontrado para este período ou filtro.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
