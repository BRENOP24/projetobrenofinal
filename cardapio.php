<?php
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// Configurações Iniciais
date_default_timezone_set('America/Sao_Paulo');
$agora = new DateTime();
$hora_atual = $agora->format('H:i:s');
$dia_hoje = date('w'); // 0 (domingo) a 6 (sábado)

// 1. Busca dados da Empresa
$empresa = $pdo->query("SELECT * FROM empresas LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// 2. Busca Horário de Funcionamento para Hoje
$stmtH = $pdo->prepare("SELECT * FROM horarios_funcionamento WHERE empresa_id = ? AND dia_semana = ?");
$stmtH->execute([$empresa['id'], $dia_hoje]);
$horario_hoje = $stmtH->fetch(PDO::FETCH_ASSOC);

// 3. Verifica se a loja está aberta (Lógica de Horários Otimizada)
$aberta = false;
if (isset($empresa['status_loja']) && (int)$empresa['status_loja'] === 1) {
    if ($horario_hoje && isset($horario_hoje['situacao']) && strtolower($horario_hoje['situacao']) === 'aberto') {
        
        // Garante a formatação correta H:i:s para comparação strings estáveis
        $inicio = date('H:i:s', strtotime($horario_hoje['abertura']));
        $fim = date('H:i:s', strtotime($horario_hoje['fechamento']));
        
        // Se a hora de fechamento for menor que a de abertura, significa que avança pela madrugada (ex: 18:00 às 02:00)
        if ($fim < $inicio) {
            if ($hora_atual >= $inicio || $hora_atual <= $fim) {
                $aberta = true;
            }
        } else {
            // Horário comercial padrão (ex: 08:00 às 18:00)
            if ($hora_atual >= $inicio && $hora_atual <= $fim) {
                $aberta = true;
            }
        }
    }
}

// 4. Monta lista de pagamentos
$pagamentos = [];
$campos_pg = [
    'aceita_dinheiro' => 'Dinheiro', 'aceita_pix' => 'PIX', 
    'aceita_cartao_debito' => 'Cartão de Débito', 'aceita_cartao_credito' => 'Cartão de Crédito',
    'aceita_alimentacao' => 'Vale Alimentação', 'aceita_refeicao' => 'Vale Refeição'
];
foreach($campos_pg as $campo => $nome) {
    if (($empresa[$campo] ?? 'N') == 'S') $pagamentos[] = $nome;
}

// 5. Busca Produtos e organiza por Categoria
$produtos = $pdo->query("SELECT p.*, c.nome as nome_categoria 
                         FROM produtos p 
                         LEFT JOIN categorias c ON p.categoria_id = c.id 
                         WHERE p.aparecer_online = 'S' AND p.status = 'Ativo' 
                         ORDER BY c.nome ASC, p.nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$categorias = [];
foreach ($produtos as $p) {
    $categorias[$p['nome_categoria'] ?? 'Outros'][] = $p;
}

// 6. Busca Bairros para o JS (importante para o carrinho)
$bairros = $pdo->query("SELECT * FROM taxas_bairros ORDER BY nome_bairro ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cardápio Online - <?= htmlspecialchars($empresa['nome_fantasia'] ?? 'Loja') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --cor-tema: <?= $empresa['cor_tema'] ?? '#0056b3' ?>; }
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
        .header { background: var(--cor-tema); color: white; text-align: center; padding: 40px 20px; }
        .status-badge { background: #28a745; color: white; padding: 5px 15px; border-radius: 20px; font-size: 14px; font-weight: bold; }
        .status-badge.fechado { background: #dc3545; }
        .container { max-width: 1000px; margin: -50px auto 50px; padding: 0 15px; }
        .info-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .info-item { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; color: #555; font-size: 14px; }
        .info-item i { color: var(--cor-tema); width: 20px; }
        .categoria-secao { margin-bottom: 40px; }
        .categoria-titulo { font-size: 22px; color: #333; border-left: 5px solid var(--cor-tema); padding-left: 15px; margin-bottom: 20px; }
        .produtos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .produto-card { background: white; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: transform 0.2s; }
        .produto-card:hover { transform: translateY(-5px); }
        .produto-img { width: 100%; height: 160px; object-fit: cover; background: #eee; border: none; }
        .produto-corpo { padding: 15px; flex-grow: 1; }
        .produto-nome { font-weight: bold; font-size: 16px; margin-bottom: 5px; color: #333; }
        .produto-preco { color: #28a745; font-weight: bold; font-size: 18px; }
        .btn-add { background: var(--cor-tema); color: white; border: none; padding: 12px; width: 100%; cursor: pointer; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.3s; }
        .btn-add:disabled { background: #ccc !important; cursor: not-allowed; }
    </style>
</head>
<body>

<div class="header">
    <h1><?= htmlspecialchars($empresa['nome_fantasia'] ?? 'SAY NOW') ?></h1>
    <span class="status-badge <?= $aberta ? '' : 'fechado' ?>">
        <?= $aberta ? '● Aberto agora' : '● Fechado' ?>
    </span>
    <p>Pedido mínimo: R$ <?= number_format($empresa['valor_minimo_pedido'] ?? 0, 2, ',', '.') ?></p>
</div>

<div class="container">
    <div class="info-card">
        <div class="info-item">
            <i class="fas fa-truck"></i> 
            Entrega: <?= ($empresa['taxa_entrega_tipo'] ?? 'fixa') == 'fixa' ? 'Taxa Fixa R$ '.number_format($empresa['taxa_entrega_valor'] ?? 0, 2, ',', '.') : 'Taxa por Bairro' ?>
        </div>
        <div class="info-item">
            <i class="fas fa-clock"></i> 
            Hoje: <?= ($horario_hoje['situacao'] ?? '') == 'aberto' ? substr($horario_hoje['abertura'],0,5).' às '.substr($horario_hoje['fechamento'],0,5) : 'Fechado hoje' ?>
        </div>
        <div class="info-item">
            <i class="fas fa-credit-card"></i> 
            Aceitamos: <?= !empty($pagamentos) ? implode(", ", $pagamentos) : "Consulte-nos" ?>
        </div>
    </div>

    <?php foreach ($categorias as $nome_cat => $itens): ?>
        <div class="categoria-secao">
            <h2 class="categoria-titulo"><?= htmlspecialchars($nome_cat) ?></h2>
            <div class="produtos-grid">
                <?php foreach ($itens as $p): ?>
                    <div class="produto-card">
                        <?php if(!empty($p['imagem'])): ?>
                            <img src="uploads/produtos/<?= $p['imagem'] ?>" class="produto-img">
                        <?php else: ?>
                            <div class="produto-img" style="display:flex; align-items:center; justify-content:center; background:#f9f9f9; color:#ccc;">Sem Foto</div>
                        <?php endif; ?>

                        <div class="produto-corpo">
                            <div class="produto-nome"><?= htmlspecialchars($p['nome']) ?></div>
                            <?php if(!empty($p['obs_online'])): ?>
                                <small style="color:#777; display:block; margin-bottom:8px; line-height:1.2;"><?= htmlspecialchars($p['obs_online']) ?></small>
                            <?php endif; ?>
                            <div class="produto-preco">R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?></div>
                        </div>

                        <!-- Data Attributes puros nos botões (Evita quebra de aspas) -->
                        <button class="btn-add btn-disparar-carrinho" 
                                data-id="<?= $p['id'] ?? '' ?>"
                                data-nome="<?= htmlspecialchars($p['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-preco="<?= $p['preco_venda'] ?? '0.00' ?>"
                                data-imagem="<?= $p['imagem'] ?? '' ?>"
                                data-categoria="<?= htmlspecialchars($p['nome_categoria'] ?? 'Outros', ENT_QUOTES, 'UTF-8') ?>"
                                <?= !$aberta ? 'disabled' : '' ?>>
                            <i class="fas fa-plus-circle"></i> 
                            <?= $aberta ? 'Adicionar' : 'Loja Fechada' ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    const configLoja = {
        tipoTaxa: "<?= $empresa['taxa_entrega_tipo'] ?? 'fixa' ?>",
        valorTaxaFixa: <?= (float)($empresa['taxa_entrega_valor'] ?? 0) ?>,
        bairrosEntrega: <?= json_encode($bairros ?? []) ?> 
    };

    // Escuta dinamicamente o clique nos botões assim que a página carrega
    document.addEventListener("DOMContentLoaded", function() {
        const botoes = document.querySelectorAll(".btn-disparar-carrinho");
        
        botoes.forEach(botao => {
            botao.addEventListener("click", function() {
                // Monta o objeto mapeando propriedades redundantes para garantir recepção correta no js/carrinho.js
                const produtoObjeto = {
                    id: this.getAttribute("data-id"),
                    nome: this.getAttribute("data-nome"),
                    preco: parseFloat(this.getAttribute("data-preco")),
                    preco_venda: parseFloat(this.getAttribute("data-preco")),
                    imagem: this.getAttribute("data-imagem"),
                    categoria: this.getAttribute("data-categoria"),
                    nome_categoria: this.getAttribute("data-categoria")
                };

                // Executa a função do seu arquivo carrinho.js
                if (typeof adicionarAoCarrinho === 'function') {
                    adicionarAoCarrinho(produtoObjeto);
                } else {
                    console.error("Erro: A função 'adicionarAoCarrinho' não existe ou o arquivo 'js/carrinho.js' falhou ao carregar.");
                }
            });
        });
    });
</script>
<script src="js/carrinho.js"></script>

</body>
</html>