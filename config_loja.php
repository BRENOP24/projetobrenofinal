<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$self = basename(__FILE__); 

// Busca os dados da empresa
$empresa = $pdo->query("SELECT * FROM empresas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$empresa) {
    die("Erro: Nenhuma empresa cadastrada no banco de dados.");
}
$empresa_id = $empresa['id'];

// --- PROCESSAMENTO ---

if (isset($_POST['salvar_geral'])) {
    // No PostgreSQL, garantimos que campos de "aceite" sejam booleanos ou inteiros (0 ou 1)
    // Se o checkbox não for marcado, atribuímos 0
    $aceita_dinheiro = $_POST['aceita_dinheiro'] ?? 0;
    $aceita_pix = $_POST['aceita_pix'] ?? 0;
    $aceita_debito = $_POST['aceita_cartao_debito'] ?? 0;
    $aceita_credito = $_POST['aceita_cartao_credito'] ?? 0;
    $aceita_alimentacao = $_POST['aceita_alimentacao'] ?? 0;
    $aceita_refeicao = $_POST['aceita_refeicao'] ?? 0;

    $sql = "UPDATE empresas SET 
            status_loja = ?, valor_minimo_pedido = ?, cor_tema = ?, pix_chave = ?,
            whats_contato = ?, instagram_loja = ?,
            taxa_entrega_tipo = ?, taxa_entrega_valor = ?,
            aceita_dinheiro = ?, aceita_pix = ?, aceita_cartao_debito = ?, 
            aceita_cartao_credito = ?, aceita_alimentacao = ?, aceita_refeicao = ?
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['status_loja'], $_POST['valor_minimo_pedido'], $_POST['cor_tema'], $_POST['pix_chave'],
        $_POST['whats_contato'], $_POST['instagram_loja'],
        $_POST['taxa_entrega_tipo'], $_POST['taxa_entrega_valor'],
        $aceita_dinheiro, $aceita_pix, $aceita_debito,
        $aceita_credito, $aceita_alimentacao, $aceita_refeicao,
        $empresa_id
    ]);
    header("Location: $self?sucesso=1"); exit;
}

if (isset($_POST['add_bairro'])) {
    $stmt = $pdo->prepare("INSERT INTO taxas_bairros (empresa_id, nome_bairro, valor_taxa) VALUES (?, ?, ?)");
    $stmt->execute([$empresa_id, $_POST['nome_bairro'], $_POST['valor_taxa']]);
    header("Location: $self?aba=entrega"); exit;
}

if (isset($_GET['excluir_bairro'])) {
    // No PostgreSQL, usamos parâmetros posicionais (?) para segurança contra SQL Injection
    $stmt = $pdo->prepare("DELETE FROM taxas_bairros WHERE id = ? AND empresa_id = ?");
    $stmt->execute([(int)$_GET['excluir_bairro'], $empresa_id]);
    header("Location: $self?aba=entrega"); exit;
}

if (isset($_POST['salvar_horarios'])) {
    // Transação para garantir que não apague os horários e falhe ao inserir novos
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM horarios_funcionamento WHERE empresa_id = ?")->execute([$empresa_id]);
        
        if (isset($_POST['dia'])) {
            foreach ($_POST['dia'] as $dia_index => $dados) {
                $situacao = isset($dados['situacao']) ? 'aberto' : 'fechado';
                $stmt = $pdo->prepare("INSERT INTO horarios_funcionamento (empresa_id, dia_semana, abertura, fechamento, situacao) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$empresa_id, $dia_index, $dados['abertura'], $dados['fechamento'], $situacao]);
            }
        }
        $pdo->commit();
        header("Location: $self?aba=horarios&sucesso=1"); exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro ao salvar horários: " . $e->getMessage());
    }
}


// Adicionamos o ORDER BY nome_bairro ASC para organizar por ordem alfabética
$stmtBairros = $pdo->prepare("SELECT * FROM taxas_bairros WHERE empresa_id = ? ORDER BY nome_bairro ASC");
$stmtBairros->execute([$empresa_id]);
$bairros = $stmtBairros->fetchAll();

$stmtHorarios = $pdo->prepare("SELECT * FROM horarios_funcionamento WHERE empresa_id = ?");
$stmtHorarios->execute([$empresa_id]);
// FETCH_UNIQUE funciona bem se o dia_semana for a primeira coluna da query
$horarios_db = $stmtHorarios->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);

