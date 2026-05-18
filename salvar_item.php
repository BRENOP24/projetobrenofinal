<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);

require_once 'config/conexao.php';
require_once 'config/sessao.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(['success' => false, 'erro' => 'Dados não recebidos']);
    exit;
}

$pedido_id  = $data['pedido_id'];
$produto_id = $data['produto_id'];
$qtd        = $data['quantidade'];
$valor      = $data['valor']; 
$subtotal   = $qtd * $valor;

try {
    $pdo->beginTransaction();

    // Ajustado para pedidos_itens (plural) e colunas valor_unitario/valor_total
    $stmt = $pdo->prepare("
        INSERT INTO pedidos_itens 
        (pedido_id, produto_id, quantidade, valor_unitario, valor_total)
        VALUES
        (:pedido, :produto, :qtd, :valor, :total)
    ");

    $stmt->execute([
        ':pedido'  => $pedido_id,
        ':produto' => $produto_id,
        ':qtd'     => $qtd,
        ':valor'   => $valor,
        ':total'   => $subtotal
    ]);

    // Atualiza o total na tabela pedidos
    $stmt = $pdo->prepare("
        UPDATE pedidos
        SET valor_total = (
            SELECT COALESCE(SUM(valor_total), 0)
            FROM pedidos_itens
            WHERE pedido_id = :pedido
        )
        WHERE id = :pedido
    ");
    $stmt->execute([':pedido' => $pedido_id]);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'erro' => $e->getMessage()]);
}