<?php
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

// Recebe os dados exatamente como seu sistema precisa
$id_online   = $_POST['id'] ?? null;
$novoStatus  = $_POST['status'] ?? null;
$tipoEntrega = $_POST['tipo_entrega'] ?? null; // Captura se é 'retirada' ou 'delivery'

if (!$id_online || !$novoStatus) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados incompletos.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Busca pedido na tabela pedidos_online para garantir que ele existe
    $stmtPed = $pdo->prepare("SELECT id FROM pedidos_online WHERE id = ?");
    $stmtPed->execute([$id_online]);
    $pedOnline = $stmtPed->fetch(PDO::FETCH_ASSOC);

    if (!$pedOnline) {
        throw new Exception("Pedido não encontrado na tabela pedidos_online.");
    }

    // 2. Se o tipo de entrega foi enviado, atualiza o status E o tipo_entrega de forma dinâmica
    if (!empty($tipoEntrega)) {
        $stmtUpdate = $pdo->prepare("UPDATE pedidos_online SET status = ?, tipo_entrega = ? WHERE id = ?");
        $stmtUpdate->execute([$novoStatus, $tipoEntrega, $id_online]);
    } else {
        // Fallback caso não venha o tipo de entrega por algum motivo
        $stmtUpdate = $pdo->prepare("UPDATE pedidos_online SET status = ? WHERE id = ?");
        $stmtUpdate->execute([$novoStatus, $id_online]);
    }

    $pdo->commit();
    echo json_encode(['sucesso' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['sucesso' => false, 'erro' => "Erro no Banco: " . $e->getMessage()]);
}
