<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$mensagem = "";

// 1. BUSCA OS DADOS DO PRODUTO
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
    $stmt->execute([$id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        die("Produto não encontrado.");
    }
} else {
    header("Location: produtos.php");
    exit;
}

// 2. LÓGICA DE ATUALIZAÇÃO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_atualizar'])) {
    $id_edit       = $_POST['id'];
    $codigo_barras = trim($_POST['codigo_barras'] ?? "");
    $nome           = trim($_POST['nome'] ?? "");
    $preco          = str_replace(',', '.', $_POST['preco'] ?? "0"); 
    $estoque        = $_POST['estoque'] ?? 0;
    $categoria      = $_POST['categoria_id'] ?? "";
    $online         = $_POST['aparecer_online'] ?? "N";
    $descricao      = trim($_POST['descricao'] ?? "");
    $unidade        = $_POST['unidade_medida'] ?? "UN";
    
    // Mantém a imagem atual por padrão
    $imagem_final = $produto['imagem'];

    // Verifica se uma nova imagem foi enviada
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === 0) {
        $extensao = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
        $novo_nome = md5(uniqid()) . "." . $extensao;
        $diretorio = "uploads/produtos/";

        if (!is_dir($diretorio)) mkdir($diretorio, 0777, true);

        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $diretorio . $novo_nome)) {
            // Se subiu a nova com sucesso, podemos apagar a antiga do servidor (opcional)
            if (!empty($produto['imagem']) && file_exists($diretorio . $produto['imagem'])) {
                @unlink($diretorio . $produto['imagem']);
            }
            $imagem_final = $novo_nome;
        }
    }

    try {
        $sql = "UPDATE produtos SET 
                codigo_barras = ?, 
                nome = ?, 
                preco_venda = ?, 
                estoque = ?, 
                categoria_id = ?, 
                aparecer_online = ?, 
                descricao = ?,
                unidade_medida = ?,
                imagem = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$codigo_barras, $nome, $preco, $estoque, $categoria, $online, $descricao, $unidade, $imagem_final, $id_edit])) {
            header("Location: produtos.php?res=editado");
            exit;
        }
    } catch (PDOException $e) {
        $mensagem = "<div style='color:red; padding:10px; background:#f8d7da; border-radius:5px;'>Erro ao atualizar: " . $e->getMessage() . "</div>";
    }
}

$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Produto - Gestão Breno</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container" style="max-width: 800px; margin: 0 auto; padding: 20px;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
            <h2 style="margin: 0;">✏️ Editar Produto</h2>
            <a href="produtos.php" style="text-decoration: none; color: #666; font-weight: bold;">⬅ Cancelar e Voltar</a>
        </div>

        <?= $mensagem ?>

        <form method="POST" enctype="multipart/form-data" style="background: #fff; padding: 25px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <input type="hidden" name="id" value="<?= $produto['id'] ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label><strong>Código de Barras:</strong></label>
                    <input type="text" name="codigo_barras" value="<?= htmlspecialchars($produto['codigo_barras']) ?>" 
                           style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div>
                    <label><strong>Nome do Produto:</strong></label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($produto['nome']) ?>" required 
                           style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div>
                    <label><strong>Preço (R$):</strong></label>
                    <input type="text" name="preco" value="<?= number_format($produto['preco_venda'], 2, ',', '.') ?>" required 
                           style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div>
                    <label><strong>Unidade:</strong></label>
                    <select name="unidade_medida" style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px;">
                        <?php 
                        $unidades = ['UN', 'KG', 'LT', 'PC', 'CX', 'GR', 'ML'];
                        foreach($unidades as $un): ?>
                            <option value="<?= $un ?>" <?= $produto['unidade_medida'] == $un ? 'selected' : '' ?>><?= $un ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label><strong>Estoque:</strong></label>
                    <input type="number" name="estoque" value="<?= $produto['estoque'] ?>" 
                           style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div>
                    <label><strong>Online?</strong></label>
                    <select name="aparecer_online" style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="N" <?= $produto['aparecer_online'] == 'N' ? 'selected' : '' ?>>Não</option>
                        <option value="S" <?= $produto['aparecer_online'] == 'S' ? 'selected' : '' ?>>Sim</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label><strong>Categoria:</strong></label>
                    <select name="categoria_id" required style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px;">
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $produto['categoria_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label><strong>Alterar Foto:</strong></label>
                    <input type="file" name="imagem" accept="image/*" style="width: 100%; padding: 8px; margin-top: 5px;">
                </div>
            </div>

            <div style="margin-bottom: 20px; display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex-grow: 1;">
                    <label><strong>Descrição:</strong></label>
                    <textarea name="descricao" rows="3" style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px;"><?= htmlspecialchars($produto['descricao']) ?></textarea>
                </div>
                <?php if(!empty($produto['imagem'])): ?>
                <div style="text-align: center;">
                    <label><strong>Imagem Atual:</strong></label><br>
                    <img src="uploads/produtos/<?= $produto['imagem'] ?>" style="width: 100px; height: 100px; object-fit: cover; border: 1px solid #ddd; border-radius: 8px; margin-top: 5px;">
                </div>
                <?php endif; ?>
            </div>

            <button type="submit" name="btn_atualizar" style="background: #007bff; color: white; padding: 15px; border: none; border-radius: 6px; cursor: pointer; width: 100%; font-weight: bold; font-size: 16px;">
                💾 Salvar Alterações
            </button>
        </form>
    </div>
</body>
</html>