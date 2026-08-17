<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Correction du curseur "épaisseur" ajouté en 1.5.0 : contrôlait par
 * erreur une bordure CSS sur l'écran vidéo (.navi-story-phone-screen)
 * alors que l'utilisateur voulait régler le padding du mockup de
 * téléphone (.navi-story-phone) — c'est ce dernier qui donne
 * visuellement l'impression d'un cadre autour de la vidéo. Remplace
 * NAVI_STORIES_VIDEO_BORDER_WIDTH (retiré) par NAVI_STORIES_PHONE_PADDING.
 * Reprend la valeur déjà choisie par l'admin si elle existe, pour ne pas
 * silencieusement revenir à la valeur par défaut.
 */
function upgrade_module_1_5_1($module)
{
    $previousValue = Configuration::get('NAVI_STORIES_VIDEO_BORDER_WIDTH');

    if (Configuration::get('NAVI_STORIES_PHONE_PADDING') === false) {
        Configuration::updateValue(
            'NAVI_STORIES_PHONE_PADDING',
            $previousValue !== false ? $previousValue : Navi::DEFAULT_STORIES_PHONE_PADDING
        );
    }

    Configuration::deleteByName('NAVI_STORIES_VIDEO_BORDER_WIDTH');

    return true;
}
