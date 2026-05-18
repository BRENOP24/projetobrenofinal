<?php
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

/* ===============================
   1. DADOS DA EMPRESA E CONFIGS
================================ */
$stmtEmpresa = $pdo->query("SELECT * FROM empresas LIMIT 1");
$empresa = $stmtEmpresa->fetch(PDO::FETCH_ASSOC);

$empresa_id = $empresa['id'] ?? 1;
$cor_tema = $empresa['cor_tema'] ?? '#e3242b';

// Verificação robusta de loja aberta
$loja_aberta = (
    isset($empresa['status_loja']) && 
    ($empresa['status_loja'] == 1 || $empresa['status_loja'] == '1' || $empresa['status_loja'] == 'S')
);

/* ===============================
   2. TAXAS DE ENTREGA E HORÁRIOS
================================ */
// Adicionamos o ORDER BY para organizar por nome do bairro
$stmtBairros = $pdo->prepare("SELECT nome_bairro, valor_taxa FROM taxas_bairros WHERE empresa_id = ? ORDER BY nome_bairro ASC");
$stmtBairros->execute([$empresa_id]);
$bairros_entrega = $stmtBairros->fetchAll(PDO::FETCH_ASSOC);

$stmtHorarios = $pdo->prepare("SELECT * FROM horarios_funcionamento WHERE empresa_id = ? ORDER BY dia_semana ASC");
$stmtHorarios->execute([$empresa_id]);
$horarios = $stmtHorarios->fetchAll(PDO::FETCH_ASSOC);
$dias_semana_nome = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

/* ===============================
   3. BUSCA PRODUTOS VISÍVEIS
================================ */
$sqlProdutos = "SELECT p.*, COALESCE(c.nome, 'Outros') as nome_categoria 
                FROM produtos p 
                LEFT JOIN categorias c ON p.categoria_id = c.id 
                WHERE p.status = 'Ativo' AND p.aparecer_online = 'S'
                ORDER BY nome_categoria ASC, p.nome ASC";
