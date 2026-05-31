<?php
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';

if (!isset($_SESSION['cliente_id'])) exit('Não autenticado.');

$id_cliente = $_SESSION['cliente_id'];

// Mudamos de id_cliente para cliente_id na linha abaixo
$queryAtivos = "SELECT * FROM pedidos_online 
                WHERE cliente_id = ? AND status NOT IN ('Finalizado', 'Cancelado') 
                ORDER BY id DESC";
$stmt = $pdo->prepare($queryAtivos);
$stmt->execute([$id_cliente]);
$ativos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($ativos)) {
    echo '<p class="text-center text-muted mb-0">Você não possui nenhum pedido em andamento no momento.</p>';
    exit;
}

// CORRIGIDO: Ajustado 'Saiu para Entrega' com 'E' maiúsculo para bater com o banco de dados
$badges = [
    'Pendente'          => 'bg-secondary text-white',
    'Confirmado'        => 'bg-info text-dark',
    'Em preparação'     => 'bg-warning text-dark',
    'Pronto'            => 'bg-primary text-white',
    'Saiu para Entrega' => 'bg-dark text-white',
];

// CORRIGIDO: Unificado os arrays para evitar que um sobrescreva o outro e ajustado as maiúsculas
$passos = [
    'Pendente'          => 1,
    'Confirmado'        => 1,
    'Em preparação'     => 2,
    'Pronto'            => 3,
    'Saiu para Entrega' => 4,
    'Finalizado'        => 5
];

foreach ($ativos as $pedido):
    $passoAtual = $passos[$pedido['status']] ?? 1;
    $tipoEntrega = (!empty($pedido['endereco_entrega'])) ? '🔒 Delivery' : '🏃 Retirada no Balcão';
?>
    <div class="border rounded p-3 mb-3 bg-white shadow-xs">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <span class="fw-bold">Pedido #<?= $pedido['id'] ?></span> 
                <small class="text-muted ms-2"><?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?></small>
                <span class="ms-3 badge bg-light text-dark border"><?= $tipoEntrega ?></span>
            </div>
            <div>
                <span class="badge <?= $badges[$pedido['status']] ?? 'bg-secondary' ?> fs-6"><?= $pedido['status'] ?></span>
                <button class="btn btn-sm btn-link" onclick="abrirDetalhes(<?= $pedido['id'] ?>)">Ver itens</button>
            </div>
        </div>

        <div class="stepper my-4">
            <div class="step <?= ($passoAtual >= 1) ? 'active' : '' ?>">
                <div class="step-icon">1</div>
                <div class="step-text small">Recebido</div>
            </div>
            <div class="step <?= ($passoAtual >= 2) ? 'active' : '' ?>">
                <div class="step-icon">2</div>
                <div class="step-text small">Preparação</div>
            </div>
            <div class="step <?= ($passoAtual >= 3) ? 'active' : '' ?>">
                <div class="step-icon">3</div>
                <div class="step-text small">Pronto</div>
            </div>
            <div class="step <?= ($passoAtual >= 4) ? 'active' : '' ?>">
                <div class="step-icon">4</div>
                <div class="step-text small">Na Entrega</div>
            </div>
            <div class="step <?= ($passoAtual >= 5) ? 'active' : '' ?>">
                <div class="step-icon">5</div>
                <div class="step-text small">Finalizado</div>
            </div>
        </div>

        <div class="row small text-muted">
            <div class="col-md-4"><strong>Valor:</strong> R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></div>
            <div class="col-md-4"><strong>Pagamento:</strong> <?= htmlspecialchars($pedido['forma_pagamento'] ?? 'Não informada') ?></div>
        </div>
    </div>
<?php endforeach; ?>