<?php
// 1. Mapeamento estático dos meios de pagamento (conforme sua imagem)
$meios_pagamento = [
    1 => 'Dinheiro',
    2 => 'Cartão Débito',
    3 => 'Cartão Crédito',
    4 => 'Pix',
    5 => 'Boleto',
    7 => 'A prazo'
];

// Array global para acumular os totais de TODAS as vendas
$totais_consolidados = array_fill_keys(array_keys($meios_pagamento), 0);
$total_geral = 0;

// -------------------------------------------------------------------------
// 2. BUSCA E PROCESSAMENTO DE PEDIDOS PRESENCIAIS (FÍSICOS)
// -------------------------------------------------------------------------
// Exemplo de SQL: SELECT p.id, p.valor_total, p.forma_pagamento_id, p.tipo_venda, c.nome FROM pedidos p INNER JOIN clientes c ON p.cliente_id = c.id WHERE p.status = 'finalizado' AND p.situacao = 'finalizado'

$pedidos_fisicos = [
    ['id' => 101, 'cliente' => 'João Silva', 'valor_total' => 150.00, 'forma_pagamento_id' => 1, 'tipo_venda' => 'balcao'],
    ['id' => 102, 'cliente' => 'Maria Souza', 'valor_total' => 210.00, 'forma_pagamento_id' => 3, 'tipo_venda' => 'local (mesa 04)'],
    ['id' => 103, 'cliente' => 'Pedro Santos', 'valor_total' => 45.00, 'forma_pagamento_id' => 1, 'tipo_venda' => 'delivery'],
];

// -------------------------------------------------------------------------
// 3. BUSCA E PROCESSAMENTO DE PEDIDOS ONLINE
// -------------------------------------------------------------------------
// Exemplo de SQL: SELECT po.id, po.valor_total, po.forma_pagamento_id, po.tipo_entrega, co.nome FROM pedidos_online po INNER JOIN clientes_online co ON po.cliente_id = co.id WHERE po.status = 'finalizado'

$pedidos_online = [
    ['id' => 501, 'cliente_online' => 'Lucas Lima (Web)', 'valor_total' => 89.90, 'forma_pagamento_id' => 4, 'tipo_entrega' => 'entrega'],
    ['id' => 502, 'cliente_online' => 'Ana Paula (App)', 'valor_total' => 120.00, 'forma_pagamento_id' => 5, 'tipo_entrega' => 'retirada'],
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Conferência Separado</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; color: #333; }
        h2 { border-bottom: 2px solid #333; padding-bottom: 5px; margin-top: 40px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .subtotal { background-color: #f9f9f9; font-weight: bold; }
        .card-resumo { width: 400px; border: 2px solid #28a745; border-radius: 5px; padding: 15px; background-color: #f8fff9; }
        .linha-total { font-weight: bold; color: #28a745; font-size: 1.1em; }
    </style>
</head>
<body>

    <h1>Relatório de Conferência de Meios de Pagamento</h1>

    <h2>1. Pedidos Presenciais (Balcão / Mesa / Delivery Interno)</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Origem/Tipo</th>
                <th>Meio de Pagamento</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $subtotal_fisico = 0;
            foreach ($pedidos_fisicos as $pf): 
                $meio_id = $pf['forma_pagamento_id'];
                $nome_pagamento = $meios_pagamento[$meio_id] ?? 'Não Informado';
                
                // Acumula nos totais gerais e no subtotal desta tabela
                $subtotal_fisico += $pf['valor_total'];
                $total_geral += $pf['valor_total'];
                if (isset($totais_consolidados[$meio_id])) {
                    $totais_consolidados[$meio_id] += $pf['valor_total'];
                }
            ?>
            <tr>
                <td>#<?= $pf['id'] ?></td>
                <td><?= $pf['cliente'] ?></td>
                <td><?= ucfirst($pf['tipo_venda']) ?></td>
                <td><?= $nome_pagamento ?></td>
                <td>R$ <?= number_format($pf['valor_total'], 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="subtotal">
                <td colspan="4" style="text-align: right;">Subtotal Presencial:</td>
                <td>R$ <?= number_format($subtotal_fisico, 2, ',', '.') ?></td>
            </tr>
        </tbody>
    </table>


    <h2>2. Pedidos Online (E-commerce / App)</h2>
    <table>
        <thead>
            <tr>
                <th>ID Online</th>
                <th>Cliente Online</th>
                <th>Tipo de Entrega</th>
                <th>Meio de Pagamento</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $subtotal_online = 0;
            foreach ($pedidos_online as $po): 
                $meio_id = $po['forma_pagamento_id'];
                $nome_pagamento = $meios_pagamento[$meio_id] ?? 'Não Informado';
                
                // Acumula nos totais gerais e no subtotal desta tabela
                $subtotal_online += $po['valor_total'];
                $total_geral += $po['valor_total'];
                if (isset($totais_consolidados[$meio_id])) {
                    $totais_consolidados[$meio_id] += $po['valor_total'];
                }
            ?>
            <tr>
                <td>#<?= $po['id'] ?></td>
                <td><?= $po['cliente_online'] ?></td>
                <td><?= ucfirst($po['tipo_entrega']) ?></td>
                <td><?= $nome_pagamento ?></td>
                <td>R$ <?= number_format($po['valor_total'], 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="subtotal">
                <td colspan="4" style="text-align: right;">Subtotal Online:</td>
                <td>R$ <?= number_format($subtotal_online, 2, ',', '.') ?></td>
            </tr>
        </tbody>
    </table>


    <h2>3. Consolidação Financeira (Total Geral do Caixa)</h2>
    <div class="card-resumo">
        <table style="margin-bottom: 0; border: none;">
            <thead>
                <tr>
                    <th style="border: none; background: none;">Meio de Pagamento</th>
                    <th style="border: none; background: none; text-align: right;">Total Apurado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($meios_pagamento as $id => $nome): ?>
                    <tr>
                        <td style="border: none; border-bottom: 1px dashed #ccc;"><?= $nome ?></td>
                        <td style="border: none; border-bottom: 1px dashed #ccc; text-align: right;">
                            R$ <?= number_format($totais_consolidados[$id], 2, ',', '.') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr class="linha-total">
                    <td style="border: none; padding-top: 15px;">TOTAL GERAL</td>
                    <td style="border: none; padding-top: 15px; text-align: right;">
                        R$ <?= number_format($total_geral, 2, ',', '.') ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

</body>
</html>
