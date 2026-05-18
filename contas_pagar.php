<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$usuario_id = $_SESSION['usuario_id'];

// 1. Busca o caixa aberto
$stmt_caixa = $pdo->prepare("SELECT id, data_abertura FROM controle_caixas WHERE usuario_id = ? AND status = 'aberto' LIMIT 1");
$stmt_caixa->execute([$usuario_id]);
$caixa_atual = $stmt_caixa->fetch();

// 2. Filtros
$data_inicial = $_GET['data_inicial'] ?? date('Y-m-01');
$data_final   = $_GET['data_final']   ?? date('Y-m-t');
$fornecedor_id = $_GET['fornecedor_id'] ?? '';
$status = $_GET['status'] ?? 'Pendente';

// 3. Query das Contas com Filtro Dinâmico
$params = [$data_inicial, $data_final];
$where = "WHERE cp.data_vencimento BETWEEN ? AND ?";

if ($status !== 'Geral') {
    $where .= " AND cp.status = ?";
    $params[] = $status;
}

if ($fornecedor_id) {
    $where .= " AND cp.id_fornecedor = ?";
    $params[] = $fornecedor_id;
}

$sql = "SELECT cp.*, f.nome_fantasia, f.razao_social, p.descricao as plano_nome 
        FROM contas_pagar cp
        JOIN fornecedores f ON cp.id_fornecedor = f.id
        JOIN plano_contas p ON cp.id_plano_conta = p.id
        $where ORDER BY cp.data_vencimento ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cálculo do total
$total_periodo = 0;
foreach($contas as $c) { $total_periodo += $c['valor_total']; }

