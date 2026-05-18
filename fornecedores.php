<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$mensagem = "";

// Função auxiliar para o Postgres: transforma string vazia em NULL real
$nulo = function($valor) {
    return ($valor === '' || $valor === null) ? null : trim($valor);
};

// Determina qual status listar (Padrão: Ativo)
$filtro_status = $_GET['ver'] ?? 'Ativo';

// 1. LÓGICA DE ALTERAÇÃO DE STATUS (Inativar / Reativar)
if (isset($_GET['mudar_status']) && isset($_GET['id'])) {
    $id = (int)$_GET['id']; // Força ser inteiro para o Postgres
    $novo_status = ($_GET['mudar_status'] == 'Ativo') ? 'Ativo' : 'Inativo';
    $acao_nome = ($novo_status == 'Ativo') ? 'Reativou' : 'Inativou';

    try {
        $stmt = $pdo->prepare("UPDATE fornecedores SET status = ? WHERE id = ?");
        $stmt->execute([$novo_status, $id]);
        
        registrarLog($pdo, 'STATUS', 'fornecedores', "$acao_nome o fornecedor ID: $id");
        header("Location: fornecedores.php?ver=$filtro_status&msg=status_ok");
        exit;
    } catch (PDOException $e) {
        $mensagem = "<div class='msg' style='background:#f8d7da; color:#721c24;'>❌ Erro: " . $e->getMessage() . "</div>";
    }
}

// 2. PROCESSA O CADASTRO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_cadastrar'])) {
    try {
        $sql = "INSERT INTO fornecedores (razao_social, nome_fantasia, cnpj_cpf, inscricao_estadual, email, telefone, vendedor_contato, cep, endereco, numero, bairro, cidade, uf, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Ativo')";
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            $nulo($_POST['razao_social']), 
            $nulo($_POST['nome_fantasia']), 
            $nulo($_POST['cnpj_cpf']), 
            $nulo($_POST['inscricao_estadual']), 
            $nulo($_POST['email']), 
            $nulo($_POST['telefone']), 
            $nulo($_POST['vendedor_contato']), 
            $nulo($_POST['cep']), 
            $nulo($_POST['endereco']), 
            $nulo($_POST['numero']), 
            $nulo($_POST['bairro']), 
            $nulo($_POST['cidade']), 
            $nulo($_POST['uf'])
        ]);

        registrarLog($pdo, 'INSERÇÃO', 'fornecedores', "Cadastrou: " . $_POST['razao_social']);
        header("Location: fornecedores.php?msg=sucesso");
        exit;
    } catch (PDOException $e) {
        // Se der erro de chave duplicada (Sequence)
        if ($e->getCode() == '23505') {
            $mensagem = "<div class='msg' style='background:#fff3cd; color:#856404;'>⚠️ Erro de sincronização de ID no banco.</div>";
        } else {
            $mensagem = "<div class='msg' style='background:#f8d7da; color:#721c24;'>❌ Erro: " . $e->getMessage() . "</div>";
        }
    }
}
// 3. MENSAGENS DE FEEDBACK
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'sucesso') $mensagem = "<div class='msg msg-success'>✅ Fornecedor cadastrado com sucesso!</div>";
    if ($_GET['msg'] == 'status_ok') $mensagem = "<div class='msg msg-success'>✅ Status atualizado com sucesso!</div>";
    if ($_GET['msg'] == 'editado') $mensagem = "<div class='msg msg-success'>✅ Dados atualizados com sucesso!</div>";
}

