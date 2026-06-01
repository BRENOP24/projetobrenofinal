<?php
require_once 'config/sessao_visitante.php'; 
require_once 'config/conexao.php';

if (!isset($_SESSION['cliente_id'])) {
    header("Location: auth.php");
    exit;
}

$id_cliente = $_SESSION['cliente_id'];
$mensagem = '';

// Altera dados cadastrais
if (isset($_POST['salvar_perfil'])) {
    $nome = $_POST['nome'];
    $telefone = preg_replace('/\D/', '', $_POST['telefone']);
    $endereco = $_POST['endereco'];
    $bairro = $_POST['bairro'];
    $complemento = $_POST['complemento'];

    $stmt = $pdo->prepare("UPDATE clientes_online SET nome = ?, telefone = ?, endereco = ?, bairro = ?, complemento = ? WHERE id = ?");
    if ($stmt->execute([$nome, $telefone, $endereco, $bairro, $complemento, $id_cliente])) {
        $mensagem = "Cadastro atualizado com sucesso!";
        $_SESSION['cliente_nome'] = $nome; // Atualiza a nav se mudar o nome
    }
}

// Carrega dados originais
$stmt = $pdo->prepare("SELECT * FROM clientes_online WHERE id = ?");
$stmt->execute([$id_cliente]);
$perfil = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Meu Perfil</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container my-5" style="max-width: 600px;">
    <div class="card shadow-sm p-4 bg-white">
        <h4 class="mb-3 text-secondary">Atualizar Meus Dados</h4>
        
        <?php if(!empty($mensagem)): ?>
            <div class="alert alert-success text-center"><?= $mensagem ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">CPF (Não Alterável)</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($perfil['cpf']) ?>" readonly disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">Nome Completo</label>
                <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($perfil['nome']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Telefone</label>
                <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($perfil['telefone']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Rua e Número</label>
                <input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars($perfil['endereco']) ?>" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Bairro</label>
                    <input type="text" name="bairro" class="form-control" value="<?= htmlspecialchars($perfil['bairro']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Complemento</label>
                    <input type="text" name="complemento" class="form-control" value="<?= htmlspecialchars($perfil['complemento']) ?>">
                </div>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <a href="painel_cliente_online.php" class="btn btn-outline-secondary">Voltar ao Painel</a>
                <button type="submit" name="salvar_perfil" class="btn btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
