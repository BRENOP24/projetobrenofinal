<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$mensagem = "";

// Perfis permitidos (SEGURANÇA BACKEND)
$niveis_permitidos = ['admin', 'user', 'garcom'];

// --- 1. LÓGICA DE CADASTRO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_salvar_usuario'])) {
    
    $nome    = trim($_POST['nome']);
    $login   = trim($_POST['usuario']);
    $nivel   = $_POST['nivel'];
    $senha   = $_POST['senha'];

    // 🔒 VALIDAÇÕES
    if (empty($nome) || empty($login) || empty($senha)) {
        $mensagem = "<div style='color:red; padding:10px; background:#f8d7da;'>Preencha todos os campos.</div>";
    } elseif (!in_array($nivel, $niveis_permitidos)) {
        $mensagem = "<div style='color:red; padding:10px; background:#f8d7da;'>Nível inválido.</div>";
    } else {

        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (nome, usuario, senha, nivel) 
                VALUES (:nome, :usuario, :senha, :nivel)
            ");

            $stmt->execute([
                ':nome' => $nome,
                ':usuario' => $login,
                ':senha' => $senhaHash,
                ':nivel' => $nivel
            ]);

            $mensagem = "<div style='color:green; padding:10px; background:#d4edda; border-radius:5px; margin-bottom:15px;'>Usuário cadastrado com sucesso!</div>";

        } catch (PDOException $e) {

            // PostgreSQL código 23505 = duplicidade
            if ($e->getCode() == '23505') {
                $mensagem = "<div style='color:red; padding:10px; background:#f8d7da;'>Este login já está em uso.</div>";
            } else {
                $mensagem = "<div style='color:red; padding:10px; background:#f8d7da;'>Erro ao cadastrar usuário.</div>";
            }
        }
    }
}

// --- 2. LÓGICA DE BUSCA ---
try {
    $stmt_lista = $pdo->query("
        SELECT id, nome, usuario, nivel, criado_em 
        FROM usuarios 
        ORDER BY nome ASC
    ");
    $usuarios = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $usuarios = [];
    $mensagem = "<div style='color:red;'>Erro ao carregar lista.</div>";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Usuários</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .tabela-usuarios { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; }
        .tabela-usuarios th, .tabela-usuarios td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        .tabela-usuarios th { background: #f4f4f4; }

        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: bold; text-transform: uppercase; }
        .badge-admin { background: #d4edda; color: #155724; }
        .badge-user { background: #fff3cd; color: #856404; }
        .badge-garcom { background: #cce5ff; color: #004085; }

        .form-cadastro { background:#f9f9f9; padding:20px; border:1px solid #ddd; border-radius:8px; margin-bottom:30px; }

        .btn-salvar { background: #28a745; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>

<div class="container" style="max-width:1000px; margin: 0 auto; padding: 20px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        
        <h2 style="margin: 0;">👥 Gestão de Usuários</h2>
        
        <a href="dashboard.php" class="btn-voltar" style="text-decoration:none; font-weight:500;">⬅ Voltar ao Painel</a>
        
    </div>

    <?= $mensagem ?>

    <div class="form-cadastro">
        <h3 style="margin-top:0;">➕ Novo Usuário</h3>
        <form method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                
                <div>
                    <label>Nome Completo</label>
                    <input type="text" name="nome" required style="width:100%; padding:8px;">
                </div>

                <div>
                    <label>Login</label>
                    <input type="text" name="usuario" required style="width:100%; padding:8px;">
                </div>

                <div>
                    <label>Senha</label>
                    <input type="password" name="senha" required style="width:100%; padding:8px;">
                </div>

                <div>
                    <label>Nível</label>
                    <select name="nivel" style="width:100%; padding:8px;">
                        <option value="user">Usuário</option>
                        <option value="garcom">Garçom</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>

            </div>

            <button type="submit" name="btn_salvar_usuario" class="btn-salvar">
                Gravar Usuário
            </button>
        </form>
    </div>

    <div style="overflow-x: auto;">
        <?php if (count($usuarios) > 0): ?>
            <table class="tabela-usuarios">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Login</th>
                        <th>Nível</th>
                        <th>Criado em</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>

                    <?php foreach ($usuarios as $user): ?>

                        <?php
                        // 🎯 Define classe do badge corretamente
                        switch ($user['nivel']) {
                            case 'admin':
                                $classe = 'badge-admin';
                                break;
                            case 'garcom':
                                $classe = 'badge-garcom';
                                break;
                            default:
                                $classe = 'badge-user';
                        }
                        ?>

                        <tr>
                            <td><strong><?= htmlspecialchars($user['nome']) ?></strong></td>
                            <td><?= htmlspecialchars($user['usuario']) ?></td>

                            <td>
                                <span class="badge <?= $classe ?>">
                                    <?= htmlspecialchars($user['nivel']) ?>
                                </span>
                            </td>

                            <td><?= date('d/m/Y', strtotime($user['criado_em'])) ?></td>

                            <td>
                                <a href="usuario_acoes.php?editar=<?= $user['id'] ?>">✏️</a>
                                <a href="usuario_acoes.php?excluir=<?= $user['id'] ?>" 
                                   onclick="return confirm('Deseja excluir este usuário?')" 
                                   style="color:red; margin-left:10px;">🗑️</a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>
            </table>

        <?php else: ?>

            <div style="text-align:center; padding:20px; background:#eee; border-radius:8px;">
                Nenhum usuário cadastrado.
            </div>

        <?php endif; ?>
    </div>
</div>

</body>
</html>