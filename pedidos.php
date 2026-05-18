<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

try {
    // Busca as formas de pagamento usando ILIKE (case-insensitive no Postgres)
    $stmt = $pdo->query("SELECT id, descricao FROM formas_pagamento WHERE status ILIKE 'ativo' ORDER BY descricao ASC");
    $formas_pagamento = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Se a tabela não existir, cria um array vazio para não dar erro no foreach
    $formas_pagamento = [];
    $erro_banco = "Erro ao carregar formas de pagamento. Verifique a tabela.";
}

// Adicione isso logo após os requires
$stmt_caixa = $pdo->prepare("SELECT id FROM controle_caixas WHERE usuario_id = ? AND status = 'aberto' LIMIT 1");
$stmt_caixa->execute([$_SESSION['usuario_id']]);
$caixa = $stmt_caixa->fetch();

if (!$caixa) {
    header("Location: caixas.php?erro=abrir_caixa_primeiro");
    exit;
}
$caixa_id_aberto = $caixa['id']; // Você vai precisar passar esse ID para o seu JS/AJAX salvar o pedido

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Pedido - Gestão Breno</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .busca-container { position: relative; }
        .lista-resultado { position: absolute; top: 100%; left: 0; right: 0; z-index: 1050; display: none; background: white; border: 1px solid #ddd; max-height: 200px; overflow-y: auto; }
        .item-busca { padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; }
        .item-busca:hover { background: #f8f9fa; }
        .card { border-radius: 10px; }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <h5 class="mb-4 text-primary"><i class="fas fa-shopping-basket"></i> Detalhes do Pedido</h5>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-8 busca-container">
                            <label class="form-label fw-bold text-secondary">Pesquisar Cliente</label>
                            <input type="text" id="input_cliente" class="form-control" placeholder="Digite o nome ou CPF..." autocomplete="off">
                            <div id="lista_clientes" class="lista-resultado shadow"></div>
                            <input type="hidden" id="cliente_id">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-secondary">Tipo de Pedido</label>
                                <select id="tipo_venda" class="form-select" onchange="gerenciarTipoVenda()">
                                    <option value="balcao">🏪 Balcão</option>
                                     <option value="delivery">🛵 Tele Entrega</option>
                                        </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-4 bg-light p-3 rounded border">
                        <div class="col-md-8 busca-container">
                            <label class="form-label fw-bold">Adicionar Produto</label>
                            <input type="text" id="input_produto" class="form-control" placeholder="Nome do item ou código..." autocomplete="off">
                            <div id="lista_produtos" class="lista-resultado shadow"></div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Qtd</label>
                            <input type="number" id="qtd_item" value="1" min="1" class="form-control text-center">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-success w-100 fw-bold" onclick="addItem()">
                                <i class="fas fa-plus"></i> ADD
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="tabela_itens">
                            <thead class="table-secondary">
                                <tr>
                                    <th>Produto</th>
                                    <th class="text-center">Qtd</th>
                                    <th class="text-end">Preço</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-center">#</th>
                                </tr>
                            </thead>
                            <tbody>
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="mb-4 border-bottom pb-2">Resumo e Pagamento</h5>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Forma de Pagamento</label>
                        <select id="pagamento_id" class="form-select">
                            <?php foreach($formas_pagamento as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= $f['descricao'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-danger">Desconto (R$)</label>
                        <input type="number" id="desc_venda" class="form-control" value="0.00" step="0.01" oninput="calcularTotal()">
                    </div>

                        <div id="area_taxa" class="mb-3 d-none">
                              <label class="form-label small fw-bold text-primary">Taxa de Entrega (R$)</label>
                              <input type="number" id="taxa_venda" class="form-control" value="0.00" step="0.01" oninput="calcularTotal()">
                        </div>

                    <hr>
                    
                    <div class="bg-dark text-white rounded p-3 text-center mb-4">
                        <small class="text-uppercase opacity-75">Total a Pagar</small>
                        <h2 class="mb-0 fw-bold" id="total_exibicao">R$ 0,00</h2>
                    </div>

                    <button class="btn btn-primary btn-lg w-100 py-3 fw-bold mb-2 shadow" onclick="finalizarVenda()">
                        <i class="fas fa-check-circle"></i> FINALIZAR PEDIDO
                    </button>

                    <a href="dashboard.php" class="btn btn-outline-secondary w-100 py-2 fw-bold">
                        <i class="fas fa-arrow-left"></i> Voltar ao Painel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEnd" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-map-marker-alt"></i> Endereço de Entrega</h5>
            </div>
            <div class="modal-body" id="modal_body_end">
                </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary w-100 fw-bold" data-bs-dismiss="modal">Confirmar Endereço</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/pedidos.js"></script>
</body>
</html>