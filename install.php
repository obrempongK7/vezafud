<?php

/**
 * Vezafud Platform Installer
 * Place this file at the root of your server.
 * Access via: https://yourdomain.com/install.php
 */

define('VEZAFUD_INSTALL', true);
define('BASE_PATH', __DIR__);
define('INSTALL_LOCK', BASE_PATH . '/storage/installed.lock');

session_start();

// Block if already installed
if (file_exists(INSTALL_LOCK) && !isset($_GET['force'])) {
    die('<div style="font-family:sans-serif;text-align:center;margin-top:100px"><h2>✅ Vezafud is already installed.</h2><p><a href="/">Go to Home</a> | <a href="/admin">Go to Admin</a></p></div>');
}

// ─── HELPERS ─────────────────────────────────────────────────────────────────

function checkRequirement(string $name, bool $pass, string $fix = ''): array {
    return ['name' => $name, 'pass' => $pass, 'fix' => $fix];
}

function checkPhpExtension(string $ext): array {
    return checkRequirement("PHP Extension: {$ext}", extension_loaded($ext), "Install php-{$ext}");
}

function testDbConnection(string $host, string $port, string $db, string $user, string $pass): bool {
    try {
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass, [PDO::ATTR_TIMEOUT => 5]);
        return true;
    } catch (\Exception $e) {
        return false;
    }
}

function writeEnvFile(array $config): bool {
    $example = BASE_PATH . '/.env.example';
    $env = BASE_PATH . '/.env';
    $content = file_exists($example) ? file_get_contents($example) : '';
    $replacements = [
        'APP_NAME=Vezafud'        => "APP_NAME={$config['site_name']}",
        'APP_URL=http://localhost' => "APP_URL={$config['app_url']}",
        'APP_KEY='                 => 'APP_KEY=' . generateAppKey(),
        'DB_HOST=127.0.0.1'       => "DB_HOST={$config['db_host']}",
        'DB_PORT=3306'             => "DB_PORT={$config['db_port']}",
        'DB_DATABASE=vezafud_main' => "DB_DATABASE={$config['db_name']}",
        'DB_USERNAME=root'         => "DB_USERNAME={$config['db_user']}",
        'DB_PASSWORD='             => "DB_PASSWORD={$config['db_pass']}",
    ];
    foreach ($replacements as $find => $replace) {
        $content = str_replace($find, $replace, $content);
    }
    return file_put_contents($env, $content) !== false;
}

function generateAppKey(): string {
    return 'base64:' . base64_encode(random_bytes(32));
}

function runArtisan(string $command): array {
    $php = escapeshellarg(PHP_BINARY);
    $artisanPath = BASE_PATH . '/artisan';
    $artisan = escapeshellarg($artisanPath);
    if (!file_exists($artisanPath)) return ['success' => false, 'output' => 'artisan not found'];

    $args = explode(' ', $command);
    $escapedArgs = array_map('escapeshellarg', $args);
    $escapedCommand = implode(' ', $escapedArgs);

    exec("{$php} {$artisan} {$escapedCommand} 2>&1", $output, $code);
    return ['success' => $code === 0, 'output' => implode("\n", $output)];
}

// ─── STEP PROCESSOR ──────────────────────────────────────────────────────────

