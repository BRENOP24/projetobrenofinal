<?php 
require_once 'config/sessao.php';
require_once 'config/funcoes.php';

if ($_SESSION['nivel'] === 'garcom') {
    header("Location: garcom.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gestão Breno</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Reset e Estrutura Base */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { background: #f1f5f9; overflow: hidden; }

        .layout {
            display: flex;
            height: 100vh;
            width: 100vw;
        }

        /* Sidebar Dinâmica */
        .sidebar {
            width: 260px;
            background: #1e293b;
            color: white;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .logo {
            padding: 25px 20px;
            font-size: 1.3rem;
            text-align: center;
            background: #0f172a;
            color: #38bdf8;
            border-bottom: 1px solid #334155;
        }

        .nav-container {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        .nav-menu { list-style: none; }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: 0.2s;
        }

        .nav-link:hover { background: #334155; color: white; }

        .nav-link i:first-child { width: 25px; margin-right: 10px; }
        .arrow { margin-left: auto; font-size: 0.8rem; transition: 0.3s; }

        /* Submenu Acordeão */
        .submenu {
            list-style: none;
            padding-left: 35px;
            display: none; /* Controlado pelo JS */
            margin-bottom: 10px;
        }

        .submenu li a {
            display: block;
            padding: 8px 10px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.9rem;
            transition: 0.2s;
            border-radius: 4px;
        }

        .submenu li a:hover { color: #38bdf8; background: rgba(56, 189, 248, 0.1); }

        /* Área de Conteúdo */
        .content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .topo {
            height: 70px;
            background: #fff;
            display: flex;
            align-items: center;
            padding: 0 30px;
            justify-content: space-between;
            border-bottom: 1px solid #e2e8f0;
        }

        .user-info h2 { font-size: 1.1rem; color: #1e293b; }
        .badge { background: #38bdf8; color: white; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; }

        /* Iframe Dinâmico (estilo de card) */
        .dashboard-content {
            flex: 1;
            padding: 20px;
            background: #f1f5f9;
        }

        iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 12px;
            background: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        .btn-sair { color: #f87171 !important; font-weight: bold; }
        
        .site-footer {
            padding: 10px;
            text-align: center;
            font-size: 0.75rem;
            background: #0f172a;
            color: #64748b;
        }
    </style>
</head>
<body>

<div class="layout">

    <aside class="sidebar">
        <h2 class="logo">Gestão Empresa - Say Now</h2>

        <nav class="nav-container">
            <ul class="nav-menu">
                
                <?php if ($_SESSION['nivel'] !== 'garcom'): ?>
                <li class="nav-item">
                    <a href="javascript:void(0)" class="nav-link dropdown-btn"><i class="fas fa-edit"></i> Cadastros <i class="fas fa-chevron-down arrow"></i></a>
                    <ul class="submenu">
                        <li><a href="cadastro_bandeiras.php" target="conteudo">🏷️ Bandeiras</a></li>
                        <li><a href="categorias.php" target="conteudo">📂 Categorias</a></li>
                        <li><a href="clientes.php" target="conteudo">👥 Clientes</a></li>
                        <li><a href="clientes_online.php" target="conteudo">👥 Clientes Online</a></li>
                        <li><a href="empresa.php" target="conteudo">🏢 Empresa</a></li>
                        <li><a href="fornecedores.php" target="conteudo">🏭 Fornecedores</a></li>
                        <li><a href="pagamentos.php" target="conteudo">💳 Formas de Pagamento</a></li>
                        <li><a href="motoboy.php" target="conteudo">🏍️ Motoboy</a></li>
                        <li><a href="plano_contas.php" target="conteudo">📊 Plano de Contas</a></li>
                        <li><a href="produtos.php" target="conteudo">📦 Produtos</a></li>
                        <?php if ($_SESSION['nivel'] === 'admin'): ?>
                            <li><a href="usuarios.php" target="conteudo">👥 Usuários Sistema</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a href="javascript:void(0)" class="nav-link dropdown-btn"><i class="fas fa-cash-register"></i> Faturamento <i class="fas fa-chevron-down arrow"></i></a>
                    <ul class="submenu">
                        <li><a href="caixas.php" target="conteudo">🏧 Abertura / Fechamento</a></li>
                        <li><a href="gerenciar_sangrias.php" target="conteudo">💰 Sangrias</a></li>
                        <li><a href="pedidos.php" target="conteudo">🛒 Pedidos / Vendas</a></li>
                        <?php if ($_SESSION['nivel'] !== 'garcom'): ?>
                            <li><a href="expedicao_manual.php" target="conteudo">🏍️ Expedição Pedido (Motoboy) - Manual</a></li>
                            <li><a href="fechamento_com_mesa.php" target="conteudo">🍽️ Mesas / Comandas</a></li>
                            <li><a href="pedidos_online.php" target="conteudo">🛒 Pedidos Online</a></li>
                            <li><a href="pedido_compra.php" target="conteudo">🛒 Pedido de Compra</a></li>
                            <li><a href="entrada_mercadoria.php" target="conteudo">📦 Entrada Mercadoria</a></li>
                            <li><a href="gerenciar_entrada.php" target="conteudo">📦 Gerenciar Compras</a></li>
                            <li><a href="gerenciar_pedidos_compra.php" target="conteudo">⚙️ Pedidos Compra</a></li>
                            <li><a href="gerenciar_pedidos.php" target="conteudo">📑 Gerenciar Vendas</a></li>
                        <?php endif; ?>
                    </ul>
                </li>

                <?php if ($_SESSION['nivel'] === 'admin'): ?>
                <li class="nav-item">
                    <a href="javascript:void(0)" class="nav-link dropdown-btn"><i class="fas fa-university"></i> Financeiro <i class="fas fa-chevron-down arrow"></i></a>
                    <ul class="submenu">
                        <li><a href="conferencia_caixas.php" target="conteudo">🕵️ Conferência Caixas</a></li>
                        <li><a href="auditoria_caixas.php" target="conteudo">🛡️ Auditoria de Caixa - Relatorios</a></li>
                        <li><a href="relatorio_dfc.php" target="conteudo">📊 Demonstração do Fluxo de Caixa (DFC)</a></li>
                        <li><a href="estorno_vendas.php" target="conteudo">🔄 Estorno de Caixas Conferidos - Cancelamentos</a></li>

                        
                        <li><a href="contas_receber.php" target="conteudo">📑 Contas a Receber</a></li>
                        <li><a href="contas_pagar.php" target="conteudo">📉 Contas a Pagar</a></li>
                        <li><a href="gerenciamento_cartoes.php" target="conteudo">💳 Cartões</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a href="javascript:void(0)" class="nav-link dropdown-btn"><i class="fas fa-plug"></i> Integrações <i class="fas fa-chevron-down arrow"></i></a>
                    <ul class="submenu">
                        <li><a href="cardapio_online.php" target="conteudo">🍽️ Cardápio Online</a></li>
                        <li><a href="config_loja.php" target="conteudo">🏪 Configuração</a></li>
                        <li><a href="produtos_online.php" target="conteudo">🌐 Produtos Online</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="javascript:void(0)" class="nav-link dropdown-btn"><i class="fas fa-chart-line"></i> Relatórios <i class="fas fa-chevron-down arrow"></i></a>
                    <ul class="submenu">
                        <li><a href="rel_vendas.php" target="conteudo">📈 Vendas Período</a></li>
                        <li><a href="rel_pedidos_online.php" target="conteudo">📈 Vendas Online</a></li>
                        <li><a href="rel_estoque.php" target="conteudo">📦 Posição Estoque</a></li>
                        <li><a href="itens_online_venda.php" target="conteudo">📦 Estoque Vendas Online</a></li>
                        <li><a href="rel_financeiro_geral.php" target="conteudo">💸 Resumo (DRE)</a></li>
                        <li><a href="rel_curva.php" target="conteudo">👥 Curva ABC</a></li>
                        <li><a href="rel_pagamentos.php" target="conteudo">💳 Meios Pagamento</a></li>
                        <li><a href="rel_conciliacao_cartao.php" target="conteudo">💳 Conciliação Cartões</a></li>
                        <li><a href="rel_contas_receber.php" target="conteudo">💰 Contas Rec + Pag</a></li>
                        <li><a href="motoboy_taxas.php" target="conteudo">🏍️ Motoboy Taxas</a></li>
                        <li><a href="rel_mesa_comanda.php" target="conteudo">🍽️ Mesas / Comandas</a></li>
                        <li><a href="logs.php" target="conteudo">🗑️ Logs Sistema</a></li>
                    </ul>
                </li>

                <li class="nav-item" style="margin-top: 20px;">
                    <a href="sair.php" class="nav-link btn-sair">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </li>
            </ul>
        </nav>
        <footer class="site-footer">
            <p>&copy; <?= date('Y'); ?> - Breno Paz</p>
        </footer>
    </aside>

    <main class="content">
        <header class="topo">
            <div class="user-info">
                <h2>Bem-vindo, <?= $_SESSION['usuario_nome'] ?>!</h2>
                <p>Perfil: <span class="badge"><?= strtoupper($_SESSION['nivel']) ?></span></p>
            </div>
        </header>

        <section class="dashboard-content">
            <iframe src="home.php" name="conteudo"></iframe>
        </section>
    </main>

</div>

<script>
    document.querySelectorAll('.dropdown-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const submenu = this.nextElementSibling;
            const arrow = this.querySelector('.arrow');
            const isVisible = submenu.style.display === "block";
            
            // Fecha todos os outros antes de abrir o atual
            document.querySelectorAll('.submenu').forEach(s => s.style.display = "none");
            document.querySelectorAll('.arrow').forEach(a => a.style.transform = "rotate(0deg)");
            
            if (!isVisible) {
                submenu.style.display = "block";
                arrow.style.transform = "rotate(180deg)";
            }
        });
    });
</script>
</body>
</html>
