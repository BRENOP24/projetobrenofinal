<?php
require_once 'config/conexao.php';

// Iniciamos a sessão caso ela ainda não tenha sido iniciada, para capturar o usuário se necessário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mensagem_painel = "";

// =======================================================
// AÇÃO EM MASSA: AVANÇAR STATUS DE TODOS OS PEDIDOS ATIVOS
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_avancar_todos'])) {
    try {
        $pdo->beginTransaction();

        // [TRAVA DO CAIXA REAL] Busca um caixa que esteja com status 'aberto' na sua tabela controle_caixas
        // Damos preferência para o caixa do usuário logado, se não houver, pega o primeiro aberto do sistema
        $usuario_id = $_SESSION['usuario_id'] ?? null;
        
        if ($usuario_id) {
            $sql_caixa = "SELECT id FROM controle_caixas WHERE usuario_id = ? AND status = 'aberto' LIMIT 1";
            $stmt_caixa = $pdo->prepare($sql_caixa);
            $stmt_caixa->execute([$usuario_id]);
        } else {
            $sql_caixa = "SELECT id FROM controle_caixas WHERE status = 'aberto' LIMIT 1";
            $stmt_caixa = $pdo->query($sql_caixa);
        }
        
        $caixa_ativo = $stmt_caixa->fetch(PDO::FETCH_ASSOC);

        // Se nenhum caixa estiver aberto no sistema, bloqueia a finalização em massa
        if (!$caixa_ativo) {
            throw new Exception("Não há nenhum caixa aberto! Abra o caixa na tela de movimentação antes de finalizar os pedidos.");
        }

        $id_caixa = (int)$caixa_ativo['id'];

        // 1. Quem é 'Saiu para Entrega' -> Finaliza (Atualizando o id_caixa correto)
        $sql1 = "UPDATE pedidos_online SET status = 'Finalizado', id_caixa = :id_caixa WHERE status = 'Saiu para Entrega'";
        $stmt1 = $pdo->prepare($sql1);
        $stmt1->execute([':id_caixa' => $id_caixa]);
        $total1 = $stmt1->rowCount();

        // 2. Quem é 'Em preparação' e RETIRADA -> Finaliza (Atualizando o id_caixa correto)
        $sql2 = "UPDATE pedidos_online SET status = 'Finalizado', id_caixa = :id_caixa WHERE status = 'Em preparação' AND tipo_entrega = 'retirada'";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([':id_caixa' => $id_caixa]);
        $total2 = $stmt2->rowCount();

        // 3. Quem é 'Em preparação' e DELIVERY -> Sai para entrega
        $sql3 = "UPDATE pedidos_online SET status = 'Saiu para Entrega' WHERE status = 'Em preparação' AND tipo_entrega != 'retirada'";
        $stmt3 = $pdo->prepare($sql3);
        $stmt3->execute();
        $total3 = $stmt3->rowCount();

        // 4. Quem é 'Confirmado' -> Vai para a Cozinha
        $sql4 = "UPDATE pedidos_online SET status = 'Em preparação' WHERE status = 'Confirmado'";
        $stmt4 = $pdo->prepare($sql4);
        $stmt4->execute();
        $total4 = $stmt4->rowCount();

        // 5. Quem é 'Pendente' -> Confirma/Aceita
        $sql5 = "UPDATE pedidos_online SET status = 'Confirmado' WHERE status = 'Pendente'";
        $stmt5 = $pdo->prepare($sql5);
        $stmt5->execute();
        $total5 = $stmt5->rowCount();

        $pdo->commit();

        $totalAlterados = $total1 + $total2 + $total3 + $total4 + $total5;

        if ($totalAlterados > 0) {
            $mensagem_painel = "<div style='color:#155724; padding:15px; background:#d4edda; border: 1px solid #c3e6cb; border-radius:8px; margin-bottom:20px; font-weight:bold; display:flex; align-items:center; gap:10px;'>
                                    <i class='fas fa-forward fa-lg'></i> 🚀 Sucesso! O fluxo de $totalAlterados status foi avançado no painel!
                                 </div>";
        } else {
            $mensagem_painel = "<div style='color:#383d41; padding:15px; background:#e2e3e5; border: 1px solid #d6d8db; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:10px;'>
                                    <i class='fas fa-info-circle fa-lg'></i> Nenhum pedido ativo para avançar no momento.
                                 </div>";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensagem_painel = "<div style='color:#721c24; padding:15px; background:#f8d7da; border: 1px solid #f5c6cb; border-radius:8px; margin-bottom:20px; font-weight:bold;'>
                                ❌ Erro ao processar fluxo em lote: " . $e->getMessage() . "
                            </div>";
    }
}


