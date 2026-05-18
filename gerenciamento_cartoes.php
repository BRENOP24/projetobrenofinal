<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// --- 1. LÓGICA DE PROCESSAMENTO (BACK-END) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_vincular_bandeira'])) {
    $bandeira_id = (int)$_POST['id_bandeira_lote'];
    $selecionados = $_POST['vendas_selecionadas'] ?? [];
    $tipo_filtro = $_POST['tipo_filtro'] ?? 'todos';

    if ($bandeira_id > 0 && !empty($selecionados)) {
        foreach ($selecionados as $item) {
            $partes = explode('_', $item);
            $origem = $partes[0];
            $id_venda = (int)$partes[1];

            // Atualiza a tabela correspondente (pedidos ou pedidos_online)
            $tabela = ($origem === 'manual') ? 'pedidos' : 'pedidos_online';
            
            $stmt = $pdo->prepare("UPDATE $tabela SET bandeira_id = ? WHERE id = ?");
            $stmt->execute([$bandeira_id, $id_venda]);
        }
        header("Location: gerenciamento_cartoes.php?sucesso=1&data_inicio=" . $_POST['data_inicio'] . "&data_fim=" . $_POST['data_fim'] . "&tipo_filtro=" . $tipo_filtro);
        exit;
    }
}

// --- 2. CONFIGURAÇÃO DE FILTROS E BUSCA ---
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$tipo_filtro = $_GET['tipo_filtro'] ?? 'todos'; // Opções: 'todos', 'debito', 'credito'

// Busca as bandeiras ativas para o select
$bandeiras = $pdo->query("SELECT id, nome FROM bandeiras_cartao WHERE ativo = 1 ORDER BY nome ASC")->fetchAll();

// Filtro de texto dinâmico para a Query com base no tipo selecionado
$filtro_forma_sql = "(f.descricao LIKE '%Cartão%' OR f.descricao LIKE '%Debito%' OR f.descricao LIKE '%Credito%')";
if ($tipo_filtro === 'debito') {
    $filtro_forma_sql = "(f.descricao LIKE '%Debito%' OR f.descricao LIKE '%Débito%')";
} elseif ($tipo_filtro === 'credito') {
    $filtro_forma_sql = "(f.descricao LIKE '%Credito%' OR f.descricao LIKE '%Crédito%')";
}

// Query unificada trazendo todos os registros do período
$sql = "
    SELECT 'manual' as origem, p.id, p.data_pedido, p.valor_total, f.descricao as forma, 
           b.nome as bandeira, u.nome as nome_usuario
    FROM pedidos p
    JOIN formas_pagamento f ON p.forma_pagamento_id = f.id
    JOIN usuarios u ON p.usuario_id = u.id
    LEFT JOIN bandeiras_cartao b ON p.bandeira_id = b.id
    WHERE DATE(p.data_pedido) BETWEEN :inicio AND :fim 
    AND $filtro_forma_sql

    UNION ALL

    SELECT 'online' as origem, po.id, po.data_pedido, po.valor_total, f.descricao as forma, 
           b.nome as bandeira, 'Cliente Online' as nome_usuario
    FROM pedidos_online po
    JOIN formas_pagamento f ON po.forma_pagamento_id = f.id
    LEFT JOIN bandeiras_cartao b ON po.bandeira_id = b.id
    WHERE DATE(po.data_pedido) BETWEEN :inicio AND :fim 
    AND $filtro_forma_sql
    
    ORDER BY data_pedido DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['inicio' => $data_inicio, 'fim' => $data_fim]);
$todas_movimentacoes = $stmt->fetchAll();

// Separando os dados em dois arrays: Pendentes de vínculo e Já Vinculados
$pendentes = [];
$vinculados = [];

foreach ($todas_movimentacoes as $mov) {
    if (empty($mov['bandeira'])) {
        $pendentes[] = $mov;
    } else {
        $vinculados[] = $mov;
    }
}

