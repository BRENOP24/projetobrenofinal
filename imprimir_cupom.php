<?php
require_once 'config/conexao.php';
$id = $_GET['id'] ?? null;

if (!$id) {
    die("ID do pedido não fornecido.");
}

// 1. Busca dados do pedido e da forma de pagamento
$stmt = $pdo->prepare("
    SELECT p.*, f.descricao as pagamento 
    FROM pedidos p 
    LEFT JOIN formas_pagamento f ON p.forma_pagamento_id = f.id 
    WHERE p.id = ?
");
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) {
    die("Pedido não encontrado.");
}

// 2. Busca os itens. Fiz um LEFT JOIN com a tabela produtos para garantir que o nome apareça
// Se sua tabela de itens tiver a coluna 'nome', mudei para usar um COALESCE como segurança
$itens = $pdo->prepare("
    SELECT pi.*, pr.nome as nome_prod_tabela
    FROM pedidos_itens pi
    LEFT JOIN produtos pr ON pi.produto_id = pr.id
    WHERE pi.pedido_id = ?
");
$itens->execute([$id]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cupom #<?= $id ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; width: 72mm; font-size: 12px; margin: 0; padding: 5px; }
        .text-center { text-align: center; }
        .line { border-bottom: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        .item-row td { vertical-align: top; padding: 2px 0; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print();">
    <div class="text-center">
        <strong>GESTÃO BRENO</strong><br>
        Comprovante de Venda<br>
        <?= date('d/m/Y H:i', strtotime($p['data_pedido'])) ?>
    </div>
    
    <div class="line"></div>
    
    <div style="font-size: 10px; margin-bottom: 5px;">
        PEDIDO: #<?= str_pad($p['id'], 5, '0', STR_PAD_LEFT) ?><br>
        TIPO: <?= strtoupper($p['origem_tipo'] ?? 'VENDA') ?> <?= $p['origem_id'] ?? '' ?>
    </div>

    <div class="line"></div>
    
    <table>
        <thead>
            <tr>
                <th align="left">Item</th>
                <th align="right">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php while($i = $itens->fetch()): 
                // Tenta pegar o nome de 'nome_produto', 'nome' ou do JOIN com a tabela produtos
                $exibir_nome = $i['nome_produto'] ?? $i['nome'] ?? $i['nome_prod_tabela'] ?? 'Produto sem nome';
                // Tenta pegar o preço de 'valor_unitario' ou 'preco_unitario'
                $preco = $i['valor_unitario'] ?? $i['preco_unitario'] ?? 0;
            ?>
            <tr class="item-row">
                <td>
                    <?= $i['quantidade'] ?>x <?= substr($exibir_nome, 0, 20) ?>
                </td>
                <td align="right">
                    R$ <?= number_format($i['quantidade'] * $preco, 2, ',', '.') ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
    <div class="line"></div>
    
    <div align="right">
        Subtotal: R$ <?= number_format($p['valor_total'] + $p['desconto'], 2, ',', '.') ?><br>
        Desconto: R$ <?= number_format($p['desconto'], 2, ',', '.') ?><br>
        <strong>TOTAL: R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></strong>
    </div>
    
    <div class="line"></div>
    
    <div class="text-center">
        Pagamento: <?= $p['pagamento'] ?? 'Não informado' ?><br>
        <br>
        Obrigado pela preferência!
    </div>
    
    <div class="text-center mt-3">
        <button class="no-print" onclick="window.close()" style="margin-top: 20px; padding: 10px;">Fechar Guia</button>
    </div>
</body>
</html>