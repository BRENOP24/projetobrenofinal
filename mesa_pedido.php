<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

// 🔒 segurança
if (!in_array($_SESSION['nivel'], ['admin','garcom'])) {
    header("Location: dashboard.php");
    exit;
}

$pedido_id = $_GET['pedido_id'] ?? null;

if (!$pedido_id) {
    die("Pedido inválido");
}

// 🔹 valida se o pedido existe e é de mesa
$stmt = $pdo->prepare("
    SELECT id FROM pedidos
    WHERE id = :id
    AND origem_tipo = 'mesa'
");
$stmt->execute([':id' => $pedido_id]);

if (!$stmt->fetch()) {
    die("Pedido não encontrado ou inválido");
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Mesa - Pedido</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.card { border-radius: 10px; }
.lista-resultado {
    position: absolute;
    z-index: 1000;
    background: white;
    width: 100%;
    border: 1px solid #ddd;
    display: none;
}
.item-busca {
    padding: 10px;
    cursor: pointer;
}
.item-busca:hover {
    background: #f1f1f1;
}
</style>
</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3">
        <h4>🍽️ Pedido da Mesa</h4>
        <a href="mesas.php" class="btn btn-secondary">Voltar</a>
    </div>

    <div class="card p-3 mb-3">

        <!-- BUSCA PRODUTO -->
        <div class="row g-2 mb-3">
            <div class="col-md-8 position-relative">
                <input type="text" id="input_produto" class="form-control" placeholder="Buscar produto...">
                <div id="lista_produtos" class="lista-resultado"></div>
            </div>

            <div class="col-md-2">
                <input type="number" id="qtd_item" value="1" min="1" class="form-control text-center">
            </div>

            <div class="col-md-2">
<button class="btn btn-success w-100" onclick="addItemGarcom()">
    ADD
</button>
            </div>
        </div>

        <!-- TABELA -->
        <div class="table-responsive">
            <table class="table" id="tabela_itens">
<thead>
    <tr>
        <th>Produto</th>
        <th class="text-center">Qtd</th>
        <th class="text-end">Preço</th>
        <th class="text-end">Total</th>
        <th class="text-center">Ações</th> </tr>
</thead>
                <tbody></tbody>
            </table>
        </div>

        <!-- TOTAL -->
        <div class="text-end mt-3">
            <h4>Total: <span id="total_exibicao">R$ 0,00</span></h4>
        </div>

    </div>

</div>

<script>
    window.pedido_id = <?= (int)$pedido_id ?>;
</script>

<script src="js/garcom.js?v=1.2"></script>

</body>
</html>