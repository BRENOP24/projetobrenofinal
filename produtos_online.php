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

if ($filtro === 'visiveis') {
    $condicaoFiltro .= " AND p.aparecer_online = 'S' ";
} elseif ($filtro === 'ocultos') {
    $condicaoFiltro .= " AND p.aparecer_online = 'N' ";
}

if (!empty($busca_nome)) {
    $condicaoFiltro .= " AND p.nome ILIKE :busca_nome "; 
    $params[':busca_nome'] = "%" . $busca_nome . "%";
}

if (!empty($busca_categoria)) {
    $condicaoFiltro .= " AND p.categoria_id = :busca_categoria ";
    $params[':busca_categoria'] = (int)$busca_categoria;
}

/* ===============================
   ATUALIZAÇÃO (Preço Exclusivo Online)
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_atualizar_online'])) {
    $id = $_POST['id_produto'];
    $status_online = $_POST['aparecer_online'];
    $obs = trim($_POST['obs_online']);
    
    // Tratamento do preço online vindo do formulário
    $preco_online = str_replace(',', '.', $_POST['preco_online']); 
    $preco_online = !empty($preco_online) ? floatval($preco_online) : 0.00;

    try {
        // ATENÇÃO: Alterado de preco_venda para preco_online para NÃO mexer no preço físico!
        $sql = "UPDATE produtos 
                SET aparecer_online = ?, obs_online = ?, preco_online = ? 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$status_online, $obs, $preco_online, $id])) {
            $mensagem = "<div style='color:green; padding:10px; background:#d4edda; border-radius:5px; margin-bottom:15px;'>
                            Produto atualizado com sucesso no cardápio online!
                         </div>";
        }
    } catch (PDOException $e) {
        $mensagem = "<div style='color:red; padding:10px; background:#f8d7da; margin-bottom:15px;'>
                        Erro: " . $e->getMessage() . "
                     </div>";
    }
}

/* ===============================
   BUSCA LISTA DE CATEGORIAS
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
    /* Grid reajustado para acomodar o preço físico (exibição) e preço online (input) */
    grid-template-columns: 80px 1.2fr 100px 110px 1.5fr 140px;
    gap: 15px;
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
<div class="container" style="max-width:1200px; margin:0 auto; padding:20px;">

<div style="display:flex; justify-content:space-between; align-items:center;">
    <h2>🌐 Controle de Visibilidade e Preço Online</h2>
    <a href="dashboard.php" style="text-decoration:none; font-weight:bold;">⬅ Voltar</a>
</div>

<p>Defina preços e visibilidade exclusivos para o ambiente online sem afetar o preço físico do estabelecimento.</p>

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
        <?php if(!empty($p['imagem'])): ?>
            <?php 
               $caminho_imagem = (strpos($p['imagem'], 'http') === 0) ? $p['imagem'] : "uploads/produtos/" . $p['imagem']; 
            ?>
            <img src="<?= htmlspecialchars($caminho_imagem) ?>" 
                 width="70" height="70" 
                 style="object-fit:cover; border-radius:5px;"
                 onerror="this.onerror=null; this.src='img/sem-foto.png';">
        <?php else: ?>
            <div style="width:70px; height:70px; background:#eee; text-align:center; line-height:70px; font-size:10px; border-radius:5px; color:#666;">
            Sem foto
            </div>
        <?php endif; ?>
        </div>

        <div>
            <strong><?= htmlspecialchars($p['nome']) ?></strong>
        </div>

        <div>
            <span style="font-size:11px; color:#6c757d; display:block;">Preço Físico:</span>
            <span style="font-weight:bold; color:#333;">R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?></span>
        </div>

        <form method="POST" style="display: contents;">
        <input type="hidden" name="id_produto" value="<?= $p['id'] ?>">

        <div>
            <label style="font-size:11px; font-weight: bold; display:block; margin-bottom:3px; color:#007bff;">Preço Online:</label>
            <?php 
                $valor_online_inicial = (!empty($p['preco_online']) && $p['preco_online'] > 0) ? $p['preco_online'] : $p['preco_venda'];
            ?>
            <input type="number" 
                   name="preco_online" 
                   value="<?= $valor_online_inicial ?>" 
                   step="0.01" 
                   min="0.00"
                   style="width:100%; padding:5px; border:1px solid #007bff; border-radius:4px; font-weight:bold; color:#007bff; background:#f4f9ff;">
        </div>

        <div>
            <label style="font-size:11px; display:block; margin-bottom:3px;">Obs. no Cardápio:</label>
            <input type="text" 
                   name="obs_online" 
                   value="<?= htmlspecialchars($p['obs_online'] ?? '') ?>" 
                   style="width:100%; padding:5px; border:1px solid #cbd5e1; border-radius:4px;">
        </div>

        <div style="text-align:right;">
            <select name="aparecer_online" 
                    style="padding:5px; margin-bottom:5px; width:100%; border:1px solid #cbd5e1; border-radius:4px;">
                <option value="S" <?= $p['aparecer_online'] == 'S' ? 'selected' : '' ?>>✅ Visível</option>
                <option value="N" <?= $p['aparecer_online'] == 'N' ? 'selected' : '' ?>>❌ Oculto</option>
            </select>

            <button type="submit" 
                    name="btn_atualizar_online"
                    style="background:#007bff; color:white; border:none; padding:5px 10px; border-radius:4px; cursor:pointer; width:100%; font-weight:bold;">
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
