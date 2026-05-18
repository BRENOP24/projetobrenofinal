<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

try {
    // 1. Fornecedores
    $fornecedores = $pdo->query("SELECT id, razao_social FROM fornecedores 
                                 WHERE status ILIKE 'Ativo' 
                                 ORDER BY razao_social")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Planos de Contas
    $planos = $pdo->query("SELECT id, codigo, descricao FROM plano_contas 
                           WHERE status ILIKE 'ativo' AND tipo ILIKE 'despesa' 
                           ORDER BY codigo")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Produtos (Trazendo o código_barras para permitir a filtragem por ele)
    $produtos = $pdo->query("SELECT id, nome, preco_venda, codigo_barras FROM produtos 
                             ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar dados para compra: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Entrada de Mercadoria - Projeto Breno</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }

        .btn-voltar {
            text-decoration: none;
            font-weight: 500;
            background: #f1f3f5;
            padding: 8px 14px;
            border-radius: 8px;
            color: #333;
            transition: 0.2s;
        }

        .btn-voltar:hover {
            background: #383c3f;
        }

        .sessao-nota {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }

        .grid-entrada {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .tabela-itens {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .tabela-itens th {
            background: #333;
            color: white;
            padding: 10px;
        }

        .tabela-itens td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }

        .total-container {
            text-align: right;
            font-size: 20px;
            font-weight: bold;
            margin-top: 15px;
            color: #28a745;
        }

        .btn-add {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            border-radius: 4px;
        }

        .btn-submit {
            background: #28a745;
            width: 100%;
            color: white;
            padding: 15px;
            font-size: 18px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        /* Estilização básica para o novo campo de busca rápida */
        .busca-produto-container {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        #busca_rapida_produto {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            outline: none;
        }
        #busca_rapida_produto:focus {
            border-color: #007bff;
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header-section">
       <a href="dashboard.php" class="btn-voltar" style="text-decoration:none;font-weight:500;">⬅ Voltar ao Painel</a>
    </div>

    <h2>📦 Entrada de Mercadoria / Compra</h2>

    <form action="processar_entrada.php" method="POST" id="formEntrada">

        <div class="sessao-nota">
            <div class="grid-entrada">

                <div class="form-group" style="grid-column: span 2;">
                    <label>Fornecedor</label>
                    <select name="id_fornecedor" required>
                        <option value="">Selecione...</option>
                        <?php foreach($fornecedores as $forn): ?>
                            <option value="<?= $forn['id'] ?>"><?= $forn['razao_social'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <label>Plano de Contas (Financeiro)</label>
                    <select name="id_plano_conta" required>
                        <option value="">Selecione a categoria...</option>
                        <?php foreach($planos as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= $p['codigo'] ?> - <?= $p['descricao'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Nº Nota Fiscal</label>
                    <input type="text" name="numero_nota" required>
                </div>

                <div class="form-group">
                    <label>Data Emissão</label>
                    <input type="date" name="data_emissao" required>
                </div>

                <div class="form-group">
                    <label>Vencimento do Boleto</label>
                    <input type="date" name="data_vencimento" required>
                </div>

                <div class="form-group">
                    <label>Forma de Pagamento</label>
                    <select name="tipo_pagamento" id="tipo_pagamento" onchange="toggleParcelas()">
                        <option value="AVISTA">À Vista</option>
                        <option value="PARCELADO">Parcelado</option>
                    </select>
                </div>

                <div class="form-group" id="campo_parcelas" style="display: none;">
                    <label>Qtd. Parcelas</label>
                    <input type="number" name="parcelas" min="2" max="12" value="2">
                </div>

            </div>
        </div>

        <div class="sessao-nota">
            <h4>Adicionar Produtos</h4>

            <!-- Grid adaptada para conter o filtro por texto e código de barras -->
            <div class="grid-entrada" style="grid-template-columns: 2fr 2fr 1fr 1fr 1fr; align-items: flex-end;">
                
                <!-- NOVO CAMPO: Filtro digitável -->
                <div class="busca-produto-container">
                    <label style="font-size: 13px; font-weight: bold; color: #555;">Filtrar Produto (Nome ou Código)</label>
                    <input type="text" id="busca_rapida_produto" placeholder="Digite o nome ou bipe o código..." onkeyup="filtrarProdutosSelect()">
                </div>

                <div class="busca-produto-container">
                    <label style="font-size: 13px; font-weight: bold; color: #555;">Selecione o Produto</label>
                    <select id="select_produto">
                        <option value="">Buscar produto...</option>
                        <?php foreach($produtos as $prod): ?>
                            <option value="<?= $prod['id'] ?>" data-preco="<?= $prod['preco_venda'] ?>" data-codigo="<?= htmlspecialchars($prod['codigo_barras'] ?? '') ?>">
                                <?= htmlspecialchars($prod['nome']) ?> <?= !empty($prod['codigo_barras']) ? '('.$prod['codigo_barras'].')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <input type="number" id="input_qtd" placeholder="Qtd" min="1">
                <input type="number" id="input_custo" placeholder="Custo Un." step="0.01">

                <button type="button" class="btn-add" onclick="adicionarItem()">+ Adicionar</button>
            </div>

            <table class="tabela-itens" id="tabelaItens">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Qtd</th>
                        <th>Custo Un.</th>
                        <th>Subtotal</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <div class="total-container">
                Total da Nota: R$ <span id="valor_total_exibido">0,00</span>
                <input type="hidden" name="valor_total" id="valor_total_input" value="0">
            </div>
        </div>

        <button type="submit" class="btn-submit">
            🚀 Finalizar Entrada e Gerar Contas a Pagar
        </button>

    </form>

</div>

<script>
let itens = [];

// NOVA FUNÇÃO: Faz o filtro em tempo real sem afetar a estrutura original do formulário
function filtrarProdutosSelect() {
    const termoBusca = document.getElementById('busca_rapida_produto').value.toLowerCase().trim();
    const select = document.getElementById('select_produto');
    const opcoes = select.options;

    let primeiroEncontrado = null;

    for (let i = 0; i < opcoes.length; i++) {
        if (opcoes[i].value === "") continue; // Pula a opção padrão "Buscar produto..."

        const nomeProduto = opcoes[i].text.toLowerCase();
        const codigoBarras = opcoes[i].getAttribute('data-codigo').toLowerCase();

        // Se encontrar o termo digitado no nome OU no código de barras do produto
        if (nomeProduto.includes(termoBusca) || codigoBarras.includes(termoBusca)) {
            opcoes[i].style.display = ""; // Mostra a opção
            if (!primeiroEncontrado) {
                primeiroEncontrado = opcoes[i];
            }
        } else {
            opcoes[i].style.display = "none"; // Esconde a opção que não bate com a busca
        }
    }

    // Se o usuário apagar tudo, reseta o select para a posição inicial
    if (termoBusca === "") {
        select.value = "";
    } else if (primeiroEncontrado && termoBusca.length >= 3) {
        // Seleciona automaticamente se houver uma correspondência exata ou forte (ex: ao ripar um código de barras)
        select.value = primeiroEncontrado.value;
    }
}

function adicionarItem() {
    const select = document.getElementById('select_produto');
    const qtd = parseFloat(document.getElementById('input_qtd').value);
    const custo = parseFloat(document.getElementById('input_custo').value);

    if (!select.value || !qtd || !custo) {
        alert("Preencha todos os campos do produto!");
        return;
    }

    const item = {
        id: select.value,
        nome: select.options[select.selectedIndex].text,
        qtd: qtd,
        custo: custo,
        subtotal: qtd * custo
    };

    itens.push(item);
    atualizarTabela();

    document.getElementById('input_qtd').value = '';
    document.getElementById('input_custo').value = '';
    document.getElementById('busca_rapida_produto').value = ''; // Limpa o campo de busca
    filtrarProdutosSelect(); // Reseta a lista de exibição do select
}

function atualizarTabela() {
    const tbody = document.querySelector('#tabelaItens tbody');
    tbody.innerHTML = '';
    let totalGeral = 0;

    itens.forEach((item, index) => {
        totalGeral += item.subtotal;

        tbody.innerHTML += `
            <tr>
                <td>${item.nome} <input type="hidden" name="produtos[]" value="${item.id}"></td>
                <td>${item.qtd} <input type="hidden" name="qtds[]" value="${item.qtd}"></td>
                <td>R$ ${item.custo.toFixed(2)} <input type="hidden" name="custos[]" value="${item.custo}"></td>
                <td>R$ ${item.subtotal.toFixed(2)}</td>
                <td>
                    <button type="button" onclick="removerItem(${index})" style="color:red; border:none; background:none; cursor:pointer;">
                        ❌
                    </button>
                </td>
            </tr>
        `;
    });

    document.getElementById('valor_total_exibido').innerText =
        totalGeral.toLocaleString('pt-br', { minimumFractionDigits: 2 });

    document.getElementById('valor_total_input').value = totalGeral;
}

function removerItem(index) {
    itens.splice(index, 1);
    atualizarTabela();
}

function toggleParcelas() {
    const tipo = document.getElementById('tipo_pagamento').value;
    const campo = document.getElementById('campo_parcelas');
    
    if (tipo === 'PARCELADO') {
        campo.style.display = 'block';
    } else {
        campo.style.display = 'none';
    }
}
</script>

</body>
</html>