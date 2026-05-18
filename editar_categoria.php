<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$mensagem = "";

// 1. Busca os dados atuais para preencher o formulário
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = ?");
    $stmt->execute([$id]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$categoria) {
        die("Categoria não encontrada.");
    }
}

// 2. Processa a atualização (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_atualizar'])) {
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);
    $exibir_online = $_POST['exibir_online'] ?? 'S'; // Novo campo capturado
    $id_edit = $_POST['id'];

    if (!empty($nome)) {
        try {
            // SQL atualizado para incluir exibir_online
            $sql = "UPDATE categorias SET nome = ?, descricao = ?, exibir_online = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$nome, $descricao, $exibir_online, $id_edit])) {
                header("Location: categorias.php?sucesso=editado");
                exit;
            }
        } catch (PDOException $e) {
            $mensagem = "<div style='color:red; padding:10px; background:#f8d7da; border-radius:5px;'>Erro ao atualizar: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Categoria - Gestão Breno</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container" style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <nav style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
            <a href="categorias.php" style="text-decoration: none; color: #666; font-weight: bold;">⬅ Cancelar e Voltar</a>
        </nav>

        <h2>✏️ Editar Categoria</h2>
        <?= $mensagem ?>

        <form method="POST" style="background: #fffbe6; padding: 20px; border: 1px solid #ffe58f; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <input type="hidden" name="id" value="<?= $categoria['id'] ?>">
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label><strong>Nome da Categoria:</strong></label><br>
                    <input type="text" name="nome" value="<?= htmlspecialchars($categoria['nome']) ?>" required 
                           style="width: 100%; padding: 10px; margin-top: 5px; border-radius: 4px; border: 1px solid #ccc;">
                </div>
                <div>
                    <label><strong>Disponível Online?</strong></label><br>
                    <select name="exibir_online" style="width: 100%; padding: 10px; margin-top: 5px; border-radius: 4px; border: 1px solid #ccc; background: white;">
                        <option value="S" <?= $categoria['exibir_online'] == 'S' ? 'selected' : '' ?>>Sim (Visível)</option>
                        <option value="N" <?= $categoria['exibir_online'] == 'N' ? 'selected' : '' ?>>Não (Oculta)</option>
                    </select>
                </div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label><strong>Descrição:</strong></label><br>
                <textarea name="descricao" rows="3" 
                          style="width: 100%; padding: 10px; margin-top: 5px; border-radius: 4px; border: 1px solid #ccc;"><?= htmlspecialchars($categoria['descricao']) ?></textarea>
            </div>

            <button type="submit" name="btn_atualizar" 
                    style="background: #007bff; color: white; padding: 12px 25px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%;">
                Salvar Alterações
            </button>
        </form>
    </div>
</body>
</html>