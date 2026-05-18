<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// fornecedores
$fornecedores = $pdo->query("
    SELECT id, nome_fantasia 
    FROM fornecedores 
    ORDER BY nome_fantasia
")->fetchAll(PDO::FETCH_ASSOC);

// produtos
$produtos = $pdo->query("
    SELECT id, nome, preco_custo
    FROM produtos 
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Pedido de Compra</title>

<style>
body {
    font-family: 'Segoe UI', Arial;
    background: #f5f6fa;
    padding: 20px;
}

.container {
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

h2 {
    margin-bottom: 15px;
}

select, input, textarea {
    padding: 10px;
    margin: 5px;
    border: 1px solid #ccc;
    border-radius: 6px;
}

button {
    padding: 10px 15px;
    margin: 5px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    background: #007bff;
    color: white;
    font-weight: bold;
}

button:hover {
    background: #0056b3;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th {
    background: #007bff;
    color: white;
}

th, td {
    padding: 10px;
    border: 1px solid #ddd;
}

td button {
    background: #dc3545;
}

.total {
    font-size: 18px;
    font-weight: bold;
    margin-top: 10px;
    text-align: right;
}
#observacao {
    width: 100%;
    max-width: 600px;
    height: 150px;
    resize: vertical; /* usuário pode aumentar */
}
</style>
</head>

<body>

<div class="container">

<h2>Pedido de Compra</h2>

<!-- fornecedor -->
<label>Fornecedor:</label>
<select id="fornecedor">
    <option value="">Selecione</option>
    <?php foreach($fornecedores as $f): ?>
        <option value="<?= $f['id'] ?>">
            <?= htmlspecialchars($f['nome_fantasia']) ?>
        </option>
    <?php endforeach; ?>
</select>

<hr>

<!-- produto -->
<select id="produto">
    <option value="">Selecione um produto</option>
    <?php foreach($produtos as $p): ?>
        <option value="<?= $p['id'] ?>" data-preco="<?= $p['preco_custo'] ?? 0 ?>">
            <?= htmlspecialchars($p['nome']) ?>
        </option>
    <?php endforeach; ?>
</select>

<input type="number" id="quantidade" value="1" step="0.01">
<input type="number" id="preco" placeholder="Preço" step="0.01">

<button onclick="adicionarItem()">Adicionar</button>

<!-- tabela -->
<table id="tabela">
    <thead>
        <tr>
            <th>Produto</th>
            <th>Qtd</th>
            <th>Preço</th>
            <th>Subtotal</th>
            <th>Ação</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>

<div class="total">Total: R$ <span id="total">0.00</span></div>

<br>

<textarea id="observacao" placeholder="Observação"></textarea>

<br><br>

<button onclick="salvarPedido()">Salvar e Imprimir</button>
<a href="dashboard.php" class="btn-voltar" style="text-decoration:none;font-weight:500;">⬅ Voltar ao Painel</a>
</div>

<script>
let itens = [];

// auto preço
document.getElementById('produto').addEventListener('change', function() {
    let preco = this.selectedOptions[0]?.dataset.preco || '';
    document.getElementById('preco').value = preco;
});

// adicionar item
function adicionarItem() {
    let select = document.getElementById('produto');

    let produto_id = select.value;
    let nome = select.selectedOptions[0]?.text;

    let quantidade = parseFloat(document.getElementById('quantidade').value);
    let preco = parseFloat(document.getElementById('preco').value);

    if (!produto_id || !quantidade || !preco) {
        alert('Preencha corretamente');
        return;
    }

    let subtotal = quantidade * preco;

    itens.push({
        produto_id: parseInt(produto_id),
        nome,
        quantidade,
        preco,
        subtotal
    });

    renderTabela();
}

// render tabela
function renderTabela() {
    let tbody = document.querySelector('#tabela tbody');
    tbody.innerHTML = '';

    let total = 0;

    itens.forEach((item, index) => {
        total += item.subtotal;

        tbody.innerHTML += `
            <tr>
                <td>${item.nome}</td>
                <td>${item.quantidade}</td>
                <td>${item.preco.toFixed(2)}</td>
                <td>${item.subtotal.toFixed(2)}</td>
                <td><button onclick="remover(${index})">X</button></td>
            </tr>
        `;
    });

    document.getElementById('total').innerText = total.toFixed(2);
}

// remover
function remover(index) {
    itens.splice(index, 1);
    renderTabela();
}

// salvar + imprimir
function salvarPedido() {
    let fornecedor_id = document.getElementById('fornecedor').value;
    let observacao = document.getElementById('observacao').value;

    if (!fornecedor_id) {
        alert('Selecione fornecedor');
        return;
    }

    if (itens.length === 0) {
        alert('Adicione itens');
        return;
    }

    fetch('salvar_pedido_compra.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            fornecedor_id,
            observacao,
            itens: JSON.stringify(itens)
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.erro) {
            alert(res.msg);
        } else {
            // 🔥 abre tela de impressão
            window.open('imprimir_pedido_compra.php?id=' + res.pedido_id, '_blank');

            // limpa tela
            location.reload();
        }
    })
    .catch(() => {
        alert('Erro na requisição');
    });
}
</script>

</body>
</html>