<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

// Filtros
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$data_fim    = $_GET['data_fim'] ?? date('Y-m-d');
$busca       = $_GET['busca'] ?? '';
$origem_filtro = $_GET['origem_filtro'] ?? 'TODOS'; // Novo filtro: TODOS, PRESENCIAL ou ONLINE

// Montando os subblocos SQL baseados na escolha do usuário para poupar processamento do banco
$queries = [];

if ($origem_filtro === 'TODOS' || $origem_filtro === 'PRESENCIAL') {
    $queries[] = "
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
    ";
}

if ($origem_filtro === 'TODOS' || $origem_filtro === 'ONLINE') {
    $queries[] = "
        SELECT 
            po.id,
            po.data_pedido,
            COALESCE(c.nome, 'Consumidor') AS cliente_nome,
            COALESCE(c.cpf_cnpj, '-') AS cpf_cnpj,
            f.descricao AS pagamento,
            po.valor_total,
            po.tipo_entrega AS tipo,
            'ONLINE' AS origem
        FROM pedidos_online po
        LEFT JOIN clientes c ON po.cliente_id = c.id
        LEFT JOIN formas_pagamento f ON po.forma_pagamento_id = f.id
    ";
}

// Une as queries necessárias com UNION ALL
$sql_uniao = implode(" UNION ALL ", $queries);

// Monta a query principal envolvendo a união selecionada
$sql = "
SELECT * FROM (
    $sql_uniao
) AS pedidos_geral
WHERE data_pedido BETWEEN :inicio AND :fim
";

// Parâmetros de Data
$params = [
    ':inicio' => $data_inicio . ' 00:00:00',
    ':fim'    => $data_fim . ' 23:59:59'
];

// Busca por Nome, CPF ou ID
if (!empty($busca)) {
    $sql .= " AND (
        cliente_nome ILIKE :busca
        OR cpf_cnpj ILIKE :busca
    ";

    if (is_numeric($busca)) {
        $sql .= " OR CAST(id AS TEXT) = :id_busca";
        $params[':id_busca'] = $busca;
    }

    $sql .= ")";
    $params[':busca'] = "%$busca%";
}

// Ordenação final
$sql .= " ORDER BY data_pedido DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar os pedidos: " . $e->getMessage());
}
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

    <!-- Formulário de Filtros Modificado -->
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
                    <label class="form-label small fw-bold">Origem</label>
                    <select name="origem_filtro" class="form-select">
                        <option value="TODOS" <?= $origem_filtro === 'TODOS' ? 'selected' : '' ?>>Todos os Pedidos</option>
                        <option value="PRESENCIAL" <?= $origem_filtro === 'PRESENCIAL' ? 'selected' : '' ?>>Apenas Presenciais</option>
                        <option value="ONLINE" <?= $origem_filtro === 'ONLINE' ? 'selected' : '' ?>>Apenas Online (Cardápio)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Cliente (Nome, CPF ou Nº Pedido)</label>
                    <input type="text" name="busca" class="form-control" placeholder="Ex: João ou 123..." value="<?= htmlspecialchars($busca) ?>">
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
                        <th>Tipo / Canal</th>
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
                                    <span class="badge bg-success"><i class="fas fa-globe"></i> <?= htmlspecialchars($p['tipo']) ?> (SITE)</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark"><i class="fas fa-store"></i> <?= htmlspecialchars($p['tipo']) ?> (LOJA)</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['pagamento']) ?></td>
                            <td class="text-end fw-bold text-success">R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></td>
                            <td class="text-center">
                                <button onclick="window.open('imprimir_pedido.php?id=<?= $p['id'] ?>&origem=<?= $p['origem'] ?>', '_blank')" class="btn btn-sm btn-secondary">
                                    <i class="fas fa-print"></i> Reimprimir
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Nenhum pedido encontrado para este filtro ou período.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
