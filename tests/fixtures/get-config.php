<?php
/**
 * Lit un ensemble de clés Configuration PrestaShop et les renvoie en JSON
 * sur stdout — utilisé par tests/fixtures.js (getConfig()) pour sauver
 * l'état actuel avant qu'un test ne le modifie temporairement (voir
 * withConfig()).
 *
 * Usage : php get-config.php /chemin/vers/cles.json
 * Le JSON en entrée est un tableau de noms de clés : ["NAVI_CLE", ...]
 * La sortie est un objet plat { "NAVI_CLE": "valeur ou null", ... }.
 */
chdir('/var/www/html');
require_once '/var/www/html/config/config.inc.php';

if (!isset($argv[1]) || !is_readable($argv[1])) {
    fwrite(STDERR, "Usage: php get-config.php <cles.json>\n");
    exit(1);
}

$keys = json_decode(file_get_contents($argv[1]), true);
if (!is_array($keys)) {
    fwrite(STDERR, "JSON invalide.\n");
    exit(1);
}

$result = [];
foreach ($keys as $key) {
    $value = Configuration::get($key);
    $result[$key] = $value === false ? null : $value;
}

echo json_encode($result);
