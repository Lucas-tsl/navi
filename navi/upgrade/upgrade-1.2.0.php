<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Ajoute les clés de configuration introduites en 1.2.0 (mini-panier,
 * couleurs/arrondis) sur les installations déjà en place — install() ne
 * s'exécute qu'à la toute première installation, pas au moment d'une mise
 * à jour de version.
 */
function upgrade_module_1_2_0($module)
{
    if (Configuration::get('NAVI_MINICART_ENABLED') === false) {
        Configuration::updateValue('NAVI_MINICART_ENABLED', '0');
    }

    if (!Configuration::get('NAVI_COLOR_ACCENT')) {
        Configuration::updateValue('NAVI_COLOR_ACCENT', Navi::DEFAULT_COLOR_ACCENT);
    }

    if (!Configuration::get('NAVI_COLOR_ACCENT_DEEP')) {
        Configuration::updateValue('NAVI_COLOR_ACCENT_DEEP', Navi::DEFAULT_COLOR_ACCENT_DEEP);
    }

    if (Configuration::get('NAVI_RADIUS_BUTTON') === false) {
        Configuration::updateValue('NAVI_RADIUS_BUTTON', Navi::DEFAULT_RADIUS_BUTTON);
    }

    if (Configuration::get('NAVI_RADIUS_IMAGE') === false) {
        Configuration::updateValue('NAVI_RADIUS_IMAGE', Navi::DEFAULT_RADIUS_IMAGE);
    }

    return true;
}
