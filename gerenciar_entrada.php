<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// 1. CAPTURA DOS FILTROS
$data_inicial = $_GET['data_inicial'] ?? date('Y-m-01'); // Padrão: Primeiro dia do mês atual
$data_final   = $_GET['data_final']   ?? date('Y-m-d');    // Padrão: Hoje
$fornecedor_id = $_GET['fornecedor_id'] ?? '';

// 2. CONSTRUÇÃO DA QUERY DINÂMICA
$params = [];
$where = "WHERE DATE(c.data_entrada) BETWEEN ? AND ? ";
$params[] = $data_inicial;
$params[] = $data_final;

if (!empty($fornecedor_id)) {
    $where .= " AND c.id_fornecedor = ?";
    $params[] = $fornecedor_id;
}

$sql = "SELECT c.*, f.razao_social, p.descricao as plano_nome 
        FROM compras c
        JOIN fornecedores f ON c.id_fornecedor = f.id
        JOIN plano_contas p ON c.id_plano_conta = p.id
        $where
        ORDER BY c.data_entrada DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entradas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca lista de fornecedores para o Select do filtro
$lista_fornecedores = $pdo->query("SELECT id, razao_social FROM fornecedores ORDER BY razao_social ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Entradas - Projeto Breno</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .filtros-box { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; align-items: end; }
        .btn-voltar { background: #f8f9fa; color: #333; text-decoration: none; padding: 8px 15px; border-radius: 4px; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        th { background: #1f2937; color: white; padding: 15px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .total-bold { font-weight: bold; color: #28a745; }
        .btn-filtrar {
    background: #007bff;
    color: white;
    border: none;
    padding: 12px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    height: 42px; /* igual aos inputs */
    align-self: end; /* garante alinhamento no grid */
}
    </style>
</head>
<body>
    <div class="container" style="max-width: 1200px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>🚚 Gerenciador de Entradas</h2>
            <div>
                <a href="dashboard.php" class="btn-voltar">🏠 Voltar ao Painel</a>
                <a href="entrada_mercadoria.php" style="background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; margin-left: 10px;">+ Nova Entrada</a>
            </div>
        </div>

        <form method="GET" class="filtros-box">
            <div class="form-group">
                <label>Data Inicial</label>
                <input type="date" name="data_inicial" value="<?= $data_inicial ?>">
            </div>
            <div class="form-group">
                <label>Data Final</label>
                <input type="date" name="data_final" value="<?= $data_final ?>">
            </div>
            <div class="form-group">
                <label>Fornecedor</label>
                <select name="fornecedor_id">
                    <option value="">Todos os Fornecedores</option>
                    <?php foreach($lista_fornecedores as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= ($fornecedor_id == $f['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['razao_social']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-filtrar">🔍 Filtrar Resultados</button>
        </form>

        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'entrada_ok'): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid #c3e6cb;">
                ✅ Entrada de mercadoria processada e estoque atualizado com sucesso!
            </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Data Entrada</th>
                    <th>Fornecedor</th>
                    <th>NF nº</th>
                    <th>Plano de Contas</th>
                    <th>Valor Total</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entradas)): ?>
                    <tr><td colspan="7" style="text-align:center; padding: 30px; color: #666;">Nenhuma entrada encontrada para este período.</td></tr>
                <?php endif; ?>

                <?php foreach($entradas as $e): ?>
                <tr>
                    <td>#<?= $e['id'] ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($e['data_entrada'])) ?></td>
                    <td><?= htmlspecialchars($e['razao_social']) ?></td>
                    <td><?= htmlspecialchars($e['numero_nota']) ?></td>
                    <td><small><?= htmlspecialchars($e['plano_nome']) ?></small></td>
                    <td class="total-bold">R$ <?= number_format($e['valor_total'], 2, ',', '.') ?></td>
                    <td>
                        <a href="ver_detalhes_entrada.php?id=<?= $e['id'] ?>" style="color: #007bff; text-decoration: none; font-weight: bold;">🔍 Detalhes</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>