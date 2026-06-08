<?php
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

if ($_SESSION['nivel'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Filtros de data (Padrão: início do mês até hoje)
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');

// Busca apenas caixas CONFERIDOS no período
$sql_caixas = "
    SELECT c.*, u.nome as operador 
    FROM controle_caixas c 
    JOIN usuarios u ON c.usuario_id = u.id 
    WHERE c.status = 'conferido' 
    AND DATE(c.data_fechamento) BETWEEN :inicio AND :fim
    ORDER BY c.data_fechamento DESC
";
$stmt = $pdo->prepare($sql_caixas);
$stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
$caixas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Estorno de Vendas - Gestão Breno</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Centralização de variáveis para CSS Dinâmico */
        :root {
            --primary-color: #007bff;
            --danger-color: #dc3545;
            --bg-light: #f8f9fa;
            --border-color: #dee2e6;
            --text-muted: #666;
            --radius: 8px;
            --btn-padding-y: 6px;
            --btn-padding-x: 14px;
        }

        .container-fluid { max-width: 1200px; margin: auto; background: #fff; padding: 20px; border-radius: var(--radius); }
        .filter-row { display: flex; gap: 15px; align-items: flex-end; background: var(--bg-light); padding: 15px; border-radius: var(--radius); margin-bottom: 20px; border: 1px solid #e9ecef; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-size: 0.8rem; font-weight: bold; margin-bottom: 5px; }
        
        /* Botões Base */
        .btn-pequeno { padding: var(--btn-padding-y) var(--btn-padding-x); font-size: 0.75rem; border-radius: 4px; cursor: pointer; border: none; font-weight: bold; transition: opacity 0.2s; }
        .btn-pequeno:hover { opacity: 0.9; }
        
        .btn-filtrar { background: var(--primary-color); color: white; height: 35px; width: auto; }
        
        /* Botão de Estornar Corrigido: 
           width: auto !important impede que o style.css force o botão a ocupar a tela toda
        */
        .btn-cancelar { 
            background: var(--danger-color); 
            color: white; 
            padding: var(--btn-padding-y) var(--btn-padding-x) !important;
            font-size: 0.75rem !important; 
            width: auto !important; 
            min-width: 90px;
            text-align: center;
            line-height: 1;
            height: auto !important;
            display: inline-block;
        }
        
        .caixa-section { border: 1px solid var(--border-color); border-radius: var(--radius); margin-bottom: 15px; overflow: hidden; }
        .caixa-header { background: #e9ecef; padding: 10px 15px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .caixa-header:hover { background: var(--border-color); }
        
        /* Layout flexível alinhado verticalmente ao centro */
        .venda-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 15px; border-bottom: 1px solid #f1f1f1; font-size: 0.9rem; }
        .venda-row:last-child { border-bottom: none; }
        .venda-info { display: flex; gap: 20px; color: #444; align-items: center; }
        .status-badge { font-size: 0.7rem; padding: 2px 6px; border-radius: 10px; background: #eee; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="header-section" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin:0;">🔄 Estorno de Caixas Conferidos</h2>
        <a href="dashboard.php" class="btn-voltar">⬅ Voltar</a>
    </div>

    <form method="GET" class="filter-row">
        <div class="filter-group">
            <label>Data Inicial</label>
            <input type="date" name="data_inicio" value="<?= $data_inicio ?>">
        </div>
        <div class="filter-group">
            <label>Data Final</label>
            <input type="date" name="data_fim" value="<?= $data_fim ?>">
        </div>
        <button type="submit" class="btn-pequeno btn-filtrar">Filtrar Caixas</button>
    </form>

    <?php if (empty($caixas)): ?>
        <p style="text-align:center; color: var(--text-muted); padding:20px;">Nenhum caixa conferido encontrado neste período.</p>
    <?php endif; ?>

    <?php foreach ($caixas as $caixa): ?>
        <div class="caixa-section">
            <div class="caixa-header" onclick="toggleVendas(<?= $caixa['id'] ?>)">
                <span>
                    <strong>📦 Caixa #<?= $caixa['id'] ?></strong> | 
                    Operador: <?= htmlspecialchars($caixa['operador']) ?> | 
                    Fechado em: <?= date('d/m/Y H:i', strtotime($caixa['data_fechamento'])) ?>
                </span>
                <span id="seta-<?= $caixa['id'] ?>">▼</span>
            </div>
            
            <div id="vendas-caixa-<?= $caixa['id'] ?>" style="display: none; background: #fff;">
                <?php
                // Busca vendas do caixa
                $stmt_vendas = $pdo->prepare("
                    SELECT p.*, f.descricao as pgto 
                    FROM pedidos p
                    JOIN formas_pagamento f ON p.forma_pagamento_id = f.id
                    WHERE p.caixa_id = ?
                    ORDER BY p.data_pedido DESC
                ");
                $stmt_vendas->execute([$caixa['id']]);
                $vendas = $stmt_vendas->fetchAll();

                if(empty($vendas)): ?>
                    <p style="padding:15px; font-size:0.8rem; color:#999;">Sem lançamentos neste caixa.</p>
                <?php endif;

                foreach ($vendas as $v) : ?>
                    <div class="venda-row">
                        <div class="venda-info">
                            <span><strong>#<?= $v['id'] ?></strong></span>
                            <span>🕒 <?= date('H:i', strtotime($v['data_pedido'])) ?></span>
                            <span>💰 R$ <?= number_format($v['valor_total'], 2, ',', '.') ?></span>
                            <span>💳 <?= $v['pgto'] ?></span>
                            <?php if($v['status'] === 'cancelado'): ?>
                                <span class="status-badge" style="color: var(--danger-color);">CANCELADA</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($v['status'] !== 'cancelado'): ?>
                            <button class="btn-pequeno btn-cancelar" onclick="confirmarEstorno(<?= $v['id'] ?>, '<?= number_format($v['valor_total'], 2, ',', '.') ?>')">
                                Estornar
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
// Função para abrir/fechar os detalhes do caixa
function toggleVendas(id) {
    const div = document.getElementById('vendas-caixa-' + id);
    const seta = document.getElementById('seta-' + id);
    if (div.style.display === 'none') {
        div.style.display = 'block';
        seta.innerText = '▲';
    } else {
        div.style.display = 'none';
        seta.innerText = '▼';
    }
}

function confirmarEstorno(idVenda, valor) {
    if (confirm("Deseja realmente estornar a venda #" + idVenda + " de R$ " + valor + "?\n\nIsso irá:\n1. Devolver itens ao estoque\n2. Cancelar parcelas (se houver)\n3. Gerar saída no caixa atual")) {
        window.location.href = "processa_estorno.php?id=" + idVenda;
    }
}
</script>

</body>
</html>
