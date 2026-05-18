<?php
header('Content-Type: application/json');
ini_set('display_errors', 0); // Evita que erros de texto quebrem o JSON

require_once 'config/conexao.php';

try {
    $pedido_id = $_GET['pedido_id'] ?? null;

    if (!$pedido_id) {
        echo json_encode([]);
        exit;
    }

    // Note o uso de 'pedidos_itens' e 'valor_unitario / valor_total'
    $sql = "SELECT 
                pi.id, 
                pi.quantidade, 
                pi.valor_unitario AS preco_unitario,
                pi.valor_total AS total,
                p.nome AS produto
            FROM pedidos_itens pi
            JOIN produtos p ON p.id = pi.produto_id
            WHERE pi.pedido_id = :pedido
            ORDER BY pi.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':pedido' => $pedido_id]);
    
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Retorna a lista para o JavaScript
    echo json_encode($itens);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["erro" => $e->getMessage()]);
}