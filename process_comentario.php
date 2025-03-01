<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_chamado = $_POST['id_chamado'];
    $comentario = $_POST['comentario'];
    $id_usuario = $_SESSION['user_id'];
    
    try {
        mysqli_begin_transaction($conn);

       
        $stmt = mysqli_prepare($conn, "INSERT INTO comentarios (id_chamado, id_usuario, comentario) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iis", $id_chamado, $id_usuario, $comentario);
        if (mysqli_stmt_execute($stmt)) {
            $id_comentario = mysqli_insert_id($conn);

            // Processar imagem se foi enviada
            if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
                $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'];
                $filename = $_FILES['imagem']['name'];
                $filetype = $_FILES['imagem']['type'];
                $filesize = $_FILES['imagem']['size'];

                
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (!array_key_exists($ext, $allowed)) {
                    throw new Exception('Formato de imagem não permitido');
                }

               
                if (!in_array($filetype, $allowed)) {
                    throw new Exception('Formato de imagem não permitido');
                }

                // Verificar tamanho (max 5MB)
                if ($filesize > 5 * 1024 * 1024) {
                    throw new Exception('Arquivo muito grande. Máximo permitido: 5MB');
                }

                // Ler a imagem e converter para base64
                $image_base64 = base64_encode(file_get_contents($_FILES['imagem']['tmp_name']));
                $image_data = 'data:' . $filetype . ';base64,' . $image_base64;

                
                $stmt = mysqli_prepare($conn, "INSERT INTO anexos (id_chamado, id_comentario, imagem) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "iis", $id_chamado, $id_comentario, $image_data);
                mysqli_stmt_execute($stmt);
            }

            mysqli_commit($conn);
            header("Location: ver_chamado.php?id=" . $id_chamado . "&success=1");
            exit();
        }
    } catch(Exception $e) {
        mysqli_rollback($conn);
        header("Location: ver_chamado.php?id=" . $id_chamado . "&error=1&message=" . urlencode($e->getMessage()));
        exit();
    }
}

header("Location: dashboard.php");
exit();
?>
