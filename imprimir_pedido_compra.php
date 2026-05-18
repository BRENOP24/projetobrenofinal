<?php
require_once 'config/conexao.php';

$pedido_id = $_GET['id'] ?? 0;

// pedido + fornecedor
$stmt = $pdo->prepare("
    SELECT pc.*, f.nome_fantasia, f.email, f.telefone
    FROM pedidos_compra pc
    JOIN fornecedores f ON f.id = pc.fornecedor_id
    WHERE pc.id = :id
");
$stmt->execute([':id' => $pedido_id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

// itens
$stmt = $pdo->prepare("
    SELECT i.*, p.nome
    FROM pedidos_compra_itens i
    JOIN produtos p ON p.id = i.produto_id
    WHERE i.pedido_id = :id
");
$stmt->execute([':id' => $pedido_id]);
$itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Imprimir Pedido</title>

<style>
body { font-family: Arial; padding:20px; }

h2 { margin-bottom: 5px; }

table {
    width:100%;
    border-collapse: collapse;
    margin-top:10px;
}

th, td {
    border:1px solid #000;
    padding:8px;
}

th {
    background:#eee;
}

.total {
    text-align:right;
    margin-top:10px;
    font-weight:bold;
}

@media print {
    button { display:none; }
}
</style>
</head>

<body onload="window.print()">

<h2>Pedido de Compra - Empresa SAY NOW</h2>

<p>
<strong>Fornecedor:</strong> <?= $pedido['nome_fantasia'] ?><br>
<strong>Email:</strong> <?= $pedido['email'] ?><br>
<strong>Telefone:</strong> <?= $pedido['telefone'] ?><br>
</p>

<table>
<tr>
    <th>Produto</th>
    <th>Qtd</th>
    <th>Preço</th>
    <th>Subtotal</th>
</tr>

<?php foreach($itens as $item): ?>
<tr>
    <td><?= $item['nome'] ?></td>
    <td><?= $item['quantidade'] ?></td>
    <td>R$ <?= number_format($item['preco_unitario'],2,',','.') ?></td>
    <td>R$ <?= number_format($item['subtotal'],2,',','.') ?></td>
</tr>
<?php endforeach; ?>

</table>

<div class="total">
Total: R$ <?= number_format($pedido['total'],2,',','.') ?>
</div>

<p><strong>Observação:</strong><br><?= $pedido['observacao'] ?></p>

</body>
</html>