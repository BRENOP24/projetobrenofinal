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
        // Mude o status 'Saiu para entrega' se o seu sistema usar outro nome de status para rota
        $sql = "UPDATE pedidos_online SET motoboy_id = :moto, status = 'Saiu para entrega' WHERE id = :pedido";
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
    // Corrigido aqui: em PHP o operador de concatenação é o ponto (.), não o mais (+)
    echo json_encode(['status' => 'erro', 'msg' => $e->getMessage()]);
}
