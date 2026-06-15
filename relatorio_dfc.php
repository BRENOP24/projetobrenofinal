<?php
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

if ($_SESSION['nivel'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// --- FILTROS DE DATA (Padrão: mês atual) ---
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');

// Convertendo para o formato de timestamp do banco
$data_inicio_db = $data_inicio . ' 00:00:00';
$data_fim_db    = $data_fim . ' 23:59:59';

// --- 1. BUSCA TODOS OS CAIXAS DO PERÍODO ---
// Buscamos caixas 'fechados' e 'conferidos' para ter a visão real de tudo que movimentou
$sql_caixas = "SELECT id FROM controle_caixas WHERE status IN ('fechado', 'conferido') AND data_fechamento BETWEEN ? AND ?";
$stmt = $pdo->prepare($sql_caixas);
$stmt->execute([$data_inicio_db, $data_fim_db]);
$ids_caixas = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Inicializa as variáveis do relatório
$faturamento_sistema = ['dinheiro' => 0, 'pix' => 0, 'credito' => 0, 'debito' => 0];
$total_vendas_prazo = 0;
$total_titulos_recebidos = 0;
$total_saidas = 0;

if (!empty($ids_caixas)) {
    $placeholders = implode(',', array_fill(0, count($ids_caixas), '?'));

    // --- 2. TOTAL DE SAÍDAS (SANGRIAS / DESPESAS) ---
    $sql_saidas = "SELECT COALESCE(SUM(valor), 0) FROM movimentacoes_caixa WHERE id_caixa IN ($placeholders) AND tipo = 'saida'";
    $stmt_s = $pdo->prepare($sql_saidas);
    $stmt_s->execute($ids_caixas);
    $total_saidas = (float)$stmt_s->fetchColumn();

    // --- 3. CONSOLIDADO DE VENDAS (LOJA + ONLINE) ---
    $sql_vendas = "
        SELECT forma_id, nome_pagto, SUM(valor_total) as total 
        FROM (
            SELECT p.forma_pagamento_id as forma_id, f.descricao as nome_pagto, p.valor_total 
            FROM pedidos p 
            JOIN formas_pagamento f ON p.forma_pagamento_id = f.id 
            WHERE p.caixa_id IN ($placeholders)
            
            UNION ALL
            
            SELECT po.forma_pagamento_id as forma_id, f.descricao as nome_pagto, po.valor_total 
            FROM pedidos_online po 
            JOIN formas_pagamento f ON po.forma_pagamento_id = f.id 
            WHERE po.id_caixa IN ($placeholders) AND po.status = 'Finalizado'
        ) as vendas_unificadas
        GROUP BY forma_id, nome_pagto
    ";
    
    // Como usamos os mesmos IDs duas vezes no UNION, duplicamos o array de parâmetros
    $params_vendas = array_merge($ids_caixas, $ids_caixas);
    $stmt_v = $pdo->prepare($sql_vendas);
    $stmt_v->execute($params_vendas);
    $vendas_banco = $stmt_v->fetchAll(PDO::FETCH_ASSOC);

    foreach ($vendas_banco as $venda) {
        $nome = mb_strtolower($venda['nome_pagto']);
        $id_f = (int)$venda['forma_id'];

        if ($id_f === 7 || str_contains($nome, 'prazo')) {
            $total_vendas_prazo += $venda['total'];
        } 
        elseif (str_contains($nome, 'dinheiro')) $faturamento_sistema['dinheiro'] += $venda['total'];
        elseif (str_contains($nome, 'pix')) $faturamento_sistema['pix'] += $venda['total'];
        elseif (str_contains($nome, 'credito') || str_contains($nome, 'cartão')) $faturamento_sistema['credito'] += $venda['total'];
        elseif (str_contains($nome, 'debito')) $faturamento_sistema['debito'] += $venda['total'];
    }

    // --- 4. CONSOLIDADO DE RECEBIMENTOS DE TÍTULOS ---
    $sql_receber = "SELECT f.descricao as nome_pagto, SUM(cr.valor_total) as total 
                    FROM contas_receber cr 
                    JOIN formas_pagamento f ON cr.id_forma_pagamento = f.id 
                    WHERE cr.id_caixa_baixa IN ($placeholders) AND cr.status = 'Recebido'
                    GROUP BY f.descricao";
    $stmt_r = $pdo->prepare($sql_receber);
    $stmt_r->execute($ids_caixas);
    $receber_banco = $stmt_r->fetchAll(PDO::FETCH_ASSOC);

    foreach ($receber_banco as $rec) {
        $total_titulos_recebidos += $rec['total'];
        $nome = mb_strtolower($rec['nome_pagto']);
        if (str_contains($nome, 'dinheiro')) $faturamento_sistema['dinheiro'] += $rec['total'];
        elseif (str_contains($nome, 'pix')) $faturamento_sistema['pix'] += $rec['total'];
        elseif (str_contains($nome, 'credito') || str_contains($nome, 'cartão')) $faturamento_sistema['credito'] += $rec['total'];
        elseif (str_contains($nome, 'debito')) $faturamento_sistema['debito'] += $rec['total'];
    }
}

// CÁLCULOS DO FLUXO DE CAIXA REAL (Apenas o que gerou dinheiro imediato)
$entradas_reais = array_sum($faturamento_sistema); 
$saldo_liquido = $entradas_reais - $total_saidas;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>DFC - Demonstração do Fluxo de Caixa</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; padding: 20px; }
        .container-fluid { max-width: 900px; margin: auto; background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header-section { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; margin-bottom: 20px; padding-bottom: 15px; }
        .btn-voltar { background: #6c757d; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; font-size: 0.9rem; }
        .filter-container { background: #f8f9fa; border: 1px solid #e9ecef; padding: 15px; border-radius: 8px; margin-bottom: 25px; }
        
        .form-filtros { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .form-grupo { display: flex; flex-direction: column; gap: 5px; }
        .form-grupo label { font-size: 0.85rem; font-weight: bold; color: #495057; }
        .form-controle { padding: 6px 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 0.9rem; color: #495057; }
        
        .btn-filtrar { 
            background: #007bff; color: white; border: none; padding: 8px 18px; border-radius: 6px; 
            font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: background 0.2s; height: 35px;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-filtrar:hover { background: #0056b3; }

        /* Estilos da Tabela DFC */
        .table-dfc { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 1rem; }
        .table-dfc td, .table-dfc th { padding: 14px 18px; border-bottom: 1px solid #e9ecef; }
        .table-dfc th { background: #f8f9fa; text-align: left; color: #495057; font-weight: bold; }
        
        .subcategoria { padding-left: 35px !important; color: #6c757d; font-size: 0.95rem; }
        .titulo-secao { font-weight: bold; background-color: #f1f3f5; }
        
        .texto-positivo { color: #28a745; font-weight: bold; }
        .texto-negativo { color: #dc3545; font-weight: bold; }
        
        .linha-resultado-final { background: #e2f0d9; font-size: 1.15rem; font-weight: bold; border-top: 2px solid #28a745; }
        .linha-resultado-final.negativo { background: #fce8e6; border-top: 2px solid #dc3545; }

        .card-informativo { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 12px 15px; border-radius: 6px; margin-top: 20px; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="header-section">
        <h2 style="margin:0;">📊 Demonstração do Fluxo de Caixa (DFC)</h2>
        <a href="dashboard.php" class="btn-voltar">⬅ Voltar ao Painel</a>
    </div>

    <div class="filter-container">
        <form method="GET" class="form-filtros">
            <div class="form-grupo">
                <label for="data_inicio">Período Inicial</label>
                <input type="date" name="data_inicio" id="data_inicio" class="form-controle" value="<?= htmlspecialchars($data_inicio) ?>">
            </div>

            <div class="form-grupo">
                <label for="data_fim">Período Final</label>
                <input type="date" name="data_fim" id="data_fim" class="form-controle" value="<?= htmlspecialchars($data_fim) ?>">
            </div>

            <button type="submit" class="btn-filtrar">🔍 Filtrar Período</button>
        </form>
    </div>

    <table class="table-dfc">
        <thead>
            <tr>
                <th>Fluxo de Movimentação</th>
                <th style="text-align: right;">Valor Acumulado</th>
            </tr>
        </thead>
        <tbody>
            <tr class="titulo-secao">
                <td>📥 ENTRADAS OPERACIONAIS REAIS</td>
                <td style="text-align: right;" class="texto-positivo">+ R$ <?= number_format($entradas_reais, 2, ',', '.') ?></td>
            </tr>
            <tr>
                <td class="subcategoria">💵 Vendas & Recebimentos em Dinheiro (Físico)</td>
                <td style="text-align: right;">R$ <?= number_format($faturamento_sistema['dinheiro'], 2, ',', '.') ?></td>
            </tr>
            <tr>
                <td class="subcategoria">📱 Recebimentos via PIX</td>
                <td style="text-align: right;">R$ <?= number_format($faturamento_sistema['pix'], 2, ',', '.') ?></td>
            </tr>
            <tr>
                <td class="subcategoria">💳 Vendas em Cartão de Crédito</td>
                <td style="text-align: right;">R$ <?= number_format($faturamento_sistema['credito'], 2, ',', '.') ?></td>
            </tr>
            <tr>
                <td class="subcategoria">💳 Vendas em Cartão de Débito</td>
                <td style="text-align: right;">R$ <?= number_format($faturamento_sistema['debito'], 2, ',', '.') ?></td>
            </tr>
            <tr>
                <td class="subcategoria" style="font-style: italic;">↳ Incluído do total: Quitação de Títulos antigos</td>
                <td style="text-align: right; font-style: italic; color: #6c757d;">R$ <?= number_format($total_titulos_recebidos, 2, ',', '.') ?></td>
            </tr>

            <tr class="titulo-secao">
                <td>💸 SAÍDAS OPERACIONAIS (Caixa)</td>
                <td style="text-align: right;" class="texto-negativo">- R$ <?= number_format($total_saidas, 2, ',', '.') ?></td>
            </tr>
            <tr>
                <td class="subcategoria">📉 Despesas Pagas / Sangrias / Estornos</td>
                <td style="text-align: right;">R$ <?= number_format($total_saidas, 2, ',', '.') ?></td>
            </tr>

            <tr class="linha-resultado-final <?= $saldo_liquido < 0 ? 'negativo' : '' ?>">
                <td>(=) SALDO LÍQUIDO OPERACIONAL NO PERÍODO</td>
                <td style="text-align: right;">
                    R$ <?= number_format($saldo_liquido, 2, ',', '.') ?>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="card-informativo">
        📌 <strong>Indicador Gerencial Adicional:</strong> No período selecionado, foram realizadas <strong>R$ <?= number_format($total_vendas_prazo, 2, ',', '.') ?></strong> em vendas a prazo (Convênio). 
        Este valor <strong>não entra</strong> no cálculo acima porque ainda não virou dinheiro físico no caixa, tornando-se uma previsão de recebimento futuro.
    </div>
</div>

</body>
</html>