<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// 1. Definição dos Filtros (Datas, Horas e Clientes)
$data_inicial = $_GET['data_inicial'] ?? date('Y-m-01');
$data_final   = $_GET['data_final']   ?? date('Y-m-t');
$hora_inicial = $_GET['hora_inicial'] ?? '00:00';
$hora_final   = $_GET['hora_final']   ?? '23:59';
$clientes_selecionados = $_GET['clientes_ids'] ?? []; 

// Monta o timestamp completo unindo Data + Hora:segundos
$timestamp_inicial = $data_inicial . ' ' . $hora_inicial . ':00';
$timestamp_final   = $data_final . ' ' . $hora_final . ':59';

// 2. Construção da Query com as novas colunas e filtros de horário
$params = [$timestamp_inicial, $timestamp_final];
$where = "WHERE p.data_pedido BETWEEN ? AND ? 
          AND p.situacao = 'finalizado'
          AND p.origem_tipo IN ('balcao', 'delivery')";

if (!empty($clientes_selecionados)) {
    $placeholders = implode(',', array_fill(0, count($clientes_selecionados), '?'));
    $where .= " AND p.cliente_id IN ($placeholders)";
    foreach($clientes_selecionados as $id) {
        $params[] = $id;
    }
}

$sql = "SELECT p.*, c.nome as nome_cliente 
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        $where 
        ORDER BY p.data_pedido DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cálculos de Totais (Como a query já filtra os finalizados, basta somar direto)
$total_vendas = 0;
foreach($vendas as $v) { 
    $total_vendas += (float)$v['valor_total']; 
}

$lista_clientes = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Vendas - Gestão Breno</title>
    
    <!-- Bibliotecas para Busca Inteligente (Select2) -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .container { max-width: 1150px; margin: auto; background: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #f7fafc; padding: 15px; text-align: left; color: #4a5568; border-bottom: 2px solid #edf2f7; font-size: 14px; }
        td { padding: 15px; border-bottom: 1px solid #edf2f7; color: #2d3748; font-size: 14px; }
        
        tbody tr:nth-child(even) { background-color: #fcfcfc; }
        tbody tr:hover { background-color: #f1f5f9; transition: 0.2s; }

        .badge-finalizado { background: #c6f6d5; color: #22543d; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        
        .filtro-card { background: #f7fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 30px; }
        
        .select2-container--default .select2-selection--multiple { border: 1px solid #cbd5e0 !important; border-radius: 6px !important; min-height: 42px; }

        @media print {
            form, .btn-voltar, .no-print, .select2-container { display: none !important; }
            .container { box-shadow: none; padding: 0; max-width: 100%; }
            body { background: #fff; }
        }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 2px solid #3182ce; padding-bottom: 10px;">
        <h2 style="margin:0; color: #1a202c;">📈 Relatório de Vendas (Presencial & Delivery)</h2>
        <div class="no-print">
            <button onclick="exportarExcel()" style="background:#38a169; color:white; border:none; padding:8px 15px; border-radius:6px; font-weight:bold; cursor:pointer; margin-right:10px;">Excel 📥</button>
            <a href="dashboard.php" class="btn-voltar" style="text-decoration:none; color:#718096; font-weight:500;">← Voltar</a>
        </div>
    </div>

    <!-- Formulário com novos campos de Hora -->
    <form method="GET" id="formFiltro" class="filtro-card" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px;">
        <div>
            <label style="font-size:11px; font-weight:bold; color:#718096; display:block; margin-bottom:5px;">DATA INÍCIO</label>
            <input type="date" name="data_inicial" value="<?= $data_inicial ?>" style="width:100%; padding:10px; border-radius:6px; border:1px solid #cbd5e0;">
        </div>
        <div>
            <label style="font-size:11px; font-weight:bold; color:#718096; display:block; margin-bottom:5px;">HORA INÍCIO</label>
            <input type="time" name="hora_inicial" value="<?= $hora_inicial ?>" style="width:100%; padding:10px; border-radius:6px; border:1px solid #cbd5e0;">
        </div>
        <div>
            <label style="font-size:11px; font-weight:bold; color:#718096; display:block; margin-bottom:5px;">DATA FIM</label>
            <input type="date" name="data_final" value="<?= $data_final ?>" style="width:100%; padding:10px; border-radius:6px; border:1px solid #cbd5e0;">
        </div>
        <div>
            <label style="font-size:11px; font-weight:bold; color:#718096; display:block; margin-bottom:5px;">HORA FIM</label>
            <input type="time" name="hora_final" value="<?= $hora_final ?>" style="width:100%; padding:10px; border-radius:6px; border:1px solid #cbd5e0;">
        </div>
        <div style="grid-column: span 1; min-width: 180px;">
            <label style="font-size:11px; font-weight:bold; color:#718096; display:block; margin-bottom:5px;">CLIENTES</label>
            <select name="clientes_ids[]" multiple class="select-busca" style="width:100%;">
                <?php foreach($lista_clientes as $cl): ?>
                    <option value="<?= $cl['id'] ?>" <?= in_array($cl['id'], $clientes_selecionados) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display: flex; align-items: flex-end; gap: 8px;">
            <button type="submit" style="flex:2; height:42px; background:#3182ce; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">🔍 FILTRAR</button>
            <button type="button" onclick="window.print()" style="flex:1; height:42px; background:#4a5568; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">🖨️</button>
        </div>
    </form>

    <table id="tabelaVendas">
        <thead>
            <tr>
                <th>Data / Hora</th>
                <th>Cód. Pedido</th>
                <th>Cliente</th>
                <th>Tipo Origem</th>
                <th>Valor Total</th>
                <th style="text-align:center;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($vendas)): ?>
                <tr><td colspan="6" style="text-align:center; padding:30px; color:#718096;">Nenhuma venda encontrada para os critérios selecionados.</td></tr>
            <?php endif; ?>

            <?php foreach($vendas as $v): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($v['data_pedido'])) ?></td>
                <td><strong>#<?= str_pad($v['id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                <td><?= htmlspecialchars($v['nome_cliente'] ?: 'Consumidor Final') ?></td>
                <td style="text-transform: capitalize;"><?= htmlspecialchars($v['origem_tipo']) ?></td>
                <td style="font-weight:bold;">R$ <?= number_format($v['valor_total'], 2, ',', '.') ?></td>
                <td style="text-align:center;"><span class="badge-finalizado">Finalizado</span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f7fafc;">
                <td colspan="4" style="text-align:right; font-weight:bold; padding:20px;">TOTAL EM VENDAS BALCÃO/DELIVERY:</td>
                <td colspan="2" style="font-weight:bold; color:#38a169; font-size:18px; padding:20px;">R$ <?= number_format($total_vendas, 2, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<script>
$(document).ready(function() {
    $('.select-busca').select2({
        placeholder: " Procure por nomes...",
        allowClear: true
    });
});

function exportarExcel() {
    let csv = [];
    let rows = document.querySelectorAll("table tr");
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) {
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/,/g, ".");
            row.push(data);
        }
        csv.push(row.join(";"));
    }

    let csv_string = csv.join("\n");
    let filename = 'relatorio_vendas_' + new Date().toLocaleDateString() + '.csv';
    let link = document.createElement("a");
    link.style.display = 'none';
    link.setAttribute('target', '_blank');
    link.setAttribute('href', 'data:text/csv;charset=utf-8,%EF%BB%BF' + encodeURIComponent(csv_string));
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

</body>
</html>