// --- 3. LÓGICA DO RESUMO FINANCEIRO (SOMA POR BANDEIRA) ---
$sql_resumo = "
    SELECT nome_bandeira, SUM(valor_total) as total_bruto
    FROM (
        SELECT b.nome as nome_bandeira, p.valor_total
        FROM pedidos p
        JOIN bandeiras_cartao b ON p.bandeira_id = b.id
        WHERE DATE(p.data_pedido) BETWEEN :inicio AND :fim

        UNION ALL

        SELECT b.nome as nome_bandeira, po.valor_total
        FROM pedidos_online po
        JOIN bandeiras_cartao b ON po.bandeira_id = b.id
        WHERE DATE(po.data_pedido) BETWEEN :inicio AND :fim
    ) as totais
    GROUP BY nome_bandeira
    ORDER BY total_bruto DESC
";

$stmt_resumo = $pdo->prepare($sql_resumo);
$stmt_resumo->execute(['inicio' => $data_inicio, 'fim' => $data_fim]);
$resumos = $stmt_resumo->fetchAll();
$total_geral_conciliado = 0;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Conciliação de Cartões - Gestão Breno</title>
    <link rel="stylesheet" href="css/gerenciamento_cartao.css">
    <style>
        .section-title { margin-top: 40px; margin-bottom: 15px; color: #4a5568; border-left: 4px solid #3182ce; padding-left: 10px; font-size: 18px; font-weight: bold; }
        .table-vinculados { opacity: 0.9; }
        .table-vinculados thead th { background: #edf2f7; color: #4a5568; }
        .status-ok { background: #c6f6d5; color: #22543d; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
        .status-pendente { background: #feebc8; color: #9c4221; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="header-flex">
        <h2>💳 Conciliação de Cartões</h2>
        <a href="dashboard.php" class="btn-voltar">← Voltar ao Painel</a>
    </div>

    <!-- Filtros de Busca Avançada -->
    <div class="bar-tools" style="background: #f7fafc; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
        <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group">
                <label>Data Inicial</label>
                <input type="date" name="data_inicio" value="<?= $data_inicio ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Data Final</label>
                <input type="date" name="data_fim" value="<?= $data_fim ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Tipo de Operação</label>
                <select name="tipo_filtro" class="form-control" style="min-width: 160px;">
                    <option value="todos" <?= $tipo_filtro === 'todos' ? 'selected' : '' ?>>Geral (Tudo)</option>
                    <option value="debito" <?= $tipo_filtro === 'debito' ? 'selected' : '' ?>>Apenas Débito</option>
                    <option value="credito" <?= $tipo_filtro === 'credito' ? 'selected' : '' ?>>Apenas Crédito</option>
                </select>
            </div>
            <button type="submit" class="btn-vincular" style="background: #3182ce; height: 38px;">🔍 Filtrar Lançamentos</button>
        </form>
    </div>

    <!-- FORMULÁRIO DE VÍNCULO EM LOTE (Apenas atua nas pendentes) -->
    <form method="POST">
        <input type="hidden" name="data_inicio" value="<?= $data_inicio ?>">
        <input type="hidden" name="data_fim" value="<?= $data_fim ?>">
        <input type="hidden" name="tipo_filtro" value="<?= $tipo_filtro ?>">

        <div class="section-title">⚠️ Lançamentos Pendentes de Conciliação</div>

        <div class="bar-tools" style="margin-bottom: 15px; background: #fffaf0; border: 1px solid #feebc8;">
            <div class="form-group">
                <label style="color:#c05621; font-weight:bold;">Bandeira destino para vincular em lote:</label>
                <select name="id_bandeira_lote" class="form-control" style="min-width: 250px;" required>
                    <option value="">-- Selecione a Bandeira --</option>
                    <?php foreach ($bandeiras as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="btn_vincular_bandeira" class="btn-vincular" style="background: #dd6b20;">⚡ Gravar e Vincular Selecionados</button>
        </div>

        <table class="table-resumo">
            <thead>
                <tr>
                    <th width="30"><input type="checkbox" id="checkAll"></th>
                    <th>Origem</th>
                    <th>Venda Nº</th>
                    <th>Data/Hora</th>
                    <th>Usuário</th>
                    <th>Forma Pgto</th>
                    <th>Valor Total</th>
                    <th>Situação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($pendentes) > 0): ?>
                    <?php foreach ($pendentes as $mov): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="vendas_selecionadas[]" 
                                   value="<?= $mov['origem'] ?>_<?= $mov['id'] ?>" class="item-check">
                        </td>
                        <td>
                            <span class="badge badge-<?= $mov['origem'] ?>">
                                <?= $mov['origem'] == 'manual' ? '🖥️ Balcão' : '🌐 Online' ?>
                            </span>
                        </td>
                        <td><strong>#<?= $mov['id'] ?></strong></td>
                        <td><?= date('d/m H:i', strtotime($mov['data_pedido'])) ?></td>
                        <td><small><?= htmlspecialchars($mov['nome_usuario']) ?></small></td>
                        <td><?= htmlspecialchars($mov['forma']) ?></td>
                        <td><strong>R$ <?= number_format($mov['valor_total'], 2, ',', '.') ?></strong></td>
                        <td>
                            <span class="status-pendente">❓ Pendente</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 30px; color: #718096; background: #f7fafc;">
                            Nenhum cartão pendente encontrado para os filtros selecionados.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </form>

    <!-- SEÇÃO DOS VINCULADOS (Aparece abaixo conforme os itens são gravados) -->
    <div class="section-title">✅ Lançamentos Já Vinculados no Período</div>
    <table class="table-resumo table-vinculados">
        <thead>
            <tr>
                <th>Origem</th>
                <th>Venda Nº</th>
                <th>Data/Hora</th>
                <th>Usuário</th>
                <th>Forma Pgto</th>
                <th>Valor Total</th>
                <th>Bandeira Vinculada</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($vinculados) > 0): ?>
                <?php foreach ($vinculados as $mov): ?>
                <tr>
                    <td>
                        <span class="badge badge-<?= $mov['origem'] ?>">
                            <?= $mov['origem'] == 'manual' ? '🖥️ Balcão' : '🌐 Online' ?>
                        </span>
                    </td>
                    <td>#<?= $mov['id'] ?></td>
                    <td><?= date('d/m H:i', strtotime($mov['data_pedido'])) ?></td>
                    <td><small><?= htmlspecialchars($mov['nome_usuario']) ?></small></td>
                    <td><?= htmlspecialchars($mov['forma']) ?></td>
                    <td style="color: #4a5568;">R$ <?= number_format($mov['valor_total'], 2, ',', '.') ?></td>
                    <td>
                        <span class="status-ok">✅ <?= htmlspecialchars($mov['bandeira']) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 25px; color: #a0aec0;">
                        Nenhum cartão foi vinculado ainda neste período.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Bloco de Resumo Estatístico Consolidado -->
    <div class="container-resumo" style="margin-top: 30px;">
        <div class="card-resumo">
            <div class="card-resumo-header">📊 Resumo por Bandeira (Total Geral do Período)</div>
            
            <?php if (count($resumos) > 0): ?>
                <?php foreach ($resumos as $r): 
                    $total_geral_conciliado += $r['total_bruto']; 
                ?>
                    <div class="resumo-item">
                        <span><?= htmlspecialchars($r['nome_bandeira']) ?></span>
                        <span>R$ <?= number_format($r['total_bruto'], 2, ',', '.') ?></span>
                    </div>
                <?php endforeach; ?>
                
                <div class="resumo-item resumo-total">
                    <span>TOTAL CONCILIADO</span>
                    <span>R$ <?= number_format($total_geral_conciliado, 2, ',', '.') ?></span>
                </div>
            <?php else: ?>
                <div class="resumo-item" style="color: #999; justify-content: center;">
                    Ainda não há valores conciliados neste período.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Selecionar todos os checkboxes apenas do grupo de pendentes
    const checkAll = document.getElementById('checkAll');
    if(checkAll) {
        checkAll.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.item-check');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    }
</script>

</body>
</html>