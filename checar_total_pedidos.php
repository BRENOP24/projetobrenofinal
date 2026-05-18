<?php
require_once 'config/conexao.php';

try {
    // Agora contamos apenas quantos pedidos estão com o status exatamente como 'Pendente'
    $sql = "SELECT COUNT(id) as total FROM pedidos_online WHERE status = 'Pendente'";
    $res = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode(['total_pendentes' => (int)$res['total']]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}