<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

// --- LOGICA DE FILTRO ---
// Verifica se o usuário quer ver os inativos. Se não definido, o padrão é 'ativo'
$filtro_status = isset($_GET['ver_inativos']) && $_GET['ver_inativos'] == '1' ? 'inativo' : 'ativo';

// --- LÓGICA DE STATUS (INATIVAR/ATIVAR) ---
if (isset($_GET['alterar_status'])) {
    $id = (int)$_GET['alterar_status'];
    $novo_status = $_GET['status'] == 'ativo' ? 'inativo' : 'ativo';
    $stmt = $pdo->prepare("UPDATE formas_pagamento SET status = ? WHERE id = ?");
    $stmt->execute([$novo_status, $id]);
    
    // Mantém o filtro atual após a alteração
    $url_retorno = ($novo_status == 'ativo') ? "pagamentos.php?ver_inativos=1" : "pagamentos.php";
    header("Location: " . $url_retorno);
    exit;
}

// --- LÓGICA DE SALVAMENTO (Revisada) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_salvar_pgto'])) {
    $descricao = $_POST['descricao'];
    // Se não selecionar plano, vira NULL em vez de string vazia
    $plano_conta_id = !empty($_POST['plano_conta_id']) ? (int)$_POST['plano_conta_id'] : null;
    $id = !empty($_POST['id_editar']) ? (int)$_POST['id_editar'] : null;

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE formas_pagamento SET descricao = ?, plano_conta_id = ? WHERE id = ?");
            $stmt->execute([$descricao, $plano_conta_id, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO formas_pagamento (descricao, plano_conta_id, status) VALUES (?, ?, 'ativo')");
            $stmt->execute([$descricao, $plano_conta_id]);
        }
        header("Location: pagamentos.php?sucesso=1");
        exit;
    } catch (PDOException $e) {
        die("Erro ao salvar: " . $e->getMessage());
    }
}

