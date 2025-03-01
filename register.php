<?php
session_start();
require_once 'db_connection.php';


if (isset($_SESSION['user_id'])) {
    // Se for admin, permite acesso
    if ($_SESSION['cargo'] === 'Administrador') {
        
    } else {
        // Se não for admin, redireciona para o dashboard
        header('Location: dashboard.php');
        exit();
    }
}

$erro = null;
$sucesso = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $data_nascimento = $_POST['data_nascimento'];
    $telefone = trim($_POST['telefone']);
    $whatsapp = trim($_POST['whatsapp']);
    $cidade = trim($_POST['cidade']);
    $estado = trim($_POST['estado']);
    $senha = $_POST['senha'];
    
    // Se for criação por admin, usar o cargo especificado
    if (isset($_POST['admin_created']) && $_SESSION['cargo'] === 'Administrador') {
        $cargo = $_POST['cargo'];
        $redirect_success = 'admin_usuarios.php';
    } else {
        // Registro normal de usuário
        $cargo = 'Usuário';
        $confirma_senha = $_POST['confirma_senha'];
        $redirect_success = 'login.php';
        
       
        if ($senha !== $confirma_senha) {
            $erro = "As senhas não coincidem.";
        }
    }

    // Validação da idade
    $idade = date_diff(date_create($data_nascimento), date_create('today'))->y;
    if ($idade < 18) {
        $erro = "Você precisa ter mais de 18 anos para se cadastrar.";
    }
    // Validação do email
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Por favor, insira um e-mail válido.";
    }
    // Validação da senha
    elseif (strlen($senha) < 6) {
        $erro = "A senha deve ter pelo menos 6 caracteres.";
    }
    else {
        try {
            // Verifica se o email já existe
            $stmt = mysqli_prepare($conn, "SELECT id_usuario FROM usuarios WHERE email = ?");
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_fetch_assoc($result)) {
                $erro = "Este e-mail já está cadastrado.";
            } else {
                // Insere o novo usuário
                $stmt = mysqli_prepare($conn, "
                    INSERT INTO usuarios (nome, email, senha, data_nascimento, telefone, whatsapp, cidade, estado, cargo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                
                mysqli_stmt_bind_param($stmt, "sssssssss", 
                    $nome,
                    $email,
                    $senha_hash,
                    $data_nascimento,
                    $telefone,
                    $whatsapp,
                    $cidade,
                    $estado,
                    $cargo
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    if (isset($_POST['admin_created'])) {
                        $_SESSION['success_message'] = "Usuário cadastrado com sucesso!";
                    } else {
                        $_SESSION['success_message'] = "Cadastro realizado com sucesso! Faça login para continuar.";
                    }
                    header("Location: $redirect_success");
                    exit();
                }
            }
        } catch (Exception $e) {
            $erro = "Erro ao cadastrar: " . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Sistema de Chamados</title>
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
        }
        .material-symbols-outlined {
            vertical-align: middle;
            font-size: 20px;
            margin-right: 5px;
        }
        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="mb-4">
            <span class="material-symbols-outlined">how_to_reg</span>
            Cadastre o usuário
        </h2>

        <?php if ($erro): ?>
            <div class="alert alert-danger">
                <span class="material-symbols-outlined">error</span>
                <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="alert alert-success">
                <span class="material-symbols-outlined">check_circle</span>
                <?php echo $sucesso; ?>
                <a href="login.php" class="btn btn-success ms-3">Fazer Login</a>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="nome" class="form-label">
                        <span class="material-symbols-outlined">person</span>
                        Nome Completo
                    </label>
                    <input type="text" class="form-control" id="nome" name="nome" required>
                    <div class="invalid-feedback">Por favor, insira seu nome completo.</div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="data_nascimento" class="form-label">
                        <span class="material-symbols-outlined">cake</span>
                        Data de Nascimento
                    </label>
                    <input type="date" class="form-control" id="data_nascimento" name="data_nascimento" required>
                    <div class="invalid-feedback">Por favor, insira sua data de nascimento.</div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">
                        <span class="material-symbols-outlined">mail</span>
                        E-mail
                    </label>
                    <input type="email" class="form-control" id="email" name="email" required>
                    <div class="invalid-feedback">Por favor, insira um e-mail válido.</div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="telefone" class="form-label">
                        <span class="material-symbols-outlined">phone</span>
                        Telefone
                    </label>
                    <input type="text" class="form-control" id="telefone" name="telefone" required>
                    <div class="invalid-feedback">Por favor, insira seu telefone.</div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="whatsapp" class="form-label">
                        <span class="material-symbols-outlined">chat</span>
                        WhatsApp
                    </label>
                    <input type="text" class="form-control" id="whatsapp" name="whatsapp" required>
                    <div class="invalid-feedback">Por favor, insira seu WhatsApp.</div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="estado" class="form-label">
                        <span class="material-symbols-outlined">location_on</span>
                        Estado
                    </label>
                    <select class="form-select" id="estado" name="estado" required>
                        <option value="">Selecione...</option>
                    </select>
                    <div class="invalid-feedback">Por favor, selecione seu estado.</div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="cidade" class="form-label">
                        <span class="material-symbols-outlined">location_city</span>
                        Cidade
                    </label>
                    <select class="form-select" id="cidade" name="cidade" required disabled>
                        <option value="">Selecione um estado primeiro...</option>
                    </select>
                    <div class="invalid-feedback">Por favor, selecione sua cidade.</div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="senha" class="form-label">
                        <span class="material-symbols-outlined">lock</span>
                        Senha
                    </label>
                    <input type="password" class="form-control" id="senha" name="senha" required>
                    <div class="invalid-feedback">A senha deve ter pelo menos 6 caracteres.</div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="confirma_senha" class="form-label">
                        <span class="material-symbols-outlined">lock_reset</span>
                        Confirmar Senha
                    </label>
                    <input type="password" class="form-control" id="confirma_senha" name="confirma_senha" required>
                    <div class="invalid-feedback">As senhas não coincidem.</div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined">how_to_reg</span>
                    Cadastrar
                </button>
                <a href="login.php" class="btn btn-secondary">
                    <span class="material-symbols-outlined">login</span>
                    Já tenho uma conta
                </a>
            </div>
        </form>
    </div>

    <script>
        // Máscaras para telefone e WhatsApp
        $(document).ready(function() {
            $('#telefone, #whatsapp').mask('(00) 00000-0000');
        });

        // Validação do formulário
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()

        // Carregar estados
        fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados')
            .then(response => response.json())
            .then(estados => {
                estados.sort((a, b) => a.nome.localeCompare(b.nome));
                const selectEstado = document.getElementById('estado');
                estados.forEach(estado => {
                    const option = document.createElement('option');
                    option.value = estado.sigla;
                    option.textContent = estado.nome;
                    selectEstado.appendChild(option);
                });
            });

        // Carregar cidades quando selecionar estado
        document.getElementById('estado').addEventListener('change', function() {
            const estado = this.value;
            const selectCidade = document.getElementById('cidade');
            
            selectCidade.innerHTML = '<option value="">Carregando...</option>';
            selectCidade.disabled = true;

            if (estado) {
                fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${estado}/municipios`)
                    .then(response => response.json())
                    .then(cidades => {
                        selectCidade.innerHTML = '<option value="">Selecione...</option>';
                        cidades.forEach(cidade => {
                            const option = document.createElement('option');
                            option.value = cidade.nome;
                            option.textContent = cidade.nome;
                            selectCidade.appendChild(option);
                        });
                        selectCidade.disabled = false;
                    });
            } else {
                selectCidade.innerHTML = '<option value="">Selecione um estado primeiro...</option>';
            }
        });

        // Validação de senha em tempo real
        document.getElementById('confirma_senha').addEventListener('input', function() {
            const senha = document.getElementById('senha').value;
            const confirmaSenha = this.value;
            
            if (senha !== confirmaSenha) {
                this.setCustomValidity('As senhas não coincidem');
            } else {
                this.setCustomValidity('');
            }
        });

        // Validação de idade em tempo real
        document.getElementById('data_nascimento').addEventListener('change', function() {
            const dataNascimento = new Date(this.value);
            const hoje = new Date();
            const idade = hoje.getFullYear() - dataNascimento.getFullYear();
            const m = hoje.getMonth() - dataNascimento.getMonth();
            
            if (m < 0 || (m === 0 && hoje.getDate() < dataNascimento.getDate())) {
                idade--;
            }
            
            if (idade < 18) {
                this.setCustomValidity('Você precisa ter mais de 18 anos');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
