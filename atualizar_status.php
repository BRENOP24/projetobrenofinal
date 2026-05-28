<?php
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

$id_online = $_POST['id'] ?? null;
$novoStatus = $_POST['status'] ?? null;
$usuario_id = $_SESSION['usuario_id'] ?? 1;

if (!$id_online || !$novoStatus) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados incompletos.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Busca pedido na tabela pedidos_online
    $stmtPed = $pdo->prepare("SELECT * FROM pedidos_online WHERE id = ?");
    $stmtPed->execute([$id_online]);
    $pedOnline = $stmtPed->fetch(PDO::FETCH_ASSOC);

    if (!$pedOnline) {
        throw new Exception("Pedido não encontrado na tabela pedidos_online.");
    }

    if ($novoStatus === 'Finalizado') {
        if (!empty($pedOnline['id_caixa'])) {
            throw new Exception("Pedido já foi finalizado anteriormente.");
        }

        // 2. Busca caixa aberto em controle_caixas
        $stmtCx = $pdo->prepare("SELECT id FROM controle_caixas WHERE status = 'aberto' ORDER BY id DESC LIMIT 1");
        $stmtCx->execute();
        $caixa = $stmtCx->fetch(PDO::FETCH_ASSOC);

        if (!$caixa) {
            throw new Exception("Não existe um caixa aberto em 'controle_caixas'.");
        }

        $caixa_id = $caixa['id'];

      // 3. Insere no Gerenciador Oficial: pedidos (AGORA DINÂMICO)
        // Lemos o tipo original do site ('retirada' ou 'delivery')
        $tipoVendaReal = strtolower($pedOnline['tipo_entrega']) === 'retirada' ? 'balcao' : 'delivery';
        $origemTipoReal = strtolower($pedOnline['tipo_entrega']) === 'retirada' ? 'site_retirada' : 'delivery';

        $sqlInsert = "INSERT INTO pedidos (
            usuario_id, 
            caixa_id, 
            cliente_id, 
            forma_pagamento_id, 
            valor_total, 
            status, 
            tipo_venda, 
            taxa_entrega, 
            endereco_entrega, 
            data_pedido,
            origem_tipo
        ) VALUES (?, ?, ?, ?, ?, 'finalizado', ?, ?, ?, NOW(), ?)";

        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([
            $usuario_id, 
            $caixa_id,
            $pedOnline['cliente_id'] ?? null,
            $pedOnline['forma_pagamento_id'],
            $pedOnline['valor_total'],
            $tipoVendaReal, //  Trocado de 'delivery' fixo para dinâmico (balcao ou delivery)
            $pedOnline['taxa_entrega'] ?? 0,
            $pedOnline['endereco_completo'],
            $origemTipoReal //  Trocado para salvar a origem real no banco
        ]);

        
        // RECUPERA O ID QUE ACABOU DE SER GERADO (Importante para os itens!)
        $id_pedido_oficial = $pdo->lastInsertId();

        // 4. MIGRA ITENS: pedidos_online_itens -> pedidos_itens
        $stmtItensOnline = $pdo->prepare("SELECT * FROM pedidos_online_itens WHERE pedido_id = ?");
        $stmtItensOnline->execute([$id_online]);
        $itens = $stmtItensOnline->fetchAll(PDO::FETCH_ASSOC);

        foreach ($itens as $item) {
            $v_unitario = (float)$item['preco_unitario'];
            $qtd = (int)$item['quantidade'];

            $stmtItemOficial = $pdo->prepare("
                INSERT INTO pedidos_itens 
                (pedido_id, produto_id, quantidade, valor_unitario, valor_total) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtItemOficial->execute([
                $id_pedido_oficial, // Agora a variável está definida
                $item['produto_id'],
                $qtd,
                $v_unitario,
                ($v_unitario * $qtd)
            ]);
        }

        // 5. Vincula o pedido online ao caixa
        $stmtLink = $pdo->prepare("UPDATE pedidos_online SET id_caixa = ? WHERE id = ?");
        $stmtLink->execute([$caixa_id, $id_online]);
    }

    // 6. Atualiza status do pedido na tabela online
    $stmtUpdate = $pdo->prepare("UPDATE pedidos_online SET status = ? WHERE id = ?");
    $stmtUpdate->execute([$novoStatus, $id_online]);

    $pdo->commit();
    echo json_encode(['sucesso' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['sucesso' => false, 'erro' => "Erro no Banco: " . $e->getMessage()]);
}
