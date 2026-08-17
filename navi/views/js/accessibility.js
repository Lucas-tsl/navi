(function () {
    'use strict';

    // Même précaution que views/js/core.js : la combinaison CSS/JS de
    // PrestaShop peut placer ce script avant le HTML de footer.tpl dans la
    // page finale, auquel cas les document.getElementById() ci-dessous
    // renverraient null.
    document.addEventListener('DOMContentLoaded', function () {

        var STORAGE_CONTRAST = 'navi_a11y_contrast';
        var STORAGE_CURSOR = 'navi_a11y_cursor';
        var STORAGE_UNDERLINE = 'navi_a11y_underline';
        var STORAGE_TEXTSIZE = 'navi_a11y_textsize_index';
        var TEXT_SIZES = [100, 112, 125, 137, 150];

        var html = document.documentElement;
        var panel = document.getElementById('navi-a11y-panel');
        var closeBtn = panel ? panel.querySelector('.navi-a11y-close') : null;
        var contrastToggle = document.getElementById('navi-a11y-contrast-toggle');
        var cursorToggle = document.getElementById('navi-a11y-cursor-toggle');
        var underlineToggle = document.getElementById('navi-a11y-underline-toggle');
        var textDecBtn = document.getElementById('navi-a11y-textsize-dec');
        var textIncBtn = document.getElementById('navi-a11y-textsize-inc');
        var textValueEl = document.getElementById('navi-a11y-textsize-value');
        var resetBtn = document.getElementById('navi-a11y-reset');

        // --- Préférences persistantes (contraste / curseur / soulignage),
        // appliquées dès le chargement ---
        function readPref(key) {
            try {
                return window.localStorage.getItem(key) === '1';
            } catch {
                return false;
            }
        }

        function writePref(key, active) {
            try {
                window.localStorage.setItem(key, active ? '1' : '0');
            } catch {
                // Stockage indisponible (navigation privée...) : le réglage ne
                // sera simplement pas mémorisé d'une page à l'autre.
            }
        }

        function applyToggleState(className, storageKey, button) {
            var active = readPref(storageKey);
            html.classList.toggle(className, active);
            if (button) button.setAttribute('aria-pressed', active ? 'true' : 'false');
        }

        applyToggleState('navi-a11y-contrast', STORAGE_CONTRAST, contrastToggle);
        applyToggleState('navi-a11y-large-cursor', STORAGE_CURSOR, cursorToggle);
        applyToggleState('navi-a11y-underline-links', STORAGE_UNDERLINE, underlineToggle);

        if (contrastToggle) {
            contrastToggle.addEventListener('click', function () {
                var active = !html.classList.contains('navi-a11y-contrast');
                html.classList.toggle('navi-a11y-contrast', active);
                contrastToggle.setAttribute('aria-pressed', active ? 'true' : 'false');
                writePref(STORAGE_CONTRAST, active);
            });
        }

        if (cursorToggle) {
            cursorToggle.addEventListener('click', function () {
                var active = !html.classList.contains('navi-a11y-large-cursor');
                html.classList.toggle('navi-a11y-large-cursor', active);
                cursorToggle.setAttribute('aria-pressed', active ? 'true' : 'false');
                writePref(STORAGE_CURSOR, active);
            });
        }

        if (underlineToggle) {
            underlineToggle.addEventListener('click', function () {
                var active = !html.classList.contains('navi-a11y-underline-links');
                html.classList.toggle('navi-a11y-underline-links', active);
                underlineToggle.setAttribute('aria-pressed', active ? 'true' : 'false');
                writePref(STORAGE_UNDERLINE, active);
            });
        }

        // --- Taille du texte : applique un pourcentage sur la racine (rem),
        // mémorisé au même titre que le contraste et le curseur. Suppose que
        // le thème actif déclare son font-size de base en unité relative
        // (rem/%) plutôt qu'en px absolu sur :root — cas de la grande
        // majorité des thèmes PrestaShop récents (Classic et dérivés). ---
        var textSizeIndex = 0;

        function applyTextSize(index) {
            textSizeIndex = Math.min(Math.max(index, 0), TEXT_SIZES.length - 1);
            var percent = TEXT_SIZES[textSizeIndex];
            html.style.fontSize = percent + '%';
            if (textValueEl) textValueEl.textContent = percent + '%';
            if (textDecBtn) textDecBtn.disabled = (textSizeIndex === 0);
            if (textIncBtn) textIncBtn.disabled = (textSizeIndex === TEXT_SIZES.length - 1);
        }

        function readTextSizeIndex() {
            try {
                var parsed = parseInt(window.localStorage.getItem(STORAGE_TEXTSIZE), 10);
                return isNaN(parsed) ? 0 : parsed;
            } catch {
                return 0;
            }
        }

        function writeTextSizeIndex(index) {
            try {
                window.localStorage.setItem(STORAGE_TEXTSIZE, String(index));
            } catch {
                // Stockage indisponible (navigation privée...) : le réglage ne
                // sera simplement pas mémorisé d'une page à l'autre.
            }
        }

        applyTextSize(readTextSizeIndex());

        if (textDecBtn) {
            textDecBtn.addEventListener('click', function () {
                applyTextSize(textSizeIndex - 1);
                writeTextSizeIndex(textSizeIndex);
            });
        }

        if (textIncBtn) {
            textIncBtn.addEventListener('click', function () {
                applyTextSize(textSizeIndex + 1);
                writeTextSizeIndex(textSizeIndex);
            });
        }

        // --- Réinitialisation groupée : sans elle, l'utilisateur devait rouvrir
        // chaque bascule une à une pour revenir à l'état par défaut. ---
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                html.classList.remove('navi-a11y-contrast', 'navi-a11y-large-cursor', 'navi-a11y-underline-links');
                writePref(STORAGE_CONTRAST, false);
                writePref(STORAGE_CURSOR, false);
                writePref(STORAGE_UNDERLINE, false);
                if (contrastToggle) contrastToggle.setAttribute('aria-pressed', 'false');
                if (cursorToggle) cursorToggle.setAttribute('aria-pressed', 'false');
                if (underlineToggle) underlineToggle.setAttribute('aria-pressed', 'false');

                applyTextSize(0);
                writeTextSizeIndex(0);
            });
        }

        // --- Lien d'évitement (views/templates/hook/footer.tpl) ---
        // Ce module ne connaît pas la structure exacte de chaque page (fiche
        // produit, CMS, catégorie...) : on cherche le premier repère de contenu
        // principal usuel plutôt que de supposer un id particulier, et on lui
        // ajoute un tabindex si besoin pour que le focus y atterrisse même s'il
        // n'est pas nativement focusable.
        var skipLink = document.querySelector('.navi-a11y-skip-link');
        if (skipLink) {
            var mainContent = document.querySelector('main, [role="main"], #content, #main, #primary');
            if (mainContent) {
                if (!mainContent.id) mainContent.id = 'navi-a11y-main-content';
                if (!mainContent.hasAttribute('tabindex')) mainContent.setAttribute('tabindex', '-1');
                skipLink.setAttribute('href', '#' + mainContent.id);
            }
        }

        // --- Ouverture / fermeture du panneau, coordonnée avec le hub ---
        if (!panel) return;

        function openPanel() {
            function apply() {
                panel.classList.add('navi-a11y-panel-open');
            }
            if (window.navi) {
                window.navi.showDetail('accessibility', apply);
            } else {
                apply();
            }
        }

        // Fermeture manuelle (croix) : revient au choix des icônes (état 2),
        // voir views/js/core.js.
        function closePanel() {
            function apply() {
                panel.classList.remove('navi-a11y-panel-open');
            }
            if (window.navi) {
                window.navi.backToMenu('accessibility', apply);
            } else {
                apply();
            }
        }

        document.addEventListener('navi:action', function (event) {
            if (event.detail && event.detail.action === 'open-accessibility-panel') {
                openPanel();
            }
        });

        // Le hub s'est refermé entièrement pendant que ce panneau était actif
        // (clic extérieur, Échap, un autre module affiché...) : on remet à jour
        // notre propre état d'affichage sans redéclencher de fermeture.
        document.addEventListener('navi:closed', function (event) {
            if (event.detail && event.detail.id === 'accessibility') {
                panel.classList.remove('navi-a11y-panel-open');
            }
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', closePanel);
        }

        // Le clic en dehors et la touche Échap sont gérés de façon centralisée
        // par le noyau (views/js/core.js), puisque ce panneau est un contenu du
        // même objet #navi-fab plutôt qu'un élément indépendant.
    });
})();
