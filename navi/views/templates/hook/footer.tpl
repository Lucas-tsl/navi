{* Lien d'évitement : sans lui, un utilisateur clavier doit tabuler à
   travers toute la page avant d'atteindre les réglages, le FAB étant rendu
   en tout dernier dans le DOM. *}
<a href="#navi-fab-toggle" class="navi-skip-link">{l s="Aller au menu d'accessibilité et cookies" mod='navi'}</a>

{* Second lien d'évitement (WCAG 2.4.1), distinct du précédent : celui-ci
   saute par-dessus l'en-tête/la navigation pour atteindre directement le
   contenu principal de la page. La cible réelle est déterminée en JS
   (views/js/accessibility.js), ce gabarit ne connaissant pas la structure
   de chaque page (fiche produit, CMS, catégorie...). *}
<a href="#navi-a11y-main-content" class="navi-a11y-skip-link">{l s='Aller au contenu' mod='navi'}</a>

{* Un seul objet DOM traverse les 3 états (fermé / menu / détail) : voir
   views/css/core.css et views/js/core.js. #navi-fab-detail est le slot
   partagé où chaque module vient afficher son propre contenu. *}
<div id="navi-fab" class="navi-fab" data-state="closed" data-position="{$navi_fab_position|escape:'html':'UTF-8'}">
    <button type="button" id="navi-fab-toggle" class="navi-fab-toggle" aria-expanded="false" aria-label="{l s='Ouvrir le menu' mod='navi'}">
        <span class="navi-fab-gear" aria-hidden="true">{$navi_gear_svg nofilter}</span>
    </button>
    <div id="navi-fab-menu" class="navi-fab-menu" role="menu"></div>
    <div id="navi-fab-detail" class="navi-fab-detail"></div>
</div>

<div id="navi-cookie-banner" class="navi-cookie-banner" role="region" aria-labelledby="navi-cookie-banner-title" style="display: {if $navi_cookie_choice_made}none{else}block{/if};">
    {if $navi_cookie_logo_url}<img src="{$navi_cookie_logo_url|escape:'html':'UTF-8'}" alt="" class="navi-cookie-logo">{/if}
    <h3 class="navi-cookie-title" id="navi-cookie-banner-title">{l s='Gérer le consentement' mod='navi'}</h3>
    <p class="navi-cookie-desc">{$navi_cookie_text|escape:'html':'UTF-8'|nl2br nofilter}</p>
    <div class="navi-cookie-links">
        <a href="{$navi_cookie_privacy_url|escape:'html':'UTF-8'}">{l s='Politique de confidentialité' mod='navi'}</a> | <a href="{$navi_cookie_legal_url|escape:'html':'UTF-8'}">{l s='Mentions légales' mod='navi'}</a>
    </div>
    {* "Tout Accepter" et "Tout Refuser" au même niveau, même poids visuel :
       la CNIL exige une prééminence équivalente entre les deux (recommandations
       2020). "Personnaliser" reste un choix possible mais secondaire. *}
    <div class="navi-cookie-actions">
        <button id="navi-cookie-btn-accepter" class="navi-cookie-btn navi-cookie-btn-accepter">{l s='Tout Accepter' mod='navi'}</button>
        <button id="navi-cookie-btn-refuser" class="navi-cookie-btn navi-cookie-btn-refuser">{l s='Tout Refuser' mod='navi'}</button>
    </div>
    <button id="navi-cookie-btn-prefs" class="navi-cookie-btn-link">{l s='Personnaliser mes choix' mod='navi'}</button>
</div>

