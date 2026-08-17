(function () {
    'use strict';

    // Même précaution que views/js/core.js : la combinaison CSS/JS de
    // PrestaShop peut placer ce script avant le HTML de la fiche produit
    // dans la page finale.
    document.addEventListener('DOMContentLoaded', function () {

    var i18n = window.naviStickyCartI18n || {
        addToCart: 'Add to cart',
        adding: 'Adding...',
        added: 'Added',
        outOfStock: 'Out of stock',
        closeLabel: 'Close'
    };

    var detail = document.getElementById('navi-fab-detail');

    // Sélecteurs par défaut du thème PrestaShop "Classic" et de la plupart
    // de ses dérivés — un thème tiers avec un markup très différent peut ne
    // pas matcher ces sélecteurs, auquel cas le panier sticky se
    // désactive proprement (voir l'avertissement console ci-dessous)
    // plutôt que d'afficher un panneau cassé.
    var addToCartBtn = document.querySelector('#add-to-cart-or-refresh button[type="submit"], .js-add-to-cart');

    // Ce script n'est chargé QUE sur une fiche produit (voir
    // navi.php::isProductPage()) : contrairement à `!detail` (coquille du
    // hub lui-même, couverte ailleurs), l'absence du vrai bouton "Ajouter
    // au panier" signale un vrai changement du côté du thème. Avertit
    // plutôt que d'échouer silencieusement.
    if (!detail) return;
    if (!addToCartBtn) {
        console.warn(
            '[navi] Panier sticky : bouton "Ajouter au panier" introuvable ' +
            '(sélecteur attendu : #add-to-cart-or-refresh button[type="submit"], .js-add-to-cart) ' +
            'sur une page produit — le thème utilise peut-être un markup différent.'
        );
        return;
    }

    // ============================================================
    // Construction du panneau — un seul objet, comme les autres modules du
    // hub (cookies, accessibilité). Limitation connue : pas de sélecteur de
    // variation/déclinaison — pour un produit avec combinaisons, ce panneau
    // reflète l'état de la combinaison actuellement affichée sur la page,
    // sans permettre d'en changer depuis le panneau lui-même.
    // ============================================================
    var bar = document.createElement('div');
    bar.id = 'navi-sticky-bar';
    bar.className = 'navi-sticky-bar';
    bar.tabIndex = -1;
    bar.innerHTML =
        '<button type="button" class="navi-sticky-close" aria-label="' + i18n.closeLabel + '">✕</button>' +
        '<div class="navi-sticky-scroll">' +
        '<div class="navi-sticky-content">' +
        '<div class="navi-sticky-image"><img class="navi-sticky-img" src="" alt=""></div>' +
        '<div class="navi-sticky-info">' +
        '<span class="navi-sticky-name"></span>' +
        '</div>' +
        '<div class="navi-sticky-cart-zone">' +
        '<button type="button" class="navi-sticky-add-to-cart" disabled>' +
        '<span class="navi-sticky-button-text">' + i18n.addToCart + '</span>' +
        '<span class="navi-sticky-dash" aria-hidden="true">-</span>' +
        '<span class="navi-sticky-price"></span>' +
        '</button>' +
        '<div class="navi-sticky-out-of-stock" style="display:none;">' + i18n.outOfStock + '</div>' +
        '</div>' +
        '</div>' +
        '</div>';
    detail.appendChild(bar);

    var closeBtn = bar.querySelector('.navi-sticky-close');
    var cartBtn = bar.querySelector('.navi-sticky-add-to-cart');
    var outOfStockLabel = bar.querySelector('.navi-sticky-out-of-stock');
    var nameEl = bar.querySelector('.navi-sticky-name');
    var priceEl = bar.querySelector('.navi-sticky-price');
    var imgEl = bar.querySelector('.navi-sticky-img');

    // ============================================================
    // Contenu : repris directement de la fiche produit affichée, pas d'appel
    // API séparé — le panneau reflète simplement ce que la page montre déjà.
    // ============================================================
    var titleEl = document.querySelector('h1.product-name, h1[itemprop="name"], h1.h1');
    if (titleEl) {
        nameEl.textContent = titleEl.textContent.trim();
        bar.setAttribute('aria-label', titleEl.textContent.trim());
    }

    var priceSourceEl = document.querySelector('.product-price.current-price, .product__product-price.product-price, .current-price');
    if (priceSourceEl) {
        priceEl.textContent = priceSourceEl.textContent.trim();
    }

    var coverImg = document.querySelector('.js-qv-product-cover, #product-images img, .product-cover img');
    if (coverImg && coverImg.src) {
        imgEl.src = coverImg.src;
        imgEl.alt = titleEl ? titleEl.textContent.trim() : '';
    }

    // ============================================================
    // Rupture de stock : reprend l'état du VRAI bouton "Ajouter au panier"
    // (attribut disabled) plutôt qu'une classe globale posée sur <body> par
    // certains thèmes pour refléter un réglage admin ("autoriser les
    // commandes en ligne") — cette classe ne reflète pas forcément le stock
    // réel du produit affiché.
    // ============================================================
    function setOutOfStock(outOfStock) {
        cartBtn.disabled = outOfStock;
        cartBtn.style.display = outOfStock ? 'none' : 'flex';
        outOfStockLabel.style.display = outOfStock ? 'block' : 'none';
    }

    setOutOfStock(addToCartBtn.disabled);

    // ============================================================
    // Ajout au panier : déclenche le VRAI bouton de la fiche produit (garde
    // toute la logique PrestaShop existante — quantité, règles de panier...)
    // plutôt que de la ré-implémenter ; on écoute juste sa confirmation.
    // ============================================================
    var addingInProgress = false;

    cartBtn.addEventListener('click', function () {
        if (addingInProgress || cartBtn.disabled) return;
        addingInProgress = true;

        var originalHTML = cartBtn.innerHTML;
        cartBtn.classList.add('loading');
        cartBtn.disabled = true;
        cartBtn.innerHTML = '<span class="navi-sticky-loading-text">' + i18n.adding + '</span>';

        // Filet de sécurité : si l'événement 'updateCart' n'arrive jamais
        // (erreur réseau, thème qui ne l'émet pas...), le bouton ne doit pas
        // rester bloqué indéfiniment sur "Ajout en cours...".
        var safetyTimeout = setTimeout(function () {
            addingInProgress = false;
            cartBtn.classList.remove('loading');
            cartBtn.disabled = false;
            cartBtn.innerHTML = originalHTML;
        }, 6000);

        function onAdded() {
            clearTimeout(safetyTimeout);
            addingInProgress = false;
            cartBtn.classList.remove('loading');
            cartBtn.classList.add('added');
            cartBtn.disabled = false;
            cartBtn.innerHTML = '<span>✓ ' + i18n.added + '</span>';
            setTimeout(function () {
                cartBtn.classList.remove('added');
                cartBtn.innerHTML = originalHTML;
            }, 1200);
        }

        // window.prestashop est l'EventEmitter du thème : .once() se
        // désabonne de lui-même après le premier déclenchement, pas besoin
        // de gérer le retrait à la main.
        if (window.prestashop && typeof window.prestashop.once === 'function') {
            window.prestashop.once('updateCart', onAdded);
        }

        addToCartBtn.click();
    });

    // ============================================================
    // Affichage/masquage au scroll, ancré au bouton flottant (comme les
    // autres panneaux). Masqué quand le vrai bouton "Ajouter au panier" est
    // déjà visible à l'écran, ou en approchant du pied de page.
    // ============================================================
    var dismissedManually = false;

    // focus : ne déplace le focus clavier que pour une ouverture voulue par
    // l'utilisateur (icône du menu) — pas pour l'apparition automatique au
    // scroll (checkVisibility ci-dessous), qui volerait sinon le focus sans
    // action de l'utilisateur, y compris au tout premier chargement d'une
    // fiche produit. Voir window.navi.showDetail, views/js/core.js.
    // Fermeture manuelle gérée séparément par wireCloseButton ci-dessous
    // (fermeture entière du bouton flottant, pas juste masquage du panier).
    function setVisible(visible, focus) {
        if (!window.navi) {
            bar.classList.toggle('navi-sticky-visible', visible);
            return;
        }
        if (visible) {
            window.navi.showDetail('sticky-cart', function () {
                bar.classList.add('navi-sticky-visible');
            }, focus !== false);
        } else {
            window.navi.hideDetail('sticky-cart', function () {
                bar.classList.remove('navi-sticky-visible');
            });
        }
    }

    // wireCloseButton (core.js) centralise "nettoyage propre au module ->
    // fermeture entière du bouton flottant" — chaque module du hub partage
    // ce patron. Ferme entièrement le bouton flottant (pas retour au menu) :
    // le panier sticky est une fonctionnalité intégrée à l'UX de la fiche
    // produit, le fermer ne présume pas qu'on veuille enchaîner sur une
    // autre proposée dans le menu.
    if (window.navi) {
        window.navi.wireCloseButton(closeBtn, function () {
            dismissedManually = true;
            bar.classList.remove('navi-sticky-visible');
        });
    } else {
        closeBtn.addEventListener('click', function () {
            dismissedManually = true;
            bar.classList.remove('navi-sticky-visible');
        });
    }

    // Rouvre après une fermeture manuelle, via l'icône du menu du bouton
    // flottant (voir navi.php, item 'sticky-cart') — action volontaire de
    // l'utilisateur, le focus doit suivre.
    document.addEventListener('navi:action', function (event) {
        if (event.detail && event.detail.action === 'open-sticky-cart') {
            dismissedManually = false;
            setVisible(true, true);
        }
    });

    document.addEventListener('navi:closed', function (event) {
        if (event.detail && event.detail.id === 'sticky-cart') {
            bar.classList.remove('navi-sticky-visible');
        }
    });

    function checkVisibility() {
        if (dismissedManually) return;

        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var windowHeight = window.innerHeight;
        var windowBottom = scrollTop + windowHeight;
        var docHeight = document.documentElement.scrollHeight;

        // Se base sur la fin du document plutôt que sur un sélecteur de
        // pied de page : certains thèmes ont plusieurs éléments <footer>
        // sur une fiche produit, le premier trouvé dans le DOM n'étant pas
        // forcément le vrai pied de page du site.
        if (docHeight - windowBottom <= 200) {
            setVisible(false);
            return;
        }

        var btnRect = addToCartBtn.getBoundingClientRect();
        var btnTop = btnRect.top + scrollTop;
        var btnBottom = btnTop + btnRect.height;
        var btnVisibleOnScreen = btnRect.width > 0 && btnRect.height > 0;
        var btnInViewport = btnTop < windowBottom && btnBottom > scrollTop && btnVisibleOnScreen;

        setVisible(!btnInViewport, false);
    }

    var ticking = false;
    function throttledCheck() {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(function () {
            checkVisibility();
            ticking = false;
        });
    }

    window.addEventListener('scroll', throttledCheck, { passive: true });
    window.addEventListener('resize', throttledCheck);

    setTimeout(checkVisibility, 300);

    });
})();
