<?php
header('Content-Type: application/json');
require_once 'config/sessao.php';
require_once 'config/conexao.php';

$pedido_id = $_POST['pedido_id'] ?? null;
$motoboy_id = $_POST['motoboy_id'] ?? null;

if (!$pedido_id || !$motoboy_id) {
    echo json_encode(['status' => 'erro', 'msg' => 'Dados incompletos']);
    exit;
}

try {
    // Usando 'finalizado' que já é aceito pelo seu banco conforme o erro anterior
    $stmt = $pdo->prepare("UPDATE pedidos SET motoboy_id = :moto, status = 'finalizado' WHERE id = :pedido");
    $resultado = $stmt->execute([
        ':moto' => $motoboy_id, 
        ':pedido' => $pedido_id
    ]);

    if ($resultado) {
        echo json_encode(['status' => 'sucesso']);
    } else {
        echo json_encode(['status' => 'erro', 'msg' => 'Erro ao atualizar banco']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'msg' => $e.getMessage()]);
}