<?php
// CRÍTICO: Páginas internas do painel PRECISAM usar a sessão restrita para não perder os dados do cliente logado!
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// Garante que se a sessão sumir por timeout, manda para a tela de login ao invés de quebrar a página
if (!isset($_SESSION['cliente_id'])) {
    header("Location: auth.php");
    exit;
}

$id_cliente = $_SESSION['cliente_id'];
$mensagem = '';

// Altera dados cadastrais
if (isset($_POST['salvar_perfil'])) {
    $nome = $_POST['nome'];
    $telefone = preg_replace('/\D/', '', $_POST['telefone']); // Mantém apenas números
    $endereco = $_POST['endereco'];
    $bairro = $_POST['bairro'];
    $complemento = !empty($_POST['complemento']) ? $_POST['complemento'] : null; // Trata nulos do banco de forma segura

    $stmt = $pdo->prepare("UPDATE clientes_online SET nome = ?, telefone = ?, endereco = ?, bairro = ?, complemento = ? WHERE id = ?");
    if ($stmt->execute([$nome, $telefone, $endereco, $bairro, $complemento, $id_cliente])) {
        $mensagem = "Cadastro atualizado com sucesso!";
        $_SESSION['cliente_nome'] = $nome; // Atualiza a barra de navegação se mudar o nome em tempo real
    } else {
        $mensagem = "Erro ao atualizar dados. Tente novamente.";
    }
}

// Carrega dados originais atualizados do banco
$stmt = $pdo->prepare("SELECT * FROM clientes_online WHERE id = ?");
$stmt->execute([$id_cliente]);
$perfil = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Meu Perfil - Ajustar Dados</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .profile-card { max-width: 600px; margin: 20px auto; }
        @media (min-width: 768px) {
            .profile-card { margin: 60px auto; } /* Centralização otimizada em telas desktop */
        }
    </style>
</head>
<body>
<div class="container px-3">
    <div class="card profile-card shadow-sm p-4 bg-white">
        <h4 class="mb-4 text-secondary fw-bold">Atualizar Meus Dados</h4>
        
        <?php if(!empty($mensagem)): ?>
            <div class="alert alert-success text-center small fw-semibold"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold text-muted">CPF (Não Alterável)</label>
                <?php 
                    $cpf_formatado = $perfil['cpf'];
                    if(strlen($cpf_formatado) == 11) {
                        $cpf_formatado = substr($cpf_formatado, 0, 3) . '.' . substr($cpf_formatado, 3, 3) . '.' . substr($cpf_formatado, 6, 3) . '-' . substr($cpf_formatado, 9);
                    }
                ?>
                <input type="text" class="form-control form-control-lg bg-light" value="<?= htmlspecialchars($cpf_formatado) ?>" readonly disabled>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-semibold">Nome Completo</label>
                <input type="text" name="nome" class="form-control form-control-lg" value="<?= htmlspecialchars($perfil['nome'] ?? '') ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-semibold">Telefone para Contato</label>
                <input type="text" id="txt_telefone" name="telefone" class="form-control form-control-lg" value="<?= htmlspecialchars($perfil['telefone'] ?? '') ?>" required inputmode="numeric" placeholder="(00) 00000-0000">
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-semibold">Rua e Número</label>
                <input type="text" name="endereco" class="form-control form-control-lg" value="<?= htmlspecialchars($perfil['endereco'] ?? '') ?>" required>
            </div>
            
            <div class="row text-start">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label fw-semibold">Bairro</label>
                    <input type="text" name="bairro" class="form-control form-control-lg" value="<?= htmlspecialchars($perfil['bairro'] ?? '') ?>" required>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label fw-semibold">Complemento</label>
                    <input type="text" name="complemento" class="form-control form-control-lg" value="<?= htmlspecialchars($perfil['complemento'] ?? '') ?>" placeholder="Ex: Ap 12 / Bloco B">
                </div>
            </div>
            
            <div class="d-grid d-sm-flex justify-content-sm-between gap-2 mt-4">
                <a href="painel_cliente_online.php" class="btn btn-lg btn-outline-secondary order-2 order-sm-1">Voltar ao Painel</a>
                <button type="submit" name="salvar_perfil" class="btn btn-lg btn-primary order-1 order-sm-2">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-masker/1.2.0/vanilla-masker.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const txtTelefone = document.getElementById('txt_telefone');
    
    // Função dinâmica que alterna entre máscaras de 8 e 9 dígitos para telefones celulares/fixos brasileiros
    function aplicarMascaraTelefone(el) {
        let numeros = el.value.replace(/\D/g, '');
        if (numeros.length > 10) {
            VMasker(el).maskPattern("(99) 99999-9999");
        } else {
            VMasker(el).maskPattern("(99) 9999-9999");
        }
    }
    
    // Dispara a máscara assim que a página carrega os dados originais
    if(txtTelefone.value) {
        aplicarMascaraTelefone(txtTelefone);
    }
    
    // Adapta dinamicamente enquanto o usuário altera o número
    txtTelefone.addEventListener('input', function() {
        aplicarMascaraTelefone(txtTelefone);
    });
});
</script>
</body>
</html>
