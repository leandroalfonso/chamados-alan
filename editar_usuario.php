<?php
session_start();
require_once 'db_connection.php';


if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'Administrador') {
    header('Location: index.php');
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usuario = $_POST['id_usuario'];
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $data_nascimento = $_POST['data_nascimento'];
    $telefone = trim($_POST['telefone']);
    $whatsapp = trim($_POST['whatsapp']);
    $cidade = trim($_POST['cidade']);
    $estado = trim($_POST['estado']);
    $cargo = $_POST['cargo'];
    $nova_senha = trim($_POST['nova_senha']);

    try {
        // Verificar se o email já existe para outro usuário
        $stmt = mysqli_prepare($conn, "SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
        mysqli_stmt_bind_param($stmt, "si", $email, $id_usuario);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_fetch_assoc($result)) {
            $_SESSION['error_message'] = "Este e-mail já está sendo usado por outro usuário.";
            header("Location: editar_usuario.php?id=" . $id_usuario);
            exit();
        }

        // Validação da idade
        $idade = date_diff(date_create($data_nascimento), date_create('today'))->y;
        if ($idade < 18) {
            $_SESSION['error_message'] = "O usuário precisa ter mais de 18 anos.";
            header("Location: editar_usuario.php?id=" . $id_usuario);
            exit();
        }

       
        $sql = "UPDATE usuarios SET 
                nome = ?, 
                email = ?, 
                data_nascimento = ?,
                telefone = ?,
                whatsapp = ?,
                cidade = ?,
                estado = ?,
                cargo = ?";
        $params = [$nome, $email, $data_nascimento, $telefone, $whatsapp, $cidade, $estado, $cargo];
        $types = "ssssssss";

        // Adicionar senha à query se uma nova senha foi fornecida
        if (!empty($nova_senha)) {
            $sql .= ", senha = ?";
            $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $params[] = $senha_hash;
            $types .= "s";
        }

        $sql .= " WHERE id_usuario = ?";
        $params[] = $id_usuario;
        $types .= "i";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);

        $_SESSION['success_message'] = "Usuário atualizado com sucesso!";
        header('Location: admin_usuarios.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erro ao atualizar usuário: " . $e->getMessage();
        header("Location: editar_usuario.php?id=" . $id_usuario);
        exit();
    }
}

