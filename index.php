<?php
require_once 'config/conexao.php';
session_start();

$erro = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'];
    $senha = $_POST['senha'];

    $sql = "SELECT * FROM usuarios WHERE usuario = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario]);
    $user = $stmt->fetch();

    if ($user && password_verify($senha, $user['senha'])) {
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario_nome'] = $user['nome'];
        $_SESSION['nivel'] = $user['nivel'];

        // Primeiro inclua o arquivo de funções no topo do index.php
        require_once 'config/funcoes.php';

        // Dentro do IF onde o login tem sucesso:
        registrarLog($pdo, 'LOGIN', 'usuarios', "O usuário " . $user['nome'] . " acessou o sistema.");
        
        header("Location: dashboard.php"); // Direciona para o painel principal
        exit();
    } else {
        $erro = "Usuário ou senha inválidos!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso ao Sistema - Breno Sistemas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="main-wrapper">
        <div class="login-box animate-pop-in">
            
            <header class="login-header">
                <div class="logo-icon">
                    <i class="fas fa-satellite-dish"></i> </div>
                <h1>EMPRESA SAY NOW</h1>
                <p class="subtitle">Sistema de Gestão Integrado</p>
            </header>

            <?php if(isset($erro) && $erro): ?>
                <div class="error-banner">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= $erro ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <div class="input-container">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="usuario" placeholder="Nome de usuário" required>
                </div>
                
                <div class="input-container">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="senha" placeholder="Sua senha segura" required>
                </div>

                <button type="submit" class="btn-primary">
                    <span>Acessar Sistema</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

        </div>
    </div>
</body>
</html>
