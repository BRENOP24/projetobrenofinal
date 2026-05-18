<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

header('Content-Type: application/json');

$fornecedor_id = $_POST['fornecedor_id'] ?? null;
$observacao    = $_POST['observacao'] ?? '';
$itens_json    = $_POST['itens'] ?? '[]';

$itens = json_decode($itens_json, true);

if (!$fornecedor_id || empty($itens)) {
    echo json_encode([
        'erro' => true,
        'msg' => 'Dados inválidos'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    // calcula total
    $total = 0;
    foreach ($itens as $item) {
        $total += $item['subtotal'];
    }

    // 1. cria pedido
    $stmt = $pdo->prepare("
        INSERT INTO pedidos_compra 
        (fornecedor_id, observacao, total)
        VALUES (:fornecedor_id, :observacao, :total)
        RETURNING id
    ");

    $stmt->execute([
        ':fornecedor_id' => $fornecedor_id,
        ':observacao'    => $observacao,
        ':total'         => $total
    ]);

    $pedido_id = $stmt->fetchColumn();

    // 2. insere itens
    $stmtItem = $pdo->prepare("
        INSERT INTO pedidos_compra_itens
        (pedido_id, produto_id, quantidade, preco_unitario, subtotal)
        VALUES (:pedido_id, :produto_id, :quantidade, :preco, :subtotal)
    ");

    foreach ($itens as $item) {
        $stmtItem->execute([
            ':pedido_id'  => $pedido_id,
            ':produto_id' => $item['produto_id'],
            ':quantidade' => $item['quantidade'],
            ':preco'      => $item['preco'],
            ':subtotal'   => $item['subtotal']
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'erro' => false,
        'msg' => 'Pedido salvo com sucesso',
        'pedido_id' => $pedido_id
    ]);

} catch (Exception $e) {

    $pdo->rollBack();

    echo json_encode([
        'erro' => true,
        'msg' => 'Erro ao salvar pedido',
        'detalhe' => $e->getMessage()
    ]);
}