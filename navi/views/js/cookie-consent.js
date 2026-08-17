document.addEventListener('DOMContentLoaded', function () {
    var banner = document.getElementById('navi-cookie-banner');
    var modal = document.getElementById('navi-cookie-modal-overlay');
    var modalBox = modal ? modal.querySelector('.navi-cookie-modal') : null;

    var lastFocusedElement = null;

    function getFocusableModalElements() {
        return modalBox.querySelectorAll('button, input:not([disabled]), a[href]');
    }

    function handleModalKeydown(event) {
        if (event.key === 'Escape') {
            closeModal();
            return;
        }
        if (event.key !== 'Tab') return;
        var focusable = getFocusableModalElements();
        if (focusable.length === 0) return;
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function openModal(trigger) {
        if (!modal || !modalBox) return;
        lastFocusedElement = trigger || document.activeElement;
        function apply() {
            modal.classList.add('navi-cookie-modal-overlay-open');
            document.addEventListener('keydown', handleModalKeydown);
        }
        if (window.navi) {
            window.navi.showDetail('cookie-consent', apply);
        } else {
            apply();
            modalBox.focus();
        }
    }

    // Nettoyage propre au module (retrait de la classe d'overlay, focus
    // restauré) séparé de la fermeture du bouton flottant lui-même :
    // réutilisé à la fois par closeModal() (Échap, après enregistrement des
    // choix) et par les boutons de fermeture câblés via wireCloseButton
    // ci-dessous (qui appellent forceClose() eux-mêmes, voir core.js).
    function cleanupModal() {
        if (!modal) return;
        modal.classList.remove('navi-cookie-modal-overlay-open');
        document.removeEventListener('keydown', handleModalKeydown);
        if (lastFocusedElement) lastFocusedElement.focus();
    }

    // Ferme entièrement le bouton flottant (pas retour au menu des icônes) :
    // après avoir choisi ses préférences cookies (ou simplement fermé la
    // modale), l'utilisateur s'attend à pouvoir naviguer directement sans
    // être gêné — même choix pour la croix du panier sticky.
    function closeModal() {
        cleanupModal();
        if (window.navi) window.navi.forceClose();
    }

    function setConsent(stats, mkt) {
        var expires = new Date(new Date().getTime() + 365 * 24 * 60 * 60 * 1000).toUTCString();
        // "secure" est ignoré silencieusement par le navigateur en HTTP : ne
        // l'ajouter qu'en HTTPS, sinon le cookie n'est jamais posé.
        var secureFlag = window.location.protocol === 'https:' ? '; secure' : '';
        var consentVersion = (typeof naviCookieConfig !== 'undefined' && naviCookieConfig.consentVersion) ? naviCookieConfig.consentVersion : '1';
        document.cookie = 'navi_consent_stats=' + stats + '; expires=' + expires + '; path=/; samesite=strict' + secureFlag;
        document.cookie = 'navi_consent_mkt=' + mkt + '; expires=' + expires + '; path=/; samesite=strict' + secureFlag;
        document.cookie = 'navi_consent_version=' + consentVersion + '; expires=' + expires + '; path=/; samesite=strict' + secureFlag;
        document.cookie = 'navi_consent_all=1; expires=' + expires + '; path=/; samesite=strict' + secureFlag;

        var statsStatus = stats === 1 ? 'granted' : 'denied';
        var mktStatus = mkt === 1 ? 'granted' : 'denied';

        // Consent Mode v2 : met à jour l'état si gtag existe déjà. Ne
        // remplace PAS un éventuel gtag('consent','default', ...) posé plus
        // tôt dans <head> (voir hookDisplayHeader, navi.php) — c'est ce
        // default, chargé AVANT le script Google, qui bloque réellement le
        // tracking tant que l'utilisateur n'a pas choisi.
        if (typeof gtag === 'function') {
            gtag('consent', 'update', {
                ad_storage: mktStatus,
                ad_user_data: mktStatus,
                ad_personalization: mktStatus,
                analytics_storage: statsStatus
            });
        }

        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({ event: 'navi_cookie_consent_updated' });

        if (banner) banner.style.display = 'none';
        closeModal();
        showSavedToast();
    }

    function showSavedToast() {
        var savedText = (typeof naviCookieConfig !== 'undefined' && naviCookieConfig.savedText)
            ? naviCookieConfig.savedText
            : 'Saved';
        var toast = document.createElement('div');
        toast.className = 'navi-cookie-toast';
        toast.setAttribute('role', 'status');
        toast.textContent = savedText;
        document.body.appendChild(toast);
        window.requestAnimationFrame(function () {
            toast.classList.add('navi-cookie-toast-visible');
        });
        setTimeout(function () {
            toast.classList.remove('navi-cookie-toast-visible');
            setTimeout(function () { toast.remove(); }, 300);
        }, 2200);
    }

    var btnAccepter = document.getElementById('navi-cookie-btn-accepter');
    var btnRefuser = document.getElementById('navi-cookie-btn-refuser');
    var btnPrefs = document.getElementById('navi-cookie-btn-prefs');
    var btnSavePrefs = document.getElementById('navi-cookie-btn-save-prefs');
    var btnCloseModal = document.getElementById('navi-cookie-btn-close-modal');
    var btnModalCross = modal ? modal.querySelector('.navi-cookie-modal-close') : null;

    if (btnAccepter) btnAccepter.addEventListener('click', function () { setConsent(1, 1); });
    if (btnRefuser) btnRefuser.addEventListener('click', function () { setConsent(0, 0); });

    if (btnPrefs) btnPrefs.addEventListener('click', function (event) {
        // #navi-cookie-banner n'est jamais déplacé à l'intérieur de
        // #navi-fab : sans stopPropagation, ce clic remonterait jusqu'à
        // document, où le noyau referme tout ce qui est cliqué en dehors du
        // FAB — annulant l'ouverture dans le clic même qui la demande.
        event.stopPropagation();
        if (banner) banner.style.display = 'none';
        openModal(btnPrefs);
    });

    // wireCloseButton (core.js) centralise "nettoyage propre au module ->
    // fermeture entière du bouton flottant" — chaque module du hub partage
    // exactement ce patron (voir core.js pour le détail).
    function handleModalCloseButton() {
        if (document.cookie.indexOf('navi_consent_all') === -1 && banner) banner.style.display = 'block';
        cleanupModal();
    }

    if (window.navi) {
        window.navi.wireCloseButton(btnCloseModal, handleModalCloseButton);
        window.navi.wireCloseButton(btnModalCross, handleModalCloseButton);
    } else {
        if (btnCloseModal) btnCloseModal.addEventListener('click', function () { handleModalCloseButton(); });
        if (btnModalCross) btnModalCross.addEventListener('click', function () { handleModalCloseButton(); });
    }

    if (btnSavePrefs) btnSavePrefs.addEventListener('click', function () {
        var stats = document.getElementById('navi-chk-stats').checked ? 1 : 0;
        var mkt = document.getElementById('navi-chk-mkt').checked ? 1 : 0;
        setConsent(stats, mkt);
    });

    // Réouverture de la modale depuis l'icône du bouton flottant.
    document.addEventListener('navi:action', function (event) {
        if (event.detail && event.detail.action === 'open-cookie-modal') {
            if (banner) banner.style.display = 'none';
            openModal();
        }
    });

    // Le hub s'est refermé entièrement (clic extérieur, Échap, un autre
    // module affiché...) pendant que ce panneau était actif.
    document.addEventListener('navi:closed', function (event) {
        if (event.detail && event.detail.id === 'cookie-consent') {
            if (modal) modal.classList.remove('navi-cookie-modal-overlay-open');
            document.removeEventListener('keydown', handleModalKeydown);
        }
    });
});
