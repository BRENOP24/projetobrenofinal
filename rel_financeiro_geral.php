<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// Parâmetros de filtro
$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');
$visao = $_GET['visao'] ?? 'sintetico'; 
$plano_conta_filtro = $_GET['plano_conta'] ?? ''; 

$data_inicio = "$ano-$mes-01";
$data_fim = date("Y-m-t", strtotime($data_inicio));

/* ==========================================================================
   1. BUSCA LISTA DE PLANO DE CONTAS (Organizado por Tipo para o Filtro)
   ========================================================================== */
try {
    $sql_plano = "SELECT id, descricao, tipo FROM plano_contas ORDER BY tipo DESC, descricao ASC";
    $stmt_plano = $pdo->query($sql_plano);
    $lista_planos = $stmt_plano->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lista_planos = [];
}

/* ==========================================================================
   2. CONSTRUÇÃO DAS CONDIÇÕES DE FILTRO
   ========================================================================== */
$condicaoDespesa = "";
$condicaoReceitaPresencial = "";
$condicaoReceitaOnline = "";

$params = [
    ':inicio'  => $data_inicio, ':fim'  => $data_fim,
    ':inicio2' => $data_inicio, ':fim2' => $data_fim,
    ':inicio3' => $data_inicio, ':fim3' => $data_fim
];

if (!empty($plano_conta_filtro)) {
    $plano_id = (int)$plano_conta_filtro;
    $params[':id_plano'] = $plano_id;

    $condicaoDespesa           = " AND cp.id_plano_conta = :id_plano ";
    $condicaoReceitaPresencial = " AND p.forma_pagamento_id = :id_plano ";
    $condicaoReceitaOnline     = " AND po.forma_pagamento_id = :id_plano ";
}

/* ==========================================================================
   3. CONSULTA DA DRE
   ========================================================================== */
if ($visao == 'analitico') {
    $sql = "
    WITH Receitas AS (
        SELECT p.data_pedido as data_mov, pc.descricao as descricao, p.valor_total as valor, 'RECEITA' as tipo_grupo 
        FROM pedidos p
        INNER JOIN plano_contas pc ON p.forma_pagamento_id = pc.id
        WHERE p.situacao = 'finalizado' AND p.data_pedido BETWEEN :inicio AND :fim
        $condicaoReceitaPresencial
        
        UNION ALL
        
        SELECT po.data_pedido as data_mov, 'Online - ' || pc.descricao as descricao, po.valor_total as valor, 'RECEITA' as tipo_grupo 
        FROM pedidos_online po
        INNER JOIN plano_contas pc ON po.forma_pagamento_id = pc.id
        WHERE po.status = 'Finalizado' AND po.data_pedido BETWEEN :inicio2 AND :fim2
        $condicaoReceitaOnline
    ),
    Despesas AS (
        SELECT cp.data_pagamento as data_mov, pc.descricao || ' (' || cp.descricao || ')' as descricao, cp.valor_total as valor, 'DESPAN' as tipo_grupo 
        FROM contas_pagar cp 
        JOIN plano_contas pc ON cp.id_plano_conta = pc.id
        WHERE cp.status = 'Pago' AND cp.data_pagamento BETWEEN :inicio3 AND :fim3
        $condicaoDespesa
    )
    SELECT data_mov, descricao, valor, CASE WHEN tipo_grupo = 'DESPAN' THEN 'DESPESA' ELSE 'RECEITA' END as tipo_grupo 
    FROM Receitas 
    UNION ALL 
    SELECT data_mov, descricao, valor, CASE WHEN tipo_grupo = 'DESPAN' THEN 'DESPESA' ELSE 'RECEITA' END as tipo_grupo 
    FROM Despesas 
    ORDER BY tipo_grupo DESC, data_mov ASC";

} else {
    $sql = "
    WITH Receitas AS (
        SELECT NULL as data_mov, pc.descricao as descricao, SUM(p.valor_total) as valor, 'RECEITA' as tipo_grupo
        FROM pedidos p
        INNER JOIN plano_contas pc ON p.forma_pagamento_id = pc.id
        WHERE p.situacao = 'finalizado' AND p.data_pedido BETWEEN :inicio AND :fim 
        $condicaoReceitaPresencial
        GROUP BY pc.descricao
        
        UNION ALL
        
        SELECT NULL as data_mov, 'Online - ' || pc.descricao as descricao, SUM(po.valor_total) as valor, 'RECEITA' as tipo_grupo
        FROM pedidos_online po
        INNER JOIN plano_contas pc ON po.forma_pagamento_id = pc.id
        WHERE po.status = 'Finalizado' AND po.data_pedido BETWEEN :inicio2 AND :fim2
        $condicaoReceitaOnline
        GROUP BY pc.descricao
    ),
    Despesas AS (
        SELECT NULL as data_mov, pc.descricao, SUM(cp.valor_total) as valor, 'DESPESA' as tipo_grupo
        FROM contas_pagar cp 
        JOIN plano_contas pc ON cp.id_plano_conta = pc.id
        WHERE cp.status = 'Pago' AND cp.data_pagamento BETWEEN :inicio3 AND :fim3 
        $condicaoDespesa
        GROUP BY pc.descricao
    )
    SELECT * FROM Receitas UNION ALL SELECT * FROM Despesas ORDER BY tipo_grupo DESC, valor DESC";
}

