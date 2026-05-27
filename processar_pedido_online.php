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
       1. CLIENTE (Postgres não gosta de LIMIT 1 sem ORDER BY, mas funciona)
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

        // Ajuste: Adicionado RETURNING id para garantir captura no Postgres
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
       2. VALIDAÇÃO (Sem mudanças necessárias)
    ============================================================== */
    if (!isset($dados['forma_pagamento']) || empty($dados['forma_pagamento'])) {
        throw new Exception('Forma de pagamento não informada.');
    }
    $forma_pagamento_id = filter_var($dados['forma_pagamento'], FILTER_VALIDATE_INT);

    /* ==============================================================
       3. PEDIDO (Ajuste no RETURNING)
    ============================================================== */
    $sqlPedido = "
        INSERT INTO pedidos_online (
            cliente_id, valor_total, taxa_entrega, tipo_entrega, 
            bairro_entrega, endereco_completo, forma_pagamento_id, 
            precisa_troco, status, origem
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

    $pedido_id = $stmtPedido->fetchColumn(); // Captura o ID do RETURNING

    /* ==============================================================
       4. ITENS
    ============================================================== */
    $sqlItem = "
        INSERT INTO pedidos_online_itens (
            pedido_id, produto_id, quantidade, preco_unitario, subtotal
        ) VALUES (
            :pedido_id, :produto_id, :quantidade, :preco_unitario, :subtotal
        )
    ";

    $stmtItem = $pdo->prepare($sqlItem);

    foreach ($dados['itens'] as $item) {
        $stmtItem->execute([
            ':pedido_id' => $pedido_id,
            ':produto_id' => (int)$item['id'],
            ':quantidade' => (float)$item['qtd'],
            ':preco_unitario' => (float)$item['preco'],
            ':subtotal' => (float)$item['preco'] * (float)$item['qtd']
        ]);
    }

    $pdo->commit();
    echo json_encode(['sucesso' => true, 'pedido_id' => $pedido_id]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>
