-- Configuração de charset
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Configuração do banco de dados
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    cargo ENUM('Administrador', 'Técnico', 'Usuário') NOT NULL,
    telefone VARCHAR(20),
    whatsapp VARCHAR(20),
    cidade VARCHAR(100),
    estado CHAR(2),
    data_nascimento DATE,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela de chamados
CREATE TABLE IF NOT EXISTS chamados (
    id_chamado INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(100) NOT NULL,
    descricao TEXT NOT NULL,
    status ENUM('Aberto', 'Em andamento', 'Fechado') NOT NULL DEFAULT 'Aberto',
    prioridade ENUM('Alta', 'Média', 'Baixa') NOT NULL,
    id_usuario INT NOT NULL,
    id_tecnico INT,
    data_abertura TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_captura TIMESTAMP NULL,
    data_fechamento TIMESTAMP NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_tecnico) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela de comentários
CREATE TABLE IF NOT EXISTS comentarios (
    id_comentario INT AUTO_INCREMENT PRIMARY KEY,
    id_chamado INT NOT NULL,
    id_usuario INT NOT NULL,
    comentario TEXT NOT NULL,
    data_comentario TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_chamado) REFERENCES chamados(id_chamado) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela de anexos
CREATE TABLE IF NOT EXISTS anexos (
    id_anexo INT AUTO_INCREMENT PRIMARY KEY,
    id_chamado INT NOT NULL,
    id_comentario INT NULL,
    imagem LONGTEXT NOT NULL,
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_chamado) REFERENCES chamados(id_chamado) ON DELETE CASCADE,
    FOREIGN KEY (id_comentario) REFERENCES comentarios(id_comentario) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela de configurações de usuário
CREATE TABLE IF NOT EXISTS configs (
    id_config INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    nome VARCHAR(100) NOT NULL,
    valor TEXT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    UNIQUE KEY unique_config (id_usuario, tipo, nome)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela de histórico de chamados
CREATE TABLE IF NOT EXISTS historico_chamados (
    id_historico INT AUTO_INCREMENT PRIMARY KEY,
    id_chamado INT NOT NULL,
    id_usuario INT NOT NULL,
    acao VARCHAR(50) NOT NULL,
    comentario TEXT NOT NULL,
    data_acao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_chamado) REFERENCES chamados(id_chamado),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela de notificações
CREATE TABLE IF NOT EXISTS notificacoes (
    id_notificacao INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    mensagem TEXT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_leitura TIMESTAMP NULL,
    id_referencia INT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela de solicitações de reabertura
CREATE TABLE IF NOT EXISTS solicitacoes_reabertura (
    id_solicitacao INT PRIMARY KEY AUTO_INCREMENT,
    id_chamado INT NOT NULL,
    id_usuario INT NOT NULL,
    justificativa TEXT NOT NULL,
    status ENUM('Pendente', 'Aprovada', 'Rejeitada') DEFAULT 'Pendente',
    data_solicitacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_resposta TIMESTAMP NULL,
    id_responsavel INT NULL,
    FOREIGN KEY (id_chamado) REFERENCES chamados(id_chamado),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_responsavel) REFERENCES usuarios(id_usuario)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Inserir usuário administrador padrão (senha: admin123)
INSERT INTO usuarios (nome, email, senha, cargo, telefone, whatsapp, cidade, estado, data_nascimento) VALUES
('Administrador', 'admin@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', '', '', '', '', NULL);

-- Inserir configurações padrão para o admin
INSERT INTO configs (id_usuario, tipo, nome, valor) VALUES
(1, 'filtro_chamados', 'status', '["Aberto","Em andamento"]'),
(1, 'filtro_chamados', 'prioridade', '["Alta","Média"]'),
(1, 'filtro_chamados', 'ordem', 'data_abertura DESC');

COMMIT;
