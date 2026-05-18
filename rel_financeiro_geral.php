<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// Parâmetros de filtro
$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');
$visao = $_GET['visao'] ?? 'sintetico'; 
$data_inicio = "$ano-$mes-01";
$data_fim = date("Y-m-t", strtotime($data_inicio));

// Lógica de Query ajustada para focar estritamente nas baixas reais realizadas
if ($visao == 'analitico') {
    $sql = "
    WITH Receitas AS (
        SELECT p.data_pedido as data_mov, fp.descricao as descricao, p.valor_total as valor, 'RECEITA' as tipo_grupo 
        FROM pedidos p
        INNER JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
        WHERE p.situacao = 'finalizado' AND p.data_pedido BETWEEN :inicio AND :fim
        
        UNION ALL
        
        SELECT po.data_pedido as data_mov, 'Online - ' || fp.descricao as descricao, po.valor_total as valor, 'RECEITA' as tipo_grupo 
        FROM pedidos_online po
        INNER JOIN formas_pagamento fp ON po.forma_pagamento_id = fp.id
        WHERE po.status = 'finalizado' AND po.data_pedido BETWEEN :inicio2 AND :fim2
    ),
    Despesas AS (
        /* Trazemos o valor positivo do banco para padronizar a manipulação matemática no PHP */
        SELECT cp.data_pagamento as data_mov, pc.descricao || ' (' || cp.descricao || ')' as descricao, cp.valor_total as valor, 'DESPESA' as tipo_grupo 
        FROM contas_pagar cp 
        JOIN plano_contas pc ON cp.id_plano_conta = pc.id
        WHERE cp.status = 'Pago' AND cp.data_pagamento BETWEEN :inicio3 AND :fim3
    )
    SELECT * FROM Receitas UNION ALL SELECT * FROM Despesas ORDER BY tipo_grupo DESC, data_mov ASC";
} else {
    $sql = "
    WITH Receitas AS (
        SELECT NULL as data_mov, fp.descricao as descricao, SUM(p.valor_total) as valor, 'RECEITA' as tipo_grupo
        FROM pedidos p
        INNER JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
        WHERE p.situacao = 'finalizado' AND p.data_pedido BETWEEN :inicio AND :fim 
        GROUP BY fp.descricao
        
        UNION ALL
        
        SELECT NULL as data_mov, 'Online - ' || fp.descricao as descricao, SUM(po.valor_total) as valor, 'RECEITA' as tipo_grupo
        FROM pedidos_online po
        INNER JOIN formas_pagamento fp ON po.forma_pagamento_id = fp.id
        WHERE po.status = 'finalizado' AND po.data_pedido BETWEEN :inicio2 AND :fim2
        GROUP BY fp.descricao
    ),
    Despesas AS (
        /* Agrupamento por categoria do plano de contas baseado nas baixas do período */
        SELECT NULL as data_mov, pc.descricao, SUM(cp.valor_total) as valor, 'DESPESA' as tipo_grupo
        FROM contas_pagar cp 
        JOIN plano_contas pc ON cp.id_plano_conta = pc.id
        WHERE cp.status = 'Pago' AND cp.data_pagamento BETWEEN :inicio3 AND :fim3 
        GROUP BY pc.descricao
    )
    SELECT * FROM Receitas UNION ALL SELECT * FROM Despesas ORDER BY tipo_grupo DESC, valor DESC";
}

try {
    $stmt = $pdo->prepare($sql); 
    $stmt->execute([
        ':inicio'  => $data_inicio, ':fim'  => $data_fim,
        ':inicio2' => $data_inicio, ':fim2' => $data_fim,
        ':inicio3' => $data_inicio, ':fim3' => $data_fim
    ]);
    $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro na consulta: " . $e->getMessage());
}

