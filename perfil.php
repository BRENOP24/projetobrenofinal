<?php
// CRÍTICO: Páginas internas do painel PRECISAM usar a sessão restrita para não perder os dados do cliente logado!
require_once 'config/sessao_visitante.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// Garante que se a sessão sumir por timeout, manda para a tela de login ao invés de quebrar a página
if (!isset($_SESSION['cliente_id'])) {
    header("Location: cliente_online.php");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Meu Perfil - Ajustar Dados</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { 
            /* Fundo moderno em degradê suave igual à tela de login */
            background: linear-gradient(135deg, #e0e8f5 0%, #f5f7fa 100%); 
            min-height: 100vh;
        }
        .profile-card { 
            max-width: 650px; 
            width: 100%;
            border: none !important;
            border-radius: 1.25rem !important; /* Bordas suaves combinando */
            background-color: #ffffff;
        }
        /* Efeito de foco profissional nos inputs */
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }
        /* Estilização para o campo apenas de leitura (CPF) */
        .form-control[readonly] {
            background-color: #f1f3f5 !important;
            color: #6c757d;
            border-color: #dee2e6;
        }
    </style>
</head>
<body>

<div class="container min-vh-100 d-flex align-items-center justify-content-center py-4 px-3">
    <div class="card profile-card shadow-lg p-4 p-sm-5">
        
        <h4 class="mb-4 text-secondary fw-bold" style="letter-spacing: -0.5px;">Atualizar Meus Dados</h4>
        
        <?php if(!empty($mensagem)): ?>
            <div class="alert alert-success text-center border-0 small fw-semibold mb-4 rounded-3" style="background-color: #d1e7dd; color: #0f5132;">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold text-secondary small">CPF (Não Alterável)</label>
                <?php 
                    $cpf_formatado = $perfil['cpf'];
                    if(strlen($cpf_formatado) == 11) {
                        $cpf_formatado = substr($cpf_formatado, 0, 3) . '.' . substr($cpf_formatado, 3, 3) . '.' . substr($cpf_formatado, 6, 3) . '-' . substr($cpf_formatado, 9);
                    }
                ?>
                <input type="text" class="form-control form-control-lg rounded-3" style="font-size: 1rem;" value="<?= htmlspecialchars($cpf_formatado) ?>" readonly disabled>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-semibold text-secondary small">Nome Completo</label>
                <input type="text" name="nome" class="form-control form-control-lg rounded-3" style="font-size: 1rem;" value="<?= htmlspecialchars($perfil['nome'] ?? '') ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-semibold text-secondary small">Telefone para Contato</label>
                <input type="text" id="txt_telefone" name="telefone" class="form-control form-control-lg rounded-3" style="font-size: 1rem;" value="<?= htmlspecialchars($perfil['telefone'] ?? '') ?>" required inputmode="numeric" placeholder="(00) 00000-0000">
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-semibold text-secondary small">Rua e Número</label>
                <input type="text" name="endereco" class="form-control form-control-lg rounded-3" style="font-size: 1rem;" value="<?= htmlspecialchars($perfil['endereco'] ?? '') ?>" required>
            </div>
            
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label fw-semibold text-secondary small">Bairro</label>
                    <input type="text" name="bairro" class="form-control form-control-lg rounded-3" style="font-size: 1rem;" value="<?= htmlspecialchars($perfil['bairro'] ?? '') ?>" required>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label fw-semibold text-secondary small">Complemento</label>
                    <input type="text" name="complemento" class="form-control form-control-lg rounded-3" style="font-size: 1rem;" value="<?= htmlspecialchars($perfil['complemento'] ?? '') ?>" placeholder="Ex: Ap 12 / Bloco B">
                </div>
            </div>
            
            <div class="d-grid d-sm-flex justify-content-sm-between gap-2 mt-4 pt-2">
                <a href="painel_cliente_online.php" class="btn btn-lg btn-outline-secondary rounded-3 fw-semibold order-2 order-sm-1 py-2 px-4" style="font-size: 0.95rem;">
                    Voltar ao Painel
                </a>
                <button type="submit" name="salvar_perfil" class="btn btn-lg btn-primary rounded-3 fw-semibold shadow-sm order-1 order-sm-2 py-2 px-4" style="font-size: 0.95rem;">
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-masker/1.2.0/vanilla-masker.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const txtTelefone = document.getElementById('txt_telefone');
    
    function aplicarMascaraTelefone(el) {
        let numeros = el.value.replace(/\D/g, '');
        if (numeros.length > 10) {
            VMasker(el).maskPattern("(99) 99999-9999");
        } else {
            VMasker(el).maskPattern("(99) 9999-9999");
        }
    }
    
    if(txtTelefone.value) {
        aplicarMascaraTelefone(txtTelefone);
    }
    
    txtTelefone.addEventListener('input', function() {
        aplicarMascaraTelefone(txtTelefone);
    });
});
</script>
</body>
</html>
