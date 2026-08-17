<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Ajoute les clés de configuration introduites en 1.3.0 (bascules
 * "Afficher sur ordinateur/mobile" par fonctionnalité, titre des bulles
 * stories, épaisseur de leur bordure) sur les installations déjà en
 * place — install() ne s'exécute qu'à la toute première installation.
 * Toutes les bascules "Afficher" sont activées par défaut (comportement
 * identique à avant la mise à jour, rien ne se masque tant que l'admin ne
 * désactive pas explicitement une option).
 */
function upgrade_module_1_3_0($module)
{
    foreach (Navi::VISIBILITY_TOGGLES as $desktopKey => $info) {
        if (Configuration::get($desktopKey) === false) {
            Configuration::updateValue($desktopKey, '1');
        }
        if (Configuration::get($info['mobileKey']) === false) {
            Configuration::updateValue($info['mobileKey'], '1');
        }
    }

    if (Configuration::get('NAVI_MINICART_SHOW_DESKTOP') === false) {
        Configuration::updateValue('NAVI_MINICART_SHOW_DESKTOP', '1');
    }

    if (Configuration::get('NAVI_MINICART_SHOW_MOBILE') === false) {
        Configuration::updateValue('NAVI_MINICART_SHOW_MOBILE', '1');
    }

    if (Configuration::get('NAVI_STORIES_SHOW_LABEL') === false) {
        Configuration::updateValue('NAVI_STORIES_SHOW_LABEL', '1');
    }

    if (Configuration::get('NAVI_STORIES_BORDER_WIDTH') === false) {
        Configuration::updateValue('NAVI_STORIES_BORDER_WIDTH', Navi::DEFAULT_STORIES_BORDER_WIDTH);
    }

    return true;
}
