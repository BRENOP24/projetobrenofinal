<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

$id_venda = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$usuario_id = $_SESSION['usuario_id'];

if (!$id_venda) {
    die("ID da venda não fornecido.");
}

try {
    $pdo->beginTransaction();

    // 1. Pega o caixa atual (onde o dinheiro vai sair hoje)
    $stmt_caixa = $pdo->prepare("SELECT id FROM controle_caixas WHERE usuario_id = ? AND status = 'aberto' LIMIT 1");
    $stmt_caixa->execute([$usuario_id]);
    $caixa_atual = $stmt_caixa->fetch();

    if (!$caixa_atual) {
        throw new Exception("Você precisa de um caixa aberto para realizar o estorno.");
    }

    // 2. Busca os dados da venda (incluindo o caixa original dela)
    $stmt_venda = $pdo->prepare("SELECT id, valor_total, caixa_id FROM pedidos WHERE id = ? AND status != 'cancelado'");
    $stmt_venda->execute([$id_venda]);
    $venda = $stmt_venda->fetch();

    if (!$venda) {
        throw new Exception("Venda não encontrada ou já cancelada.");
    }

    // 3. Devolve Estoque (Tabela pedidos_itens conforme sua correção)
    $stmt_itens = $pdo->prepare("SELECT produto_id, quantidade FROM pedidos_itens WHERE pedido_id = ?");
    $stmt_itens->execute([$id_venda]);
    $itens = $stmt_itens->fetchAll();

    foreach ($itens as $item) {
        $stmt_est = $pdo->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ?");
        $stmt_est->execute([$item['quantidade'], $item['produto_id']]);
    }

    // 4. Atualiza a venda para cancelado
    $pdo->prepare("UPDATE pedidos SET status = 'cancelado' WHERE id = ?")->execute([$id_venda]);

    // 5. REGISTRO NA TABELA DE AUDITORIA (Para aparecer no seu novo relatório)
    $sql_auditoria = "
        INSERT INTO auditoria_financeira (
            data_auditoria, tipo_operacao, registro_origem_id, valor_estornado, 
            caixa_origem_id, caixa_atual_id, usuario_id, motivo
        ) VALUES (NOW(), 'ESTORNO_VENDA', ?, ?, ?, ?, ?, ?)
    ";
    $stmt_aud = $pdo->prepare($sql_auditoria);
    $stmt_aud->execute([
        $venda['id'], 
        $venda['valor_total'], 
        $venda['caixa_id'], // ID do caixa do dia que a venda foi feita
        $caixa_atual['id'],  // ID do caixa de hoje
        $usuario_id,
        "Estorno de venda via painel de auditoria"
    ]);

    // 6. Movimentação financeira de saída no caixa de hoje
    $stmt_mov = $pdo->prepare("
        INSERT INTO movimentacoes_caixa (id_caixa, tipo, valor, descricao, data_movimentacao) 
        VALUES (?, 'saida', ?, ?, NOW())
    ");
    $stmt_mov->execute([$caixa_atual['id'], $venda['valor_total'], "Estorno Ref. Venda #".$id_venda]);

    $pdo->commit();
    header("Location: auditoria_caixas.php?sucesso=1"); // Redireciona direto para o relatório

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Erro: " . $e->getMessage());
}