<?php
header('Content-Type: application/json');
require_once 'config/sessao.php';
require_once 'config/conexao.php';

$pedido_id  = $_POST['pedido_id'] ?? null;
$motoboy_id = $_POST['motoboy_id'] ?? null;
$origem     = $_POST['origem'] ?? 'sistema'; // Padrão é sistema se não vier nada

if (!$pedido_id || !$motoboy_id) {
    echo json_encode(['status' => 'erro', 'msg' => 'Dados incompletos']);
    exit;
}

try {
    if ($origem === 'site') {
        // Atualiza a tabela do site (pedidos_online)
        // Alterado para 'Finalizado' para respeitar o CHECK constraint do seu banco Postgres
        $sql = "UPDATE pedidos_online SET motoboy_id = :moto, status = 'Finalizado' WHERE id = :pedido";
    } else {
        // Mantém a regra atual para os pedidos manuais (pedidos)
        $sql = "UPDATE pedidos SET motoboy_id = :moto, status = 'finalizado' WHERE id = :pedido";
    }

    $stmt = $pdo->prepare($sql);
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
    echo json_encode(['status' => 'erro', 'msg' => $e->getMessage()]);
}