$dias_semana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Configurações da Loja</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .header-config { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header-config h2 { margin: 0; }
        .btn-voltar { text-decoration: none; background: #6c757d; color: white; padding: 8px 15px; border-radius: 4px; font-size: 14px; font-weight: bold; }
        .tabs { display: flex; gap: 5px; margin-bottom: 0px; background: #e9ecef; padding: 8px 8px 0 8px; border-radius: 8px 8px 0 0; border: 1px solid #ddd; }
        .tab-btn { flex: 1; padding: 12px; cursor: pointer; border: 1px solid transparent; background: #dee2e6; font-weight: bold; border-radius: 8px 8px 0 0; color: #495057; transition: 0.2s; }
        .tab-btn.active { background: white; color: #007bff; border: 1px solid #ddd; border-bottom: 1px solid white; position: relative; z-index: 2; }
        .tab-content { display: none; background: white; padding: 25px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .tab-content.active { display: block; }
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; display: flex; flex-direction: column; }
        input, select { padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-top: 5px; }
        .btn-save { background: #28a745; color: white; border: none; padding: 15px; cursor: pointer; border-radius: 4px; font-weight: bold; width: 100%; margin-top: 10px; font-size: 16px; }
        .btn-save:hover { background: #218838; }
        
        /* Grid para pagamentos */
        .pg-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eee; margin-top: 10px; }
        .pg-item { display: flex; flex-direction: column; }
        .pg-item label { font-size: 12px; font-weight: bold; color: #555; }
    </style>
</head>
<body>

<div class="container" style="max-width: 900px; margin: 30px auto;">
    
    <div class="header-config">
        <h2>⚙️ Configuração do Cardápio Online</h2>
        <a href="dashboard.php" class="btn-voltar">⬅ Voltar ao Painel</a>
    </div>

    <?php if (isset($_GET['sucesso'])): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
            ✅ Configurações salvas com sucesso!
        </div>
    <?php endif; ?>

    <div class="tabs">
        <button class="tab-btn active" onclick="openTab(event, 'geral')">1. Geral & Contato</button>
        <button class="tab-btn" onclick="openTab(event, 'entrega')">2. Taxas de Entrega</button>
        <button class="tab-btn" onclick="openTab(event, 'horarios')">3. Horários</button>
    </div>

    <div id="geral" class="tab-content active">
        <form method="POST">
            <h3>Informações Visíveis no Cardápio</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>WhatsApp de Pedidos</label>
                    <input type="text" name="whats_contato" value="<?= $empresa['whats_contato'] ?>" placeholder="Ex: 46991032063">
                </div>
                <div class="form-group">
                    <label>Instagram (@loja)</label>
                    <input type="text" name="instagram_loja" value="<?= $empresa['instagram_loja'] ?>" placeholder="@sua_loja">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Status da Loja</label>
                    <select name="status_loja">
                        <option value="1" <?= $empresa['status_loja'] == 1 ? 'selected' : '' ?>>Loja Aberta</option>
                        <option value="0" <?= $empresa['status_loja'] == 0 ? 'selected' : '' ?>>Loja Fechada</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Cor do Tema</label>
                    <input type="color" name="cor_tema" value="<?= $empresa['cor_tema'] ?>" style="height: 45px;">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Pedido Mínimo (R$)</label>
                    <input type="number" step="0.01" name="valor_minimo_pedido" value="<?= $empresa['valor_minimo_pedido'] ?>">
                </div>
                <div class="form-group">
                    <label>Chave PIX</label>
                    <input type="text" name="pix_chave" value="<?= $empresa['pix_chave'] ?>">
                </div>
            </div>

            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
            <h3 style="margin-bottom: 5px;">💳 Formas de Pagamento Aceitas</h3>
            <p style="font-size: 13px; color: #666; margin-bottom: 10px;">Selecione o que o cliente poderá escolher ao finalizar o pedido.</p>
            
            <div class="pg-grid">
                <div class="pg-item">
                    <label>💵 Dinheiro</label>
                    <select name="aceita_dinheiro">
                        <option value="S" <?= ($empresa['aceita_dinheiro'] ?? 'S') == 'S' ? 'selected' : '' ?>>Sim</option>
                        <option value="N" <?= ($empresa['aceita_dinheiro'] ?? 'S') == 'N' ? 'selected' : '' ?>>Não</option>
                    </select>
                </div>
                <div class="pg-item">
                    <label>💎 PIX</label>
                    <select name="aceita_pix">
                        <option value="S" <?= ($empresa['aceita_pix'] ?? 'S') == 'S' ? 'selected' : '' ?>>Sim</option>
                        <option value="N" <?= ($empresa['aceita_pix'] ?? 'S') == 'N' ? 'selected' : '' ?>>Não</option>
                    </select>
                </div>
                <div class="pg-item">
                    <label>💳 C. Débito</label>
                    <select name="aceita_cartao_debito">
                        <option value="S" <?= ($empresa['aceita_cartao_debito'] ?? 'S') == 'S' ? 'selected' : '' ?>>Sim</option>
                        <option value="N" <?= ($empresa['aceita_cartao_debito'] ?? 'S') == 'N' ? 'selected' : '' ?>>Não</option>
                    </select>
                </div>
                <div class="pg-item">
                    <label>💳 C. Crédito</label>
                    <select name="aceita_cartao_credito">
                        <option value="S" <?= ($empresa['aceita_cartao_credito'] ?? 'S') == 'S' ? 'selected' : '' ?>>Sim</option>
                        <option value="N" <?= ($empresa['aceita_cartao_credito'] ?? 'S') == 'N' ? 'selected' : '' ?>>Não</option>
                    </select>
                </div>
                <div class="pg-item">
                    <label>🍱 Alimentação</label>
                    <select name="aceita_alimentacao">
                        <option value="S" <?= ($empresa['aceita_alimentacao'] ?? 'N') == 'S' ? 'selected' : '' ?>>Sim</option>
                        <option value="N" <?= ($empresa['aceita_alimentacao'] ?? 'N') == 'N' ? 'selected' : '' ?>>Não</option>
                    </select>
                </div>
                <div class="pg-item">
                    <label>🍕 Refeição</label>
                    <select name="aceita_refeicao">
                        <option value="S" <?= ($empresa['aceita_refeicao'] ?? 'N') == 'S' ? 'selected' : '' ?>>Sim</option>
                        <option value="N" <?= ($empresa['aceita_refeicao'] ?? 'N') == 'N' ? 'selected' : '' ?>>Não</option>
                    </select>
                </div>
            </div>

            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
            
            <h3>Configuração de Entrega Principal</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Tipo de Cobrança</label>
                    <select name="taxa_entrega_tipo" id="taxa_tipo" onchange="toggleTaxa()">
                        <option value="fixa" <?= $empresa['taxa_entrega_tipo'] == 'fixa' ? 'selected' : '' ?>>Taxa Fixa</option>
                        <option value="bairro" <?= $empresa['taxa_entrega_tipo'] == 'bairro' ? 'selected' : '' ?>>Taxa por Bairro</option>
                    </select>
                </div>
                <div class="form-group" id="box_fixa">
                    <label>Valor da Taxa Fixa (R$)</label>
                    <input type="number" step="0.01" name="taxa_entrega_valor" value="<?= $empresa['taxa_entrega_valor'] ?>">
                </div>
            </div>
            
            <button type="submit" name="salvar_geral" class="btn-save">💾 Salvar Configurações Gerais</button>
        </form>
    </div>

    <div id="entrega" class="tab-content">
        <h3>Cadastrar Taxas por Bairro</h3>
        <form method="POST" class="form-row" style="background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
            <input type="text" name="nome_bairro" placeholder="Nome do Bairro" required style="flex: 2;">
            <input type="number" step="0.01" name="valor_taxa" placeholder="R$ 0,00" required style="flex: 1;">
            <button type="submit" name="add_bairro" style="flex: 1; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">+ Adicionar</button>
        </form>

        <table width="100%" style="margin-top: 20px; border-collapse: collapse;" border="1" cellpadding="10" bordercolor="#eee">
            <tr bgcolor="#f8f9fa"><th>Bairro</th><th>Taxa</th><th>Ação</th></tr>
            <?php foreach($bairros as $b): ?>
            <tr>
                <td><?= $b['nome_bairro'] ?></td>
                <td>R$ <?= number_format($b['valor_taxa'], 2, ',', '.') ?></td>
                <td align="center"><a href="?excluir_bairro=<?= $b['id'] ?>" onclick="return confirm('Excluir este bairro?')" style="color: red; text-decoration: none; font-weight: bold;">❌ Remover</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div id="horarios" class="tab-content">
        <form method="POST">
            <h3>Grade de Horários Funcionamento</h3>
            <table width="100%" cellpadding="10">
                <?php foreach($dias_semana as $id => $nome): 
                    $h_abertura = $horarios_db[$id]['abertura'] ?? '18:00';
                    $h_fechamento = $horarios_db[$id]['fechamento'] ?? '23:00';
                    $aberto = ($horarios_db[$id]['situacao'] ?? 'aberto') == 'aberto';
                ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td width="150"><strong><?= $nome ?></strong></td>
                    <td><input type="checkbox" name="dia[<?= $id ?>][situacao]" <?= $aberto ? 'checked' : '' ?>> Aberto</td>
                    <td><input type="time" name="dia[<?= $id ?>][abertura]" value="<?= $h_abertura ?>"></td>
                    <td>às</td>
                    <td><input type="time" name="dia[<?= $id ?>][fechamento]" value="<?= $h_fechamento ?>"></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <button type="submit" name="salvar_horarios" class="btn-save">💾 Salvar Horários de Funcionamento</button>
        </form>
    </div>
</div>

<script>
function openTab(evt, tabName) {
    let i, content, btns;
    content = document.getElementsByClassName("tab-content");
    for (i = 0; i < content.length; i++) content[i].style.display = "none";
    
    btns = document.getElementsByClassName("tab-btn");
    for (i = 0; i < btns.length; i++) btns[i].classList.remove("active");
    
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.classList.add("active");
}

function toggleTaxa() {
    let tipo = document.getElementById('taxa_tipo').value;
    document.getElementById('box_fixa').style.opacity = (tipo === 'fixa') ? '1' : '0.3';
}

const urlParams = new URLSearchParams(window.location.search);
const aba = urlParams.get('aba');
if (aba) {
    document.querySelector(`[onclick*="${aba}"]`).click();
}
</script>

</body>
</html>