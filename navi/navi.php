<?php
/**
 * Navi — hub d'engagement flottant pour PrestaShop : un seul bouton
 * flottant qui regroupe plusieurs modules d'engagement client (consentement
 * cookies, accessibilité, panier sticky, stories vidéo produit), chacun
 * s'affichant dans le même objet visuel plutôt que comme des widgets
 * indépendants (voir views/js/core.js).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Navi extends Module
{
    const STORIES_TABLE = 'navi_story';
    const STORY_LIMIT = 4;
    const UPLOAD_SUBDIR = 'views/uploads';
    const MAX_UPLOAD_BYTES = 20971520; // 20 Mo

    const DEFAULT_COLOR_ACCENT = '#2563eb';
    const DEFAULT_COLOR_ACCENT_DEEP = '#1e40af';
    const DEFAULT_RADIUS_BUTTON = '4';
    const DEFAULT_RADIUS_IMAGE = '4';
    const DEFAULT_STORIES_BORDER_WIDTH = '2';
    const DEFAULT_STORIES_PHONE_BG = '#111111';
    const DEFAULT_STORIES_CLOSE_ICON = '#ffffff';
    const DEFAULT_STORIES_CLOSE_BG = '#000000';
    const DEFAULT_STORIES_OVERLAY_BG = '#000000';
    const DEFAULT_FAB_POSITION = 'right';
    const REPO_URL = 'https://github.com/Lucas-tsl/navi';

    const DEFAULT_STORIES_PHONE_PADDING = '10';
    const DEFAULT_STORIES_PHONE_WIDTH = '200';
    const MIN_STORIES_PHONE_WIDTH = 150;
    const MAX_STORIES_PHONE_WIDTH = 280;
    const MAX_STORIES_PHONE_PADDING = 20;

    /**
     * Bascules "Afficher sur ordinateur / mobile" partagées par toutes les
     * fonctionnalités pilotées depuis le bouton flottant — un seul endroit
     * pour ajouter/retirer une fonctionnalité de ce mécanisme (defaults à
     * l'installation, suppression à la désinstallation, sauvegarde
     * BO, génération des règles @media, voir getVisibilityStyleRules()).
     * Le mini-panier (comportement, pas un élément du menu de l'engrenage)
     * suit sa propre logique côté JS, voir NAVI_MINICART_SHOW_*.
     */
    const VISIBILITY_TOGGLES = [
        'NAVI_COOKIE_SHOW_DESKTOP' => [
            'mobileKey' => 'NAVI_COOKIE_SHOW_MOBILE',
            'selector' => '#navi-cookie-banner, .navi-fab-item[data-item-id="cookie-consent"]',
        ],
        'NAVI_A11Y_SHOW_DESKTOP' => [
            'mobileKey' => 'NAVI_A11Y_SHOW_MOBILE',
            'selector' => '.navi-fab-item[data-item-id="accessibility"]',
        ],
        'NAVI_STICKYCART_SHOW_DESKTOP' => [
            'mobileKey' => 'NAVI_STICKYCART_SHOW_MOBILE',
            'selector' => '#navi-sticky-bar, .navi-fab-item[data-item-id="sticky-cart"]',
        ],
        'NAVI_STORIES_SHOW_DESKTOP' => [
            'mobileKey' => 'NAVI_STORIES_SHOW_MOBILE',
            'selector' => '.navi-story-row',
        ],
    ];

    public function __construct()
    {
        $this->name = 'navi';
        $this->tab = 'front_office_features';
        $this->version = '1.5.1';
        $this->author = 'Troteseil Lucas';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Navi');
        $this->description = $this->l(
            "Hub d'engagement flottant : consentement cookies (Google Consent " .
            'Mode v2), accessibilité (taille du texte, contraste, curseur), ' .
            'ajout au panier sticky, bulles vidéo "stories" sur les fiches ' .
            'produit et mini-panier automatique, pilotés depuis un bouton ' .
            'unique. Couleurs et arrondis personnalisables.'
        );
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => '8.99.99'];

        $this->confirmUninstall = $this->l('Supprimer ce module désactivera le bouton flottant, tous ses modules, et supprimera les stories enregistrées (vidéos incluses).');
    }

    /**
     * Hooks utilisés :
     * - displayHeader : pose gtag('consent','default', ...) le plus tôt
     *   possible dans <head>, AVANT que Google Analytics ne charge.
     * - actionFrontControllerSetMedia : enregistrement CSS/JS front-office.
     * - displayBeforeBodyClosingTag : coquille du bouton flottant.
     * - displayAdminProductsExtra : onglet de gestion des stories sur la
     *   fiche produit (Back Office).
     * - actionObjectProductAddAfter / actionObjectProductUpdateAfter :
     *   sauvegarde des stories soumises avec le formulaire produit — AUCUN
     *   contrôleur front dédié : la sauvegarde n'est possible qu'au travers
     *   d'un enregistrement produit réel, donc déjà protégée par la session
     *   employé et le jeton CSRF que PrestaShop applique lui-même à ce
     *   formulaire (voir handleProductSave()).
     * - actionObjectProductDeleteAfter : nettoyage des stories orphelines.
     * - displayAfterProductThumbs : rendu des bulles en fiche produit (un
     *   seul hook de rendu, pas deux — évite le double-affichage).
     */
    const HOOKS = [
        'displayHeader',
        'actionFrontControllerSetMedia',
        'displayBeforeBodyClosingTag',
        'displayAdminProductsExtra',
        'actionObjectProductAddAfter',
        'actionObjectProductUpdateAfter',
        'actionObjectProductDeleteAfter',
        'displayAfterProductThumbs',
    ];

    /**
     * Chaque étape s'exécute indépendamment (pas de chaîne && qui
     * s'arrêterait silencieusement à la première étape en échec) : sur un
     * shop avec beaucoup de modules déjà installés, l'enregistrement d'un
     * hook peut occasionnellement échouer sans lever d'exception PHP
     * exploitable (observé en conditions réelles) — mieux vaut tenter
     * toutes les étapes et remonter une erreur visible dans le Back Office
     * que de laisser un module à moitié installé sans aucun signal. Voir
     * aussi ensureFullyInstalled(), qui complète automatiquement ce qui
     * manquerait encore au prochain accès à Configurer.
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        $ok = true;

        foreach (self::HOOKS as $hookName) {
            if (!$this->registerHook($hookName)) {
                $this->_errors[] = sprintf('Navi: failed to register hook "%s".', $hookName);
                $ok = false;
            }
        }

        if (!$this->installStoriesTable()) {
            $this->_errors[] = $this->l('Navi : échec de la création de la table des stories.');
            $ok = false;
        }

        if (!$this->installUploadDir()) {
            $this->_errors[] = $this->l("Navi : échec de la création du dossier d'upload.");
            $ok = false;
        }

        Configuration::updateValue('NAVI_COOKIE_TEXT', $this->getDefaultCookieText());
        Configuration::updateValue('NAVI_COOKIE_PRIVACY_URL', '');
        Configuration::updateValue('NAVI_COOKIE_LEGAL_URL', '');
        Configuration::updateValue('NAVI_COOKIE_LOGO_URL', $this->getDefaultLogoUrl());
        Configuration::updateValue('NAVI_MINICART_ENABLED', '0');
        Configuration::updateValue('NAVI_MINICART_SHOW_DESKTOP', '1');
        Configuration::updateValue('NAVI_MINICART_SHOW_MOBILE', '1');
        Configuration::updateValue('NAVI_COLOR_ACCENT', self::DEFAULT_COLOR_ACCENT);
        Configuration::updateValue('NAVI_COLOR_ACCENT_DEEP', self::DEFAULT_COLOR_ACCENT_DEEP);
        Configuration::updateValue('NAVI_RADIUS_BUTTON', self::DEFAULT_RADIUS_BUTTON);
        Configuration::updateValue('NAVI_RADIUS_IMAGE', self::DEFAULT_RADIUS_IMAGE);
        Configuration::updateValue('NAVI_STORIES_SHOW_LABEL', '1');
        Configuration::updateValue('NAVI_STORIES_BORDER_WIDTH', self::DEFAULT_STORIES_BORDER_WIDTH);
        Configuration::updateValue('NAVI_STORIES_COLOR_PHONE_BG', self::DEFAULT_STORIES_PHONE_BG);
        Configuration::updateValue('NAVI_STORIES_COLOR_CLOSE_ICON', self::DEFAULT_STORIES_CLOSE_ICON);
        Configuration::updateValue('NAVI_STORIES_COLOR_CLOSE_BG', self::DEFAULT_STORIES_CLOSE_BG);
        Configuration::updateValue('NAVI_STORIES_COLOR_OVERLAY', self::DEFAULT_STORIES_OVERLAY_BG);
        Configuration::updateValue('NAVI_FAB_POSITION', self::DEFAULT_FAB_POSITION);
        Configuration::updateValue('NAVI_STORIES_PHONE_PADDING', self::DEFAULT_STORIES_PHONE_PADDING);
        Configuration::updateValue('NAVI_STORIES_PHONE_WIDTH', self::DEFAULT_STORIES_PHONE_WIDTH);

        foreach (self::VISIBILITY_TOGGLES as $desktopKey => $info) {
            Configuration::updateValue($desktopKey, '1');
            Configuration::updateValue($info['mobileKey'], '1');
        }

        return $ok;
    }

    /**
     * Filet de sécurité : si l'installation initiale s'est arrêtée en
     * route (voir le commentaire d'install() ci-dessus), complète
     * silencieusement ce qui manque au premier accès à Modules > Navi >
     * Configurer, plutôt que d'exiger une désinstallation/réinstallation
     * complète pour corriger un état partiel.
     */
    private function ensureFullyInstalled()
    {
        foreach (self::HOOKS as $hookName) {
            if (!Hook::isModuleRegisteredOnHook($this, $hookName, $this->context->shop->id)) {
                $this->registerHook($hookName);
            }
        }

        if (!Db::getInstance()->executeS('SHOW TABLES LIKE \'' . _DB_PREFIX_ . self::STORIES_TABLE . '\'')) {
            $this->installStoriesTable();
        }

        $this->installUploadDir();
    }

    public function uninstall()
    {
        $ok = parent::uninstall()
            && $this->uninstallStoriesTable()
            && $this->uninstallUploadDir()
            && Configuration::deleteByName('NAVI_COOKIE_TEXT')
            && Configuration::deleteByName('NAVI_COOKIE_PRIVACY_URL')
            && Configuration::deleteByName('NAVI_COOKIE_LEGAL_URL')
            && Configuration::deleteByName('NAVI_COOKIE_LOGO_URL')
            && Configuration::deleteByName('NAVI_MINICART_ENABLED')
            && Configuration::deleteByName('NAVI_MINICART_SHOW_DESKTOP')
            && Configuration::deleteByName('NAVI_MINICART_SHOW_MOBILE')
            && Configuration::deleteByName('NAVI_COLOR_ACCENT')
            && Configuration::deleteByName('NAVI_COLOR_ACCENT_DEEP')
            && Configuration::deleteByName('NAVI_RADIUS_BUTTON')
            && Configuration::deleteByName('NAVI_RADIUS_IMAGE')
            && Configuration::deleteByName('NAVI_STORIES_SHOW_LABEL')
            && Configuration::deleteByName('NAVI_STORIES_BORDER_WIDTH')
            && Configuration::deleteByName('NAVI_STORIES_COLOR_PHONE_BG')
            && Configuration::deleteByName('NAVI_STORIES_COLOR_CLOSE_ICON')
            && Configuration::deleteByName('NAVI_STORIES_COLOR_CLOSE_BG')
            && Configuration::deleteByName('NAVI_STORIES_COLOR_OVERLAY')
            && Configuration::deleteByName('NAVI_FAB_POSITION')
            && Configuration::deleteByName('NAVI_STORIES_PHONE_PADDING')
            && Configuration::deleteByName('NAVI_STORIES_PHONE_WIDTH');

        foreach (self::VISIBILITY_TOGGLES as $desktopKey => $info) {
            $ok = Configuration::deleteByName($desktopKey) && $ok;
            $ok = Configuration::deleteByName($info['mobileKey']) && $ok;
        }

        return $ok;
    }

    /**
     * Logo du thème (Configurer > Apparence > Logos, PS_LOGO) converti en
     * URL publique complète — pré-remplit le champ « Logo » de la bannière
     * cookie sans obliger l'admin à ressaisir une URL déjà connue du site.
     */
    public function getDefaultLogoUrl()
    {
        $logo = Configuration::get('PS_LOGO');

        return $logo ? Context::getContext()->shop->getBaseURL(true, false) . 'img/' . $logo : '';
    }

    /**
     * Résout l'URL d'une page CMS existante à partir de son link_rewrite,
     * pour un shop qui voudrait pré-remplir ses propres liens légaux depuis
     * une intégration ou un script de configuration — non appelée par
     * install() (les slugs CMS varient d'une boutique à l'autre), laissée
     * disponible comme utilitaire public.
     */
    public function getCmsUrlBySlug($slug)
    {
        $idLang = (int) Context::getContext()->language->id;
        $idCms = (int) Db::getInstance()->getValue(
            'SELECT `id_cms` FROM `' . _DB_PREFIX_ . 'cms_lang` WHERE `link_rewrite` = \'' . pSQL($slug) . '\' AND `id_lang` = ' . $idLang
        );

        return $idCms ? Context::getContext()->link->getCMSLink($idCms) : '';
    }

    /**
     * N'accepte qu'un hex CSS valide (#rgb ou #rrggbb) — ces valeurs sont
     * réinjectées telles quelles dans un bloc <style> (voir
     * hookDisplayHeader ci-dessous), jamais de valeur non validée dans du
     * CSS/HTML généré côté serveur.
     */
    private function sanitizeHexColor($value, $default)
    {
        $value = trim((string) $value);

        return preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value) ? $value : $default;
    }

    private function getDefaultCookieText()
    {
        return $this->l(
            'Nous utilisons des cookies pour assurer le bon fonctionnement du site, ' .
            'analyser notre trafic et personnaliser nos publicités. Vous pouvez choisir vos préférences ci-dessous.'
        );
    }

    /**
     * Réglages back-office (Modules > Navi > Configurer), organisés en
     * sections distinctes (une par fonctionnalité) plutôt qu'un seul
     * formulaire plat : bannière cookies, mini-panier automatique,
     * apparence (couleurs, arrondis). La gestion des stories se fait
     * directement sur chaque fiche produit (onglet Navi), pas ici.
     */
    public function getContent()
    {
        $this->ensureFullyInstalled();

        $output = '';

        if (Tools::isSubmit('submitNaviCookie')) {
            Configuration::updateValue('NAVI_COOKIE_TEXT', (string) Tools::getValue('NAVI_COOKIE_TEXT'));
            Configuration::updateValue('NAVI_COOKIE_PRIVACY_URL', (string) Tools::getValue('NAVI_COOKIE_PRIVACY_URL'));
            Configuration::updateValue('NAVI_COOKIE_LEGAL_URL', (string) Tools::getValue('NAVI_COOKIE_LEGAL_URL'));
            Configuration::updateValue('NAVI_COOKIE_LOGO_URL', (string) Tools::getValue('NAVI_COOKIE_LOGO_URL'));
            Configuration::updateValue('NAVI_MINICART_ENABLED', (int) Tools::getValue('NAVI_MINICART_ENABLED'));
            Configuration::updateValue('NAVI_MINICART_SHOW_DESKTOP', (int) Tools::getValue('NAVI_MINICART_SHOW_DESKTOP'));
            Configuration::updateValue('NAVI_MINICART_SHOW_MOBILE', (int) Tools::getValue('NAVI_MINICART_SHOW_MOBILE'));
            Configuration::updateValue('NAVI_COLOR_ACCENT', $this->sanitizeHexColor(Tools::getValue('NAVI_COLOR_ACCENT'), self::DEFAULT_COLOR_ACCENT));
            Configuration::updateValue('NAVI_COLOR_ACCENT_DEEP', $this->sanitizeHexColor(Tools::getValue('NAVI_COLOR_ACCENT_DEEP'), self::DEFAULT_COLOR_ACCENT_DEEP));
            Configuration::updateValue('NAVI_RADIUS_BUTTON', max(0, (int) Tools::getValue('NAVI_RADIUS_BUTTON')));
            Configuration::updateValue('NAVI_RADIUS_IMAGE', max(0, (int) Tools::getValue('NAVI_RADIUS_IMAGE')));
            Configuration::updateValue('NAVI_STORIES_SHOW_LABEL', (int) Tools::getValue('NAVI_STORIES_SHOW_LABEL'));
            Configuration::updateValue('NAVI_STORIES_BORDER_WIDTH', max(0, (int) Tools::getValue('NAVI_STORIES_BORDER_WIDTH')));
            Configuration::updateValue('NAVI_STORIES_COLOR_PHONE_BG', $this->sanitizeHexColor(Tools::getValue('NAVI_STORIES_COLOR_PHONE_BG'), self::DEFAULT_STORIES_PHONE_BG));
            Configuration::updateValue('NAVI_STORIES_COLOR_CLOSE_ICON', $this->sanitizeHexColor(Tools::getValue('NAVI_STORIES_COLOR_CLOSE_ICON'), self::DEFAULT_STORIES_CLOSE_ICON));
            Configuration::updateValue('NAVI_STORIES_COLOR_CLOSE_BG', $this->sanitizeHexColor(Tools::getValue('NAVI_STORIES_COLOR_CLOSE_BG'), self::DEFAULT_STORIES_CLOSE_BG));
            Configuration::updateValue('NAVI_STORIES_COLOR_OVERLAY', $this->sanitizeHexColor(Tools::getValue('NAVI_STORIES_COLOR_OVERLAY'), self::DEFAULT_STORIES_OVERLAY_BG));
            Configuration::updateValue('NAVI_FAB_POSITION', in_array(Tools::getValue('NAVI_FAB_POSITION'), ['left', 'right'], true) ? Tools::getValue('NAVI_FAB_POSITION') : self::DEFAULT_FAB_POSITION);

            foreach (self::VISIBILITY_TOGGLES as $desktopKey => $info) {
                Configuration::updateValue($desktopKey, (int) Tools::getValue($desktopKey));
                Configuration::updateValue($info['mobileKey'], (int) Tools::getValue($info['mobileKey']));
            }

            $output .= $this->displayConfirmation($this->l('Réglages enregistrés.'));
        }

        if (Tools::isSubmit('submitNaviVideoAppearance')) {
            $phonePadding = max(0, min(self::MAX_STORIES_PHONE_PADDING, (int) Tools::getValue('NAVI_STORIES_PHONE_PADDING')));
            $phoneWidth = max(self::MIN_STORIES_PHONE_WIDTH, min(self::MAX_STORIES_PHONE_WIDTH, (int) Tools::getValue('NAVI_STORIES_PHONE_WIDTH')));
            Configuration::updateValue('NAVI_STORIES_PHONE_PADDING', $phonePadding);
            Configuration::updateValue('NAVI_STORIES_PHONE_WIDTH', $phoneWidth);
            $output .= $this->displayConfirmation($this->l('Réglages enregistrés.'));
        }

        return $output . $this->getHelpBlock() . $this->getVideoAppearanceBlock() . $this->renderForm();
    }

    /**
     * Mini-formulaire à part (pas un fieldset HelperForm standard) : les
     * deux curseurs et l'aperçu en direct ont besoin de vrais éléments
     * <input type="range"> avec des graduations et du JS pour la mise à
     * jour instantanée — HelperForm ne propose pas ce type de champ.
     * Soumission indépendante (submitNaviVideoAppearance) pour rester
     * simple, plutôt que d'essayer de le faire cohabiter dans le même
     * <form> que les fieldsets générés par HelperForm ci-dessous.
     */
    private function getVideoAppearanceBlock()
    {
        $this->context->smarty->assign([
            'navi_phone_padding' => max(0, (int) (Configuration::get('NAVI_STORIES_PHONE_PADDING') !== false ? Configuration::get('NAVI_STORIES_PHONE_PADDING') : self::DEFAULT_STORIES_PHONE_PADDING)),
            'navi_phone_padding_max' => self::MAX_STORIES_PHONE_PADDING,
            'navi_phone_width' => max(self::MIN_STORIES_PHONE_WIDTH, (int) (Configuration::get('NAVI_STORIES_PHONE_WIDTH') !== false ? Configuration::get('NAVI_STORIES_PHONE_WIDTH') : self::DEFAULT_STORIES_PHONE_WIDTH)),
            'navi_phone_width_min' => self::MIN_STORIES_PHONE_WIDTH,
            'navi_phone_width_max' => self::MAX_STORIES_PHONE_WIDTH,
            'navi_accent_color' => $this->sanitizeHexColor(Configuration::get('NAVI_COLOR_ACCENT'), self::DEFAULT_COLOR_ACCENT),
            'navi_ajax_token' => Tools::getAdminTokenLite('AdminModules'),
            'navi_current_index' => $this->context->link->getAdminLink('AdminModules', false)
                . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name,
            'navi_phone_padding_ticks' => range(0, self::MAX_STORIES_PHONE_PADDING, 2),
            'navi_phone_width_ticks' => range(self::MIN_STORIES_PHONE_WIDTH, self::MAX_STORIES_PHONE_WIDTH, 10),
        ]);

        return $this->fetch('module:' . $this->name . '/views/templates/admin/video-appearance.tpl');
    }

    /**
     * Bloc "Aide / Documentation" en haut du Configure — pas de nouvelle
     * clé de configuration, purement informatif : lien vers le dépôt et
     * lien pré-rempli pour ouvrir une issue avec le contexte utile déjà
     * renseigné (version du module/de PrestaShop/du thème), pour éviter
     * les allers-retours "peux-tu préciser ta version ?".
     */
    private function getHelpBlock()
    {
        $issueBody = "**Version du module Navi :** " . $this->version . "\n"
            . '**Version PrestaShop :** ' . _PS_VERSION_ . "\n"
            . '**Thème actif :** ' . Configuration::get('PS_THEME_NAME') . "\n\n"
            . "**Description du problème**\n\n\n"
            . "**Étapes pour reproduire**\n1. \n2. \n3. \n\n"
            . "**Comportement attendu**\n\n";

        $issueUrl = self::REPO_URL . '/issues/new?title=' . rawurlencode('[Bug] ')
            . '&body=' . rawurlencode($issueBody);

        return '<div class="panel">
            <h3><i class="icon-life-ring"></i> ' . $this->l('Aide / Documentation') . '</h3>
            <p>' . $this->l('Documentation complète, changelog et code source :') . '
                <a href="' . self::REPO_URL . '" target="_blank" rel="noopener">' . self::REPO_URL . '</a>
            </p>
            <p>' . $this->l('Un problème, une question, une suggestion ?') . '
                <a href="' . $issueUrl . '" target="_blank" rel="noopener" class="btn btn-default">
                    <i class="icon-github"></i> ' . $this->l('Ouvrir une issue sur GitHub') . '
                </a>
            </p>
        </div>';
    }

    /**
     * Paire de switches "Afficher sur ordinateur / mobile" partagée par
     * toutes les fonctionnalités du menu de l'engrenage (voir
     * self::VISIBILITY_TOGGLES) — évite de répéter la même définition de
     * champs 4 fois.
     */
    private function getVisibilityInputs($desktopKey, $mobileKey)
    {
        return [
            [
                'type' => 'switch',
                'label' => $this->l('Afficher sur ordinateur'),
                'name' => $desktopKey,
                'is_bool' => true,
                'values' => [
                    ['id' => $desktopKey . '_on', 'value' => 1, 'label' => $this->l('Oui')],
                    ['id' => $desktopKey . '_off', 'value' => 0, 'label' => $this->l('Non')],
                ],
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Afficher sur mobile'),
                'name' => $mobileKey,
                'is_bool' => true,
                'values' => [
                    ['id' => $mobileKey . '_on', 'value' => 1, 'label' => $this->l('Oui')],
                    ['id' => $mobileKey . '_off', 'value' => 0, 'label' => $this->l('Non')],
                ],
            ],
        ];
    }

    private function renderForm()
    {
        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Bannière cookies'),
                    'icon' => 'icon-cogs',
                ],
                'input' => array_merge([
                    [
                        'type' => 'text',
                        'label' => $this->l('URL du logo'),
                        'name' => 'NAVI_COOKIE_LOGO_URL',
                        'desc' => $this->l('Pré-rempli avec le logo du thème (Apparence > Logos). Laisser vide pour ne pas afficher de logo.'),
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->l('Texte de la bannière'),
                        'name' => 'NAVI_COOKIE_TEXT',
                        'rows' => 4,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('URL politique de confidentialité'),
                        'name' => 'NAVI_COOKIE_PRIVACY_URL',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('URL mentions légales'),
                        'name' => 'NAVI_COOKIE_LEGAL_URL',
                    ],
                ], $this->getVisibilityInputs('NAVI_COOKIE_SHOW_DESKTOP', 'NAVI_COOKIE_SHOW_MOBILE')),
                'submit' => [
                    'title' => $this->l('Enregistrer'),
                ],
            ],
        ];

        $accessibilityForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Accessibilité'),
                    'icon' => 'icon-universal-access',
                ],
                'input' => $this->getVisibilityInputs('NAVI_A11Y_SHOW_DESKTOP', 'NAVI_A11Y_SHOW_MOBILE'),
                'submit' => [
                    'title' => $this->l('Enregistrer'),
                ],
            ],
        ];

        $stickyCartForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Panier sticky'),
                    'icon' => 'icon-shopping-cart',
                ],
                'input' => $this->getVisibilityInputs('NAVI_STICKYCART_SHOW_DESKTOP', 'NAVI_STICKYCART_SHOW_MOBILE'),
                'submit' => [
                    'title' => $this->l('Enregistrer'),
                ],
            ],
        ];

        $storiesForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Stories'),
                    'icon' => 'icon-video-camera',
                ],
                'input' => array_merge([
                    [
                        'type' => 'switch',
                        'label' => $this->l('Afficher le titre de la bulle'),
                        'name' => 'NAVI_STORIES_SHOW_LABEL',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'stories_label_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'stories_label_off', 'value' => 0, 'label' => $this->l('Non')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Épaisseur de la bordure (px)'),
                        'name' => 'NAVI_STORIES_BORDER_WIDTH',
                        'suffix' => 'px',
                        'desc' => $this->l('0 = pas de bordure.'),
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Couleur du fond du mockup téléphone'),
                        'name' => 'NAVI_STORIES_COLOR_PHONE_BG',
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Couleur de la croix (icône)'),
                        'name' => 'NAVI_STORIES_COLOR_CLOSE_ICON',
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Couleur du fond du bouton de fermeture'),
                        'name' => 'NAVI_STORIES_COLOR_CLOSE_BG',
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Couleur du fond plein écran (mobile)'),
                        'name' => 'NAVI_STORIES_COLOR_OVERLAY',
                    ],
                ], $this->getVisibilityInputs('NAVI_STORIES_SHOW_DESKTOP', 'NAVI_STORIES_SHOW_MOBILE')),
                'submit' => [
                    'title' => $this->l('Enregistrer'),
                ],
            ],
        ];

        $miniCartForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Mini-panier automatique'),
                    'icon' => 'icon-shopping-basket',
                ],
                'input' => array_merge([
                    [
                        'type' => 'switch',
                        'label' => $this->l('Ouverture automatique du mini-panier'),
                        'name' => 'NAVI_MINICART_ENABLED',
                        'is_bool' => true,
                        'desc' => $this->l("Ouvre automatiquement le mini-panier après un ajout au panier, et le referme tout seul après quelques secondes. Suppose le markup du thème PrestaShop \"Classic\" (#_desktop_cart) — désactivé par défaut, à activer une fois vérifié que ça fonctionne sur votre thème."),
                        'values' => [
                            ['id' => 'minicart_on', 'value' => 1, 'label' => $this->l('Activé')],
                            ['id' => 'minicart_off', 'value' => 0, 'label' => $this->l('Désactivé')],
                        ],
                    ],
                ], $this->getVisibilityInputs('NAVI_MINICART_SHOW_DESKTOP', 'NAVI_MINICART_SHOW_MOBILE')),
                'submit' => [
                    'title' => $this->l('Enregistrer'),
                ],
            ],
        ];

        $appearanceForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Apparence'),
                    'icon' => 'icon-paint-brush',
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Position du bouton flottant'),
                        'name' => 'NAVI_FAB_POSITION',
                        'options' => [
                            'query' => [
                                ['id' => 'right', 'name' => $this->l('Droite')],
                                ['id' => 'left', 'name' => $this->l('Gauche')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l("Couleur d'accent"),
                        'name' => 'NAVI_COLOR_ACCENT',
                        'desc' => $this->l('Couleur principale des boutons (bannière cookies, panier sticky...).'),
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l("Couleur d'accent (survol / texte)"),
                        'name' => 'NAVI_COLOR_ACCENT_DEEP',
                        'desc' => $this->l('Variante plus sombre, utilisée au survol des boutons et comme couleur de texte sur fond clair (contraste).'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Arrondi des boutons (px)'),
                        'name' => 'NAVI_RADIUS_BUTTON',
                        'suffix' => 'px',
                        'desc' => $this->l('0 = angles droits. Boutons concernés : bannière cookies, panier sticky.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l("Arrondi de l'image produit (px)"),
                        'name' => 'NAVI_RADIUS_IMAGE',
                        'suffix' => 'px',
                        'desc' => $this->l('Miniature produit affichée dans le panier sticky.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Enregistrer'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitNaviCookie';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $fieldsValue = [
            'NAVI_COOKIE_LOGO_URL' => Configuration::get('NAVI_COOKIE_LOGO_URL'),
            'NAVI_COOKIE_TEXT' => Configuration::get('NAVI_COOKIE_TEXT'),
            'NAVI_COOKIE_PRIVACY_URL' => Configuration::get('NAVI_COOKIE_PRIVACY_URL'),
            'NAVI_COOKIE_LEGAL_URL' => Configuration::get('NAVI_COOKIE_LEGAL_URL'),
            'NAVI_MINICART_ENABLED' => (bool) Configuration::get('NAVI_MINICART_ENABLED'),
            'NAVI_COLOR_ACCENT' => Configuration::get('NAVI_COLOR_ACCENT') ?: self::DEFAULT_COLOR_ACCENT,
            'NAVI_COLOR_ACCENT_DEEP' => Configuration::get('NAVI_COLOR_ACCENT_DEEP') ?: self::DEFAULT_COLOR_ACCENT_DEEP,
            'NAVI_RADIUS_BUTTON' => Configuration::get('NAVI_RADIUS_BUTTON') !== false ? Configuration::get('NAVI_RADIUS_BUTTON') : self::DEFAULT_RADIUS_BUTTON,
            'NAVI_RADIUS_IMAGE' => Configuration::get('NAVI_RADIUS_IMAGE') !== false ? Configuration::get('NAVI_RADIUS_IMAGE') : self::DEFAULT_RADIUS_IMAGE,
            'NAVI_STORIES_SHOW_LABEL' => (bool) Configuration::get('NAVI_STORIES_SHOW_LABEL'),
            'NAVI_STORIES_BORDER_WIDTH' => Configuration::get('NAVI_STORIES_BORDER_WIDTH') !== false ? Configuration::get('NAVI_STORIES_BORDER_WIDTH') : self::DEFAULT_STORIES_BORDER_WIDTH,
            'NAVI_STORIES_COLOR_PHONE_BG' => Configuration::get('NAVI_STORIES_COLOR_PHONE_BG') ?: self::DEFAULT_STORIES_PHONE_BG,
            'NAVI_STORIES_COLOR_CLOSE_ICON' => Configuration::get('NAVI_STORIES_COLOR_CLOSE_ICON') ?: self::DEFAULT_STORIES_CLOSE_ICON,
            'NAVI_STORIES_COLOR_CLOSE_BG' => Configuration::get('NAVI_STORIES_COLOR_CLOSE_BG') ?: self::DEFAULT_STORIES_CLOSE_BG,
            'NAVI_STORIES_COLOR_OVERLAY' => Configuration::get('NAVI_STORIES_COLOR_OVERLAY') ?: self::DEFAULT_STORIES_OVERLAY_BG,
            'NAVI_FAB_POSITION' => Configuration::get('NAVI_FAB_POSITION') ?: self::DEFAULT_FAB_POSITION,
        ];

        foreach (self::VISIBILITY_TOGGLES as $desktopKey => $info) {
            $fieldsValue[$desktopKey] = (bool) Configuration::get($desktopKey);
            $fieldsValue[$info['mobileKey']] = (bool) Configuration::get($info['mobileKey']);
        }
        $fieldsValue['NAVI_MINICART_SHOW_DESKTOP'] = (bool) Configuration::get('NAVI_MINICART_SHOW_DESKTOP');
        $fieldsValue['NAVI_MINICART_SHOW_MOBILE'] = (bool) Configuration::get('NAVI_MINICART_SHOW_MOBILE');

        $helper->fields_value = $fieldsValue;

        return $helper->generateForm([
            $fieldsForm,
            $accessibilityForm,
            $stickyCartForm,
            $storiesForm,
            $miniCartForm,
            $appearanceForm,
        ]);
    }

    /**
     * Consent Mode v2 : refuse tout par défaut jusqu'au choix de
     * l'utilisateur. Le stub gtag() (pousse simplement dans dataLayer si
     * aucun gtag réel n'existe encore) garantit que cette commande est prise
     * en compte quel que soit l'ordre de chargement réel de Google
     * Analytics — mais SEULEMENT si ce bloc s'exécute avant le script GA
     * lui-même. Ordre des hooks non garanti par PrestaShop à
     * l'installation : à vérifier dans Back Office > Modules > Positions >
     * displayHeader que Navi passe avant tout module Google Analytics/Tag
     * Manager.
     */
    public function hookDisplayHeader()
    {
        $consentVersion = '1';
        $hasConsent = isset($_COOKIE['navi_consent_all'])
            && isset($_COOKIE['navi_consent_version'])
            && $_COOKIE['navi_consent_version'] === $consentVersion;
        $statsGranted = $hasConsent && isset($_COOKIE['navi_consent_stats']) && $_COOKIE['navi_consent_stats'] === '1';
        $mktGranted = $hasConsent && isset($_COOKIE['navi_consent_mkt']) && $_COOKIE['navi_consent_mkt'] === '1';

        // Un visiteur dont le choix est déjà connu (cookie déjà posé) n'a pas
        // besoin d'attendre : wait_for_update ne sert qu'à laisser un
        // NOUVEAU visiteur le temps de répondre à la bannière.
        $waitForUpdate = $hasConsent ? 0 : 500;

        return '<script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag("consent", "default", {
                "ad_storage": "' . ($mktGranted ? 'granted' : 'denied') . '",
                "ad_user_data": "' . ($mktGranted ? 'granted' : 'denied') . '",
                "ad_personalization": "' . ($mktGranted ? 'granted' : 'denied') . '",
                "analytics_storage": "' . ($statsGranted ? 'granted' : 'denied') . '",
                "wait_for_update": ' . (int) $waitForUpdate . '
            });
        </script>' . $this->getConfigStyleTag();
    }

    /**
     * Couleurs/arrondis/visibilité configurés depuis Modules > Navi >
     * Configurer, réinjectés en CSS à chaque page. Deux mécanismes :
     * - variables CSS sur `html:root` (pas `:root` seul) : spécificité
     *   (0,1,1) contre (0,1,0) pour `:root` seul — garantit que ce bloc
     *   l'emporte sur les valeurs par défaut de core.css quel que soit
     *   l'ordre relatif des deux dans le <head> final (non garanti par
     *   PrestaShop/le thème) ;
     * - règles `@media` avec `!important` pour les bascules
     *   "Afficher sur ordinateur/mobile" (self::VISIBILITY_TOGGLES) : ici
     *   on veut un masquage inconditionnel, pas une valeur par défaut
     *   surchageable, donc pas besoin du même mécanisme de spécificité.
     */
    private function getConfigStyleTag()
    {
        $accent = $this->sanitizeHexColor(Configuration::get('NAVI_COLOR_ACCENT'), self::DEFAULT_COLOR_ACCENT);
        $accentDeep = $this->sanitizeHexColor(Configuration::get('NAVI_COLOR_ACCENT_DEEP'), self::DEFAULT_COLOR_ACCENT_DEEP);
        $radiusButton = max(0, (int) (Configuration::get('NAVI_RADIUS_BUTTON') !== false ? Configuration::get('NAVI_RADIUS_BUTTON') : self::DEFAULT_RADIUS_BUTTON));
        $radiusImage = max(0, (int) (Configuration::get('NAVI_RADIUS_IMAGE') !== false ? Configuration::get('NAVI_RADIUS_IMAGE') : self::DEFAULT_RADIUS_IMAGE));
        $storiesBorderWidth = max(0, (int) (Configuration::get('NAVI_STORIES_BORDER_WIDTH') !== false ? Configuration::get('NAVI_STORIES_BORDER_WIDTH') : self::DEFAULT_STORIES_BORDER_WIDTH));
        $storiesPhoneBg = $this->sanitizeHexColor(Configuration::get('NAVI_STORIES_COLOR_PHONE_BG'), self::DEFAULT_STORIES_PHONE_BG);
        $storiesCloseIcon = $this->sanitizeHexColor(Configuration::get('NAVI_STORIES_COLOR_CLOSE_ICON'), self::DEFAULT_STORIES_CLOSE_ICON);
        $storiesCloseBg = $this->sanitizeHexColor(Configuration::get('NAVI_STORIES_COLOR_CLOSE_BG'), self::DEFAULT_STORIES_CLOSE_BG);
        $storiesOverlayBg = $this->sanitizeHexColor(Configuration::get('NAVI_STORIES_COLOR_OVERLAY'), self::DEFAULT_STORIES_OVERLAY_BG);
        $phonePadding = max(0, min(self::MAX_STORIES_PHONE_PADDING, (int) (Configuration::get('NAVI_STORIES_PHONE_PADDING') !== false ? Configuration::get('NAVI_STORIES_PHONE_PADDING') : self::DEFAULT_STORIES_PHONE_PADDING)));
        $phoneWidth = max(self::MIN_STORIES_PHONE_WIDTH, min(self::MAX_STORIES_PHONE_WIDTH, (int) (Configuration::get('NAVI_STORIES_PHONE_WIDTH') !== false ? Configuration::get('NAVI_STORIES_PHONE_WIDTH') : self::DEFAULT_STORIES_PHONE_WIDTH)));

        $css = 'html:root{'
            . '--navi-color-accent:' . $accent . ';'
            . '--navi-color-accent-deep:' . $accentDeep . ';'
            . '--navi-radius-button:' . $radiusButton . 'px;'
            . '--navi-radius-image:' . $radiusImage . 'px;'
            . '--navi-story-border-width:' . $storiesBorderWidth . 'px;'
            . '--navi-story-phone-bg:' . $storiesPhoneBg . ';'
            . '--navi-story-close-icon:' . $storiesCloseIcon . ';'
            . '--navi-story-close-bg:' . $storiesCloseBg . ';'
            . '--navi-story-overlay-bg:' . $storiesOverlayBg . ';'
            . '--navi-story-phone-padding:' . $phonePadding . 'px;'
            . '--navi-story-phone-width:' . $phoneWidth . 'px;'
            . '}';

        if (!Configuration::get('NAVI_STORIES_SHOW_LABEL')) {
            $css .= 'html .navi-story-bubble-label{display:none}';
        }

        foreach (self::VISIBILITY_TOGGLES as $desktopKey => $info) {
            if (!Configuration::get($desktopKey)) {
                $css .= '@media (min-width:481px){' . $info['selector'] . '{display:none!important}}';
            }
            if (!Configuration::get($info['mobileKey'])) {
                $css .= '@media (max-width:480px){' . $info['selector'] . '{display:none!important}}';
            }
        }

        return '<style>' . $css . '</style>';
    }

    /**
     * CSS/JS chargés sur toutes les pages front-office (le bouton flottant
     * doit rester accessible partout, pas seulement sur la fiche produit).
     */
    public function hookActionFrontControllerSetMedia()
    {
        $this->context->controller->registerStylesheet(
            'navi-core',
            'modules/' . $this->name . '/views/css/core.css',
            ['media' => 'all', 'priority' => 200]
        );
        $this->context->controller->registerStylesheet(
            'navi-cookie-consent',
            'modules/' . $this->name . '/views/css/cookie-consent.css',
            ['media' => 'all', 'priority' => 200]
        );
        $this->context->controller->registerStylesheet(
            'navi-accessibility',
            'modules/' . $this->name . '/views/css/accessibility.css',
            ['media' => 'all', 'priority' => 200]
        );

        $this->context->controller->registerJavascript(
            'navi-core',
            'modules/' . $this->name . '/views/js/core.js',
            ['position' => 'bottom', 'priority' => 200]
        );
        $this->context->controller->registerJavascript(
            'navi-cookie-consent',
            'modules/' . $this->name . '/views/js/cookie-consent.js',
            ['position' => 'bottom', 'priority' => 200]
        );
        $this->context->controller->registerJavascript(
            'navi-accessibility',
            'modules/' . $this->name . '/views/js/accessibility.js',
            ['position' => 'bottom', 'priority' => 200]
        );

        // Mini-panier automatique : sur toutes les pages, pas seulement la
        // fiche produit — l'ajout au panier peut se faire depuis une liste
        // de catégorie, la page panier (cross-sell), etc. Non chargé du
        // tout si désactivé dans Configurer (désactivé par défaut) plutôt
        // qu'un simple flag JS ignoré.
        if (Configuration::get('NAVI_MINICART_ENABLED')) {
            $this->context->controller->registerJavascript(
                'navi-mini-cart',
                'modules/' . $this->name . '/views/js/mini-cart.js',
                ['position' => 'bottom', 'priority' => 200]
            );
            // Contrairement aux autres bascules "Afficher sur ordinateur/
            // mobile" (gérées en CSS, voir getConfigStyleTag()), le
            // mini-panier n'a pas d'élément DOM propre à masquer — c'est un
            // comportement (ouverture automatique) sur l'élément panier
            // natif du thème, jamais à masquer lui-même. Gating côté JS
            // via matchMedia à la place.
            Media::addJsDef([
                'naviMiniCartConfig' => [
                    'showOnDesktop' => (bool) Configuration::get('NAVI_MINICART_SHOW_DESKTOP'),
                    'showOnMobile' => (bool) Configuration::get('NAVI_MINICART_SHOW_MOBILE'),
                ],
            ]);
        }

        $stories = [];

        // Panier sticky + stories : chargés uniquement sur les fiches
        // produit — inutiles ailleurs.
        if ($this->isProductPage()) {
            $this->context->controller->registerStylesheet(
                'navi-sticky-cart',
                'modules/' . $this->name . '/views/css/sticky-cart.css',
                ['media' => 'all', 'priority' => 200]
            );
            $this->context->controller->registerJavascript(
                'navi-sticky-cart',
                'modules/' . $this->name . '/views/js/sticky-cart.js',
                ['position' => 'bottom', 'priority' => 200]
            );
            Media::addJsDef([
                'naviStickyCartI18n' => [
                    'addToCart' => $this->l('Ajouter au panier'),
                    'adding' => $this->l('Ajout en cours...'),
                    'added' => $this->l('Ajouté'),
                    'outOfStock' => $this->l('Bientôt disponible'),
                    'closeLabel' => $this->l('Fermer'),
                ],
            ]);

            $idProduct = $this->getCurrentProductId();
            if ($idProduct) {
                $stories = $this->getStoriesForProduct($idProduct);
            }

            if (!empty($stories)) {
                $this->context->controller->registerStylesheet(
                    'navi-stories',
                    'modules/' . $this->name . '/views/css/stories.css',
                    ['media' => 'all', 'priority' => 200]
                );
                $this->context->controller->registerJavascript(
                    'navi-stories',
                    'modules/' . $this->name . '/views/js/stories.js',
                    ['position' => 'bottom', 'priority' => 200]
                );
                Media::addJsDef([
                    'naviStoriesConfig' => [
                        'closeLabel' => $this->l('Fermer'),
                        'prevLabel' => $this->l('Story précédente'),
                        'nextLabel' => $this->l('Story suivante'),
                    ],
                ]);
            }
        }

        $items = [
            // Icône "retour en haut" : toujours proposée par le noyau,
            // visible uniquement après 50% de scroll — pas un module, elle
            // n'a pas d'état activable.
            [
                'id' => 'top',
                'icon' => '↑',
                'label' => $this->l('Haut de page'),
                'shortLabel' => $this->l('Haut'),
                'action' => 'scroll-top',
                'condition' => 'scroll',
                'scrollThreshold' => 50,
            ],
            [
                'id' => 'accessibility',
                'label' => $this->l('Accessibilité'),
                'shortLabel' => $this->l('Accessibilité'),
                'action' => 'open-accessibility-panel',
                'condition' => '',
                'iconSvg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4" r="1.5" fill="currentColor" stroke="none"></circle><path d="M11 6v6h5"></path><path d="M9 12l4 2 3 6"></path><circle cx="9" cy="16" r="5"></circle></svg>',
            ],
            [
                'id' => 'cookie-consent',
                'label' => $this->l('Consentement cookies'),
                'shortLabel' => $this->l('Cookies'),
                'action' => 'open-cookie-modal',
                'condition' => '',
                'iconSvg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><circle cx="8.5" cy="10.5" r="1" fill="currentColor" stroke="none"></circle><circle cx="15" cy="9" r="1" fill="currentColor" stroke="none"></circle><circle cx="15.5" cy="15" r="1" fill="currentColor" stroke="none"></circle><circle cx="9" cy="15.5" r="1" fill="currentColor" stroke="none"></circle></svg>',
            ],
            [
                'id' => 'sticky-cart',
                'label' => $this->l('Ajouter au panier'),
                'shortLabel' => $this->l('Panier'),
                'action' => 'open-sticky-cart',
                'condition' => 'is_product',
                'iconSvg' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 22 22" fill="none"><path fill="currentColor" d="M15.95 6H19.7V17.875C19.7 18.7344 19.3875 19.4635 18.7625 20.0625C18.1635 20.6875 17.4344 21 16.575 21H5.325C4.46563 21 3.72344 20.6875 3.09844 20.0625C2.49948 19.4635 2.2 18.7344 2.2 17.875V6H5.95C5.95 4.61979 6.43177 3.44792 7.39531 2.48438C8.3849 1.49479 9.56979 1 10.95 1C12.3302 1 13.5021 1.49479 14.4656 2.48438C15.4552 3.44792 15.95 4.61979 15.95 6ZM13.1375 3.8125C12.5385 3.1875 11.8094 2.875 10.95 2.875C10.0906 2.875 9.34844 3.1875 8.72344 3.8125C8.12448 4.41146 7.825 5.14062 7.825 6H14.075C14.075 5.14062 13.7625 4.41146 13.1375 3.8125ZM17.825 17.875V7.875H15.95V9.4375C15.95 9.69792 15.8589 9.91927 15.6766 10.1016C15.4943 10.2839 15.2729 10.375 15.0125 10.375C14.7521 10.375 14.5307 10.2839 14.3484 10.1016C14.1661 9.91927 14.075 9.69792 14.075 9.4375V7.875H7.825V9.4375C7.825 9.69792 7.73385 9.91927 7.55156 10.1016C7.36927 10.2839 7.14792 10.375 6.8875 10.375C6.62708 10.375 6.40573 10.2839 6.22344 10.1016C6.04115 9.91927 5.95 9.69792 5.95 9.4375V7.875H4.075V17.875C4.075 18.2135 4.19219 18.5 4.42656 18.7344C4.68698 18.9948 4.98646 19.125 5.325 19.125H16.575C16.9135 19.125 17.2 18.9948 17.4344 18.7344C17.6948 18.5 17.825 18.2135 17.825 17.875Z"></path></svg>',
            ],
        ];

        // Pas d'entrée "stories" dans ce menu : les bulles sont des
        // boutons autonomes affichés directement sur la fiche produit
        // (voir hookDisplayAfterProductThumbs), pas une fonctionnalité
        // qu'on ouvre depuis l'engrenage — $stories sert seulement à
        // conditionner l'enregistrement de stories.css/js ci-dessus.

        Media::addJsDef([
            'naviConfig' => [
                'items' => $items,
                'isProduct' => (bool) $this->isProductPage(),
                'closeLabel' => $this->l('Fermer'),
            ],
            'naviCookieConfig' => [
                'consentVersion' => '1',
                'savedText' => $this->l('Préférences enregistrées'),
            ],
        ]);
    }

    /**
     * Conditionne les modules qui ne doivent apparaître que sur une fiche
     * produit (panier sticky, stories).
     */
    private function isProductPage()
    {
        return isset($this->context->controller) && $this->context->controller instanceof ProductController;
    }

    /**
     * `id_product` n'est pas toujours présent dans l'URL d'une fiche
     * produit selon la configuration de réécriture d'URL du shop — replis
     * successifs sur la variable Smarty `product` assignée par le
     * ProductController natif avant d'abandonner.
     */
    private function getCurrentProductId()
    {
        if (!$this->isProductPage()) {
            return 0;
        }

        $idProduct = (int) Tools::getValue('id_product');
        if ($idProduct > 0) {
            return $idProduct;
        }

        $product = $this->context->smarty->getTemplateVars('product');
        if (is_array($product) && !empty($product['id_product'])) {
            return (int) $product['id_product'];
        }
        if (is_object($product) && isset($product->id)) {
            return (int) $product->id;
        }

        return 0;
    }

    /**
     * Même logique de replis que getCurrentProductId(), mais à partir des
     * paramètres reçus par un hook d'administration plutôt que du contexte
     * front — la clé exacte présente dans $params varie selon le hook et
     * la version de PrestaShop.
     */
    private function resolveProductIdFromHookParams($params)
    {
        if (is_array($params)) {
            foreach (['id_product', 'product_id', 'id'] as $key) {
                if (!empty($params[$key]) && is_numeric($params[$key])) {
                    return (int) $params[$key];
                }
            }
            if (isset($params['product']) && is_object($params['product']) && isset($params['product']->id)) {
                return (int) $params['product']->id;
            }
        }

        $idProduct = (int) Tools::getValue('id_product');

        return $idProduct > 0 ? $idProduct : 0;
    }

    /**
     * Coquille du bouton flottant + panneaux, injectée juste avant </body>.
     * Le Smarty ne connaît que les réglages texte de la bannière cookie et
     * l'état déjà enregistré (cookies déjà posés par
     * views/js/cookie-consent.js) : la logique d'affichage (état
     * fermé/menu/détail) reste entièrement gérée en JS (views/js/core.js).
     */
    public function hookDisplayBeforeBodyClosingTag($params)
    {
        $consentVersion = '1';
        $hasConsent = isset($_COOKIE['navi_consent_all'])
            && isset($_COOKIE['navi_consent_version'])
            && $_COOKIE['navi_consent_version'] === $consentVersion;

        $this->context->smarty->assign([
            'navi_cookie_logo_url' => Configuration::get('NAVI_COOKIE_LOGO_URL'),
            'navi_cookie_text' => Configuration::get('NAVI_COOKIE_TEXT'),
            'navi_cookie_privacy_url' => Configuration::get('NAVI_COOKIE_PRIVACY_URL') ?: '#',
            'navi_cookie_legal_url' => Configuration::get('NAVI_COOKIE_LEGAL_URL') ?: '#',
            'navi_cookie_choice_made' => $hasConsent,
            'navi_cookie_stats_checked' => isset($_COOKIE['navi_consent_stats']) && $_COOKIE['navi_consent_stats'] === '1',
            'navi_cookie_mkt_checked' => isset($_COOKIE['navi_consent_mkt']) && $_COOKIE['navi_consent_mkt'] === '1',
            'navi_gear_svg' => $this->getGearSvg(),
            'navi_fab_position' => in_array(Configuration::get('NAVI_FAB_POSITION'), ['left', 'right'], true) ? Configuration::get('NAVI_FAB_POSITION') : self::DEFAULT_FAB_POSITION,
        ]);

        return $this->fetch('module:' . $this->name . '/views/templates/hook/footer.tpl');
    }

    /**
     * SVG dessiné plutôt que l'emoji ⚙️, dont le rendu diffère trop d'un
     * appareil/navigateur à l'autre pour rester cohérent.
     */
    private function getGearSvg()
    {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>';
    }

    // ================================================================
    // Stories — gestion native des bulles vidéo produit.
    //
    // Absorbe la fonctionnalité auparavant déléguée au module tiers
    // lstvideostory, en corrigeant au passage les points trouvés lors de
    // l'analyse de ce module : sauvegarde exposée sur un contrôleur front
    // public non protégé par un jeton vérifié (ici : aucun contrôleur
    // front du tout, la sauvegarde ne passe QUE par le formulaire produit
    // réel du Back Office, donc déjà couverte par la session employé et le
    // jeton CSRF que PrestaShop applique lui-même) ; validation d'upload
    // dupliquée à plusieurs endroits (ici centralisée) ; aucune limite de
    // taille réellement appliquée (ici : MAX_UPLOAD_BYTES) ; aucun
    // nettoyage des fichiers uploadés à la désinstallation (ici :
    // uninstallUploadDir()).
    // ================================================================

    private function installStoriesTable()
    {
        return Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::STORIES_TABLE . '` (
                `id_navi_story` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_product` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `story_index` TINYINT UNSIGNED NOT NULL,
                `youtube` VARCHAR(32) NOT NULL,
                `preview` VARCHAR(255) NOT NULL,
                `label` VARCHAR(128) NOT NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_navi_story`),
                UNIQUE KEY `shop_product_story` (`id_shop`, `id_product`, `story_index`),
                KEY `id_product` (`id_product`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
        );
    }

    private function uninstallStoriesTable()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::STORIES_TABLE . '`');
    }

    private function getUploadDirectory()
    {
        return _PS_MODULE_DIR_ . $this->name . '/' . self::UPLOAD_SUBDIR . '/';
    }

    private function installUploadDir()
    {
        $dir = $this->getUploadDirectory();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }

        return true;
    }

    /**
     * Contrairement à lstvideostory, qui ne supprimait que sa table à la
     * désinstallation : les fichiers vidéo uploadés (potentiellement
     * volumineux, et des données du marchand) doivent partir avec le
     * module, pas rester orphelins sur le disque indéfiniment.
     */
    private function uninstallUploadDir()
    {
        $dir = $this->getUploadDirectory();
        if (!is_dir($dir)) {
            return true;
        }

        foreach (glob($dir . '*.mp4') as $file) {
            @unlink($file);
        }

        return true;
    }

    /**
     * Récupère les stories du produit pour la boutique courante — scoping
     * par id_shop dès la conception (contrairement à lstvideostory, qui
     * n'avait pas cette colonne et mélangeait les données entre boutiques
     * d'un même multiboutique).
     */
    public function getStoriesForProduct($idProduct, $idShop = null)
    {
        if ($idShop === null) {
            $idShop = (int) $this->context->shop->id;
        }

        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . self::STORIES_TABLE . '`
             WHERE `id_product` = ' . (int) $idProduct . '
             AND `id_shop` = ' . (int) $idShop . '
             ORDER BY `story_index` ASC'
        );
    }

    private function deleteStoriesForProduct($idProduct, $idShop)
    {
        return Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . self::STORIES_TABLE . '`
             WHERE `id_product` = ' . (int) $idProduct . '
             AND `id_shop` = ' . (int) $idShop
        );
    }

    /**
     * Accepte une URL YouTube complète (watch?v=, youtu.be/, /shorts/) ou
     * un identifiant brut de 11 caractères déjà saisi tel quel.
     */
    private function extractYoutubeId($input)
    {
        $input = trim((string) $input);
        if ($input === '') {
            return '';
        }

        if (preg_match('#(?:youtube\.com/(?:watch\?v=|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $input, $m)) {
            return $m[1];
        }

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
            return $input;
        }

        return '';
    }

    /**
     * Validation centralisée (contrairement à lstvideostory, dupliquée à 3
     * endroits) : extension + MIME stricts (pas de repli sur
     * application/octet-stream, trop permissif), taille plafonnée.
     * Retourne un message d'erreur, ou '' si le fichier est accepté.
     */
    private function validateMp4Upload(array $file)
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return $this->l('Erreur lors du transfert du fichier.');
        }

        if ($file['size'] > self::MAX_UPLOAD_BYTES) {
            return sprintf($this->l('Le fichier dépasse la taille maximale autorisée (%d Mo).'), self::MAX_UPLOAD_BYTES / 1048576);
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'mp4') {
            return $this->l('Seuls les fichiers .mp4 sont acceptés.');
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($mime !== 'video/mp4') {
                return $this->l('Le fichier ne semble pas être une vidéo MP4 valide.');
            }
        }

        return '';
    }

    /**
     * Déplace un upload validé vers le dossier du module et retourne son
     * URL publique, ou null si aucun fichier valide n'a été soumis pour cet
     * index (absence de fichier n'est pas une erreur : l'admin peut avoir
     * laissé ce champ vide volontairement).
     */
    private function handleUploadedPreview($index, &$errors)
    {
        $field = 'navi_story_preview_file_' . (int) $index;
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $file = $_FILES[$field];
        $error = $this->validateMp4Upload($file);
        if ($error !== '') {
            $errors[] = sprintf('#%d — %s', $index, $error);

            return null;
        }

        $filename = 'story_' . (int) $index . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.mp4';
        $destination = $this->getUploadDirectory() . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $errors[] = sprintf('#%d — %s', $index, $this->l("Échec de l'enregistrement du fichier."));

            return null;
        }

        return $this->_path . self::UPLOAD_SUBDIR . '/' . $filename;
    }

    /**
     * Point d'entrée UNIQUE de sauvegarde, appelé uniquement depuis un
     * enregistrement produit réel (voir hookActionObjectProduct*After
     * ci-dessous) — jamais depuis un contrôleur front public. Un flag
     * statique évite un double traitement si plusieurs hooks du cycle de
     * sauvegarde produit se déclenchaient pour la même requête (le bug
     * inverse observé sur lstvideostory : un upload ne peut être déplacé
     * qu'une seule fois, un second passage sur le même $_FILES échouerait
     * silencieusement et effacerait la story qui venait d'être créée).
     */
    private static $storiesSavedThisRequest = [];

    public function handleProductSave($idProduct)
    {
        $idProduct = (int) $idProduct;
        if (!$idProduct || !Tools::isSubmit('navi_story_submitted')) {
            return;
        }

        $idShop = (int) $this->context->shop->id;
        $requestKey = $idShop . ':' . $idProduct;
        if (isset(self::$storiesSavedThisRequest[$requestKey])) {
            return;
        }
        self::$storiesSavedThisRequest[$requestKey] = true;

        $errors = [];
        $rows = [];
        $now = date('Y-m-d H:i:s');

        for ($index = 1; $index <= self::STORY_LIMIT; $index++) {
            $youtube = $this->extractYoutubeId(Tools::getValue('navi_story_youtube_' . $index));
            if ($youtube === '') {
                continue; // slot vide : pas de story à cet emplacement.
            }

            $uploadedUrl = $this->handleUploadedPreview($index, $errors);
            $preview = $uploadedUrl
                ?: (string) Tools::getValue('navi_story_preview_' . $index)
                ?: 'https://img.youtube.com/vi/' . $youtube . '/maxresdefault.jpg';

            $rows[] = [
                'id_product' => $idProduct,
                'id_shop' => $idShop,
                'story_index' => $index,
                'youtube' => pSQL($youtube),
                'preview' => pSQL($preview),
                'label' => pSQL((string) Tools::getValue('navi_story_label_' . $index)),
                'date_add' => $now,
                'date_upd' => $now,
            ];
        }

        $this->deleteStoriesForProduct($idProduct, $idShop);
        foreach ($rows as $row) {
            Db::getInstance()->insert(self::STORIES_TABLE, $row);
        }

        if (!empty($errors)) {
            $this->context->controller->errors[] = $this->l('Stories Navi : certains fichiers ont été ignorés.') . ' ' . implode(' ', $errors);
        }
    }

    public function hookActionObjectProductAddAfter($params)
    {
        if (isset($params['object']) && $params['object'] instanceof Product) {
            $this->handleProductSave($params['object']->id);
        }
    }

    public function hookActionObjectProductUpdateAfter($params)
    {
        if (isset($params['object']) && $params['object'] instanceof Product) {
            $this->handleProductSave($params['object']->id);
        }
    }

    public function hookActionObjectProductDeleteAfter($params)
    {
        if (isset($params['object']) && $params['object'] instanceof Product) {
            Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . self::STORIES_TABLE . '` WHERE `id_product` = ' . (int) $params['object']->id
            );
        }
    }

    /**
     * Onglet "Navi" sur la fiche produit du Back Office — un seul hook
     * (displayAdminProductsExtra), contrairement à lstvideostory qui en
     * utilisait deux en parallèle pour la même fonction.
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        $idProduct = $this->resolveProductIdFromHookParams($params);
        if (!$idProduct) {
            return '';
        }

        $existing = [];
        foreach ($this->getStoriesForProduct($idProduct) as $row) {
            $existing[(int) $row['story_index']] = $row;
        }

        $slots = [];
        for ($index = 1; $index <= self::STORY_LIMIT; $index++) {
            $slots[] = [
                'index' => $index,
                'youtube' => $existing[$index]['youtube'] ?? '',
                'preview' => $existing[$index]['preview'] ?? '',
                'label' => $existing[$index]['label'] ?? '',
            ];
        }

        $this->context->smarty->assign([
            'navi_story_slots' => $slots,
            'navi_story_max_mb' => self::MAX_UPLOAD_BYTES / 1048576,
        ]);

        return $this->fetch('module:' . $this->name . '/views/templates/admin/story-fields.tpl');
    }

    /**
     * Rendu des bulles en fiche produit — un seul hook (contrairement à
     * lstvideostory, qui utilisait plusieurs hooks de rendu en parallèle et
     * affichait parfois la bulle deux fois).
     */
    public function hookDisplayAfterProductThumbs($params)
    {
        if (!$this->isProductPage()) {
            return '';
        }

        $idProduct = $this->getCurrentProductId();
        if (!$idProduct) {
            return '';
        }

        $stories = $this->getStoriesForProduct($idProduct);
        if (empty($stories)) {
            return '';
        }

        foreach ($stories as &$story) {
            $story['preview_is_video'] = substr($story['preview'], -4) === '.mp4';
        }
        unset($story);

        $this->context->smarty->assign([
            'navi_stories' => $stories,
            'navi_story_product_id' => $idProduct,
        ]);

        return $this->fetch('module:' . $this->name . '/views/templates/hook/story-bubbles.tpl');
    }
}
