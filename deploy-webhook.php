<?php
// Token musí odpovídat hodnotě GitHub secret DEPLOY_SECRET.
// Po nahrání na server změň CHANGE_ME na náhodný řetězec.
define('DEPLOY_SECRET', 'CHANGE_ME');

if (($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '') !== DEPLOY_SECRET) {
    http_response_code(403);
    exit('Unauthorized');
}

$base_url = 'https://raw.githubusercontent.com/outly-jan/vlcci-svetylka/main/';
$log      = [];
$errors   = 0;

// Hlavní soubor pluginu
$url     = $base_url . 'vlcci-odborky.php';
$content = @file_get_contents($url);
if (!$content || strlen($content) < 10000) {
    http_response_code(500);
    exit('Download failed: vlcci-odborky.php');
}
file_put_contents(__DIR__ . '/vlcci-odborky.php', $content);
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__DIR__ . '/vlcci-odborky.php', true);
}
$log[] = 'vlcci-odborky.php — ' . strlen($content) . ' bytes';

// Obrázky odznaků
$images = [
    'ajtak.png', 'atlet.png', 'cestovatel.png', 'chovatel.jpg',
    'ctenar.png', 'cyklista.jpg', 'deskovkar.png', 'divadelnik.png',
    'fotograf.png', 'historik.png', 'hudebnik.png', 'hvezdar.jpg',
    'jazykar.png', 'kuchar.png', 'kutil.png', 'plavec.png',
    'polarnik.png', 'poutnik.png', 'prirodovedet.png', 'pruvodce.png',
    'reporter.png', 'rukodelkar.png', 'sberatel.png', 'sportovec.jpg',
    'tabornik.png', 'vodak.png', 'vytvarnik.png', 'zahradnik.png',
    'zdravotnik.jpg', 'zpevak.png',
    'jezisuvucednik.png', 'pruvodce-krestanskou-kulturou.png',
];

if (!is_dir(__DIR__ . '/images')) {
    mkdir(__DIR__ . '/images', 0755, true);
}

foreach ($images as $img) {
    $data = @file_get_contents($base_url . 'images/' . $img);
    if (!$data) {
        $log[] = 'CHYBA: images/' . $img;
        $errors++;
        continue;
    }
    file_put_contents(__DIR__ . '/images/' . $img, $data);
    $log[] = 'images/' . $img . ' — ' . strlen($data) . ' bytes';
}

// Plugin Skautská burza — samostatná složka vedle vlcci-svetylka/, soubory
// se stahují podle aktuálního obsahu repa (žádný natvrdo psaný seznam),
// ať se s přibývajícími částmi pluginu nemusí tenhle skript ručně rozšiřovat.
$burza_target = dirname(__DIR__) . '/skautska-burza';
$tree_context = stream_context_create([
    'http' => ['header' => "User-Agent: vlcci-svetylka-deploy\r\n"],
]);
$tree_json = @file_get_contents(
    'https://api.github.com/repos/outly-jan/vlcci-svetylka/git/trees/main?recursive=1',
    false,
    $tree_context
);
$tree = $tree_json ? json_decode($tree_json, true) : null;

if (!$tree || empty($tree['tree'])) {
    $log[] = 'CHYBA: nepodařilo se načíst seznam souborů skautska-burza/';
    $errors++;
} else {
    foreach ($tree['tree'] as $item) {
        if ($item['type'] !== 'blob') continue;
        if (strpos($item['path'], 'skautska-burza/') !== 0) continue;

        $relative = substr($item['path'], strlen('skautska-burza/'));
        if ($relative === '') continue;

        $data = @file_get_contents($base_url . $item['path']);
        if (!$data) {
            $log[] = 'CHYBA: skautska-burza/' . $relative;
            $errors++;
            continue;
        }

        $dest = $burza_target . '/' . $relative;
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0755, true);
        }
        file_put_contents($dest, $data);
        if (function_exists('opcache_invalidate') && str_ends_with($dest, '.php')) {
            opcache_invalidate($dest, true);
        }
        $log[] = 'skautska-burza/' . $relative . ' — ' . strlen($data) . ' bytes';
    }
}

$status = $errors === 0 ? 'OK' : "CHYBY: $errors";
echo $status . ' — ' . date('Y-m-d H:i:s') . "\n" . implode("\n", $log);