<div id="navi-cookie-modal-overlay" class="navi-cookie-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="navi-cookie-modal-title" tabindex="-1">
    <div class="navi-cookie-modal" tabindex="-1">
        <button type="button" class="navi-cookie-modal-close" aria-label="{l s='Fermer' mod='navi'}">✕</button>
        <div class="navi-cookie-modal-scroll">
            <h3 class="navi-cookie-title" id="navi-cookie-modal-title">{l s='Préférences des cookies' mod='navi'}</h3>
            <div class="navi-cookie-type">
                <label for="navi-chk-necessaires">
                    <strong>{l s='Strictement Nécessaires' mod='navi'}</strong>
                    <p class="navi-cookie-desc">{l s='Requis pour le site (panier, sécurité). Non désactivables.' mod='navi'}</p>
                </label>
                <input type="checkbox" id="navi-chk-necessaires" checked disabled>
            </div>
            <div class="navi-cookie-type">
                <label for="navi-chk-stats">
                    <strong>{l s='Statistiques (Google Analytics)' mod='navi'}</strong>
                    <p class="navi-cookie-desc">{l s="Pour mesurer l'audience de la boutique." mod='navi'}</p>
                </label>
                <input type="checkbox" id="navi-chk-stats" {if $navi_cookie_stats_checked}checked{/if}>
            </div>
            <div class="navi-cookie-type">
                <label for="navi-chk-mkt">
                    <strong>{l s='Marketing (Pixel Facebook, Google Ads)' mod='navi'}</strong>
                    <p class="navi-cookie-desc">{l s='Pour afficher des publicités ciblées.' mod='navi'}</p>
                </label>
                <input type="checkbox" id="navi-chk-mkt" {if $navi_cookie_mkt_checked}checked{/if}>
            </div>
            <div class="navi-cookie-actions" style="margin-top: 20px;">
                <button id="navi-cookie-btn-save-prefs" class="navi-cookie-btn navi-cookie-btn-accepter">{l s='Enregistrer mes choix' mod='navi'}</button>
                <button id="navi-cookie-btn-close-modal" class="navi-cookie-btn navi-cookie-btn-refuser">{l s='Annuler' mod='navi'}</button>
            </div>
        </div>
    </div>
</div>

{* Panneau Accessibilité : rendu au niveau racine, déplacé dans le slot
   partagé #navi-fab-detail par views/js/core.js au chargement — même
   traitement que #navi-cookie-modal-overlay ci-dessus. tabindex="-1" :
   reçoit le focus programmatique à l'ouverture (focusActiveDetail,
   views/js/core.js), mais n'est pas lui-même un élément interactif. *}
<div id="navi-a11y-panel" class="navi-a11y-panel" tabindex="-1">
    <button type="button" class="navi-a11y-close" aria-label="{l s='Fermer' mod='navi'}">✕</button>
    <div class="navi-a11y-scroll">
        <h3 class="navi-a11y-title">{l s='Accessibilité' mod='navi'}</h3>

        <div class="navi-a11y-row">
            <span id="navi-a11y-textsize-label">{l s='Taille du texte' mod='navi'}</span>
            <div class="navi-a11y-stepper">
                <button type="button" id="navi-a11y-textsize-dec" aria-label="{l s='Réduire la taille du texte' mod='navi'}" aria-describedby="navi-a11y-textsize-label">−</button>
                <span id="navi-a11y-textsize-value" aria-live="polite">100%</span>
                <button type="button" id="navi-a11y-textsize-inc" aria-label="{l s='Augmenter la taille du texte' mod='navi'}" aria-describedby="navi-a11y-textsize-label">+</button>
            </div>
        </div>

        <div class="navi-a11y-row">
            <span>{l s='Contraste élevé' mod='navi'}</span>
            <button type="button" id="navi-a11y-contrast-toggle" class="navi-a11y-switch" aria-pressed="false">
                <span class="navi-a11y-switch-knob"></span>
            </button>
        </div>

        <div class="navi-a11y-row navi-a11y-row--cursor">
            <span>{l s='Curseur agrandi' mod='navi'}</span>
            <button type="button" id="navi-a11y-cursor-toggle" class="navi-a11y-switch" aria-pressed="false">
                <span class="navi-a11y-switch-knob"></span>
            </button>
        </div>

        <div class="navi-a11y-row">
            <span>{l s='Souligner les liens' mod='navi'}</span>
            <button type="button" id="navi-a11y-underline-toggle" class="navi-a11y-switch" aria-pressed="false">
                <span class="navi-a11y-switch-knob"></span>
            </button>
        </div>

        <button type="button" id="navi-a11y-reset" class="navi-a11y-reset">{l s='Réinitialiser les réglages' mod='navi'}</button>
    </div>
</div>
