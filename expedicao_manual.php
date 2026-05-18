<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

// Filtros de data (Padrão: hoje)
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');

// Busca pedidos de delivery que ainda não têm motoboy dentro do período
// Usamos criado_em::date para comparar apenas a data no Postgres
$sql = "SELECT p.*, c.nome as cliente_nome 
        FROM pedidos p 
        LEFT JOIN clientes c ON p.cliente_id = c.id 
        WHERE p.tipo_venda = 'delivery' 
        AND p.motoboy_id IS NULL 
        AND p.criado_em::date BETWEEN :inicio AND :fim
        ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca a lista de motoboys para o select
$motoboys = $pdo->query("SELECT id, nome FROM motoboys ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Expedição - SAY NOW</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
<div class="container mt-4">
    <div class="card shadow-sm mb-3 border-0">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Data Inicial</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= $data_inicio ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Data Final</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= $data_fim ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-dark w-100"><i class="fas fa-filter"></i> Filtrar</button>
                </div>
            </form>
        </div>
    </div>

<div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
    <h5 class="mb-0">
        <i class="fas fa-shipping-fast"></i> Pedidos Aguardando Motoboy
    </h5>

    <div>
        <button class="btn btn-success btn-sm" onclick="otimizarRotas()">
            <i class="fas fa-route"></i> Otimizar Rotas
        </button>

        <a href="dashboard.php" class="btn btn-sm btn-outline-light">Voltar</a>
    </div>
</div>

        
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Endereço</th>
                            <th class="text-end">Total</th>
                            <th class="text-center">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pedidos as $p): ?>
                        <tr>
                            <td><strong>#<?= $p['id'] ?></strong></td>
                            <td><?= $p['cliente_nome'] ?></td>
                            <td><small><?= $p['endereco_entrega'] ?></small></td>
                            <td class="text-end">R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></td>
                            <td class="text-center">
                                <button class="btn btn-primary btn-sm" onclick="prepararVinculo(<?= $p['id'] ?>)">
                                    <i class="fas fa-motorcycle"></i> Despachar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($pedidos)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Nenhum pedido pendente neste período.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMotoboy" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Despachar Pedido <span id="num_pedido_modal"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pedido_id_vincular">
                <div class="mb-3">
                    <label class="form-label fw-bold">Quem vai entregar?</label>
                    <select id="select_motoboy" class="form-select form-select-lg">
                        <option value="">Selecione o Motoboy...</option>
                        <?php foreach($motoboys as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= $m['nome'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success px-4" id="btnConfirmar" onclick="confirmarVinculo()">
                    <i class="fas fa-check"></i> CONFIRMAR SAÍDA
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let modalInstancia = new bootstrap.Modal(document.getElementById('modalMotoboy'));

function prepararVinculo(id) {
    document.getElementById('pedido_id_vincular').value = id;
    document.getElementById('num_pedido_modal').innerText = '#' + id;
    document.getElementById('select_motoboy').value = ""; // Reseta seleção
    modalInstancia.show();
}

async function confirmarVinculo() {
    const pedido_id = document.getElementById('pedido_id_vincular').value;
    const motoboy_id = document.getElementById('select_motoboy').value;
    const btn = document.getElementById('btnConfirmar');

    if(!motoboy_id) return alert("Por favor, selecione um motoboy!");

    try {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processando...';

        const res = await fetch('atualizar_entrega.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `pedido_id=${pedido_id}&motoboy_id=${motoboy_id}`
        });

        const r = await res.json();
        
        if(r.status === 'sucesso') {
            modalInstancia.hide();
            alert("Pedido despachado! Motoboy vinculado.");
            location.reload();
        } else {
            alert("Erro do servidor: " + (r.msg || "Erro desconhecido"));
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> CONFIRMAR SAÍDA';
        }
    } catch (e) {
        console.error("Erro no Fetch:", e);
        alert("Erro de comunicação com o sistema.");
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> CONFIRMAR SAÍDA';
    }
}

async function otimizarRotas() {
    if (!confirm("Deseja otimizar automaticamente os pedidos?")) return;

    try {
        const res = await fetch('otimizar_rotas.php');
        const r = await res.json();

        if (r.status === 'sucesso') {
            alert("Rotas otimizadas com sucesso!");
            location.reload();
        } else {
            alert("Erro: " + r.msg);
        }
    } catch (e) {
        console.error(e);
        alert("Erro ao otimizar rotas.");
    }
}
</script>

</script>
</body>
</html>