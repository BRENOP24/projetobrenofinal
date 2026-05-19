<?php
$host = 'localhost';
$port = '5432'; // Porta padrão do PostgreSQL
$db   = 'projeto_breno';
$user = 'postgres'; // Usuário padrão do Postgres
$pass = 'root';     // Senha definida por você

try {
    // No PostgreSQL, usamos 'pgsql' no início da string de conexão
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    
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