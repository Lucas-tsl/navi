<?php
/**
 * Applique un ensemble de clés Configuration PrestaShop depuis un fichier
 * JSON — utilisé par tests/fixtures.js (setConfig()) pour piloter des
 * réglages du module Navi sans passer par le Back Office (pas
 * d'identifiants admin disponibles pour Playwright, voir tests/README.md).
 *
 * Usage : php set-config.php /chemin/vers/valeurs.json
 * Le JSON est un simple objet plat { "NAVI_CLE": "valeur", ... }.
 */
chdir('/var/www/html');
require_once '/var/www/html/config/config.inc.php';

if (!isset($argv[1]) || !is_readable($argv[1])) {
    fwrite(STDERR, "Usage: php set-config.php <fichier.json>\n");
    exit(1);
}

$data = json_decode(file_get_contents($argv[1]), true);
if (!is_array($data)) {
    fwrite(STDERR, "JSON invalide.\n");
    exit(1);
}

foreach ($data as $key => $value) {
    Configuration::updateValue($key, $value);
}

echo "OK\n";
