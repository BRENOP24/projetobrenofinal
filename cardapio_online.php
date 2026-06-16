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
$stmtBairros = $pdo->prepare("
    SELECT nome_bairro, valor_taxa
    FROM taxas_bairros
    WHERE empresa_id = ?
    ORDER BY nome_bairro ASC
");
$stmtBairros->execute([$empresa_id]);
$bairros_entrega = $stmtBairros->fetchAll(PDO::FETCH_ASSOC);

$stmtHorarios = $pdo->prepare("
    SELECT *
    FROM horarios_funcionamento
    WHERE empresa_id = ?
    ORDER BY dia_semana ASC
");
$stmtHorarios->execute([$empresa_id]);
$horarios = $stmtHorarios->fetchAll(PDO::FETCH_ASSOC);

$dias_semana_nome = [
    'Domingo',
    'Segunda',
    'Terça',
    'Quarta',
    'Quinta',
    'Sexta',
    'Sábado'
];

/* ===============================
   3. PRODUTOS (Mantendo a estrutura)
================================ */
$sqlProdutos = "
    SELECT
        p.*,
        COALESCE(c.nome, 'Outros') AS nome_categoria
    FROM produtos p
    LEFT JOIN categorias c
        ON p.categoria_id = c.id
    WHERE p.status = 'Ativo'
    AND p.aparecer_online = 'S'
    ORDER BY nome_categoria ASC, p.nome ASC
";

$stmtProdutos = $pdo->query($sqlProdutos);
$produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-VCJ8JQBD87"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){ dataLayer.push(arguments); }
      gtag('js', new Date());
      gtag('config', 'G-VCJ8JQBD87');
    </script>
   
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
    <?= htmlspecialchars($empresa['nome_fantasia']) ?> - Cardápio Online
</title>

<style>

:root{
    --cor-primaria: <?= $cor_tema ?>;
}

body{
    font-family: Arial, sans-serif;
    margin:0;
    background:#f8f9fa;
    padding-bottom:100px;
}

.cabecalho{
    background: var(--cor-primaria);
    color:white;
    padding:20px;
    text-align:center;
    box-shadow:0 2px 5px rgba(0,0,0,.1);
    position: relative;
}

/* BOTÃO ACESSAR PAINEL */
.btn-painel-topo {
    display: inline-block;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    text-decoration: none;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
    border: 1px solid rgba(255, 255, 255, 0.4);
    margin-top: 12px;
    transition: background 0.2s;
}

.btn-painel-topo:hover {
    background: rgba(255, 255, 255, 0.35);
}

.status-loja{
    display:inline-block;
    padding:6px 14px;
    border-radius:20px;
    font-weight:bold;
    font-size:14px;
    margin-top:10px;
}

.aberta{
    background:#28a745;
}

.fechada{
    background:#dc3545;
}

.container{
    max-width:1200px;
    margin:0 auto;
    padding:15px;
}

/* INFO LOJA */
.info-loja{
    background:white;
    border-radius:10px;
    margin-bottom:25px;
    border:1px solid #eee;
    box-shadow:0 2px 4px rgba(0,0,0,.05);
}

.info-loja summary{
    padding:15px;
    font-weight:bold;
    cursor:pointer;
}

.info-loja-conteudo{
    padding:15px;
    border-top:1px solid #eee;
}

.info-item{
    margin-bottom:20px;
}

.badge-pgto{
    display:inline-block;
    background:#f1f1f1;
    padding:5px 10px;
    border-radius:20px;
    margin:3px;
    font-size:13px;
}

.horarios-lista{
    list-style:none;
    padding:0;
    margin:10px 0 0;
}

.horarios-lista li{
    display:flex;
    justify-content:space-between;
    padding:5px 0;
    border-bottom:1px dashed #eee;
}

/* TITULO CATEGORIA */
.categoria-titulo{
    font-size:24px;
    margin:35px 0 20px;
    color:#333;
    border-bottom:3px solid var(--cor-primaria);
    padding-bottom:8px;
}

/* GRID PRODUTOS */
.produtos-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));
    gap:18px;
}