try {
    $stmt = $pdo->prepare($sql); 
    $stmt->execute($params);
    $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro na consulta técnica: " . $e->getMessage());
}

$receitas = array_filter($movimentacoes, fn($m) => $m['tipo_grupo'] === 'RECEITA');
$despesas = array_filter($movimentacoes, fn($m) => $m['tipo_grupo'] === 'DESPESA');

$totalReceita = array_sum(array_column($receitas, 'valor'));
$totalDespesa = array_sum(array_column($despesas, 'valor'));
$resultado = $totalReceita - $totalDespesa;
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
            --warning: #f39c12;
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; color: #333; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn { padding: 10px 15px; border-radius: 5px; text-decoration: none; font-weight: bold; border: none; cursor: pointer; display: inline-block; transition: 0.2s; font-size: 14px; }
        .btn-dash { background-color: var(--primary); color: white; margin-right: 10px; }
        .btn-dash:hover { background-color: #1a252f; }
        .btn-print { background-color: #95a5a6; color: white; }
        .btn-print:hover { background-color: #7f8c8d; }
        .btn-visao { background-color: var(--warning); color: white; margin-right: 10px; }
        .btn-visao:hover { background-color: #d35400; }

        .cards-resumo { display: flex; gap: 15px; margin-bottom: 25px; }
        .card { flex: 1; padding: 15px; border-radius: 6px; background: #fafafa; border-left: 4px solid #ccc; }
        .card.card-receitas { border-left-color: var(--success); }
        .card.card-despesas { border-left-color: var(--danger); }
        .card.card-resultado { border-left-color: var(--info); }
        .card span { display: block; font-size: 12px; color: #7f8c8d; font-weight: bold; text-transform: uppercase; }
        .card strong { font-size: 20px; display: block; margin-top: 5px; }

        .filtros { display: flex; gap: 15px; margin-bottom: 30px; align-items: flex-end; background: var(--light); padding: 15px; border-radius: 8px; flex-wrap: wrap; }
        .filtros select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; background: #fff; min-width: 130px; height: 38px; }
        .btn-filter { background: var(--info); color: white; height: 38px; padding: 0 20px; }
        .btn-filter:hover { background: #2980b9; }

        .dre-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .dre-table th { text-align: left; padding: 15px; border-bottom: 2px solid var(--primary); color: var(--primary); font-weight: 600; }
        .dre-table td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        
        .grupo-title { background: #fdfdfd; font-weight: bold; color: #7f8c8d; font-size: 0.9em; text-transform: uppercase; letter-spacing: 1px; }
        .subtotal { background: #f8f9fa; font-weight: bold; }
        .lucro-liquido { background: var(--primary); color: white; font-size: 1.15em; font-weight: bold; }
        
        .text-success { color: var(--success); }
        .text-danger { color: var(--danger); }
        .right { text-align: right; }
        .sem-dados { text-align: center; color: #95a5a6; font-style: italic; padding: 20px !important; }

        @media print { 
            .filtros, .header-actions, .btn { display: none !important; } 
            body { background: white; padding: 0; }
            .container { box-shadow: none; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-actions">
        <h2>DRE - Visão Realizada (Caixa) - <?= ucfirst($visao) ?></h2>
        <div>
            <a href="?mes=<?= $mes ?>&ano=<?= $ano ?>&visao=<?= $visao == 'sintetico' ? 'analitico' : 'sintetico' ?>&plano_conta=<?= urlencode($plano_conta_filtro) ?>" class="btn btn-visao">
                Ver Plano <?= $visao == 'sintetico' ? 'Analítico' : 'Sintético' ?>
            </a>
            <button onclick="window.print()" class="btn btn-print">Imprimir PDF</button>
            <a href="dashboard.php" class="btn btn-dash">← Voltar</a>
        </div>
    </div>
    
    <form method="GET" class="filtros">
        <input type="hidden" name="visao" value="<?= $visao ?>">
        <div>
            <label style="font-size:12px; font-weight:bold;">Mês:</label><br>
            <select name="mes">
                <?php 
                $mesesNome = ["Jan", "Fev", "Mar", "Abr", "Mai", "Jun", "Jul", "Ago", "Set", "Out", "Nov", "Dez"];
                for($i=1; $i<=12; $i++): $m = sprintf('%02d', $i); 
                ?>
                    <option value="<?= $m ?>" <?= $mes == $m ? 'selected' : '' ?>><?= $mesesNome[$i-1] ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label style="font-size:12px; font-weight:bold;">Ano:</label><br>
            <select name="ano">
                <?php 
                $anoAtual = (int)date('Y');
                for($a = $anoAtual - 2; $a <= $anoAtual + 1; $a++): 
                ?>
                    <option value="<?= $a ?>" <?= $ano == $a ? 'selected' : '' ?>><?= $a ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label style="font-size:12px; font-weight:bold;">Filtrar Conta/Forma:</label><br>
            <select name="plano_conta" style="min-width: 230px;">
                <option value="">-- Todos os Planos --</option>
                
                <optgroup label="Receitas (Formas de Pagamento)">
                    <?php foreach($lista_planos as $plano): if($plano['tipo'] == 'receita'): ?>
                        <option value="<?= $plano['id'] ?>" <?= $plano_conta_filtro == $plano['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($plano['descricao']) ?>
                        </option>
                    <?php endif; endforeach; ?>
                </optgroup>

                <optgroup label="Custos / Despesas">
                    <?php foreach($lista_planos as $plano): if($plano['tipo'] == 'despesa'): ?>
                        <option value="<?= $plano['id'] ?>" <?= $plano_conta_filtro == $plano['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($plano['descricao']) ?>
                        </option>
                    <?php endif; endforeach; ?>
                </optgroup>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-filter">Filtrar</button>
            <?php if(!empty($plano_conta_filtro)): ?>
                <a href="?mes=<?= $mes ?>&ano=<?= $ano ?>&visao=<?= $visao ?>" style="background:#6c757d; color:white; text-decoration:none; padding:9px 15px; border-radius:4px; font-size:13px; font-weight:bold; margin-left:5px; display:inline-block; height:18px;">Limpar</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="cards-resumo">
        <div class="card card-receitas">
            <span>Receitas (A)</span>
            <strong class="text-success">R$ <?= number_format($totalReceita, 2, ',', '.') ?></strong>
        </div>
        <div class="card card-despesas">
            <span>Despesas (B)</span>
            <strong class="text-danger">R$ <?= number_format($totalDespesa, 2, ',', '.') ?></strong>
        </div>
        <div class="card card-resultado">
            <span>Resultado Líquido (A - B)</span>
            <strong class="<?= $resultado >= 0 ? 'text-success' : 'text-danger' ?>">R$ <?= number_format($resultado, 2, ',', '.') ?></strong>
        </div>
    </div>

    <table class="dre-table">
        <thead>
            <tr>
                <?php if($visao == 'analitico'): ?><th style="width: 130px;">Data Mov.</th><?php endif; ?>
                <th>Descrição das Operações</th>
                <th class="right" style="width: 180px;">Valor Acumulado</th>
            </tr>
        </thead>
        <tbody>
            <tr class="grupo-title"><td colspan="<?= $visao == 'analitico' ? 3 : 2 ?>">Receitas Operacionais</td></tr>
            <?php if(empty($receitas)): ?>
                <tr><td colspan="<?= $visao == 'analitico' ? 3 : 2 ?>" class="sem-dados">Nenhuma receita registrada neste período.</td></tr>
            <?php else: ?>
                <?php foreach($receitas as $mov): ?>
                    <tr>
                        <?php if($visao == 'analitico'): ?><td><?= date('d/m/Y', strtotime($mov['data_mov'])) ?></td><?php endif; ?>
                        <td>(+) <?= $visao == 'sintetico' ? 'Vendas '.ucfirst($mov['descricao']) : $mov['descricao'] ?></td>
                        <td class="right text-success" style="font-weight: 500;">R$ <?= number_format($mov['valor'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr class="subtotal">
                <td colspan="<?= $visao == 'analitico' ? 2 : 1 ?>">FATURAMENTO BRUTO</td>
                <td class="right text-success">R$ <?= number_format($totalReceita, 2, ',', '.') ?></td>
            </tr>

            <tr><td colspan="<?= $visao == 'analitico' ? 3 : 2 ?>">&nbsp;</td></tr> 
            
            <tr class="grupo-title"><td colspan="<?= $visao == 'analitico' ? 3 : 2 ?>">Custos e Despesas Pagas</td></tr>
            <?php if(empty($despesas)): ?>
                <tr><td colspan="<?= $visao == 'analitico' ? 3 : 2 ?>" class="sem-dados">Nenhuma despesa registrada neste período.</td></tr>
            <?php else: ?>
                <?php foreach($despesas as $mov): ?>
                    <tr>
                        <?php if($visao == 'analitico'): ?><td><?= date('d/m/Y', strtotime($mov['data_mov'])) ?></td><?php endif; ?>
                        <td>(-) <?= $mov['descricao'] ?></td>
                        <td class="right text-danger" style="font-weight: 500;">R$ <?= number_format($mov['valor'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr class="subtotal">
                <td colspan="<?= $visao == 'analitico' ? 2 : 1 ?>">TOTAL DE DESPESAS</td>
                <td class="right text-danger">R$ <?= number_format($totalDespesa, 2, ',', '.') ?></td>
            </tr>

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
    <div style="margin-top: 20px; text-align: right; padding: 15px; background: #fafafa; border: 1px solid #eee; border-radius: 5px; font-size: 15px;">
        <strong>Margem de Lucro Operacional: </strong>
        <span class="<?= $corMargem ?>" style="font-weight: bold;"><?= number_format($margemLucro, 2, ',', '.') ?>%</span>
    </div>
</div>

</body>
</html>