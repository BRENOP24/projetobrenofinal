<?php
require_once 'config/conexao.php';
require_once 'config/sessao.php';
require_once 'config/funcoes.php';

exigirNivel(['garcom']);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Painel do Garçom</title>

<style>
:root {
    --bg-principal: #f8fafc;
    --card-bg: #ffffff;
    --texto-principal: #1e293b;
    --texto-secundario: #64748b;
    --cor-primaria: #10b981; /* Verde esmeralda moderno */
    --cor-perigo: #ef4444; /* Vermelho suave para o botão Sair */
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
    --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    --shadow-lg: 0 10px 15px -3px rgba(16, 185, 129, 0.2);
}

body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background-color: var(--bg-principal);
    color: var(--texto-principal);
    margin: 0;
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: flex-start; /* Melhor para rolar a tela se crescer */
    min-height: 100vh;
}

.container {
    width: 100%;
    max-width: 600px; /* Um tamanho ideal para painéis mobile/tablet */
    margin: 0 auto;
    padding: 24px 16px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* Cabeçalho */
header {
    text-align: center;
    margin-bottom: 32px;
}

header h2 {
    font-size: 1.8rem;
    margin: 0 0 8px 0;
    color: var(--texto-principal);
    font-weight: 700;
}

header p {
    margin: 0;
    font-size: 1rem;
    color: var(--cor-primaria);
    font-weight: 600;
    background: rgba(16, 185, 129, 0.1);
    padding: 6px 16px;
    border-radius: 20px;
    display: inline-block;
}

/* Grid de Navegação */
.grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr); /* 2 Colunas no PC/Celular padrão */
    gap: 16px;
    flex-grow: 1; /* Empurra o botão sair para o rodapé */
}

.grid a {
    text-decoration: none;
    color: inherit;
    display: block;
}

.card {
    background: var(--card-bg);
    padding: 32px 16px;
    border-radius: 16px;
    box-shadow: var(--shadow-sm);
    border: 1px solid #e2e8f0;
    text-align: center;
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--texto-principal);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    gap: 12px;
    align-items: center;
    justify-content: center;
    -webkit-tap-highlight-color: transparent; /* Remove o flash azul do clique no celular */
}

/* Efeito de hover (PC) e active (Celular) */
.card:hover {
    transform: translateY(-4px);
    border-color: var(--cor-primaria);
    box-shadow: var(--shadow-lg);
}

.card:active {
    transform: translateY(1px);
    background-color: #f0fdf4; /* Leve fundo verde ao tocar */
}

/* Estilizando os ícones dentro dos cards */
.card span {
    font-size: 2.5rem;
    display: block;
}

/* Rodapé / Botão Sair */
.footer {
    margin-top: 40px;
    text-align: center;
}

.btn-sair {
    display: inline-block;
    width: 100%;
    max-width: 200px;
    padding: 12px;
    background: transparent;
    border: 2px solid #cbd5e1;
    color: var(--texto-secundario);
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-sair:hover {
    border-color: var(--cor-perigo);
    color: var(--cor-perigo);
    background: #fef2f2;
}

/* Responsividade para telas muito pequenas */
@media (max-width: 400px) {
    .grid {
        grid-template-columns: 1fr; /* Vira 1 coluna se o celular for muito pequeno */
    }
    
    .card {
        padding: 24px 16px;
        flex-direction: row; /* Ícone do lado do texto em telas mini */
        justify-content: flex-start;
        padding-left: 30px;
    }
    
    .card span {
        font-size: 1.8rem;
    }
}
</style>

</head>
<body>

<div class="container">

    <header>
        <h2>👨‍🍳 Painel do Garçom</h2>
        <p>Olá, <?= htmlspecialchars($_SESSION['usuario_nome']) ?></p>
    </header>

    <div class="grid">

        <a href="mesas.php">
            <div class="card">
                <span>🍽️</span> Mesas
            </div>
        </a>

        <a href="comandas.php">
            <div class="card">
                <span>📋</span> Comandas
            </div>
        </a>

    </div>

    <div class="footer">
        <a href="sair.php" class="btn-sair">Sair do Sistema</a>
    </div>

</div>

</body>
</html>
