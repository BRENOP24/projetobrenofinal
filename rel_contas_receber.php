<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// 1. Filtros da URL
$data_inicial = $_GET['data_inicial'] ?? date('Y-m-01');
$data_final   = $_GET['data_final']   ?? date('Y-m-t');
$tipo_filtro  = $_GET['tipo_filtro']  ?? 'Todos';
$status       = $_GET['status']       ?? 'Geral';
$pessoa_id    = $_GET['pessoa_id']    ?? ''; // ID unificado para filtro

$todos_lancamentos = [];

// 2. Busca listas para o filtro de Pessoa
$clientes_lista = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$fornecedores_lista = $pdo->query("SELECT id, razao_social as nome FROM fornecedores ORDER BY razao_social ASC")->fetchAll(PDO::FETCH_ASSOC);

// 3. Lógica de Busca (Receber)
if ($tipo_filtro == 'Todos' || $tipo_filtro == 'Receber') {
    $params_receber = [$data_inicial, $data_final];
    $where_receber = "WHERE cr.data_vencimento BETWEEN ? AND ?";
    
    if ($status !== 'Geral') { $where_receber .= " AND cr.status = ?"; $params_receber[] = $status; }
    if ($pessoa_id) { $where_receber .= " AND cr.id_cliente = ?"; $params_receber[] = $pessoa_id; }

    $sql_receber = "SELECT cr.data_vencimento, cr.valor_total, cr.status, c.nome as pessoa, 'Receber' as tipo 
                    FROM contas_receber cr
                    JOIN clientes c ON cr.id_cliente = c.id $where_receber";
    $stmt = $pdo->prepare($sql_receber);
    $stmt->execute($params_receber);
    $todos_lancamentos = array_merge($todos_lancamentos, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// 4. Lógica de Busca (Pagar)
if ($tipo_filtro == 'Todos' || $tipo_filtro == 'Pagar') {
    $params_pagar = [$data_inicial, $data_final];
    $where_pagar = "WHERE cp.data_vencimento BETWEEN ? AND ?";
    
    if ($status !== 'Geral') { $where_pagar .= " AND cp.status = ?"; $params_pagar[] = $status; }
    if ($pessoa_id) { $where_pagar .= " AND cp.id_fornecedor = ?"; $params_pagar[] = $pessoa_id; }

    $sql_pagar = "SELECT cp.data_vencimento, cp.valor_total, cp.status, f.razao_social as pessoa, 'Pagar' as tipo 
                  FROM contas_pagar cp
                  JOIN fornecedores f ON cp.id_fornecedor = f.id $where_pagar";
    $stmt = $pdo->prepare($sql_pagar);
    $stmt->execute($params_pagar);
    $todos_lancamentos = array_merge($todos_lancamentos, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// Ordenação por data
usort($todos_lancamentos, function($a, $b) {
    return strtotime($a['data_vencimento']) - strtotime($b['data_vencimento']);
});
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório Financeiro Profissional</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5; 
            --success: #10b981; 
            --danger: #ef4444; 
            --bg: #f8fafc;
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg); margin: 0; padding: 20px; color: #334155; }
        .container { max-width: 1200px; margin: auto; background: white; padding: 30px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); }
        
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 25px; }
        h1 { margin: 0; font-size: 24px; font-weight: 700; color: #0f172a; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; background: #f1f5f9; padding: 20px; border-radius: 12px; margin-bottom: 25px; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; }
        label { font-size: 11px; font-weight: 700; text-transform: uppercase; margin-bottom: 6px; color: #475569; letter-spacing: 0.5px; }
        input, select { padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-size: 14px; background: white; font-family: inherit; }
        input:focus, select:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1); }
        
        .btn-group { display: flex; gap: 8px; }
        button, .btn { padding: 11px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; text-decoration: none; font-size: 14px; transition: 0.2s; text-align: center; }
        .btn-filter { background: var(--primary); color: white; flex: 1; }
        .btn-print { background: #334155; color: white; }
        .btn-back { background: #e2e8f0; color: #334155; font-size: 13px; padding: 8px 16px; }
        button:hover, .btn:hover { opacity: 0.9; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f8fafc; text-align: left; padding: 14px; border-bottom: 2px solid #e2e8f0; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        td { padding: 14px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        tr:hover td { background-color: #f8fafc; }
        
        .badge { padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-block; text-align: center; }
        .badge-receber { background: #dcfce7; color: #15803d; }
        .badge-pagar { background: #fee2e2; color: #b91c1c; }

        .footer-summary { margin-top: 30px; display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
        .card { padding: 20px; border-radius: 12px; color: white; }
        .card small { font-size: 12px; font-weight: 600; text-transform: uppercase; opacity: 0.8; letter-spacing: 0.5px; }
        .card .value { font-size: 24px; font-weight: 700; margin-top: 5px; }
        
        @media print { 
            .no-print { display: none !important; } 
            body { padding: 0; background: white; } 
            .container { box-shadow: none; padding: 0; max-width: 100%; } 
            th { background: #f1f5f9 !important; color: black !important; }
            .card { border: 1px solid #cbd5e1 !important; color: black !important; background: none !important; }
            .card small { color: #475569; }
            .card .value { color: black; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1>Relatório Financeiro</h1>
            <small style="color: #64748b; font-weight: 500;"><?php echo date('d/m/Y', strtotime($data_inicial)); ?> — <?php echo date('d/m/Y', strtotime($data_final)); ?></small>
        </div>
        <div class="no-print">
            <a href="dashboard.php" class="btn btn-back">← Voltar ao Dashboard</a>
        </div>
    </div>

    <div class="no-print filtro-area">
        <form method="GET" class="form-grid">
            <div class="form-group">
                <label>Data Inicial</label>
                <input type="date" name="data_inicial" value="<?php echo $data_inicial; ?>">
            </div>
            <div class="form-group">
                <label>Data Final</label>
                <input type="date" name="data_final" value="<?php echo $data_final; ?>">
            </div>
            <div class="form-group">
                <label>Fluxo</label>
                <select name="tipo_filtro">
                    <option value="Todos" <?php echo $tipo_filtro == 'Todos' ? 'selected' : ''; ?>>Todos</option>
                    <option value="Receber" <?php echo $tipo_filtro == 'Receber' ? 'selected' : ''; ?>>A Receber</option>
                    <option value="Pagar" <?php echo $tipo_filtro == 'Pagar' ? 'selected' : ''; ?>>A Pagar</option>
                </select>
            </div>
            <div class="form-group">
                <label>Cliente/Fornecedor</label>
                <select name="pessoa_id">
                    <option value="">Todos</option>
                    <optgroup label="Clientes">
                        <?php foreach($clientes_lista as $cli): ?>
                            <option value="<?php echo $cli['id']; ?>" <?php echo $pessoa_id == $cli['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cli['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Fornecedores">
                        <?php foreach($fornecedores_lista as $for): ?>
                            <option value="<?php echo $for['id']; ?>" <?php echo $pessoa_id == $for['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($for['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
            <div class="btn-group">
                <button type="submit" class="btn-filter">Filtrar</button>
                <button type="button" onclick="window.print();" class="btn-print">Imprimir</button>
            </div>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>VENCIMENTO</th>
                <th>PESSOA (CLIENTE/FORNEC.)</th>
                <th>TIPO</th>
                <th>STATUS</th>
                <th style="text-align: right;">VALOR</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $t_receber = 0; $t_pagar = 0;
            foreach($todos_lancamentos as $item): 
                $item['tipo'] == 'Receber' ? $t_receber += $item['valor_total'] : $t_pagar += $item['valor_total'];
            ?>
            <tr>
                <td style="font-weight: 500;"><?php echo date('d/m/Y', strtotime($item['data_vencimento'])); ?></td>
                <td><?php echo htmlspecialchars($item['pessoa']); ?></td>
                <td>
                    <span class="badge <?php echo $item['tipo'] == 'Receber' ? 'badge-receber' : 'badge-pagar'; ?>">
                        <?php echo strtoupper($item['tipo']); ?>
                    </span>
                </td>
                <td>
                    <span style="font-weight: 500; font-size: 13px;"><?php echo htmlspecialchars($item['status']); ?></span>
                </td>
                <td style="text-align: right; font-weight: 600; color: <?php echo $item['tipo'] == 'Receber' ? '#16a34a' : '#dc2626'; ?>">
                    <?php echo $item['tipo'] == 'Receber' ? '+' : '-'; ?> R$ <?php echo number_format($item['valor_total'], 2, ',', '.'); ?>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($todos_lancamentos)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #64748b; padding: 30px;">Nenhum lançamento encontrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer-summary">
        <div class="card" style="background: linear-gradient(135deg, #10b981, #059669);">
            <small>Total Receber</small>
            <div class="value">R$ <?php echo number_format($t_receber, 2, ',', '.'); ?></div>
        </div>
        <div class="card" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <small>Total Pagar</small>
            <div class="value">R$ <?php echo number_format($t_pagar, 2, ',', '.'); ?></div>
        </div>
        <?php $saldo = $t_receber - $t_pagar; ?>
        <div class="card" style="background:  <?php echo $saldo >= 0 ? 'linear-gradient(135deg, #4f46e5, #4338ca)' : 'linear-gradient(135deg, #ef4444, #dc2626)'; ?>;">
            <small>Saldo Líquido</small>
            <div class="value">R$ <?php echo number_format($saldo, 2, ',', '.'); ?></div>
        </div>
    </div>
</div>

</body>
</html>