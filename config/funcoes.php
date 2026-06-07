<?php
// config/funcoes.php

// CORREÇÃO DO FUSO HORÁRIO NO PHP
date_default_timezone_set('America/Sao_Paulo');

/**
 * Registra ações críticas no banco de logs
 */
function registrarLog($pdo, $acao, $tabela, $descricao) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $usuario_id = $_SESSION['usuario_id'] ?? 0; // 0 para ações do sistema/anônimas
    $usuario_nome = $_SESSION['usuario_nome'] ?? 'Sistema';

    $sql = "INSERT INTO logs_sistema (usuario_id, usuario_nome, acao, tabela_afetada, descricao) VALUES (?, ?, ?, ?, ?)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id, $usuario_nome, strtoupper($acao), $tabela, $descricao]);
    } catch (PDOException $e) {
        // Silencioso ou log de erro do servidor para não travar a aplicação principal
        error_log("Erro ao registrar log: " . $e->getMessage());
    }
}

/**
 * Verifica se o usuário está autenticado
 */
function usuarioLogado() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['usuario_id']);
}

/**
 * Retorna o nível do usuário atual
 */
function nivelUsuario() {
    return $_SESSION['nivel'] ?? null;
}

/**
 * Trava de segurança por nível de acesso
 */
function exigirNivel($niveisPermitidos = []) {

    $nivelUsuario = strtolower(trim(nivelUsuario()));

    $niveisPermitidos = array_map(function($nivel){
        return strtolower(trim($nivel));
    }, $niveisPermitidos);

    if (!usuarioLogado() || !in_array($nivelUsuario, $niveisPermitidos)) {
        header("Location: dashboard.php?erro=acesso_negado");
        exit;
    }
}


/**
 * Processa estornos com segurança financeira e auditoria
 */
function processarEstornoSeguro($pdo, $tipo, $id_registro, $usuario_id, $motivo) {
    $tipo = strtoupper($tipo); // Garante padrão (VENDA ou COMPRA)
    
    try {
        $pdo->beginTransaction();

        // 1. Identifica o registro e o caixa original
        $tabela = ($tipo === 'VENDA') ? 'pedidos' : 'compras';
        $stmt = $pdo->prepare("SELECT valor_total, caixa_id, status FROM $tabela WHERE id = ?");
        $stmt->execute([$id_registro]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dados) {
            throw new Exception("Registro de $tipo não encontrado.");
        }

        if ($dados['status'] === 'estornado') {
            throw new Exception("Este registro já foi estornado anteriormente.");
        }

        // 2. Verifica status do caixa de origem
        $stmtCaixa = $pdo->prepare("SELECT status FROM controle_caixas WHERE id = ?");
        $stmtCaixa->execute([$dados['caixa_id']]);
        $status_caixa_origem = $stmtCaixa->fetchColumn();

        // 3. Busca o caixa ABERTO do usuário atual
        $stmtCaixaAtual = $pdo->prepare("SELECT id FROM controle_caixas WHERE usuario_id = ? AND status = 'aberto' LIMIT 1");
        $stmtCaixaAtual->execute([$usuario_id]);
        $caixa_atual_id = $stmtCaixaAtual->fetchColumn();

        if (!$caixa_atual_id) {
            throw new Exception("Operação negada: Você não possui um caixa ABERTO para processar este estorno.");
        }

        // --- EXECUÇÃO DO ESTORNO ---

        // Atualiza o status do registro original para 'estornado' (Melhor que DELETE para auditoria)
        $stmtUpdate = $pdo->prepare("UPDATE $tabela SET status = 'estornado' WHERE id = ?");
        $stmtUpdate->execute([$id_registro]);

        // Registra na Auditoria Financeira
        $sqlAuditoria = "INSERT INTO auditoria_financeira 
            (tipo_operacao, registro_origem_id, valor_estornado, caixa_origem_id, caixa_atual_id, usuario_id, motivo, data_estorno) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $pdo->prepare($sqlAuditoria)->execute([
            "ESTORNO_$tipo", 
            $id_registro, 
            $dados['valor_total'], 
            $dados['caixa_id'], 
            $caixa_atual_id, 
            $usuario_id, 
            $motivo
        ]);

        // 4. LOG DE SISTEMA (Chamando sua função de log)
        registrarLog($pdo, 'ESTORNO', $tabela, "Estorno de $tipo ID $id_registro. Motivo: $motivo");

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return $e->getMessage();
    }
}
