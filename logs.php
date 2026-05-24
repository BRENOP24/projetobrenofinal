<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';

// Proteção de Acesso
if (($_SESSION['nivel'] ?? '') !== 'admin') {
    header("Location: dashboard.php?erro=acesso_negado");
    exit();
}

// Higienização e Filtros
$filtro_usuario = filter_input(INPUT_GET, 'usuario', FILTER_SANITIZE_SPECIAL_CHARS);
$filtro_acao    = filter_input(INPUT_GET, 'acao', FILTER_SANITIZE_SPECIAL_CHARS);
$data_inicial   = filter_input(INPUT_GET, 'data_inicial', FILTER_SANITIZE_SPECIAL_CHARS);
$data_final     = filter_input(INPUT_GET, 'data_final', FILTER_SANITIZE_SPECIAL_CHARS);

$where = [];
$params = [];

if ($filtro_usuario) {
    $where[] = "usuario_nome ILIKE ?"; // ILIKE para PostgreSQL (case-insensitive)
    $params[] = "%$filtro_usuario%";
}
if ($filtro_acao) {
    $where[] = "acao = ?";
    $params[] = $filtro_acao;
}
// Filtro por intervalo de datas
if ($data_inicial && $data_final) {
    $where[] = "DATE(data_hora) BETWEEN ? AND ?";
    $params[] = $data_inicial;
    $params[] = $data_final;
} elseif ($data_inicial) {
    $where[] = "DATE(data_hora) >= ?";
    $params[] = $data_inicial;
} elseif ($data_final) {
    $where[] = "DATE(data_hora) <= ?";
    $params[] = $data_final;
}

$sql = "SELECT * FROM logs_sistema";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY data_hora DESC LIMIT 100"; // Limitado para performance

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Função para definir a cor da Badge dinamicamente
function getAcaoColor($acao) {
    return match (strtoupper($acao)) {
        'INSERÇÃO', 'CADASTRO' => 'bg-success',
        'EDIÇÃO', 'ALTERAÇÃO'  => 'bg-warning text-dark',
        'EXCLUSÃO', 'INATIVAÇÃO' => 'bg-danger',
        'LOGIN'                => 'bg-info text-dark',
        default                => 'bg-secondary',
    };
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs do Sistema | ERP SAY NOW</title>
    <!-- Usando Bootstrap para um visual moderno e responsivo -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-logs { border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .table thead { background-color: #343a40; color: white; }
        .badge-acao { font-size: 0.75rem; padding: 6px 10px; border-radius: 20px; text-transform: uppercase; }
        .filtro-bar { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px; border: 1px solid #dee2e6; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark">📋 Histórico de Logs</h2>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Voltar ao Painel</a>
    </div>

    <!-- Barra de Filtros -->
    <form method="GET" class="filtro-bar shadow-sm">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label small fw-bold">Usuário</label>
                <input type="text" name="usuario" class="form-control" placeholder="Ex: João..." value="<?= $filtro_usuario ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Ação</label>
                <select name="acao" class="form-select">
                    <option value="">Todas</option>
                    <option value="INSERÇÃO" <?= $filtro_acao == 'INSERÇÃO' ? 'selected' : '' ?>>Inserção</option>
                    <option value="EDIÇÃO" <?= $filtro_acao == 'EDIÇÃO' ? 'selected' : '' ?>>Edição</option>
                    <option value="EXCLUSÃO" <?= $filtro_acao == 'EXCLUSÃO' ? 'selected' : '' ?>>Exclusão</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Data Inicial</label>
                <input type="date" name="data_inicial" class="form-control" value="<?= $data_inicial ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Data Final</label>
                <input type="date" name="data_final" class="form-control" value="<?= $data_final ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </div>
    </form>

    <!-- Tabela de Resultados -->
    <div class="card card-logs overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="px-4">Horário</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Módulo/Tabela</th>
                        <th>Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">Nenhum registro encontrado.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($logs as $l): ?>
                    <tr>
                        <td class="px-4 text-muted" style="white-space: nowrap;">
                            <?= date('d/m/Y H:i', strtotime($l['data_hora'])) ?>
                        </td>
                        <td class="fw-semibold text-secondary">
                            <?= htmlspecialchars($l['usuario_nome']) ?>
                        </td>
                        <td>
                            <span class="badge badge-acao <?= getAcaoColor($l['acao']) ?>">
                                <?= htmlspecialchars($l['acao']) ?>
                            </span>
                        </td>
                        <td><code class="text-primary"><?= htmlspecialchars($l['tabela_afetada']) ?></code></td>
                        <td class="small text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($l['descricao']) ?>">
                            <?= htmlspecialchars($l['descricao']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
