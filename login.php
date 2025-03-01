<?php
session_start();
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];

    try {
        // Buscar usuário pelo email
        $stmt = mysqli_prepare($conn, "SELECT id_usuario, nome, cargo, senha FROM usuarios WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $usuario = mysqli_fetch_assoc($result);

        // Verificar se o usuário existe e a senha está correta
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['user_id'] = $usuario['id_usuario'];
            $_SESSION['nome'] = $usuario['nome'];
            $_SESSION['cargo'] = $usuario['cargo'];
            $_SESSION['success_message'] = "Login realizado com sucesso!";
            header('Location: dashboard.php');
            exit();
        }
        
        $_SESSION['error_message'] = "Email ou senha incorretos.";
        header('Location: index.php');
        exit();
        
    } catch (Exception $e) {
        error_log("Erro no login: " . $e->getMessage());
        $_SESSION['error_message'] = "Erro ao processar login. Por favor, tente novamente.";
        header('Location: index.php');
        exit();
    }
}

header('Location: index.php');
exit;
