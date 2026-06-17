<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$mensagem = "";

// Função auxiliar para o Postgres
$nulo = function($valor) {
    return ($valor === '' || $valor === null) ? null : $valor;
};

// 1. LÓGICA DE EXCLUSÃO
if (isset($_GET['excluir'])) {
    $id_excluir = (int)$_GET['excluir']; 
    try {
        $stmt_nome = $pdo->prepare("SELECT nome FROM clientes WHERE id = ?");
        $stmt_nome->execute([$id_excluir]);
        $nome_cliente = $stmt_nome->fetchColumn();

        if ($nome_cliente) {
            $pdo->prepare("DELETE FROM clientes WHERE id = ?")->execute([$id_excluir]);
            registrarLog($pdo, 'EXCLUSÃO', 'clientes', "Removeu o cliente: $nome_cliente (ID: $id_excluir)");
            $mensagem = "<div class='msg msg-success' style='padding:10px; background:#d4edda; color:#155724; border-radius:5px; margin-bottom:15px;'>✅ Cliente excluído com sucesso!</div>";
        }
    } catch (PDOException $e) {
        $mensagem = "<div class='msg' style='padding:10px; background:#f8d7da; color:#721c24; border-radius:5px; margin-bottom:15px;'>❌ Erro ao excluir: " . $e->getMessage() . "</div>";
    }
}

// 2. BUSCA CONVÊNIOS PARA O SELECT
try {
    $lista_convenios = $pdo->query("SELECT nome_convenio FROM convenios WHERE status = 'Ativo' ORDER BY nome_convenio ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lista_convenios = [];
}

// 3. PROCESSA O CADASTRO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_cadastrar'])) {
    try {
$sql = "INSERT INTO clientes (nome, cpf_cnpj, telefone, email, cep, endereco, numero, bairro, cidade, possui_convenio, nome_convenio) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            $nulo($_POST['nome']), 
            $nulo($_POST['cpf_cnpj']), 
            $nulo($_POST['telefone']), 
            $nulo($_POST['email']),
            $nulo($_POST['cep']), 
            $nulo($_POST['endereco']), 
            $nulo($_POST['numero']), 
            $nulo($_POST['bairro']), 
            $nulo($_POST['cidade']), 
            $_POST['possui_convenio'], 
            $nulo($_POST['nome_convenio'])
        ]);

        registrarLog($pdo, 'INSERÇÃO', 'clientes', "Cadastrou o cliente: " . $_POST['nome']);
        $mensagem = "<div class='msg msg-success' style='padding:10px; background:#d4edda; color:#155724; border-radius:5px; margin-bottom:15px;'>✅ Cliente cadastrado com sucesso!</div>";
    } catch (PDOException $e) {
        if ($e->getCode() == '23505') {
            $mensagem = "<div class='msg' style='padding:10px; background:#fff3cd; color:#856404; border-radius:5px; margin-bottom:15px;'>⚠️ Erro de ID: Execute o ajuste de SEQUENCE no banco de dados.</div>";
        } else {
            $mensagem = "<div class='msg' style='padding:10px; background:#f8d7da; color:#721c24; border-radius:5px; margin-bottom:15px;'>❌ Erro: " . $e->getMessage() . "</div>";
        }
    }
}

