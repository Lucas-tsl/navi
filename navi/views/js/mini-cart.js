(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

    if (typeof jQuery === 'undefined') return;

    // Bascules "Afficher sur ordinateur/mobile" (Configurer > Mini-panier
    // automatique) — même seuil que le reste du hub (480px, voir
    // views/css/core.css). Pas de masquage CSS possible ici (voir
    // navi.php::hookActionFrontControllerSetMedia) : le comportement
    // entier ne doit simplement jamais s'activer sur la largeur exclue.
    var miniCartConfig = window.naviMiniCartConfig || { showOnDesktop: true, showOnMobile: true };
    var isMobileViewport = window.matchMedia('(max-width: 480px)').matches;
    if (isMobileViewport && !miniCartConfig.showOnMobile) return;
    if (!isMobileViewport && !miniCartConfig.showOnDesktop) return;

    var $ = jQuery;

    // ============================================================
    // Supprime la modale "produit ajouté" par défaut de PrestaShop
    // (#blockcart-modal) au profit de l'ouverture du mini-panier ci-dessous.
    // Un MutationObserver plutôt qu'un simple appel unique : PrestaShop
    // injecte cette modale dynamiquement après l'ajout, pas présente au
    // chargement de la page.
    // ============================================================
    function removeModals() {
        $('#blockcart-modal').remove();
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
    }

    removeModals();

    var modalObserver = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if ($(node).is('#blockcart-modal') || $(node).find('#blockcart-modal').length) {
                    removeModals();
                }
            });
        });
    });
    modalObserver.observe(document.body, { childList: true, subtree: true });

    // ============================================================
    // Anti-double-clic sur "Ajouter au panier" : évite un double ajout si
    // l'utilisateur clique plusieurs fois pendant que l'appel AJAX est en
    // cours (bouton réactivé après 2s, largement suffisant pour un aller-
    // retour réseau normal).
    // ============================================================
    $('body').on('click', '.add-to-cart', function () {
        var $btn = $(this);
        if ($btn.hasClass('disabled') || $btn.prop('disabled')) return false;
        $btn.addClass('disabled').prop('disabled', true);
        setTimeout(function () {
            $btn.removeClass('disabled').prop('disabled', false);
        }, 2000);
    });

    // ============================================================
    // Ouverture automatique du mini-panier après un ajout, fermeture
    // automatique après quelques secondes. Un seul timer de fermeture
    // actif à la fois (clearTimeout avant chaque nouvelle programmation) :
    // sans ça, deux ajouts rapprochés laissent deux fermetures automatiques
    // indépendantes en attente, celle du premier ajout pouvant fermer le
    // panier bien avant les secondes attendues depuis le second ajout.
    // ============================================================
    var OPEN_DELAY = 600; // laisse le temps à PrestaShop de finir son updateCart (rafraîchissement AJAX)
    var CLOSE_DELAY = 5000;
    var openTimeoutId = null;
    var closeTimeoutId = null;

    // Sélecteurs par défaut du thème PrestaShop "Classic" et de la plupart
    // de ses dérivés — un thème tiers avec un markup très différent peut ne
    // pas matcher ces sélecteurs, auquel cas ce module se désactive
    // proprement (voir l'avertissement console ci-dessous).
    function getCartElements() {
        var $container = $('#_desktop_cart');

        return {
            $container: $container,
            $toggle: $container.find('.dropdown-toggle-cart'),
            $dropdown: $container.find('.dropdown-menu-cart'),
        };
    }

    var missingCartWarned = false;

    function openMiniCart() {
        var els = getCartElements();
        if (!els.$toggle.length || !els.$dropdown.length) {
            if (!missingCartWarned) {
                missingCartWarned = true;
                console.warn(
                    '[navi] Mini-panier automatique : #_desktop_cart ' +
                    '.dropdown-toggle-cart/.dropdown-menu-cart introuvable — ' +
                    'le thème utilise peut-être un markup différent.'
                );
            }
            return;
        }

        els.$dropdown.stop(true, true).slideDown(300);
        els.$toggle.addClass('show').attr('aria-expanded', 'true');
        els.$container.find('.blockcart').addClass('active');

        if (closeTimeoutId) clearTimeout(closeTimeoutId);
        closeTimeoutId = setTimeout(function () {
            closeTimeoutId = null;
            closeMiniCart();
        }, CLOSE_DELAY);
    }

    function closeMiniCart() {
        var els = getCartElements();
        if (!els.$dropdown.length || !els.$dropdown.is(':visible')) return;

        els.$dropdown.stop(true, true).slideUp(300);
        els.$toggle.removeClass('show').attr('aria-expanded', 'false');
        els.$container.find('.blockcart').removeClass('active');
    }

    if (typeof prestashop !== 'undefined') {
        prestashop.on('updateCart', function (event) {
            removeModals();

            // Seulement pour un ajout produit — pas une simple mise à jour de
            // quantité ou une suppression, qui déclenchent aussi 'updateCart'.
            if (!event || !event.reason || event.reason.linkAction !== 'add-to-cart') return;

            if (openTimeoutId) clearTimeout(openTimeoutId);
            openTimeoutId = setTimeout(function () {
                openTimeoutId = null;
                openMiniCart();
            }, OPEN_DELAY);
        });
    }

    // ============================================================
    // Fermeture au clic en dehors du panier — annule aussi le timer de
    // fermeture auto en attente.
    // ============================================================
    $(document).on('click', function (event) {
        var els = getCartElements();
        if (els.$dropdown.length && els.$dropdown.is(':visible') && !$(event.target).closest('#_desktop_cart').length) {
            if (closeTimeoutId) {
                clearTimeout(closeTimeoutId);
                closeTimeoutId = null;
            }
            closeMiniCart();
        }
    });

    });
})();
