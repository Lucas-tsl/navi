const { test, expect } = require('@playwright/test');
const { productUrl, acceptCookies, PRODUCT_IN_STOCK, dockerFixturesAvailable, withConfig } = require('../fixtures');

test.describe('Mini-panier automatique', () => {
    test.skip(!dockerFixturesAvailable(), 'docker inaccessible depuis cet environnement — voir tests/README.md');

    test('ouverture automatique après un ajout au panier', async ({ page }) => {
        await withConfig({
            NAVI_MINICART_ENABLED: '1',
            NAVI_MINICART_SHOW_DESKTOP: '1',
            NAVI_MINICART_SHOW_MOBILE: '1',
        }, async () => {
            await page.goto(productUrl(PRODUCT_IN_STOCK));
            await acceptCookies(page);

            // .locator(...).first() choisit le premier match dans l'ordre du
            // DOM, pas forcément celui réellement affiché : ce thème rend
            // deux copies du bouton "Ajouter au panier" (desktop + mobile),
            // une seule visible selon la largeur — trouvé en écrivant ce
            // test (le clic réussissait sur la copie masquée, aucun
            // événement 'updateCart' réel n'était donc déclenché).
            const addToCartBtn = page.locator('#add-to-cart-or-refresh button[type="submit"]:visible, .js-add-to-cart:visible').first();
            if ((await addToCartBtn.count()) === 0 || (await addToCartBtn.isDisabled())) {
                test.skip(true, "bouton \"Ajouter au panier\" introuvable ou désactivé — ajuster NAVI_TEST_PRODUCT_IN_STOCK");
            }

            const dropdown = page.locator('#_desktop_cart .dropdown-menu-cart');
            if ((await dropdown.count()) === 0) {
                test.skip(true, '#_desktop_cart introuvable — thème sans ce markup');
            }

            await addToCartBtn.click();
            await expect(dropdown).toBeVisible({ timeout: 5000 });
        });
    });

    test('ne provoque aucune ouverture quand désactivé', async ({ page }) => {
        await withConfig({ NAVI_MINICART_ENABLED: '0' }, async () => {
            await page.goto(productUrl(PRODUCT_IN_STOCK));
            await acceptCookies(page);

            // .locator(...).first() choisit le premier match dans l'ordre du
            // DOM, pas forcément celui réellement affiché : ce thème rend
            // deux copies du bouton "Ajouter au panier" (desktop + mobile),
            // une seule visible selon la largeur — trouvé en écrivant ce
            // test (le clic réussissait sur la copie masquée, aucun
            // événement 'updateCart' réel n'était donc déclenché).
            const addToCartBtn = page.locator('#add-to-cart-or-refresh button[type="submit"]:visible, .js-add-to-cart:visible').first();
            if ((await addToCartBtn.count()) === 0 || (await addToCartBtn.isDisabled())) {
                test.skip(true, "bouton \"Ajouter au panier\" introuvable ou désactivé — ajuster NAVI_TEST_PRODUCT_IN_STOCK");
            }

            const dropdown = page.locator('#_desktop_cart .dropdown-menu-cart');
            await addToCartBtn.click();
            await page.waitForTimeout(1200);
            if ((await dropdown.count()) > 0) {
                await expect(dropdown).not.toBeVisible();
            }
        });
    });
});
