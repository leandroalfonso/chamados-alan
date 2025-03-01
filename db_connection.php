<?php

$host = 'problemas-bd.mysql.uhserver.com';  
$dbname = 'problemas_bd';
$username = 'leandroalfonso';
$password = 'Leandro171716@';


$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die("Não foi possível conectar ao banco de dados: " . mysqli_connect_error());
}


mysqli_set_charset($conn, "utf8mb4");

// Verificar se existe pelo menos um usuário administrador
$stmt = mysqli_query($conn, "SELECT COUNT(*) as count FROM usuarios WHERE cargo = 'Administrador'");
$adminCount = mysqli_fetch_assoc($stmt)['count'];

if ($adminCount == 0) {
    error_log("Nenhum administrador encontrado, criando usuário admin padrão...");
    // Criar usuário administrador padrão
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, "
        INSERT INTO usuarios (nome, email, senha, cargo) 
        VALUES ('Administrador', 'admin@sistema.com', ?, 'Administrador')
    ");
    mysqli_stmt_bind_param($stmt, "s", $adminPassword);
    mysqli_stmt_execute($stmt);
}
