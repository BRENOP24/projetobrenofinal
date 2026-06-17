<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

// Supõe-se que a função registrarLog exista no funcoes.php se quiser usar
// require_once 'config/funcoes.php'; 

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = $_SESSION['usuario_nome'];
$mensagem = "";

// 1. Verifica se existe um caixa aberto
$stmt = $pdo->prepare("SELECT * FROM controle_caixas WHERE usuario_id = ? AND status = 'aberto' LIMIT 1");
$stmt->execute([$usuario_id]);
$caixa_atual = $stmt->fetch(PDO::FETCH_ASSOC);

// Se o caixa estiver aberto, busca as sangrias já feitas neste turno para exibir na tela
$sangrias_turno = [];
if ($caixa_atual) {
    $stmt_sangrias = $pdo->prepare("SELECT * FROM sangrias WHERE caixa_id = ? ORDER BY data_hora DESC");
    $stmt_sangrias->execute([$caixa_atual['id']]);
    $sangrias_turno = $stmt_sangrias->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ABERTURA DE CAIXA
    if (isset($_POST['btn_abrir'])) {
        $valor_inicial = str_replace(',', '.', $_POST['valor_inicial']);
        try {
            $sql = "INSERT INTO controle_caixas (usuario_id, valor_inicial, status, data_abertura) 
                    VALUES (?, ?, 'aberto', CURRENT_TIMESTAMP)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario_id, $valor_inicial]);
            
            header("Location: caixas.php");
            exit;
        } catch (PDOException $e) {
            die("Erro ao abrir caixa: " . $e->getMessage());
        }
    }

    // NOVA SANGRIAS (RETIRADA DE VALOR)
    if (isset($_POST['btn_sangria']) && $caixa_atual) {
        $valor_sangria = (float)str_replace(',', '.', $_POST['valor_sangria']);
        $motivo = trim($_POST['motivo_sangria']);
        $observacao = trim($_POST['obs_sangria']);

        if ($valor_sangria > 0 && !empty($motivo)) {
            try {
                $sql = "INSERT INTO sangrias (caixa_id, valor, motivo, observacao) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$caixa_atual['id'], $valor_sangria, $motivo, $observacao]);
                
                header("Location: caixas.php?sucesso=sangria");
                exit;
            } catch (PDOException $e) {
                die("Erro ao registrar sangria: " . $e->getMessage());
            }
        }
    }

    // FECHAMENTO DE CAIXA
    if (isset($_POST['btn_fechar']) && $caixa_atual) {
        $contado = $_POST['contado']; 
        
        $valor_total_informado = array_sum(array_map(function($v) { 
            return (float)str_replace(',', '.', $v); 
        }, $contado));
        
        $detalhes_json = json_encode($contado);

        try {
            $stmt = $pdo->prepare("UPDATE controle_caixas SET 
                valor_final_informado = ?, 
                observacao_adm = ?, 
                data_fechamento = CURRENT_TIMESTAMP, 
                status = 'fechado' 
                WHERE id = ? AND status = 'aberto'");
            
            $stmt->execute([$valor_total_informado, $detalhes_json, $caixa_atual['id']]);
            
            header("Location: caixas.php?sucesso=fechado");
            exit;
        } catch (PDOException $e) {
            die("Erro ao fechar caixa: " . $e->getMessage());
        }
    }
}

