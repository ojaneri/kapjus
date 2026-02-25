<?php
/**
 * KAPJUS RAG - Forgot Password Page
 * Allows lawyers to reset their password via magic link
 * Accessed via: /forgot-password
 */

define('BASE_DIR', realpath(__DIR__ . '/..'));
define('SOCKET_HOST', 'http://127.0.0.1:8000');
require_once BASE_DIR . '/src/php/auth.php';

session_start();

$error = null;
$success = null;
$token = isset($_GET['token']) ? $_GET['token'] : null;
$email = isset($_GET['email']) ? $_GET['email'] : null;

// If token and email provided, show password reset form
if ($token && $email) {
    // Handle password reset submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (strlen($password) < 6) {
            $error = "A senha deve ter pelo menos 6 caracteres.";
        } elseif ($password !== $confirm_password) {
            $error = "As senhas não conferem.";
        } else {
            // Reset password via API
            $ch = curl_init(SOCKET_HOST . "/reset_password");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'email' => $email,
                'token' => $token,
                'password' => $password
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $success = "Senha redefinida com sucesso! Você já pode fazer login.";
            } else {
                $result = json_decode($response, true);
                $error = $result['detail'] ?? $result['message'] ?? "Erro ao redefinir senha.";
            }
        }
    }
} else {
    // Show email submission form
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'] ?? '';
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Por favor, insira um e-mail válido.";
        } else {
            // Request password reset via API
            $ch = curl_init(SOCKET_HOST . "/forgot_password");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'email' => $email
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Always show success to prevent email enumeration
            $success = "Se o e-mail estiver cadastrado, você receberá um link para redefinir sua senha.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KAPJUS - Esqueci minha Senha</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="min-h-screen gradient-bg flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center space-x-3 mb-4">
                <img src="https://kaponline.com.br/logo.jpeg" alt="KapOnline" class="h-12 w-12 rounded-xl shadow-lg">
                <span class="text-3xl font-black tracking-tighter text-white uppercase">KAP<span class="text-indigo-300">JUS</span></span>
            </div>
            <p class="text-indigo-200">Sistema de Gestão Jurídica</p>
        </div>
        
        <?php if ($success): ?>
        <!-- Success Card -->
        <div class="bg-white rounded-3xl shadow-2xl p-8">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check text-2xl text-emerald-500"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-900">
                    <?php if ($token): ?>
                        Senha Redefinida!
                    <?php else: ?>
                        E-mail Enviado!
                    <?php endif; ?>
                </h2>
                <p class="text-slate-600"><?php echo htmlspecialchars($success); ?></p>
            </div>
            
            <a href="/login" class="block w-full py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold rounded-xl hover:from-indigo-700 hover:to-purple-700 transition-all shadow-lg shadow-indigo-200 text-center">
                <i class="fas fa-sign-in-alt mr-2"></i> Voltar ao Login
            </a>
        </div>
        
        <?php elseif ($token && $email): ?>
        <!-- Password Reset Form -->
        <div class="bg-white rounded-3xl shadow-2xl p-8">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-key text-2xl text-indigo-500"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-900">Nova Senha</h2>
                <p class="text-slate-600">Digite sua nova senha para <strong><?php echo htmlspecialchars($email); ?></strong></p>
            </div>
            
            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 rounded-2xl px-4 py-3 text-sm font-medium text-red-700 flex items-center gap-2 mb-4">
                <i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-widest mb-2">Nova Senha</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="password" name="password" required minlength="6"
                            class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition-all"
                            placeholder="Mínimo 6 caracteres">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-widest mb-2">Confirmar Senha</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="password" name="confirm_password" required minlength="6"
                            class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition-all"
                            placeholder="Repita a senha">
                    </div>
                </div>
                
                <button type="submit" class="w-full py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold rounded-xl hover:from-indigo-700 hover:to-purple-700 transition-all shadow-lg shadow-indigo-200">
                    <i class="fas fa-check mr-2"></i> Redefinir Senha
                </button>
            </form>
            
            <div class="text-center mt-4">
                <a href="/login" class="text-sm text-slate-500 hover:text-indigo-600">
                    <i class="fas fa-arrow-left mr-1"></i> Voltar ao Login
                </a>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Email Submission Form -->
        <div class="bg-white rounded-3xl shadow-2xl p-8">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-key text-2xl text-indigo-500"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-900">Esqueci minha Senha</h2>
                <p class="text-slate-600">Digite seu e-mail para receber um link de recuperação.</p>
            </div>
            
            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 rounded-2xl px-4 py-3 text-sm font-medium text-red-700 flex items-center gap-2 mb-4">
                <i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-widest mb-2">E-mail</label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="email" name="email" required
                            class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition-all"
                            placeholder="seu@email.com.br">
                    </div>
                </div>
                
                <button type="submit" class="w-full py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold rounded-xl hover:from-indigo-700 hover:to-purple-700 transition-all shadow-lg shadow-indigo-200">
                    <i class="fas fa-paper-plane mr-2"></i> Enviar Link de Recuperação
                </button>
            </form>
            
            <div class="text-center mt-4">
                <a href="/login" class="text-sm text-slate-500 hover:text-indigo-600">
                    <i class="fas fa-arrow-left mr-1"></i> Voltar ao Login
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="text-center mt-6 text-indigo-200 text-sm">
            <p>&copy; 2024 KAPJUS. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