$totalReceita = 0;
$totalDespesa = 0;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>DRE - Gestão Financeira</title>
    <style>
        :root {
            --primary: #2c3e50;
            --success: #27ae60;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --info: #3498db;
            --warning: #8e44ad;
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; color: #333; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn { padding: 10px 15px; border-radius: 5px; text-decoration: none; font-weight: bold; border: none; cursor: pointer; display: inline-block; transition: 0.3s; }
        .btn-dash { background-color: var(--primary); color: white; margin-right: 10px; }
        .btn-dash:hover { background-color: #1a252f; }
        .btn-print { background-color: #95a5a6; color: white; }
        .btn-print:hover { background-color: #7f8c8d; }
        .btn-visao { background-color: var(--warning); color: white; margin-right: 10px; }

        .filtros { display: flex; gap: 10px; margin-bottom: 30px; align-items: flex-end; background: var(--light); padding: 15px; border-radius: 8px; }
        .filtros select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; }
        .btn-filter { background: var(--info); color: white; }

        .dre-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .dre-table th { text-align: left; padding: 15px; border-bottom: 2px solid var(--primary); color: var(--primary); }
        .dre-table td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        
        .grupo-title { background: #fdfdfd; font-weight: bold; color: #7f8c8d; font-size: 0.9em; text-transform: uppercase; letter-spacing: 1px; }
        .subtotal { background: #f8f9fa; font-weight: bold; }
        .lucro-liquido { background: var(--primary); color: white; font-size: 1.2em; font-weight: bold; }
        
        .text-success { color: var(--success); font-weight: bold; }
        .text-danger { color: var(--danger); font-weight: bold; }
        .right { text-align: right; }

        @media print { .filtros, .header-actions { display: none; } }
    </style>
</head>
<body>

<div class="container">
    <div class="header-actions">
        <h2>DRE - Visão Realizada (Caixa) - <?= ucfirst($visao) ?></h2>
        <div>
            <a href="?mes=<?= $mes ?>&ano=<?= $ano ?>&visao=<?= $visao == 'sintetico' ? 'analitico' : 'sintetico' ?>" class="btn btn-visao">
                Mudar para <?= $visao == 'sintetico' ? 'Analítico' : 'Sintético' ?>
            </a>
            
            <button onclick="window.print()" class="btn btn-print">Imprimir PDF</button>
            <a href="dashboard.php" class="btn btn-dash">← Voltar dashboard</a>
        </div>
    </div>
    
    <form method="GET" class="filtros">
        <input type="hidden" name="visao" value="<?= $visao ?>">
        <div>
            <label>Mês:</label><br>
            <select name="mes">
                <?php for($i=1; $i<=12; $i++): $m = sprintf('%02d', $i); ?>
                    <option value="<?= $m ?>" <?= $mes == $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label>Ano:</label><br>
            <select name="ano">
                <option value="2025" <?= $ano == '2025' ? 'selected' : '' ?>>2025</option>
                <option value="2026" <?= $ano == '2026' ? 'selected' : '' ?>>2026</option>
            </select>
        </div>
        <button type="submit" class="btn btn-filter">Filtrar</button>
    </form>

    <table class="dre-table">
        <thead>
            <tr>
                <?php if($visao == 'analitico'): ?><th>Data Mov.</th><?php endif; ?>
                <th>Descrição das Operações</th>
                <th class="right">Valor Acumulado</th>
            </tr>
        </thead>
        <tbody>
            <tr class="grupo-title"><td colspan="<?= $visao == 'analitico' ? 3 : 2 ?>">Receitas Operacionais</td></tr>
            <?php foreach($movimentacoes as $mov): if($mov['tipo_grupo'] == 'RECEITA'): $totalReceita += $mov['valor']; ?>
                <tr>
                    <?php if($visao == 'analitico'): ?><td><?= date('d/m/Y', strtotime($mov['data_mov'])) ?></td><?php endif; ?>
                    <td>(+) <?= $visao == 'sintetico' ? 'Vendas '.ucfirst($mov['descricao']) : $mov['descricao'] ?></td>
                    <td class="right text-success">R$ <?= number_format($mov['valor'], 2, ',', '.') ?></td>
                </tr>
            <?php endif; endforeach; ?>
            <tr class="subtotal">
                <td colspan="<?= $visao == 'analitico' ? 2 : 1 ?>">FATURAMENTO BRUTO</td>
                <td class="right text-success">R$ <?= number_format($totalReceita, 2, ',', '.') ?></td>
            </tr>

            <tr><td colspan="<?= $visao == 'analitico' ? 3 : 2 ?>">&nbsp;</td></tr> 
            
            <tr class="grupo-title"><td colspan="<?= $visao == 'analitico' ? 3 : 2 ?>">Custos e Despesas Pagas</td></tr>
            <?php foreach($movimentacoes as $mov): if($mov['tipo_grupo'] == 'DESPESA'): $totalDespesa += $mov['valor']; ?>
                <tr>
                    <?php if($visao == 'analitico'): ?><td><?= date('d/m/Y', strtotime($mov['data_mov'])) ?></td><?php endif; ?>
                    <td>(-) <?= $mov['descricao'] ?></td>
                    <td class="right text-danger">R$ <?= number_format($mov['valor'], 2, ',', '.') ?></td>
                </tr>
            <?php endif; endforeach; ?>
            <tr class="subtotal">
                <td colspan="<?= $visao == 'analitico' ? 2 : 1 ?>">TOTAL DE DESPESAS</td>
                <td class="right text-danger">R$ <?= number_format($totalDespesa, 2, ',', '.') ?></td>
            </tr>

            <?php 
            // Subtração limpa feita diretamente aqui no cálculo final
            $resultado = $totalReceita - $totalDespesa; 
            ?>
            <tr class="lucro-liquido">
                <td colspan="<?= $visao == 'analitico' ? 2 : 1 ?>">RESULTADO LÍQUIDO DO PERÍODO</td>
                <td class="right">R$ <?= number_format($resultado, 2, ',', '.') ?></td>
            </tr>
        </tbody>
    </table>

    <?php 
        $margemLucro = ($totalReceita > 0) ? ($resultado / $totalReceita) * 100 : 0;
        $corMargem = $margemLucro >= 0 ? 'text-success' : 'text-danger';
    ?>
    <div style="margin-top: 20px; text-align: right; padding: 15px; background: #fcfcfc; border: 1px solid #eee; border-radius: 5px;">
        <strong>Margem de Lucro: </strong>
        <span class="<?= $corMargem ?>"><?= number_format($margemLucro, 2, ',', '.') ?>%</span>
    </div>
</div>

</body>
</html>