<?php
// Exécuté SUR LE SERVEUR (via SSH, script lu sur stdin) avec le PHP 8.4 du site :
//   cd <dossier du site> && php8.4 < check-db.php
// Lit DATABASE_URL dans .env.local et tente une vraie connexion. N'affiche jamais le mot de passe.
$url = null;
foreach (@file('.env.local', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    if (preg_match('/^DATABASE_URL=(.*)$/', $line, $m)) {
        $url = trim($m[1], " \t\"'");
    }
}
if ($url === null) {
    fwrite(STDERR, "❌ DATABASE_URL absent de .env.local\n");
    exit(1);
}
$p = parse_url($url);
if ($p === false || empty($p['host']) || empty($p['path'])) {
    fwrite(STDERR, "❌ DATABASE_URL illisible (caractères spéciaux du mot de passe à encoder : @ -> %40, : -> %3A, / -> %2F, # -> %23)\n");
    exit(1);
}
$host = $p['host'];
$port = $p['port'] ?? 5432;
$user = rawurldecode($p['user'] ?? '');
$pass = rawurldecode($p['pass'] ?? '');
$db = ltrim($p['path'], '/');

echo "Connexion : utilisateur « $user », base « $db », hôte « $host:$port »\n";
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
    echo "⚠️  L'hôte n'est pas local : PostgreSQL n'accepte en général que 127.0.0.1 depuis le serveur lui-même (erreur « no pg_hba.conf entry » sinon).\n";
}
if ($user === 'postgres') {
    echo "⚠️  Utilisateur « postgres » (super-administrateur de tout le serveur) : créer un utilisateur dédié à cette base est recommandé.\n";
}
try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db;connect_timeout=8", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $version = $pdo->query('select version()')->fetchColumn();
    $tables = (int) $pdo->query("select count(*) from information_schema.tables where table_schema = 'public'")->fetchColumn();
    echo "✅ Connexion à la base OK — ".preg_replace('/ on .*/', '', (string) $version)." — $tables table(s) dans le schéma public\n";
} catch (Throwable $e) {
    // Le message de PDO ne contient pas le mot de passe ; on retire quand même l'éventuelle occurrence.
    fwrite(STDERR, '❌ Connexion impossible : '.str_replace($pass !== '' ? $pass : "\0", '***', preg_replace('/\s+/', ' ', $e->getMessage()))."\n");
    exit(1);
}
