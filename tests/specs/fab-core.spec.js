const { test, expect } = require('@playwright/test');
const { productUrl, acceptCookies, PRODUCT_IN_STOCK } = require('../fixtures');

test.describe('Bouton flottant (FAB) — comportements de base', () => {
    test('fermé par défaut au chargement', async ({ page }) => {
        await page.goto(productUrl(PRODUCT_IN_STOCK));
        await expect(page.locator('#navi-fab')).toHaveAttribute('data-state', 'closed');
    });

    test("clic sur l'engrenage ouvre le menu", async ({ page }) => {
        await page.goto(productUrl(PRODUCT_IN_STOCK));
        await acceptCookies(page);
        await page.click('#navi-fab-toggle');
        await expect(page.locator('#navi-fab')).toHaveAttribute('data-state', 'menu');
    });

    test('Échap ferme le menu', async ({ page }) => {
        await page.goto(productUrl(PRODUCT_IN_STOCK));
        await acceptCookies(page);
        await page.click('#navi-fab-toggle');
        await page.keyboard.press('Escape');
        await expect(page.locator('#navi-fab')).toHaveAttribute('data-state', 'closed');
    });
});
