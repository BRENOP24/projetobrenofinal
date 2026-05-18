<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// --- LOGICA DE FILTRO ---
$filtro_status = isset($_GET['ver_inativos']) && $_GET['ver_inativos'] == '1' ? 'inativo' : 'ativo';

// --- LÓGICA DE STATUS (INATIVAR/ATIVAR) ---
if (isset($_GET['alterar_status'])) {
    $id = (int)$_GET['alterar_status'];
    // No Postgres/PDO, se usamos SMALLINT, enviamos 1 ou 0
    $novo_status_val = ($_GET['status'] == 'ativo') ? 0 : 1; 
    
    $stmt = $pdo->prepare("UPDATE bandeiras_cartao SET ativo = ? WHERE id = ?");
    $stmt->execute([$novo_status_val, $id]);
    
    // Registra Log (Aproveitando sua função de logs)
    registrarLog($pdo, 'ALTERAR_STATUS', 'bandeiras_cartao', "Alterado status da bandeira ID $id");

    $url_retorno = ($novo_status_val == 1) ? "cadastro_bandeiras.php" : "cadastro_bandeiras.php?ver_inativos=1";
    header("Location: " . $url_retorno);
    exit;
}

// --- LÓGICA DE SALVAMENTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_salvar_bandeira'])) {
    $nome = $_POST['nome'];
    $id = $_POST['id_editar'];

    if (!empty($id)) {
        $stmt = $pdo->prepare("UPDATE bandeiras_cartao SET nome = ? WHERE id = ?");
        $stmt->execute([$nome, (int)$id]);
        registrarLog($pdo, 'UPDATE', 'bandeiras_cartao', "Editou bandeira: $nome");
    } else {
        // No Postgres, usamos 1 para ativo (conforme nosso script SQL)
        $stmt = $pdo->prepare("INSERT INTO bandeiras_cartao (nome, ativo) VALUES (?, 1)");
        $stmt->execute([$nome]);
        registrarLog($pdo, 'INSERT', 'bandeiras_cartao', "Cadastrou nova bandeira: $nome");
    }
    header("Location: cadastro_bandeiras.php?sucesso=1");
    exit;
}

// --- BUSCA DADOS PARA EDIÇÃO ---
$dados_edit = ['id' => '', 'nome' => ''];
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM bandeiras_cartao WHERE id = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $dados_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- LISTAGEM COM FILTRO ---
// Convertendo o filtro para o valor numérico que está no banco (1 ou 0)
$status_busca = ($filtro_status == 'ativo') ? 1 : 0;
$sql_lista = "SELECT id, nome, ativo FROM bandeiras_cartao WHERE ativo = ? ORDER BY id ASC";
$stmt_lista = $pdo->prepare($sql_lista);
$stmt_lista->execute([$status_busca]);
$bandeiras = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Bandeiras de Cartão - Gestão Breno</title>
    <link rel="stylesheet" href="css/bandeiras.css">
</head>
<body>

<div class="container-fluid">
    <div class="header-section">
        <h2>🏷️ Cadastro de Bandeiras</h2>
        <a href="dashboard.php" class="btn-voltar" style="text-decoration:none;font-weight:500;">⬅ Voltar ao Painel</a>
    </div>

    <form method="POST" class="form-inline">
        <input type="hidden" name="id_editar" value="<?= $dados_edit['id'] ?>">
        
        <div class="form-group">
            <label>Nome da Bandeira</label>
            <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($dados_edit['nome']) ?>" placeholder="Ex: Visa, MasterCard, Elo..." required>
        </div>

        <button type="submit" name="btn_salvar_bandeira" class="btn-submit <?= !empty($dados_edit['id']) ? 'btn-edit-mode' : '' ?>">
            <?= !empty($dados_edit['id']) ? 'Atualizar' : 'Salvar Bandeira' ?>
        </button>
        
        <?php if(!empty($dados_edit['id'])): ?>
            <a href="cadastro_bandeiras.php" style="font-size: 0.8rem; color: #999; margin-left: 5px;">Cancelar</a>
        <?php endif; ?>
    </form>

    <div class="filter-bar">
        <h3 style="font-size: 1.1rem; margin: 0; color: #444;">
            <?= $filtro_status == 'ativo' ? '🟢 Bandeiras Ativas' : '🔴 Bandeiras Inativas' ?>
        </h3>
        
        <?php if ($filtro_status == 'ativo'): ?>
            <a href="?ver_inativos=1" class="btn-toggle-filter">🔍 Ver Inativas</a>
        <?php else: ?>
            <a href="cadastro_bandeiras.php" class="btn-toggle-filter">⬅ Voltar para Ativas</a>
        <?php endif; ?>
    </div>

    <table class="table-resumo">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome da Bandeira</th>
                <th>Status</th>
                <th style="text-align: right;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($bandeiras) > 0): ?>
                <?php foreach ($bandeiras as $b): ?>
                <tr class="<?= !$b['ativo'] ? 'tr-inativo' : '' ?>">
                    <td><?= $b['id'] ?></td>
                    <td><strong><?= htmlspecialchars($b['nome']) ?></strong></td>
                    <td>
                        <span style="color: <?= $b['ativo'] ? '#28a745' : '#dc3545' ?>; font-size: 0.8rem;">
                            ● <?= $b['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td style="text-align: right;">
                        <a href="?editar=<?= $b['id'] ?>" class="btn-acao btn-edit">Editar</a>
                        
                        <a href="?alterar_status=<?= $b['id'] ?>&status=<?= $b['ativo'] ? 'ativo' : 'inativo' ?>" 
                           class="btn-acao <?= $b['ativo'] ? 'btn-status-off' : 'btn-status-on' ?>"
                           onclick="return confirm('Deseja alterar o status?')">
                            <?= $b['ativo'] ? 'Inativar' : 'Ativar' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: #999; padding: 20px;">
                        Nenhuma bandeira encontrada.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>