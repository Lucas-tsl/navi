const { test, expect } = require('@playwright/test');
const { productUrl, acceptCookies } = require('../fixtures');

const PRODUCT_WITH_STORY = process.env.NAVI_TEST_PRODUCT_WITH_STORY || '';

test.describe('Stories', () => {
    test.skip(!PRODUCT_WITH_STORY, 'NAVI_TEST_PRODUCT_WITH_STORY non défini — configurer un produit avec au moins une story pour activer cette spec');

    test('bulle cliquable ouvre le panneau desktop', async ({ page }) => {
        await page.goto(productUrl(PRODUCT_WITH_STORY));
        await acceptCookies(page);

        const bubble = page.locator('.navi-story-bubble[data-video-id]').first();
        if ((await bubble.count()) === 0) {
            test.skip(true, 'aucune bulle trouvée — vérifier NAVI_TEST_PRODUCT_WITH_STORY');
        }

        await bubble.click();
        await expect(page.locator('#navi-fab')).toHaveAttribute('data-detail', 'stories');
        await expect(page.locator('#navi-story-iframe')).toHaveAttribute('src', /youtube-nocookie\.com\/embed\//);
    });

    test('la croix ferme entièrement le bouton flottant', async ({ page }) => {
        await page.goto(productUrl(PRODUCT_WITH_STORY));
        await acceptCookies(page);

        const bubble = page.locator('.navi-story-bubble[data-video-id]').first();
        if ((await bubble.count()) === 0) {
            test.skip(true, 'aucune bulle trouvée — vérifier NAVI_TEST_PRODUCT_WITH_STORY');
        }

        await bubble.click();
        await page.click('.navi-story-close');
        await expect(page.locator('#navi-fab')).toHaveAttribute('data-state', 'closed');
    });
});