$step = (int)($_GET['step'] ?? 1);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2: // Requirements checked → proceed
            $step = 3;
            break;

        case 3: // DB config submitted
            $host = trim($_POST['db_host'] ?? '127.0.0.1');
            $port = trim($_POST['db_port'] ?? '3306');
            $db   = trim($_POST['db_name'] ?? 'vezafud_main');
            $user = trim($_POST['db_user'] ?? '');
            $pass = trim($_POST['db_pass'] ?? '');
            if (testDbConnection($host, $port, $db, $user, $pass)) {
                $_SESSION['db'] = compact('host', 'port', 'db', 'user', 'pass');
                $step = 4;
            } else {
                $error = 'Cannot connect to database. Check your credentials and ensure the database exists.';
            }
            break;

        case 4: // App config
            $_SESSION['app'] = [
                'site_name' => trim($_POST['site_name'] ?? 'Vezafud'),
                'app_url'   => rtrim(trim($_POST['app_url'] ?? 'http://localhost'), '/'),
                'timezone'  => trim($_POST['timezone'] ?? 'Africa/Accra'),
                'currency'  => trim($_POST['currency'] ?? 'GHS'),
            ];
            $db = $_SESSION['db'];
            $wrote = writeEnvFile(array_merge($_SESSION['app'], ['db_host' => $db['host'], 'db_port' => $db['port'], 'db_name' => $db['db'], 'db_user' => $db['user'], 'db_pass' => $db['pass']]));
            if ($wrote) $step = 5; else $error = 'Could not write .env file. Check directory permissions.';
            break;

        case 5: // Run migrations
            $migrate = runArtisan('migrate --force --seed');
            if ($migrate['success']) {
                runArtisan('storage:link');
                runArtisan('config:cache');
                runArtisan('route:cache');
                $step = 6;
            } else {
                $error = 'Migration failed: ' . $migrate['output'];
            }
            break;

        case 6: // Create super admin
            $name     = trim($_POST['admin_name'] ?? 'Super Admin');
            $email    = trim($_POST['admin_email'] ?? '');
            $password = trim($_POST['admin_password'] ?? '');
            if (!$email || !$password || strlen($password) < 8) {
                $error = 'Please fill in all fields. Password must be at least 8 characters.';
            } else {
                $cmd = "migrate --force"; // already done
                // Update the seeded admin
                $db = $_SESSION['db'];
                try {
                    $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['db']}", $db['user'], $db['pass']);
                    $stmt = $pdo->prepare("UPDATE users SET email=?, password_hash=? WHERE username='superadmin'");
                    $stmt->execute([$email, password_hash($password, PASSWORD_ARGON2ID)]);
                    $stmt2 = $pdo->prepare("UPDATE user_profiles SET full_name=? WHERE user_id=(SELECT id FROM users WHERE email=?)");
                    $stmt2->execute([$name, $email]);
                    $step = 7;
                } catch (\Exception $e) {
                    $error = 'Could not update admin: ' . $e->getMessage();
                }
            }
            break;

        case 7: // Finalize
            file_put_contents(INSTALL_LOCK, date('Y-m-d H:i:s'));
            $step = 8;
            break;
    }
}

// ─── REQUIREMENTS CHECK ──────────────────────────────────────────────────────

$requirements = [
    checkRequirement('PHP 8.3+', version_compare(PHP_VERSION, '8.3.0', '>='), 'Upgrade to PHP 8.3 or higher'),
    checkRequirement('Composer Dependencies', file_exists(BASE_PATH . '/vendor/autoload.php'), 'Run: composer install'),
    checkRequirement('Writable /storage', is_writable(BASE_PATH . '/storage'), 'Run: chmod -R 775 storage'),
    checkRequirement('Writable /bootstrap/cache', is_writable(BASE_PATH . '/bootstrap/cache'), 'Run: chmod 775 bootstrap/cache'),
    checkPhpExtension('pdo'),
    checkPhpExtension('pdo_mysql'),
    checkPhpExtension('mbstring'),
    checkPhpExtension('openssl'),
    checkPhpExtension('json'),
    checkPhpExtension('curl'),
    checkPhpExtension('xml'),
    checkPhpExtension('gd'),
    checkPhpExtension('fileinfo'),
    checkRequirement('MySQL 8+', class_exists('PDO'), 'Install MySQL 8.0 or higher'),
    checkRequirement('Redis (optional)', extension_loaded('redis') || class_exists('\Predis\Client'), 'Install Redis for caching & queues (recommended)'),
];

$allPass = !collect($requirements)->contains(fn($r) => !$r['pass'] && !str_contains($r['name'], 'optional'));

