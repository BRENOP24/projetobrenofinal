<?php
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

if ($_SESSION['nivel'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// --- FILTROS ---
$ver_conferidos = filter_input(INPUT_GET, 'ver_conferidos', FILTER_VALIDATE_INT) == 1;
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');

// --- CONFERIR CAIXA ---
if (isset($_POST['btn_confirmar_conferencia'])) {
    $id_caixa = filter_input(INPUT_POST, 'id_caixa', FILTER_VALIDATE_INT);
    $obs_admin = trim($_POST['observacao_adm'] ?? '');

    if (!$id_caixa) {
        die('ID inválido');
    }

    $sql = "
        UPDATE controle_caixas 
        SET status = 'conferido',
            notas_admin = :obs
        WHERE id = :id
        AND status = 'fechado'
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':obs' => $obs_admin,
        ':id' => $id_caixa
    ]);

    header("Location: conferencia_caixas.php?sucesso=1&ver_conferidos=1");
    exit;
}

// --- BUSCA ---
$status_busca = $ver_conferidos ? 'conferido' : 'fechado';

$sql = "
    SELECT c.*, u.nome as operador 
    FROM controle_caixas c 
    JOIN usuarios u ON c.usuario_id = u.id 
    WHERE c.status = :status
    AND c.data_fechamento BETWEEN :data_inicio AND :data_fim
    ORDER BY c.data_fechamento DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':status'      => $status_busca,
    ':data_inicio' => $data_inicio . ' 00:00:00',
    ':data_fim'    => $data_fim . ' 23:59:59'
]);
$caixas_brutos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$caixas = [];
foreach ($caixas_brutos as $item) {
    $id_c = $item['id'];

    // 🔹 TOTAL DE ENTRADAS (GERAL)
    $stmt_total = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM movimentacoes_caixa WHERE id_caixa = ? AND tipo = 'entrada'");
    $stmt_total->execute([$id_c]);
    $item['total_entradas'] = $stmt_total->fetchColumn();

    // 🔹 SAÍDAS
    $stmt_saida = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM movimentacoes_caixa WHERE id_caixa = ? AND tipo = 'saida'");
    $stmt_saida->execute([$id_c]);
    $item['total_saidas'] = $stmt_saida->fetchColumn();

    // 🔹 SANGRIAS (Adicionado para controle de retiradas)
    $stmt_sangria = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM sangrias WHERE caixa_id = ?");
    $stmt_sangria->execute([$id_c]);
    $item['total_sangrias'] = $stmt_sangria->fetchColumn();

    $caixas[] = $item;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Conferência de Caixas - Gestão Breno</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; padding: 20px; }
        .container-fluid { max-width: 1100px; margin: auto; background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header-section { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; margin-bottom: 20px; padding-bottom: 15px; }
        .btn-voltar { background: #6c757d; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; font-size: 0.9rem; }
        .filter-container { background: #f8f9fa; border: 1px solid #e9ecef; padding: 15px; border-radius: 8px; margin-bottom: 25px; }
        .card-caixa { border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 25px; overflow: hidden; background: white; }
        .card-caixa-header { background: #f1f3f5; padding: 12px 15px; display: flex; justify-content: space-between; font-weight: bold; border-bottom: 1px solid #dee2e6; }
        .table-conferencia { width: 100%; border-collapse: collapse; }
        .table-conferencia th { background: #fff; color: #666; font-size: 0.75rem; text-transform: uppercase; padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .table-conferencia td { padding: 12px; border-bottom: 1px solid #f8f9fa; }
        .val-negativo { color: #dc3545; font-weight: bold; }
        .val-positivo { color: #28a745; font-weight: bold; }
        .linha-saida { background: #fff5f5; color: #c53030; }
        .linha-receber { background: #f0fff4; color: #276749; }
        .linha-prazo { background: #f8f9fa; color: #6c757d; }
        .input-obs { width: 100%; height: 60px; padding: 10px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 10px; resize: none; box-sizing: border-box;}
        .btn-confirmar { background: #28a745; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer; font-weight: bold; width: 100%; }
        .badge-status { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; color: white; }
        .bg-conferido { background: #28a745; }
        
        .form-filtros { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e9ecef; }
        .form-grupo { display: flex; flex-direction: column; gap: 5px; }
        .form-grupo label { font-size: 0.85rem; font-weight: bold; color: #495057; }
        .form-controle { padding: 6px 12px; border: 1px solid #ced4da; border-radius: 6px; font-family: inherit; font-size: 0.9rem; color: #495057; background-color: #fff; }
        
        .btn-filtrar { 
            background: #007bff; 
            color: white; 
            border: none; 
            padding: 8px 18px; 
            border-radius: 6px; 
            font-weight: 600; 
            font-size: 0.85rem;
            cursor: pointer; 
            transition: background 0.2s ease, transform 0.1s ease;
            height: 35px; 
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-filtrar:hover { background: #0056b3; }
        .btn-filtrar:active { transform: scale(0.98); }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="header-section">
        <h2 style="margin:0;">🕵️ Conferência de Caixas</h2>
        <a href="dashboard.php" class="btn-voltar">⬅ Voltar ao Painel</a>
    </div>

    <div class="filter-container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin:0; font-size: 1.1rem; color: #333;">
                <?= $ver_conferidos ? "✅ Histórico de Caixas Conferidos" : "📥 Caixas Aguardando Conferência" ?>
            </h3>
            <a href="<?= $ver_conferidos ? 'conferencia_caixas.php?data_inicio='.$data_inicio.'&data_fim='.$data_fim : 'conferencia_caixas.php?ver_conferidos=1&data_inicio='.$data_inicio.'&data_fim='.$data_fim ?>" style="text-decoration:none; color:#007bff; font-weight:bold;">
                <?= $ver_conferidos ? "⬅ Ver Pendentes" : "🔎 Ver Histórico" ?>
            </a>
        </div>

        <form method="GET" class="form-filtros">
            <?php if($ver_conferidos): ?>
                <input type="hidden" name="ver_conferidos" value="1">
            <?php endif; ?>
            
            <div class="form-grupo">
                <label for="data_inicio">Data Inicial</label>
                <input type="date" name="data_inicio" id="data_inicio" class="form-controle" value="<?= htmlspecialchars($data_inicio) ?>">
            </div>

            <div class="form-grupo">
                <label for="data_fim">Data Final</label>
                <input type="date" name="data_fim" id="data_fim" class="form-controle" value="<?= htmlspecialchars($data_fim) ?>">
            </div>

            <button type="submit" class="btn-filtrar">🔍 Filtrar</button>
        </form>
    </div>

    <?php if (empty($caixas)): ?>
        <p style="text-align:center; color:#666;">Nenhum caixa encontrado para este período.</p>
    <?php endif; ?>

    <?php foreach ($caixas as $caixa): 
        $informado = json_decode($caixa['observacao_adm'], true); 

        $sql_vendas = "
            SELECT forma_id, nome_pagto, SUM(valor_total) as total 
            FROM (
                SELECT p.forma_pagamento_id as forma_id, f.descricao as nome_pagto, p.valor_total 
                FROM pedidos p 
                JOIN formas_pagamento f ON p.forma_pagamento_id = f.id 
                WHERE p.caixa_id = :caixa_id1
                
                UNION ALL
                
                SELECT po.forma_pagamento_id as forma_id, f.descricao as nome_pagto, po.valor_total 
                FROM pedidos_online po 
                JOIN formas_pagamento f ON po.forma_pagamento_id = f.id 
                WHERE po.id_caixa = :caixa_id2 AND po.status = 'Finalizado'
            ) as vendas_unificadas
            GROUP BY forma_id, nome_pagto
        ";
        
        $stmt_v = $pdo->prepare($sql_vendas);
        $stmt_v->execute([
            ':caixa_id1' => $caixa['id'],
            ':caixa_id2' => $caixa['id']
        ]);
        $vendas_banco = $stmt_v->fetchAll(PDO::FETCH_ASSOC);

        $sql_receber = "SELECT f.descricao as nome_pagto, SUM(cr.valor_total) as total 
                        FROM contas_receber cr 
                        JOIN formas_pagamento f ON cr.id_forma_pagamento = f.id 
                        WHERE cr.id_caixa_baixa = ? AND cr.status = 'Recebido'
                        GROUP BY f.descricao";
        $stmt_r = $pdo->prepare($sql_receber);
        $stmt_r->execute([$caixa['id']]);
        $receber_banco = $stmt_r->fetchAll(PDO::FETCH_ASSOC);

        $total_saidas = (float)$caixa['total_saidas'];
        $total_sangrias = (float)$caixa['total_sangrias'];

        $sistema = ['dinheiro' => 0, 'pix' => 0, 'credito' => 0, 'debito' => 0];
        $total_vendas_prazo = 0;
        
        foreach ($vendas_banco as $venda) {
            $nome = mb_strtolower($venda['nome_pagto']);
            $id_f = (int)$venda['forma_id'];

            if ($id_f === 7 || str_contains($nome, 'prazo')) {
                $total_vendas_prazo += $venda['total'];
            } 
            elseif (str_contains($nome, 'dinheiro')) $sistema['dinheiro'] += $venda['total'];
            elseif (str_contains($nome, 'pix')) $sistema['pix'] += $venda['total'];
            elseif (str_contains($nome, 'credito') || str_contains($nome, 'cartão')) $sistema['credito'] += $venda['total'];
            elseif (str_contains($nome, 'debito')) $sistema['debito'] += $venda['total'];
        }

        foreach ($receber_banco as $rec) {
            $nome = mb_strtolower($rec['nome_pagto']);
            if (str_contains($nome, 'dinheiro')) $sistema['dinheiro'] += $rec['total'];
            elseif (str_contains($nome, 'pix')) $sistema['pix'] += $rec['total'];
            elseif (str_contains($nome, 'credito') || str_contains($nome, 'cartão')) $sistema['credito'] += $rec['total'];
            elseif (str_contains($nome, 'debito')) $sistema['debito'] += $rec['total'];
        }
    ?>

    <div class="card-caixa">
        <div class="card-caixa-header">
            <span>👤 Operador: <?= htmlspecialchars($caixa['operador']) ?></span>
            <span>📅 Fechamento: <?= date('d/m/Y H:i', strtotime($caixa['data_fechamento'])) ?></span>
        </div>
        
        <table class="table-conferencia">
            <thead>
                <tr>
                    <th>Meio de Pagamento</th>
                    <th>Esperado (Sistema)</th>
                    <th>Informado (Operador)</th>
                    <th>Diferença</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $meios = [
                    'dinheiro' => '💵 Dinheiro (Fisico)', 
                    'pix' => '📱 PIX', 
                    'credito' => '💳 Cartão Crédito', 
                    'debito' => '💳 Cartão Débito'
                ];
                foreach ($meios as $chave => $label): 
                    $v_sist = (float)($sistema[$chave] ?? 0);
                    
                    // Se for dinheiro, o valor esperado no caixa físico é: Vendas + Fundo de Reserva - Sangrias
                    if ($chave === 'dinheiro') {
                        $v_sist = $v_sist + (float)$caixa['valor_inicial'] - $total_sangrias;
                    }
                    
                    $v_inf = (float)($informado[$chave] ?? 0);
                    $diff = $v_inf - $v_sist;
                ?>
                <tr>
                    <td><?= $label ?></td>
                    <td>R$ <?= number_format($v_sist, 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($v_inf, 2, ',', '.') ?></td>
                    <td class="<?= $diff < 0 ? 'val-negativo' : ($diff > 0 ? 'val-positivo' : '') ?>">
                        R$ <?= number_format($diff, 2, ',', '.') ?>
                    </td>
                </tr>
                <?php endforeach; ?>

                <tr class="linha-prazo">
                    <td><strong>📝 Vendas Realizadas A Prazo (Convênio)</strong></td>
                    <td>R$ <?= number_format($total_vendas_prazo, 2, ',', '.') ?></td>
                    <td>--</td>
                    <td style="font-size: 0.8rem;">A receber futuramente</td>
                </tr>

                <tr class="linha-receber">
                    <td><strong>📥 Recebimento de Títulos (Hoje)</strong></td>
                    <td><strong>+ R$ <?= number_format((float)array_sum(array_column($receber_banco, 'total')), 2, ',', '.') ?></strong></td>
                    <td>--</td>
                    <td style="font-size: 0.8rem;">Quitação de dívidas</td>
                </tr>

                <tr class="linha-saida">
                    <td><strong>💸 Saídas (Contas Pagas / Estornos)</strong></td>
                    <td><strong>- R$ <?= number_format($total_saidas, 2, ',', '.') ?></strong></td>
                    <td>--</td>
                    <td style="font-size: 0.8rem;">Saídas do caixa</td>
                </tr>

                <tr class="linha-saida" style="background: #fff0f0; color: #b83232;">
                    <td><strong>🚨 Sangrias Efetuadas (Retiradas de segurança)</strong></td>
                    <td><strong>- R$ <?= number_format($total_sangrias, 2, ',', '.') ?></strong></td>
                    <td>--</td>
                    <td style="font-size: 0.8rem; font-weight: bold;">Dinheiro removido da gaveta</td>
                </tr>
            </tbody>
        </table>

        <div style="padding: 15px; background: #fafafa; border-top: 1px solid #eee;">
            <?php if (!$ver_conferidos): ?>
                <form method="POST">
                    <input type="hidden" name="id_caixa" value="<?= $caixa['id'] ?>">
                    <textarea name="observacao_adm" class="input-obs" placeholder="Escreva observações sobre as diferenças se houver..."></textarea>
                    <button type="submit" name="btn_confirmar_conferencia" class="btn-confirmar">✅ VALIDAR E ARQUIVAR</button>
                </form>
            <?php else: ?>
                <div style="display:flex; justify-content: space-between;">
                    <span class="badge-status bg-conferido">CONFERIDO</span>
                    <span style="color:#666;">Obs Admin: <?= htmlspecialchars($caixa['notas_admin'] ?: 'Nenhuma observação.') ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

</body>
</html>