<?php
require_once 'db_connection.php';

if ($conn) {
    echo "✅ Conexão OK\n\n";
    
    $query = "SELECT id_usuario, nome, email, cargo FROM usuarios WHERE email = 'admin@sistema.com'";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        echo "Usuário encontrado:\n";
        echo "ID: " . $user['id_usuario'] . "\n";
        echo "Nome: " . $user['nome'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Cargo: " . $user['cargo'] . "\n";
    } else {
        echo "❌ Usuário admin não encontrado!\n";
        
        // Tentar criar o usuário admin
        $senha = password_hash('admin123', PASSWORD_DEFAULT);
        $query = "INSERT INTO usuarios (nome, email, senha, cargo) VALUES ('Administrador', 'admin@sistema.com', '$senha', 'Administrador')";
        if (mysqli_query($conn, $query)) {
            echo "\n✅ Usuário admin criado com sucesso!\n";
            echo "Email: admin@sistema.com\n";
            echo "Senha: admin123\n";
        } else {
            echo "\n❌ Erro ao criar usuário: " . mysqli_error($conn) . "\n";
        }
    }
} else {
    echo "❌ Erro na conexão\n";
}
?>
