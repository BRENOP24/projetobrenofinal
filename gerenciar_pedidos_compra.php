<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

// 📅 filtros
$data_inicial = $_GET['data_inicial'] ?? date('Y-m-01');
$data_final   = $_GET['data_final'] ?? date('Y-m-d');

// busca pedidos
$stmt = $pdo->prepare("
    SELECT pc.id, pc.data_pedido, pc.total, pc.status,
           f.nome_fantasia
    FROM pedidos_compra pc
    JOIN fornecedores f ON f.id = pc.fornecedor_id
    WHERE DATE(pc.data_pedido) BETWEEN :data_inicial AND :data_final
    ORDER BY pc.id DESC
");

$stmt->execute([
    ':data_inicial' => $data_inicial,
    ':data_final'   => $data_final
]);

$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Gerenciar Pedidos de Compra</title>

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

.filtros {
    margin-bottom: 15px;
}

input, select, button {
    padding: 8px;
    margin: 5px;
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

button {
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.btn-filtrar {
    background: #007bff;
    color: white;
}

.btn-imprimir {
    background: #28a745;
    color: white;
}

.status {
    font-weight: bold;
}

.status.aberto { color: #007bff; }
.status.em_analise { color: #ffc107; }
.status.finalizado { color: #28a745; }
.status.cancelado { color: #dc3545; }

.sem-dados {
    margin-top: 20px;
    color: #666;
}
</style>
</head>

<body>

<div class="container">

<h2>Gerenciar Pedidos de Compra</h2>

<!-- 🔎 FILTRO -->
<form method="GET" class="filtros">
    <label>Data Inicial:</label>
    <input type="date" name="data_inicial" value="<?= $data_inicial ?>">

    <label>Data Final:</label>
    <input type="date" name="data_final" value="<?= $data_final ?>">

    <button type="submit" class="btn-filtrar">Filtrar</button>
</form>

<?php if (empty($pedidos)): ?>
    <div class="sem-dados">Nenhum pedido encontrado.</div>
<?php else: ?>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Fornecedor</th>
            <th>Data</th>
            <th>Total</th>
            <th>Status</th>
            <th>Alterar</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>

    <?php foreach ($pedidos as $p): ?>
        <tr>
            <td><?= $p['id'] ?></td>
            <td><?= htmlspecialchars($p['nome_fantasia']) ?></td>
            <td><?= date('d/m/Y H:i', strtotime($p['data_pedido'])) ?></td>
            <td>R$ <?= number_format($p['total'], 2, ',', '.') ?></td>

            <!-- status atual -->
            <td class="status <?= $p['status'] ?>">
                <?= ucfirst(str_replace('_', ' ', $p['status'])) ?>
            </td>

            <!-- alterar status -->
            <td>
                <select onchange="alterarStatus(<?= $p['id'] ?>, this.value)">
                    <option value="aberto" <?= $p['status']=='aberto'?'selected':'' ?>>Aberto</option>
                    <option value="em_analise" <?= $p['status']=='em_analise'?'selected':'' ?>>Em Análise</option>
                    <option value="finalizado" <?= $p['status']=='finalizado'?'selected':'' ?>>Finalizado</option>
                    <option value="cancelado" <?= $p['status']=='cancelado'?'selected':'' ?>>Cancelado</option>
                </select>
            </td>

            <!-- ações -->
            <td>
                <button class="btn-imprimir" onclick="reimprimir(<?= $p['id'] ?>)">
                    Imprimir
                </button>
            </td>
        </tr>
    <?php endforeach; ?>

    </tbody>
</table>

<?php endif; ?>

</div>

<script>
function reimprimir(id) {
    window.open('imprimir_pedido_compra.php?id=' + id, '_blank');
}

function alterarStatus(id, status) {

    console.log('Enviando:', { id, status });

    fetch('/projeto_breno/atualizar_status_pedido.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            id: id,
            status: status
        })
    })
    .then(async (response) => {

        // 🔥 captura resposta crua
        const text = await response.text();

        console.log('Resposta bruta:', text);

        // tenta converter pra JSON
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('Resposta não é JSON válido');
        }
    })
    .then(res => {

        if (res.erro) {
            alert('Erro: ' + (res.msg || 'Falha ao atualizar'));
            return;
        }

        // sucesso
        location.reload();

    })
    .catch(err => {
        console.error('Erro completo:', err);
        alert('Erro na requisição. Veja o console (F12).');
    });
}
</script>

</body>
</html>