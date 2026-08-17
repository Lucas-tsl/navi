<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Ajoute les clés de configuration introduites en 1.4.0 (couleurs des
 * stories, position du bouton flottant) sur les installations déjà en
 * place.
 */
function upgrade_module_1_4_0($module)
{
    if (Configuration::get('NAVI_STORIES_COLOR_PHONE_BG') === false) {
        Configuration::updateValue('NAVI_STORIES_COLOR_PHONE_BG', Navi::DEFAULT_STORIES_PHONE_BG);
    }

    if (Configuration::get('NAVI_STORIES_COLOR_CLOSE_ICON') === false) {
        Configuration::updateValue('NAVI_STORIES_COLOR_CLOSE_ICON', Navi::DEFAULT_STORIES_CLOSE_ICON);
    }

    if (Configuration::get('NAVI_STORIES_COLOR_CLOSE_BG') === false) {
        Configuration::updateValue('NAVI_STORIES_COLOR_CLOSE_BG', Navi::DEFAULT_STORIES_CLOSE_BG);
    }

    if (Configuration::get('NAVI_STORIES_COLOR_OVERLAY') === false) {
        Configuration::updateValue('NAVI_STORIES_COLOR_OVERLAY', Navi::DEFAULT_STORIES_OVERLAY_BG);
    }

    if (Configuration::get('NAVI_FAB_POSITION') === false) {
        Configuration::updateValue('NAVI_FAB_POSITION', Navi::DEFAULT_FAB_POSITION);
    }

    return true;
}
