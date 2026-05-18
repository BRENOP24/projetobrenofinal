<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// Forçamos o ID da compra a ser um inteiro puro
$id_compra = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$id_compra) { 
    header("Location: gerenciar_entrada.php"); 
    exit; 
}

// --- LÓGICA DE EXCLUSÃO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_excluir'])) {
    $motivo = $_POST['motivo_exclusao'] ?? 'Não informado';

    try {
        $pdo->beginTransaction();

        // 1. Busca os itens (garantindo que pegamos o ID do produto como inteiro)
        $stmt_itens_del = $pdo->prepare("SELECT id_produto, quantidade FROM compras_itens WHERE id_compra = ?");
        $stmt_itens_del->execute([$id_compra]);
        $itens_para_estorno = $stmt_itens_del->fetchAll(PDO::FETCH_ASSOC);

        // 2. Verifica se a conta já foi paga
        $stmt_check = $pdo->prepare("SELECT status FROM contas_pagar WHERE id_compra = ?");
        $stmt_check->execute([$id_compra]);
        $status_financeiro = $stmt_check->fetchColumn();

        if ($status_financeiro === 'Pago') {
            throw new Exception("Esta nota não pode ser excluída porque o título financeiro já foi PAGO.");
        }

        // 3. Estorna o estoque
        // Se seu estoque no banco for INTEGER, use (int)$it['quantidade']
        // Se for DECIMAL, pode manter apenas $it['quantidade']
        $stmt_update_estoque = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?");
        foreach ($itens_para_estorno as $it) {
            // Convertendo ambos para INT para não ter erro de sintaxe no banco
            $quantidade = (int)$it['quantidade']; 
            $id_prod = (int)$it['id_produto'];

            $stmt_update_estoque->execute([$quantidade, $id_prod]);
        }

        // 4. Deleta os registros (IDs forçados para inteiro para evitar o erro SQLSTATE[22P02])
        $stmt_del_cp = $pdo->prepare("DELETE FROM contas_pagar WHERE id_compra = ?");
        $stmt_del_cp->execute([(int)$id_compra]);

        $stmt_del_itens = $pdo->prepare("DELETE FROM compras_itens WHERE id_compra = ?");
        $stmt_del_itens->execute([(int)$id_compra]);

        $stmt_del_compra = $pdo->prepare("DELETE FROM compras WHERE id = ?");
        $stmt_del_compra->execute([(int)$id_compra]);

        $pdo->commit();
        header("Location: gerenciar_entrada.php?msg=excluido_ok");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $erro_exclusao = $e->getMessage();
    }
}

// --- BUSCA DE DADOS PARA EXIBIÇÃO ---
$stmt = $pdo->prepare("SELECT c.*, f.razao_social, f.nome_fantasia, f.cnpj_cpf, p.descricao as plano_nome 
                       FROM compras c 
                       JOIN fornecedores f ON c.id_fornecedor = f.id 
                       JOIN plano_contas p ON c.id_plano_conta = p.id 
                       WHERE c.id = ?");
$stmt->execute([$id_compra]);
$compra = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$compra) { die("Entrada não encontrada ou já excluída."); }

$stmt_itens = $pdo->prepare("SELECT ci.*, prod.nome 
                             FROM compras_itens ci 
                             JOIN produtos prod ON ci.id_produto = prod.id 
                             WHERE ci.id_compra = ?");
$stmt_itens->execute([$id_compra]);
$itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

$fornecedor_exibicao = !empty($compra['nome_fantasia']) ? $compra['nome_fantasia'] : $compra['razao_social'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Detalhes da Nota - Gestão Breno</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .header-detalhe { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .card-info { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .total-valor { color: #28a745; font-size: 22px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th { background: #f4f4f4; padding: 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .perigo-box { background: #fff5f5; border: 1px solid #feb2b2; padding: 20px; border-radius: 8px; margin-top: 30px; }
        .alert-erro { background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f87171; font-family: monospace; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1100px; margin-top: 20px;">
        
        <div class="header-detalhe">
            <h2 style="margin:0;">🔍 Nota Fiscal #<?= htmlspecialchars($compra['numero_nota']) ?></h2>
            <a href="gerenciar_entrada.php" style="text-decoration: none; color: #666; background: #f8f9fa; padding: 8px 15px; border-radius: 5px; border: 1px solid #ddd;">← Voltar</a>
        </div>

        <?php if (isset($erro_exclusao)): ?>
            <div class="alert-erro">
                <strong>⚠️ Erro de Banco de Dados:</strong><br>
                <?= htmlspecialchars($erro_exclusao) ?>
            </div>
        <?php endif; ?>

        <div class="card-info">
            <div style="display: flex; justify-content: space-between;">
                <div>
                    <p><strong>Fornecedor:</strong> <?= htmlspecialchars($fornecedor_exibicao) ?></p>
                    <p><strong>Plano de Contas:</strong> <?= htmlspecialchars($compra['plano_nome']) ?></p>
                </div>
                <div style="text-align: right;">
                    <p><strong>Entrada em:</strong> <?= date('d/m/Y H:i', strtotime($compra['data_entrada'])) ?></p>
                    <div class="total-valor">R$ <?= number_format($compra['valor_total'], 2, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <h3>Itens da Nota</h3>
        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Qtd</th>
                    <th>Vl. Unitário</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($itens as $it): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($it['nome']) ?></strong></td>
                    <td><?= number_format($it['quantidade'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($it['valor_unitario'], 2, ',', '.') ?></td>
                    <td><strong>R$ <?= number_format($it['subtotal'], 2, ',', '.') ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="perigo-box">
            <h4 style="color: #c53030; margin-top: 0;">⚠️ Zona de Perigo</h4>
            <form method="POST" onsubmit="return confirm('Confirmar a exclusão desta nota e o estorno do estoque?')">
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="motivo_exclusao" placeholder="Descreva o motivo..." required 
                           style="flex-grow: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <button type="submit" name="btn_excluir" 
                            style="background: #e53e3e; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold;">
                        Excluir Nota
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>