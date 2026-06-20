<?php
header('Content-Type: application/json');
require_once 'config/sessao.php';
require_once 'config/conexao.php';

// Captura o input JSON enviado pelo JavaScript
$json = file_get_contents('php://input');
$dados = json_decode($json, true);

// Valida a estrutura (Lote ou Individual)
if (!$dados || empty($dados['motoboy_id']) || empty($dados['pedidos']) || !is_array($dados['pedidos'])) {
    echo json_encode(['status' => 'erro', 'msg' => 'Dados incompletos (Faltando ID, Motoboy ou Origem)']);
    exit;
}

$motoboy_id = $dados['motoboy_id'];
$pedidos    = $dados['pedidos']; 

try {
    $pdo->beginTransaction();

    // =======================================================
    // QUERIES LOGÍSTICAS (ATUALIZA APENAS O MOTOBOY)
    // =======================================================
    // Removemos a alteração de status e a trava/injeção de caixa, já que o pedido já foi faturado
    $sqlSite    = "UPDATE pedidos_online SET motoboy_id = :moto WHERE id = :pedido";
    $sqlSistema = "UPDATE pedidos SET motoboy_id = :moto WHERE id = :pedido";

    $stmtSite    = $pdo->prepare($sqlSite);
    $stmtSistema = $pdo->prepare($sqlSistema);

    // =======================================================
    // PROCESSAMENTO EM LOTE
    // =======================================================
    foreach ($pedidos as $pedido) {
        $pedido_id = $pedido['id'];
        $origem    = $pedido['origem'];

        if (strtolower($origem) === 'site') {
            $stmtSite->execute([
                ':moto'   => $motoboy_id, 
                ':pedido' => $pedido_id
            ]);
        } else {
            $stmtSistema->execute([
                ':moto'   => $motoboy_id, 
                ':pedido' => $pedido_id
            ]);
        }
    }

    // Grava as alterações logísticas de uma só vez
    $pdo->commit();
    echo json_encode(['status' => 'sucesso']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'erro', 'msg' => 'Erro no servidor: ' . $e->getMessage()]);
}