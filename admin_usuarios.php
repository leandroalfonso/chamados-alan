<?php
session_start();
require_once 'db_connection.php';


if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}


$stmt = mysqli_prepare($conn, "SELECT cargo FROM usuarios WHERE id_usuario = ?");
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$usuario = mysqli_fetch_assoc($result);

if ($usuario['cargo'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $userId = $_POST['user_id'] ?? null;
    
    switch ($_POST['action']) {
        case 'update':
            $cargo = $_POST['cargo'] ?? '';
            if ($userId && $cargo) {
                $stmt = mysqli_prepare($conn, "UPDATE usuarios SET cargo = ? WHERE id_usuario = ?");
                mysqli_stmt_bind_param($stmt, "si", $cargo, $userId);
                mysqli_stmt_execute($stmt);
            }
            break;
            
        case 'delete':
            if ($userId && $userId != $_SESSION['user_id']) {
                $stmt = mysqli_prepare($conn, "DELETE FROM usuarios WHERE id_usuario = ?");
                mysqli_stmt_bind_param($stmt, "i", $userId);
                mysqli_stmt_execute($stmt);
            }
            break;
    }
    
    header('Location: admin_usuarios.php');
    exit;
}


$stmt = mysqli_prepare($conn, "
    SELECT id_usuario, nome, email, cargo, data_cadastro 
    FROM usuarios 
    ORDER BY nome ASC
");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$usuarios = [];
while ($row = mysqli_fetch_assoc($result)) {
    $usuarios[] = $row;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração de Usuários</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <style>
        .user-actions {
            display: flex;
            gap: 10px;
        }
        .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Administração de Usuários</h1>
            <div>
                <a href="register.php"  class="btn btn-primary">
                    
                   <strong>+</strong> Novo Usuário
                </a>
                <a href="dashboard.php" class="btn btn-primary">
                    <span class="material-symbols-outlined align-middle me-1">dashboard</span>
                    Dashboard
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?> 
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Cargo</th>
                        <th>Data de Criação</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['nome']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="user_id" value="<?php echo $user['id_usuario']; ?>">
                                <select name="cargo" class="form-select form-select-sm" onchange="this.form.submit()" <?php echo $user['id_usuario'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                    <option value="Usuário" <?php echo $user['cargo'] === 'Usuário' ? 'selected' : ''; ?>>Usuário</option>
                                    <option value="Técnico" <?php echo $user['cargo'] === 'Técnico' ? 'selected' : ''; ?>>Técnico</option>
                                    <option value="Administrador" <?php echo $user['cargo'] === 'Administrador' ? 'selected' : ''; ?>>Administrador</option>
                                </select>
                            </form>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($user['data_cadastro'])); ?></td>
                        <td>
                            <div class="user-actions">
                                <a href="editar_usuario.php?id=<?php echo $user['id_usuario']; ?>" class="btn btn-primary btn-sm">
                                    <span class="material-symbols-outlined">edit</span>
                                </a>
                                <?php if ($user['id_usuario'] != $_SESSION['user_id']): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este usuário?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id_usuario']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <span class="material-symbols-outlined">delete</span>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

  
    

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#telefone, #whatsapp').mask('(00) 00000-0000');
        });
    </script>
</body>
</html>
