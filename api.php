<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

require_once 'config.php';

// ─── HELPERS ─────────────────────────────────────────────
function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getBody() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

// ─── BANCO DE DADOS ───────────────────────────────────────
function getDB() {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    static $pdo = null;
    if (!$pdo) {
        try {
            $pdo = new PDO(
                "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
                $DB_USER, $DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            respond(['error' => 'Erro de conexão com o banco. Verifique o config.php.'], 500);
        }
    }
    return $pdo;
}

// ─── INICIALIZAÇÃO (cria admins se não existir) ───────────
function initAdmins() {
    $db = getDB();
    $count = $db->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    if ($count == 0) {
        $stmt = $db->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
        $stmt->execute(['admin',    password_hash('ifce2026',      PASSWORD_BCRYPT)]);
        $stmt->execute(['eleudson', password_hash('academia2026@', PASSWORD_BCRYPT)]);
    }
}
initAdmins();

// ─── JWT ─────────────────────────────────────────────────
function b64url($str) {
    return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
}
function b64urlDecode($str) {
    return base64_decode(strtr($str, '-_', '+/'));
}

function jwtCreate($payload) {
    global $JWT_SECRET;
    $header  = b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['exp'] = time() + 12 * 3600;
    $payload = b64url(json_encode($payload));
    $sig     = b64url(hash_hmac('sha256', "$header.$payload", $JWT_SECRET, true));
    return "$header.$payload.$sig";
}

function jwtVerify($token) {
    global $JWT_SECRET;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h, $p, $s] = $parts;
    $expected = b64url(hash_hmac('sha256', "$h.$p", $JWT_SECRET, true));
    if (!hash_equals($expected, $s)) return null;
    $data = json_decode(b64urlDecode($p), true);
    if (!$data || ($data['exp'] ?? 0) < time()) return null;
    return $data;
}

// ─── AUTH MIDDLEWARE ─────────────────────────────────────
function requireAuth() {
    // Tenta pegar o token do header Authorization
    $header = $_SERVER['HTTP_AUTHORIZATION']
           ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
           ?? getallheaders()['Authorization']
           ?? '';

    if (!str_starts_with($header, 'Bearer ')) {
        respond(['error' => 'Token ausente'], 401);
    }
    $user = jwtVerify(substr($header, 7));
    if (!$user) respond(['error' => 'Token inválido ou expirado'], 401);
    return $user;
}

// ─── RATE LIMITING ───────────────────────────────────────
function getIP() {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return trim(explode(',', $ip)[0]);
}

function checkRateLimit($ip) {
    $db = getDB();
    $stmt = $db->prepare("SELECT attempt_count, blocked_until FROM login_attempts WHERE ip = ?");
    $stmt->execute([$ip]);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$rec) return ['blocked' => false];

    if ($rec['blocked_until'] && strtotime($rec['blocked_until']) > time()) {
        $mins = ceil((strtotime($rec['blocked_until']) - time()) / 60);
        return ['blocked' => true, 'minutesLeft' => max(1, $mins)];
    }
    return ['blocked' => false];
}

function registerFail($ip) {
    $db   = getDB();
    $max  = 5;
    $mins = 5;

    $stmt = $db->prepare("SELECT attempt_count FROM login_attempts WHERE ip = ?");
    $stmt->execute([$ip]);
    $rec   = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = ($rec ? (int)$rec['attempt_count'] : 0) + 1;
    $blocked = $count >= $max ? date('Y-m-d H:i:s', time() + $mins * 60) : null;

    if ($rec) {
        $db->prepare("UPDATE login_attempts SET attempt_count=?, blocked_until=? WHERE ip=?")
           ->execute([$count, $blocked, $ip]);
    } else {
        $db->prepare("INSERT INTO login_attempts (ip, attempt_count, blocked_until) VALUES (?,?,?)")
           ->execute([$ip, $count, $blocked]);
    }
    return $count;
}

function clearFails($ip) {
    getDB()->prepare("DELETE FROM login_attempts WHERE ip=?")->execute([$ip]);
}

// ─── SLUG ─────────────────────────────────────────────────
function toSlug($str) {
    $str = mb_strtolower($str, 'UTF-8');
    $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n'];
    $str = strtr($str, $map);
    $str = preg_replace('/[^a-z0-9]+/', '-', $str);
    return trim($str, '-');
}

// ─── ROUTING ─────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = preg_replace('#^.*/api#', '', $uri);
$uri    = rtrim($uri, '/') ?: '/';

