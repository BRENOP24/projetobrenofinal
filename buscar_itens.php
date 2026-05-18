<?php
require_once 'config/conexao.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(['erro' => 'ID do pedido não informado']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        i.produto_id,
        i.quantidade,
        i.preco_unitario,
        i.subtotal,
        p.nome AS produto_nome
    FROM pedidos_online_itens i
    LEFT JOIN produtos p ON p.id = i.produto_id
    WHERE i.pedido_id = ?
");

$stmt->execute([$id]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));