/* CARD */
.produto-card{
    background:white;
    border-radius:14px;
    overflow:hidden;
    border:1px solid #eee;
    box-shadow:0 2px 6px rgba(0,0,0,.06);
    display:flex;
    flex-direction:column;
    transition:.2s;
}

.produto-card:hover{
    transform:translateY(-3px);
}

.produto-img{
    width:100%;
    height:180px;
    object-fit:cover;
}

.produto-sem-foto{
    width:100%;
    height:180px;
    background:#eee;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#999;
}

.produto-info{
    padding:15px;
    display:flex;
    flex-direction:column;
    flex:1;
}

.produto-nome{
    font-size:17px;
    font-weight:bold;
    margin:0 0 10px;
    color:#333;
}

.produto-obs{
    font-size:13px;
    color:#666;
    line-height:1.5;
    margin-bottom:15px;
    flex:1;
}

.produto-rodape{
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.produto-preco{
    font-size:18px;
    font-weight:bold;
    color:#28a745;
}

.btn-add{
    background:var(--cor-primaria);
    color:white;
    border:none;
    padding:10px 15px;
    border-radius:8px;
    cursor:pointer;
    font-weight:bold;
}

.btn-add:disabled{
    background:#ccc;
    cursor:not-allowed;
}

/* MODAL */
.modal{
    display:none;
    position:fixed;
    z-index:2000;
    left:0;
    top:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,.7);
    overflow-y:auto;
}

.modal-content{
    background:white;
    margin:5% auto;
    padding:20px;
    width:90%;
    max-width:500px;
    border-radius:10px;
}

.input-checkout{
    width:100%;
    padding:12px;
    margin:8px 0;
    border:1px solid #ccc;
    border-radius:5px;
    box-sizing:border-box;
    font-size:16px;
}

/* ALERTA DE SUCESSO PÓS-PEDIDO */
.alerta-sucesso-pedido {
    display: none;
    background: #e6f4ea;
    border: 1px solid #137333;
    color: #137333;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 15px;
}

.alerta-sucesso-pedido h3 {
    margin-top: 0;
    color: #137333;
}

.btn-alerta-login {
    display: inline-block;
    background: var(--cor-primaria);
    color: white;
    text-decoration: none;
    padding: 12px 20px;
    border-radius: 6px;
    font-weight: bold;
    margin-top: 15px;
}

/* CARRINHO */
#barra-carrinho{
    position:fixed;
    bottom:0;
    left:0;
    width:100%;
    background:white;
    padding:15px;
    box-shadow:0 -2px 10px rgba(0,0,0,.1);
    display:none;
    justify-content:center;
    box-sizing:border-box;
    z-index:1000;
}

.btn-ver-carrinho{
    background:var(--cor-primaria);
    color:white;
    border:none;
    padding:15px;
    border-radius:10px;
    width:100%;
    max-width:700px;
    font-size:16px;
    font-weight:bold;
    cursor:pointer;
    display:flex;
    justify-content:space-between;
}

@media(max-width:768px){
    .container{
        padding:10px;
    }

    .produtos-grid{
        grid-template-columns:1fr 1fr;
        gap:12px;
    }

    .produto-img,
    .produto-sem-foto{
        height:140px;
    }

    .produto-preco{
        font-size:15px;
    }

    .btn-add{
        padding:8px 10px;
        font-size:13px;
    }
}

@media(max-width:500px){
    .produtos-grid{
        grid-template-columns:1fr;
    }
    .cabecalho {
        padding: 15px 10px;
    }
    .btn-painel-topo {
        display: block;
        margin: 12px auto 0;
        width: fit-content;
    }
}

</style>
</head>

<body>

<header class="cabecalho">
   
    <h1>
        <?= htmlspecialchars($empresa['nome_fantasia']) ?>
    </h1>

    <span class="status-loja <?= $loja_aberta ? 'aberta' : 'fechada' ?>">
        <?= $loja_aberta ? '🟢 Aberto para Pedidos' : '🔴 Fechado no momento' ?>
    </span>

    <br>
    <a href="cliente_online.php" class="btn-painel-topo">
        👤 Acessar Meu Painel
    </a>

</header>

