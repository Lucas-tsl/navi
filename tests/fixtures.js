const fs = require('fs');
const os = require('os');
const path = require('path');
const { execSync } = require('child_process');

// Identifiants produit utilisés par les tests — dépendent entièrement du
// catalogue de l'environnement contre lequel la suite tourne, configurables
// par variable d'environnement (voir README.md de ce dossier). Les valeurs
// par défaut correspondent à des produits réels de l'instance de dev locale
// (presta_web) au moment de l'écriture ; les specs se `skip()` proprement si
// le produit configuré n'a plus le contenu attendu (catalogue vivant, peut
// changer). Ne pas remettre 1/2 : ces ID génériques ne résolvent plus sur ce
// catalogue (404) et faisaient échouer silencieusement les tests dépendant
// d'un vrai bouton "Ajouter au panier" (ex. mini-cart.spec.js) plutôt que de
// les skip proprement.
const PRODUCT_IN_STOCK = process.env.NAVI_TEST_PRODUCT_IN_STOCK || '134';
const PRODUCT_OUT_OF_STOCK = process.env.NAVI_TEST_PRODUCT_OUT_OF_STOCK || '137';

function productUrl(idProduct) {
    return '/index.php?controller=product&id_product=' + idProduct;
}

// Accepte la bannière cookie si présente — la plupart des specs doivent
// commencer par ça pour ne pas avoir la bannière au-dessus du reste.
// #navi-cookie-btn-accepter (ID, pas la classe) : la classe
// `navi-cookie-btn-accepter` est réutilisée pour le style sur un AUTRE
// bouton ("Enregistrer mes choix" dans la modale préférences) — un
// sélecteur par classe matcherait les deux.
async function acceptCookies(page) {
    const btn = page.locator('#navi-cookie-btn-accepter');
    if (await btn.count()) {
        await btn.click();
    }
}

// ============================================================
// Fixtures de configuration — pilotent des réglages du module via la base
// (docker exec + un petit script PHP dédié, voir tests/fixtures/*.php)
// plutôt que par le Back Office : la suite n'a pas d'identifiants admin
// pour se connecter et cliquer dans Configurer (voir README.md). Se
// dégrade proprement (dockerFixturesAvailable() → false, tests concernés
// skip()) si docker n'est pas accessible depuis l'environnement qui lance
// les tests — un contributeur sans le docker-compose de ce projet peut
// toujours lancer le reste de la suite.
// ============================================================
const DOCKER_CONTAINER = process.env.NAVI_TEST_DOCKER_CONTAINER || 'presta_web';
const THEME_NAME = process.env.NAVI_TEST_THEME_NAME || 'physiomins';
const FIXTURES_DIR = path.join(__dirname, 'fixtures');

let dockerAvailableCache = null;

function dockerFixturesAvailable() {
    if (dockerAvailableCache !== null) return dockerAvailableCache;
    try {
        execSync(`docker exec ${DOCKER_CONTAINER} echo ok`, { stdio: 'ignore' });
        dockerAvailableCache = true;
    } catch (e) {
        dockerAvailableCache = false;
    }
    return dockerAvailableCache;
}

function writeTempJson(data) {
    const file = path.join(os.tmpdir(), 'navi-test-' + Date.now() + '-' + Math.random().toString(36).slice(2) + '.json');
    fs.writeFileSync(file, JSON.stringify(data));
    return file;
}

function runFixturePhp(scriptName, jsonFile) {
    // Chemin du script CÔTÉ CONTENEUR unique par appel (suffixe repris du
    // fichier JSON temporaire) : des tests tournant en parallèle (voir
    // playwright.config.js) appelaient sinon tous le même chemin fixe
    // /tmp/navi-<scriptName>, et un test qui terminait supprimait (finally)
    // le script qu'un autre test, encore en cours, était en train
    // d'exécuter — "Command failed: docker exec ... php /tmp/navi-set-config.php"
    // intermittent, pas un bug du fixture PHP lui-même.
    const uniqueSuffix = path.basename(jsonFile).replace(/^navi-test-/, '').replace(/\.json$/, '');
    const containerJson = '/tmp/' + path.basename(jsonFile);
    const containerScript = '/tmp/navi-' + uniqueSuffix + '-' + scriptName;
    execSync(`docker cp "${jsonFile}" ${DOCKER_CONTAINER}:${containerJson}`);
    execSync(`docker cp "${path.join(FIXTURES_DIR, scriptName)}" ${DOCKER_CONTAINER}:${containerScript}`);
    try {
        return execSync(`docker exec -u www-data ${DOCKER_CONTAINER} php ${containerScript} ${containerJson}`).toString();
    } finally {
        execSync(`docker exec ${DOCKER_CONTAINER} rm -f ${containerJson} ${containerScript}`);
        fs.unlinkSync(jsonFile);
    }
}

// Vide le cache CSS/JS combiné du thème (Combine/Compress/Cache) — une
// modification de Configuration ne se reflète dans le front qu'après ça,
// sinon la page continue de servir un bundle qui date d'avant le
// changement (voir memory du projet : "feedback-cache-clearing-caution").
function clearThemeCache() {
    execSync(`docker exec ${DOCKER_CONTAINER} sh -c "rm -f /var/www/html/themes/${THEME_NAME}/assets/cache/*.css /var/www/html/themes/${THEME_NAME}/assets/cache/*.js"`);
}

function getConfig(keys) {
    const jsonFile = writeTempJson(keys);
    const output = runFixturePhp('get-config.php', jsonFile);
    return JSON.parse(output);
}

function setConfig(values) {
    const jsonFile = writeTempJson(values);
    runFixturePhp('set-config.php', jsonFile);
    clearThemeCache();
}

// Sauvegarde l'état actuel des clés concernées, applique `values`, exécute
// `fn`, puis restaure l'état d'origine — même en cas d'échec du test. Cette
// instance Docker est le vrai environnement de dev de l'auteur (pas un
// bac à sable jetable) : un test qui modifierait un réglage sans le
// remettre en place laisserait le site dans un état de test après coup.
async function withConfig(values, fn) {
    const keys = Object.keys(values);
    const previous = getConfig(keys);
    setConfig(values);
    try {
        await fn();
    } finally {
        setConfig(previous);
    }
}

module.exports = {
    PRODUCT_IN_STOCK,
    PRODUCT_OUT_OF_STOCK,
    productUrl,
    acceptCookies,
    dockerFixturesAvailable,
    getConfig,
    setConfig,
    withConfig,
    clearThemeCache,
};