$stmtProdutos = $pdo->query($sqlProdutos);
$produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($empresa['nome_fantasia']) ?> - Cardápio Online</title>
    
    <style>
        :root { --cor-primaria: <?= $cor_tema ?>; }
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f8f9fa; padding-bottom: 100px; }
        .cabecalho { background-color: var(--cor-primaria); color: white; padding: 20px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .status-loja { display: inline-block; padding: 5px 15px; border-radius: 20px; font-weight: bold; font-size: 14px; }
        .aberta { background-color: #28a745; color: white; }
        .fechada { background-color: #dc3545; color: white; }
        .container { max-width: 600px; margin: 0 auto; padding: 15px; }

        /* Estilos da Modal */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); overflow-y: auto; }
        .modal-content { background-color: white; margin: 5% auto; padding: 20px; width: 90%; max-width: 500px; border-radius: 10px; }
        .input-checkout { width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 16px; }
        
        .info-loja { background: white; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #eee; color: #444; }
        .info-loja summary { padding: 15px; font-weight: bold; cursor: pointer; list-style: none; display: flex; align-items: center; }
        .info-loja-conteudo { padding: 15px; border-top: 1px solid #eee; }
        
        .categoria-titulo { font-size: 20px; color: #333; margin: 30px 0 15px; border-bottom: 2px solid var(--cor-primaria); padding-bottom: 5px; }
        
        /* CARD DE PRODUTO AJUSTADO */
        .produto-card { display: flex; background: white; padding: 15px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #eee; }
        .produto-img { width: 90px; height: 90px; border-radius: 8px; object-fit: cover; margin-right: 15px; }
        .produto-info { flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .produto-nome { font-weight: bold; font-size: 16px; margin: 0 0 5px 0; color: #333; }
        .produto-obs { font-size: 13px; color: #666; margin: 0 0 10px 0; line-height: 1.4; }
        
        /* ESPAÇAMENTO ENTRE PREÇO E BOTÃO */
        .produto-rodape { display: flex; justify-content: space-between; align-items: center; margin-top: auto; }
        .produto-preco { font-weight: bold; color: #28a745; font-size: 16px; }
        
        .btn-add { background: var(--cor-primaria); color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .btn-add:disabled { background: #ccc; cursor: not-allowed; }

        #barra-carrinho { position: fixed; bottom: 0; left: 0; width: 100%; background: white; padding: 15px; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); display: none; justify-content: center; box-sizing: border-box; z-index: 1000; }
        .btn-ver-carrinho { background: var(--cor-primaria); color: white; border: none; padding: 15px; border-radius: 8px; width: 100%; max-width: 600px; font-size: 16px; font-weight: bold; cursor: pointer; display: flex; justify-content: space-between; }
    </style>
</head>
<body>

    <header class="cabecalho">
        <h1><?= htmlspecialchars($empresa['nome_fantasia']) ?></h1>
        <span class="status-loja <?= $loja_aberta ? 'aberta' : 'fechada' ?>">
            <?= $loja_aberta ? '🟢 Aberto para Pedidos' : '🔴 Fechado no momento' ?>
        </span>
    </header>

    <main class="container">
<main class="container">
 <details class="info-loja">

            <summary>ℹ️ Ver Informações do Estabelecimento</summary>

           

            <div class="info-loja-conteudo">

                <div class="info-item">

                    <span class="info-icon">📍</span>

                    <div>

                        <strong>Endereço:</strong><br>

                        <?= htmlspecialchars($empresa['endereco'] . ', ' . $empresa['numero']) ?> - <?= htmlspecialchars($empresa['bairro']) ?><br>

                        <?= htmlspecialchars($empresa['cidade'] . '/' . $empresa['estado']) ?>

                    </div>

                </div>



                <div class="info-item">

                    <span class="info-icon">🛵</span>

                    <div>

                        <strong>Taxa de Entrega:</strong><br>

                        <span style="color: #666;">Entrega a ser avaliada</span>

                    </div>

                </div>



                <div class="info-item">

                    <span class="info-icon">💳</span>

                    <div>

                        <strong>Formas de Pagamento:</strong><br>

                        <?php if(($empresa['aceita_dinheiro'] ?? 'N') == 'S') echo '<span class="badge-pgto">💵 Dinheiro</span>'; ?>

                        <?php if(($empresa['aceita_pix'] ?? 'N') == 'S') echo '<span class="badge-pgto">💎 PIX</span>'; ?>

                        <?php if(($empresa['aceita_cartao_debito'] ?? 'N') == 'S') echo '<span class="badge-pgto">💳 Débito</span>'; ?>

                        <?php if(($empresa['aceita_cartao_credito'] ?? 'N') == 'S') echo '<span class="badge-pgto">💳 Crédito</span>'; ?>

                        <?php if(($empresa['aceita_alimentacao'] ?? 'N') == 'S') echo '<span class="badge-pgto">🍱 Alimentação</span>'; ?>

                        <?php if(($empresa['aceita_refeicao'] ?? 'N') == 'S') echo '<span class="badge-pgto">🍕 Refeição</span>'; ?>

                    </div>

                </div>



                <div class="info-item" style="margin-bottom: 0;">

                    <span class="info-icon">🕒</span>

                    <div style="width: 100%;">

                        <strong>Horários de Atendimento:</strong>

                        <ul class="horarios-lista">

                            <?php foreach($horarios as $h): ?>

                                <li>

                                    <span><?= $dias_semana_nome[$h['dia_semana']] ?? 'Dia' ?></span>

                                    <?php if($h['situacao'] == 'aberto'): ?>

                                        <span><?= date('H:i', strtotime($h['abertura'])) ?> às <?= date('H:i', strtotime($h['fechamento'])) ?></span>

                                    <?php else: ?>

                                        <span style="color: #dc3545; font-weight: bold;">Fechado</span>

                                    <?php endif; ?>

                                </li>

                            <?php endforeach; ?>

                            <?php if(empty($horarios)): ?>

                                <li>Nenhum horário cadastrado.</li>

                            <?php endif; ?>

                        </ul>

                    </div>

                </div>

            </div>

        </details>



        <?php 
        $ultima_cat = "";
        foreach ($produtos as $p): 
            if ($p['nome_categoria'] !== $ultima_cat): 
                $ultima_cat = $p['nome_categoria'];
        ?>
            <h2 class="categoria-titulo"><?= htmlspecialchars($ultima_cat) ?></h2>
        <?php endif; ?>
            <div class="produto-card">
                <?php if(!empty($p['imagem'])): ?>
                    <img src="uploads/produtos/<?= htmlspecialchars($p['imagem']) ?>" class="produto-img">
                <?php else: ?>
                    <div style="width:90px; height:90px; background:#eee; margin-right:15px; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#999; font-size:11px;">Sem Foto</div>
                <?php endif; ?>
                <div class="produto-info">
                    <div>
                        <h3 class="produto-nome"><?= htmlspecialchars($p['nome']) ?></h3>
                        <?php if(!empty($p['obs_online'])): ?>
                            <p class="produto-obs"><?= htmlspecialchars($p['obs_online']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="produto-rodape">
                        <span class="produto-preco">R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?></span>
                        <button class="btn-add btn-adicionar-produto" 
                                data-id="<?= $p['id'] ?>" 
                                data-nome="<?= htmlspecialchars($p['nome']) ?>" 
                                data-preco="<?= $p['preco_venda'] ?>"
                                <?= !$loja_aberta ? 'disabled' : '' ?>>
                            Adicionar
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </main>

    <div id="barra-carrinho">
        <button class="btn-ver-carrinho" onclick="document.getElementById('modal-checkout').style.display='block'; renderizarItensCarrinho();">
            <span id="carrinho-qtd">0 itens</span>
            <span>Ver Carrinho</span>
            <span id="carrinho-total">R$ 0,00</span>
        </button>
    </div>

    <div id="modal-checkout" class="modal">
        <div class="modal-content">
            <h2 style="text-align:center;">Finalizar Pedido</h2>
            <div id="lista-itens-carrinho" style="margin-bottom:15px; border-bottom:1px solid #eee; max-height: 150px; overflow-y: auto;"></div>
            
            <input type="text" id="cli-nome" class="input-checkout" placeholder="Seu Nome">
            <input type="text" id="cli-cpf" class="input-checkout" placeholder="CPF (apenas números)">
            <input type="text" id="cli-telefone" class="input-checkout" placeholder="WhatsApp">
            
            <label>Entrega:</label>
            <select id="cli-entrega" class="input-checkout" onchange="document.getElementById('box-endereco').style.display=(this.value==='entrega'?'block':'none');">
                <option value="entrega">Entrega</option>
                <option value="retirada">Retirar na Loja</option>
            </select>

            <div id="box-endereco">
                <input type="text" id="cli-endereco" class="input-checkout" placeholder="Rua e Número">
<select id="cli-bairro" class="input-checkout" onchange="atualizarTotalFinal()">
    <option value="">Selecione o Bairro</option>
    <?php foreach($bairros_entrega as $b): ?>
        <option value="<?= htmlspecialchars($b['nome_bairro']) ?>" data-taxa="<?= $b['valor_taxa'] ?>">
            <?= htmlspecialchars($b['nome_bairro']) ?> (Taxa: R$ <?= number_format($b['valor_taxa'], 2, ',', '.') ?>)
        </option>
    <?php endforeach; ?>
</select>
            </div>

<label>Forma de Pagamento:</label>
<select id="cli-pagamento" class="input-checkout" onchange="verificarTroco()">
    <option value="">Selecione...</option>
    
    <?php if(($empresa['aceita_dinheiro'] ?? 'N') == 'S'): ?>
        <option value="1">💵 Dinheiro</option>
    <?php endif; ?>
    
    <?php if(($empresa['aceita_cartao_debito'] ?? 'N') == 'S'): ?>
        <option value="2">💳 Cartão de Débito</option>
    <?php endif; ?>
    
    <?php if(($empresa['aceita_cartao_credito'] ?? 'N') == 'S'): ?>
        <option value="3">💳 Cartão de Crédito</option>
    <?php endif; ?>
    
    <?php if(($empresa['aceita_pix'] ?? 'N') == 'S'): ?>
        <option value="4">💎 PIX</option>
    <?php endif; ?>
</select>

            <div id="box-troco" style="display:none;">
                <input type="number" id="cli-troco" class="input-checkout" placeholder="Troco para quanto?">
            </div>

            <button onclick="enviarPedido()" id="btn-finalizar" style="width:100%; background:#28a745; color:white; padding:15px; border:none; border-radius:5px; font-weight:bold; cursor:pointer; margin-top:10px;">Confirmar Pedido</button>
            <button onclick="document.getElementById('modal-checkout').style.display='none'" style="width:100%; background:#666; color:white; padding:10px; border:none; border-radius:5px; margin-top:10px; cursor:pointer;">Voltar</button>
        </div>
    </div>

    <script>
        const configLoja = {
            tipoTaxa: "<?= $empresa['taxa_entrega_tipo'] ?? 'fixa' ?>",
            valorTaxaFixa: <?= (float)($empresa['taxa_entrega_valor'] ?? 0) ?>,
            bairrosEntrega: <?= json_encode($bairros_entrega) ?>
        };
    </script>
    <script src="js/carrinho.js"></script>
</body>
</html>