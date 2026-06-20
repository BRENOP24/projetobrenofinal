<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// Filtros de data (Padrão: hoje)
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');

// 1. CONSULTA: PEDIDOS PENDENTES (motoboy_id IS NULL)
$sql_pendentes = "
    SELECT p.id, p.cliente_id, p.motoboy_id, p.valor_total, p.endereco_entrega, c.nome as cliente_nome, 'sistema' as origem, NULL as motoboy_nome
    FROM pedidos p 
    LEFT JOIN clientes c ON p.cliente_id = c.id 
    WHERE p.tipo_venda = 'delivery' AND p.motoboy_id IS NULL 
    AND p.endereco_entrega NOT ILIKE '%Retirada%' AND p.endereco_entrega NOT ILIKE '%Balcão%'
    AND p.criado_em::date BETWEEN :inicio AND :fim
    UNION ALL
    SELECT po.id, po.cliente_id, po.motoboy_id, po.valor_total, po.endereco_completo as endereco_entrega, co.nome as cliente_nome, 'site' as origem, NULL as motoboy_nome
    FROM pedidos_online po 
    LEFT JOIN clientes_online co ON po.cliente_id = co.id 
    WHERE po.motoboy_id IS NULL AND po.status != 'Cancelado' 
    AND po.tipo_entrega NOT ILIKE '%retirada%' AND po.endereco_completo NOT ILIKE '%Retirada%' AND po.endereco_completo NOT ILIKE '%Balcão%'
    AND po.data_pedido::date BETWEEN :inicio_online AND :fim_online
    ORDER BY origem DESC, id DESC";

// 2. CONSULTA: PEDIDOS VINCULADOS (CORRIGIDO: po.motoboy_id no segundo bloco)
$sql_vinculados = "
    SELECT p.id, p.cliente_id, p.motoboy_id, p.valor_total, p.endereco_entrega, c.nome as cliente_nome, 'sistema' as origem, m.nome as motoboy_nome
    FROM pedidos p 
    LEFT JOIN clientes c ON p.cliente_id = c.id 
    LEFT JOIN motoboys m ON p.motoboy_id = m.id
    WHERE p.tipo_venda = 'delivery' AND p.motoboy_id IS NOT NULL 
    AND p.criado_em::date BETWEEN :inicio AND :fim
    UNION ALL
    SELECT po.id, po.cliente_id, po.motoboy_id, po.valor_total, po.endereco_completo as endereco_entrega, co.nome as cliente_nome, 'site' as origem, m.nome as motoboy_nome
    FROM pedidos_online po 
    LEFT JOIN clientes_online co ON po.cliente_id = co.id 
    LEFT JOIN motoboys m ON po.motoboy_id = m.id
    WHERE po.motoboy_id IS NOT NULL AND po.status != 'Cancelado' 
    AND po.data_pedido::date BETWEEN :inicio_online AND :fim_online
    ORDER BY origem DESC, id DESC";

try {
    // Busca Pendentes
    $stmt = $pdo->prepare($sql_pendentes);
    $stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim, ':inicio_online' => $data_inicio, ':fim_online' => $data_fim]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Busca Vinculados
    $stmt2 = $pdo->prepare($sql_vinculados);
    $stmt2->execute([':inicio' => $data_inicio, ':fim' => $data_fim, ':inicio_online' => $data_inicio, ':fim_online' => $data_fim]);
    $vinculados = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro na consulta do banco: " . $e->getMessage());
}

$motoboys = $pdo->query("SELECT id, nome FROM motoboys ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Expedição - SAY NOW</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .nav-pills .nav-link.active {
            background-color: #212529 !important;
            color: #fff !important;
        }
    </style>
