<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// Pegando as datas do GET
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim    = $_GET['data_fim'] ?? '';

$where = [];
$params = [];

if (!empty($data_inicio)) {
    $where[] = "a.data_auditoria >= ?";
    $params[] = $data_inicio . " 00:00:00";
}

if (!empty($data_fim)) {
    $where[] = "a.data_auditoria <= ?";
    $params[] = $data_fim . " 23:59:59";
}

$sql = "
    SELECT 
        a.*, 
        u.nome as usuario_nome,
        c_origem.data_abertura as data_caixa_origem,
        c_atual.data_abertura as data_caixa_atual
    FROM auditoria_financeira a
    LEFT JOIN usuarios u ON a.usuario_id = u.id
    LEFT JOIN controle_caixas c_origem ON a.caixa_origem_id = c_origem.id
    LEFT JOIN controle_caixas c_atual ON a.caixa_atual_id = c_atual.id
";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY a.data_auditoria DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$auditorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Auditoria | Gestão Breno</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f1f3f5; font-size: 0.85rem; }
        .container-fluid { max-width: 1300px; padding: 20px; }
        .card-header-custom { background: #212529; color: white; padding: 10px 15px; border-radius: 8px 8px 0 0; }
        
        /* Filtros compactos e alinhados */
        .filtro-container { 
            background: white; 
            padding: 12px; 
            border-radius: 8px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); 
            margin-bottom: 15px;
        }
        .form-compact { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
        .form-compact .group { display: flex; flex-direction: column; }
        .form-compact label { font-size: 11px; font-weight: bold; color: #6c757d; margin-bottom: 2px; text-transform: uppercase; }
        .form-compact .form-control-sm { width: 150px; height: 32px; font-size: 0.8rem; }
        
        /* Tabela */
        .table-custom { background: white; border-radius: 0 0 8px 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .table thead th { background: #f8f9fa; font-size: 11px; text-transform: uppercase; color: #495057; border-bottom: 2px solid #dee2e6; }
        .badge-tipo { font-size: 10px; padding: 4px 8px; border-radius: 4px; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-bold">🛡️ Auditoria Financeira</h5>
        <a href="dashboard.php" class="btn btn-sm btn-outline-dark">Voltar</a>
    </div>

    <!-- Barra de Filtro Compacta -->
    <div class="filtro-container">
        <form method="GET" class="form-compact">
            <div class="group">
                <label>Início</label>
                <input type="date" name="data_inicio" class="form-control form-control-sm" value="<?= $data_inicio ?>">
            </div>
            <div class="group">
                <label>Fim</label>
                <input type="date" name="data_fim" class="form-control form-control-sm" value="<?= $data_fim ?>">
            </div>
            
            <button type="submit" class="btn btn-sm btn-primary px-3 shadow-sm" style="height: 32px;">Filtrar</button>
            
        </form>
    </div>

    <!-- Tabela -->
    <div class="table-custom">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Data Estorno</th>
                    <th>Tipo Operação</th>
                    <th>ID Ref.</th>
                    <th>Valor</th>
                    <th>Caixa Origem</th>
                    <th>Caixa Destino</th>
                    <th>Responsável</th>
                    <th>Motivo do Estorno</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($auditorias): foreach ($auditorias as $a): ?>
                <tr>
                    <td class="fw-bold"><?= date('d/m/Y H:i', strtotime($a['data_auditoria'])) ?></td>
                    <td>
                        <span class="badge badge-tipo <?= strpos($a['tipo_operacao'], 'VENDA') !== false ? 'bg-info-subtle text-info border border-info-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?>">
                            <?= $a['tipo_operacao'] ?>
                        </span>
                    </td>
                    <td>#<?= $a['registro_origem_id'] ?></td>
                    <td class="text-success fw-bold">R$ <?= number_format($a['valor_estornado'], 2, ',', '.') ?></td>
                    <td>
                        <small class="d-block text-muted">Caixa #<?= $a['caixa_origem_id'] ?></small>
                        <small><?= date('d/m/Y', strtotime($a['data_caixa_origem'])) ?></small>
                    </td>
                    <td>
                        <small class="d-block text-muted">Caixa #<?= $a['caixa_atual_id'] ?></small>
                        <small><?= date('d/m/Y', strtotime($a['data_caixa_atual'])) ?></small>
                    </td>
                    <td><?= htmlspecialchars($a['usuario_nome']) ?></td>
                    <td class="text-muted italic small" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= $a['motivo'] ?>">
                        "<?= htmlspecialchars($a['motivo']) ?>"
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">Nenhum estorno registrado no período.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>