// Mensagens de retorno na URL
if (isset($_GET['sucesso'])) {
    if ($_GET['sucesso'] == 'sangria') {
        $mensagem = "<div class='alert alert-success'>💸 Sangria realizada com sucesso! Retire o dinheiro do caixa.</div>";
    } elseif ($_GET['sucesso'] == 'fechado') {
        $mensagem = "<div class='alert alert-success'>✅ Caixa fechado com sucesso!</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Movimentação de Caixa - Gestão Breno</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; padding: 20px; }
        .container-fluid { max-width: 900px; margin: auto; background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header-section { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; margin-bottom: 25px; padding-bottom: 15px; }
        .btn-voltar { background: #6c757d; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-size: 0.9rem; font-weight: bold; transition: 0.3s; }
        .btn-voltar:hover { background: #495057; }
        .caixa-status { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; text-align: center; }
        .status-aberto { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        
        /* Layout em colunas para Sangria e Fechamento */
        .secao-caixa-aberto { display: grid; grid-template-columns: 1fr; gap: 25px; }
        @media(min-width: 768px) {
            .secao-caixa-aberto { grid-template-columns: 1.2fr 0.8fr; }
        }
        
        .box-container { background: #fdfdfd; padding: 20px; border: 1px solid #e3e6f0; border-radius: 8px; }
        .grid-valores { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; }
        .card-pagamento { background: #f8f9fa; padding: 12px; border-radius: 8px; border-top: 4px solid #007bff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .card-pagamento label { display: block; font-size: 0.8rem; font-weight: bold; color: #555; margin-bottom: 5px; }
        .input-valor { width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 5px; font-size: 1rem; font-weight: bold; box-sizing: border-box; }
        
        .btn-acao { width: 100%; padding: 12px; border: none; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; color: white; margin-top: 15px; transition: 0.3s; }
        .btn-abrir { background: #28a745; }
        .btn-fechar { background: #dc3545; }
        .btn-sangria { background: #e74a3b; }
        
        .info-user { font-size: 0.9rem; color: #666; margin-bottom: 20px; }
        .alert-success { padding: 12px; background: #d4edda; color: #155724; border-radius: 6px; margin-bottom: 20px; font-weight: bold; }
        
        /* Estilo lista de sangrias */
        .lista-sangrias { margin-top: 15px; max-height: 180px; overflow-y: auto; font-size: 0.85rem; }
        .item-sangria { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed #ddd; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="header-section">
        <h2 style="margin:0;">🏧 Movimentação de Caixa</h2>
        <a href="dashboard.php" class="btn-voltar">⬅ Voltar ao Painel</a>
    </div>

    <div class="info-user">
        Operador: <strong><?= $usuario_nome ?></strong>
    </div>

    <?php if(!empty($mensagem)) echo $mensagem; ?>

    <?php if (!$caixa_atual): ?>
        <div style="text-align: center; padding: 20px;">
            <h3 style="color: #28a745; margin-top: 0;">Abrir Novo Caixa</h3>
            <p>Informe o valor disponível em dinheiro para troco:</p>
            
            <form method="POST" style="max-width: 400px; margin: auto;">
                <label style="display:block; margin-bottom: 10px; font-weight:bold;">Valor Inicial (Fundo de Reserva):</label>
                <input type="text" name="valor_inicial" class="input-valor" style="text-align: center;" placeholder="0,00" required>
                <button type="submit" name="btn_abrir" class="btn-acao btn-abrir">Iniciar Expediente</button>
            </form>
        </div>

    <?php else: ?>
        <div class="caixa-status status-aberto">
            CAIXA ABERTO DESDE: <?= date('d/m/Y H:i', strtotime($caixa_atual['data_abertura'])) ?>
        </div>

        <div class="secao-caixa-aberto">
            
            <div class="box-container">
                <form method="POST">
                    <h3 style="margin:0 0 5px 0;">Finalizar Turno</h3>
                    <p style="color: #666; font-size: 0.85rem; margin: 0;">Conte os valores físicos na gaveta e informe abaixo:</p>

                    <div class="grid-valores">
                        <div class="card-pagamento">
                            <label>💵 Dinheiro (Espécie)</label>
                            <input type="number" step="0.01" name="contado[dinheiro]" class="input-valor" placeholder="0.00" required>
                        </div>

                        <div class="card-pagamento" style="border-top-color: #32bcad;">
                            <label>📱 PIX</label>
                            <input type="number" step="0.01" name="contado[pix]" class="input-valor" placeholder="0.00" required>
                        </div>

                        <div class="card-pagamento" style="border-top-color: #ffc107;">
                            <label>💳 Cartão de Crédito</label>
                            <input type="number" step="0.01" name="contado[credito]" class="input-valor" placeholder="0.00" required>
                        </div>

                        <div class="card-pagamento" style="border-top-color: #17a2b8;">
                            <label>💳 Cartão de Débito</label>
                            <input type="number" step="0.01" name="contado[debito]" class="input-valor" placeholder="0.00" required>
                        </div>
                    </div>

                    <button type="submit" name="btn_fechar" class="btn-acao btn-fechar" onclick="return confirm('Deseja realmente fechar o caixa?')">
                        Encerrar e Enviar para Conferência
                    </button>
                </form>
            </div>

            <div class="box-container" style="border-top: 4px solid #e74a3b;">
                <h3 style="margin:0 0 5px 0; color:#e74a3b;">💸 Fazer Sangria</h3>
                <p style="color: #666; font-size: 0.85rem; margin-bottom: 15px;">Retirar dinheiro da gaveta durante o expediente:</p>
                
                <form method="POST" onsubmit="return confirm('Confirma a retirada deste valor do caixa físico?')">
                    <div style="margin-bottom:10px;">
                        <label style="font-size:0.8rem; font-weight:bold; display:block; margin-bottom:4px;">Valor da Sangria (R$)</label>
                        <input type="number" step="0.01" name="valor_sangria" class="input-valor" placeholder="0.00" required style="color:#e74a3b;">
                    </div>

                    <div style="margin-bottom:10px;">
                        <label style="font-size:0.8rem; font-weight:bold; display:block; margin-bottom:4px;">Motivo</label>
                        <select name="motivo_sangria" class="input-valor" style="font-size:0.9rem; padding:6px;" required>
                            <option value="">-- Selecione --</option>
                            <option value="Sangria de Segurança">Sangria de Segurança (Excesso)</option>
                            <option value="Pagamento de Fornecedor">Pagamento de Fornecedor</option>
                            <option value="Retirada p/ Depósito">Retirada p/ Depósito</option>
                            <option value="Outros">Outros</option>
                        </select>
                    </div>

                    <div style="margin-bottom:10px;">
                        <label style="font-size:0.8rem; font-weight:bold; display:block; margin-bottom:4px;">Obs (Opcional)</label>
                        <input type="text" name="obs_sangria" class="input-valor" style="font-size:0.9rem; font-weight:normal;" placeholder="Ex: Pago motoboy">
                    </div>

                    <button type="submit" name="btn_sangria" class="btn-acao btn-sangria">Executar Sangria</button>
                </form>

                <?php if (count($sangrias_turno) > 0): ?>
                    <h4 style="margin:20px 0 5px 0; font-size:0.9rem;">Sangrias Deste Turno:</h4>
                    <div class="lista-sangrias">
                        <?php foreach($sangrias_turno as $sang): ?>
                            <div class="item-sangria">
                                <span style="color:#e74a3b; font-weight:bold;">- R$ <?= number_format($sang['valor'], 2, ',', '.') ?></span>
                                <span style="color:#555;" title="<?= htmlspecialchars($sang['observacao']) ?>"><?= htmlspecialchars($sang['motivo']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    <?php endif; ?>
</div>

</body>
</html>