// =======================================================
// BUSCA DOS PEDIDOS ATIVOS
// =======================================================
try {
    
    $sql = "SELECT 
                p.*, 
                c.nome AS cliente_nome, 
                c.telefone AS cliente_telefone,
                fp.descricao AS nome_pagamento
            FROM pedidos_online p 
            LEFT JOIN clientes_online c ON p.cliente_id = c.id 
            LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
            WHERE p.status NOT IN ('Finalizado', 'Cancelado')
            ORDER BY p.data_pedido DESC";

    $pedidos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar pedidos online: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel de Pedidos - Restaurante</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        
        .card-pedido { background: white; border-radius: 8px; padding: 15px; margin-bottom: 15px; border-left: 5px solid #ffc107; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: bold; }
        
        /* Cores dos Status conforme o seu Enum do Banco */
        .status-Pendente { color: #856404; background: #fff3cd; }
        .status-Confirmado { color: #0c5460; background: #d1ecf1; }
        .status-Em-preparacao { color: #004085; background: #cce5ff; }
        .status-Saiu-para-Entrega { color: #155724; background: #d4edda; }
        
        /* Tipos de Entrega */
        .tipo-entrega { padding: 8px; border-radius: 5px; font-size: 14px; margin-bottom: 10px; display: inline-block; font-weight: bold; }
        .tipo-delivery { background: #e8f4fd; color: #0d6efd; border: 1px solid #b6d4fe; }
        .tipo-balcao { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }

        .endereco-box { background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 14px; margin-bottom: 10px; border-left: 3px solid #ccc; }

        .info-pagamento { background: #e9ecef; padding: 5px 10px; border-radius: 5px; font-size: 14px; display: inline-block; margin-top: 5px; border: 1px solid #dee2e6; }
        .badge-troco { background: #fff3cd; color: #856404; padding: 5px 10px; border-radius: 5px; font-size: 14px; font-weight: bold; border: 1px solid #ffeeba; display: inline-block; margin-top: 5px; }

        .btn-whats { background: #25d366; color: white; padding: 5px 10px; text-decoration: none; border-radius: 5px; font-size: 13px; display: inline-block; }
        .btn-acao { border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-weight: bold; margin-right: 5px; margin-top: 10px; color: white; }
        .btn-detalhes { background: #6c757d; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; }
        .btn-cancelar { background: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-weight: bold; margin-top: 10px; }

        /* Estilo da Barra Superior de Ação em Lote */
        .barra-acoes-massa { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #e3e6f0; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-massa { background: #007bff; color: white; border: none; padding: 12px 20px; border-radius: 6px; font-weight: bold; font-size: 14px; cursor: pointer; box-shadow: 0 3px 6px rgba(0,123,255,0.15); transition: 0.2s; display: flex; align-items: center; gap: 8px; }
        .btn-massa:hover { background: #0069d9; transform: translateY(-1px); }
    </style>
</head>
<body>
    <audio id="audioAlerta" src="alerta/alerta_pedido.mp3" preload="auto"></audio>
    <div class="header">
        <h1 style="margin: 0;">🛎️ Pedidos Ativos</h1>
        <div>Atualização automática em <span id="contador">30</span>s</div>
    </div>
    
    <?= $mensagem_painel ?>

    <div class="barra-acoes-massa">
        <div>
            <h3 style="margin:0; color:#333;"><i class="fas fa-forward"></i> Controle de Fluxo Rápido</h3>
            <p style="margin:5px 0 0 0; font-size:13px; color:#666;">Avança todos os pedidos simultaneamente para o próximo estágio (TCC Mode).</p>
        </div>
        <form method="POST" onsubmit="return confirm('Deseja avançar o estágio de TODOS os pedidos ativos na tela?');">
            <button type="submit" name="btn_avancar_todos" class="btn-massa">
                <i class="fas fa-angle-double-right"></i> Avançar Próxima Etapa Geral
            </button>
        </form>
    </div>
    
    <?php if(empty($pedidos)): ?>
        <div style="text-align:center; padding: 50px; background: white; border-radius: 8px; color: #666;">
            <i class="fas fa-inbox fa-3x" style="color: #ccc; margin-bottom: 15px;"></i>
            <h2>Nenhum pedido no momento.</h2>
            <p>Fique de olho, logo chegam novos pedidos!</p>
        </div>
    <?php endif; ?>

    <?php foreach($pedidos as $p): ?>
        <?php 
            // Define a cor da borda baseado no status
            $corBorda = '#ffc107'; // Pendente
            if($p['status'] == 'Confirmado') $corBorda = '#17a2b8';
            if($p['status'] == 'Em preparação') $corBorda = '#007bff';
            if($p['status'] == 'Saiu para Entrega') $corBorda = '#28a745';

            // Cria um nome de classe limpo para o CSS (ex: "Em preparação" vira "Em-preparacao")
            $classeStatus = 'status-' . str_replace([' ', 'ç', 'ã'], ['-', 'c', 'a'], $p['status']);
        ?>
        <div class="card-pedido" style="border-left-color: <?= $corBorda ?>;">
            <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="margin:0;">#<?= $p['id'] ?> - <?= htmlspecialchars($p['cliente_nome']) ?></h3>
                <span class="status-badge <?= $classeStatus ?>"><?= $p['status'] ?></span>
            </div>
            
            <div style="margin-bottom: 15px;">
                <small style="color: #666;"><i class="far fa-clock"></i> Pedido feito às <?= date('H:i', strtotime($p['data_pedido'])) ?></small>
            </div>

            <?php if($p['tipo_entrega'] === 'retirada'): ?>
                <div class="tipo-entrega tipo-balcao">
                    <i class="fas fa-store"></i> Cliente Retira no Local
                </div>
            <?php else: ?>
                <div class="tipo-entrega tipo-delivery">
                    <i class="fas fa-motorcycle"></i> Entrega
                </div>
                <div class="endereco-box">
                    <strong>Endereço:</strong> <?= htmlspecialchars($p['endereco_completo']) ?><br>
                    <strong>Bairro:</strong> <?= htmlspecialchars($p['bairro_entrega']) ?><br>
                    <small>Taxa de Entrega: R$ <?= number_format($p['taxa_entrega'], 2, ',', '.') ?></small>
                </div>
            <?php endif; ?>
            
            <div style="margin: 10px 0;">
                <div class="info-pagamento">
                    <i class="fas fa-wallet"></i> <b>Pagamento:</b> <?= $p['nome_pagamento'] ?? 'Não informado' ?>
                </div>

                <?php if(!empty($p['precisa_troco']) && $p['precisa_troco'] > 0): ?>
                    <div class="badge-troco">
                        <i class="fas fa-money-bill-wave"></i> Troco para: <b>R$ <?= number_format($p['precisa_troco'], 2, ',', '.') ?></b>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="display:flex; justify-content: space-between; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <div style="font-size: 18px;"><strong>Total: R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></strong></div>
                <button class="btn-detalhes" onclick="verDetalhes(<?= $p['id'] ?>)">
                    <i class="fa fa-list"></i> Ver Itens
                </button>
            </div>
            
            <div style="margin-bottom: 15px;">
                <?php if(!empty($p['cliente_telefone'])): ?>
                    <a href="https://wa.me/55<?= preg_replace('/\D/', '', $p['cliente_telefone']) ?>" class="btn-whats" target="_blank">
                        <i class="fab fa-whatsapp"></i> Chamar no WhatsApp
                    </a>
                <?php endif; ?>
            </div>

            <div style="border-top: 1px solid #eee; padding-top: 10px; display: flex; flex-wrap: wrap; gap: 5px; justify-content: space-between;">
                <div>
                    <?php if($p['status'] == 'Pendente'): ?>
                        <button onclick="atualizarStatus(<?= $p['id'] ?>, 'Confirmado', '<?= $p['tipo_entrega'] ?>')" class="btn-acao" style="background: #17a2b8;">Aceitar Pedido</button>
                    <?php endif; ?>

                    <?php if($p['status'] == 'Confirmado'): ?>
                        <button onclick="atualizarStatus(<?= $p['id'] ?>, 'Em preparação', '<?= $p['tipo_entrega'] ?>')" class="btn-acao" style="background: #007bff;">Mandar p/ Cozinha</button>
                    <?php endif; ?>

                    <?php if($p['status'] == 'Em preparação'): ?>
                        <?php if($p['tipo_entrega'] === 'retirada'): ?>
                            <button onclick="atualizarStatus(<?= $p['id'] ?>, 'Finalizado', '<?= $p['tipo_entrega'] ?>')" class="btn-acao" style="background: #28a745;">Cliente Retirou (Finalizar)</button>
                        <?php else: ?>
                            <button onclick="atualizarStatus(<?= $p['id'] ?>, 'Saiu para Entrega', '<?= $p['tipo_entrega'] ?>')" class="btn-acao" style="background: #ffc107; color:#333;"><i class="fas fa-motorcycle"></i> Despachar Entrega</button>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if($p['status'] == 'Saiu para Entrega'): ?>
                        <button onclick="atualizarStatus(<?= $p['id'] ?>, 'Finalizado', '<?= $p['tipo_entrega'] ?>')" class="btn-acao" style="background: #28a745;">Entrega Concluída (Finalizar)</button>
                    <?php endif; ?>
                </div>

                <button onclick="atualizarStatus(<?= $p['id'] ?>, 'Cancelado')" class="btn-cancelar"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </div>
    <?php endforeach; ?>

    <div id="modalDetalhes" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
        <div style="background:white; max-width:400px; margin:50px auto; padding:20px; border-radius:10px;">
            <h3>📋 Itens do Pedido</h3>
            <div id="conteudoItens" style="max-height: 300px; overflow-y: auto; margin: 15px 0;">Carregando...</div>
            <hr>
            <button onclick="fecharModal()" style="width:100%; padding:10px; background:#6c757d; color:white; border:none; border-radius:5px; cursor:pointer;">Fechar</button>
        </div>
    </div>

    <script>
        const somAlerta = document.getElementById('audioAlerta');
        let tempo = 30;

        function atualizarStatus(id, novoStatus, tipoEntrega = '') {
            if(!confirm("Mudar status do pedido #" + id + " para '" + novoStatus + "'?")) return;
            
            const formData = new FormData();
            formData.append('id', id);
            formData.append('status', novoStatus);   
            
            if(tipoEntrega !== '') {
                formData.append('tipo_entrega', tipoEntrega);
            }

            fetch('atualizar_status.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if(res.sucesso || res.status === 'sucesso') {
                    location.reload();
                } else {
                    alert("BLOQUEADO: " + (res.erro || res.msg || "Verifique o processamento do pedido."));
                }
            })
            .catch(err => {
                console.error(err);
                alert("Erro de comunicação com o servidor.");
            });
        }

        function verificarNovosPedidos() {
            fetch('checar_total_pedidos.php')
                .then(res => res.json())
                .then(data => {
                    if(data.total_pendentes > 0) {
                        if (somAlerta) {
                            somAlerta.loop = true;
                            somAlerta.play().catch(e => console.log("Clique na página para ativar o som."));
                        }
                    } else {
                        if (somAlerta) {
                            somAlerta.pause();
                            somAlerta.currentTime = 0;
                        }
                    }
                })
                .catch(err => console.error("Erro ao checar:", err));
        }

        function verDetalhes(id) {
            document.getElementById('modalDetalhes').style.display = 'block';
            const conteudo = document.getElementById('conteudoItens');
            conteudo.innerHTML = "Carregando...";

            fetch('buscar_itens.php?id=' + id)
                .then(res => res.json())
                .then(itens => {
                    if(itens.erro) {
                        conteudo.innerHTML = `<span style='color:red;'>${itens.erro}</span>`;
                        return;
                    }
                    if(itens.length === 0) {
                        conteudo.innerHTML = "Nenhum item encontrado.";
                        return;
                    }

                    let html = '<ul style="list-style:none; padding:0;">';
                    itens.forEach(item => {
                        let nomeProduto = item.produto_nome || item.nome_produto || "Produto ID " + item.produto_id;
                        let subtotal = parseFloat(item.preco_unitario) * parseInt(item.quantidade);

                        html += `<li style="padding:10px 0; border-bottom:1px solid #eee; display:flex; justify-content:space-between;">
                                    <span><b>${item.quantidade}x</b> ${nomeProduto}</span>
                                    <span>R$ ${subtotal.toFixed(2).replace('.', ',')}</span>
                                 </li>`;
                    });
                    html += '</ul>';
                    conteudo.innerHTML = html;
                })
                .catch(err => {
                    console.error(err);
                    conteudo.innerHTML = "Erro ao comunicar com o servidor.";
                });
        }

        function fecharModal() {
            document.getElementById('modalDetalhes').style.display = 'none';
        }

        setInterval(verificarNovosPedidos, 5000);

        setInterval(() => {
            tempo--;
            const elementoContador = document.getElementById('contador');
            if(elementoContador) {
                elementoContador.innerText = tempo;
            }
            if(tempo <= 0) location.reload();
        }, 1000);
    </script>
</body>
</html>
