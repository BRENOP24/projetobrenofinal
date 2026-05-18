<?php
require_once 'config/conexao.php';

$tipo = $_GET['tipo'] ?? '';
$q = $_GET['q'] ?? '';

// Retornar um array vazio se a busca for muito curta
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

if ($tipo == 'cliente') {
    // Usamos ILIKE para busca insensível a maiúsculas/minúsculas no Postgres
    $sql = "SELECT id, nome, endereco, numero, bairro, ponto_referencia 
            FROM clientes 
            WHERE nome ILIKE :q 
            OR cpf_cnpj ILIKE :q 
            LIMIT 5";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':q' => "%$q%"]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

elseif ($tipo == 'produto') {
    // ILIKE para o nome e busca exata ou aproximada para o código
    $sql = "SELECT id, nome, preco_venda 
            FROM produtos 
            WHERE nome ILIKE :q 
            OR codigo_barras = :codigo
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    // Para o código de barras, tentamos a busca exata também para ser mais rápido
    $stmt->execute([
        ':q' => "%$q%",
        ':codigo' => $q 
    ]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}