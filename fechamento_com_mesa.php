<?php 
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';

$pedido_id = $_GET['pedido_id'] ?? null;

// --- ETAPA 1: SE NÃO TEM ID, MOSTRA A LISTA DE SELEÇÃO ---
if (!$pedido_id) {
    try {
        $sql_abertos = "
            SELECT p.id as pedido_id, p.origem_tipo, p.origem_id, 
                   COALESCE(m.numero::text, c.numero) as identificador
            FROM pedidos p
            LEFT JOIN mesas m ON p.origem_id = m.id AND p.origem_tipo = 'mesa'
            LEFT JOIN comandas c ON p.origem_id = c.id AND p.origem_tipo = 'comanda'
            WHERE p.situacao = 'aberto'
              AND p.origem_tipo IN ('mesa', 'comanda') -- Filtra apenas estes dois tipos
            ORDER BY p.origem_tipo, identificador
        ";
        $pedidos_abertos = $pdo->query($sql_abertos)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Erro ao buscar abertos: " . $e->getMessage());
    }
?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>Selecionar para Fechamento</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </head>
    <body class="bg-light p-4">
        <div class="container">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h5 class="mb-0"><i class="fas fa-cash-register"></i> Selecione uma conta para fechar</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pedidos_abertos)): ?>
                        <div class="alert alert-warning text-center">Não há mesas ou comandas abertas no momento.</div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach($pedidos_abertos as $pa): ?>
                                <a href="fechamento_com_mesa.php?pedido_id=<?= $pa['pedido_id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="<?= $pa['origem_tipo'] == 'mesa' ? 'fas fa-utensils' : 'fas fa-clipboard-list' ?> me-2 text-secondary"></i>
                                        <strong><?= ucfirst($pa['origem_tipo']) ?>:</strong> <?= $pa['identificador'] ?>
                                    </span>
                                    <span class="badge bg-success rounded-pill px-3">Ver Conta <i class="fas fa-chevron-right ms-1"></i></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <a href="dashboard.php" class="btn btn-secondary mt-3 w-100">Voltar ao Painel</a>
                </div>
            </div>
        </div>
    </body>
    </html>
<?php 
    exit; 
} 

// --- ETAPA 2: TELA DE FECHAMENTO (QUANDO O ID É SELECIONADO) ---

try {
    // Busca dados do pedido e soma dos itens (CORRIGIDO PARA pedidos_itens)
    $stmt = $pdo->prepare("
        SELECT p.*, 
               (SELECT SUM(quantidade * valor_unitario) FROM pedidos_itens WHERE pedido_id = p.id) as subtotal
        FROM pedidos p 
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) die("Pedido não encontrado.");

    // Busca Formas de Pagamento
    $formas_pagamento = $pdo->query("SELECT id, descricao FROM formas_pagamento WHERE status ILIKE 'ativo' ORDER BY descricao ASC")->fetchAll();

    // Busca Caixa Aberto para o usuário atual
    $stmt_caixa = $pdo->prepare("SELECT id FROM controle_caixas WHERE usuario_id = ? AND status = 'aberto' LIMIT 1");
    $stmt_caixa->execute([$_SESSION['usuario_id']]);
    $caixa = $stmt_caixa->fetch();

    if (!$caixa) {
        header("Location: caixas.php?erro=abrir_caixa_primeiro");
        exit;
    }

} catch (PDOException $e) {
    die("Erro no processamento: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Finalizar <?= ucfirst($pedido['origem_tipo']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card-total { background: #212529; color: #fff; border-radius: 10px; padding: 20px; }
        .text-money { font-size: 2.5rem; font-weight: bold; color: #2ecc71; }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h4 class="mb-4 text-center">Fechamento de Conta</h4>
                    
                    <div class="alert alert-info py-2 text-center">
                        <i class="fas fa-info-circle"></i> 
                        <strong><?= ucfirst($pedido['origem_tipo']) ?>:</strong> <?= $pedido_id ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Forma de Pagamento</label>
                        <select id="pagamento_id" class="form-select form-select-lg">
                            <?php foreach($formas_pagamento as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= $f['descricao'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-danger">Desconto (R$)</label>
                        <input type="number" id="desconto" class="form-control form-control-lg" value="0.00" step="0.01" oninput="atualizarTotal()">
                    </div>

                    <div class="card-total text-center mb-4">
                        <span class="text-uppercase small opacity-75">Total a Receber</span><br>
                        <span class="text-money" id="total_exibicao">R$ <?= number_format($pedido['subtotal'], 2, ',', '.') ?></span>
                    </div>

                    <button class="btn btn-success btn-lg w-100 py-3 fw-bold mb-3 shadow" onclick="finalizarNoCaixa()">
                        <i class="fas fa-check-circle"></i> CONFIRMAR PAGAMENTO
                    </button>

                    <a href="fechamento_com_mesa.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-arrow-left"></i> Voltar à Lista
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Variáveis que o arquivo js/caixa.js irá utilizar
    const pedidoId = <?= (int)$pedido_id ?>;
    const caixaId = <?= (int)$caixa['id'] ?>;
    const subtotalOriginal = <?= (float)($pedido['subtotal'] ?? 0) ?>;

    // Função local para atualizar o total na tela enquanto digita o desconto
    function atualizarTotal() {
        const desc = parseFloat(document.getElementById('desconto').value) || 0;
        const total = subtotalOriginal - desc;
        document.getElementById('total_exibicao').innerText = total.toLocaleString('pt-br', {style: 'currency', currency: 'BRL'});
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/caixa.js?v=<?= time() ?>"></script>

</body>
</html>