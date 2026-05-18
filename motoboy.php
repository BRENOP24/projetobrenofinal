<?php
require_once 'config/conexao.php';

// INSERT
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $nome = $_POST['nome'];
    $cpf = $_POST['cpf'];
    $endereco = $_POST['endereco'];
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];

    $stmt = $pdo->prepare("
        INSERT INTO motoboys (nome, cpf, endereco, latitude, longitude)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([$nome, $cpf, $endereco, $latitude, $longitude]);
}

// SELECT
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
            max-width: 900px;
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
    </style>
</head>
<body>

<div class="container">

    <!-- FORM -->
    <div class="card">
        <h2>Cadastro de Motoboy</h2>

        <form method="POST">
            
            <div class="form-group">
                <label>Nome</label>
                <input type="text" name="nome" required>
            </div>

            <div class="form-group">
                <label>CPF</label>
                <input type="text" name="cpf" maxlength="14" placeholder="000.000.000-00" required>
            </div>

            <div class="form-group">
                <label>Endereço</label>
                <input type="text" name="endereco" required>
            </div>

            <div class="form-group">
                <label>Latitude</label>
                <input type="text" name="latitude" required>
            </div>

            <div class="form-group">
                <label>Longitude</label>
                <input type="text" name="longitude" required>
            </div>

            <button type="submit" class="btn">Cadastrar</button>
        </form>
    </div>

    <!-- LISTA -->
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
                </tr>
            </thead>
            <tbody>

            <?php if (count($motoboys) > 0): ?>
                <?php foreach ($motoboys as $m): ?>
                    <tr>
                        <td><?= $m['id'] ?></td>
                        <td><?= htmlspecialchars($m['nome']) ?></td>
                        <td><?= $m['cpf'] ?></td>
                        <td><?= htmlspecialchars($m['endereco']) ?></td>
                        <td><?= $m['latitude'] ?></td>
                        <td><?= $m['longitude'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="vazio">Nenhum motoboy cadastrado</td>
                </tr>
            <?php endif; ?>

            </tbody>
        </table>
    </div>

</div>

</body>
</html>