</head>
<body class="bg-light">
<div class="container mt-4">
    
    <div class="card shadow-sm mb-4 border-0">
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

    <div id="barraLote" class="card shadow-sm mb-3 border-0 bg-warning-subtle d-none">
        <div class="card-body d-flex justify-content-between align-items-center py-2">
            <div>
                <i class="fas fa-tasks text-warning-emphasis me-2"></i>
                <span id="txtContagemLote" class="fw-bold text-warning-emphasis">0 pedidos selecionados</span>
            </div>
            <button class="btn btn-warning btn-sm fw-bold" onclick="prepararVinculoLote()">
                <i class="fas fa-motorcycle"></i> Despachar Selecionados
            </button>
        </div>
    </div>

    <ul class="nav nav-pills bg-white p-2 rounded shadow-sm mb-3" id="pills-tab" role="tablist">
        <li class="nav-item flex-fill text-center" role="presentation">
            <button class="nav-link w-100 fw-bold active" id="pills-pendentes-tab" data-bs-toggle="pill" data-bs-target="#pills-pendentes" type="button" role="tab" aria-controls="pills-pendentes" aria-selected="true">
                <i class="fas fa-clock me-1"></i> Aguardando Motoboy (<?= count($pedidos) ?>)
            </button>
        </li>
        <li class="nav-item flex-fill text-center" role="presentation">
            <button class="nav-link w-100 fw-bold text-secondary" id="pills-vinculados-tab" data-bs-toggle="pill" data-bs-target="#pills-vinculados" type="button" role="tab" aria-controls="pills-vinculados" aria-selected="false">
                <i class="fas fa-shipping-fast me-1"></i> Já Vinculados (<?= count($vinculados) ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="pills-tabContent">
        
        <div class="tab-pane fade show active" id="pills-pendentes" role="tabpanel" aria-labelledby="pills-pendentes-tab">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-boxes"></i> Painel de Entregas - Aguardando Despacho</h5>
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
                                    <th width="40" class="text-center"><input type="checkbox" class="form-check-input" id="checkTodos" onclick="toggleTodos(this)" <?= empty($pedidos) ? 'disabled' : '' ?>></th>
                                    <th>Pedido</th>
                                    <th>Origem</th>
                                    <th>Cliente</th>
                                    <th>Endereço</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-center">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pedidos as $p): ?>
                                <tr>
                                    <td class="text-center"><input type="checkbox" class="form-check-input check-pedido" data-id="<?= $p['id'] ?>" data-origem="<?= $p['origem'] ?>" onclick="atualizarBarraLote()"></td>
                                    <td><strong>#<?= $p['id'] ?></strong></td>
                                    <td><?= $p['origem'] === 'site' ? '<span class="badge bg-success">Site</span>' : '<span class="badge bg-primary">Sistema</span>' ?></td>
                                    <td><?= htmlspecialchars($p['cliente_nome']) ?></td>
                                    <td><small><?= htmlspecialchars($p['endereco_entrega']) ?></small></td>
                                    <td class="text-end">R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-primary btn-sm" onclick="prepararVinculo(<?= $p['id'] ?>, '<?= $p['origem'] ?>')">
                                            <i class="fas fa-motorcycle"></i> Despachar
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; if(empty($pedidos)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">Nenhum pedido pendente neste período.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pills-vinculados" role="tabpanel" aria-labelledby="pills-vinculados-tab">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-shipping-fast"></i> Pedidos com Motoboy Atribuído</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Pedido</th>
                                    <th>Origem</th>
                                    <th>Cliente</th>
                                    <th>Endereço</th>
                                    <th>Motoboy</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-center">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($vinculados as $v): ?>
                                <tr>
                                    <td><strong>#<?= $v['id'] ?></strong></td>
                                    <td><?= $v['origem'] === 'site' ? '<span class="badge bg-success">Site</span>' : '<span class="badge bg-primary">Sistema</span>' ?></td>
                                    <td><?= htmlspecialchars($v['cliente_nome']) ?></td>
                                    <td><small><?= htmlspecialchars($v['endereco_entrega']) ?></small></td>
                                    <td><span class="badge bg-dark px-2 py-1"><i class="fas fa-user-motorcycle me-1 text-warning"></i> <?= htmlspecialchars($v['motoboy_nome'] ?? 'Não Encontrado') ?></span></td>
                                    <td class="text-end">R$ <?= number_format($v['valor_total'], 2, ',', '.') ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-warning fw-bold text-dark" onclick="prepararVinculo(<?= $v['id'] ?>, '<?= $v['origem'] ?>', <?= $v['motoboy_id'] ?>)">
                                            <i class="fas fa-edit"></i> Alterar Motoboy
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; if(empty($vinculados)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">Nenhum pedido vinculado neste período.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="modalMotoboy" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vincular Entregador <span id="num_pedido_modal"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="dados_vincular">
                <div class="mb-3">
                    <label class="form-label fw-bold">Quem vai entregar?</label>
                    <select id="select_motoboy" class="form-select form-select-lg">
                        <option value="">Selecione o Motoboy...</option>
                        <?php foreach($motoboys as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success px-4" id="btnConfirmar" onclick="confirmarVinculo()">
                    <i class="fas fa-check"></i> CONFIRMAR ALTERAÇÃO
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let modalInstancia = new bootstrap.Modal(document.getElementById('modalMotoboy'));

function toggleTodos(master) {
    const checkboxes = document.querySelectorAll('.check-pedido');
    checkboxes.forEach(cb => cb.checked = master.checked);
    atualizarBarraLote();
}

function atualizarBarraLote() {
    const marcados = document.querySelectorAll('.check-pedido:checked');
    const barra = document.getElementById('barraLote');
    const txtContagem = document.getElementById('txtContagemLote');
    
    if(barra && marcados.length > 0) {
        barra.classList.remove('d-none');
        txtContagem.innerText = `${marcados.length} pedido(s) selecionado(s) para despacho`;
    } else if(barra) {
        barra.classList.add('d-none');
        if(document.getElementById('checkTodos')) document.getElementById('checkTodos').checked = false;
    }
}

function prepararVinculo(id, origem, motoboyAtualId = "") {
    const dados = [{ id: id, origem: origem }];
    document.getElementById('dados_vincular').value = JSON.stringify(dados);
    document.getElementById('num_pedido_modal').innerText = '#' + id;
    document.getElementById('select_motoboy').value = motoboyAtualId; 
    modalInstancia.show();
}

function prepararVinculoLote() {
    const marcados = document.querySelectorAll('.check-pedido:checked');
    const dados = [];
    marcados.forEach(cb => {
        dados.push({ id: cb.getAttribute('data-id'), origem: cb.getAttribute('data-origem') });
    });
    document.getElementById('dados_vincular').value = JSON.stringify(dados);
    document.getElementById('num_pedido_modal').innerText = `em lote (${dados.length} itens)`;
    document.getElementById('select_motoboy').value = ""; 
    modalInstancia.show();
}

async function confirmarVinculo() {
    const dadosRaw = document.getElementById('dados_vincular').value;
    const motoboy_id = document.getElementById('select_motoboy').value;
    const btn = document.getElementById('btnConfirmar');

    if(!motoboy_id) return alert("Por favor, selecione um motoboy!");
    if(!dadosRaw) return alert("Nenhum dado encontrado.");

    try {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';

        const res = await fetch('atualizar_entrega.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ motoboy_id: motoboy_id, pedidos: JSON.parse(dadosRaw) })
        });

        const r = await res.json();
        if(r.status === 'sucesso') {
            modalInstancia.hide();
            alert("Operação concluída com sucesso!");
            location.reload();
        } else {
            alert("Erro do servidor: " + (r.msg || "Erro"));
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> CONFIRMAR ALTERAÇÃO';
        }
    } catch (e) {
        alert("Erro de comunicação.");
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> CONFIRMAR ALTERAÇÃO';
    }
}

async function otimizarRotas() {
    if (!confirm("Deseja otimizar automaticamente os pedidos?")) return;
    try {
        const res = await fetch('otimizar_rotas.php');
        const r = await res.json();
        if (r.status === 'sucesso') {
            alert("Rotas otimizadas!");
            location.reload();
        } else {
            alert("Erro: " + r.msg);
        }
    } catch (e) {
        alert("Erro ao otimizar rotas.");
    }
}
</script>
</body>
</html>