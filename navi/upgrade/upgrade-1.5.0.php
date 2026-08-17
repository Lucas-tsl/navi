<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Ajoute les clés de configuration introduites en 1.5.0 (épaisseur de la
 * bordure vidéo, taille du mockup de téléphone) sur les installations
 * déjà en place.
 */
function upgrade_module_1_5_0($module)
{
    if (Configuration::get('NAVI_STORIES_VIDEO_BORDER_WIDTH') === false) {
        Configuration::updateValue('NAVI_STORIES_VIDEO_BORDER_WIDTH', Navi::DEFAULT_STORIES_VIDEO_BORDER_WIDTH);
    }

    if (Configuration::get('NAVI_STORIES_PHONE_WIDTH') === false) {
        Configuration::updateValue('NAVI_STORIES_PHONE_WIDTH', Navi::DEFAULT_STORIES_PHONE_WIDTH);
    }

    return true;
}
