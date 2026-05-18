<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$mensagem = "";
$id = $_GET['id'] ?? null;

if (!$id) { header("Location: clientes.php"); exit; }

// 1. Busca os dados atuais do cliente
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) { die("Cliente não encontrado."); }

// 2. Busca lista de convênios para o select
$lista_convenios = $pdo->query("SELECT nome_convenio FROM convenios WHERE status = 'Ativo'")->fetchAll(PDO::FETCH_ASSOC);

// 3. Processa a Edição
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $sql = "UPDATE clientes SET 
                nome = ?, cpf_cnpj = ?, telefone = ?, email = ?, 
                cep = ?, endereco = ?, numero = ?, bairro = ?, 
                cidade = ?, possui_convenio = ?, nome_convenio = ? 
                WHERE id = ?";
        
        $pdo->prepare($sql)->execute([
            $_POST['nome'], $_POST['cpf_cnpj'], $_POST['telefone'], $_POST['email'],
            $_POST['cep'], $_POST['endereco'], $_POST['numero'], $_POST['bairro'], 
            $_POST['cidade'], $_POST['possui_convenio'], $_POST['nome_convenio'], $id
        ]);

        registrarLog($pdo, 'EDIÇÃO', 'clientes', "Editou dados do cliente ID: $id - " . $_POST['nome']);
        $mensagem = "<div class='msg msg-success'>✅ Alterações salvas com sucesso! <a href='clientes.php'>Voltar à lista</a></div>";
        
        // Atualiza os dados locais para exibir no formulário refeito
        $stmt->execute([$id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $mensagem = "<div class='msg' style='background:#f8d7da; color:#721c24;'>❌ Erro ao atualizar: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Cliente - Projeto Breno</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .grid-form { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        .two-columns { grid-column: span 2; }
        .required-label::after { content: " *"; color: red; }
        input:focus { border-color: #007bff; outline: none; }
    </style>
</head>
<body>
    <div class="container" style="max-width: 900px;">
        <a href="clientes.php" style="text-decoration:none; color:#666;">← Cancelar e Voltar</a>
        <h2>Editar Cadastro de Cliente</h2>

        <?php if(!empty($mensagem)) echo $mensagem; ?>

        <form method="POST">
            <div class="grid-form">
                <div class="form-group two-columns">
                    <label class="required-label">Nome Completo</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($cliente['nome']) ?>" required>
                </div>
                <div class="form-group">
                    <label>E-mail</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>CPF/CNPJ</label>
                    <input type="text" name="cpf_cnpj" value="<?= htmlspecialchars($cliente['cpf_cnpj'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="required-label">Telefone</label>
                    <input type="text" name="telefone" value="<?= htmlspecialchars($cliente['telefone']) ?>" required>
                </div>

                <div class="form-group">
                    <label>CEP (Digite para buscar)</label>
                    <input type="text" name="cep" id="cep" value="<?= htmlspecialchars($cliente['cep'] ?? '') ?>" maxlength="9">
                </div>
                
                <div class="form-group two-columns">
                    <label>Endereço</label>
                    <input type="text" name="endereco" id="endereco" value="<?= htmlspecialchars($cliente['endereco'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Número</label>
                    <input type="text" name="numero" value="<?= htmlspecialchars($cliente['numero'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Bairro</label>
                    <input type="text" name="bairro" id="bairro" value="<?= htmlspecialchars($cliente['bairro'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Cidade</label>
                    <input type="text" name="cidade" id="cidade" value="<?= htmlspecialchars($cliente['cidade'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="required-label">Usa Convênio?</label>
                    <select name="possui_convenio" required>
                        <option value="Não" <?= $cliente['possui_convenio'] == 'Não' ? 'selected' : '' ?>>Não</option>
                        <option value="Sim" <?= $cliente['possui_convenio'] == 'Sim' ? 'selected' : '' ?>>Sim</option>
                    </select>
                </div>
                <div class="form-group two-columns">
                    <label>Convênio Selecionado</label>
                    <select name="nome_convenio">
                        <option value="">Nenhum</option>
                        <?php foreach($lista_convenios as $conv): ?>
                            <option value="<?= $conv['nome_convenio'] ?>" <?= $cliente['nome_convenio'] == $conv['nome_convenio'] ? 'selected' : '' ?>>
                                <?= $conv['nome_convenio'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" style="background: #007bff; margin-top:20px;">Salvar Alterações</button>
        </form>
    </div>

    <script>
        document.getElementById('cep').addEventListener('blur', function() {
            let cep = this.value.replace(/\D/g, '');
            if (cep.length === 8) {
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(response => response.json())
                    .then(data => {
                        if (!data.erro) {
                            document.getElementById('endereco').value = data.logradouro;
                            document.getElementById('bairro').value = data.bairro;
                            document.getElementById('cidade').value = data.localidade;
                        } else {
                            alert("CEP não encontrado.");
                        }
                    })
                    .catch(error => console.error('Erro ao buscar CEP:', error));
            }
        });
    </script>
</body>
</html>