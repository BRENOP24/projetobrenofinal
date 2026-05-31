<?php
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$mensagem = '';

// --- 1. PRIMEIRO ACESSO (VERIFICAÇÃO) ---
if (isset($_POST['verificar_primeiro_acesso'])) {
    $identificador = preg_replace('/\D/', '', $_POST['identificador']); // Remove pontos/traços do CPF ou Telefone
    
    $stmt = $pdo->prepare("SELECT id, nome, cpf, telefone, senha FROM clientes_online WHERE cpf = :id OR telefone = :id");
    $stmt->execute(['id' => $identificador]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cliente) {
        if (!empty($cliente['senha'])) {
            $mensagem = "Você já possui uma senha cadastrada. Use o formulário de login.";
        } else {
            $_SESSION['primeiro_acesso_id'] = $cliente['id'];
            $_SESSION['primeiro_acesso_nome'] = $cliente['nome'];
        }
    } else {
        $mensagem = "Nenhum cadastro encontrado com este CPF ou Telefone.";
    }
}

// --- 2. CRIAR SENHA (PRIMEIRO ACESSO) ---
if (isset($_POST['criar_senha'])) {
    $senha = $_POST['nova_senha'];
    $confirmar = $_POST['confirmar_senha'];
    $id = $_SESSION['primeiro_acesso_id'] ?? null;

    if ($id && $senha === $confirmar) {
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE clientes_online SET senha = ? WHERE id = ?");
        if ($stmt->execute([$senhaHash, $id])) {
            $mensagem = "Senha criada com sucesso! Faça seu login.";
            unset($_SESSION['primeiro_acesso_id'], $_SESSION['primeiro_acesso_nome']);
        }
    } else {
        $mensagem = "As senhas não coincidem ou a sessão expirou.";
    }
}

// --- 3. LOGIN ---
if (isset($_POST['login'])) {
    $login_input = preg_replace('/\D/', '', $_POST['login_input']);
    $senha = $_POST['senha'];

    $stmt = $pdo->prepare("SELECT * FROM clientes_online WHERE cpf = :input OR telefone = :input");
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

// --- 4. RECUPERAÇÃO DE SENHA (RESET) ---
if (isset($_POST['resetar_senha'])) {
    $identificador = preg_replace('/\D/', '', $_POST['reset_input']);
    $nova_senha = $_POST['nova_senha_reset'];
    $confirmar = $_POST['confirmar_senha_reset'];

    if ($nova_senha !== $confirmar) {
        $mensagem = "As senhas não coincidem.";
    } else {
        $stmt = $pdo->prepare("SELECT id, resets_hoje, ultima_atualizacao_reset FROM clientes_online WHERE cpf = :id OR telefone = :id");
        $stmt->execute(['id' => $identificador]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cliente) {
            $hoje = date('Y-m-d');
            $resets = $cliente['resets_hoje'];
            
            // Se mudou o dia, reseta o contador localmente
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
                $mensagem = "Senha redefinida com sucesso ($resets/15 hoje)!";
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
    <title>Cardápio Online - Área do Cliente</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .auth-card { max-width: 450px; margin: 50px auto; }
    </style>
</head>
<body>
<div class="container">
    <div class="card auth-card shadow-sm p-4">
        <h2 class="text-center mb-4 text-primary">Área do Cliente</h2>
        
        <?php if(!empty($mensagem)): ?>
            <div class="alert alert-info text-center"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <ul class="nav nav-tabs mb-3" id="authTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabLogin">Login</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPrimeiroAcesso">Primeiro Acesso</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabEsqueci">Esqueci Senha</button></li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tabLogin">
                <form method="POST">
                    <div class="mb-3">
                        <label>CPF ou Telefone</label>
                        <input type="text" name="login_input" class="form-control" required placeholder="Apenas números">
                    </div>
                    <div class="mb-3">
                        <label>Senha</label>
                        <input type="password" name="senha" class="form-control" required>
                    </div>
                    <button type="submit" name="login" class="btn btn-primary w-100">Entrar</button>
                </form>
            </div>

            <div class="tab-pane fade" id="tabPrimeiroAcesso">
                <?php if (!isset($_SESSION['primeiro_acesso_id'])): ?>
                    <form method="POST">
                        <p class="text-muted small">Se você já fez pedidos, digite seu CPF ou Telefone para criar uma senha.</p>
                        <div class="mb-3">
                            <label>CPF ou Telefone Cadastrado</label>
                            <input type="text" name="identificador" class="form-control" required>
                        </div>
                        <button type="submit" name="verificar_primeiro_acesso" class="btn btn-warning w-100">Verificar Dados</button>
                    </form>
                <?php else: ?>
                    <form method="POST">
                        <p class="text-success">Olá, <strong><?= htmlspecialchars($_SESSION['primeiro_acesso_nome']) ?></strong>! Defina sua senha abaixo:</p>
                        <div class="mb-3">
                            <label>Nova Senha</label>
                            <input type="password" name="nova_senha" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Confirmar Senha</label>
                            <input type="password" name="confirmar_senha" class="form-control" required>
                        </div>
                        <button type="submit" name="criar_senha" class="btn btn-success w-100">Salvar Senha</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade" id="tabEsqueci">
                <form method="POST">
                    <div class="mb-3">
                        <label>CPF ou Telefone</label>
                        <input type="text" name="reset_input" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Nova Senha</label>
                        <input type="password" name="nova_senha_reset" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Confirmar Nova Senha</label>
                        <input type="password" name="confirmar_senha_reset" class="form-control" required>
                    </div>
                    <button type="submit" name="resetar_senha" class="btn btn-danger w-100">Resetar Senha</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
