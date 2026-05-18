<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$mensagem = "";

// --- 1. LÓGICA PARA SALVAR (Mantida igual) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['btn_salvar'])) {
        $codigo_barras = trim($_POST['codigo_barras'] ?? "");
        $nome           = trim($_POST['nome'] ?? "");
        $preco          = str_replace(',', '.', $_POST['preco'] ?? "0"); 
        $estoque        = $_POST['estoque'] ?? 0;
        $categoria      = $_POST['categoria_id'] ?? "";
        $online         = $_POST['aparecer_online'] ?? "N";
        $descricao      = trim($_POST['descricao'] ?? "");
        $unidade        = $_POST['unidade_medida'] ?? "UN";
        $imagem_nome    = null;

        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === 0) {
            $extensao = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
            $novo_nome = md5(uniqid()) . "." . $extensao;
            $diretorio = "uploads/produtos/";
            if (!is_dir($diretorio)) mkdir($diretorio, 0777, true);
            if (move_uploaded_file($_FILES['imagem']['tmp_name'], $diretorio . $novo_nome)) {
                $imagem_nome = $novo_nome;
            }
        }

        try {
            $sql = "INSERT INTO produtos (codigo_barras, nome, preco_venda, estoque, categoria_id, aparecer_online, descricao, unidade_medida, imagem, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Ativo')";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$codigo_barras, $nome, $preco, $estoque, $categoria, $online, $descricao, $unidade, $imagem_nome])) {
                $mensagem = "<div style='color:green; padding:10px; background:#d4edda; border-radius:5px; margin-bottom:15px;'>Produto cadastrado com sucesso!</div>";
            }
        } catch (PDOException $e) {
            $mensagem = "<div style='color:red; padding:10px; background:#f8d7da; margin-bottom:15px;'>Erro ao salvar: " . $e->getMessage() . "</div>";
        }
    }

    if (isset($_POST['btn_inativar'])) {
        $id_inativar = $_POST['id_produto'];
        $pdo->prepare("UPDATE produtos SET status = 'Inativo' WHERE id = ?")->execute([$id_inativar]);
        $mensagem = "<div style='color:orange; padding:10px; background:#fff3cd; border-radius:5px; margin-bottom:15px;'>Produto movido para inativos!</div>";
    }
}

// --- 2. LÓGICA DE FILTRO ATUALIZADA ---
$busca        = isset($_GET['busca']) ? trim($_GET['busca']) : "";
$filtro_cat   = isset($_GET['filtro_categoria']) ? $_GET['filtro_categoria'] : "";
$ver_inativos = (isset($_GET['status']) && $_GET['status'] == 'Inativo') ? 'Inativo' : 'Ativo';

$sql_lista = "SELECT p.*, c.nome as nome_categoria 
              FROM produtos p 
              LEFT JOIN categorias c ON p.categoria_id = c.id 
              WHERE p.status = ?";
$params = [$ver_inativos];

if (!empty($busca)) {
    // Usando ILIKE para o Postgres não diferenciar maiúsculas/minúsculas
    $sql_lista .= " AND (p.nome ILIKE ? OR p.codigo_barras = ?)";
    $params[] = "%$busca%";
    $params[] = $busca;
}

if (!empty($filtro_cat)) {
    $sql_lista .= " AND p.categoria_id = ?";
    $params[] = (int)$filtro_cat; // Garante que seja um inteiro
}

