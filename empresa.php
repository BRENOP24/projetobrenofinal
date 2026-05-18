<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

try {
    // Usamos FETCH_ASSOC para garantir que os nomes das colunas venham limpos
    $query = $pdo->query("SELECT * FROM empresas LIMIT 1");
    $empresa = $query->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Caso a tabela 'empresas' ainda não tenha sido criada no Postgres
    $empresa = false;
    $erro_db = "Erro ao acessar os dados: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Empresa - Gestão Breno</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="container" style="max-width:900px;">
    <div class="box-empresa">

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin:0;">🏢 Dados da Empresa</h2>
        <a href="dashboard.php" class="btn-voltar" style="text-decoration:none; font-weight:500; background: #6c757d; color: white; padding: 8px 15px; border-radius: 6px;">
            ⬅ Voltar ao Painel
        </a>
    </div>

        <?php if ($empresa): ?>
            <div style="background:#f9f9f9; padding:20px; border-radius:8px; border:1px solid #ddd;">

                <p><strong>Razão Social:</strong> <?= $empresa['razao_social'] ?></p>
                <p><strong>Nome Fantasia:</strong> <?= $empresa['nome_fantasia'] ?></p>
                <p><strong>CNPJ:</strong> <?= $empresa['cnpj'] ?></p>
                <p><strong>Email:</strong> <?= $empresa['email'] ?></p>
                <p><strong>Telefone:</strong> <?= $empresa['telefone'] ?></p>

                <hr>

                <p><strong>Endereço:</strong> <?= $empresa['endereco'] ?>, <?= $empresa['numero'] ?></p>
                <p><strong>Bairro:</strong> <?= $empresa['bairro'] ?></p>
                <p><strong>Cidade:</strong> <?= $empresa['cidade'] ?> / <?= $empresa['estado'] ?></p>
                <p><strong>CEP:</strong> <?= $empresa['cep'] ?></p>

                <a href="empresa_editar.php"
                   style="display:inline-block; margin-top:15px; background:#007bff; color:#fff; padding:10px 15px; border-radius:4px; text-decoration:none; font-weight:bold;">
                    ✏️ Editar Empresa
                </a>

            </div>
        <?php else: ?>
            <p>Nenhuma empresa cadastrada.</p>
        <?php endif; ?>

    </div>
</div>

</body>
</html>