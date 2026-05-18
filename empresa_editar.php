<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$mensagem = "";

// Buscar empresa
$empresa = $pdo->query("SELECT * FROM empresas LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        empty($_POST['razao_social']) ||
        empty($_POST['cnpj']) ||
        empty($_POST['email']) ||
        empty($_POST['telefone'])
    ) {
        $mensagem = "<div class='alert alert-error'>
            Campos obrigatórios não preenchidos.
        </div>";
    } else {

        $dados = [
            trim($_POST['razao_social']),
            trim($_POST['nome_fantasia']),
            preg_replace('/\D/', '', $_POST['cnpj']),
            $_POST['email'],
            preg_replace('/\D/', '', $_POST['telefone']),
            $_POST['endereco'],
            $_POST['numero'],
            $_POST['bairro'],
            $_POST['cidade'],
            $_POST['estado'],
            $_POST['cep']
        ];

        $sql = "UPDATE empresas SET
            razao_social=?, nome_fantasia=?, cnpj=?, email=?, telefone=?,
            endereco=?, numero=?, bairro=?, cidade=?, estado=?, cep=?
            WHERE id = ?";

        $dados[] = $empresa['id'];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($dados);

        header("Location: empresa.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Editar Empresa</title>

<style>
body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f8;
}

.container {
    max-width: 960px;
    margin: 40px auto;
}

.card {
    background: #fff;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}

h2 {
    margin-bottom: 25px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-size: 14px;
    margin-bottom: 5px;
    font-weight: 600;
}

.form-group input {
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 14px;
}

.col-12 { grid-column: span 12; }
.col-8  { grid-column: span 8; }
.col-6  { grid-column: span 6; }
.col-4  { grid-column: span 4; }
.col-3  { grid-column: span 3; }
.col-2  { grid-column: span 2; }

hr {
    grid-column: span 12;
    border: none;
    border-top: 1px solid #eee;
    margin: 10px 0;
}

.actions {
    margin-top: 30px;
    display: flex;
    gap: 15px;
}

.btn {
    padding: 12px 20px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-weight: bold;
    font-size: 14px;
    text-decoration: none;
}

.btn-success {
    background: #28a745;
    color: #fff;
}

.btn-cancel {
    background: #e0e0e0;
    color: #333;
}

.alert {
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-weight: 600;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
}
</style>
</head>

<body>

<div class="container">
    <div class="card">

        <h2>✏️ Editar Empresa</h2>

        <?= $mensagem ?>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group col-6">
                    <label>Razão Social *</label>
                    <input type="text" name="razao_social" required value="<?= $empresa['razao_social'] ?>">
                </div>

                <div class="form-group col-6">
                    <label>Nome Fantasia</label>
                    <input type="text" name="nome_fantasia" value="<?= $empresa['nome_fantasia'] ?>">
                </div>

                <div class="form-group col-4">
                    <label>CNPJ *</label>
                    <input type="text" name="cnpj" required value="<?= $empresa['cnpj'] ?>">
                </div>

                <div class="form-group col-4">
                    <label>Email *</label>
                    <input type="email" name="email" required value="<?= $empresa['email'] ?>">
                </div>

                <div class="form-group col-4">
                    <label>Telefone *</label>
                    <input type="text" name="telefone" required value="<?= $empresa['telefone'] ?>">
                </div>

                <hr>

                <div class="form-group col-6">
                    <label>Endereço</label>
                    <input type="text" name="endereco" value="<?= $empresa['endereco'] ?>">
                </div>

                <div class="form-group col-2">
                    <label>Número</label>
                    <input type="text" name="numero" value="<?= $empresa['numero'] ?>">
                </div>

                <div class="form-group col-4">
                    <label>Bairro</label>
                    <input type="text" name="bairro" value="<?= $empresa['bairro'] ?>">
                </div>

                <div class="form-group col-4">
                    <label>Cidade</label>
                    <input type="text" name="cidade" value="<?= $empresa['cidade'] ?>">
                </div>

                <div class="form-group col-2">
                    <label>Estado</label>
                    <input type="text" name="estado" maxlength="2" value="<?= $empresa['estado'] ?>">
                </div>

                <div class="form-group col-3">
                    <label>CEP</label>
                    <input type="text" name="cep" value="<?= $empresa['cep'] ?>">
                </div>

            </div>

            <div class="actions">
                <button type="submit" class="btn btn-success">💾 Salvar Alterações</button>
                <a href="empresa.php" class="btn btn-cancel">Cancelar</a>
            </div>

        </form>

    </div>
</div>

</body>
</html>
