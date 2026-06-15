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
    $stmt = $pdo->prepare("DELETE FROM taxas_bairros WHERE id = ? AND empresa_id = ?");
    $stmt->execute([(int)$_GET['excluir_bairro'], $empresa_id]);
    header("Location: $self?aba=entrega"); exit;
}

if (isset($_POST['salvar_horarios'])) {
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

$stmtBairros = $pdo->prepare("SELECT * FROM taxas_bairros WHERE empresa_id = ? ORDER BY nome_bairro ASC");
$stmtBairros->execute([$empresa_id]);
$bairros = $stmtBairros->fetchAll();

$stmtHorarios = $pdo->prepare("SELECT * FROM horarios_funcionamento WHERE empresa_id = ?");
$stmtHorarios->execute([$empresa_id]);
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
        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 30px auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        
        /* Header */
        .header-config { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f3f5; margin-bottom: 25px; padding-bottom: 15px; }
        .header-config h2 { margin: 0; font-size: 1.5rem; color: #212529; }
        .btn-voltar { text-decoration: none; background: #6c757d; color: white; padding: 8px 16px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; transition: background 0.2s; }
        .btn-voltar:hover { background: #5a6268; }
        
        /* Alertas */
        .alert-sucesso { background: #d1e7dd; color: #0f5132; padding: 15px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #badbcc; font-weight: 500; }
        
        /* Abas Dinâmicas */
        .tabs { display: flex; gap: 4px; margin-bottom: 0px; background: #f8f9fa; padding: 6px 6px 0 6px; border-radius: 10px 10px 0 0; border: 1px solid #e9ecef; border-bottom: none; }
        .tab-btn { flex: 1; padding: 12px 15px; cursor: pointer; border: 1px solid transparent; background: transparent; font-weight: 600; border-radius: 8px 8px 0 0; color: #6c757d; transition: all 0.2s ease; font-size: 0.9rem; }
        .tab-btn:hover { background: #e9ecef; color: #495057; }
        .tab-btn.active { background: white; color: #007bff; border: 1px solid #e9ecef; border-bottom: 1px solid white; position: relative; z-index: 2; box-shadow: 0 -2px 6px rgba(0,0,0,0.02); }
        
        /* Conteúdo das Abas */
        .tab-content { display: none; background: white; padding: 30px; border: 1px solid #e9ecef; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .tab-content.active { display: block; }
        .tab-content h3 { margin-top: 0; margin-bottom: 20px; font-size: 1.2rem; color: #495057; border-left: 4px solid #007bff; padding-left: 10px; }
        
        /* Formulários */
        .form-row { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 220px; display: flex; flex-direction: column; }
        .form-group label { font-size: 0.85rem; font-weight: 600; color: #495057; margin-bottom: 6px; }
        
        input[type="text"], input[type="number"], input[type="time"], select { 
            padding: 10px 14px; border: 1px solid #ced4da; border-radius: 6px; font-size: 0.95rem; color: #495057; 
            background-color: #fff; transition: border-color 0.2s, box-shadow 0.2s; font-family: inherit;
        }
        input:focus, select:focus { border-color: #86b7fe; outline: 0; box-shadow: 0 0 0 0.25rem rgba(13,110,253,.15); }
        input[type="color"] { border: 1px solid #ced4da; border-radius: 6px; cursor: pointer; width: 100%; box-sizing: border-box; background: none; padding: 2px; }
        
        /* Botões de Ação */
        .btn-save { background: #28a745; color: white; border: none; padding: 14px; cursor: pointer; border-radius: 6px; font-weight: 600; width: 100%; margin-top: 15px; font-size: 1rem; transition: background 0.2s, transform 0.1s; }
        .btn-save:hover { background: #218838; }
        .btn-save:active { transform: scale(0.99); }
        
        .btn-add { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: background 0.2s; font-size: 0.95rem; }
        .btn-add:hover { background: #0056b3; }

        /* Grid de Pagamentos */
        .pg-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 15px; background: #f8f9fa; padding: 20px; border-radius: 10px; border: 1px solid #e9ecef; margin: 15px 0 25px 0; }
        .pg-item { display: flex; flex-direction: column; gap: 6px; }
        .pg-item label { font-size: 0.85rem; font-weight: 600; color: #495057; }
        
        /* Tabelas Modernizadas */
        .table-modern { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.95rem; }
        .table-modern th { background: #f8f9fa; color: #495057; font-weight: 600; text-align: left; padding: 12px 16px; border-bottom: 2px solid #dee2e6; }
        .table-modern td { padding: 12px 16px; border-bottom: 1px solid #efefef; color: #495057; vertical-align: middle; }
        .table-modern tr:hover { background-color: #fdfdfd; }
        
        /* Customizações Extras */
        .box-entrega-bairro { background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e9ecef; align-items: flex-end; }
        .action-delete { color: #dc3545; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: color 0.2s; }
        .action-delete:hover { color: #bd2130; }
        .checkbox-row { display: flex; align-items: center; gap: 8px; font-weight: 500; color: #495057; }
        .checkbox-row input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; }
        
        /* Efeito transição suave da opacidade da taxa fixa */
        #box_fixa { transition: opacity 0.25s ease, transform 0.25s ease; }
    </style>
</head>
<body>

<div class="container">
    
    <div class="header-config">
        <h2>⚙️ Configuração do Cardápio Online</h2>
        <a href="dashboard.php" class="btn-voltar">⬅ Voltar ao Painel</a>
    </div>

    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert-sucesso">
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
                    <input type="text" name="whats_contato" value="<?= htmlspecialchars($empresa['whats_contato'] ?? '') ?>" placeholder="Ex: 46991032063">
                </div>
                <div class="form-group">
                    <label>Instagram (@loja)</label>
                    <input type="text" name="instagram_loja" value="<?= htmlspecialchars($empresa['instagram_loja'] ?? '') ?>" placeholder="@sua_loja">
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
                    <input type="color" name="cor_tema" value="<?= htmlspecialchars($empresa['cor_tema'] ?? '#007bff') ?>" style="height: 42px;">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Pedido Mínimo (R$)</label>
                    <input type="number" step="0.01" name="valor_minimo_pedido" value="<?= htmlspecialchars($empresa['valor_minimo_pedido'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Chave PIX</label>
                    <input type="text" name="pix_chave" value="<?= htmlspecialchars($empresa['pix_chave'] ?? '') ?>">
                </div>
            </div>

            <hr style="margin: 25px 0; border: 0; border-top: 1px solid #e9ecef;">
            <h3 style="margin-bottom: 5px;">💳 Formas de Pagamento Aceitas</h3>
            <p style="font-size: 13px; color: #6c757d; margin-bottom: 10px;">Selecione o que o cliente poderá escolher ao finalizar o pedido.</p>
            
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

            <hr style="margin: 25px 0; border: 0; border-top: 1px solid #e9ecef;">
            
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
                    <input type="number" step="0.01" name="taxa_entrega_valor" value="<?= htmlspecialchars($empresa['taxa_entrega_valor'] ?? '') ?>">
                </div>
            </div>
            
            <button type="submit" name="salvar_geral" class="btn-save">💾 Salvar Configurações Gerais</button>
        </form>
    </div>

    <div id="entrega" class="tab-content">
        <h3>Cadastrar Taxas por Bairro</h3>
        <form method="POST" class="form-row box-entrega-bairro">
            <div style="flex: 2; display: flex; flex-direction: column; gap: 5px;">
                <label style="font-size: 0.85rem; font-weight: 600; color: #495057;">Nome do Bairro</label>
                <input type="text" name="nome_bairro" placeholder="Ex: Centro" required>
            </div>
            <div style="flex: 1; display: flex; flex-direction: column; gap: 5px;">
                <label style="font-size: 0.85rem; font-weight: 600; color: #495057;">Valor da Taxa</label>
                <input type="number" step="0.01" name="valor_taxa" placeholder="R$ 0,00" required>
            </div>
            <button type="submit" name="add_bairro" class="btn-add" style="height: 42px;">+ Adicionar</button>
        </form>

        <table class="table-modern">
            <thead>
                <tr>
                    <th>Bairro</th>
                    <th>Taxa</th>
                    <th style="text-align: center; width: 120px;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($bairros)): ?>
                    <tr><td colspan="3" style="text-align: center; color: #6c757d;">Nenhum bairro cadastrado.</td></tr>
                <?php endif; ?>
                <?php foreach($bairros as $b): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($b['nome_bairro']) ?></strong></td>
                    <td>R$ <?= number_format($b['valor_taxa'], 2, ',', '.') ?></td>
                    <td align="center">
                        <a href="?excluir_bairro=<?= $b['id'] ?>" onclick="return confirm('Excluir este bairro?')" class="action-delete">❌ Remover</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="horarios" class="tab-content">
        <form method="POST">
            <h3>Grade de Horários Funcionamento</h3>
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Dia da Semana</th>
                        <th>Status</th>
                        <th>Horário de Abertura</th>
                        <th style="width: 40px; text-align: center;">-</th>
                        <th>Horário de Fechamento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($dias_semana as $id => $nome): 
                        $h_abertura = $horarios_db[$id]['abertura'] ?? '18:00';
                        $h_fechamento = $horarios_db[$id]['fechamento'] ?? '23:00';
                        $aberto = ($horarios_db[$id]['situacao'] ?? 'aberto') == 'aberto';
                    ?>
                    <tr>
                        <td style="width: 180px;"><strong><?= $nome ?></strong></td>
                        <td style="width: 140px;">
                            <label class="checkbox-row">
                                <input type="checkbox" name="dia[<?= $id ?>][situacao]" <?= $aberto ? 'checked' : '' ?>> 
                                <span>Aberto</span>
                            </label>
                        </td>
                        <td><input type="time" name="dia[<?= $id ?>][abertura]" value="<?= $h_abertura ?>" style="padding: 6px 10px;"></td>
                        <td align="center" style="color: #6c757d; font-weight: 600;">às</td>
                        <td><input type="time" name="dia[<?= $id ?>][fechamento]" value="<?= $h_fechamento ?>" style="padding: 6px 10px;"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
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
    let box = document.getElementById('box_fixa');
    if (tipo === 'fixa') {
        box.style.opacity = '1';
        box.style.pointerEvents = 'auto';
    } else {
        box.style.opacity = '0.3';
        box.style.pointerEvents = 'none'; // Desabilita interação visualmente
    }
}

// Inicializa o estado do campo de taxa fixa ao carregar a página
document.addEventListener("DOMContentLoaded", function() {
    toggleTaxa();
});

const urlParams = new URLSearchParams(window.location.search);
const aba = urlParams.get('aba');
if (aba) {
    const activeTab = document.querySelector(`[onclick*="${aba}"]`);
    if (activeTab) activeTab.click();
}
</script>

</body>
</html>
