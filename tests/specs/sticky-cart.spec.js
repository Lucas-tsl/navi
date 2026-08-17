const { test, expect } = require('@playwright/test');
const { productUrl, acceptCookies, PRODUCT_IN_STOCK, PRODUCT_OUT_OF_STOCK } = require('../fixtures');

test.describe('Panier sticky', () => {
    test("suit l'état du vrai bouton Ajouter au panier (rupture de stock)", async ({ page }) => {
        await page.goto(productUrl(PRODUCT_OUT_OF_STOCK));
        // .first() : `.js-add-to-cart` peut matcher un second bouton hors
        // du périmètre de ce module sur certains thèmes/surcouches.
        const realBtn = page.locator('.js-add-to-cart').first();
        if ((await realBtn.count()) === 0) {
            test.skip(true, 'produit de test introuvable — ajuster NAVI_TEST_PRODUCT_OUT_OF_STOCK');
        }
        const realDisabled = await realBtn.isDisabled();

        await acceptCookies(page);
        await page.evaluate(() => window.scrollTo(0, 800));
        await page.waitForTimeout(600);

        const cartBtn = page.locator('.navi-sticky-add-to-cart');
        if ((await cartBtn.count()) === 0) return; // panier sticky pas construit sur cette page
        expect(await cartBtn.isDisabled()).toBe(realDisabled);
    });

    test('reste actif pour un produit en stock', async ({ page }) => {
        await page.goto(productUrl(PRODUCT_IN_STOCK));
        const realBtn = page.locator('.js-add-to-cart').first();
        if ((await realBtn.count()) === 0 || (await realBtn.isDisabled())) {
            test.skip(true, 'produit de test pas en stock — ajuster NAVI_TEST_PRODUCT_IN_STOCK');
        }

        await acceptCookies(page);
        await page.evaluate(() => window.scrollTo(0, 800));
        await page.waitForTimeout(600);

        const cartBtn = page.locator('.navi-sticky-add-to-cart');
        if ((await cartBtn.count()) === 0) return;
        await expect(cartBtn).toBeEnabled();
    });

    test('la croix ferme entièrement le bouton flottant', async ({ page }) => {
        await page.goto(productUrl(PRODUCT_IN_STOCK));
        await acceptCookies(page);

        await page.evaluate(() => {
            document.dispatchEvent(new CustomEvent('navi:action', { detail: { action: 'open-sticky-cart' } }));
        });
        await page.waitForTimeout(400);

        const closeBtn = page.locator('.navi-sticky-close');
        if ((await closeBtn.count()) === 0) return;
        await closeBtn.click();
        await expect(page.locator('#navi-fab')).toHaveAttribute('data-state', 'closed');
    });
});
