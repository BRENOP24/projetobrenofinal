<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// --- 1. CONFIGURAÇÃO DE FILTROS ---
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$data_fim    = $_GET['data_fim'] ?? date('Y-m-d');
$situacao    = $_GET['situacao'] ?? 'todos';       // todos, pendentes, conciliados
$tipo_filtro = $_GET['tipo_filtro'] ?? 'todos';     // todos, debito, credito
$origem      = $_GET['origem'] ?? 'todos';          // todos, manual, online
$bandeira_id = isset($_GET['bandeira_id']) ? (int)$_GET['bandeira_id'] : 0;

// Busca listas para os selects dos filtros
$bandeiras = $pdo->query("SELECT id, nome FROM bandeiras_cartao WHERE ativo = 1 ORDER BY nome ASC")->fetchAll();

// --- 2. CONSTRUÇÃO DINÂMICA DA QUERY SQL ---
$where_pedidos = ["DATE(p.data_pedido) BETWEEN :inicio AND :fim"];
$where_online  = ["DATE(po.data_pedido) BETWEEN :inicio AND :fim"];
$params = ['inicio' => $data_inicio, 'fim' => $data_fim];

// Filtro por Situação (Pendentes vs Conciliados)
if ($situacao === 'pendentes') {
    $where_pedidos[] = "p.bandeira_id IS NULL";
    $where_online[]  = "po.bandeira_id IS NULL";
} elseif ($situacao === 'conciliados') {
    $where_pedidos[] = "p.bandeira_id IS NOT NULL";
    $where_online[]  = "po.bandeira_id IS NOT NULL";
}

// Filtro por Tipo de Operação (Débito vs Crédito)
if ($tipo_filtro === 'debito') {
    $where_pedidos[] = "(f.descricao LIKE '%Debito%' OR f.descricao LIKE '%Débito%')";
    $where_online[]  = "(f.descricao LIKE '%Debito%' OR f.descricao LIKE '%Débito%')";
} elseif ($tipo_filtro === 'credito') {
    $where_pedidos[] = "(f.descricao LIKE '%Credito%' OR f.descricao LIKE '%Crédito%')";
    $where_online[]  = "(f.descricao LIKE '%Credito%' OR f.descricao LIKE '%Crédito%')";
} else {
    $where_pedidos[] = "(f.descricao LIKE '%Cartão%' OR f.descricao LIKE '%Debito%' OR f.descricao LIKE '%Credito%')";
    $where_online[]  = "(f.descricao LIKE '%Cartão%' OR f.descricao LIKE '%Debito%' OR f.descricao LIKE '%Credito%')";
}

// Filtro por Bandeira Específica
if ($bandeira_id > 0) {
    $where_pedidos[] = "p.bandeira_id = :bandeira_id";
    $where_online[]  = "po.bandeira_id = :bandeira_id";
    $params['bandeira_id'] = $bandeira_id;
}

// Junta as cláusulas WHERE de cada tabela
$sql_where_pedidos = implode(" AND ", $where_pedidos);
$sql_where_online  = implode(" AND ", $where_online);

// Monta a Query principal baseada na Origem selecionada
$queries = [];

if ($origem === 'todos' || $origem === 'manual') {
    $queries[] = "
        SELECT 'manual' as origem_chave, '🖥️ Balcão' as origem_txt, p.id, p.data_pedido, p.valor_total, f.descricao as forma, 
               b.nome as bandeira, u.nome as nome_usuario, p.bandeira_id
        FROM pedidos p
        JOIN formas_pagamento f ON p.forma_pagamento_id = f.id
        JOIN usuarios u ON p.usuario_id = u.id
        LEFT JOIN bandeiras_cartao b ON p.bandeira_id = b.id
        WHERE $sql_where_pedidos
    ";
}

if ($origem === 'todos' || $origem === 'online') {
    $queries[] = "
        SELECT 'online' as origem_chave, '🌐 Online' as origem_txt, po.id, po.data_pedido, po.valor_total, f.descricao as forma, 
               b.nome as bandeira, 'Cliente Online' as nome_usuario, po.bandeira_id
        FROM pedidos_online po
        JOIN formas_pagamento f ON po.forma_pagamento_id = f.id
        LEFT JOIN bandeiras_cartao b ON po.bandeira_id = b.id
        WHERE $sql_where_online
    ";
}

$sql_final = implode(" UNION ALL ", $queries) . " ORDER BY data_pedido DESC";

$stmt = $pdo->prepare($sql_final);
$stmt->execute($params);
$linhas = $stmt->fetchAll();

// --- 3. LÓGICA DE INDICADORES (KPIs) ---
$total_geral = 0;
$total_conciliado = 0;
$total_pendente = 0;
$qtd_conciliado = 0;
$qtd_pendente = 0;

