(function () {
    'use strict';

    // La combinaison CSS/JS de PrestaShop (Combine, Compress and Cache) peut
    // placer ce script AVANT le HTML injecté par displayBeforeBodyClosingTag
    // (footer.tpl) dans la page finale : sans cette attente, les
    // document.getElementById() ci-dessous renvoient null et tout le module
    // reste inerte (menu qui ne s'ouvre jamais, panneaux jamais déplacés dans
    // le slot partagé — ils restent alors affichés tels quels, en bas de
    // page). Même précaution que views/js/cookie-consent.js.
    document.addEventListener('DOMContentLoaded', function () {

        // :focus-visible seul ne suffit pas partout : certains navigateurs
        // affichent quand même l'anneau de focus après un clic souris sur nos
        // boutons (croix de fermeture, etc.). On détecte nous-mêmes la dernière
        // modalité utilisée (souris vs clavier) pour le masquer de façon fiable
        // (voir la règle html.navi-mouse-user dans views/css/core.css).
        var htmlEl = document.documentElement;
        document.addEventListener('mousedown', function () {
            htmlEl.classList.add('navi-mouse-user');
        }, true);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Tab') {
                htmlEl.classList.remove('navi-mouse-user');
            }
        }, true);

        // Les liens d'évitement (footer.tpl) sont rendus par
        // displayBeforeBodyClosingTag, donc tout en bas du DOM — un thème
        // dont le layout n'expose pas displayAfterBodyOpeningTag sur toutes
        // ses pages ne laisse pas d'autre point d'entrée pour les injecter
        // en tête de page côté serveur. Sans ce déplacement, un utilisateur
        // clavier devait tabuler à travers toute la page avant de les
        // atteindre — annulant leur utilité de lien d'évitement. Repositionnés
        // ici plutôt qu'en CSS (order/position) : ce sont de vrais premiers
        // éléments focusables du document, pas juste affichés en premier
        // visuellement.
        var bodyFirstChild = document.body.firstChild;
        ['navi-skip-link', 'navi-a11y-skip-link'].forEach(function (cls) {
            var link = document.querySelector('.' + cls);
            if (link) document.body.insertBefore(link, bodyFirstChild);
        });

        var config = window.naviConfig || { items: [], isProduct: false };
        var fab = document.getElementById('navi-fab');
        var toggle = document.getElementById('navi-fab-toggle');
        var menu = document.getElementById('navi-fab-menu');
        var detail = document.getElementById('navi-fab-detail');

        if (!fab || !toggle || !menu || !detail) return;

        // #navi-fab est UN SEUL objet qui traverse 3 états (voir
        // views/css/core.css) : 'closed' (engrenage), 'menu' (choix des
        // icônes), 'detail' (contenu du module choisi). Ce n'est jamais deux
        // blocs distincts qui se suivent.
        var state = 'closed';
        var activeDetail = null;
        var scrollPercent = 0;

        // Les panneaux rendus côté serveur (cookie-consent, accessibilité...)
        // sont déplacés une seule fois dans le slot partagé #navi-fab-detail,
        // pour qu'ils deviennent littéralement une partie du même objet
        // plutôt que des éléments fixed indépendants.
        ['navi-cookie-modal-overlay', 'navi-a11y-panel'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) detail.appendChild(el);
        });

        function setState(newState) {
            state = newState;
            fab.setAttribute('data-state', newState);
            toggle.setAttribute('aria-expanded', newState === 'closed' ? 'false' : 'true');
            if (newState === 'closed') {
                fab.removeAttribute('data-detail');
            }
        }

        function updateScrollPercent() {
            var doc = document.documentElement;
            var scrollTop = window.pageYOffset || doc.scrollTop;
            var height = (doc.scrollHeight - doc.clientHeight) || 1;
            scrollPercent = Math.min(100, Math.max(0, (scrollTop / height) * 100));
            fab.style.setProperty('--navi-scroll', String(scrollPercent));
        }

        function visibleItems() {
            return config.items.filter(function (item) {
                if (item.condition === 'is_product') return !!config.isProduct;
                if (item.condition === 'scroll') return scrollPercent >= (item.scrollThreshold || 100);
                return true;
            });
        }

        function renderMenu() {
            var items = visibleItems();
            menu.innerHTML = '';
            items.forEach(function (item, index) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'navi-fab-item';
                btn.style.setProperty('--navi-index', String(index));
                btn.setAttribute('role', 'menuitem');
                // Consommé par les règles @media "Afficher sur
                // ordinateur/mobile" injectées depuis navi.php (voir
                // Navi::getConfigStyleTag()) pour masquer une entrée
                // précise du menu selon la largeur d'écran.
                btn.setAttribute('data-item-id', item.id);
                btn.setAttribute('title', item.label);
                btn.setAttribute('aria-label', item.label);
                var icon = document.createElement('span');
                icon.setAttribute('aria-hidden', 'true');
                if (item.iconSvg) {
                    icon.className = 'navi-fab-item-icon navi-fab-item-icon--svg';
                    icon.innerHTML = item.iconSvg;
                } else {
                    icon.className = 'navi-fab-item-icon navi-fab-item-icon--emoji';
                    icon.textContent = item.icon;
                }

                var ring = document.createElement('span');
                ring.className = 'navi-fab-item-ring';
                ring.appendChild(icon);
                btn.appendChild(ring);

                var label = document.createElement('span');
                label.className = 'navi-fab-item-label';
                label.setAttribute('aria-hidden', 'true');
                label.textContent = item.shortLabel || item.label;
                btn.appendChild(label);
                btn.addEventListener('click', function () {
                    if (item.action === 'scroll-top') {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        forceClose();
                        return;
                    }
                    document.dispatchEvent(new CustomEvent('navi:action', { detail: item }));
                });
                menu.appendChild(btn);
            });

            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'navi-fab-menu-close';
            closeBtn.style.setProperty('--navi-index', String(items.length));
            closeBtn.setAttribute('aria-label', config.closeLabel || 'Close');
            closeBtn.textContent = '✕';
            closeBtn.addEventListener('click', function () {
                forceClose();
            });
            menu.appendChild(closeBtn);
        }

        function openMenu() {
            renderMenu();
            setState('menu');
        }

        function forceClose() {
            if (state === 'closed') return;
            var closingId = activeDetail;
            activeDetail = null;
            setState('closed');
            if (closingId) {
                document.dispatchEvent(new CustomEvent('navi:closed', { detail: { id: closingId } }));
            }
        }

        toggle.addEventListener('click', function () {
            if (state === 'closed') {
                openMenu();
            } else {
                forceClose();
            }
        });

        document.addEventListener('click', function (event) {
            if (state === 'closed' || fab.contains(event.target)) return;
            forceClose();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && state !== 'closed') forceClose();
        });

        var ticking = false;
        window.addEventListener('scroll', function () {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(function () {
                updateScrollPercent();
                if (state === 'menu') renderMenu();
                ticking = false;
            });
        }, { passive: true });

        updateScrollPercent();

        function focusActiveDetail() {
            var children = detail.children;
            for (var i = 0; i < children.length; i++) {
                var child = children[i];
                if (window.getComputedStyle(child).display !== 'none') {
                    if (typeof child.focus === 'function') child.focus();
                    return;
                }
            }
        }

        window.navi = {
            // moveFocus (défaut true) : à mettre à false pour un affichage
            // automatique non déclenché par l'utilisateur (ex. panier
            // sticky qui apparaît au scroll, views/js/sticky-cart.js) — sans
            // ça, le focus clavier était happé vers le panneau dès son
            // apparition automatique, y compris au tout premier chargement
            // d'une fiche produit, avant même que l'utilisateur n'ait
            // appuyé sur Tab. Une ouverture volontaire (clic sur une icône
            // du menu) doit en revanche toujours déplacer le focus.
            showDetail: function (id, applyFn, moveFocus) {
                // Un appel automatique (moveFocus === false) ne doit jamais
                // voler l'affichage à un panneau déjà ouvert par une action
                // volontaire de l'utilisateur. Seul un clic explicite
                // (moveFocus !== false) peut remplacer ce qui est déjà
                // affiché.
                if (moveFocus === false && activeDetail && activeDetail !== id) return;
                activeDetail = id;
                fab.setAttribute('data-detail', id);
                setState('detail');
                if (typeof applyFn === 'function') applyFn();
                if (moveFocus !== false) focusActiveDetail();
            },
            hideDetail: function (id, applyFn) {
                if (activeDetail !== id) return;
                activeDetail = null;
                setState('closed');
                if (typeof applyFn === 'function') applyFn();
            },
            backToMenu: function (id, applyFn) {
                if (activeDetail !== id) return;
                activeDetail = null;
                renderMenu();
                setState('menu');
                if (typeof applyFn === 'function') applyFn();
            },
            forceClose: forceClose,

            // Centralise le patron "bouton de fermeture -> nettoyage propre
            // au module -> fermeture entière du bouton flottant", répété à
            // l'identique dans chaque module du hub (sticky-cart,
            // cookie-consent...) — chacun avec sa propre garde
            // `if (window.navi)` et son propre appel à forceClose().
            // `cleanupFn` : nettoyage propre au module appelant (vider un
            // src d'iframe, retirer une classe CSS...), exécuté AVANT la
            // fermeture du bouton flottant.
            wireCloseButton: function (buttonEl, cleanupFn) {
                if (!buttonEl) return;
                buttonEl.addEventListener('click', function () {
                    if (typeof cleanupFn === 'function') cleanupFn();
                    forceClose();
                });
            }
        };
    });
})();
