<?php
require_once 'config/sessao.php';
require_once 'config/funcoes.php';

// 🔒 Só garçom entra
exigirNivel(['garcom']);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Painel do Garçom</title>

<style>
body {
    font-family: 'Segoe UI', Arial, sans-serif; /* Fonte mais moderna */
    background: #f4f6f9;
    margin: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh; /* min-height é melhor que height para telas pequenas */
}

.container {
    width: 95%; /* Ocupa quase toda a largura em telas pequenas */
    max-width: 1000px;
    margin: 20px auto;
    text-align: center;
}

.grid {
    display: grid;
    /* DINÂMICO: Cria colunas de no mínimo 200px que preenchem o espaço disponível */
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
    gap: 20px;
    margin-top: 30px;
    padding: 10px;
}

.card {
    background: #fff;
    padding: 30px;
    border-radius: 10px;
    border: 1px solid #ddd;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05); /* Sombra leve para profundidade */
    cursor: pointer;
    transition: all 0.3s ease; /* Transição mais suave */
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.card:hover {
    background: #28a745;
    color: #fff;
    transform: translateY(-5px); /* Efeito de flutuar ao passar o mouse */
    box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
}

a {
    text-decoration: none;
    color: inherit;
    display: block; /* Garante que o link ocupe o card todo */
}

/* Ajuste para telas muito pequenas (Mobile) */
@media (max-width: 480px) {
    .grid {
        grid-template-columns: 1fr; /* Força 1 coluna em celulares pequenos */
    }
}
</style>

</head>
<body>

<div class="container">

    <h2>👨‍🍳 Painel do Garçom</h2>
    <p><?= $_SESSION['usuario_nome'] ?></p>

    <div class="grid">

        <a href="mesas.php">
            <div class="card">🍽️ Mesas</div>
        </a>

        <a href="comandas.php">
            <div class="card">📋 Comandas</div>
        </a>

    </div>

    <br><br>
    <a href="sair.php">Sair</a>

</div>

</body>
</html>