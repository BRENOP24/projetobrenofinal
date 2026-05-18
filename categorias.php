<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php'; 
require_once 'config/funcoes.php';

$mensagem = "";

// Lógica para Salvar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_salvar'])) {
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);
    $exibir_online = $_POST['exibir_online'] ?? 'S';

    if (empty($nome)) {
        $mensagem = "<div style='color:red; padding:10px; background:#f8d7da; border-radius:5px; margin-bottom:15px;'>O nome da categoria é obrigatório!</div>";
    } else {
        try {
            $sql = "INSERT INTO categorias (nome, descricao, exibir_online) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql); 
            if ($stmt->execute([$nome, $descricao, $exibir_online])) {
                registrarLog($pdo, 'INSERT', 'categorias', "Cadastrou a categoria: $nome");
                $mensagem = "<div style='color:green; padding:10px; background:#d4edda; border-radius:5px; margin-bottom:15px;'>Categoria cadastrada com sucesso!</div>";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23505) {
                $mensagem = "<div style='color:red; padding:10px; background:#f8d7da; border-radius:5px; margin-bottom:15px;'>Erro de sincronização de ID. Ajuste a SEQUENCE no banco.</div>";
            } else {
                $mensagem = "<div style='color:red; padding:10px; background:#f8d7da; border-radius:5px; margin-bottom:15px;'>Erro ao cadastrar: " . $e->getMessage() . "</div>";
            }
        }
    }
}

// Busca as categorias
try {
    $query = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC");
    $categorias = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categorias = []; 
    $mensagem = "<div style='color:orange; margin-bottom:15px;'>Aviso: Verifique a tabela 'categorias' no banco.</div>";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Categorias - Gestão Breno</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Estilo para manter o padrão dos botões que você gostou */
        .btn-voltar-estilo {
            text-decoration: none;
            font-weight: 500;
            background: #6c757d;
            color: white !important;
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 14px;
        }
        .btn-voltar-estilo:hover { background: #5a6268; }
    </style>
</head>
<body>

<div class="container" style="max-width:1000px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 2px solid #f0f2f5; padding-bottom: 15px;">
        <h2 style="margin:0; color: #333;">📂 Cadastro de Categorias</h2>
        <a href="dashboard.php" class="btn-voltar-estilo">⬅ Voltar ao Painel</a>
    </div>
      
    <?= $mensagem ?>

    <form method="POST" style="background: #f8fafc; padding: 25px; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 35px;">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label><strong>Nome da Categoria:</strong></label>
                <input type="text" name="nome" placeholder="Ex: Bebidas, Doces" required style="width: 100%; padding: 12px; margin-top: 8px; border-radius: 6px; border: 1px solid #cbd5e0; box-sizing: border-box;">
            </div>
            <div>
                <label><strong>Disponível Online?</strong></label>
                <select name="exibir_online" style="width: 100%; padding: 12px; margin-top: 8px; border-radius: 6px; border: 1px solid #cbd5e0; background: white; cursor: pointer;">
                    <option value="S">Sim (Visível)</option>
                    <option value="N">Não (Oculta)</option>
                </select>
            </div>
        </div>
        <div style="margin-bottom: 20px;">
            <label><strong>Descrição:</strong></label>
            <textarea name="descricao" rows="2" style="width: 100%; padding: 12px; margin-top: 8px; border-radius: 6px; border: 1px solid #cbd5e0; box-sizing: border-box; resize: vertical;"></textarea>
        </div>
        <button type="submit" name="btn_salvar" style="background: #28a745; color: white; padding: 14px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; font-size: 16px;">
            Salvar Categoria
        </button>
    </form>

    <h3 style="color: #4a5568; margin-bottom: 15px;">Lista de Categorias</h3>
    <table width="100%" style="border-collapse: collapse; background: white; border: 1px solid #e2e8f0;">
        <thead style="background: #edf2f7; color: #4a5568;">
            <tr>
                <th style="padding: 15px; text-align: left; border-bottom: 2px solid #cbd5e0; width: 60px;">ID</th>
                <th style="padding: 15px; text-align: left; border-bottom: 2px solid #cbd5e0;">Nome</th>
                <th style="padding: 15px; text-align: left; border-bottom: 2px solid #cbd5e0;">Descrição</th>
                <th style="padding: 15px; text-align: center; border-bottom: 2px solid #cbd5e0; width: 100px;">Online</th>
                <th style="padding: 15px; text-align: center; border-bottom: 2px solid #cbd5e0; width: 120px;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($categorias)): ?>
                <?php foreach ($categorias as $cat): ?>
                <tr style="border-bottom: 1px solid #edf2f7;">
                    <td style="padding: 12px; text-align: center; color: #718096;"><?= $cat['id'] ?></td>
                    <td style="padding: 12px; font-weight: 600; color: #2d3748;"><?= htmlspecialchars($cat['nome']) ?></td>
                    <td style="padding: 12px; color: #718096; font-size: 14px;"><?= htmlspecialchars($cat['descricao'] ?: '-') ?></td>
                    <td style="padding: 12px; text-align: center;">
                        <?= $cat['exibir_online'] == 'S' ? '<span title="Visível">✅</span>' : '<span title="Oculto">❌</span>' ?>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <a href="editar_categoria.php?id=<?= $cat['id'] ?>" style="text-decoration: none; margin-right: 10px;">✏️</a>
                        <a href="excluir_categoria.php?id=<?= $cat['id'] ?>" style="text-decoration: none;" onclick="return confirm('Tem certeza que deseja excluir esta categoria?')">🗑️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="padding: 30px; text-align: center; color: #a0aec0;">Nenhuma categoria encontrada.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>