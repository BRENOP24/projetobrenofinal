<?php
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

// Resgata os parâmetros estritamente mapeados com o seu arquivo original
$id_online   = $_POST['id'] ?? null;
$novoStatus  = $_POST['status'] ?? null;
$tipoEntrega = $_POST['tipo_entrega'] ?? null; 

// Validação padrão do seu sistema original
if (!$id_online || !$novoStatus) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados incompletos recebidos pelo servidor.']);
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

    // 2. Executa o UPDATE dinamicamente com base na presença do tipo_entrega
    if ($tipoEntrega !== null && $tipoEntrega !== '') {
        // Se foi enviado (Retirada ou Delivery), atualiza ambos os campos
        $stmtUpdate = $pdo->prepare("UPDATE pedidos_online SET status = ?, tipo_entrega = ? WHERE id = ?");
        $stmtUpdate->execute([$novoStatus, $tipoEntrega, $id_online]);
    } else {
        // Se omitido (como na ação de Cancelar), atualiza estritamente apenas o status do fluxo
        $stmtUpdate = $pdo->prepare("UPDATE pedidos_online SET status = ? WHERE id = ?");
        $stmtUpdate->execute([$novoStatus, $id_online]);
    }

    $pdo->commit();
    echo json_encode(['sucesso' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['sucesso' => false, 'erro' => "Erro no Banco: " . $e->getMessage()]);
}
