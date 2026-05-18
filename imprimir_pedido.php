<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    die('Pedido inválido.');
}

/*
|--------------------------------------------------------------------------
| 1. Busca dados do pedido (Ajustado para pegar o endereço gravado no pedido)
|--------------------------------------------------------------------------
*/
$sqlPedido = "
    SELECT 
        p.id,
        p.valor_total,
        p.desconto,
        p.taxa_entrega,
        p.tipo_venda,
        p.status,
        p.criado_em,
        p.endereco_entrega, -- O endereço que confirmamos no modal
        c.nome AS cliente_nome,
        c.telefone,
        f.descricao AS forma_pagamento
    FROM pedidos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    LEFT JOIN formas_pagamento f ON p.forma_pagamento_id = f.id
    WHERE p.id = :id
    LIMIT 1
";

$stmtPedido = $pdo->prepare($sqlPedido);
$stmtPedido->execute([':id' => $id]);
$pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die('Pedido não encontrado.');
}

/*
|--------------------------------------------------------------------------
| 2. Busca itens do pedido
|--------------------------------------------------------------------------
*/
$sqlItens = "
    SELECT 
        prod.nome,
        pi.quantidade,
        pi.valor_unitario,
        (pi.quantidade * pi.valor_unitario) AS subtotal
    FROM pedidos_itens pi
    INNER JOIN produtos prod ON prod.id = pi.produto_id
    WHERE pi.pedido_id = :id
";

$stmtItens = $pdo->prepare($sqlItens);
$stmtItens->execute([':id' => $id]);
$itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Pedido #<?= $pedido['id'] ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            width: 72mm; /* Ajustado para bobinas padrão de 80mm com margens */
            margin: 0 auto;
            padding: 5px;
            font-size: 11px;
            line-height: 1.2;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .line {
            border-bottom: 1px dashed #000;
            margin: 5px 0;
        }
        .bold { font-weight: bold; }
        .titulo { font-size: 14px; margin-bottom: 2px; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .tipo-venda {
            background: #000;
            color: #fff;
            padding: 2px;
            display: block;
            margin: 5px 0;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>

<body onload="window.print();">

<div class="center">
    <strong class="titulo">SAY NOW</strong><br>
    Pedido #<?= $pedido['id'] ?><br>
    <?= date('d/m/Y H:i', strtotime($pedido['criado_em'])) ?>
</div>

<div class="tipo-venda">
    <?= strtoupper($pedido['tipo_venda']) ?>
</div>

<div class="line"></div>

<span class="bold">Cliente:</span> <?= $pedido['cliente_nome'] ?><br>
<span class="bold">Tel:</span> <?= $pedido['telefone'] ?? 'Não informado' ?><br>

<?php if ($pedido['tipo_venda'] === 'delivery' || $pedido['tipo_venda'] === 'Tele Entrega'): ?>
    <div style="margin-top: 5px; padding: 3px; border: 1px solid #000;">
        <span class="bold">ENDEREÇO DE ENTREGA:</span><br>
        <?= !empty($pedido['endereco_entrega']) ? $pedido['endereco_entrega'] : ($pedido['endereco'] ?? 'Endereço não informado'); ?>
    </div>
<?php endif; ?>

<div class="line"></div>

<table>
    <thead>
        <tr>
            <th align="left">Item</th>
            <th class="right">Total</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($itens as $item): ?>
        <tr>
            <td colspan="2"><?= $item['nome'] ?></td>
        </tr>
        <tr>
            <td><?= number_format($item['quantidade'], 0) ?> x <?= number_format($item['valor_unitario'], 2, ',', '.') ?></td>
            <td class="right"><?= number_format($item['subtotal'], 2, ',', '.') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="line"></div>

<?php
$subtotal_calculado = $pedido['valor_total'] + $pedido['desconto'] - $pedido['taxa_entrega'];
?>

<div class="right">
    Subtotal: R$ <?= number_format($subtotal_calculado, 2, ',', '.') ?><br>
    Desconto: R$ <?= number_format($pedido['desconto'], 2, ',', '.') ?><br>
    Taxa Entrega: R$ <?= number_format($pedido['taxa_entrega'], 2, ',', '.') ?><br>
    <span class="bold" style="font-size: 13px;">TOTAL: R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></span>
</div>

<div class="line"></div>

<span class="bold">FORMA DE PAGAMENTO:</span><br>
<?= $pedido['forma_pagamento'] ?><br>

<?php if ($pedido['tipo_venda'] === 'delivery'): ?>
    <div class="line"></div>
    <div style="height: 40px; border-bottom: 1px solid #000; margin-top: 10px;"></div>
    <div class="center">Assinatura do Cliente</div>
<?php endif; ?>

<div class="center" style="margin-top: 15px;">
    Obrigado pela preferência!<br>
    SAY NOW - Gestão de Vendas
</div>

</body>
</html>