<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

header('Content-Type: application/json');

$pedido_id = $_POST['pedido_id'] ?? null;
$caixa_id = $_POST['caixa_id'] ?? null;
$pagamento_id = $_POST['pagamento_id'] ?? null;
$desconto = (float)($_POST['desconto'] ?? 0);

try {
    $pdo->beginTransaction();

    // 1. Busca os dados do pedido
    $stmt = $pdo->prepare("SELECT origem_tipo, origem_id FROM pedidos WHERE id = ?");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch();

    if (!$pedido) throw new Exception("Pedido não encontrado.");

    // 2. Calcula o total real dos itens usando o nome correto: pedidos_itens
    $stmt_total = $pdo->prepare("SELECT SUM(quantidade * valor_unitario) FROM pedidos_itens WHERE pedido_id = ?");
    $stmt_total->execute([$pedido_id]);
    $subtotal = (float)$stmt_total->fetchColumn();
    
    $total_final = $subtotal - $desconto;

    // 3. Finaliza o Pedido e vincula ao Caixa
    $sql_update = "UPDATE pedidos SET 
                    situacao = 'finalizado', 
                    caixa_id = ?, 
                    forma_pagamento_id = ?, 
                    desconto = ?, 
                    valor_total = ?,
                    data_pedido = NOW() 
                  WHERE id = ?";
    $pdo->prepare($sql_update)->execute([$caixa_id, $pagamento_id, $desconto, $total_final, $pedido_id]);

    // 4. Libera a Mesa ou Comanda (muda status para 'livre' ou 'aberto')
    if ($pedido['origem_tipo'] == 'mesa') {
        $pdo->prepare("UPDATE mesas SET status = 'livre' WHERE id = ?")->execute([$pedido['origem_id']]);
    } else {
        // Se for comanda, podemos desativar ou apenas mudar o status
        $pdo->prepare("UPDATE comandas SET status = 'fechado' WHERE id = ?")->execute([$pedido['origem_id']]);
    }

    $pdo->commit();
    echo json_encode(['sucesso' => true, 'pedido_id' => $pedido_id]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
}