<?php
// Configurações do SQLite
$dbFile = __DIR__ . '/gastos.db';

// Conexão com o SQLite
try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Criar tabelas se não existirem
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        nome TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'user'
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS gastos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_id INTEGER NOT NULL,
        data DATE NOT NULL,
        categoria TEXT NOT NULL,
        descricao TEXT NOT NULL,
        valor REAL NOT NULL,
        forma_pagamento TEXT NOT NULL,
        observacoes TEXT,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS parcelamentos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_id INTEGER NOT NULL,
        descricao TEXT NOT NULL,
        valor_total REAL NOT NULL,
        qtd_parcelas INTEGER NOT NULL,
        parcela_atual INTEGER NOT NULL DEFAULT 1,
        status TEXT NOT NULL DEFAULT 'ativo',
        data_inicio DATE NOT NULL,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS rendas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    descricao TEXT NOT NULL,
    valor REAL NOT NULL,
    tipo TEXT NOT NULL, -- 'salario' ou 'extra'
    data DATE NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    )");
    
    // Inserir usuários iniciais se não existirem
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
    $count = $stmt->fetchColumn();
    if ($count == 0) {
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
        $user1Pass = password_hash('gabicris25', PASSWORD_DEFAULT);
        $user2Pass = password_hash('MPS@2017', PASSWORD_DEFAULT);
        
        $pdo->exec("INSERT INTO usuarios (username, password, nome, role) VALUES 
            ('admin', '$adminPass', 'Administrador', 'admin'),
            ('gabriele', '$user1Pass', 'Gabriele', 'user'),
            ('matheus', '$user2Pass', 'Matheus', 'user')");
    }
    
} catch (PDOException $e) {
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}
?>