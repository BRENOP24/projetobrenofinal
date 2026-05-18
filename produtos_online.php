<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php'; 
require_once 'config/funcoes.php';

$mensagem = "";

/* ===============================
   FILTRO
================================ */
$filtro = $_GET['filtro'] ?? 'todos';
$condicaoFiltro = "";

if ($filtro === 'visiveis') {
    $condicaoFiltro = " AND p.aparecer_online = 'S' ";
} elseif ($filtro === 'ocultos') {
    $condicaoFiltro = " AND p.aparecer_online = 'N' ";
}

/* ===============================
   ATUALIZAÇÃO
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_atualizar_online'])) {
    $id = $_POST['id_produto'];
    $status_online = $_POST['aparecer_online'];
    $obs = trim($_POST['obs_online']);

    try {
        $sql = "UPDATE produtos 
                SET aparecer_online = ?, obs_online = ? 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$status_online, $obs, $id])) {
            $mensagem = "<div style='color:green; padding:10px; background:#d4edda; border-radius:5px; margin-bottom:15px;'>
                            Produto atualizado com sucesso!
                         </div>";
        }
    } catch (PDOException $e) {
        $mensagem = "<div style='color:red; padding:10px; background:#f8d7da; margin-bottom:15px;'>
                        Erro: " . $e->getMessage() . "
                     </div>";
    }
}

/* ===============================
   BUSCA PRODUTOS ORDENADOS
================================ */
try {
    $sql = "SELECT p.*, 
                   COALESCE(c.nome, 'Sem Categoria') as nome_categoria
            FROM produtos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            WHERE p.status = 'Ativo'
            $condicaoFiltro
            ORDER BY 
                nome_categoria ASC,
                p.nome ASC";

    $stmt = $pdo->query($sql);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $produtos = [];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Gestão Cardápio Online</title>
<link rel="stylesheet" href="css/style.css">
<style>
.card-produto {
    background: #fff;
    border: 1px solid #ddd;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 8px;
    display: grid;
    grid-template-columns: 80px 1fr 200px 150px;
    gap: 20px;
    align-items: center;
}
.categoria-header {
    background: #e9ecef;
    padding: 10px;
    margin: 20px 0 10px 0;
    border-radius: 4px;
    font-weight: bold;
}
.filtro-btn {
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    margin-right: 10px;
}
.ativo {
    background: #007bff;
    color: #fff;
}
.inativo {
    background: #e9ecef;
    color: #333;
}
</style>
</head>

<body>
<div class="container" style="max-width:1100px; margin:0 auto; padding:20px;">

<div style="display:flex; justify-content:space-between; align-items:center;">
    <h2>🌐 Controle de Visibilidade no Cardápio</h2>
    <a href="dashboard.php" style="text-decoration:none; font-weight:bold;">⬅ Voltar</a>
</div>

<p>Gerencie quais produtos aparecem no cardápio online.</p>

<?= $mensagem ?>

<!-- FILTROS -->
<div style="margin-bottom:20px;">
    <a href="?filtro=todos" 
       class="filtro-btn <?= $filtro == 'todos' ? 'ativo' : 'inativo' ?>">
       Todos
    </a>

    <a href="?filtro=visiveis" 
       class="filtro-btn <?= $filtro == 'visiveis' ? 'ativo' : 'inativo' ?>">
       ✅ Visíveis
    </a>

    <a href="?filtro=ocultos" 
       class="filtro-btn <?= $filtro == 'ocultos' ? 'ativo' : 'inativo' ?>">
       ❌ Ocultos
    </a>
</div>

<?php 
$ultima_cat = "";
foreach ($produtos as $p): 
    if ($p['nome_categoria'] !== $ultima_cat): 
        $ultima_cat = $p['nome_categoria'];
?>
<div class="categoria-header">
    📂 Categoria: <?= htmlspecialchars($ultima_cat) ?>
</div>
<?php endif; ?>

<div class="card-produto">

<div>
<?php if($p['imagem']): ?>
<img src="uploads/produtos/<?= htmlspecialchars($p['imagem']) ?>" 
     width="70" height="70" 
     style="object-fit:cover; border-radius:5px;">
<?php else: ?>
<div style="width:70px; height:70px; background:#eee; text-align:center; line-height:70px; font-size:10px;">
Sem foto
</div>
<?php endif; ?>
</div>

<div>
<strong><?= htmlspecialchars($p['nome']) ?></strong><br>
<span style="color:#28a745;">
R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?>
</span>
</div>

<form method="POST" style="display: contents;">
<input type="hidden" name="id_produto" value="<?= $p['id'] ?>">

<div>
<label style="font-size:12px;">Obs. no Cardápio:</label>
<input type="text" 
       name="obs_online" 
       value="<?= htmlspecialchars($p['obs_online'] ?? '') ?>" 
       style="width:100%; padding:5px;">
</div>

<div style="text-align:right;">
<select name="aparecer_online" 
        style="padding:5px; margin-bottom:5px; width:100%;">
<option value="S" <?= $p['aparecer_online'] == 'S' ? 'selected' : '' ?>>✅ Visível</option>
<option value="N" <?= $p['aparecer_online'] == 'N' ? 'selected' : '' ?>>❌ Oculto</option>
</select>

<button type="submit" 
        name="btn_atualizar_online"
        style="background:#007bff; color:white; border:none; padding:5px 10px; border-radius:4px; cursor:pointer; width:100%;">
Atualizar
</button>
</div>
</form>

</div>
<?php endforeach; ?>

</div>
</body>
</html>