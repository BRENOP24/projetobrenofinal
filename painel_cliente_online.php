<?php
// Certifique-se de que a sessão e a conexão estão com os caminhos corretos que você configurou
require_once 'config/sessao_visitante.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

if (!isset($_SESSION['cliente_id'])) {
    header("Location: cliente_online.php");
    exit;
}

$id_cliente = $_SESSION['cliente_id'];

// --- 1. SEÇÃO RESUMO ---
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Painel Cliente - Say Now</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #e0e8f5 0%, #f5f7fa 100%); 
            min-height: 100vh;
        }
        
        /* Customização dos painéis centrais */
        .custom-card {
            border: none !important;
            border-radius: 1rem !important;
            background-color: #ffffff;
        }
        
        .card-header-custom {
            border-top-left-radius: 1rem !important;
            border-top-right-radius: 1rem !important;
            border-bottom: none;
            padding: 1rem 1.25rem;
        }

        /* Ajuste do foco de inputs de filtro */
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }

        /* Ajuste responsivo para o Stepper que vem da API */
        .stepper { display: flex; justify-content: space-between; position: relative; margin-bottom: 25px; }
        .stepper::before { content: ""; position: absolute; top: 18px; left: 0; width: 100%; height: 4px; background: #e0e0e0; z-index: 1; }
        .step { position: relative; z-index: 2; text-align: center; width: 20%; }
        .step-icon { width: 40px; height: 40px; border-radius: 50%; background: #e0e0e0; margin: 0 auto 5px; line-height: 40px; color: #fff; font-weight: bold; }
        .step.active .step-icon { background: #198754; }
        .step.active .step-text { color: #198754; font-weight: bold; }

        /* Correção para telas pequenas de celular no Stepper */
        @media (max-width: 576px) {
            .step-text { font-size: 10px; display: block; word-break: break-word; }
            .step-icon { width: 30px; height: 30px; line-height: 30px; font-size: 12px; }
            .stepper::before { top: 14px; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm py-3">
    <div class="container">
        <a class="navbar-brand fw-bold text-wrap" style="max-width: 60%; font-size: 1.1rem; letter-spacing: -0.5px;" href="#">
            SAY NOW - Pedidos Concluídos + Andamento <span class="fw-light text-muted" style="font-size: 0.9rem;">| Painel</span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarPainel">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarPainel">
            <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center mt-3 mt-lg-0">
                <span class="navbar-text text-white me-lg-4 mb-3 mb-lg-0 fw-medium">
                    Olá, <span class="text-info"><?= htmlspecialchars($_SESSION['cliente_nome']) ?></span>
                </span>
                <div class="d-flex flex-wrap gap-2">
                    <a href="perfil.php" class="btn btn-sm btn-outline-light rounded-2 px-3">Meu Perfil</a>
                    <a href="cardapio_online.php" class="btn btn-sm btn-primary rounded-2 px-3 fw-semibold">Acessar Cardápio</a>
                    <a href="logout.php" class="btn btn-sm btn-danger rounded-2 px-3">Sair</a>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="container my-5">
    
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-4">
            <div class="card custom-card p-4 shadow-sm h-100">
                <h6 class="text-uppercase text-muted fw-bold small mb-2" style="letter-spacing: 0.5px;">Total de Pedidos</h6>
                <h2 class="fw-bold m-0 text-dark"><?= $resumo['total_pedidos'] ?></h2>
            </div>
        </div>
        <div class="col-12 col-sm-4">
            <div class="card custom-card p-4 shadow-sm h-100">
                <h6 class="text-uppercase text-muted fw-bold small mb-2" style="letter-spacing: 0.5px;">Total Gasto Histórico</h6>
                <h2 class="fw-bold m-0 text-success">R$ <?= number_format($resumo['total_gasto'], 2, ',', '.') ?></h2>
            </div>
        </div>
        <div class="col-12 col-sm-4">
            <div class="card custom-card p-4 shadow-sm h-100">
                <h6 class="text-uppercase text-muted fw-bold small mb-2" style="letter-spacing: 0.5px;">Último Pedido</h6>
                <h2 class="fw-bold m-0 text-primary" style="font-size: 1.6rem; padding-top: 2px;">
                    <?= $ultimoPedido ? date('d/m/Y', strtotime($ultimoPedido['data_pedido'])) : 'Nenhum' ?>
                </h2>
            </div>
        </div>
    </div>

    <div class="card custom-card shadow-sm mb-4">
        <div class="card-header-custom bg-primary text-white fw-bold d-flex align-items-center">
            <span>Acompanhamento em Tempo Real (Pedidos Ativos)</span>
        </div>
        <div class="card-body p-4" id="container-pedidos-ativos">
            <div class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                <span class="text-muted small">Buscando updates de pedidos ativos...</span>
            </div>
        </div>
    </div>

    <div class="card custom-card shadow-sm">
        <div class="card-header-custom bg-secondary text-white fw-bold">
            Histórico de Compras (Concluídos e Cancelados)
        </div>
        <div class="card-body p-4">
            
            <form method="GET" class="row g-3 mb-4 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold text-secondary">Data Inicial</label>
                    <input type="date" name="data_inicio" class="form-control rounded-3" value="<?= $_GET['data_inicio'] ?? '' ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold text-secondary">Data Final</label>
                    <input type="date" name="data_fim" class="form-control rounded-3" value="<?= $_GET['data_fim'] ?? '' ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-semibold text-secondary">Nº Pedido</label>
                    <input type="number" name="num_pedido" class="form-control rounded-3" placeholder="Ex: 1042" value="<?= $_GET['num_pedido'] ?? '' ?>">
                </div>
                <div class="col-12 col-md-3">
                    <button type="submit" class="btn btn-dark w-100 rounded-3 fw-semibold py-2">Filtrar Histórico</button>
                </div>
            </form>

            <div class="table-responsive rounded-3 border">
                <table class="table table-hover align-middle mb-0" style="min-width: 650px;">
                    <thead class="table-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-3">Nº Pedido</th>
                            <th>Data</th>
                            <th>Valor Total</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($historico)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Nenhum pedido localizado no período.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($historico as $ped): 
                                $badgeClasse = ($ped['status'] === 'Cancelado') ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success';
                            ?>
                                <tr>
                                    <td class="ps-3"><strong>#<?= $ped['id'] ?></strong></td>
                                    <td class="text-secondary small"><?= date('d/m/Y H:i', strtotime($ped['data_pedido'])) ?></td>
                                    <td class="fw-semibold">R$ <?= number_format($ped['valor_total'], 2, ',', '.') ?></td>
                                    <td>
                                        <span class="badge <?= $badgeClasse ?> px-2.5 py-1.5 rounded-2 fw-semibold" style="font-size: 0.8rem;">
                                            <?= htmlspecialchars($ped['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <button class="btn btn-sm btn-outline-primary rounded-2 px-3" onclick="abrirDetalhes(<?= $ped['id'] ?>)">
                                            Ver Detalhes
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow" id="conteudo-modal-detalhes">
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
    setInterval(atualizarPedidosAtivos, 30000); // 30 segundos
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
