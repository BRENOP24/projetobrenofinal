<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

// 🔒 Segurança
if (!in_array($_SESSION['nivel'], ['admin','garcom'])) {
    header("Location: dashboard.php");
    exit;
}

$pedido_id = $_GET['pedido_id'] ?? null;

if (!$pedido_id) {
    die("Pedido inválido");
}

// 🔹 Valida se o pedido existe e captura o tipo (mesa ou comanda) para o título
$stmt = $pdo->prepare("
    SELECT id, origem_tipo, origem_id 
    FROM pedidos 
    WHERE id = :id
");
$stmt->execute([':id' => $pedido_id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die("Pedido não encontrado");
}

// Busca o "nome/número" da origem (seja mesa ou comanda)
$identificador = "";
if ($pedido['origem_tipo'] == 'mesa') {
    $sql_origem = "SELECT numero FROM mesas WHERE id = ?";
    $prefixo = "Mesa";
} else {
    $sql_origem = "SELECT numero FROM comandas WHERE id = ?";
    $prefixo = "Comanda";
}

$stmt_origem = $pdo->prepare($sql_origem);
$stmt_origem->execute([$pedido['origem_id']]);
$dados_origem = $stmt_origem->fetch();
$identificador = $prefixo . ": " . ($dados_origem['numero'] ?? 'N/A');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?= $identificador ?> - Pedido</title>
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
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .item-busca { padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; }
        .item-busca:hover { background: #f1f1f1; }
    </style>
</head>

<body class="bg-light">
<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3 align-items: center;">
        <h4>📋 <?= $identificador ?></h4>
        <a href="<?= $pedido['origem_tipo'] == 'mesa' ? 'mesas.php' : 'comandas.php' ?>" class="btn btn-secondary">Voltar</a>
    </div>

    <div class="card p-3 mb-3">
        <div class="row g-2 mb-3">
            <div class="col-md-8 position-relative">
                <input type="text" id="input_produto" class="form-control form-control-lg" placeholder="Buscar produto por nome ou código..." autocomplete="off">
                <div id="lista_produtos" class="lista-resultado"></div>
            </div>

            <div class="col-md-2">
                <input type="number" id="qtd_item" value="1" min="1" class="form-control form-control-lg text-center">
            </div>

            <div class="col-md-2">
                <button class="btn btn-success btn-lg w-100" onclick="addItemGarcom()">ADD</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover" id="tabela_itens">
                <thead class="table-dark">
                    <tr>
                        <th>Produto</th>
                        <th class="text-center">Qtd</th>
                        <th class="text-end">Preço</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    </tbody>
            </table>
        </div>

        <div class="text-end mt-3 border-top pt-3">
            <h3 class="fw-bold">Total: <span id="total_exibicao" class="text-success">R$ 0,00</span></h3>
        </div>
    </div>
</div>

<script>
    // Variáveis globais para o garcom.js
    window.pedido_id = <?= (int)$pedido_id ?>;
</script>
<script src="js/garcom.js?v=<?= time() ?>"></script>

</body>
</html>