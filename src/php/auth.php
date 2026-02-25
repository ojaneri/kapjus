<?php
/**
 * KapJus Authentication
 * - Login page HTML
 * - requireAuth() middleware
 * - Session helpers
 */

define('AUTH_SESSION_KEY', 'kapjus_user');

function get_app_secret(): string {
    // Read from .env APP_SECRET; fallback to a static if not set (should always be set)
    $secret = getenv('APP_SECRET') ?: '';
    if (!$secret) {
        // Try parsing .env manually (PHP doesn't auto-load it)
        $env_file = BASE_DIR . '/.env';
        if (file_exists($env_file)) {
            foreach (file($env_file) as $line) {
                $line = trim($line);
                if (strpos($line, 'APP_SECRET=') === 0) {
                    $secret = substr($line, strlen('APP_SECRET='));
                    break;
                }
            }
        }
    }
    return $secret ?: 'fallback-insecure-key-change-me';
}

/**
 * Returns the currently logged-in user array, or null if not authenticated.
 */
function current_user(): ?array {
    $user = $_SESSION[AUTH_SESSION_KEY] ?? null;
    if ($user) return $user;

    // Fallback to signed cookie (for magic links)
    $cookie = $_COOKIE['kapjus_session'] ?? null;
    if ($cookie) {
        $data = get_signed_cookie('kapjus_session');
        if ($data) {
            // Populate session from valid cookie so we don't have to re-verify every time
            $_SESSION[AUTH_SESSION_KEY] = $data;
            return $data;
        }
    }
    return null;
}

/**
 * Sign a value with HMAC-SHA256
 */
function sign_data(string $data): string {
    return hash_hmac('sha256', $data, get_app_secret());
}

/**
 * Set a signed cookie
 */
function set_signed_cookie(string $name, array $data, int $expiry): void {
    $payload = json_encode($data);
    $signature = sign_data($payload);
    $cookie_value = base64_encode($payload) . '.' . $signature;
    
    // Set cookie with security flags
    setcookie($name, $cookie_value, $expiry, '/', '', true, true);
}

/**
 * Get and verify a signed cookie
 */
function get_signed_cookie(string $name): ?array {
    $cookie_value = $_COOKIE[$name] ?? null;
    if (!$cookie_value) return null;

    $parts = explode('.', $cookie_value);
    if (count($parts) !== 2) return null;

    $payload_b64 = $parts[0];
    $signature = $parts[1];

    $payload = base64_decode($payload_b64, true);
    if (!$payload) return null;

    $expected_signature = sign_data($payload);
    if (!hash_equals($expected_signature, $signature)) {
        return null; // Signature mismatch!
    }

    return json_decode($payload, true);
}

/**
 * Redirect to login if not authenticated. Call at the top of every protected route.
 */
function requireAuth(): void {
    if (!current_user()) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header("Location: /login?redirect={$redirect}");
        exit;
    }
}

/**
 * Log in a user: validate credentials, set session.
 * Returns error string or null on success.
 */
function attempt_login(SQLite3 $db, string $email, string $password): ?string {
    if (!$email || !$password) return 'Preencha e-mail e senha.';

    $stmt = $db->prepare("SELECT id, email, name, password_hash FROM users WHERE email = :email LIMIT 1");
    $stmt->bindValue(':email', strtolower(trim($email)), SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$row || !password_verify($password, $row['password_hash'])) {
        return 'E-mail ou senha inválidos.';
    }

    // Regenerate session ID on login (session fixation protection)
    session_regenerate_id(true);
    $_SESSION[AUTH_SESSION_KEY] = [
        'id'    => $row['id'],
        'email' => $row['email'],
        'name'  => $row['name'],
    ];
    return null;
}

/**
 * Log out: destroy session.
 */
function logout(): void {
    $_SESSION = [];
    session_destroy();
}

/**
 * Render the login page.
 */
function render_login_page(string $error = '', string $redirect = '/', string $prefill_email = ''): void {
    render_header('KapJus — Login');
    $safe_error    = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
    $safe_redirect = htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8');
    $safe_email    = htmlspecialchars($prefill_email, ENT_QUOTES, 'UTF-8');
    $error_html    = $safe_error
        ? '<div class="bg-red-50 border border-red-200 rounded-2xl px-4 py-3 text-sm font-medium text-red-700 flex items-center gap-2"><i class="fas fa-exclamation-circle"></i>' . $safe_error . '</div>'
        : '';
    echo <<<HTML
<div class="min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <a href="/" class="inline-flex items-center gap-3">
                <img src="https://kaponline.com.br/logo.jpeg" alt="KapOnline" class="h-12 w-12 rounded-2xl shadow-lg">
                <span class="text-3xl font-black tracking-tighter text-white">KAP<span class="text-indigo-400">JUS</span></span>
            </a>
            <p class="text-slate-400 mt-2 text-sm">Inteligência Artificial Jurídica</p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-3xl shadow-2xl p-8 space-y-6">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Entrar</h2>
                <p class="text-slate-500 text-sm mt-1">Acesse sua conta para continuar</p>
            </div>

            {$error_html}

            <form method="POST" action="/login" class="space-y-4">
                <input type="hidden" name="redirect" value="{$safe_redirect}">
                <input type="hidden" name="_csrf" value="{$_SESSION['_csrf_token']}">

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-widest mb-2">E-mail</label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="email" name="email" required autofocus
                            class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition-all"
                            placeholder="seu@email.com.br" value="{$safe_email}">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-widest mb-2">Senha</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="password" name="password" required
                            class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition-all"
                            placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 rounded-xl transition-all shadow-lg shadow-indigo-200 hover:scale-[1.01] active:scale-[0.99]">
                    <i class="fas fa-sign-in-alt mr-2"></i>Entrar
                </button>
            </form>
            
            <div class="text-center mt-4">
                <a href="/forgot-password" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                    <i class="fas fa-key mr-1"></i> Esqueci minha senha
                </a>
            </div>
        </div>

        <p class="text-center text-slate-500 text-xs mt-6">
            KapJus © <?= date('Y') ?> — Acesso restrito
        </p>
    </div>
</div>
HTML;
    render_footer();
    exit;
}
