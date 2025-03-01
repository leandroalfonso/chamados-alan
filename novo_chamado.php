<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $prioridade = $_POST['prioridade'];
    $id_usuario = $_SESSION['user_id'];
    $id_tecnico = null;
    
    // Apenas administradores e técnicos podem atribuir técnicos
    if (($_SESSION['cargo'] === 'Administrador' || $_SESSION['cargo'] === 'Técnico') && !empty($_POST['tecnico'])) {
        $id_tecnico = $_POST['tecnico'];
    }
    
    try {
        mysqli_begin_transaction($conn);

        // Inserir o chamado
        if ($id_tecnico) {
            $stmt = mysqli_prepare($conn, "INSERT INTO chamados (titulo, descricao, status, prioridade, id_usuario, id_tecnico, data_abertura) VALUES (?, ?, 'Aberto', ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt, "sssii", $titulo, $descricao, $prioridade, $id_usuario, $id_tecnico);
            $result = mysqli_stmt_execute($stmt);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO chamados (titulo, descricao, status, prioridade, id_usuario, data_abertura) VALUES (?, ?, 'Aberto', ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt, "sssi", $titulo, $descricao, $prioridade, $id_usuario);
            $result = mysqli_stmt_execute($stmt);
        }
        
        if ($result) {
            $id_chamado = mysqli_insert_id($conn);

            // verificar imagem se foi enviada
            if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
                $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'];
                $filename = $_FILES['imagem']['name'];
                $filetype = $_FILES['imagem']['type'];
                $filesize = $_FILES['imagem']['size'];

                // Verificar extensão
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (!array_key_exists($ext, $allowed)) {
                    throw new Exception('Formato de imagem não permitido');
                }

                // Verificar MIME type
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

                
                $stmt = mysqli_prepare($conn, "INSERT INTO anexos (id_chamado, imagem) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, "is", $id_chamado, $image_data);
                mysqli_stmt_execute($stmt);
            }

            mysqli_commit($conn);
            $_SESSION['success_message'] = "Chamado criado com sucesso!";
            header("Location: dashboard.php");
            exit();
        } else {
            throw new Exception('Erro ao criar o chamado');
        }
    } catch(Exception $e) {
        mysqli_rollback($conn);
        $error_message = $e->getMessage();
        $has_error = true;
    }
}


$stmt = mysqli_prepare($conn, "SELECT id_usuario, nome FROM usuarios WHERE cargo = 'Técnico'");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$tecnicos = [];
while ($row = mysqli_fetch_assoc($result)) {
    $tecnicos[] = $row;
}

// Mensagens de erro/sucesso
$error_message = $error_message ?? '';
$has_error = $has_error ?? false;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Chamado - Sistema de Chamados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .alert {
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Abrir Chamado</h2>
        
        <?php if ($has_error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message ?: 'Erro ao criar chamado. Por favor, tente novamente.'); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="titulo">Título:</label>
                <input type="text" class="form-control" id="titulo" name="titulo" required>
            </div>
            
            <div class="form-group">
                <label for="descricao">Descrição:</label>
                <textarea class="form-control" id="descricao" name="descricao" required></textarea>
            </div>

            <div class="form-group">
                <label for="imagem">Anexar Imagem:</label>
                <input type="file" class="form-control" id="imagem" name="imagem" accept="image/*">
                <small class="form-text text-muted">Formatos suportados: JPG, PNG, GIF (max 5MB)</small>
            </div>
            
            <div class="form-group">
                <label for="prioridade">Prioridade:</label>
                <select class="form-control" id="prioridade" name="prioridade" required>
                    <option value="Baixa">Baixa</option>
                    <option value="Média" selected>Média</option>
                    <option value="Alta">Alta</option>
                </select>
            </div>
            
            <?php if ($_SESSION['cargo'] === 'Administrador' || $_SESSION['cargo'] === 'Técnico'): ?>
            <div class="form-group">
                <label for="tecnico">Técnico (opcional):</label>
                <select class="form-control" id="tecnico" name="tecnico">
                    <option value="">Selecione um técnico</option>
                    <?php foreach ($tecnicos as $tecnico): ?>
                        <option value="<?php echo $tecnico['id_usuario']; ?>">
                            <?php echo htmlspecialchars($tecnico['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="buttons mt-3">
                <button type="submit" class="btn btn-primary">Criar Chamado</button>
                <a href="dashboard.php" class="btn btn-danger">Cancelar</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/lang/summernote-pt-BR.min.js"></script>
    
    <script>
    $(document).ready(function() {
        $('#descricao').summernote({
            lang: 'pt-BR',
            height: 300,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ],
            placeholder: 'Digite a descrição do chamado aqui...'
        });
    });
    </script>
</body>
</html>
