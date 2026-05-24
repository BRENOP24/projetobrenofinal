<?php
require_once 'config/conexao.php';
require_once 'config/sessao.php';
require_once 'config/funcoes.php';

// INATIVAR (GET)
if (isset($_GET['inativar'])) {
    $id_inativar = (int)$_GET['inativar'];
    $stmt = $pdo->prepare("UPDATE motoboys SET status = 'Inativo' WHERE id = ?");
    $stmt->execute([$id_inativar]);
    header("Location: " . $_SERVER['PHP_SELF']); // Limpa a URL após a ação
    exit;
}

// CARREGAR DADOS PARA EDIÇÃO (GET)
$motoboy_editando = null;
if (isset($_GET['editar'])) {
    $id_editar = (int)$_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM motoboys WHERE id = ?");
    $stmt->execute([$id_editar]);
    $motoboy_editando = $stmt->fetch(PDO::FETCH_ASSOC);
}

// PROCESSAR FORMULÁRIO (INSERT OU UPDATE)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $nome = $_POST['nome'];
    $cpf = $_POST['cpf'];
    $endereco = $_POST['endereco'];
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];

    if ($id) {
        // UPDATE
        $stmt = $pdo->prepare("
            UPDATE motoboys 
            SET nome = ?, cpf = ?, endereco = ?, latitude = ?, longitude = ? 
            WHERE id = ?
        ");
        $stmt->execute([$nome, $cpf, $endereco, $latitude, $longitude, $id]);
    } else {
        // INSERT (Garante status 'Ativo' por padrão ao cadastrar)
        $stmt = $pdo->prepare("
            INSERT INTO motoboys (nome, cpf, endereco, latitude, longitude, status)
            VALUES (?, ?, ?, ?, ?, 'Ativo')
        ");
        $stmt->execute([$nome, $cpf, $endereco, $latitude, $longitude]);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// SELECT (Busca todos os registros, destacando os ativos e inativos)
$stmt = $pdo->query("SELECT * FROM motoboys ORDER BY id DESC");
$motoboys = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Motoboys</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            padding: 20px;
        }

        .container {
            max-width: 1000px; /* Um pouco mais largo para acomodar a coluna de ações */
            margin: auto;
        }

        .card {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        h2 {
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            background: #007bff;
            color: #fff;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn:hover {
            background: #0056b3;
        }

        .btn-cancelar {
            display: block;
            text-align: center;
            margin-top: 10px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #f1f1f1;
            text-align: left;
        }

        tr:hover {
            background: #f9f9f9;
        }

        .vazio {
            text-align: center;
            padding: 20px;
            color: #777;
        }

        /* Estilos das Ações */
        .btn-acao {
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
            margin-right: 5px;
        }

        .btn-editar {
            background: #ffc107;
            color: #212529;
        }

        .btn-inativar {
            background: #dc3545;
            color: #fff;
        }

        .status-inativo {
            color: #aaa;
            text-decoration: line-through;
        }

        .badge-inativo {
            background: #e2e3e5;
            color: #383d41;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
        }
    </style>
</head>
<body>

<div class="container">

    <div class="card">
        <h2><?= $motoboy_editando ? 'Editar Motoboy' : 'Cadastro de Motoboy' ?></h2>

        <form method="POST">
            <input type="hidden" name="id" value="<?= $motoboy_editando['id'] ?? '' ?>">
            
            <div class="form-group">
                <label>Nome</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($motoboy_editando['nome'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>CPF</label>
                <input type="text" name="cpf" maxlength="14" placeholder="000.000.000-00" value="<?= htmlspecialchars($motoboy_editando['cpf'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Endereço</label>
                <input type="text" name="endereco" value="<?= htmlspecialchars($motoboy_editando['endereco'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Latitude</label>
                <input type="text" name="latitude" value="<?= htmlspecialchars($motoboy_editando['latitude'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Longitude</label>
                <input type="text" name="longitude" value="<?= htmlspecialchars($motoboy_editando['longitude'] ?? '') ?>" required>
            </div>

            <button type="submit" class="btn">
                <?= $motoboy_editando ? 'Salvar Alterações' : 'Cadastrar' ?>
            </button>

            <?php if ($motoboy_editando): ?>
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn-cancelar">Cancelar Edição</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h2>Motoboys Cadastrados</h2>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>Endereço</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>

            <?php if (count($motoboys) > 0): ?>
                <?php foreach ($motoboys as $m): ?>
                    <?php $isInativo = ($m['status'] ?? '') === 'Inativo'; ?>
                    <tr class="<?= $isInativo ? 'status-inativo' : '' ?>">
                        <td><?= $m['id'] ?></td>
                        <td>
                            <?= htmlspecialchars($m['nome']) ?>
                            <?= $isInativo ? '<span class="badge-inativo">Inativo</span>' : '' ?>
                        </td>
                        <td><?= $m['cpf'] ?></td>
                        <td><?= htmlspecialchars($m['endereco']) ?></td>
                        <td><?= $m['latitude'] ?></td>
                        <td><?= $m['longitude'] ?></td>
                        <td>
                            <a href="?editar=<?= $m['id'] ?>" class="btn-acao btn-editar">Editar</a>
                            
                            <?php if (!$isInativo): ?>
                                <a href="?inativar=<?= $m['id'] ?>" class="btn-acao btn-inativar" onclick="return confirm('Tem certeza que deseja inativar este motoboy?')">Inativar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="vazio">Nenhum motoboy cadastrado</td>
                </tr>
            <?php endif; ?>

            </tbody>
        </table>
    </div>

</div>

</body>
</html>