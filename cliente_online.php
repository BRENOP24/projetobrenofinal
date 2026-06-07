<?php
require_once 'config/sessao_visitante.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$mensagem = '';

// --- 1. LOGIN ---
if (isset($_POST['login'])) {
    $login_input = preg_replace('/\D/', '', $_POST['login_input']);
    $senha = $_POST['senha'];

    // Consulta focada estritamente no CPF
    $stmt = $pdo->prepare("SELECT * FROM clientes_online WHERE cpf = :input");
    $stmt->execute(['input' => $login_input]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cliente && !empty($cliente['senha']) && password_verify($senha, $cliente['senha'])) {
        $_SESSION['cliente_id'] = $cliente['id'];
        $_SESSION['cliente_nome'] = $cliente['nome'];
        header("Location: painel_cliente_online.php");
        exit;
    } else {
        $mensagem = "Credenciais inválidas ou senha não cadastrada.";
    }
}

// --- 2. RECUPERAÇÃO DE SENHA (RESET) ---
if (isset($_POST['resetar_senha'])) {
    $identificador = preg_replace('/\D/', '', $_POST['reset_input']);
    $nova_senha = $_POST['nova_senha_reset'];
    $confirmar = $_POST['confirmar_senha_reset'];

    if ($nova_senha !== $confirmar) {
        $mensagem = "As senhas não coincidem.";
    } else {
        // Consulta focada estritamente no CPF
        $stmt = $pdo->prepare("SELECT id, resets_hoje, ultima_atualizacao_reset FROM clientes_online WHERE cpf = :id");
        $stmt->execute(['id' => $identificador]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cliente) {
            $hoje = date('Y-m-d');
            $resets = $cliente['resets_hoje'];
            
            if ($cliente['ultima_atualizacao_reset'] != $hoje) {
                $resets = 0;
            }

            if ($resets >= 15) {
                $mensagem = "Limite máximo de 15 alterações de senha atingido por hoje.";
            } else {
                $senhaHash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $resets++;
                $stmtUpdate = $pdo->prepare("UPDATE clientes_online SET senha = :senha, resets_hoje = :resets, ultima_atualizacao_reset = :hoje WHERE id = :id");
                $stmtUpdate->execute([
                    'senha' => $senhaHash,
                    'resets' => $resets,
                    'hoje' => $hoje,
                    'id' => $cliente['id']
                ]);
                $mensagem = "Senha definida/redefinida com sucesso ($resets/15 hoje)!";
            }
        } else {
            $mensagem = "Usuário não localizado.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Cardápio Online - Área do Cliente</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .auth-card { max-width: 450px; margin: 20px auto; }
        @media (min-width: 768px) {
            .auth-card { margin: 80px auto; } /* Mais espaçamento no topo apenas em telas grandes */
        }
    </style>
</head>
<body>
<div class="container px-3"> <div class="card auth-card shadow-sm p-4">
        <h2 class="text-center mb-4 text-primary fw-bold" style="font-size: 1.75rem;">Área do Cliente</h2>
        
        <?php if(!empty($mensagem)): ?>
            <div class="alert alert-info text-center small"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <ul class="nav nav-tabs nav-fill mb-3" id="authTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#tabLogin">Login</button></li>
            <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#tabEsqueci">Primeiro Acesso / Esqueci Senha</button></li>
        </ul>

        <div class="tab-content mt-4">
            <div class="tab-pane fade show active" id="tabLogin">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">CPF do Cliente</label>
                        <input type="text" id="cpf_login_visual" class="form-control form-control-lg" required placeholder="000.000.000-00" autocomplete="off" inputmode="numeric">
                        <input type="hidden" name="login_input" id="cpf_login_real">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Senha</label>
                        <input type="password" name="senha" class="form-control form-control-lg" required placeholder="Digite sua senha">
                    </div>
                    <button type="submit" name="login" class="btn btn-primary btn-lg w-100 mt-2">Entrar</button>
                </form>
            </div>

            <div class="tab-pane fade" id="tabEsqueci">
                <form method="POST">
                    <div class="mb-3">
                        <p class="text-muted small bg-light p-2 rounded border">Insira seu CPF para criar uma senha ou redefinir a atual caso tenha esquecido.</p>
                        <label class="form-label fw-semibold">CPF do Cliente</label>
                        <input type="text" id="cpf_reset_visual" class="form-control form-control-lg" required placeholder="000.000.000-00" autocomplete="off" inputmode="numeric">
                        <input type="hidden" name="reset_input" id="cpf_reset_real">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nova Senha</label>
                        <input type="password" name="nova_senha_reset" class="form-control form-control-lg" required placeholder="Mínimo 6 caracteres">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirmar Nova Senha</label>
                        <input type="password" name="confirmar_senha_reset" class="form-control form-control-lg" required placeholder="Repita a nova senha">
                    </div>
                    <button type="submit" name="resetar_senha" class="btn btn-danger btn-lg w-100 mt-2">Salvar Nova Senha</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-masker/1.2.0/vanilla-masker.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    function gerenciarCpfMascarado(idVisual, idReal) {
        const inputVisual = document.getElementById(idVisual);
        const inputReal = document.getElementById(idReal);
        
        VMasker(inputVisual).maskPattern("999.999.999-99");
        
        inputVisual.addEventListener('focus', function() {
            if (inputReal.value) {
                inputVisual.value = inputReal.value;
                VMasker(inputVisual).maskPattern("999.999.999-99");
            }
        });
        
        inputVisual.addEventListener('blur', function() {
            let numeros = inputVisual.value.replace(/\D/g, '');
            
            if (numeros.length === 11) {
                inputReal.value = numeros;
                
                let bloco3 = numeros.substring(6, 9);
                let digitos = numeros.substring(9, 11);
                inputVisual.value = `***.***.${bloco3}-${digitos}`;
            } else {
                inputReal.value = "";
            }
        });
    }

    gerenciarCpfMascarado('cpf_login_visual', 'cpf_login_real');
    gerenciarCpfMascarado('cpf_reset_visual', 'cpf_reset_real');
});
</script>
</body>
</html>
