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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5; --success: #10b981; --danger: #ef4444; --bg: #f3f4f6;
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg); margin: 0; padding: 20px; color: #374151; }
        .container { max-width: 1100px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 30px; }
        h1 { margin: 0; font-size: 24px; color: #111827; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; background: #f9fafb; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .form-group { display: flex; flex-direction: column; }
        label { font-size: 12px; font-weight: 600; text-transform: uppercase; margin-bottom: 5px; color: #6b7280; }
        input, select { padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; outline: none; }
        
        .btn-group { display: flex; gap: 10px; margin-top: 10px; }
        button, .btn { padding: 10px 20px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; text-decoration: none; font-size: 14px; transition: 0.2s; }
        .btn-filter { background: var(--primary); color: white; }
        .btn-print { background: #374151; color: white; }
        .btn-back { background: #d1d5db; color: #374151; }
        button:hover { opacity: 0.9; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f9fafb; text-align: left; padding: 12px; border-bottom: 2px solid #eee; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 14px; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-receber { background: #dcfce7; color: #166534; }
        .badge-pagar { background: #fee2e2; color: #991b1b; }

        .footer-summary { margin-top: 30px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .card { padding: 20px; border-radius: 8px; color: white; text-align: center; }
        
        @media print { .no-print { display: none; } body { padding: 0; } .container { box-shadow: none; width: 100%; max-width: 100%; } }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1>Relatório Financeiro</h1>
            <small><?php echo date('d/m/Y', strtotime($data_inicial)); ?> — <?php echo date('d/m/Y', strtotime($data_final)); ?></small>
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
                                <?php echo $cli['nome']; ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Fornecedores">
                        <?php foreach($fornecedores_lista as $for): ?>
                            <option value="<?php echo $for['id']; ?>" <?php echo $pessoa_id == $for['id'] ? 'selected' : ''; ?>>
                                <?php echo $for['nome']; ?>
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
                <td><?php echo date('d/m/Y', strtotime($item['data_vencimento'])); ?></td>
                <td><?php echo $item['pessoa']; ?></td>
                <td>
                    <span class="badge <?php echo $item['tipo'] == 'Receber' ? 'badge-receber' : 'badge-pagar'; ?>">
                        <?php echo strtoupper($item['tipo']); ?>
                    </span>
                </td>
                <td><?php echo $item['status']; ?></td>
                <td style="text-align: right; font-weight: 600;">
                    R$ <?php echo number_format($item['valor_total'], 2, ',', '.'); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer-summary">
        <div class="card" style="background: var(--success);">
            <small>Total Receber</small>
            <div style="font-size: 20px; font-weight: 600;">R$ <?php echo number_format($t_receber, 2, ',', '.'); ?></div>
        </div>
        <div class="card" style="background: var(--danger);">
            <small>Total Pagar</small>
            <div style="font-size: 20px; font-weight: 600;">R$ <?php echo number_format($t_pagar, 2, ',', '.'); ?></div>
        </div>
        <div class="card" style="background: var(--primary);">
            <small>Saldo Líquido</small>
            <div style="font-size: 20px; font-weight: 600;">R$ <?php echo number_format($t_receber - $t_pagar, 2, ',', '.'); ?></div>
        </div>
    </div>
</div>

</body>
</html>