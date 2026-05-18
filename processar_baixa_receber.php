<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_conta'])) {

    $id_conta = (int)$_POST['id_conta'];
    $id_caixa = (int)$_POST['id_caixa'];
    $id_forma = (int)$_POST['id_forma_pagamento'];

    try {
        $pdo->beginTransaction();

        // 1. Busca os dados da conta
        $stmt = $pdo->prepare("SELECT valor_total FROM contas_receber WHERE id = ?");
        $stmt->execute([$id_conta]);
        $conta = $stmt->fetch();

        if (!$conta) { throw new Exception("Conta não encontrada."); }
        $valor = (float)$conta['valor_total'];

        // 2. ATUALIZA A CONTA (Status e Vínculo com o Caixa)
        // Verificamos na sua imagem que a coluna se chama id_caixa_baixa
        $stmt_update = $pdo->prepare("
            UPDATE contas_receber SET 
                status = 'Recebido',
                id_forma_pagamento = ?, 
                id_caixa_baixa = ?, 
                data_pagamento = NOW()
            WHERE id = ?
        ");
        $stmt_update->execute([$id_forma, $id_caixa, $id_conta]);

        // 3. REGISTRA A MOVIMENTAÇÃO (Para aparecer na Conferência)
        $stmt_mov = $pdo->prepare("
            INSERT INTO movimentacoes_caixa 
            (id_caixa, tipo, valor, descricao, data_mov) 
            VALUES (?, 'entrada', ?, 'RECEBIMENTO CLIENTE', NOW())
        ");
        $stmt_mov->execute([$id_caixa, $valor]);

        $pdo->commit();
        header("Location: contas_receber.php?msg=recebido_ok");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Esse comando abaixo vai te dizer EXATAMENTE qual o erro se falhar
        die("Erro ao baixar: " . $e->getMessage());
    }
}