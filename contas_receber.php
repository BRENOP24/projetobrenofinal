<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$usuario_id = $_SESSION['usuario_id'];

// 1. Caixa aberto
$stmt_caixa = $pdo->prepare("
    SELECT id, data_abertura 
    FROM controle_caixas 
    WHERE usuario_id = ? AND status = 'aberto' 
    LIMIT 1
");
$stmt_caixa->execute([$usuario_id]);
$caixa_atual = $stmt_caixa->fetch();

// 2. Filtros
$data_inicial = $_GET['data_inicial'] ?? date('Y-m-01');
$data_final   = $_GET['data_final']   ?? date('Y-m-t');
$cliente_id   = $_GET['cliente_id']   ?? '';
$status       = $_GET['status']       ?? 'Todos';

// Normaliza
$status = ucfirst(strtolower($status));
$status_permitidos = ['Pendente', 'Recebido', 'Todos'];

if (!in_array($status, $status_permitidos)) {
    $status = 'Todos';
}

// 🔥 WHERE CORRETO (pagamento vs vencimento)
$where = "WHERE (
    (cr.status = 'Recebido' AND DATE(cr.data_pagamento) BETWEEN ? AND ?)
    OR
    (cr.status != 'Recebido' AND cr.data_vencimento BETWEEN ? AND ?)
)";
$params = [$data_inicial, $data_final, $data_inicial, $data_final];

// filtro status
if ($status !== 'Todos') {
    $where .= " AND LOWER(cr.status) = LOWER(?)";
    $params[] = $status;
}

// filtro cliente
if (!empty($cliente_id)) {
    $where .= " AND cr.id_cliente = ?";
    $params[] = $cliente_id;
}

// 3. Query
$sql = "
    SELECT cr.*, c.nome as cliente_nome 
    FROM contas_receber cr
    JOIN clientes c ON cr.id_cliente = c.id
    $where
    ORDER BY cr.data_vencimento ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Total
$total_periodo = 0;
foreach ($contas as $c) {
    $total_periodo += (float)$c['valor_total'];
}

// 5. Selects
$clientes = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome")->fetchAll();

