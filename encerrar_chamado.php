<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Verificar se é técnico ou administrador
if ($_SESSION['cargo'] !== 'Técnico' && $_SESSION['cargo'] !== 'Administrador') {
    $_SESSION['error_message'] = "Você não tem permissão para encerrar chamados.";
    header('Location: dashboard.php');
    exit();
}

if (isset($_GET['id'])) {
    $id_chamado = $_GET['id'];
    $id_usuario = $_SESSION['user_id'];
    $nome_usuario = $_SESSION['nome'];
    $cargo = $_SESSION['cargo'];

    try {
        // Verificar se o chamado existe e pode ser encerrado
        $stmt = mysqli_prepare($conn, "
            SELECT id_tecnico, status 
            FROM chamados 
            WHERE id_chamado = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $id_chamado);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $chamado = mysqli_fetch_assoc($result);

        // Apenas o técnico atribuído ou um administrador pode encerrar
        if ($chamado && 
            $chamado['status'] !== 'Fechado' && 
            ($chamado['id_tecnico'] == $id_usuario || $cargo === 'Administrador')) {
            
           
            mysqli_begin_transaction($conn);

           
            $stmt = mysqli_prepare($conn, "
                UPDATE chamados 
                SET status = 'Fechado',
                    data_fechamento = CURRENT_TIMESTAMP 
                WHERE id_chamado = ?
            ");
            mysqli_stmt_bind_param($stmt, "i", $id_chamado);
            
            if (mysqli_stmt_execute($stmt)) {
                
                $comentario = "Chamado encerrado por " . htmlspecialchars($nome_usuario);
                $stmt = mysqli_prepare($conn, "
                    INSERT INTO comentarios (id_chamado, id_usuario, comentario) 
                    VALUES (?, ?, ?)
                ");
                mysqli_stmt_bind_param($stmt, "iis", $id_chamado, $id_usuario, $comentario);
                
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_commit($conn);
                    $_SESSION['success_message'] = "Chamado encerrado com sucesso!";
                } else {
                    throw new Exception("Erro ao adicionar comentário de encerramento.");
                }
            } else {
                throw new Exception("Erro ao encerrar o chamado.");
            }
        } else {
            $_SESSION['error_message'] = "Você não tem permissão para encerrar este chamado ou ele já está fechado.";
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_message'] = "Erro ao processar a solicitação: " . $e->getMessage();
    }
}

header('Location: ver_chamado.php?id=' . $id_chamado);
exit();
?>
