<?php
ob_start();

require_once 'config/conexao.php';

header('Content-Type: application/json');

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$status = $_POST['status'] ?? '';

$permitidos = ['aberto', 'em_analise', 'finalizado', 'cancelado'];

if (!$id || !in_array($status, $permitidos)) {
    ob_clean();
    echo json_encode(['erro' => true, 'msg' => 'Dados inválidos']);
    exit;
}

try {

    $stmt = $pdo->prepare("
        UPDATE pedidos_compra
        SET status = :status
        WHERE id = :id
    ");

    $stmt->execute([
        ':status' => $status,
        ':id' => $id
    ]);

    ob_clean();
    echo json_encode(['erro' => false]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'erro' => true,
        'msg' => $e->getMessage()
    ]);
}