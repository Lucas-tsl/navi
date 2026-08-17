const { test, expect } = require('@playwright/test');
const { productUrl, PRODUCT_IN_STOCK, dockerFixturesAvailable, withConfig } = require('../fixtures');

test.describe('Apparence', () => {
    test.skip(!dockerFixturesAvailable(), 'docker inaccessible depuis cet environnement — voir tests/README.md');

    test("couleur d'accent et position du bouton flottant reflètent la configuration", async ({ page }) => {
        await withConfig({
            NAVI_COLOR_ACCENT: '#ff00aa',
            NAVI_FAB_POSITION: 'left',
        }, async () => {
            await page.goto(productUrl(PRODUCT_IN_STOCK));
            const html = await page.content();
            expect(html).toContain('--navi-color-accent:#ff00aa');
            await expect(page.locator('#navi-fab')).toHaveAttribute('data-position', 'left');
        });
    });

    test('arrondi des boutons se reflète réellement sur le bouton cookies (style calculé)', async ({ page }) => {
        await withConfig({ NAVI_RADIUS_BUTTON: '20' }, async () => {
            await page.goto(productUrl(PRODUCT_IN_STOCK));
            const btn = page.locator('#navi-cookie-btn-accepter');
            if ((await btn.count()) === 0) {
                test.skip(true, 'bannière cookie déjà répondue sur cet environnement (cookie déjà posé)');
            }
            const borderRadius = await btn.evaluate((el) => getComputedStyle(el).borderRadius);
            expect(borderRadius).toBe('20px');
        });
    });
});
