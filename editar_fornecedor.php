<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$id = $_GET['id'] ?? null;
$mensagem = "";

if (!$id) {
    header("Location: fornecedores.php");
    exit;
}

// 1. BUSCA OS DADOS ATUAIS DO FORNECEDOR
$stmt = $pdo->prepare("SELECT * FROM fornecedores WHERE id = ? AND status = 'Ativo'");
$stmt->execute([$id]);
$f = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$f) {
    header("Location: fornecedores.php");
    exit;
}

// 2. PROCESSA A ATUALIZAÇÃO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_atualizar'])) {
    try {
        $sql = "UPDATE fornecedores SET 
                razao_social = ?, nome_fantasia = ?, inscricao_estadual = ?, 
                email = ?, telefone = ?, vendedor_contato = ?, cep = ?, 
                endereco = ?, numero = ?, bairro = ?, cidade = ?, uf = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['razao_social'], 
            $_POST['nome_fantasia'], 
            $_POST['inscricao_estadual'],
            $_POST['email'], 
            $_POST['telefone'], 
            $_POST['vendedor_contato'],
            $_POST['cep'], 
            $_POST['endereco'], 
            $_POST['numero'], 
            $_POST['bairro'], 
            $_POST['cidade'],
            $_POST['uf'],
            $id
        ]);

        registrarLog($pdo, 'EDIÇÃO', 'fornecedores', "Editou o fornecedor: " . $_POST['razao_social'] . " (ID: $id)");
        
        header("Location: fornecedores.php?msg=editado");
        exit;
    } catch (PDOException $e) {
        $mensagem = "<div class='msg' style='background:#f8d7da; color:#721c24;'>❌ Erro ao atualizar: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Fornecedor - Projeto Breno</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .grid-form { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .two-columns { grid-column: span 2; }
        input:read-only { background-color: #e9ecef; cursor: not-allowed; border-left: 4px solid #6c757d; }
        input:required { border-left: 4px solid #28a745; }
        .btn-cancel { background: #6c757d; color: white; text-decoration: none; padding: 12px; border-radius: 4px; display: inline-block; text-align: center; }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1200px;">
        <h2>📝 Editar Fornecedor: <?= htmlspecialchars($f['razao_social']) ?></h2>
        
        <?php echo $mensagem; ?>

        <form method="POST">
            <div class="grid-form">
                <div class="form-group two-columns">
                    <label>Razão Social</label>
                    <input type="text" name="razao_social" value="<?= htmlspecialchars($f['razao_social']) ?>" required>
                </div>
                <div class="form-group two-columns">
                    <label>Nome Fantasia</label>
                    <input type="text" name="nome_fantasia" value="<?= htmlspecialchars($f['nome_fantasia']) ?>">
                </div>
                <div class="form-group">
                    <label>CNPJ / CPF (Não editável)</label>
                    <input type="text" name="cnpj_cpf" value="<?= htmlspecialchars($f['cnpj_cpf']) ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Inscrição Estadual</label>
                    <input type="text" name="inscricao_estadual" value="<?= htmlspecialchars($f['inscricao_estadual']) ?>">
                </div>
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="telefone" value="<?= htmlspecialchars($f['telefone']) ?>">
                </div>
                <div class="form-group">
                    <label>E-mail</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($f['email']) ?>">
                </div>
                <div class="form-group two-columns">
                    <label>Vendedor / Contato</label>
                    <input type="text" name="vendedor_contato" value="<?= htmlspecialchars($f['vendedor_contato']) ?>">
                </div>
                <div class="form-group">
                    <label>CEP</label>
                    <input type="text" name="cep" id="cep" value="<?= htmlspecialchars($f['cep']) ?>" maxlength="9">
                </div>
                <div class="form-group">
                    <label>Endereço</label>
                    <input type="text" name="endereco" id="endereco" value="<?= htmlspecialchars($f['endereco']) ?>">
                </div>
                <div class="form-group">
                    <label>Número</label>
                    <input type="text" name="numero" value="<?= htmlspecialchars($f['numero']) ?>">
                </div>
                <div class="form-group">
                    <label>Bairro</label>
                    <input type="text" name="bairro" id="bairro" value="<?= htmlspecialchars($f['bairro']) ?>">
                </div>
                <div class="form-group">
                    <label>Cidade</label>
                    <input type="text" name="cidade" id="cidade" value="<?= htmlspecialchars($f['cidade']) ?>">
                </div>
                <div class="form-group">
                    <label>UF</label>
                    <input type="text" name="uf" id="uf" value="<?= htmlspecialchars($f['uf']) ?>" maxlength="2">
                </div>
            </div>

            <div style="margin-top: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <a href="fornecedores.php" class="btn-cancel">✖ Cancelar / Voltar</a>
                <button type="submit" name="btn_atualizar" style="background: #007bff; color: white; border: none; padding: 12px; cursor: pointer; border-radius: 4px; font-weight: bold;">💾 Salvar Alterações</button>
            </div>
        </form>
    </div>

    <script>
        // Mantendo a funcionalidade de CEP também na edição
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
                            document.getElementById('uf').value = data.uf;
                        }
                    });
            }
        });
    </script>
</body>
</html>