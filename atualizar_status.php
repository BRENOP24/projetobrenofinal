<?php
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

// Resgata os parâmetros mapeados do JavaScript
$id_online   = $_POST['id'] ?? null;
$novoStatus  = $_POST['status'] ?? null;
$tipoEntrega = $_POST['tipo_entrega'] ?? null; 

// Validação padrão do sistema
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

    // 2. SE O STATUS FOR FINALIZADO, BUSCA O CAIXA ATIVO PARA VINCULAR
    $id_caixa = null;
    if ($novoStatus === 'Finalizado') {
        $usuario_id = $_SESSION['usuario_id'] ?? null;
        
        // Tentativa 1: Busca caixa do usuário logado
        if ($usuario_id) {
            $sql_caixa = "SELECT id FROM controle_caixas WHERE usuario_id = ? AND (status = 'aberto' OR status = 'ABERTO') LIMIT 1";
            $stmt_caixa = $pdo->prepare($sql_caixa);
            $stmt_caixa->execute([$usuario_id]);
            $caixa_ativo = $stmt_caixa->fetch(PDO::FETCH_ASSOC);
        }
        
        // Tentativa 2: Busca qualquer caixa aberto no sistema
        if (empty($caixa_ativo)) {
            $sql_caixa = "SELECT id FROM controle_caixas WHERE status = 'aberto' OR status = 'ABERTO' LIMIT 1";
            $stmt_caixa = $pdo->query($sql_caixa);
            $caixa_ativo = $stmt_caixa->fetch(PDO::FETCH_ASSOC);
        }

        // Define o ID do caixa ou usa o fallback 1 para testes do TCC
        $id_caixa = !empty($caixa_ativo) ? (int)$caixa_ativo['id'] : 1;
    }

    // 3. Monta e executa a Query de UPDATE baseada nos dados recebidos
    if ($novoStatus === 'Finalizado') {
        // Se está finalizando, força a gravação do id_caixa
        if ($tipoEntrega !== null && $tipoEntrega !== '') {
            $stmtUpdate = $pdo->prepare("UPDATE pedidos_online SET status = ?, tipo_entrega = ?, id_caixa = ? WHERE id = ?");
            $stmtUpdate->execute([$novoStatus, $tipoEntrega, $id_caixa, $id_online]);
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE pedidos_online SET status = ?, id_caixa = ? WHERE id = ?");
            $stmtUpdate->execute([$novoStatus, $id_caixa, $id_online]);
        }
    } else {
        // Fluxo normal (Pendente, Confirmado, Em Preparo, Cancelado)
        if ($tipoEntrega !== null && $tipoEntrega !== '') {
            $stmtUpdate = $pdo->prepare("UPDATE pedidos_online SET status = ?, tipo_entrega = ? WHERE id = ?");
            $stmtUpdate->execute([$novoStatus, $tipoEntrega, $id_online]);
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE pedidos_online SET status = ? WHERE id = ?");
            $stmtUpdate->execute([$novoStatus, $id_online]);
        }
    }

    $pdo->commit();
    echo json_encode(['sucesso' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['sucesso' => false, 'erro' => "Erro no Banco: " . $e->getMessage()]);
}
