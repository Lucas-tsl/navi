// Identifiants produit utilisés par les tests — dépendent entièrement du
// catalogue de l'environnement contre lequel la suite tourne, configurables
// par variable d'environnement (voir README.md de ce dossier). Les valeurs
// par défaut (1/2) sont des identifiants PrestaShop de démonstration
// plausibles, pas garantis d'exister ; les specs se `skip()` proprement si
// le produit configuré n'a pas le contenu attendu.
const PRODUCT_IN_STOCK = process.env.NAVI_TEST_PRODUCT_IN_STOCK || '1';
const PRODUCT_OUT_OF_STOCK = process.env.NAVI_TEST_PRODUCT_OUT_OF_STOCK || '2';

function productUrl(idProduct) {
    return '/index.php?controller=product&id_product=' + idProduct;
}

// Accepte la bannière cookie si présente — la plupart des specs doivent
// commencer par ça pour ne pas avoir la bannière au-dessus du reste.
// #navi-cookie-btn-accepter (ID, pas la classe) : la classe
// `navi-cookie-btn-accepter` est réutilisée pour le style sur un AUTRE
// bouton ("Enregistrer mes choix" dans la modale préférences) — un
// sélecteur par classe matcherait les deux.
async function acceptCookies(page) {
    const btn = page.locator('#navi-cookie-btn-accepter');
    if (await btn.count()) {
        await btn.click();
    }
}

module.exports = {
    PRODUCT_IN_STOCK,
    PRODUCT_OUT_OF_STOCK,
    productUrl,
    acceptCookies,
};
