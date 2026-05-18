<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_conta'])) {
    $id_conta = (int)$_POST['id_conta'];
    $id_caixa = (int)$_POST['id_caixa']; // ID da tabela controle_caixas
    $id_forma = (int)$_POST['id_forma_pagamento'];
    $usuario_id = $_SESSION['usuario_id'];

    try {
        $pdo->beginTransaction();

        // 1. Busca os detalhes da conta para o histórico
        $stmt_dados = $pdo->prepare("
            SELECT cp.valor_total, f.razao_social 
            FROM contas_pagar cp 
            JOIN fornecedores f ON cp.id_fornecedor = f.id 
            WHERE cp.id = ?
        ");
        $stmt_dados->execute([$id_conta]);
        $conta = $stmt_dados->fetch();

        if ($conta) {
            $valor_pago = $conta['valor_total'];
            $fornecedor = $conta['razao_social'];

            // 2. Atualiza o status da conta para 'Pago'
            $stmt_update = $pdo->prepare("UPDATE contas_pagar SET 
                status = 'Pago', 
                id_forma_pagamento = ?, 
                id_controle_caixa = ?, 
                data_pagamento = NOW() 
                WHERE id = ?");
            $stmt_update->execute([$id_forma, $id_caixa, $id_conta]);

            // 3. REGISTRA A SAÍDA NO CAIXA (Para bater com o fechamento)
            // Note: Usei a descrição do fornecedor para você saber exatamente para onde foi o dinheiro
            $descricao_mov = "PAGTO FORNECEDOR: " . $fornecedor;
            
            // Verifique se sua tabela de movimentações chama-id_caixa ou controle_caixa_id
            $stmt_mov = $pdo->prepare("INSERT INTO movimentacoes_caixa 
                (id_caixa, tipo, valor, descricao, data_mov) 
                VALUES (?, 'saida', ?, ?, NOW())");
            $stmt_mov->execute([$id_caixa, $valor_pago, $descricao_mov]);
        }

        $pdo->commit();
        header("Location: contas_pagar.php?msg=pago_ok");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro ao processar pagamento: " . $e->getMessage());
    }
}