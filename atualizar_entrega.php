<?php
header('Content-Type: application/json');
require_once 'config/sessao.php';
require_once 'config/conexao.php';

$pedido_id  = $_POST['pedido_id'] ?? null;
$motoboy_id = $_POST['motoboy_id'] ?? null;
$origem     = $_POST['origem'] ?? null; 

if (!$pedido_id || !$motoboy_id || !$origem) {
    echo json_encode(['status' => 'erro', 'msg' => 'Dados incompletos (Faltando ID, Motoboy ou Origem)']);
    exit;
}

try {
    $pdo->beginTransaction();

    // =======================================================
    // [TRAVA DO CAIXA] Busca se existe um caixa 'aberto'
    // =======================================================
    $usuario_id = $_SESSION['usuario_id'] ?? null;
    
    // Tenta buscar primeiro o caixa do usuário logado, se não achar, pega o geral aberto
    if ($usuario_id) {
        $sql_caixa = "SELECT id FROM controle_caixas WHERE usuario_id = ? AND status = 'aberto' LIMIT 1";
        $stmt_caixa = $pdo->prepare($sql_caixa);
        $stmt_caixa->execute([$usuario_id]);
    } else {
        $sql_caixa = "SELECT id FROM controle_caixas WHERE status = 'aberto' LIMIT 1";
        $stmt_caixa = $pdo->query($sql_caixa);
    }
    
    $caixa_ativo = $stmt_caixa->fetch(PDO::FETCH_ASSOC);

    // Se o caixa estiver fechado, barra na hora e desfaz qualquer início de operação
    if (!$caixa_ativo) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'erro', 'msg' => 'O caixa está fechado! Você precisa abrir o caixa para poder finalizar pedidos e computar os valores.']);
        exit;
    }

    $id_caixa = (int)$caixa_ativo['id'];

    // =======================================================
    // ATUALIZAÇÃO DOS PEDIDOS COM ID_CAIXA
    // =======================================================
    if (strtolower($origem) === 'site') {
        // Atualiza a tabela do site (pedidos_online) - Injetando o id_caixa
        $sql = "UPDATE pedidos_online SET motoboy_id = :moto, status = 'Finalizado', id_caixa = :caixa WHERE id = :pedido";
    } else {
        // Atualiza a tabela manual (pedidos) - Injetando o id_caixa
        $sql = "UPDATE pedidos SET motoboy_id = :moto, status = 'finalizado', id_caixa = :caixa WHERE id = :pedido";
    }

    $stmt = $pdo->prepare($sql);
    $resultado = $stmt->execute([
        ':moto' => $motoboy_id, 
        ':pedido' => $pedido_id,
        ':caixa' => $id_caixa
    ]);

    if ($resultado) {
        $pdo->commit();
        echo json_encode(['status' => 'sucesso']);
    } else {
        $pdo->rollBack();
        echo json_encode(['status' => 'erro', 'msg' => 'Erro ao atualizar banco']);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'erro', 'msg' => $e->getMessage()]);
}