// Buscar informações do usuário
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = mysqli_prepare($conn, "
        SELECT u.*, 
               (SELECT COUNT(*) FROM chamados WHERE id_usuario = u.id_usuario) as total_chamados,
               (SELECT COUNT(*) FROM chamados WHERE id_tecnico = u.id_usuario) as total_atendimentos
        FROM usuarios u 
        WHERE id_usuario = ?
    ");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $usuario = mysqli_fetch_assoc($result);

    if (!$usuario) {
        $_SESSION['error_message'] = "Usuário não encontrado.";
        header('Location: admin_usuarios.php');
        exit();
    }
} else {
    header('Location: admin_usuarios.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuário - Sistema de Chamados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-label {
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .material-symbols-outlined {
            font-size: 20px;
        }
        .user-stats {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .stats-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .stats-item:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="mb-4">
            <span class="material-symbols-outlined">edit</span>
            Editar Usuário
        </h2>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?= $_SESSION['error_message'] ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['success_message'] ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <div class="user-stats">
            <h5 class="mb-3">Estatísticas do Usuário</h5>
            <div class="stats-item">
                <span class="material-symbols-outlined">question_mark</span>
                <div>Chamados Abertos: <strong><?= $usuario['total_chamados'] ?></strong></div>
            </div>
            <?php if ($usuario['cargo'] === 'Técnico' || $usuario['cargo'] === 'Administrador'): ?>
            <div class="stats-item">
                <span class="material-symbols-outlined">done</span>
                <div>Chamados Atendidos: <strong><?= $usuario['total_atendimentos'] ?></strong></div>
            </div>
            <?php endif; ?>
            <div class="stats-item">
                <span class="material-symbols-outlined">calendar_today</span>
                <div>Data de Cadastro: <strong><?= date('d/m/Y', strtotime($usuario['data_cadastro'])) ?></strong></div>
            </div>
        </div>

        <form action="editar_usuario.php" method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="id_usuario" value="<?= $usuario['id_usuario'] ?>">
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="nome" class="form-label">
                        <span class="material-symbols-outlined">person</span>
                        Nome Completo
                    </label>
                    <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">
                        <span class="material-symbols-outlined">email</span>
                        E-mail
                    </label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="data_nascimento" class="form-label">
                        <span class="material-symbols-outlined">cake</span>
                        Data de Nascimento
                    </label>
                    <input type="date" class="form-control" id="data_nascimento" name="data_nascimento" 
                           value="<?= $usuario['data_nascimento'] ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="telefone" class="form-label">
                        <span class="material-symbols-outlined">phone</span>
                        Telefone
                    </label>
                    <input type="text" class="form-control" id="telefone" name="telefone" 
                           value="<?= htmlspecialchars($usuario['telefone']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="whatsapp" class="form-label">
                        <span class="material-symbols-outlined">chat</span>
                        WhatsApp
                    </label>
                    <input type="text" class="form-control" id="whatsapp" name="whatsapp" 
                           value="<?= htmlspecialchars($usuario['whatsapp']) ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="estado" class="form-label">
                        <span class="material-symbols-outlined">location_on</span>
                        Estado
                    </label>
                    <select class="form-select" id="estado" name="estado" required>
                        <option value="">Selecione o estado</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="cidade" class="form-label">
                        <span class="material-symbols-outlined">location_city</span>
                        Cidade
                    </label>
                    <select class="form-select" id="cidade" name="cidade" required>
                        <option value="">Selecione a cidade</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="cargo" class="form-label">
                        <span class="material-symbols-outlined">badge</span>
                        Cargo
                    </label>
                    <select class="form-select" id="cargo" name="cargo" required>
                        <option value="Usuário" <?= $usuario['cargo'] === 'Usuário' ? 'selected' : '' ?>>Usuário</option>
                        <option value="Técnico" <?= $usuario['cargo'] === 'Técnico' ? 'selected' : '' ?>>Técnico</option>
                        <option value="Administrador" <?= $usuario['cargo'] === 'Administrador' ? 'selected' : '' ?>>Administrador</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="nova_senha" class="form-label">
                        <span class="material-symbols-outlined">key</span>
                        Nova Senha (opcional)
                    </label>
                    <input type="password" class="form-control" id="nova_senha" name="nova_senha" 
                           minlength="6" placeholder="Deixe em branco para manter a senha atual">
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <a href="admin_usuarios.php" class="btn btn-secondary">Voltar</a>
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>

    <script>
        $(document).ready(function() {
            // Máscaras para telefone e WhatsApp
            $('#telefone, #whatsapp').mask('(00) 00000-0000');

            // Carregar estados
            $.getJSON('https://servicodados.ibge.gov.br/api/v1/localidades/estados', function(data) {
                data.sort((a, b) => a.nome.localeCompare(b.nome));
                data.forEach(function(estado) {
                    $('#estado').append(`<option value="${estado.sigla}" ${estado.sigla === '<?= $usuario['estado'] ?>' ? 'selected' : ''}>${estado.nome}</option>`);
                });
                
                // Carregar cidades do estado selecionado
                if ($('#estado').val()) {
                    carregarCidades($('#estado').val(), '<?= $usuario['cidade'] ?>');
                }
            });

            // Atualizar cidades quando o estado for alterado
            $('#estado').change(function() {
                carregarCidades($(this).val());
            });

            function carregarCidades(uf, cidadeSelecionada = '') {
                $('#cidade').html('<option value="">Carregando...</option>');
                $.getJSON(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${uf}/municipios`, function(data) {
                    $('#cidade').empty().append('<option value="">Selecione a cidade</option>');
                    data.forEach(function(cidade) {
                        $('#cidade').append(`<option value="${cidade.nome}" ${cidade.nome === cidadeSelecionada ? 'selected' : ''}>${cidade.nome}</option>`);
                    });
                });
            }

            // Validação do formulário
            (function() {
                'use strict';
                var forms = document.querySelectorAll('.needs-validation');
                Array.prototype.slice.call(forms).forEach(function(form) {
                    form.addEventListener('submit', function(event) {
                        if (!form.checkValidity()) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            })();
        });
    </script>
</body>
</html>
