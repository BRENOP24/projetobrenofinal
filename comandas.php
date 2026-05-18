<?php 
require_once 'config/sessao.php';
require_once 'config/conexao.php';

if (!in_array($_SESSION['nivel'], ['admin','garcom'])) {
    header("Location: dashboard.php");
    exit;
}

try {
    // Busca comandas e verifica se há pedidos abertos vinculados (origem_tipo = comanda)
    $sql = "
        SELECT 
            c.id, 
            c.numero,
            EXISTS (
                SELECT 1 FROM pedidos p 
                WHERE p.origem_id = c.id 
                AND p.origem_tipo = 'comanda'
                AND p.situacao = 'aberto'
            ) AS ocupada
        FROM comandas c
        ORDER BY c.numero ASC
    ";
    
    $stmt = $pdo->query($sql);
    $comandas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar comandas: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gestão Breno - Comandas</title>
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
        
        /* Modal Style */
        .modal { 
            display: none; position: fixed; top:0; left:0; width:100%; height:100%; 
            background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000;
        }
        .modal-content { background: #fff; padding: 25px; border-radius: 10px; width: 300px; }
        input { width: 100%; padding: 10px; margin: 15px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .acoes-modal { display: flex; gap: 10px; }
    </style>
</head>
<body>

<div class="topo">
    <h2>📋 Controle de Comandas</h2>
    <div>
        <a href="garcom.php" style="text-decoration:none; margin-right: 15px; color: #666;">← Voltar</a>
        <button class="btn btn-nova" onclick="abrirModal()">+ Nova Comanda</button>
    </div>
</div>

<div class="grid">
    <?php if (empty($comandas)): ?>
        <p>Nenhuma comanda cadastrada.</p>
    <?php else: ?>
        <?php foreach($comandas as $c): ?>
            <div 
                class="card <?= $c['ocupada'] ? 'ocupada' : 'livre' ?>"
                onclick="irParaComanda(<?= $c['id'] ?>)"
            >
                Comanda: <?= htmlspecialchars($c['numero']) ?>
                <br>
                <small><?= $c['ocupada'] ? '(Em uso)' : '(Disponível)' ?></small>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div id="modalComanda" class="modal">
    <div class="modal-content">
        <h3>Abrir Comanda</h3>
        <input type="text" id="comanda_numero" placeholder="Nº da comanda ou Nome cliente">
        <div class="acoes-modal">
            <button class="btn btn-nova" style="flex:1" onclick="salvarComanda()">Salvar</button>
            <button class="btn" style="background:#6c757d; color:#fff; flex:1" onclick="fecharModal()">Cancelar</button>
        </div>
    </div>
</div>

<script>
function irParaComanda(id) {
    window.location.href = "abrir_pedido.php?tipo=comanda&id=" + id;
}

function abrirModal() {
    document.getElementById('modalComanda').style.display = 'flex';
    document.getElementById('comanda_numero').focus();
}

function fecharModal() {
    document.getElementById('modalComanda').style.display = 'none';
    document.getElementById('comanda_numero').value = '';
}

function salvarComanda() {
    const numero = document.getElementById('comanda_numero').value;
    if(!numero) return alert("Digite uma identificação!");

    // Envia para o processamento via POST
    fetch('ajax_salvar_comanda.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'numero=' + encodeURIComponent(numero)
    })
    .then(response => response.json())
    .then(data => {
        if(data.sucesso) {
            location.reload();
        } else {
            alert("Erro: " + data.mensagem);
        }
    })
    .catch(err => alert("Erro ao processar requisição."));
}
</script>

</body>
</html>