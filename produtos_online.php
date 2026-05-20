<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php'; 
require_once 'config/funcoes.php';

$mensagem = "";

/* ===============================
   FILTROS DE BUSCA (Ajustados)
================================ */
$filtro = $_GET['filtro'] ?? 'todos';
$busca_nome = $_GET['busca_nome'] ?? '';
$busca_categoria = $_GET['busca_categoria'] ?? '';

$condicaoFiltro = "";
$params = [];

// Filtro por status de visibilidade
if ($filtro === 'visiveis') {
    $condicaoFiltro .= " AND p.aparecer_online = 'S' ";
} elseif ($filtro === 'ocultos') {
    $condicaoFiltro .= " AND p.aparecer_online = 'N' ";
}

// Filtro por nome do produto
if (!empty($busca_nome)) {
    $condicaoFiltro .= " AND p.nome ILIKE :busca_nome "; // Usado ILIKE para PostgreSQL (ou LIKE se for MySQL)
    $params[':busca_nome'] = "%" . $busca_nome . "%";
}

// Filtro por ID da categoria
if (!empty($busca_categoria)) {
    $condicaoFiltro .= " AND p.categoria_id = :busca_categoria ";
    $params[':busca_categoria'] = (int)$busca_categoria;
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
   BUSCA LISTA DE CATEGORIAS (Para o Filtro Dropdown)
================================ */
try {
    $sql_cat = "SELECT id, nome FROM categorias ORDER BY nome ASC";
    $stmt_cat = $pdo->query($sql_cat);
    $lista_categorias = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lista_categorias = [];
}

/* ===============================
   BUSCA PRODUTOS FILTRADOS
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

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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
    display: inline-block;
}
.ativo {
    background: #007bff;
    color: #fff;
}
.inativo {
    background: #e9ecef;
    color: #333;
}
.form-filtro-texto {
    background: #fdfdfd;
    border: 1px solid #e2e8f0;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.input-busca {
    padding: 8px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    margin-right: 10px;
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

<div style="margin-bottom:15px;">
    <a href="?filtro=todos&busca_nome=<?= urlencode($busca_nome) ?>&busca_categoria=<?= urlencode($busca_categoria) ?>" 
       class="filtro-btn <?= $filtro == 'todos' ? 'ativo' : 'inativo' ?>">
       Todos
    </a>

    <a href="?filtro=visiveis&busca_nome=<?= urlencode($busca_nome) ?>&busca_categoria=<?= urlencode($busca_categoria) ?>" 
       class="filtro-btn <?= $filtro == 'visiveis' ? 'ativo' : 'inativo' ?>">
       ✅ Visíveis
    </a>

    <a href="?filtro=ocultos&busca_nome=<?= urlencode($busca_nome) ?>&busca_categoria=<?= urlencode($busca_categoria) ?>" 
       class="filtro-btn <?= $filtro == 'ocultos' ? 'ativo' : 'inativo' ?>">
       ❌ Ocultos
    </a>
</div>

<div class="form-filtro-texto">
    <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="filtro" value="<?= htmlspecialchars($filtro) ?>">
        
        <div style="display: flex; flex-direction: column; flex: 2; min-width: 200px;">
            <label style="font-size: 13px; font-weight: bold; margin-bottom: 5px;">Nome do Produto</label>
            <input type="text" name="busca_nome" class="input-busca" placeholder="Ex: Pizza, Hambúrguer..." value="<?= htmlspecialchars($busca_nome) ?>">
        </div>

        <div style="display: flex; flex-direction: column; flex: 1; min-width: 200px;">
            <label style="font-size: 13px; font-weight: bold; margin-bottom: 5px;">Filtrar por Categoria</label>
            <select name="busca_categoria" class="input-busca" style="width: 100%;">
                <option value="">Todas as Categorias</option>
                <?php foreach ($lista_categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $busca_categoria == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <button type="submit" style="background: #28a745; color: white; border: none; padding: 9px 20px; border-radius: 4px; font-weight: bold; cursor: pointer;">
                🔍 Filtrar
            </button>
            <?php if (!empty($busca_nome) || !empty($busca_categoria)): ?>
                <a href="?filtro=<?= htmlspecialchars($filtro) ?>" style="background: #6c757d; color: white; text-decoration: none; padding: 9px 15px; border-radius: 4px; font-weight: bold; margin-left: 5px; display: inline-block;">
                    Limpar
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (count($produtos) > 0): ?>
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
<?php else: ?>
    <div style="text-align: center; padding: 40px; color: #666; background: #fff; border: 1px dashed #ccc; border-radius: 8px;">
        Nenhum produto encontrado com os filtros selecionados.
    </div>
<?php endif; ?>

</div>
</body>
</html>