$sql_lista .= " ORDER BY p.codigo_barras ASC LIMIT 100";
$stmt = $pdo->prepare($sql_lista);
$stmt->execute($params);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Produtos - Breno</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container" style="max-width: 1150px; margin: 0 auto; padding: 20px;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
            <h2 style="margin: 0;">📦 Gestão de Produtos</h2>
           <a href="dashboard.php" class="btn-voltar" style="text-decoration:none;font-weight:500;">⬅ Voltar ao Painel</a>
        </div>

        <?= $mensagem ?>

        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 30px;">
            <form method="POST" enctype="multipart/form-data">
                <div style="display: grid; grid-template-columns: 1fr 2fr 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div><label><strong>Cód. Barras:</strong></label><input type="text" name="codigo_barras" style="width: 100%; padding: 8px;"></div>
                    <div><label><strong>Nome do Produto:</strong></label><input type="text" name="nome" required style="width: 100%; padding: 8px;"></div>
                    <div><label><strong>Preço (R$):</strong></label><input type="text" name="preco" required style="width: 100%; padding: 8px;"></div>
                    <div><label><strong>Unidade:</strong></label>
                        <select name="unidade_medida" style="width: 100%; padding: 8px;">
                            <option value="UN">UN</option><option value="KG">KG</option><option value="LT">LT</option>
                        </select>
                    </div>
                    <div><label><strong>Estoque:</strong></label><input type="number" name="estoque" value="0" style="width: 100%; padding: 8px;"></div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label><strong>Categoria:</strong></label>
                        <select name="categoria_id" required style="width: 100%; padding: 8px;">
                            <option value="">-- Selecione --</option>
                            <?php foreach ($categorias as $cat): ?><option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div><label><strong>Cardápio Online?</strong></label>
                        <select name="aparecer_online" style="width: 100%; padding: 8px;"><option value="N">Não</option><option value="S">Sim</option></select>
                    </div>
                    <div><label><strong>Foto do Produto:</strong></label><input type="file" name="imagem" accept="image/*" style="width: 100%; padding: 5px;"></div>
                </div>
                <button type="submit" name="btn_salvar" style="background: #28a745; color: white; padding: 12px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-weight: bold;">Gravar Produto</button>
            </form>
        </div>

        <div style="background: #e9ecef; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-end;">
            <form method="GET" style="display: flex; gap: 10px; width: 100%;">
                <div style="flex: 2;">
                    <label style="font-size: 12px; font-weight: bold;">Buscar por Nome ou Código:</label>
                    <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Ex: Coca Cola..." style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                </div>
                <div style="flex: 1;">
                    <label style="font-size: 12px; font-weight: bold;">Filtrar Categoria:</label>
                    <select name="filtro_categoria" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                        <option value="">Todas as Categorias</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($filtro_cat == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 5px;">
                    <button type="submit" style="background: #007bff; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Filtrar</button>
                    <a href="produtos.php" style="background: #6c757d; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-size: 13px; line-height: 20px;">Limpar</a>
                </div>
            </form>
        </div>

        <table border="1" width="100%" style="border-collapse: collapse; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <thead style="background: #343a40; color: white;">
                <tr>
                    <th style="padding: 12px;">Foto</th>
                    <th style="padding: 12px;">Cód. Barras</th>
                    <th style="padding: 12px;">Produto</th>
                    <th style="padding: 12px;">Preço</th>
                    <th style="padding: 12px;">Estoque / Un.</th>
                    <th style="padding: 12px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($produtos) > 0): ?>
                    <?php foreach ($produtos as $p): ?>
                    <tr>
                        <td style="padding: 5px; text-align: center;">
                            <?php if($p['imagem']): ?>
                                <img src="uploads/produtos/<?= $p['imagem'] ?>" width="50" height="50" style="object-fit: cover; border-radius: 4px;">
                            <?php else: ?>
                                <div style="width: 50px; height: 50px; background: #eee; margin: 0 auto; line-height: 50px; font-size: 10px; color: #aaa;">S/ Foto</div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px; text-align: center; font-family: monospace;"><?= $p['codigo_barras'] ?></td>
                        <td style="padding: 10px;">
                            <strong><?= htmlspecialchars($p['nome']) ?></strong><br>
                            <small style="color:#777;"><?= htmlspecialchars($p['nome_categoria'] ?? 'Sem Categoria') ?></small>
                        </td>
                        <td style="padding: 10px; text-align: right;">R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?></td>
                        <td style="padding: 10px; text-align: center; font-weight: bold;">
                            <span style="color: <?= $p['estoque'] <= 0 ? 'red' : 'green' ?>;"><?= $p['estoque'] ?></span> 
                            <small style="color: #666;"><?= $p['unidade_medida'] ?></small>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <div style="display: flex; gap: 15px; justify-content: center; align-items: center;">
                                <a href="editar_produto.php?id=<?= $p['id'] ?>" title="Editar">✏️</a>
                                <form method="POST" onsubmit="return confirm('Inativar?')" style="margin: 0;">
                                    <input type="hidden" name="id_produto" value="<?= $p['id'] ?>">
                                    <button type="submit" name="btn_inativar" style="background:none; border:none; cursor:pointer; color:orange; font-size: 18px;">🚫</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="padding: 20px; text-align: center; color: #999;">Nenhum produto encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>