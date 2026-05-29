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

    // 2. VALIDAÇÃO RIGOROSA DO CAIXA (Apenas se estiver finalizando o pedido)
    $id_caixa = null;
    if ($novoStatus === 'Finalizado') {
        $usuario_id = $_SESSION['usuario_id'] ?? null;
        $caixa_ativo = null;
        
        // Tentativa 1: Busca caixa aberto estritamente do usuário logado
        if ($usuario_id) {
            $sql_caixa = "SELECT id FROM controle_caixas WHERE usuario_id = ? AND LOWER(status) = 'aberto' LIMIT 1";
            $stmt_caixa = $pdo->prepare($sql_caixa);
            $stmt_caixa->execute([$usuario_id]);
            $caixa_ativo = $stmt_caixa->fetch(PDO::FETCH_ASSOC);
        }
        
        // Tentativa 2: Se não achou do usuário, busca QUALQUER caixa que esteja aberto no sistema
        if (empty($caixa_ativo)) {
            $sql_caixa = "SELECT id FROM controle_caixas WHERE LOWER(status) = 'aberto' LIMIT 1";
            $stmt_caixa = $pdo->query($sql_caixa);
            $caixa_ativo = $stmt_caixa->fetch(PDO::FETCH_ASSOC);
        }

        // TRAVA REAL: Se não encontrou NENHUM caixa com status 'aberto', bloqueia e avisa o usuário
        if (empty($caixa_ativo)) {
            echo json_encode([
                'sucesso' => false, 
                'erro' => 'Não há nenhum caixa aberto! Abra o caixa na tela de movimentação antes de finalizar este pedido.'
            ]);
            exit;
        }

        $id_caixa = (int)$caixa_ativo['id'];
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