<main class="container">

    <details class="info-loja">
        <summary>
            ℹ️ Ver Informações do Estabelecimento
        </summary>

        <div class="info-loja-conteudo">
            <div class="info-item">
                <strong>📍 Endereço:</strong><br>
                <?= htmlspecialchars($empresa['endereco'] . ', ' . $empresa['numero']) ?>
                -
                <?= htmlspecialchars($empresa['bairro']) ?><br>
                <?= htmlspecialchars($empresa['cidade'] . '/' . $empresa['estado']) ?>
            </div>

            <div class="info-item">
                <strong>🛵 Taxa de Entrega:</strong><br>
                Entrega a ser avaliada
            </div>

            <div class="info-item">
                <strong>💳 Formas de Pagamento:</strong><br>
                <?php if(($empresa['aceita_dinheiro'] ?? 'N') == 'S'): ?>
                    <span class="badge-pgto">💵 Dinheiro</span>
                <?php endif; ?>

                <?php if(($empresa['aceita_pix'] ?? 'N') == 'S'): ?>
                    <span class="badge-pgto">💎 PIX</span>
                <?php endif; ?>

                <?php if(($empresa['aceita_cartao_debito'] ?? 'N') == 'S'): ?>
                    <span class="badge-pgto">💳 Débito</span>
                <?php endif; ?>

                <?php if(($empresa['aceita_cartao_credito'] ?? 'N') == 'S'): ?>
                    <span class="badge-pgto">💳 Crédito</span>
                <?php endif; ?>
            </div>

            <div class="info-item">
                <strong>🕒 Horários:</strong>
                <ul class="horarios-lista">
                    <?php foreach($horarios as $h): ?>
                        <li>
                            <span>
                                <?= $dias_semana_nome[$h['dia_semana']] ?? 'Dia' ?>
                            </span>

                            <?php if($h['situacao'] == 'aberto'): ?>
                                <span>
                                    <?= date('H:i', strtotime($h['abertura'])) ?>
                                    às
                                    <?= date('H:i', strtotime($h['fechamento'])) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#dc3545;font-weight:bold;">
                                    Fechado
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </details>

    <?php
    $ultima_cat = "";

    foreach ($produtos as $p):
        if ($p['nome_categoria'] !== $ultima_cat):
            if ($ultima_cat !== "") {
                echo "</div>";
            }
            $ultima_cat = $p['nome_categoria'];
    ?>
        <h2 class="categoria-titulo">
            <?= htmlspecialchars($ultima_cat) ?>
        </h2>
        <div class="produtos-grid">
    <?php endif; ?>

        <div class="produto-card">
            <?php if(!empty($p['imagem'])): ?>
                <?php 
                    $src_imagem = (strpos($p['imagem'], 'http') === 0) ? $p['imagem'] : 'uploads/produtos/' . $p['imagem'];
                ?>
                <img
                    src="<?= $src_imagem ?>"
                    class="produto-img"
                >
            <?php else: ?>
                <div class="produto-sem-foto">
                    Sem Foto
                </div>
            <?php endif; ?>

            <div class="produto-info">
                <div>
                    <h3 class="produto-nome">
                        <?= htmlspecialchars($p['nome']) ?>
                    </h3>

                    <?php if(!empty($p['obs_online'])): ?>
                        <p class="produto-obs">
                            <?= htmlspecialchars($p['obs_online']) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="produto-rodape">
                    <?php 
                        $preco_atual = (!empty($p['preco_online']) && $p['preco_online'] > 0) ? $p['preco_online'] : $p['preco_venda'];
                    ?>

                    <span class="produto-preco">
                        R$ <?= number_format($preco_atual, 2, ',', '.') ?>
                    </span>

                    <button
                        class="btn-add btn-adicionar-produto"
                        data-id="<?= $p['id'] ?>"
                        data-nome="<?= htmlspecialchars($p['nome']) ?>"
                        data-preco="<?= $preco_atual ?>" 
                        <?= !$loja_aberta ? 'disabled' : '' ?>
                    >
                        Adicionar
                    </button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</main>

