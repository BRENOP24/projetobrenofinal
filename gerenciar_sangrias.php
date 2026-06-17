<?php
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';

if ($_SESSION['nivel'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// --- FILTROS ---
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');

// --- BUSCA DAS SANGRIAS ---
$sql = "
    SELECT s.*, u.nome as operador, c.data_abertura
    FROM sangrias s
    JOIN controle_caixas c ON s.caixa_id = c.id
    JOIN usuarios u ON c.usuario_id = u.id
    WHERE s.data_hora BETWEEN :data_inicio AND :data_fim
    ORDER BY s.data_hora DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':data_inicio' => $data_inicio . ' 00:00:00',
    ':data_fim'    => $data_fim . ' 23:59:59'
]);
$sangrias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totalizador do período filtrado
$total_periodo = array_sum(array_column($sangrias, 'valor'));
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relação de Sangrias - Gestão Breno</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header-section { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; margin-bottom: 20px; padding-bottom: 15px; }
        .btn-voltar { background: #6c757d; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; font-size: 0.9rem; }
        
        /* Layout dos filtros alinhado */
        .form-filtros { display: flex; gap: 15px; align-items: flex-end; background: #f8f9fa; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e9ecef; }
        .form-grupo { display: flex; flex-direction: column; gap: 5px; }
        .form-grupo label { font-size: 0.85rem; font-weight: bold; color: #495057; }
        .form-controle { padding: 6px 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 0.9rem; height: 35px; box-sizing: border-box; }
        
        /* Botão de filtrar ajustado e menor */
        .btn-filtrar { background: #007bff; color: white; border: none; padding: 0 15px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer; height: 35px; display: inline-flex; align-items: center; justify-content: center; transition: background 0.2s; }
        .btn-filtrar:hover { background: #0056b3; }
        
        .card-total { background: #fff5f5; color: #c53030; padding: 15px; border-radius: 8px; font-size: 1.1rem; font-weight: bold; margin-bottom: 20px; border: 1px solid #feb2b2; text-align: right; }
        .table-sangrias { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table-sangrias th { background: #f1f3f5; padding: 12px; text-align: left; font-size: 0.85rem; color: #495057; border-bottom: 2px solid #dee2e6; }
        .table-sangrias td { padding: 12px; border-bottom: 1px solid #eee; font-size: 0.95rem; }
        .btn-imprimir { background: #17a2b8; color: white; text-decoration: none; padding: 5px 10px; border-radius: 4px; font-size: 0.85rem; font-weight: bold; }
        .btn-imprimir:hover { background: #138496; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-section">
        <h2 style="margin:0;">🚨 Relação de Sangrias Efetuadas</h2>
        <a href="dashboard.php" class="btn-voltar">⬅ Voltar ao Painel</a>
    </div>

    <form method="GET" class="form-filtros">
        <div class="form-grupo">
            <label for="data_inicio">Data Inicial</label>
            <input type="date" name="data_inicio" id="data_inicio" class="form-controle" value="<?= htmlspecialchars($data_inicio) ?>">
        </div>
        <div class="form-grupo">
            <label for="data_fim">Data Final</label>
            <input type="date" name="data_fim" id="data_fim" class="form-controle" value="<?= htmlspecialchars($data_fim) ?>">
        </div>
        <button type="submit" class="btn-filtrar">🔍 Filtrar</button>
    </form>

    <div class="card-total">
        💸 Total Retirado no Período: R$ <?= number_format($total_periodo, 2, ',', '.') ?>
    </div>

    <?php if (empty($sangrias)): ?>
        <p style="text-align:center; color:#666; margin-top: 30px;">Nenhuma sangria encontrada para este período.</p>
    <?php else: ?>
        <table class="table-sangrias">
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Operador (Caixa)</th>
                    <th>Motivo</th>
                    <th>Observação</th>
                    <th>Valor Retirado</th>
                    <th style="text-align: center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sangrias as $s): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($s['data_hora'])) ?></td>
                    <td><?= htmlspecialchars($s['operador']) ?> (Cod: <?= $s['caixa_id'] ?>)</td>
                    <td><?= htmlspecialchars($s['motivo']) ?></td>
                    <td><?= htmlspecialchars($s['observacao'] ?: '-') ?></td>
                    <td style="color: #dc3545; font-weight: bold;">R$ <?= number_format($s['valor'], 2, ',', '.') ?></td>
                    <td style="text-align: center;">
                        <a href="imprimir_sangria.php?id=<?= $s['id'] ?>" target="_blank" class="btn-imprimir">🖨 Comprovante</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>