try {

    // ── POST /auth/login ──────────────────────────────────
    if ($method === 'POST' && $uri === '/auth/login') {
        $b  = getBody();
        $un = trim($b['username'] ?? '');
        $pw = $b['password'] ?? '';
        if (!$un || !$pw) respond(['error' => 'Campos obrigatórios'], 400);

        $ip    = getIP();
        $limit = checkRateLimit($ip);
        if ($limit['blocked']) {
            respond(['error' => "Muitas tentativas incorretas. Tente novamente em {$limit['minutesLeft']} minuto(s)."], 429);
        }

        $stmt = getDB()->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$un]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin || !password_verify($pw, $admin['password'])) {
            $attempts  = registerFail($ip);
            $remaining = 5 - $attempts;
            if ($remaining <= 0) {
                respond(['error' => 'Conta bloqueada por 5 minutos após muitas tentativas incorretas.'], 429);
            }
            respond(['error' => "Usuário ou senha incorretos. {$remaining} tentativa(s) restante(s)."], 401);
        }

        clearFails($ip);
        $token = jwtCreate(['id' => (int)$admin['id'], 'username' => $admin['username']]);
        respond(['token' => $token, 'username' => $admin['username']]);
    }

    // ── PUT /auth/password ────────────────────────────────
    if ($method === 'PUT' && $uri === '/auth/password') {
        $user = requireAuth();
        $b    = getBody();
        $stmt = getDB()->prepare("SELECT * FROM admins WHERE id = ?");
        $stmt->execute([$user['id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($b['currentPassword'] ?? '', $admin['password'])) {
            respond(['error' => 'Senha atual incorreta'], 401);
        }
        if (strlen($b['newPassword'] ?? '') < 6) {
            respond(['error' => 'Nova senha deve ter ao menos 6 caracteres'], 400);
        }
        getDB()->prepare("UPDATE admins SET password=? WHERE id=?")
               ->execute([password_hash($b['newPassword'], PASSWORD_BCRYPT), $user['id']]);
        respond(['success' => true]);
    }

    // ── GET /projects ─────────────────────────────────────
    if ($method === 'GET' && $uri === '/projects') {
        $rows = getDB()->query("SELECT * FROM projects ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        respond(array_map('parseProject', $rows));
    }

    // ── GET /projects/{id} ────────────────────────────────
    if ($method === 'GET' && preg_match('#^/projects/(\d+)$#', $uri, $m)) {
        $stmt = getDB()->prepare("SELECT * FROM projects WHERE id=?");
        $stmt->execute([$m[1]]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) respond(['error' => 'Projeto não encontrado'], 404);
        respond(parseProject($p));
    }

    // ── POST /projects ────────────────────────────────────
    if ($method === 'POST' && $uri === '/projects') {
        requireAuth();
        $b = getBody();
        if (empty($b['title']) || empty($b['team']) || empty($b['year'])) {
            respond(['error' => 'Título, equipe e ano são obrigatórios'], 400);
        }
        $db = getDB();
        $db->prepare("INSERT INTO projects (year,title,team,members,period,emoji,gradient,chips,tags,description,how_text,impact_data,files) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([
               $b['year'], $b['title'], $b['team'],
               $b['members'] ?? 1, $b['period'] ?? '',
               $b['emoji']    ?? '📚',
               $b['gradient'] ?? 'linear-gradient(135deg,#1A6B42,#2563EB)',
               json_encode($b['chips'] ?? [], JSON_UNESCAPED_UNICODE),
               json_encode($b['tags']  ?? [], JSON_UNESCAPED_UNICODE),
               $b['desc'] ?? '', $b['how'] ?? '',
               json_encode($b['data']  ?? [], JSON_UNESCAPED_UNICODE),
               json_encode($b['files'] ?? [], JSON_UNESCAPED_UNICODE),
           ]);
        respond(['id' => (int)$db->lastInsertId(), 'success' => true], 201);
    }

    // ── PUT /projects/{id} ────────────────────────────────
    if ($method === 'PUT' && preg_match('#^/projects/(\d+)$#', $uri, $m)) {
        requireAuth();
        $b  = getBody();
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM projects WHERE id=?");
        $stmt->execute([$m[1]]);
        if (!$stmt->fetch()) respond(['error' => 'Projeto não encontrado'], 404);

        $db->prepare("UPDATE projects SET year=?,title=?,team=?,members=?,period=?,emoji=?,gradient=?,chips=?,tags=?,description=?,how_text=?,impact_data=?,files=? WHERE id=?")
           ->execute([
               $b['year'], $b['title'], $b['team'],
               $b['members'], $b['period'], $b['emoji'], $b['gradient'],
               json_encode($b['chips'] ?? [], JSON_UNESCAPED_UNICODE),
               json_encode($b['tags']  ?? [], JSON_UNESCAPED_UNICODE),
               $b['desc'] ?? '', $b['how'] ?? '',
               json_encode($b['data']  ?? [], JSON_UNESCAPED_UNICODE),
               json_encode($b['files'] ?? [], JSON_UNESCAPED_UNICODE),
               $m[1]
           ]);
        respond(['success' => true]);
    }

    // ── DELETE /projects/{id} ─────────────────────────────
    if ($method === 'DELETE' && preg_match('#^/projects/(\d+)$#', $uri, $m)) {
        requireAuth();
        $db   = getDB();
        $stmt = $db->prepare("SELECT id FROM projects WHERE id=?");
        $stmt->execute([$m[1]]);
        if (!$stmt->fetch()) respond(['error' => 'Projeto não encontrado'], 404);
        $db->prepare("DELETE FROM projects WHERE id=?")->execute([$m[1]]);
        respond(['success' => true]);
    }

    // ── GET /stats ────────────────────────────────────────
    if ($method === 'GET' && $uri === '/stats') {
        $rows   = getDB()->query("SELECT team, impact_data, files FROM projects")->fetchAll(PDO::FETCH_ASSOC);
        $teams  = count(array_unique(array_column($rows, 'team')));
        $files  = array_sum(array_map(fn($r) => count(json_decode($r['files'], true) ?? []), $rows));
        $impact = array_sum(array_map(function($r) {
            $d   = json_decode($r['impact_data'], true) ?? [];
            $val = preg_replace('/\D/', '', $d[0]['v'] ?? '');
            return (int)$val;
        }, $rows));
        respond(['projects' => count($rows), 'teams' => $teams, 'files' => $files, 'impact' => $impact]);
    }

    // ── GET /tags ─────────────────────────────────────────
    if ($method === 'GET' && $uri === '/tags') {
        $tags = getDB()->query("SELECT slug, label FROM tags ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        respond($tags);
    }

    // ── POST /tags ────────────────────────────────────────
    if ($method === 'POST' && $uri === '/tags') {
        requireAuth();
        $label = trim(getBody()['label'] ?? '');
        if (!$label) respond(['error' => 'Nome da categoria é obrigatório'], 400);
        $slug = toSlug($label);
        $db   = getDB();
        $stmt = $db->prepare("SELECT id FROM tags WHERE slug=?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) respond(['error' => 'Já existe uma categoria com esse nome'], 409);
        $db->prepare("INSERT INTO tags (slug, label) VALUES (?,?)")->execute([$slug, $label]);
        respond(['slug' => $slug, 'label' => $label], 201);
    }

    // ── DELETE /tags/{slug} ───────────────────────────────
    if ($method === 'DELETE' && preg_match('#^/tags/([^/]+)$#', $uri, $m)) {
        requireAuth();
        $db   = getDB();
        $stmt = $db->prepare("SELECT id FROM tags WHERE slug=?");
        $stmt->execute([$m[1]]);
        if (!$stmt->fetch()) respond(['error' => 'Categoria não encontrada'], 404);
        $db->prepare("DELETE FROM tags WHERE slug=?")->execute([$m[1]]);
        respond(['success' => true]);
    }

    respond(['error' => 'Rota não encontrada'], 404);

} catch (PDOException $e) {
    respond(['error' => 'Erro no banco de dados: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    respond(['error' => 'Erro interno: ' . $e->getMessage()], 500);
}

// ─── HELPER: formata projeto para resposta ────────────────
function parseProject($p) {
    return [
        'id'       => (int)$p['id'],
        'year'     => $p['year'],
        'title'    => $p['title'],
        'team'     => $p['team'],
        'members'  => (int)$p['members'],
        'period'   => $p['period'],
        'emoji'    => $p['emoji'],
        'gradient' => $p['gradient'],
        'chips'    => json_decode($p['chips'],       true) ?? [],
        'tags'     => json_decode($p['tags'],        true) ?? [],
        'desc'     => $p['description'],
        'how'      => $p['how_text'],
        'data'     => json_decode($p['impact_data'], true) ?? [],
        'files'    => json_decode($p['files'],       true) ?? [],
    ];
}
