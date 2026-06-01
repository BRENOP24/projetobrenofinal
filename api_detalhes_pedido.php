<?php
require_once 'config/sessao_visitante.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

if (!isset($_SESSION['cliente_id']) || empty($_GET['id'])) exit('Acesso negado.');

$id_pedido = (int)$_GET['id'];
$id_cliente = $_SESSION['cliente_id'];

// CORRIGIDO: Adicionado o JOIN para buscar a descrição da forma de pagamento pelo ID
$queryPedido = "SELECT p.*, f.descricao AS forma_pagamento_nome 
                FROM pedidos_online p
                LEFT JOIN formas_pagamento f ON f.id = p.forma_pagamento_id
                WHERE p.id = ? AND p.cliente_id = ?";

$stmt = $pdo->prepare($queryPedido);
$stmt->execute([$id_pedido, $id_cliente]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) exit('<div class="p-3 text-danger">Pedido não encontrado.</div>');

// Busca os itens do pedido relacionando com a tabela de produtos
$queryItens = "SELECT p.nome AS produto_nome, i.quantidade, i.preco_unitario, i.subtotal 
               FROM pedidos_online_itens i
               LEFT JOIN produtos p ON p.id = i.produto_id
               WHERE i.pedido_id = ?";
$stmtItens = $pdo->prepare($queryItens);
$stmtItens->execute([$id_pedido]);
$itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="modal-header">
    <h5 class="modal-title">Detalhes do Pedido #<?= $pedido['id'] ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <div class="row mb-3">
        <div class="col-md-6">
            <p class="mb-1"><strong>Data/Hora:</strong> <?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?></p>
            <p class="mb-1"><strong>Status Atual:</strong> <?= $pedido['status'] ?></p>
            <p class="mb-1"><strong>Forma de Pagamento:</strong> <?= htmlspecialchars($pedido['forma_pagamento_nome'] ?? 'Não informada') ?></p>
        </div>
        <div class="col-md-6 border-start">
            <p class="mb-1"><strong>Endereço de Entrega:</strong></p>
            <p class="text-muted small mb-0"><?= htmlspecialchars($pedido['endereco_completo'] ?? 'Retirada Agendada no Balcão') ?></p>
        </div>
    </div>

    <h6 class="border-bottom pb-2">Produtos Adquiridos</h6>
    <table class="table table-sm">
        <thead>
            <tr>
                <th>Produto</th>
                <th class="text-center">Qtd</th>
                <th class="text-end">Unitário</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itens as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['produto_nome'] ?? 'Produto não identificado') ?></td>
                <td class="text-center"><?= $item['quantidade'] ?></td>
                <td class="text-end">R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?></td>
                <td class="text-end">R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="row justify-content-end text-end mt-3 fw-bold">
        <div class="col-md-4">
            <p class="mb-1 text-muted small">Taxa de Entrega: R$ <?= number_format($pedido['taxa_entrega'] ?? 0, 2, ',', '.') ?></p>
            <h5 class="text-primary">Total: R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></h5>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
</div>
