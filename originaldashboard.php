<?php 
require_once 'config/sessao.php';
require_once 'config/funcoes.php';

// 🔒 Garçom não acessa dashboard
if ($_SESSION['nivel'] === 'garcom') {
    header("Location: garcom.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Gestão Breno</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .nav-menu {
            display: flex;
            gap: 15px;
            list-style: none;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #ddd;
            flex-wrap: wrap;
        }

        .nav-item { position: relative; }

        .nav-link {
            text-decoration: none;
            color: #333;
            padding: 12px 20px;
            display: block;
            font-weight: 600;
            border-radius: 6px;
            background: #ffffff;
            border: 1px solid #ccc;
            transition: 0.2s;
        }

        .nav-link:hover { background: #e9ecef; }

        .submenu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: #ffffff;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            list-style: none;
            padding: 8px 0;
            min-width: 260px;
            z-index: 100;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .submenu li a {
            text-decoration: none;
            color: #444;
            padding: 10px 20px;
            display: block;
            border-bottom: 1px solid #f1f1f1;
            transition: 0.2s;
            font-weight: 500;
        }

        .submenu li:last-child a { border-bottom: none; }

        .submenu li a:hover {
            background: #f8f9fa;
            color: #007bff;
            padding-left: 25px;
        }

        .nav-item:hover .submenu { display: block; }

        .btn-sair {
            background: #f8d7da !important;
            color: #721c24 !important;
            border-color: #f5c6cb !important;
            font-weight: 700;
        }

        .btn-admin {
            background: #343a40 !important;
            color: #ffffff !important;
        }

        .highlight-item {
            color: #28a745 !important;
            font-weight: 700;
        }
    </style>
</head>

<body>
<div class="container">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h2>Bem-vindo, <?= $_SESSION['usuario_nome'] ?>!</h2>
            <p>Perfil: <strong><?= strtoupper($_SESSION['nivel']) ?></strong></p>
        </div>
    </div>

    <nav>
        <ul class="nav-menu">

            <!-- CADASTROS -->
            <?php if ($_SESSION['nivel'] !== 'garcom'): ?>
            <li class="nav-item">
                <a href="#" class="nav-link">📝 Cadastros ▼</a>
                <ul class="submenu">
                    <li><a href="cadastro_bandeiras.php">🏷️ Bandeiras</a></li>
                    <li><a href="categorias.php">📂 Categorias</a></li>
                    <li><a href="clientes.php">👥 Clientes</a></li>
                    <li><a href="clientes_online.php">👥 Clientes Online</a></li>
                    <li><a href="empresa.php">🏢 Empresa</a></li>
                    <li><a href="fornecedores.php">🏭 Fornecedores</a></li>
                    <li><a href="pagamentos.php">💳 Formas de Pagamento</a></li>
                    <li><a href="plano_contas.php">📊 Plano de Contas</a></li>
                    <li><a href="produtos.php">📦 Produtos</a></li>

                    <?php if ($_SESSION['nivel'] === 'admin'): ?>
                        <li><a href="usuarios.php">👥 Usuários Sistema</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>

            <!-- FATURAMENTO -->
            <li class="nav-item">
                <a href="#" class="nav-link">💰 Faturamento ▼</a>
                <ul class="submenu">
                    <li><a href="caixas.php">🏧 Abertura / Fechamento</a></li>
                    <li><a href="pedidos.php">🛒 Pedidos / Vendas</a></li>
                        <?php if ($_SESSION['nivel'] !== 'garcom'): ?>
                        <li><a href= "fechamento_com_mesa.php">🍽️Mesas / Comandas</a></li>
                        <li><a href="pedidos_online.php">🛒 Pedidos Online</a></li>
                        <li><a href="pedido_compra.php">🛒 Pedido de Compra - Fornecedor</a></li>
                        <li><a href="entrada_mercadoria.php">📦 Entrada de Mercadoria</a></li>
                        <li><a href="gerenciar_entrada.php">📦 Gerenciar Compras</a></li>
                        <li><a href="gerenciar_pedidos_compra.php">⚙️ Gerenciar Pedidos de Compra</a></li>
                        <li><a href="gerenciar_pedidos.php">📑 Gerenciar Vendas</a></li>
                        
                    <?php endif; ?>

                </ul>
            </li>

            <!-- FINANCEIRO -->
            <?php if ($_SESSION['nivel'] === 'admin'): ?>
            <li class="nav-item">
                <a href="#" class="nav-link">🏦 Financeiro ▼</a>
                <ul class="submenu">
                    <li><a href="conferencia_caixas.php">🕵️ Conferência de Caixas</a></li>
                    <li><a href="contas_receber.php">📑 Contas a Receber</a></li>
                    <li><a href="contas_pagar.php">📉 Contas a Pagar</a></li>
                    <li><a href="gerenciamento_cartoes.php">💳 Gerenciamento de Cartões</a></li>
                    <li><a href="movimento_caixa.php">🔄 Movimento Geral</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- RELATÓRIOS -->
            <?php if ($_SESSION['nivel'] !== 'garcom'): ?>
            <li class="nav-item">
                <a href="#" class="nav-link">📊 Relatórios ▼</a>
                <ul class="submenu">
                    <li><a href="rel_vendas.php">📈 Vendas por Período</a></li>
                    <li><a href="rel_pedidos_online.php">📈 Vendas Pedidos Online</a></li>
                    <li><a href="rel_estoque.php">📦 Posição de Estoque</a></li>
                    <li><a href="rel_financeiro_geral.php">💸 Resumo Financeiro (DRE)</a></li>
                    <li><a href="rel_curva.php">👥 Curva ABC de Clientes</a></li>
                    <li><a href="rel_pagamentos.php">💳 Vendas por Meio de Pagamento</a></li>
                    <li><a href="rel_contas_receber.php">💰Contas Receber + Pagar</a></li>
                    <li><a href= "rel_mesa_comanda.php">🍽️Mesas / Comandas</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- INTEGRAÇÕES -->
            <?php if ($_SESSION['nivel'] !== 'garcom'): ?>
            <li class="nav-item">
                <a href="#" class="nav-link">🔌 Integrações ▼</a>
                <ul class="submenu">
                    <li><a href="cardapio_online.php">🍽️ Cardápio Online</a></li>
                    <li><a href="config_loja.php">🏪 Configuração</a></li>
                    <li><a href="produtos_online.php">🌐 Produtos Online</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- ADMIN -->
            <?php if ($_SESSION['nivel'] === 'admin'): ?>
                <li class="nav-item">
                    <a href="logs.php" class="nav-link btn-admin">📋 Logs</a>
                </li>
            <?php endif; ?>

            <!-- SAIR -->
            <li class="nav-item">
                <a href="sair.php" class="nav-link btn-sair">Sair</a>
            </li>

        </ul>
    </nav>

    <hr style="margin:40px 0; opacity:0.2;">

    <div class="dashboard-content">
        <p>Selecione uma opção no menu acima para começar.</p>
    </div>

</div>
</body>
</html>