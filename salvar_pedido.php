<?php
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

header('Content-Type: application/json');

$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados) {
    echo json_encode(['status' => 'erro', 'msg' => 'Dados não recebidos.']);
    exit;
}

try {
    $pdo->beginTransaction();

    if (empty($_SESSION['usuario_id'])) {
        throw new Exception('Usuário não autenticado.');
    }

    $usuario_id = (int) $_SESSION['usuario_id'];

    if (empty($dados['itens'])) {
        throw new Exception('Pedido sem itens.');
    }

    if (empty($dados['tipo_venda'])) {
        throw new Exception('Tipo de venda não informado.');
    }

    // 1. BUSCAR CAIXA ABERTO
    $stmtCaixa = $pdo->prepare("SELECT id FROM controle_caixas WHERE usuario_id = :user AND status = 'aberto' ORDER BY id DESC LIMIT 1");
    $stmtCaixa->execute([':user' => $usuario_id]);
    $caixa = $stmtCaixa->fetch(PDO::FETCH_ASSOC);

    if (!$caixa) {
        throw new Exception('Nenhum caixa aberto para este usuário.');
    }

    $caixa_id = (int) $caixa['id'];

    // 2. DEFINIR ORIGEM E ENDEREÇO
    $origem_tipo = $dados['tipo_venda'];
    $origem_id = ($origem_tipo === 'mesa') ? ($dados['mesa_id'] ?? null) : (($origem_tipo === 'comanda') ? ($dados['comanda_id'] ?? null) : null);
    
    // Tratamento do endereço de entrega (caso venha do modal de delivery)
    $endereco_completo = null;
    $status_entrega = null;
    
    if ($origem_tipo === 'delivery' || $origem_tipo === 'Tele Entrega') {
        $status_entrega = 'pendente'; // Aguardando vincular motoboy
        if (isset($dados['endereco_confirmado'])) {
            $end = $dados['endereco_confirmado'];
            $endereco_completo = "Rua: {$end['rua']}, Nº: {$end['numero']} - Bairro: {$end['bairro']}";
        }
    }

    // 3. CALCULAR TOTAL
    $total = 0;
    foreach ($dados['itens'] as $item) {
        $total += ($item['qtd'] * $item['preco']);
    }

    $desconto = $dados['desconto'] ?? 0;
    $taxa     = $dados['taxa'] ?? 0;
    $total_final = $total - $desconto + $taxa;

    // 4. INSERIR PEDIDO (Adicionado endereco_entrega e status_entrega)
    $sqlPedido = "INSERT INTO pedidos (
                    cliente_id, caixa_id, usuario_id, tipo_venda, origem_tipo, 
                    origem_id, valor_total, desconto, taxa_entrega, 
                    forma_pagamento_id, status, situacao, endereco_entrega, 
                    status_entrega, criado_em
                ) 
                VALUES (
                    :cliente, :caixa, :usuario, :tipo, :origem, 
                    :origem_id, :total, :desconto, :taxa, 
                    :pagamento, 'finalizado', 'finalizado', :endereco, 
                    :status_ent, NOW()
                )";

    $stmt = $pdo->prepare($sqlPedido);
    $stmt->execute([
        ':cliente'    => $dados['cliente_id'] ?? null,
        ':caixa'      => $caixa_id,
        ':usuario'    => $usuario_id,
        ':tipo'       => $dados['tipo_venda'],
        ':origem'     => $origem_tipo,
        ':origem_id'  => $origem_id,
        ':total'      => $total_final,
        ':desconto'   => $desconto,
        ':taxa'       => $taxa,
        ':pagamento'  => $dados['pagamento_id'],
        ':endereco'   => $endereco_completo,
        ':status_ent' => $status_entrega
    ]);
    $pedido_id = $pdo->lastInsertId();

    // 5. INSERIR ITENS E BAIXA ESTOQUE
    $sqlItem = "INSERT INTO pedidos_itens (pedido_id, produto_id, quantidade, valor_unitario, valor_total) VALUES (:pedido, :produto, :qtd, :valor, :total)";
    $stmtItem = $pdo->prepare($sqlItem);
    $stmtEstoque = $pdo->prepare("UPDATE produtos SET estoque = estoque - :qtd WHERE id = :id");

    foreach ($dados['itens'] as $item) {
        $subtotal = $item['qtd'] * $item['preco'];
        $stmtItem->execute([
            ':pedido'  => $pedido_id,
            ':produto' => $item['id'],
            ':qtd'     => $item['qtd'],
            ':valor'   => $item['preco'],
            ':total'   => $subtotal
        ]);
        $stmtEstoque->execute([':qtd' => $item['qtd'], ':id' => $item['id']]);
    }

    // 6. GERA CONTA A RECEBER
    if ((int)$dados['pagamento_id'] === 7) {
        if (empty($dados['cliente_id'])) {
            throw new Exception('Para vendas a prazo, selecione um cliente!');
        }

        $sqlCR = "INSERT INTO contas_receber (id_cliente, id_usuario, valor_total, data_vencimento, status, id_plano_conta) 
                  VALUES (:cliente, :usuario, :valor, :vencimento, 'Pendente', 7)";
        
        $vencimento = date('Y-m-d', strtotime('+30 days')); 

        $stmtCR = $pdo->prepare($sqlCR);
        $stmtCR->execute([
            ':cliente'    => $dados['cliente_id'],
            ':usuario'    => $usuario_id,
            ':valor'      => $total_final,
            ':vencimento' => $vencimento
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'status'    => 'sucesso',
        'id'        => $pedido_id,
        'pedido_id' => $pedido_id
    ]);

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'erro',
        'msg' => $e->getMessage()
    ]);
}