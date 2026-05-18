<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'];
    $usuario = $_POST['usuario'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $nivel = $_POST['nivel'];

    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, usuario, senha, nivel) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nome, $usuario, $senha, $nivel]);
        header("Location: usuarios.php?msg=sucesso");
    } catch (PDOException $e) {
        $erro = "Erro: Este nome de usuário já existe!";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Novo Usuário - Gestão Breno</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="container" style="max-width:600px;">
    <div class="box-empresa">
        <a href="usuarios.php" class="voltar-dashboard">← Voltar à Lista</a>
        <h2>➕ Cadastrar Novo Usuário</h2>

        <?php if(isset($erro)): ?>
            <p style="color:red;"><?= $erro ?></p>
        <?php endif; ?>

        <form method="POST" style="background:#f9f9f9; padding:20px; border-radius:8px; border:1px solid #ddd;">
            <label>Nome Completo:</label>
            <input type="text" name="nome" required style="width:100%; margin-bottom:15px; padding:8px;">

            <label>Usuário (Login):</label>
            <input type="text" name="usuario" required style="width:100%; margin-bottom:15px; padding:8px;">

            <label>Senha:</label>
            <input type="password" name="senha" required style="width:100%; margin-bottom:15px; padding:8px;">

            <label>Nível de Acesso:</label>
            <select name="nivel" style="width:100%; margin-bottom:15px; padding:8px;">
                <option value="user">Usuário Comum</option>
                <option value="admin">Administrador</option>
            </select>

            <button type="submit" style="background:#28a745; color:#fff; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:bold; width:100%;">
                Cadastrar Usuário
            </button>
        </form>
    </div>
</div>

</body>
</html>