foreach ($linhas as $l) {
    $total_geral += $l['valor_total'];
    if (empty($l['bandeira_id'])) {
        $total_pendente += $l['valor_total'];
        $qtd_pendente++;
    } else {
        $total_conciliado += $l['valor_total'];
        $qtd_conciliado++;
    }
}
$indice_eficiencia = $total_geral > 0 ? ($total_conciliado / $total_geral) * 100 : 0;

// --- 4. ENGINE DE EXPORTAÇÃO EXCEL/CSV ---
if (isset($_GET['exportar']) && $_GET['exportar'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=detalhamento_cartoes_' . $data_inicio . '_a_' . $data_fim . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // Corrige acentuação no Excel brasileiro
    
    fputcsv($output, ['Origem', 'Venda Nº', 'Data/Hora', 'Usuário/Operador', 'Forma Pagamento', 'Bandeira Atribuída', 'Valor Bruto', 'Situação']);
    foreach ($linhas as $l) {
        fputcsv($output, [
            $l['origem_txt'],
            '#' . $l['id'],
            date('d/m/Y H:i', strtotime($l['data_pedido'])),
            $l['nome_usuario'],
            $l['forma'],
            $l['bandeira'] ?? 'Pendente',
            number_format($l['valor_total'], 2, ',', '.'),
            empty($l['bandeira_id']) ? 'Pendente' : 'Conciliado'
        ]);
    }
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório Detalhado de Cartões</title>
    <link rel="stylesheet" href="css/gerenciamento_cartao.css"> <!-- Reutilizando seus estilos -->
    <style>
        .filter-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; margin-bottom: 25px; }
        .grid-filtros { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; align-items: flex-end; }
        
        .grid-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .card-kpi { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-top: 4px solid #cbd5e0; }
        .kpi-title { font-size: 12px; font-weight: bold; color: #718096; text-transform: uppercase; margin-bottom: 5px; }
        .kpi-value { font-size: 20px; font-weight: bold; color: #2d3748; }
        .kpi-sub { font-size: 11px; color: #a0aec0; margin-top: 5px; }
        
        .kpi-blue { border-top-color: #3182ce; }
        .kpi-green { border-top-color: #38a169; }
        .kpi-orange { border-top-color: #dd6b20; }
        .kpi-purple { border-top-color: #805ad5; }

        .status-badge { padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 11px; display: inline-block; }
        .badge-p { background: #feebc8; color: #c05621; }
        .badge-c { background: #c6f6d5; color: #22543d; }
        
        .btn-acao { height: 38px; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; border-radius: 4px; border: none; cursor: pointer; text-decoration: none; padding: 0 15px; font-size: 13px; }
        .btn-filtrar { background: #3182ce; color: #fff; }
        .btn-exportar { background: #38a169; color: #fff; }
        .btn-filtrar:hover { background: #2b6cb0; }
        .btn-exportar:hover { background: #2f855a; }
    </style>
</head>
<body>

<div class="container-fluid" style="padding: 20px; max-width: 1400px; margin: 0 auto;">
    
    <div class="header-flex" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h2 style="margin: 0; color: #2d3748;">📋 Relatório de Detalhamento e Auditoria</h2>
            <p style="margin: 5px 0 0 0; color: #718096; font-size: 14px;">Análise cruzada de movimentações de cartões e status de conferência</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="gerenciamento_cartoes.php?data_inicio=<?= $data_inicio ?>&data_fim=<?= $data_fim ?>" class="btn-voltar">← Tela de Conciliação</a>
        </div>
    </div>

    <!-- PAINEL DE FILTROS AVANÇADOS -->
    <div class="filter-box">
        <form method="GET" class="grid-filtros">
            <div class="form-group">
                <label>Data Inicial</label>
                <input type="date" name="data_inicio" value="<?= $data_inicio ?>" class="form-control">
            </div>
            
            <div class="form-group">
                <label>Data Final</label>
                <input type="date" name="data_fim" value="<?= $data_fim ?>" class="form-control">
            </div>

            <div class="form-group">
                <label>Situação da Conciliação</label>
                <select name="situacao" class="form-control">
                    <option value="todos" <?= $situacao === 'todos' ? 'selected' : '' ?>>Todos os Lançamentos</option>
                    <option value="pendentes" <?= $situacao === 'pendentes' ? 'selected' : '' ?>>❓ Apenas Pendentes</option>
                    <option value="conciliados" <?= $situacao === 'conciliados' ? 'selected' : '' ?>>✅ Apenas Conciliados</option>
                </select>
            </div>

            <div class="form-group">
                <label>Tipo de Operação</label>
                <select name="tipo_filtro" class="form-control">
                    <option value="todos" <?= $tipo_filtro === 'todos' ? 'selected' : '' ?>>Todos (Débito+Crédito)</option>
                    <option value="debito" <?= $tipo_filtro === 'debito' ? 'selected' : '' ?>>Apenas Débito</option>
                    <option value="credito" <?= $tipo_filtro === 'credito' ? 'selected' : '' ?>>Apenas Crédito</option>
                </select>
            </div>

            <div class="form-group">
                <label>Origem/Canal</label>
                <select name="origem" class="form-control">
                    <option value="todos" <?= $origem === 'todos' ? 'selected' : '' ?>>Todos os Canais</option>
                    <option value="manual" <?= $origem === 'manual' ? 'selected' : '' ?>>🖥️ Balcão / Manual</option>
                    <option value="online" <?= $origem === 'online' ? 'selected' : '' ?>>🌐 Plataforma Online</option>
                </select>
            </div>

            <div class="form-group">
                <label>Bandeira Atribuída</label>
                <select name="bandeira_id" class="form-control">
                    <option value="0">-- Todas as Bandeiras --</option>
                    <?php foreach ($bandeiras as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $bandeira_id === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-acao btn-filtrar">🔍 Aplicar Filtros</button>
            
            <a href="?<?= http_build_query(array_merge($_GET, ['exportar' => 'csv'])) ?>" class="btn-acao btn-exportar" title="Baixar Planilha do Excel">
                📥 Exportar Excel
            </a>
        </form>
    </div>

    <!-- CARDS DE KPIS / INDICADORES -->
    <div class="grid-kpis">
        <div class="card-kpi kpi-blue">
            <div class="kpi-title">Faturamento Total Cartões</div>
            <div class="kpi-value">R$ <?= number_format($total_geral, 2, ',', '.') ?></div>
            <div class="kpi-sub"><?= count($linhas) ?> transações encontradas</div>
        </div>
        <div class="card-kpi kpi-green">
            <div class="kpi-title">Total Já Conciliado</div>
            <div class="kpi-value" style="color: #2f855a;">R$ <?= number_format($total_conciliado, 2, ',', '.') ?></div>
            <div class="kpi-sub">✅ <?= $qtd_conciliado ?> itens processados</div>
        </div>
        <div class="card-kpi kpi-orange">
            <div class="kpi-title">Total Pendente (Gargalo)</div>
            <div class="kpi-value" style="color: #c05621;">R$ <?= number_format($total_pendente, 2, ',', '.') ?></div>
            <div class="kpi-sub">⚠️ <?= $qtd_pendente ?> lançamentos sem bandeira</div>
        </div>
        <div class="card-kpi kpi-purple">
            <div class="kpi-title">Índice de Eficiência</div>
            <div class="kpi-value" style="color: #6b46c1;"><?= number_format($indice_eficiencia, 1, ',', '.') ?>%</div>
            <div class="kpi-sub">Alvo operacional: 100%</div>
        </div>
    </div>

    <!-- TABELA COMPLETA DETALHADA -->
    <table class="table-resumo" style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <thead>
            <tr style="background: #2d3748; color: white;">
                <th>Origem</th>
                <th>Venda Nº</th>
                <th>Data/Hora</th>
                <th>Usuário/Operador</th>
                <th>Forma Original</th>
                <th>Bandeira Atribuída</th>
                <th style="text-align: right; padding-right: 20px;">Valor Bruto</th>
                <th width="120" style="text-align: center;">Situação</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($linhas) > 0): ?>
                <?php foreach ($linhas as $row): ?>
                <tr style="<?= empty($row['bandeira_id']) ? 'background: #fffaf0;' : '' ?>">
                    <td>
                        <span class="badge badge-<?= $row['origem_chave'] ?>">
                            <?= $row['origem_txt'] ?>
                        </span>
                    </td>
                    <td><strong>#<?= $row['id'] ?></strong></td>
                    <td><?= date('d/m/Y H:i', strtotime($row['data_pedido'])) ?></td>
                    <td><small><?= htmlspecialchars($row['nome_usuario']) ?></small></td>
                    <td><small style="color: #718096;"><?= htmlspecialchars($row['forma']) ?></small></td>
                    <td>
                        <?php if (!empty($row['bandeira'])): ?>
                            <strong><?= htmlspecialchars($row['bandeira']) ?></strong>
                        <?php else: ?>
                            <em style="color: #a0aec0;">Não Informado</em>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right; padding-right: 20px; font-weight: bold; color: #2d3748;">
                        R$ <?= number_format($row['valor_total'], 2, ',', '.') ?>
                    </td>
                    <td style="text-align: center;">
                        <?php if (!empty($row['bandeira_id'])): ?>
                            <span class="status-badge badge-c">Conferido</span>
                        <?php else: ?>
                            <span class="status-badge badge-p">Pendente</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 50px; color: #a0aec0; font-size: 16px;">
                        Nenhum registro encontrado correspondente aos filtros aplicados.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>