// --- NOVO: LÓGICA DE PESQUISA ---
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
if (!empty($busca)) {
    $sql_list = "SELECT * FROM clientes WHERE nome ILIKE :busca OR cpf_cnpj ILIKE :busca ORDER BY nome LIMIT 100";
    $stmt_list = $pdo->prepare($sql_list);
    $stmt_list->execute(['busca' => "%$busca%"]);
    $clientes = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
} else {
    $clientes = $pdo->query("SELECT * FROM clientes ORDER BY nome LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Clientes - Projeto Breno</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .grid-form { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        .two-columns { grid-column: span 2; }
        .required-label::after { content: " *"; color: red; font-weight: bold; }
        input:required { border-left: 4px solid #007bff; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; background: white; font-size: 14px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f4f4f4; }
        .btn-edit { color: #007bff; text-decoration: none; font-weight: bold; }
        .btn-del { color: #dc3545; text-decoration: none; font-weight: bold; }
        
        /* Estilo da barra de busca */
        .search-container { 
            margin-top: 20px; 
            display: flex; 
            gap: 5px; 
            background: #f9f9f9; 
            padding: 15px; 
            border-radius: 8px; 
            border: 1px solid #eee;
        }
        .search-container input { flex-grow: 1; margin-bottom: 0; }
        .search-container button { width: auto; padding: 0 20px; margin-top: 0; }
        .btn-clear { background: #6c757d; color: white; text-decoration: none; padding: 10px 15px; border-radius: 4px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1100px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2>👥 Gestão de Clientes</h2>
            <a href="dashboard.php" class="btn-voltar" style="text-decoration:none;font-weight:500;">⬅ Voltar ao Painel</a>
        </div>

        <?php if(!empty($mensagem)) echo $mensagem; ?>

        <form method="POST">
            <div class="grid-form">
                <div class="form-group two-columns">
                    <label class="required-label">Nome Completo</label>
                    <input type="text" name="nome" required>
                </div>
                <div class="form-group">
                    <label>E-mail (Opcional)</label>
                    <input type="email" name="email">
                </div>
                <div class="form-group">
                    <label class="required-label">CPF/CNPJ</label>
                    <input type="text" name="cpf_cnpj" required>
                </div>
                <div class="form-group">
                    <label class="required-label">Telefone / WhatsApp</label>
                    <input type="text" name="telefone" required>
                </div>
                <div class="form-group">
                    <label>CEP (Busca Automática)</label>
                    <input type="text" name="cep" id="cep" maxlength="9">
                </div>
                <div class="form-group two-columns">
                    <label class="required-label">Endereço</label>
                    <input type="text" name="endereco" id="endereco" required>
                </div>
                <div class="form-group">
                    <label class="required-label">Número</label>
                    <input type="text" name="numero" required>
                </div>
                <div class="form-group">
                    <label class="required-label">Bairro</label>
                    <input type="text" name="bairro" id="bairro" required>
                </div>
                <div class="form-group">
                    <label class="required-label">Cidade</label>
                    <input type="text" name="cidade" id="cidade" required>
                </div>
                <div class="form-group">
                    <label class="required-label">Usa Convênio?</label>
                    <select name="possui_convenio" required>
                        <option value="Não">Não</option>
                        <option value="Sim">Sim</option>
                    </select>
                </div>
                <div class="form-group two-columns">
                    <label>Selecione o Convênio</label>
                    <select name="nome_convenio">
                        <option value="">-- Selecione se houver --</option>
                        <?php foreach($lista_convenios as $conv): ?>
                            <option value="<?= $conv['nome_convenio'] ?>"><?= $conv['nome_convenio'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" name="btn_cadastrar" style="margin-top: 15px; background: #28a745;">💾 Cadastrar Cliente</button>
        </form>

        <hr style="margin: 30px 0; opacity: 0.2;">

        <div style="display: flex; justify-content: space-between; align-items: flex-end;">
            <h3>Lista de Clientes</h3>
        </div>

        <!-- FORMULÁRIO DE PESQUISA -->
        <form method="GET" class="search-container">
            <input type="text" name="busca" placeholder="Pesquisar por nome ou CPF..." value="<?= htmlspecialchars($busca) ?>">
            <button type="submit" style="background: #007bff;">🔍 Buscar</button>
            <?php if(!empty($busca)): ?>
                <a href="clientes.php" class="btn-clear">Limpar</a>
            <?php endif; ?>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Telefone</th>
                    <th>Bairro/Cidade</th>
                    <th>Convênio</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($clientes) > 0): ?>
                    <?php foreach ($clientes as $c): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($c['nome']) ?></strong><br><small><?= htmlspecialchars($c['cpf_cnpj']) ?></small></td>
                        <td><?= htmlspecialchars($c['telefone']) ?></td>
                        <td><?= htmlspecialchars($c['bairro']) ?> - <?= htmlspecialchars($c['cidade']) ?></td>
                        <td>
                            <?= $c['possui_convenio'] == 'Sim' ? '<span style="color: green;">✔ '.$c['nome_convenio'].'</span>' : '<span style="color: #ccc;">Não</span>' ?>
                        </td>
                        <td>
                            <a href="editar_cliente.php?id=<?= $c['id'] ?>" class="btn-edit">Editar</a> | 
                            <a href="?excluir=<?= $c['id'] ?>" class="btn-del" onclick="return confirm('Excluir permanentemente <?= htmlspecialchars($c['nome']) ?>?')">Excluir</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px; color: #666;">Nenhum cliente encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
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
                            document.getElementsByName('numero')[0].focus();
                        }
                    })
                    .catch(error => console.error('Erro:', error));
            }
        });
    </script>
</body>
</html>