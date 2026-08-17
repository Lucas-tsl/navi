const { test, expect } = require('@playwright/test');
const { productUrl, acceptCookies, PRODUCT_IN_STOCK, dockerFixturesAvailable, withConfig } = require('../fixtures');

test.describe('Visibilité par appareil', () => {
    test.skip(!dockerFixturesAvailable(), 'docker inaccessible depuis cet environnement — voir tests/README.md');

    test('accessibilité masquée sur mobile quand désactivée pour ce viewport', async ({ page }) => {
        await withConfig({ NAVI_A11Y_SHOW_MOBILE: '0', NAVI_A11Y_SHOW_DESKTOP: '1' }, async () => {
            await page.setViewportSize({ width: 375, height: 800 });
            await page.goto(productUrl(PRODUCT_IN_STOCK));
            await acceptCookies(page);
            await page.click('#navi-fab-toggle');
            await expect(page.locator('.navi-fab-item[data-item-id="accessibility"]')).toBeHidden();
        });
    });

    test('accessibilité reste visible sur desktop même désactivée sur mobile', async ({ page }) => {
        await withConfig({ NAVI_A11Y_SHOW_MOBILE: '0', NAVI_A11Y_SHOW_DESKTOP: '1' }, async () => {
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.goto(productUrl(PRODUCT_IN_STOCK));
            await acceptCookies(page);
            await page.click('#navi-fab-toggle');
            await expect(page.locator('.navi-fab-item[data-item-id="accessibility"]')).toBeVisible();
        });
    });
});
