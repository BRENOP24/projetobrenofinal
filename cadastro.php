<?php 
require_once 'config/conexao.php'; 

$mensagem = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'];
    $user_login = $_POST['usuario'];
    // Criptografando a senha para segurança
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $nivel = $_POST['nivel'];

    try {
        // Verificando se o usuário já existe antes de inserir
        $check = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $check->execute([$user_login]);
        
        if ($check->rowCount() > 0) {
            $mensagem = "<div class='msg' style='background:#f8d7da; color:#721c24;'>❌ Este login já está em uso!</div>";
        } else {
            $sql = "INSERT INTO usuarios (nome, usuario, senha, nivel) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $user_login, $senha, $nivel]);
            $mensagem = "<div class='msg msg-success'>✅ Usuário $nome cadastrado com sucesso! <a href='index.php'>Ir para Login</a></div>";
        }
    } catch (PDOException $e) {
        $mensagem = "<div class='msg' style='background:#f8d7da; color:#721c24;'>❌ Erro no banco de dados.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastro de Usuário - Projeto Breno</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="container" style="max-width: 500px; margin-top: 50px;">
    <h2>Novo Usuário</h2>
    <p style="color: #666;">Crie uma conta para acessar o sistema de gestão.</p>
    
    <?= $mensagem ?>

    <form method="POST">
        <div class="form-group">
            <label>Nome Completo</label>
            <input type="text" name="nome" placeholder="Ex: Breno Andrade" required>
        </div>
        <div class="form-group">
            <label>Login (Usuário)</label>
            <input type="text" name="usuario" placeholder="Ex: breno.admin" required>
        </div>
        <div class="form-group">
            <label>Senha</label>
            <input type="password" name="senha" placeholder="Mínimo 6 caracteres" required>
        </div>
        <div class="form-group">
            <label>Nível de Permissão</label>
            <select name="nivel">
                <option value="user">Operador / Vendas</option>
                <option value="admin">Administrador / Financeiro</option>
            </select>
        </div>
        <button type="submit" style="background: #28a745;">Finalizar Cadastro</button>
    </form>

    <div style="margin-top: 20px; text-align: center;">
        <a href="index.php" style="color: #666; text-decoration: none;">← Voltar para o Login</a>
    </div>
</div>

</body>
</html>