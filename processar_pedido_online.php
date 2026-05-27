<?php
header('Content-Type: application/json');
require_once 'config/conexao.php';

$json = file_get_contents('php://input');
$dados = json_decode($json, true);

if (!$dados) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos recebidos.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Limpa CPF
    $cpf_limpo = preg_replace('/[^0-9]/', '', $dados['cliente_cpf']);
    $cliente_id = null;

    $tipo_entrega = $dados['tipo_entrega'] ?? 'entrega';
    $endereco_pedido = ($tipo_entrega === 'entrega') ? $dados['endereco_completo'] : 'Retirada no Balcão';
    $bairro_pedido = ($tipo_entrega === 'entrega') ? $dados['bairro_entrega'] : null;
    $taxa_entrega = ($tipo_entrega === 'entrega') ? $dados['taxa_entrega'] : 0.00;

    /* ==============================================================
       1. CLIENTE
    ============================================================== */
    $stmtVerificaCli = $pdo->prepare("SELECT id FROM clientes_online WHERE cpf = ? LIMIT 1");
    $stmtVerificaCli->execute([$cpf_limpo]);
    $clienteExistente = $stmtVerificaCli->fetch(PDO::FETCH_ASSOC);

    if ($clienteExistente) {
        $cliente_id = $clienteExistente['id'];
        
        if ($tipo_entrega === 'entrega') {
            $stmtUpdateCli = $pdo->prepare("UPDATE clientes_online SET telefone = ?, endereco = ?, bairro = ? WHERE id = ?");
            $stmtUpdateCli->execute([
                $dados['cliente_telefone'],
                $dados['endereco_completo'],
                $dados['bairro_entrega'],
                $cliente_id
            ]);
        } else {
            $stmtUpdateCli = $pdo->prepare("UPDATE clientes_online SET telefone = ? WHERE id = ?");
            $stmtUpdateCli->execute([$dados['cliente_telefone'], $cliente_id]);
        }
    } else {
        $endereco_cli = ($tipo_entrega === 'entrega') ? $dados['endereco_completo'] : null;
        $bairro_cli = ($tipo_entrega === 'entrega') ? $dados['bairro_entrega'] : null;

        $stmtInsertCli = $pdo->prepare("
            INSERT INTO clientes_online (nome, cpf, telefone, endereco, bairro)
            VALUES (?, ?, ?, ?, ?) RETURNING id
        ");

        $stmtInsertCli->execute([
            $dados['cliente_nome'],
            $cpf_limpo,
            $dados['cliente_telefone'],
            $endereco_cli,
            $bairro_cli
        ]);

        $cliente_id = $stmtInsertCli->fetchColumn(); 
    }

    /* ==============================================================
       2. VALIDAÇÃO
    ============================================================== */
    if (!isset($dados['forma_pagamento']) || empty($dados['forma_pagamento'])) {
        throw new Exception('Forma de pagamento não informada.');
    }
    $forma_pagamento_id = filter_var($dados['forma_pagamento'], FILTER_VALIDATE_INT);

    /* ==============================================================
       3. PEDIDO
    ============================================================== */
    $sqlPedido = "
        INSERT INTO pedidos_online (
            cliente_id, valor_total, taxa_entrega, tipo_entrega, 
            bairro_entrega, endereco_completo, forma_pagamento_id, 
            precisa_troco, status, origen
        ) VALUES (
            :cliente_id, :valor_total, :taxa_entrega, :tipo_entrega, 
            :bairro_entrega, :endereco_completo, :forma_pagamento_id, 
            :precisa_troco, 'Pendente', 'Site'
        ) RETURNING id
    ";

    $stmtPedido = $pdo->prepare($sqlPedido);
    $stmtPedido->execute([
        ':cliente_id' => $cliente_id,
        ':valor_total' => $dados['total_geral'],
        ':taxa_entrega' => $taxa_entrega,
        ':tipo_entrega' => $tipo_entrega,
        ':bairro_entrega' => $bairro_pedido,
        ':endereco_completo' => $endereco_pedido,
        ':forma_pagamento_id' => $forma_pagamento_id,
        ':precisa_troco' => $dados['precisa_troco'] ?? 0
    ]);

    $pedido_id = $stmtPedido->fetchColumn();

    /* ==============================================================
       4. ITENS COM VALIDAÇÃO ATÔMICA DE ESTOQUE
    ============================================================== */
    $sqlItem = "
        INSERT INTO pedidos_online_itens (
            pedido_id, produto_id, quantidade, preco_unitario, subtotal
        ) VALUES (
            :pedido_id, :produto_id, :quantidade, :preco_unitario, :subtotal
        )
    ";
    $stmtItem = $pdo->prepare($sqlItem);

    // Queries auxiliares de checagem e alteração de estoque
    $stmtCheckEstoque = $pdo->prepare("SELECT nome, estoque, controla_estoque FROM produtos WHERE id = ?");
    
    // UPDATE ATÔMICO: Só altera se tiver estoque suficiente
    $stmtUpdateEstoque = $pdo->prepare("
        UPDATE produtos 
        SET estoque = estoque - :quantidade 
        WHERE id = :produto_id 
        AND estoque >= :quantidade
    ");

    foreach ($dados['itens'] as $item) {
        $id_produto = (int)$item['id'];
        $qtd_produto = (float)$item['qtd'];

        // 1. Busca os dados atuais do produto para saber se ele controla estoque
        $stmtCheckEstoque->execute([$id_produto]);
        $prodInfo = $stmtCheckEstoque->fetch(PDO::FETCH_ASSOC);

        if (!$prodInfo) {
            throw new Exception("Produto ID {$id_produto} não foi encontrado no sistema.");
        }

        // 2. Se o produto controla estoque, aplica a trava atômica
        if (($prodInfo['controla_estoque'] ?? 'S') === 'S') {
            
            $stmtUpdateEstoque->execute([
                ':quantidade' => $qtd_produto,
                ':produto_id' => $id_produto
            ]);

            // Se nenhuma linha foi afetada, significa que o estoque é insuficiente para esta quantidade
            if ($stmtUpdateEstoque->rowCount() === 0) {
                $estoque_atual = (float)($prodInfo['estoque'] ?? 0);
                throw new Exception("O item '{$prodInfo['nome']}' não possui estoque suficiente. Quantidade máxima disponível no momento: {$estoque_atual}.");
            }
        }

        // 3. Insere o item no pedido se passar pela trava (ou se não controlar estoque)
        $stmtItem->execute([
            ':pedido_id' => $pedido_id,
            ':produto_id' => $id_produto,
            ':quantidade' => $qtd_produto,
            ':preco_unitario' => (float)$item['preco'],
            ':subtotal' => (float)$item['preco'] * $qtd_produto
        ]);
    }

    // Se tudo deu certo para todos os itens, consolida as alterações no banco
    $pdo->commit();
    echo json_encode(['sucesso' => true, 'pedido_id' => $pedido_id]);

} catch (Exception $e) {
    // Caso falte estoque em QUALQUER item, desfaz tudo (o pedido não é gerado e o estoque não mexe)
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>