<div id="barra-carrinho">
    <button
        class="btn-ver-carrinho"
        onclick="
            document.getElementById('modal-checkout').style.display='block';
            renderizarItensCarrinho();
            atualizarTotalFinal();
        "
    >
        <span id="carrinho-qtd">0 itens</span>
        <span>Ver Carrinho</span>
        <span id="carrinho-total">R$ 0,00</span>
    </button>
</div>

<div id="modal-checkout" class="modal">
    <div class="modal-content">
        
        <div id="alerta-sucesso-container" class="alerta-sucesso-pedido">
            <h3>🎉 Pedido Confirmado!</h3>
            <p>Faça login no seu perfil para acompanhar o andamento do seu pedido em tempo real.</p>
            <a href="cliente_online.php" class="btn-alerta-login">Fazer Login / Acompanhar</a>
        </div>

        <div id="form-checkout-container">
            <h2 style="text-align:center;">Finalizar Pedido</h2>

            <div
                id="lista-itens-carrinho"
                style="
                    margin-bottom:15px;
                    border-bottom:1px solid #eee;
                    max-height:150px;
                    overflow-y:auto;
                "
            ></div>

            <input type="text" id="cli-nome" class="input-checkout" placeholder="Seu Nome">
            <input type="text" id="cli-cpf" class="input-checkout" placeholder="CPF (somente os 11 números)" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
            <input type="text" id="cli-telefone" class="input-checkout" placeholder="WhatsApp (com DDD - somente números)" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>

            <label>Entrega:</label>
            <select
                id="cli-entrega"
                class="input-checkout"
                onchange="
                    document.getElementById('box-endereco').style.display = (this.value === 'entrega' ? 'block' : 'none');
                    atualizarTotalFinal();
                "
            >
                <option value="entrega">Entrega</option>
                <option value="retirada">Retirar na Loja</option>
            </select>

            <div id="box-endereco">
                <input type="text" id="cli-endereco" class="input-checkout" placeholder="Rua e Número">
                <select id="cli-bairro" class="input-checkout" onchange="atualizarTotalFinal()">
                    <option value="">Selecione o Bairro</option>
                    <?php foreach($bairros_entrega as $b): ?>
                        <option value="<?= htmlspecialchars($b['nome_bairro']) ?>" data-taxa="<?= $b['valor_taxa'] ?>">
                            <?= htmlspecialchars($b['nome_bairro']) ?> - Taxa: R$ <?= number_format($b['valor_taxa'], 2, ',', '.') ?>
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
                    <option value="2">💳 Débito</option>
                <?php endif; ?>
                <?php if(($empresa['aceita_cartao_credito'] ?? 'N') == 'S'): ?>
                    <option value="3">💳 Crédito</option>
                <?php endif; ?>
                <?php if(($empresa['aceita_pix'] ?? 'N') == 'S'): ?>
                    <option value="4">💎 PIX</option>
                <?php endif; ?>
            </select>

            <div id="box-troco" style="display:none;">
                <input type="number" id="cli-troco" class="input-checkout" placeholder="Troco para quanto?">
            </div>

            <button onclick="interceptarEnvioPedido(event)" id="btn-finalizar" style="width:100%; background:#28a745; color:white; padding:15px; border:none; border-radius:6px; font-weight:bold; cursor:pointer; margin-top:10px;">
                Confirmar Pedido
            </button>

            <button onclick="document.getElementById('modal-checkout').style.display='none'" style="width:100%; background:#666; color:white; padding:12px; border:none; border-radius:6px; margin-top:10px; cursor:pointer;">
                Voltar
            </button>
        </div>
    </div>
</div>

<script>
const configLoja = {
    tipoTaxa: "<?= $empresa['taxa_entrega_tipo'] ?? 'fixa' ?>",
    valorTaxaFixa: <?= (float)($empresa['taxa_entrega_valor'] ?? 0) ?>,
    bairrosEntrega: <?= json_encode($bairros_entrega) ?>
};

function interceptarEnvioPedido(event) {
    enviarPedido(event);
    
    setTimeout(() => {
        document.getElementById('form-checkout-container').style.display = 'none';
        document.getElementById('alerta-sucesso-container').style.display = 'block';
    }, 800);
}
</script>

<script src="js/carrinho.js"></script>

</body>
</html>