$fornecedores = $pdo->query("SELECT id, razao_social FROM fornecedores ORDER BY razao_social")->fetchAll();
$formas = $pdo->query("SELECT id, descricao FROM formas_pagamento WHERE LOWER(status) = 'ativo' ORDER BY descricao ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Contas a Pagar - Gestão Breno</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .container { max-width: 1150px; margin: auto; background: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .aviso-caixa { padding: 15px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; text-align: center; border: 1px solid; }
        .caixa-ok { background: #e6fffa; color: #2c7a7b; border-color: #b2f5ea; }
        .caixa-off { background: #fff5f5; color: #c53030; border-color: #fed7d7; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #f7fafc; padding: 15px; text-align: left; color: #4a5568; border-bottom: 2px solid #edf2f7; }
        td { padding: 15px; border-bottom: 1px solid #edf2f7; color: #2d3748; }
        
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-pendente { background: #feebc8; color: #9c4221; }
        .badge-pago { background: #c6f6d5; color: #22543d; }
        .badge-cancelado { background: #fed7d7; color: #c53030; }

        .info-impressao { display: none; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }

        /* Estilos do Modal */
        #modalBaixa { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; backdrop-filter: blur(4px); }
        .modal-content { background:#fff; width:90%; max-width:450px; margin:100px auto; padding:35px; border-radius:15px; box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
        .select-forma { width: 100% !important; height: 50px !important; padding: 10px 15px !important; border-radius: 8px !important; border: 2px solid #cbd5e0 !important; font-size: 16px !important; margin-top: 10px !important; background-color: #fff !important; display: block !important; cursor: pointer; }

        /* REGRAS DE IMPRESSÃO */
        @media print {
            body { background: white !important; padding: 0 !important; }
            .container { max-width: 100% !important; box-shadow: none !important; padding: 0 !important; }
            .aviso-caixa, form, .btn-voltar, .acao-col, button { display: none !important; }
            .info-impressao { display: block !important; } /* Mostra a legenda apenas no papel */
            table { border: 1px solid #dee2e6; width: 100%; }
            th, td { border: 1px solid #dee2e6 !important; padding: 8px !important; font-size: 12px; }
            .badge { border: 1px solid #ccc; background: transparent !important; color: black !important; }
        }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin:0; color: #2a63cd;">💰 Gestão de Contas a Pagar</h2>
        <a href="dashboard.php" class="btn-voltar">⬅ Voltar ao Painel</a>
    </div>

    <div class="info-impressao">
        <strong>Relatório de Contas a Pagar</strong><br>
        Período: <?= date('d/m/Y', strtotime($data_inicial)) ?> até <?= date('d/m/Y', strtotime($data_final)) ?><br>
        Situação: <?= ($status == 'Geral') ? 'Todas as contas' : $status ?><br>
        Emitido em: <?= date('d/m/Y H:i') ?>
    </div>

    <?php if ($caixa_atual): ?>
        <div class="aviso-caixa caixa-ok">✅ CAIXA ABERTO (#<?= $caixa_atual['id'] ?>) - Pronto para processar pagamentos.</div>
    <?php else: ?>
        <div class="aviso-caixa caixa-off">⚠️ ATENÇÃO: Seu caixa está fechado. Abra-o para habilitar as baixas.</div>
    <?php endif; ?>

    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-bottom: 30px; background: #f7fafc; padding: 20px; border-radius: 12px;">
        <div>
            <label style="font-size:11px; font-weight:bold; color:#718096;">Início</label>
            <input type="date" name="data_inicial" value="<?= $data_inicial ?>" style="width:100%; padding:8px; border-radius:6px; border:1px solid #e2e8f0;">
        </div>
        <div>
            <label style="font-size:11px; font-weight:bold; color:#718096;">Fim</label>
            <input type="date" name="data_final" value="<?= $data_final ?>" style="width:100%; padding:8px; border-radius:6px; border:1px solid #e2e8f0;">
        </div>
        <div>
            <label style="font-size:11px; font-weight:bold; color:#718096;">Fornecedor</label>
            <select name="fornecedor_id" style="width:100%; padding:8px; border-radius:6px; border:1px solid #e2e8f0;">
                <option value="">Todos</option>
                <?php foreach($fornecedores as $f): ?>
                    <option value="<?= $f['id'] ?>" <?= $fornecedor_id == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['razao_social']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:11px; font-weight:bold; color:#718096;">Situação</label>
            <select name="status" style="width:100%; padding:8px; border-radius:6px; border:1px solid #e2e8f0;">
                <option value="Pendente" <?= $status == 'Pendente' ? 'selected' : '' ?>>Ativas</option>
                <option value="Pago" <?= $status == 'Pago' ? 'selected' : '' ?>>Pagas</option>
                <option value="Cancelado" <?= $status == 'Cancelado' ? 'selected' : '' ?>>Canceladas</option>
                <option value="Geral" <?= $status == 'Geral' ? 'selected' : '' ?>>Geral (Tudo)</option>
            </select>
        </div>
        <div style="display: flex; align-items: flex-end; gap: 8px;">
            <button type="submit" style="flex:1; height:38px; background:#3182ce; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">🔍</button>
            <button type="button" onclick="window.print()" style="flex:1; height:38px; background:#4a5568; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">🖨️</button>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>Vencimento</th>
                <th>Fornecedor</th>
                <th>Valor</th>
                <th>Status</th>
                <th class="acao-col" style="text-align: center;">Ação</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($contas)): ?>
                <tr><td colspan="5" style="text-align:center; padding:30px; color:#a0aec0;">Nenhuma conta encontrada para este filtro.</td></tr>
            <?php endif; ?>

            <?php foreach($contas as $c): ?>
            <tr>
                <td style="font-weight: 500;"><?= date('d/m/Y', strtotime($c['data_vencimento'])) ?></td>
                <td><?= htmlspecialchars($c['nome_fantasia'] ?: $c['razao_social']) ?></td>
                <td style="color: #2d3748; font-weight: bold;">R$ <?= number_format($c['valor_total'], 2, ',', '.') ?></td>
                <td><span class="badge badge-<?= strtolower($c['status']) ?>"><?= $c['status'] ?></span></td>
                <td class="acao-col" style="text-align: center;">
                    <?php if ($c['status'] == 'Pendente' && $caixa_atual): ?>
                        <button onclick="baixar(<?= $c['id'] ?>, '<?= $c['valor_total'] ?>')" style="background: #38a169; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-weight:bold;">Baixar</button>
                    <?php elseif($c['status'] == 'Pago'): ?>
                        <small style="color: #718096;">Pago em <?= date('d/m/Y', strtotime($c['data_pagamento'])) ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f7fafc;">
                <td colspan="2" style="text-align:right; font-weight:bold;">TOTAL FILTRADO:</td>
                <td colspan="3" style="font-weight:bold; color:#e53e3e; font-size:16px;">R$ <?= number_format($total_periodo, 2, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<div id="modalBaixa">
    <div class="modal-content">
        <h3 style="margin-top:0; color:#2d3748;">Confirmar Pagamento</h3>
        <hr style="border: 0; border-top: 1px solid #edf2f7; margin: 20px 0;">
        <form action="processar_baixa_pagar.php" method="POST">
            <input type="hidden" name="id_conta" id="id_conta">
            <input type="hidden" name="id_caixa" value="<?= $caixa_atual['id'] ?? '' ?>">
            <p style="margin-bottom: 5px; color: #718096;">Valor do título:</p>
            <strong id="valor_txt" style="font-size: 28px; color: #38a169; display: block; margin-bottom: 25px;">R$ 0,00</strong>
            <label style="font-weight:bold; color:#4a5568;">Forma de Pagamento:</label>
            <select name="id_forma_pagamento" required class="select-forma">
                <option value="">-- Selecione --</option>
                <?php foreach($formas as $f): ?>
                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['descricao']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" style="width:100%; background:#38a169; color:white; border:none; padding:16px; border-radius:8px; margin-top:30px; cursor:pointer; font-weight:bold;">CONFIRMAR PAGAMENTO</button>
            <button type="button" onclick="document.getElementById('modalBaixa').style.display='none'" style="width:100%; background:none; color:#a0aec0; border:none; padding:10px; margin-top:10px; cursor:pointer;">Cancelar</button>
        </form>
    </div>
</div>

<script>
function baixar(id, valor) {
    document.getElementById('id_conta').value = id;
    document.getElementById('valor_txt').innerText = 'R$ ' + parseFloat(valor).toLocaleString('pt-br', {minimumFractionDigits: 2});
    document.getElementById('modalBaixa').style.display = 'block';
}
window.onclick = function(event) {
    var modal = document.getElementById('modalBaixa');
    if (event.target == modal) { modal.style.display = "none"; }
}
</script>

</body>
</html>