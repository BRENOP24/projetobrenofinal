<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$busca     = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$filter    = isset($_GET['filter']) ? $_GET['filter'] : 'todos';
$categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';

// 1. Buscar a lista de categorias da tabela 'categorias' para o select
try {
    $stmt_cat = $pdo->query("SELECT id, nome FROM categorias ORDER BY nome ASC");
    $lista_categorias = $stmt_cat->fetchAll();
} catch (Exception $e) {
    $lista_categorias = [];
}

// 2. Query Principal com JOIN para pegar o nome da categoria
$sql = "SELECT 
            p.id, 
            p.codigo_barras, 
            p.nome, 
            p.estoque, 
            c.nome as nome_categoria,
            (SELECT COALESCE(SUM(quantidade), 0) FROM compras_itens WHERE id_produto = p.id) as total_entradas,
            (SELECT COALESCE(SUM(quantidade), 0) FROM pedidos_itens WHERE produto_id = p.id) as total_saidas
        FROM produtos p
LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE 1=1";

// Filtro de Busca por texto/ID/Código de barras
if (!empty($busca)) {
    $sql .= " AND (p.nome ILIKE ? OR p.codigo_barras LIKE ? OR p.id::text LIKE ?)";
}

// Filtro por Categoria (usando o ID)
if (!empty($categoria)) {
    $sql .= " AND p.categoria_id = " . (int)$categoria;
}

// Filtro por Situação de Estoque
if ($filter == 'zerado') {
    $sql .= " AND p.estoque <= 0";
} elseif ($filter == 'baixo') {
    $sql .= " AND p.estoque > 0 AND p.estoque <= 5";
}

$sql .= " ORDER BY p.nome ASC";

try {
    $stmt = $pdo->prepare($sql);
    
    // Se houver busca, passa os parâmetros, senão executa array vazio
    if (!empty($busca)) {
        $stmt->execute(["%$busca%", "%$busca%", "%$busca%"]);
    } else {
        $stmt->execute();
    }
    
    $produtos = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erro Crítico: " . $e->getMessage());
}

