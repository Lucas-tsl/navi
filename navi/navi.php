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
    public function __construct()
    {
        $this->name = 'navi';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Troteseil Lucas';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Navi');
        $this->description = $this->l(
            "Hub d'engagement flottant : consentement cookies (Google Consent " .
            'Mode v2), accessibilité (taille du texte, contraste, curseur) et ' .
            'ajout au panier sticky sur les fiches produit, pilotés depuis un ' .
            'bouton unique.'
        );
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => '8.99.99'];

        $this->confirmUninstall = $this->l('Supprimer ce module désactivera le bouton flottant et tous ses modules.');
    }

    /**
     * Hooks utilisés :
     * - displayHeader : pose gtag('consent','default', ...) le plus tôt
     *   possible dans <head>, AVANT que Google Analytics ne charge — sans
     *   ça, le consentement par défaut ne bloque rien puisque le tracking a
     *   déjà eu l'occasion de démarrer.
     * - actionFrontControllerSetMedia : point d'entrée recommandé (PS 1.7+)
     *   pour enregistrer CSS/JS uniquement sur les pages front-office.
     * - displayBeforeBodyClosingTag : injecte la coquille du bouton flottant
     *   juste avant </body>.
     */
    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('displayBeforeBodyClosingTag')
            && Configuration::updateValue('NAVI_COOKIE_TEXT', $this->getDefaultCookieText())
            && Configuration::updateValue('NAVI_COOKIE_PRIVACY_URL', '')
            && Configuration::updateValue('NAVI_COOKIE_LEGAL_URL', '')
            && Configuration::updateValue('NAVI_COOKIE_LOGO_URL', $this->getDefaultLogoUrl());
    }

    /**
     * Logo du thème (Configurer > Apparence > Logos, PS_LOGO) converti en
     * URL publique complète — pré-remplit le champ « Logo » de la bannière
     * cookie sans obliger l'admin à ressaisir une URL déjà connue du site.
     * Reste modifiable ensuite dans Configurer (autre logo, ou vide pour ne
     * pas en afficher).
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

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName('NAVI_COOKIE_TEXT')
            && Configuration::deleteByName('NAVI_COOKIE_PRIVACY_URL')
            && Configuration::deleteByName('NAVI_COOKIE_LEGAL_URL')
            && Configuration::deleteByName('NAVI_COOKIE_LOGO_URL');
    }

    private function getDefaultCookieText()
    {
        return $this->l(
            'Nous utilisons des cookies pour assurer le bon fonctionnement du site, ' .
            'analyser notre trafic et personnaliser nos publicités. Vous pouvez choisir vos préférences ci-dessous.'
        );
    }

    /**
     * Réglages back-office (Modules > Navi > Configurer) : texte de la
     * bannière cookie et liens légaux.
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitNaviCookie')) {
            Configuration::updateValue('NAVI_COOKIE_TEXT', (string) Tools::getValue('NAVI_COOKIE_TEXT'));
            Configuration::updateValue('NAVI_COOKIE_PRIVACY_URL', (string) Tools::getValue('NAVI_COOKIE_PRIVACY_URL'));
            Configuration::updateValue('NAVI_COOKIE_LEGAL_URL', (string) Tools::getValue('NAVI_COOKIE_LEGAL_URL'));
            Configuration::updateValue('NAVI_COOKIE_LOGO_URL', (string) Tools::getValue('NAVI_COOKIE_LOGO_URL'));
            $output .= $this->displayConfirmation($this->l('Réglages enregistrés.'));
        }

        return $output . $this->renderForm();
    }

    private function renderForm()
    {
        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Bannière cookies'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
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

        $helper->fields_value = [
            'NAVI_COOKIE_LOGO_URL' => Configuration::get('NAVI_COOKIE_LOGO_URL'),
            'NAVI_COOKIE_TEXT' => Configuration::get('NAVI_COOKIE_TEXT'),
            'NAVI_COOKIE_PRIVACY_URL' => Configuration::get('NAVI_COOKIE_PRIVACY_URL'),
            'NAVI_COOKIE_LEGAL_URL' => Configuration::get('NAVI_COOKIE_LEGAL_URL'),
        ];

        return $helper->generateForm([$fieldsForm]);
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
        </script>';
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

        // Panier sticky : chargé uniquement sur les fiches produit — inutile
        // ailleurs, ce panneau n'a rien à afficher hors d'une page produit.
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
        }

        // Config JS du bouton flottant : liste des modules actifs et leurs
        // conditions d'affichage dans le menu. Pas d'entrée "stories" ici —
        // la gestion native des bulles vidéo arrive dans un chantier
        // ultérieur (voir CHANGELOG).
        Media::addJsDef([
            'naviConfig' => [
                'items' => [
                    // Icône "retour en haut" : toujours proposée par le
                    // noyau, visible uniquement après 50% de scroll — pas un
                    // module, elle n'a pas d'état activable.
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
                ],
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
     * produit (panier sticky).
     */
    private function isProductPage()
    {
        return isset($this->context->controller) && $this->context->controller instanceof ProductController;
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
}