$formas = $pdo->query("
    SELECT id, descricao 
    FROM formas_pagamento 
    WHERE LOWER(status) = 'ativo'
    ORDER BY descricao ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Contas a Receber - Gestão Breno</title>
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
        .badge-recebido { background: #c6f6d5; color: #22543d; }
        .badge-cancelado { background: #fed7d7; color: #c53030; }

        #modalBaixa { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; backdrop-filter: blur(4px); }
        .modal-content { background:#fff; width:90%; max-width:450px; margin:100px auto; padding:35px; border-radius:15px; box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
        .select-forma { width: 100% !important; height: 50px !important; border-radius: 8px !important; border: 2px solid #cbd5e0 !important; margin-top: 10px !important; }

        @media print {
            .aviso-caixa, form, .btn-voltar, .acao-col, button { display: none !important; }
        }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin:0; color: #145fe9;">💰 Gestão de Contas a Receber</h2>
        <a href="dashboard.php" class="btn-voltar" style="text-decoration:none;font-weight:500;">⬅ Voltar ao Painel</a>
    </div>

    <?php if ($caixa_atual): ?>
        <div class="aviso-caixa caixa-ok">✅ CAIXA ABERTO (#<?= $caixa_atual['id'] ?>) - Pronto para registrar recebimentos.</div>
    <?php else: ?>
        <div class="aviso-caixa caixa-off">⚠️ ATENÇÃO: Seu caixa está fechado. Abra-o para dar baixa nos títulos.</div>
    <?php endif; ?>

<form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-bottom: 30px; background: #f7fafc; padding: 20px; border-radius: 12px;">

    <div>
        <label style="font-size:11px; font-weight:bold; color:#718096;">Vencimento Início</label>
        <input type="date" name="data_inicial" value="<?= $data_inicial ?>" style="width:100%; padding:8px; border-radius:6px; border:1px solid #e2e8f0;">
    </div>

    <div>
        <label style="font-size:11px; font-weight:bold; color:#718096;">Vencimento Fim</label>
        <input type="date" name="data_final" value="<?= $data_final ?>" style="width:100%; padding:8px; border-radius:6px; border:1px solid #e2e8f0;">
    </div>

    <div>
        <label style="font-size:11px; font-weight:bold; color:#718096;">Cliente</label>
        <select name="cliente_id" style="width:100%; padding:8px; border-radius:6px; border:1px solid #e2e8f0;">
            <option value="">Todos</option>
            <?php foreach($clientes as $cl): ?>
                <option value="<?= $cl['id'] ?>" <?= $cliente_id == $cl['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cl['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- STATUS -->
    <div>
        <label style="font-size:11px; font-weight:bold; color:#718096;">Status</label>
        <select name="status" style="width:100%; padding:8px; border-radius:6px; border:1px solid #e2e8f0;">
            <option value="Pendente" <?= $status == 'Pendente' ? 'selected' : '' ?>>Pendentes</option>
            <option value="Recebido" <?= $status == 'Recebido' ? 'selected' : '' ?>>Pagas</option>
            <option value="Todos" <?= $status == 'Todos' ? 'selected' : '' ?>>Todas</option>
        </select>
    </div>

    <!-- BOTÃO -->
    <div style="display:flex; align-items:flex-end;">
        <button type="submit" style="
            width:100%;
            height:38px;
            background:#2f855a;
            color:white;
            border:none;
            border-radius:6px;
            font-weight:bold;
            cursor:pointer;
        ">
            FILTRAR
        </button>
    </div>

</form>

    <table>
        <thead>
            <tr>
                <th>Vencimento</th>
                <th>Cliente</th>
                <th>Valor</th>
                <th>Status</th>
                <th class="acao-col" style="text-align: center;">Ação</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($contas) == 0): ?>
                <tr><td colspan="5" style="text-align:center; padding:50px; color:#a0aec0;">Nenhum título encontrado para este período.</td></tr>
            <?php endif; ?>

            <?php foreach($contas as $c): ?>
            <tr>
                <td style="font-weight: 500;"><?= date('d/m/Y', strtotime($c['data_vencimento'])) ?></td>
                <td><?= htmlspecialchars($c['cliente_nome']) ?></td>
                <td style="color: #2d3748; font-weight: bold;">R$ <?= number_format($c['valor_total'], 2, ',', '.') ?></td>
                <td>
                    <span class="badge badge-<?= strtolower($c['status']) ?>">
                        <?= $c['status'] ?>
                    </span>
                </td>
                <td class="acao-col" style="text-align: center;">
                    <?php if ($c['status'] == 'Pendente' && $caixa_atual): ?>
                        <button onclick="baixar(<?= $c['id'] ?>, '<?= $c['valor_total'] ?>')" style="background: #38a169; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-weight:bold;">Receber</button>
                    <?php elseif($c['status'] == 'Recebido'): ?>
                        <small style="color: #718096;">Pago em <?= date('d/m/Y', strtotime($c['data_pagamento'])) ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f7fafc;">
                <td colspan="2" style="text-align:right; font-weight:bold;">TOTAL NO PERÍODO:</td>
                <td colspan="3" style="font-weight:bold; color:#2f855a; font-size:16px;">R$ <?= number_format($total_periodo, 2, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<div id="modalBaixa">
    <div class="modal-content">
        <h3 style="margin-top:0;">Confirmar Recebimento</h3>
        <form action="processar_baixa_receber.php" method="POST">
            <input type="hidden" name="id_conta" id="id_conta">
            <input type="hidden" name="id_caixa" value="<?= $caixa_atual['id'] ?? '' ?>">
            
            <p>Valor:</p>
            <strong id="valor_txt" style="font-size: 24px; color: #38a169;">R$ 0,00</strong>
            
            <label style="display:block; margin-top:20px;">Forma de Pagamento:</label>
            <select name="id_forma_pagamento" required class="select-forma">
                <?php foreach($formas as $f): ?>
                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['descricao']) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" style="width:100%; background:#38a169; color:white; border:none; padding:15px; border-radius:8px; margin-top:20px; cursor:pointer; font-weight:bold;">BAIXAR TÍTULO</button>
            <button type="button" onclick="document.getElementById('modalBaixa').style.display='none'" style="width:100%; background:none; border:none; margin-top:10px; cursor:pointer; color:#718096;">Cancelar</button>
        </form>
    </div>
</div>

<script>
function baixar(id, valor) {
    document.getElementById('id_conta').value = id;
    document.getElementById('valor_txt').innerText = 'R$ ' + parseFloat(valor).toLocaleString('pt-br', {minimumFractionDigits: 2});
    document.getElementById('modalBaixa').style.display = 'block';
}
</script>

</body>
</html>