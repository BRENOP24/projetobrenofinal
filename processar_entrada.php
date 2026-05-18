<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. Captura os dados do formulário
        $id_fornecedor   = $_POST['id_fornecedor'];
        $id_plano_conta  = $_POST['id_plano_conta'];
        $numero_nota     = $_POST['numero_nota'];
        $data_emissao    = $_POST['data_emissao'];
        $data_vencimento = $_POST['data_vencimento']; // Data da 1ª parcela
        $valor_total     = $_POST['valor_total'];
        
        // Novos campos de parcelamento
        $tipo_pagamento  = $_POST['tipo_pagamento'] ?? 'AVISTA';
        $qtd_parcelas    = ($tipo_pagamento === 'PARCELADO') ? (int)$_POST['parcelas'] : 1;

        // 2. Insere o cabeçalho da compra
        $sql_compra = "INSERT INTO compras (id_fornecedor, id_plano_conta, numero_nota, data_emissao, valor_total) 
                       VALUES (?, ?, ?, ?, ?)";
        $stmt_compra = $pdo->prepare($sql_compra);
        $stmt_compra->execute([$id_fornecedor, $id_plano_conta, $numero_nota, $data_emissao, $valor_total]);
        
        $id_compra = $pdo->lastInsertId();

        // 3. Processa os itens da nota
        if (isset($_POST['produtos']) && is_array($_POST['produtos'])) {
            $produtos = $_POST['produtos'];
            $qtds     = $_POST['qtds'];
            $custos   = $_POST['custos'];

            foreach ($produtos as $index => $id_produto) {
                $qtd = $qtds[$index];
                $custo = $custos[$index];
                $subtotal = $qtd * $custo;

                $sql_item = "INSERT INTO compras_itens (id_compra, id_produto, quantidade, valor_unitario, subtotal) 
                             VALUES (?, ?, ?, ?, ?)";
                $pdo->prepare($sql_item)->execute([$id_compra, $id_produto, $qtd, $custo, $subtotal]);

                $sql_estoque = "UPDATE produtos SET estoque = estoque + ? WHERE id = ?";
                $pdo->prepare($sql_estoque)->execute([$qtd, $id_produto]);
            }
        }

        // 4. GERAÇÃO DO CONTAS A PAGAR (Com Lógica de Parcelamento)
        $valor_parcela = round($valor_total / $qtd_parcelas, 2);
        
        for ($i = 1; $i <= $qtd_parcelas; $i++) {
            // Se for a última parcela, ajustamos os centavos de arredondamento
            if ($i == $qtd_parcelas) {
                $valor_parcela = $valor_total - ($valor_parcela * ($qtd_parcelas - 1));
            }

            // Calcula o vencimento: Parcela 1 é na data informada, demais somam 30 dias sucessivamente
            // (Pode ser ajustado para somar meses exatos se preferir)
            $meses_adicionais = $i - 1;
            $vencimento_atual = date('Y-m-d', strtotime($data_vencimento . " + $meses_adicionais month"));

            $descricao_financeiro = "NF: $numero_nota - Parcela $i/$qtd_parcelas";
            
            $sql_pagar = "INSERT INTO contas_pagar (id_compra, id_fornecedor, id_plano_conta, descricao, valor_total, data_vencimento, status) 
                          VALUES (?, ?, ?, ?, ?, ?, 'Pendente')";
            
            $pdo->prepare($sql_pagar)->execute([
                $id_compra, 
                $id_fornecedor, 
                $id_plano_conta, 
                $descricao_financeiro, 
                $valor_parcela, 
                $vencimento_atual
            ]);
        }

        // 5. Registra no Log
        registrarLog($pdo, 'ENTRADA', 'compras', "Lançou NF $numero_nota - Fornecedor ID $id_fornecedor - Total R$ $valor_total ($qtd_parcelas x)");

        $pdo->commit();
        header("Location: gerenciar_entrada.php?msg=entrada_ok");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "❌ Erro ao processar entrada: " . $e->getMessage();
    }
}