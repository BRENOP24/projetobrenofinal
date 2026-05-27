<?php
header('Content-Type: application/json');
require_once 'config/conexao.php';

$json = file_get_contents('php://input');
$dados = json_decode($json, true);

if (!$dados) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos recebidos.']);
    exit;
}

try {
    // Iniciamos a transação no topo para blindar o banco contra qualquer erro nas validações
    $pdo->beginTransaction();

    // ==============================================================
    // TRAVAS DE SEGURANÇA NO BACK-END (VALIDAÇÃO DOS DADOS)
    // ==============================================================
    
    // 1. Tratamento e Validação do CPF
    $cpf_limpo = preg_replace('/[^0-9]/', '', $dados['cliente_cpf'] ?? '');
    if (strlen($cpf_limpo) !== 11) {
        throw new Exception('O CPF deve conter exatamente 11 dígitos numéricos.');
    }

    // 2. Tratamento e Validação do Telefone
    $telefone_limpo = preg_replace('/[^0-9]/', '', $dados['cliente_telefone'] ?? '');
    if (strlen($telefone_limpo) < 10 || strlen($telefone_limpo) > 11) {
        throw new Exception('O telefone informado é inválido. Insira o DDD + Número.');
    }

    // 3. Validação simplificada do Nome para evitar quebras por encoding/charset
    $nome_tratado = trim($dados['cliente_nome'] ?? '');
    if (empty($nome_tratado) || strlen($nome_tratado) < 3) {
        throw new Exception('O nome inserido é inválido. Digite ao menos 3 caracteres.');
    }

    $tipo_entrega = $dados['tipo_entrega'] ?? 'entrega';
    $endereco_pedido = ($tipo_entrega === 'entrega') ? $dados['endereco_completo'] : 'Retirada no Balcão';
    $bairro_pedido = ($tipo_entrega === 'entrega') ? $dados['bairro_entrega'] : null;
    $taxa_entrega = ($tipo_entrega === 'entrega') ? $dados['taxa_entrega'] : 0.00;

    /* ==============================================================
       1. CLIENTE
    ============================================================== */
    $stmtVerificaCli = $pdo->prepare("SELECT id FROM clientes_online WHERE cpf = ? LIMIT 1");
    $stmtVerificaCli->execute([$cpf_limpo]);
    $clienteExistente = $stmtVerificaCli->fetch(PDO::FETCH_ASSOC);

    if ($clienteExistente) {
        $cliente_id = $clienteExistente['id'];
        
        if ($tipo_entrega === 'entrega') {
            $stmtUpdateCli = $pdo->prepare("UPDATE clientes_online SET telefone = ?, endereco = ?, bairro = ? WHERE id = ?");
            $stmtUpdateCli->execute([
                $telefone_limpo,
                $dados['endereco_completo'],
                $dados['bairro_entrega'],
                $cliente_id
            ]);
        } else {
            $stmtUpdateCli = $pdo->prepare("UPDATE clientes_online SET telefone = ? WHERE id = ?");
            $stmtUpdateCli->execute([$telefone_limpo, $cliente_id]);
        }
    } else {
        $endereco_cli = ($tipo_entrega === 'entrega') ? $dados['endereco_completo'] : null;
        $bairro_cli = ($tipo_entrega === 'entrega') ? $dados['bairro_entrega'] : null;

        $stmtInsertCli = $pdo->prepare("
            INSERT INTO clientes_online (nome, cpf, telefone, endereco, bairro)
            VALUES (?, ?, ?, ?, ?) RETURNING id
        ");

        $stmtInsertCli->execute([
            $nome_tratado,
            $cpf_limpo,
            $telefone_limpo,
            $endereco_cli,
            $bairro_cli
        ]);

        $cliente_id = $stmtInsertCli->fetchColumn(); 
    }

    /* ==============================================================
       2. VALIDAÇÃO DA FORMA DE PAGAMENTO
    ============================================================== */
    if (!isset($dados['forma_pagamento']) || empty($dados['forma_pagamento'])) {
        throw new Exception('Forma de pagamento não informada.');
    }
    $forma_pagamento_id = filter_var($dados['forma_pagamento'], FILTER_VALIDATE_INT);

    /* ==============================================================
       3. PEDIDO
    ============================================================== */
    $sqlPedido = "
        INSERT INTO pedidos_online (
            cliente_id, valor_total, taxa_entrega, tipo_entrega, 
            bairro_entrega, endereco_completo, forma_pagamento_id, 
            precisa_troco, status, origem
        ) VALUES (
            :cliente_id, :valor_total, :taxa_entrega, :tipo_entrega, 
            :bairro_entrega, :endereco_completo, :forma_pagamento_id, 
            :precisa_troco, 'Pendente', 'Site'
        ) RETURNING id
    ";

    $stmtPedido = $pdo->prepare($sqlPedido);
    $stmtPedido->execute([
        ':cliente_id' => $cliente_id,
        ':valor_total' => $dados['total_geral'],
        ':taxa_entrega' => $taxa_entrega,
        ':tipo_entrega' => $tipo_entrega,
        ':bairro_entrega' => $bairro_pedido,
        ':endereco_completo' => $endereco_pedido,
        ':forma_pagamento_id' => $forma_pagamento_id,
        ':precisa_troco' => $dados['precisa_troco'] ?? 0
    ]);

    $pedido_id = $stmtPedido->fetchColumn(); 

    /* ==============================================================
       4. ITENS, VALIDAÇÃO DE DISPONIBILIDADE E BAIXA DE ESTOQUE
    ============================================================== */
    // CORRIGIDO: de 'quantity' para 'quantidade' para bater com a estrutura do seu banco
    $sqlItem = "
        INSERT INTO pedidos_online_itens (
            pedido_id, produto_id, quantidade, preco_unitario, subtotal
        ) VALUES (
            :pedido_id, :produto_id, :quantidade, :preco_unitario, :subtotal
        )
    ";

    // Queries numéricas limpas (coluna integer nativa)
    $sqlConsultaEstoque = "SELECT nome, estoque FROM produtos WHERE id = ? LIMIT 1";
    $sqlEstoque = "UPDATE produtos SET estoque = estoque - :quantidade WHERE id = :produto_id";

    $stmtItem = $pdo->prepare($sqlItem);
    $stmtConsultaEstoque = $pdo->prepare($sqlConsultaEstoque);
    $stmtEstoque = $pdo->prepare($sqlEstoque);

    foreach ($dados['itens'] as $item) {
        $id_produto = (int)$item['id'];
        $qtd_comprada = (float)$item['qtd'];
        $preco_uni = (float)$item['preco'];
        $subtotal_item = $preco_uni * $qtd_comprada;

        // 1. Busca o estoque atualizado
        $stmtConsultaEstoque->execute([$id_produto]);
        $prodBanco = $stmtConsultaEstoque->fetch(PDO::FETCH_ASSOC);

        if (!$prodBanco) {
            throw new Exception("Produto ID #{$id_produto} não foi encontrado no sistema.");
        }

        $estoque_atual = (float)$prodBanco['estoque'];
        $nome_produto = $prodBanco['nome'];

        // 2. TRAVA DE ESTOQUE
        if ($qtd_comprada > $estoque_atual) {
            throw new Exception("Estoque insuficiente para '{$nome_produto}'. Temos apenas {$estoque_atual} disponíveis e você tentou levar {$qtd_comprada}.");
        }

        // 3. Insere o item vinculado ao pedido (usando a coluna corrigida 'quantidade')
        $stmtItem->execute([
            ':pedido_id' => $pedido_id,
            ':produto_id' => $id_produto,
            ':quantidade' => $qtd_comprada,
            ':preco_unitario' => $preco_uni,
            ':subtotal' => $subtotal_item
        ]);

        // 4. Executa a baixa matemática direta no banco de dados
        $stmtEstoque->execute([
            ':quantidade' => $qtd_comprada,
            ':produto_id' => $id_produto
        ]);
    }

    // Consolida a gravação se tudo correu perfeitamente
    $pdo->commit();
    echo json_encode(['sucesso' => true, 'pedido_id' => $pedido_id]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Força a resposta a ser JSON válido para o JavaScript conseguir exibir o erro exato na tela
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>
