<?php
// Certifique-se de que a sessão e a conexão estão com os caminhos corretos que você configurou
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

if (!isset($_SESSION['cliente_id'])) {
    header("Location: auth.php");
    exit;
}

$id_cliente = $_SESSION['cliente_id'];

// --- 1. SEÇÃO RESUMO ---
// Mantido apenas status 'Finalizado' para o total_gasto e total_pedidos não contar os cancelados
$queryResumo = "SELECT COUNT(id) as total_pedidos, COALESCE(SUM(valor_total), 0) as total_gasto 
                FROM pedidos_online WHERE cliente_id = ? AND status = 'Finalizado'";
$stmtResumo = $pdo->prepare($queryResumo);
$stmtResumo->execute([$id_cliente]);
$resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC);

$queryUltimo = "SELECT data_pedido FROM pedidos_online WHERE cliente_id = ? ORDER BY id DESC LIMIT 1";
$stmtUltimo = $pdo->prepare($queryUltimo);
$stmtUltimo->execute([$id_cliente]);
$ultimoPedido = $stmtUltimo->fetch(PDO::FETCH_ASSOC);

// --- 2. FILTROS HISTÓRICO DE PEDIDOS (FINALIZADOS E CANCELADOS) ---
// CORRIGIDO: Agora busca tanto 'Finalizado' quanto 'Cancelado'
$whereFiltros = "WHERE cliente_id = :id_cliente AND status IN ('Finalizado', 'Cancelado')";
$params = ['id_cliente' => $id_cliente];

if (!empty($_GET['data_inicio']) && !empty($_GET['data_fim'])) {
    $whereFiltros .= " AND data_pedido BETWEEN :data_inicio AND :data_fim";
    $params['data_inicio'] = $_GET['data_inicio'] . ' 00:00:00';
    $params['data_fim'] = $_GET['data_fim'] . ' 23:59:59';
}
if (!empty($_GET['num_pedido'])) {
    $whereFiltros .= " AND id = :num_pedido";
    $params['num_pedido'] = (int)$_GET['num_pedido'];
}

$queryHistorico = "SELECT * FROM pedidos_online $whereFiltros ORDER BY id DESC";
$stmtHist = $pdo->prepare($queryHistorico);
$stmtHist->execute($params);
$historico = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel Cliente - Say Now</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .stepper { display: flex; justify-content: space-between; position: relative; margin-bottom: 25px; }
        .stepper::before { content: ""; position: absolute; top: 18px; left: 0; width: 100%; height: 4px; background: #e0e0e0; z-index: 1; }
        .step { position: relative; z-index: 2; text-align: center; width: 20%; }
        .step-icon { width: 40px; height: 40px; border-radius: 50%; background: #e0e0e0; margin: 0 auto 5px; line-height: 40px; color: #fff; font-weight: bold; }
        .step.active .step-icon { background: #198754; }
        .step.active .step-text { color: #198754; font-weight: bold; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="#">SAY NOW - Relação de Pedidos + Status</a>
        <div class="d-flex">
            <span class="navbar-text text-white me-3">Olá, <?= htmlspecialchars($_SESSION['cliente_nome']) ?></span>
            <a href="perfil.php" class="btn btn-outline-light btn-sm me-2">Meu Perfil</a>
            <a href="logout.php" class="btn btn-danger btn-sm">Sair</a>
        </div>
    </div>
</nav>

<div class="container my-4">
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card p-3 shadow-sm bg-white">
                <h6 class="text-muted">Total de Pedidos</h6>
                <h3><?= $resumo['total_pedidos'] ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 shadow-sm bg-white">
                <h6 class="text-muted">Total gasto em todo seu histórico de pedidos</h6>
                <h3>R$ <?= number_format($resumo['total_gasto'], 2, ',', '.') ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 shadow-sm bg-white">
                <h6 class="text-muted">Último Pedido</h6>
                <h3><?= $ultimoPedido ? date('d/m/Y', strtotime($ultimoPedido['data_pedido'])) : 'Nenhum' ?></h3>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white font-weight-bold">Acompanhamento em Tempo Real (Pedidos Ativos)</div>
        <div class="card-body" id="container-pedidos-ativos">
            <p class="text-muted text-center">Buscando updates de pedidos ativos...</p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white">Histórico de Compras (Concluídos e Cancelados)</div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-4">
                <div class="col-md-3">
                    <label class="small fw-bold">Data Inicial</label>
                    <input type="date" name="data_inicio" class="form-control form-control-sm" value="<?= $_GET['data_inicio'] ?? '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold">Data Final</label>
                    <input type="date" name="data_fim" class="form-control form-control-sm" value="<?= $_GET['data_fim'] ?? '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold">Nº Pedido</label>
                    <input type="number" name="num_pedido" class="form-control form-control-sm" placeholder="Ex: 1042" value="<?= $_GET['num_pedido'] ?? '' ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-dark w-100">Filtrar Histórico</button>
                </div>
            </form>

            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Nº Pedido</th>
                        <th>Data</th>
                        <th>Valor Total</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($historico)): ?>
                        <tr><td colspan="5" class="text-center text-muted">Nenhum pedido encontrado com os parâmetros atuais.</td></tr>
                    <?php else: ?>
                        <?php foreach($historico as $ped): 
                            // CORRIGIDO: Define a cor do badge de acordo com a situação do pedido
                            $badgeClasse = ($ped['status'] === 'Cancelado') ? 'bg-danger' : 'bg-success';
                        ?>
                            <tr>
                                <td>#<?= $ped['id'] ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($ped['data_pedido'])) ?></td>
                                <td>R$ <?= number_format($ped['valor_total'], 2, ',', '.') ?></td>
                                <td><span class="badge <?= $badgeClasse ?>"><?= htmlspecialchars($ped['status']) ?></span></td>
                                <td><button class="btn btn-sm btn-outline-primary" onclick="abrirDetalhes(<?= $ped['id'] ?>)">Ver Detalhes</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" id="conteudo-modal-detalhes">
            </div>
    </div>
</div>

<script>
    function atualizarPedidosAtivos() {
        fetch('api_pedidos_ativos.php')
            .then(response => response.text())
            .then(html => {
                document.getElementById('container-pedidos-ativos').innerHTML = html;
            });
    }

    function abrirDetalhes(idPedido) {
        fetch('api_detalhes_pedido.php?id=' + idPedido)
            .then(response => response.text())
            .then(html => {
                document.getElementById('conteudo-modal-detalhes').innerHTML = html;
                const meuModal = new bootstrap.Modal(document.getElementById('modalDetalhes'));
                meuModal.show();
            });
    }

    atualizarPedidosAtivos();
    setInterval(atualizarPedidosAtivos, 30000); // 30 segundos exatos
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
