<?php
header('Content-Type: application/json');
require_once 'config/sessao.php';
require_once 'config/conexao.php';

$pedido_id  = $_POST['pedido_id'] ?? null;
$motoboy_id = $_POST['motoboy_id'] ?? null;
$origem     = $_POST['origem'] ?? null; // Tiramos o padrão automático para investigar

if (!$pedido_id || !$motoboy_id || !$origem) {
    echo json_encode(['status' => 'erro', 'msg' => 'Dados incompletos (Faltando ID, Motoboy ou Origem)']);
    exit;
}

try {
    // Forçamos a comparação exata em minúsculo para evitar divergências (ex: 'site' ou 'Site')
    if (strtolower($origem) === 'site') {
        // Atualiza a tabela do site (pedidos_online)
        $sql = "UPDATE pedidos_online SET motoboy_id = :moto, status = 'Finalizado' WHERE id = :pedido";
    } else {
        // Atualiza a tabela manual (pedidos)
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
