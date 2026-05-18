<?php 
require_once 'config/sessao.php';
require_once 'config/conexao.php';

if (!in_array($_SESSION['nivel'], ['admin','garcom'])) {
    header("Location: dashboard.php");
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT 
            m.id, 
            m.numero,
            EXISTS (
                SELECT 1 FROM pedidos p 
                WHERE p.origem_tipo = 'mesa'
                AND p.origem_id = m.id 
                AND p.situacao = 'aberto'
            ) AS ocupada
        FROM mesas m
        ORDER BY m.numero ASC
    ");

    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar mesas: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gestão Breno - Mesas</title>
    <style>
        body { font-family: 'Segoe UI', Arial; background: #f5f5f5; padding: 20px; }
        .topo { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 15px; }
        .card { 
            padding: 30px 10px; text-align: center; border-radius: 12px; 
            color: #fff; font-weight: bold; cursor: pointer; transition: 0.2s; 
        }
        .card:hover { transform: scale(1.05); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .livre { background: #28a745; }
        .ocupada { background: #dc3545; }
        .btn { padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn-nova { background: #007bff; color: #fff; }
        
        /* Modal */
        .modal { 
            display: none; position: fixed; top:0; left:0; width:100%; height:100%; 
            background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000;
        }
        .modal-content { background: #fff; padding: 25px; border-radius: 10px; width: 300px; }
        input { width: 100%; padding: 10px; margin: 15px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
    </style>
</head>
<body>

<div class="topo">
    <h2>🍽️ Controle Mesas</h2>
    <div>
        <a href="garcom.php" style="text-decoration:none; margin-right: 15px; color: #666;">← Voltar</a>
        <button class="btn btn-nova" onclick="abrirModal()">+ Nova Mesa</button>
    </div>
</div>

<div class="grid">
    <?php if (empty($mesas)): ?>
        <p>Nenhuma mesa cadastrada.</p>
    <?php else: ?>
        <?php foreach($mesas as $m): ?>
            <div 
                class="card <?= $m['ocupada'] ? 'ocupada' : 'livre' ?>"
                onclick="irParaMesa(<?= $m['id'] ?>)"
            >
                Mesa: <?= htmlspecialchars($m['numero']) ?>
                <br>
                <small><?= $m['ocupada'] ? '(Ocupada)' : '(Livre)' ?></small>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div id="modalMesa" class="modal">
    <div class="modal-content">
        <h3>Cadastrar Mesa</h3>
        <input type="number" id="nova_mesa_numero" placeholder="Ex: 3">

        <button class="btn btn-nova" onclick="window.criarMesa()">Salvar</button>
        <button class="btn" style="background:#6c757d; color:#fff;" onclick="fecharModal()">Cancelar</button>
    </div>
</div>

<script>
// Redireciona para abertura de pedido passando o tipo e o ID
function irParaMesa(id) {
    window.location.href = "abrir_pedido.php?tipo=mesa&id=" + id;
}

// Funções do Modal
function abrirModal() {
    document.getElementById('modalMesa').style.display = 'flex';
    document.getElementById('nova_mesa_numero').focus();
}

function fecharModal() {
    document.getElementById('modalMesa').style.display = 'none';
    document.getElementById('nova_mesa_numero').value = '';
}

</script>

<script src="js/garcom.js"></script>

</body>
</html>