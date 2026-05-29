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

    // 2. VALIDAÇÃO FIEL AO SEU SISTEMA LOCAL (Apenas para o status Finalizado)
    $id_caixa = null;
    if ($novoStatus === 'Finalizado') {
        $usuario_id = $_SESSION['usuario_id'] ?? null;

        if (!$usuario_id) {
            echo json_encode(['sucesso' => false, 'erro' => 'Sessão de usuário expirada. Faça login novamente.']);
            exit;
        }

        // Query idêntica à da sua tela de pedido local
        $stmt_caixa = $pdo->prepare("SELECT id FROM controle_caixas WHERE usuario_id = ? AND status = 'aberto' LIMIT 1");
        $stmt_caixa->execute([$usuario_id]);
        $caixa = $stmt_caixa->fetch(PDO::FETCH_ASSOC);

        // Se o caixa não existir/não estiver aberto, bloqueia imediatamente
        if (!$caixa) {
            echo json_encode([
                'sucesso' => false, 
                'erro' => 'Não há nenhum caixa aberto para o seu usuário! Abra o caixa na tela de movimentação antes de finalizar pedidos.'
            ]);
            exit;
        }

        $id_caixa = (int)$caixa['id'];
    }

    // 3. Executa o UPDATE aplicando o id_caixa correto
    if ($novoStatus === 'Finalizado') {
        if ($tipoEntrega !== null && $tipoEntrega !== '') {
            $stmtUpdate = $pdo->prepare("UPDATE pedidos_online SET status = ?, tipo_entrega = ?, id_caixa = ? WHERE id = ?");
            $stmtUpdate->execute([$novoStatus, $tipoEntrega, $id_caixa, $id_online]);
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE pedidos_online SET status = ?, id_caixa = ? WHERE id = ?");
            $stmtUpdate->execute([$novoStatus, $id_caixa, $id_online]);
        }
    } else {
        // Fluxos intermediários (Pendente, Confirmado, Em Preparo, Cancelado)
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
