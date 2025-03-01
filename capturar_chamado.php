<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'Técnico') {
    header('Location: index.php');
    exit();
}

if (isset($_GET['id'])) {
    $id_chamado = $_GET['id'];
    $id_tecnico = $_SESSION['user_id'];
    $nome_tecnico = $_SESSION['nome'];

    try {
        // Iniciar transação
        mysqli_begin_transaction($conn);

        // Atualizar o chamado
        $stmt = mysqli_prepare($conn, "
            UPDATE chamados 
            SET status = 'Em andamento', 
                id_tecnico = ?, 
                data_captura = CURRENT_TIMESTAMP 
            WHERE id_chamado = ? 
            AND status = 'Aberto'
        ");
        mysqli_stmt_bind_param($stmt, "ii", $id_tecnico, $id_chamado);
        
        if (mysqli_stmt_execute($stmt)) {
            // Adicionar comentário automático
            $comentario = "Chamado capturado pelo técnico " . htmlspecialchars($nome_tecnico);
            $stmt = mysqli_prepare($conn, "
                INSERT INTO comentarios (id_chamado, id_usuario, comentario) 
                VALUES (?, ?, ?)
            ");
            mysqli_stmt_bind_param($stmt, "iis", $id_chamado, $id_tecnico, $comentario);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_commit($conn);
                $_SESSION['success_message'] = "Chamado capturado com sucesso!";
            } else {
                throw new Exception("Erro ao adicionar comentário.");
            }
        } else {
            throw new Exception("Erro ao capturar o chamado.");
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_message'] = "Erro ao processar a solicitação: " . $e->getMessage();
    }
}

header('Location: ver_chamado.php?id=' . $id_chamado);
exit();
?>