// --- BUSCA DADOS PARA EDIÇÃO ---
$dados_edit = ['id' => '', 'descricao' => '', 'plano_conta_id' => ''];
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM formas_pagamento WHERE id = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $dados_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- BUSCA PLANOS DE CONTAS ATIVOS PARA O SELECT ---
$lista_planos = $pdo->query("SELECT id, codigo, descricao FROM plano_contas WHERE status = 'ativo' ORDER BY codigo ASC")->fetchAll(PDO::FETCH_ASSOC);
/// --- LISTAGEM COM FILTRO ---
try {
    $sql_lista = "SELECT f.id, f.descricao, f.status, p.descricao as nome_plano, p.codigo 
                  FROM formas_pagamento f
                  LEFT JOIN plano_contas p ON f.plano_conta_id = p.id
                  WHERE f.status = ? 
                  ORDER BY f.id DESC";
    $stmt_lista = $pdo->prepare($sql_lista);
    $stmt_lista->execute([$filtro_status]);
    $pagamentos = $stmt_lista->fetchAll(PDO::FETCH_ASSOC); // Adicionado FETCH_ASSOC
} catch (PDOException $e) {
    $pagamentos = [];
    echo "Erro de Banco: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Formas de Pagamento - Gestão Breno</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .container-fluid { max-width: 1000px; margin: auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        .header-section { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; margin-bottom: 20px; padding-bottom: 10px; }
        .btn-voltar { background: #6c757d; color: #fff; text-decoration: none; padding: 6px 12px; border-radius: 4px; font-size: 0.85rem; }

        .form-inline { display: flex; flex-wrap: wrap; gap: 10px; background: #f8f9fa; padding: 15px; border-radius: 8px; align-items: flex-end; margin-bottom: 25px; border: 1px solid #e9ecef; }
        .form-group { display: flex; flex-direction: column; flex: 1; min-width: 200px; }
        .form-group label { font-size: 0.8rem; font-weight: bold; margin-bottom: 4px; color: #666; }
        .form-control { padding: 8px; border: 1px solid #ced4da; border-radius: 4px; font-size: 0.9rem; }
        
        .btn-submit { background: #28a745; color: white; border: none; padding: 9px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-submit.btn-edit-mode { background: #007bff; }

        /* Estilo da Barra de Filtro */
        .filter-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .btn-toggle-filter { font-size: 0.85rem; text-decoration: none; color: #007bff; padding: 5px 10px; border: 1px solid #007bff; border-radius: 4px; transition: 0.3s; }
        .btn-toggle-filter:hover { background: #007bff; color: #fff; }

        .table-resumo { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .table-resumo th { background: #f8f9fa; padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6; }
        .table-resumo td { padding: 10px; border-bottom: 1px solid #eee; }
        .tr-inativo { opacity: 0.7; background: #fff5f5; }

        .btn-acao { text-decoration: none; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; border: 1px solid; display: inline-block; }
        .btn-edit { border-color: #007bff; color: #007bff; margin-right: 5px; }
        .btn-status-off { border-color: #dc3545; color: #dc3545; }
        .btn-status-on { border-color: #28a745; color: #28a745; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="header-section">
        <h2>💳 Formas de Pagamento</h2>
        <a href="dashboard.php" class="btn-voltar" style="text-decoration:none;font-weight:500;">⬅ Voltar ao Painel</a>
    </div>

    <form method="POST" class="form-inline">
        <input type="hidden" name="id_editar" value="<?= $dados_edit['id'] ?>">
        
        <div class="form-group">
            <label>Descrição do Pagamento</label>
            <input type="text" name="descricao" class="form-control" value="<?= htmlspecialchars($dados_edit['descricao']) ?>" placeholder="Ex: PIX, Cartão..." required>
        </div>

        <div class="form-group">
            <label>Plano de Contas Vinculado</label>
            <select name="plano_conta_id" class="form-control" required>
                <option value="">-- Selecione --</option>
                <?php foreach ($lista_planos as $plano): ?>
                    <option value="<?= $plano['id'] ?>" <?= ($dados_edit['plano_conta_id'] == $plano['id']) ? 'selected' : '' ?>>
                        <?= $plano['codigo'] ?> - <?= $plano['descricao'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" name="btn_salvar_pgto" class="btn-submit <?= !empty($dados_edit['id']) ? 'btn-edit-mode' : '' ?>">
            <?= !empty($dados_edit['id']) ? 'Atualizar' : 'Salvar Forma' ?>
        </button>
        
        <?php if(!empty($dados_edit['id'])): ?>
            <a href="pagamentos.php" style="font-size: 0.8rem; color: #999; margin-left: 5px;">Cancelar</a>
        <?php endif; ?>
    </form>

    <div class="filter-bar">
        <h3 style="font-size: 1.1rem; margin: 0; color: #444;">
            <?= $filtro_status == 'ativo' ? '🟢 Formas Ativas' : '🔴 Formas Inativas' ?>
        </h3>
        
        <?php if ($filtro_status == 'ativo'): ?>
            <a href="?ver_inativos=1" class="btn-toggle-filter">🔍 Pesquisar Inativos</a>
        <?php else: ?>
            <a href="pagamentos.php" class="btn-toggle-filter">⬅ Voltar para Ativos</a>
        <?php endif; ?>
    </div>

    <table class="table-resumo">
        <thead>
            <tr>
                <th>Descrição</th>
                <th>Plano de Contas</th>
                <th>Status</th>
                <th style="text-align: right;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($pagamentos) > 0): ?>
                <?php foreach ($pagamentos as $pgto): ?>
                <tr class="<?= $pgto['status'] == 'inativo' ? 'tr-inativo' : '' ?>">
                    <td><strong><?= htmlspecialchars($pgto['descricao']) ?></strong></td>
                    <td><small><?= htmlspecialchars($pgto['codigo']) ?> - <?= htmlspecialchars($pgto['nome_plano']) ?></small></td>
                    <td>
                        <span style="color: <?= $pgto['status'] == 'ativo' ? '#28a745' : '#dc3545' ?>; font-size: 0.8rem;">
                            ● <?= ucfirst($pgto['status']) ?>
                        </span>
                    </td>
                    <td style="text-align: right;">
                        <a href="?editar=<?= $pgto['id'] ?>" class="btn-acao btn-edit">Editar</a>
                        
                        <a href="?alterar_status=<?= $pgto['id'] ?>&status=<?= $pgto['status'] ?>" 
                           class="btn-acao <?= $pgto['status'] == 'ativo' ? 'btn-status-off' : 'btn-status-on' ?>"
                           onclick="return confirm('Deseja alterar o status?')">
                            <?= $pgto['status'] == 'ativo' ? 'Inativar' : 'Ativar' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: #999; padding: 20px;">
                        Nenhuma forma de pagamento <?= $filtro_status ?> encontrada.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>