const { test, expect } = require('@playwright/test');
const { productUrl, PRODUCT_IN_STOCK } = require('../fixtures');

test.describe('Consentement cookies', () => {
    test('enregistrer ses préférences ferme entièrement le bouton flottant', async ({ page }) => {
        await page.goto(productUrl(PRODUCT_IN_STOCK));

        const prefsBtn = page.locator('#navi-cookie-btn-prefs');
        if ((await prefsBtn.count()) === 0) {
            test.skip(true, 'bannière cookie déjà répondue sur cet environnement (cookie déjà posé)');
        }

        await prefsBtn.click();
        await expect(page.locator('#navi-fab')).toHaveAttribute('data-detail', 'cookie-consent');

        await page.click('#navi-cookie-btn-save-prefs');
        await expect(page.locator('#navi-fab')).toHaveAttribute('data-state', 'closed');
    });
});
