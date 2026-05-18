<?php
// Configuração para não quebrar o JSON com avisos do PHP
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'config/conexao.php';

$termo = $_GET['query'] ?? '';

try {
    if (!empty($termo)) {
        // Ajustado para as colunas reais: id, nome, preco_venda
        $sql = "SELECT id, nome, preco_venda 
                FROM produtos 
                WHERE (nome ILIKE :busca OR codigo_barras ILIKE :busca)
                AND status = 'Ativo' 
                LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':busca', "%$termo%");
        $stmt->execute();
        
        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($resultado);
    } else {
        echo json_encode([]);
    }
} catch (Exception $e) {
    // Se der erro no SQL, avisa o JS de forma limpa
    echo json_encode(["erro_sql" => $e->getMessage()]);
}