<?php
// ─────────────────────────────────────────────────────────
//  PREENCHA COM OS DADOS DO SEU BANCO NA HOSTINGER
//  hPanel → Banco de Dados → MySQL → seus dados
// ─────────────────────────────────────────────────────────

$DB_HOST = 'localhost';          // normalmente é localhost na Hostinger
$DB_NAME = 'u123456789_ifce';    // nome do banco que você criou no hPanel
$DB_USER = 'u123456789_ifce';    // usuário do banco
$DB_PASS = 'SuaSenhaAqui';       // senha do banco

// Chave secreta para gerar os tokens de login
// MUDE para algo longo e aleatório antes de subir!
$JWT_SECRET = 'academia-extensao-ifce-2026-mude-isso-urgente';
