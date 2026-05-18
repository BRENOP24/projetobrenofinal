<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

// --- LÓGICA DE STATUS ---
if (isset($_GET['alterar_status'])) {
    $id = (int)$_GET['alterar_status'];
    $novo_status = $_GET['status'] == 'ativo' ? 'inativo' : 'ativo';
    $stmt = $pdo->prepare("UPDATE plano_contas SET status = ? WHERE id = ?");
    $stmt->execute([$novo_status, $id]);
    header("Location: plano_contas.php");
    exit;
}

// --- LÓGICA DE SALVAMENTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_salvar'])) {
    $codigo = $_POST['codigo'];
    $descricao = $_POST['descricao'];
    $tipo = $_POST['tipo'];
    $id = $_POST['id_editar'];

    if (!empty($id)) {
        $stmt = $pdo->prepare("UPDATE plano_contas SET codigo = ?, descricao = ?, tipo = ? WHERE id = ?");
        $stmt->execute([$codigo, $descricao, $tipo, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO plano_contas (codigo, descricao, tipo, status) VALUES (?, ?, ?, 'ativo')");
        $stmt->execute([$codigo, $descricao, $tipo]);
    }
    header("Location: plano_contas.php");
    exit;
}

// --- BUSCA PARA EDIÇÃO ---
$dados_edit = ['id' => '', 'codigo' => '', 'descricao' => '', 'tipo' => 'receita'];
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM plano_contas WHERE id = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $dados_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

$contas = $pdo->query("SELECT * FROM plano_contas ORDER BY codigo ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Plano de Contas</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; color: #333; }
        .container-fluid { max-width: 1000px; margin: 20px auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        /* Header */
        .header-section { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; margin-bottom: 20px; padding-bottom: 10px; }
        .header-section h2 { margin: 0; font-size: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .btn-voltar { background: #6c757d; color: #fff; text-decoration: none; padding: 6px 12px; border-radius: 4px; font-size: 0.85rem; transition: 0.3s; }
        .btn-voltar:hover { background: #5a6268; }

        /* Formulário Compacto */
        .form-inline { display: flex; flex-wrap: wrap; gap: 10px; background: #f8f9fa; padding: 15px; border-radius: 8px; align-items: flex-end; margin-bottom: 25px; border: 1px solid #e9ecef; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 0.8rem; font-weight: bold; margin-bottom: 4px; color: #666; }
        .form-control { padding: 6px 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 0.9rem; }
        .btn-submit { background: #007bff; color: white; border: none; padding: 7px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 0.9rem; }
        .btn-submit:hover { background: #0069d9; }

        /* Tabela Densidade Baixa */
        .table-resumo { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .table-resumo th { background: #f8f9fa; color: #444; border-bottom: 2px solid #dee2e6; padding: 10px; text-align: left; }
        .table-resumo td { padding: 8px 10px; border-bottom: 1px solid #eee; }
        .tr-inativo { background-color: #fdfdfd; opacity: 0.6; }

        /* Badges e Botões de Ação */
        .badge { padding: 3px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .badge-receita { background: #d4edda; color: #155724; }
        .badge-despesa { background: #f8d7da; color: #721c24; }
        .btn-acao { text-decoration: none; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; margin-right: 5px; display: inline-block; }
        .btn-edit { background: #e7f1ff; color: #007bff; border: 1px solid #007bff; }
        .btn-status { background: #fff; border: 1px solid #ccc; color: #666; }
        .btn-status:hover { background: #eee; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="header-section">
        <h2>📊 Plano de Contas</h2>
        <a href="dashboard.php" class="btn-voltar" style="text-decoration:none;font-weight:500;">⬅ Voltar ao Painel</a>
    </div>

    <form method="POST" class="form-inline">
        <input type="hidden" name="id_editar" value="<?= $dados_edit['id'] ?>">
        
        <div class="form-group" style="width: 120px;">
            <label>Código</label>
            <input type="text" name="codigo" class="form-control" placeholder="1.1.01" value="<?= $dados_edit['codigo'] ?>" required>
        </div>

        <div class="form-group" style="flex: 1;">
            <label>Descrição da Conta</label>
            <input type="text" name="descricao" class="form-control" placeholder="Ex: Vendas Online" value="<?= $dados_edit['descricao'] ?>" required>
        </div>

        <div class="form-group">
            <label>Tipo</label>
            <select name="tipo" class="form-control">
                <option value="receita" <?= $dados_edit['tipo'] == 'receita' ? 'selected' : '' ?>>Receita</option>
                <option value="despesa" <?= $dados_edit['tipo'] == 'despesa' ? 'selected' : '' ?>>Despesa</option>
            </select>
        </div>

        <button type="submit" name="btn_salvar" class="btn-submit">
            <?= !empty($dados_edit['id']) ? 'Atualizar' : 'Cadastrar' ?>
        </button>
        
        <?php if(!empty($dados_edit['id'])): ?>
            <a href="plano_contas.php" style="font-size: 0.8rem; color: #999;">Cancelar</a>
        <?php endif; ?>
    </form>

    <table class="table-resumo">
        <thead>
            <tr>
                <th width="100">Código</th>
                <th>Descrição</th>
                <th width="100">Tipo</th>
                <th width="80">Status</th>
                <th width="160" style="text-align: right;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($contas as $conta): ?>
            <tr class="<?= $conta['status'] == 'inativo' ? 'tr-inativo' : '' ?>">
                <td><strong><?= htmlspecialchars($conta['codigo']) ?></strong></td>
                <td><?= htmlspecialchars($conta['descricao']) ?></td>
                <td>
                    <span class="badge badge-<?= $conta['tipo'] ?>">
                        <?= $conta['tipo'] ?>
                    </span>
                </td>
                <td>
                    <small style="color: <?= $conta['status'] == 'ativo' ? '#28a745' : '#dc3545' ?>;">
                        ● <?= ucfirst($conta['status']) ?>
                    </small>
                </td>
                <td style="text-align: right;">
                    <a href="?editar=<?= $conta['id'] ?>" class="btn-acao btn-edit">Editar</a>
                    <a href="?alterar_status=<?= $conta['id'] ?>&status=<?= $conta['status'] ?>" 
                       class="btn-acao btn-status" 
                       onclick="return confirm('Alterar status?')">
                       <?= $conta['status'] == 'ativo' ? 'Inativar' : 'Ativar' ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>