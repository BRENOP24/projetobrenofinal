<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// 1. LÓGICA DE EXCLUSÃO
if (isset($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND id != ?"); 
    // O 'id != ?' evita que o admin logado se exclua por acidente (opcional)
    $stmt->execute([$id, $_SESSION['usuario_id']]);
    header("Location: usuarios.php?msg=excluido");
    exit;
}

// 2. LÓGICA DE SALVAR EDIÇÃO (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $nome = $_POST['nome'];
    $usuario = $_POST['usuario'];
    $nivel = $_POST['nivel'];

    // Se preencheu a senha, atualiza ela também, senão mantém a antiga
    if (!empty($_POST['senha'])) {
        $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET nome=?, usuario=?, nivel=?, senha=? WHERE id=?");
        $stmt->execute([$nome, $usuario, $nivel, $senha, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE usuarios SET nome=?, usuario=?, nivel=? WHERE id=?");
        $stmt->execute([$nome, $usuario, $nivel, $id]);
    }

    header("Location: usuarios.php?msg=atualizado");
    exit;
}

// 3. BUSCAR DADOS PARA O FORMULÁRIO DE EDIÇÃO
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    header("Location: usuarios.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuário - Gestão Breno</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="container" style="max-width:600px;">
    <div class="box-empresa">
        <a href="usuarios.php" class="voltar-dashboard">← Voltar à Lista</a>
        <h2>✏️ Editar Usuário</h2>

        <form method="POST" action="usuario_acoes.php" style="background:#f9f9f9; padding:20px; border-radius:8px; border:1px solid #ddd;">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">

            <label>Nome Completo:</label>
            <input type="text" name="nome" value="<?= $u['nome'] ?>" required style="width:100%; margin-bottom:15px; padding:8px;">

            <label>Usuário (Login):</label>
            <input type="text" name="usuario" value="<?= $u['usuario'] ?>" required style="width:100%; margin-bottom:15px; padding:8px;">

            <label>Nível de Acesso:</label>
            <select name="nivel" style="width:100%; margin-bottom:15px; padding:8px;">
                <option value="user" <?= $u['nivel'] == 'user' ? 'selected' : '' ?>>Usuário Comum</option>
                <option value="admin" <?= $u['nivel'] == 'admin' ? 'selected' : '' ?>>Administrador</option>
            </select>

            <label>Nova Senha (deixe em branco para não alterar):</label>
            <input type="password" name="senha" style="width:100%; margin-bottom:15px; padding:8px;">

            <button type="submit" style="background:#007bff; color:#fff; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:bold; width:100%;">
                Salvar Alterações
            </button>
        </form>
    </div>
</div>

</body>
</html>