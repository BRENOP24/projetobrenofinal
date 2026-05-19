<?php
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$db   = getenv('DB_DATABASE') ?: 'projeto_breno';
$user = getenv('DB_USER') ?: 'postgres';
$pass = getenv('DB_PASSWORD') ?: 'root';

try {
    // No PostgreSQL, usamos 'pgsql' no início da string de conexão
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;connect_timeout=5";
    
    $pdo = new PDO($dsn, $user, $pass);
    
    // Habilita exceções para erros
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Opcional: define o esquema de busca (search path) se necessário
    // $pdo->exec("SET search_path TO public");

} catch (PDOException $e) {
    // Erro de conexão tratado com clareza
    die("Erro crítico na conexão com PostgreSQL: " . $e->getMessage());
}
?>