<?php

$host = getenv('DB_HOST') ?: 'dpg-d8rg2vj7uimc73f5pqi0-a.oregon-postgres.render.com';
$port = getenv('DB_PORT') ?: '5432';
$db   = getenv('DB_DATABASE') ?: 'projeto_breno_4xjh';
$user = getenv('DB_USER') ?: 'projeto_breno_4xjh_user';
$pass = getenv('DB_PASSWORD') ?: 'oos6D62TaTilaY2gBLJKH6WB8TDtdruu';

try {

    // Conexão PostgreSQL Render
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require;connect_timeout=5";

    $pdo = new PDO($dsn, $user, $pass);

    // Habilita exceções
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Opcional
    $pdo->exec("SET search_path TO public");
    
    // CORREÇÃO DO FUSO HORÁRIO NO POSTGRESQL (Render/UTC -> Brasília)
    $pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");

} catch (PDOException $e) {

    die("Erro crítico na conexão com PostgreSQL: " . $e->getMessage());

}

?>