// 4. LISTA OS FORNECEDORES BASEADO NO FILTRO
$stmt_lista = $pdo->prepare("SELECT * FROM fornecedores WHERE status = ? ORDER BY id DESC");
$stmt_lista->execute([$filtro_status]);
$fornecedores = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Fornecedores</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .grid-form { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .two-columns { grid-column: span 2; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; font-size: 14px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f4f4f4; }
        .btn-edit { color: #007bff; text-decoration: none; font-weight: bold; }
        .btn-del { color: #dc3545; text-decoration: none; font-weight: bold; }
        .btn-reactivate { color: #28a745; text-decoration: none; font-weight: bold; }
        .msg-success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 10px; border: 1px solid #c3e6cb; }
        
        /* Estilo dos Filtros */
        .filtros { margin: 20px 0; display: flex; gap: 10px; }
        .filtro-item { padding: 8px 15px; text-decoration: none; border-radius: 4px; background: #eee; color: #333; }
        .filtro-item.active { background: #007bff; color: white; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1200px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2>🏢 Gestão de Fornecedores</h2>
           <a href="dashboard.php" class="btn-voltar" style="text-decoration:none;font-weight:500;">⬅ Voltar ao Painel</a>
        </div>

        <?php echo $mensagem; ?>

        <form method="POST">
            <div class="grid-form">
                <div class="form-group two-columns"><label>Razão Social *</label><input type="text" name="razao_social" required></div>
                <div class="form-group two-columns"><label>Nome Fantasia</label><input type="text" name="nome_fantasia"></div>
                <div class="form-group"><label>CNPJ / CPF *</label><input type="text" name="cnpj_cpf" required></div>
                <div class="form-group"><label>Inscrição Estadual</label><input type="text" name="inscricao_estadual"></div>
                <div class="form-group"><label>Telefone</label><input type="text" name="telefone"></div>
                <div class="form-group"><label>E-mail</label><input type="email" name="email"></div>
                <div class="form-group two-columns"><label>Vendedor / Contato</label><input type="text" name="vendedor_contato"></div>
                <div class="form-group"><label>CEP</label><input type="text" name="cep" id="cep" maxlength="9"></div>
                <div class="form-group"><label>Endereço</label><input type="text" name="endereco" id="endereco"></div>
                <div class="form-group"><label>Número</label><input type="text" name="numero"></div>
                <div class="form-group"><label>Bairro</label><input type="text" name="bairro" id="bairro"></div>
                <div class="form-group"><label>Cidade</label><input type="text" name="cidade" id="cidade"></div>
                <div class="form-group"><label>UF</label><input type="text" name="uf" id="uf" maxlength="2"></div>
            </div>
            <button type="submit" name="btn_cadastrar" style="margin-top: 15px; background: #28a745; width: 100%; color: white; border: none; padding: 12px; cursor: pointer; border-radius: 4px; font-weight: bold;">💾 Cadastrar Fornecedor</button>
        </form>

        <hr style="margin: 30px 0; opacity: 0.2;">

        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3>Fornecedores <?= ($filtro_status == 'Ativo') ? 'Ativos' : 'Inativos' ?></h3>
            
            <div class="filtros">
                <a href="?ver=Ativo" class="filtro-item <?= ($filtro_status == 'Ativo') ? 'active' : '' ?>">Ativos</a>
                <a href="?ver=Inativo" class="filtro-item <?= ($filtro_status == 'Inativo') ? 'active' : '' ?>">Inativos</a>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Razão Social</th>
                    <th>CNPJ</th>
                    <th>Contato</th>
                    <th>Cidade/UF</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($fornecedores)): ?>
                    <tr><td colspan="5" style="text-align:center; color:#999;">Nenhum fornecedor encontrado nesta lista.</td></tr>
                <?php endif; ?>

                <?php foreach ($fornecedores as $f): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($f['razao_social']) ?></strong></td>
                    <td><?= htmlspecialchars($f['cnpj_cpf']) ?></td>
                    <td><?= htmlspecialchars($f['telefone']) ?></td>
                    <td><?= htmlspecialchars($f['cidade']) ?>/<?= htmlspecialchars($f['uf']) ?></td>
                    <td>
                        <?php if ($filtro_status == 'Ativo'): ?>
                            <a href="editar_fornecedor.php?id=<?= $f['id'] ?>" class="btn-edit">Editar</a> | 
                            <a href="?mudar_status=Inativo&id=<?= $f['id'] ?>&ver=Ativo" class="btn-del" onclick="return confirm('Inativar este fornecedor?')">Inativar</a>
                        <?php else: ?>
                            <a href="?mudar_status=Ativo&id=<?= $f['id'] ?>&ver=Inativo" class="btn-reactivate">Reativar</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        // Lógica do ViaCEP omitida aqui por brevidade, mas deve ser mantida.
    </script>
</body>
</html>