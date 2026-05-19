<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$mensagem = "";

// FILTRO OPCIONAL POR NOME OU CPF
$filtro = "";
$param = [];

if (isset($_GET['buscar']) && !empty($_GET['buscar'])) {
    // No PostgreSQL, usamos ILIKE para busca insensível a maiúsculas/minúsculas
    $filtro = "WHERE nome ILIKE ? OR cpf LIKE ?";
    $busca = "%" . $_GET['buscar'] . "%";
    $param = [$busca, $busca];
}

// LISTA CLIENTES ONLINE
try {
    $sql = "SELECT * FROM clientes_online 
            $filtro 
            ORDER BY data_cadastro DESC 
            LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($param);
    $clientes_online = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $clientes_online = [];
    $mensagem = "<div class='alert alert-danger'>Erro ao listar clientes: " . $e->getMessage() . "</div>";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Clientes Online - Projeto Breno</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; font-size: 14px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f4f4f4; }
        .badge-online { 
            background: #28a745; 
            color: white; 
            padding: 4px 8px; 
            border-radius: 6px; 
            font-size: 12px; 
        }
        .search-box {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1100px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2>🌐 Clientes Online</h2>
            <a href="dashboard.php" class="btn-voltar" style="text-decoration:none;font-weight:500;">⬅ Voltar ao Painel</a>
        </div>

        <form method="GET" class="search-box">
            <input type="text" name="buscar" placeholder="Buscar por nome ou CPF" 
                   value="<?= isset($_GET['buscar']) ? htmlspecialchars($_GET['buscar']) : '' ?>">
            <button type="submit" style="background:#007bff;">🔍 Buscar</button>
            <a href="clientes_online.php" style="text-decoration:none;">
                <button type="button" style="background:#6c757d;">Limpar</button>
            </a>
        </form>

        <hr style="margin: 20px 0; opacity: 0.2;">

        <?= $mensagem ?>

        <h3>Lista de Clientes Online</h3>
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>Telefone</th>
                    <th>Endereço</th>
                    <th>Data Cadastro</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($clientes_online) > 0): ?>
                    <?php foreach ($clientes_online as $c): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($c['nome'] ?? '') ?></strong></td>
                        <td><?= htmlspecialchars($c['cpf'] ?? '') ?></td>
                        <td><?= htmlspecialchars($c['telefone'] ?? '') ?></td>
                        <td>
                            <?php if (empty($c['endereco']) && empty($c['bairro'])): ?>
                                <span style="color: #d9381e; font-weight: bold;">📍 Retirada no Balcão</span>
                            <?php else: ?>
                                <?= htmlspecialchars($c['endereco'] ?? '') ?>, 
                                <?= htmlspecialchars($c['numero'] ?? '') ?> - 
                                <?= htmlspecialchars($c['bairro'] ?? '') ?>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($c['data_cadastro'])) ?></td>
                        <td><span class="badge-online">Online</span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; color:#999;">
                            Nenhum cliente online encontrado.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>