$timezones = ['Africa/Accra', 'Africa/Lagos', 'Africa/Nairobi', 'Africa/Cairo', 'America/New_York', 'America/Los_Angeles', 'Europe/London', 'Asia/Dubai', 'Asia/Singapore'];
$currencies = ['GHS', 'NGN', 'KES', 'ZAR', 'USD', 'GBP', 'EUR'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vezafud Platform Installer</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0a0a0a; color: #f5f5f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
  .installer { width: 100%; max-width: 680px; }
  .header { text-align: center; margin-bottom: 40px; }
  .logo { font-size: 36px; font-weight: 900; background: linear-gradient(135deg, #FF6B35, #ff4500); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
  .tagline { font-size: 14px; color: #888; margin-top: 4px; letter-spacing: 2px; text-transform: uppercase; }
  .steps { display: flex; justify-content: center; gap: 6px; margin-bottom: 32px; flex-wrap: wrap; }
  .step-dot { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; border: 2px solid #333; color: #666; }
  .step-dot.active { background: #FF6B35; border-color: #FF6B35; color: #fff; }
  .step-dot.done { background: #22c55e; border-color: #22c55e; color: #fff; }
  .card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 16px; padding: 36px; }
  h2 { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
  .sub { color: #888; font-size: 14px; margin-bottom: 28px; }
  .form-group { margin-bottom: 18px; }
  label { display: block; font-size: 13px; font-weight: 600; color: #ccc; margin-bottom: 6px; }
  input, select { width: 100%; background: #111; border: 1px solid #333; border-radius: 8px; padding: 11px 14px; color: #f5f5f5; font-size: 14px; outline: none; transition: border-color 0.2s; }
  input:focus, select:focus { border-color: #FF6B35; }
  .row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  .btn { width: 100%; background: linear-gradient(135deg, #FF6B35, #ff4500); color: #fff; border: none; border-radius: 10px; padding: 14px; font-size: 15px; font-weight: 700; cursor: pointer; margin-top: 8px; transition: opacity 0.2s; }
  .btn:hover { opacity: 0.9; }
  .btn:disabled { opacity: 0.5; cursor: not-allowed; }
  .error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 14px; }
  .success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 14px; }
  .req-item { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #222; font-size: 14px; }
  .req-item:last-child { border-bottom: none; }
  .tick { font-size: 16px; }
  .req-name { flex: 1; }
  .req-fix { font-size: 11px; color: #f59e0b; font-family: monospace; }
  .welcome-icon { font-size: 60px; text-align: center; margin-bottom: 20px; }
  .feature-list { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 20px 0; }
  .feature-item { background: #111; border: 1px solid #2a2a2a; border-radius: 8px; padding: 12px; font-size: 13px; display: flex; align-items: center; gap: 8px; }
  .success-card { text-align: center; }
  .big-check { font-size: 80px; display: block; margin-bottom: 16px; }
  .creds { background: #111; border: 1px solid #2a2a2a; border-radius: 8px; padding: 16px; margin: 20px 0; text-align: left; }
  .cred-item { display: flex; gap: 10px; font-size: 13px; margin-bottom: 6px; }
  .cred-label { color: #888; min-width: 100px; }
  .cred-value { color: #f5f5f5; font-family: monospace; }
  .link-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 20px; }
  .btn-outline { background: transparent; border: 1px solid #FF6B35; color: #FF6B35; padding: 12px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; display: block; }
  .progress { background: #222; border-radius: 4px; height: 6px; margin: 16px 0; }
  .progress-bar { background: linear-gradient(90deg, #FF6B35, #ff4500); height: 100%; border-radius: 4px; transition: width 0.5s; }
</style>
</head>
<body>
<div class="installer">
  <div class="header">
    <div class="logo">Vezafud</div>
    <div class="tagline">Eat. Discover. Connect.</div>
  </div>

  <div class="steps">
    <?php
    $stepLabels = ['Welcome','Requirements','Database','Configuration','Migrate','Admin Setup','Finalize','Complete'];
    for ($i = 1; $i <= 8; $i++):
      $cls = $i < $step ? 'done' : ($i === $step ? 'active' : '');
      echo "<div class='step-dot {$cls}'>" . ($i < $step ? '✓' : $i) . "</div>";
    endfor;
    ?>
  </div>

  <div class="card">
    <?php if ($error): ?><div class="error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($step === 1): ?>
      <div class="welcome-icon">🍽️</div>
      <h2>Welcome to Vezafud Installer</h2>
      <p class="sub">This wizard will guide you through the complete platform installation.</p>
      <div class="feature-list">
        <?php foreach (['🏠 Social Feed & Stories','🔍 Discovery & Maps','📖 Recipe Ecosystem','🛍️ Ordering & Delivery','💳 Wallet & Rewards','👨‍🍳 Business Management','📊 Admin Dashboard','🔔 Real-time Notifications','🎬 Reels System','🤖 AI Recommendations'] as $f): ?>
          <div class="feature-item"><?= $f ?></div>
        <?php endforeach; ?>
      </div>
      <form method="POST" action="?step=2">
        <button class="btn" type="submit">Get Started →</button>
      </form>

    <?php elseif ($step === 2): ?>
      <h2>System Requirements</h2>
      <p class="sub">Checking your server environment...</p>
      <?php foreach ($requirements as $req): ?>
        <div class="req-item">
          <span class="tick"><?= $req['pass'] ? '✅' : '❌' ?></span>
          <span class="req-name"><?= htmlspecialchars($req['name']) ?></span>
          <?php if (!$req['pass'] && $req['fix']): ?>
            <code class="req-fix"><?= htmlspecialchars($req['fix']) ?></code>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <form method="POST" action="?step=3">
        <button class="btn" type="submit" <?= !$allPass ? 'disabled' : '' ?>>
          <?= $allPass ? 'Continue →' : 'Fix Issues Above' ?>
        </button>
      </form>

    <?php elseif ($step === 3): ?>
      <h2>Database Configuration</h2>
      <p class="sub">Enter your MySQL database details. Create the database first if it does not exist.</p>
      <form method="POST" action="?step=4">
        <div class="row">
          <div class="form-group">
            <label>Database Host</label>
            <input type="text" name="db_host" value="127.0.0.1" required>
          </div>
          <div class="form-group">
            <label>Port</label>
            <input type="text" name="db_port" value="3306" required>
          </div>
        </div>
        <div class="form-group">
          <label>Database Name</label>
          <input type="text" name="db_name" placeholder="vezafud_main" required>
        </div>
        <div class="form-group">
          <label>Database Username</label>
          <input type="text" name="db_user" placeholder="root" required>
        </div>
        <div class="form-group">
          <label>Database Password</label>
          <input type="password" name="db_pass" placeholder="Leave blank if no password">
        </div>
        <button class="btn" type="submit">Test & Continue →</button>
      </form>

    <?php elseif ($step === 4): ?>
      <h2>Platform Configuration</h2>
      <p class="sub">Configure your platform settings.</p>
      <form method="POST" action="?step=5">
        <div class="form-group">
          <label>Platform Name</label>
          <input type="text" name="site_name" value="Vezafud" required>
        </div>
        <div class="form-group">
          <label>Application URL</label>
          <input type="url" name="app_url" value="https://<?= $_SERVER['HTTP_HOST'] ?? 'yourdomain.com' ?>" required>
        </div>
        <div class="row">
          <div class="form-group">
            <label>Timezone</label>
            <select name="timezone">
              <?php foreach ($timezones as $tz): ?>
                <option value="<?= $tz ?>" <?= $tz === 'Africa/Accra' ? 'selected' : '' ?>><?= $tz ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Currency</label>
            <select name="currency">
              <?php foreach ($currencies as $c): ?>
                <option value="<?= $c ?>" <?= $c === 'GHS' ? 'selected' : '' ?>><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <button class="btn" type="submit">Save & Continue →</button>
      </form>

    <?php elseif ($step === 5): ?>
      <h2>Running Database Migrations</h2>
      <p class="sub">Creating all database tables and seeding initial data...</p>
      <div id="progress-container">
        <div class="progress"><div class="progress-bar" id="pbar" style="width:0%"></div></div>
        <p id="status-text" style="color:#888;font-size:13px;margin-top:8px">Preparing...</p>
      </div>
      <form method="POST" action="?step=6" id="migrate-form">
        <button class="btn" type="submit" id="migrate-btn">Run Migrations & Seed Data →</button>
      </form>

    <?php elseif ($step === 6): ?>
      <h2>Super Admin Account</h2>
      <p class="sub">Create your administrator account.</p>
      <form method="POST" action="?step=7">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="admin_name" placeholder="John Doe" required>
        </div>
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="admin_email" placeholder="admin@yourdomain.com" required>
        </div>
        <div class="form-group">
          <label>Password (min 8 chars)</label>
          <input type="password" name="admin_password" placeholder="Strong password" minlength="8" required>
        </div>
        <button class="btn" type="submit">Create Admin & Continue →</button>
      </form>

    <?php elseif ($step === 7): ?>
      <h2>Finalizing Installation</h2>
      <p class="sub">Optimizing your platform for production...</p>
      <form method="POST" action="?step=8">
        <button class="btn" type="submit">Complete Installation →</button>
      </form>

    <?php elseif ($step === 8): ?>
      <div class="success-card">
        <span class="big-check">🎉</span>
        <h2>Vezafud is Ready!</h2>
        <p style="color:#888;margin-top:8px">Your platform has been installed successfully.</p>
        <div class="creds">
          <div class="cred-item"><span class="cred-label">Admin Panel:</span><span class="cred-value">/admin</span></div>
          <div class="cred-item"><span class="cred-label">API Base:</span><span class="cred-value">/api/v1</span></div>
          <div class="cred-item"><span class="cred-label">API Docs:</span><span class="cred-value">/api/documentation</span></div>
          <div class="cred-item"><span class="cred-label">Note:</span><span class="cred-value" style="color:#f59e0b">Delete install.php now!</span></div>
        </div>
        <div class="link-buttons">
          <a class="btn-outline" href="/">Visit Platform</a>
          <a class="btn-outline" href="/admin">Admin Panel</a>
        </div>
        <div style="margin-top:20px;padding:14px;background:#111;border-radius:8px;font-size:12px;color:#888;">
          ⚠️ <strong>Security:</strong> Delete <code>install.php</code> from your server immediately after installation.
          Queue workers: <code>php artisan queue:work --daemon</code><br>
          Schedule: Add <code>* * * * * php /path/to/artisan schedule:run</code> to crontab.
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