// Cálculos de KPI
$total_itens = 0;
foreach ($produtos as $prod) {
    $total_itens += (float)($prod['estoque'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Estoque - Gestão Breno</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important; background: #f4f6f9 !important; padding: 20px !important; }
        .container { max-width: 1200px !important; margin: auto !important; background: #fff !important; padding: 30px !important; border-radius: 15px !important; box-shadow: 0 5px 20px rgba(0,0,0,0.1) !important; }
        
        .search-box { background: #f1f5f9 !important; padding: 30px !important; border-radius: 12px !important; margin: 25px 0 !important; border: 2px solid #e2e8f0 !important; }
        
        .form-flex { display: flex !important; gap: 10px !important; width: 100% !important; align-items: center !important; margin-bottom: 20px !important; }
        .form-flex input[type="text"] { flex: 2 !important; height: 60px !important; padding: 0 20px !important; font-size: 1.1rem !important; border: 2px solid #3498db !important; border-radius: 10px !important; }
        .form-flex select { flex: 0.8 !important; height: 60px !important; padding: 0 15px !important; font-size: 1rem !important; border: 2px solid #3498db !important; border-radius: 10px !important; background: white !important; }

        .btn { height: 60px !important; padding: 0 25px !important; border-radius: 10px !important; font-weight: bold !important; cursor: pointer !important; border: none !important; display: inline-flex !important; align-items: center; justify-content: center; }
        .btn-primary { background: #3498db !important; color: white !important; }
        .btn-secondary { background: #64748b !important; color: white !important; text-decoration: none !important; }

        .filter-nav { display: flex !important; gap: 10px !important; align-items: center; font-size: 0.9rem; }
        .filter-btn { padding: 8px 18px !important; border-radius: 20px !important; text-decoration: none !important; color: #64748b !important; background: #e2e8f0 !important; font-weight: bold !important; }
        .filter-btn.active { background: #3498db !important; color: white !important; }

        .kpi-container { display: flex !important; gap: 20px !important; margin-bottom: 30px !important; }
        .kpi-card { flex: 1 !important; padding: 20px !important; border-radius: 12px !important; color: white !important; }
        .bg-blue { background: #3498db !important; }

        .table-estoque { width: 100% !important; border-collapse: collapse !important; }
        .table-estoque th { background: #f8fafc !important; padding: 15px !important; border-bottom: 2px solid #dee2e6 !important; text-align: left !important; font-size: 0.8rem; color: #64748b; }
        .table-estoque td { padding: 15px !important; border-bottom: 1px solid #eee !important; }
        
        .badge { padding: 6px 10px !important; border-radius: 6px !important; color: white !important; font-weight: bold !important; font-size: 11px !important; }
        .status-ok { background: #27ae60 !important; }
        .status-baixo { background: #f39c12 !important; }
        .status-esgotado { background: #e74c3c !important; }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2>📊 Relatório Geral de Estoque</h2>
        <a href="dashboard.php" class="btn btn-secondary" style="height: 40px !important;">← Voltar</a>
    </div>

    <div class="kpi-container">
        <div class="kpi-card bg-blue">
            <small>ESTOQUE NO FILTRO</small>
            <h2 style="margin:0;"><?= $total_itens ?> un</h2>
        </div>
    </div>

    <div class="search-box">
        <form method="GET" class="form-flex">
            <input type="hidden" name="filter" value="<?= $filter ?>">
            
            <input type="text" name="busca" placeholder="Produto, Código ou ID..." value="<?= htmlspecialchars($busca) ?>">
            
            <select name="categoria">
                <option value="">Todas as Categorias</option>
                <?php foreach($lista_categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoria == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary">BUSCAR</button>
            <button type="button" onclick="window.print()" class="btn btn-secondary" style="margin-left:5px;">🖨️</button>
        </form>

        <div class="filter-nav">
            <strong>Rápido:</strong>
            <a href="?busca=<?= urlencode($busca) ?>&categoria=<?= $categoria ?>&filter=todos" class="filter-btn <?= $filter == 'todos' ? 'active' : '' ?>">Todos</a>
            <a href="?busca=<?= urlencode($busca) ?>&categoria=<?= $categoria ?>&filter=baixo" class="filter-btn <?= $filter == 'baixo' ? 'active' : '' ?>">Baixo Estoque</a>
            <a href="?busca=<?= urlencode($busca) ?>&categoria=<?= $categoria ?>&filter=zerado" class="filter-btn <?= $filter == 'zerado' ? 'active' : '' ?>">Zerados</a>
        </div>
    </div>

    <table class="table-estoque">
        <thead>
            <tr>
                <th>CÓDIGO</th>
                <th>CATEGORIA</th>
                <th>PRODUTO</th>
                <th style="text-align:center;">ENTRADAS</th>
                <th style="text-align:center;">VENDAS</th>
                <th style="text-align:center;">SALDO</th>
                <th>STATUS</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($produtos) > 0): ?>
                <?php foreach ($produtos as $p): 
                    $v_atual = (float)$p['estoque'];
                    $status_class = "status-ok"; $label = "OK";
                    if($v_atual <= 0) { $status_class = "status-esgotado"; $label = "ZERADO"; }
                    elseif($v_atual <= 5) { $status_class = "status-baixo"; $label = "BAIXO"; }
                ?>
                <tr>
                    <td>#<?= $p['codigo_barras'] ?: $p['id'] ?></td>
                    <td><small style="color:#64748b"><?= htmlspecialchars($p['nome_categoria'] ?: 'Sem Categoria') ?></small></td>
                    <td><strong><?= htmlspecialchars($p['nome']) ?></strong></td>
                    <td style="color:green; text-align:center;">+<?= (float)$p['total_entradas'] ?></td>
                    <td style="color:red; text-align:center;">-<?= (float)$p['total_saidas'] ?></td>
                    <td style="text-align:center;"><strong><?= $v_atual ?></strong></td>
                    <td><span class="badge <?= $status_class ?>"><?= $label ?></span></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 30px; color: #64748b;">Nenhum produto encontrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
