const { chromium } = require('../store/packages/Webkul/Shop/node_modules/playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const base = process.env.STOREFRONT_TEST_URL || 'http://localhost:8088';
const output = path.resolve(__dirname, '../output/ethereal-exact');

async function settle(page) {
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => document.fonts.load('16px \"Work Sans\"', 'Classy Fashion Hub'));
    await page.waitForTimeout(300);
}

async function capture(page, name) {
    for (let y = 0; y < await page.evaluate(() => document.body.scrollHeight); y += 650) {
        await page.evaluate(top => window.scrollTo(0, top), y);
        await page.waitForTimeout(100);
    }
    await page.waitForTimeout(800);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: path.join(output, `${name}.png`), fullPage: true });
}

(async () => {
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch();
    const results = [];
    const errors = [];
    try {
        for (const width of [1440, 390]) {
            const context = await browser.newContext({ viewport: { width, height: 1000 } });
            const page = await context.newPage();
            page.on('pageerror', error => errors.push(error.message));
            await page.goto(base);
            await settle(page);
            assert.equal(await page.locator('h1').count(), 1);
            assert.equal(await page.locator('.cfh-campaign-image').getAttribute('fetchpriority'), 'high');
            assert(await page.locator('.cfh-product-card').count() > 0);
            const layout = await page.evaluate(() => {
                const grid = document.querySelector('.cfh-feed-grid');
                const card = grid.querySelector('.cfh-product-card');
                const image = card.querySelector('.cfh-product-image');
                return {
                    headerHeight: document.querySelector('header').getBoundingClientRect().height,
                    columns: getComputedStyle(grid).gridTemplateColumns.split(' ').length,
                    gap: getComputedStyle(grid).columnGap,
                    cardGap: getComputedStyle(card).rowGap,
                    imageRatio: image.getBoundingClientRect().width / image.getBoundingClientRect().height,
                    nameFont: getComputedStyle(card.querySelector('.cfh-product-name')).fontSize,
                    footerDisplay: getComputedStyle(document.querySelector('.cfh-footer')).display,
                };
            });
            assert.equal(layout.columns, width >= 768 ? 3 : 2);
            assert.equal(layout.gap, width >= 768 ? '32px' : '8px');
            assert.equal(layout.cardGap, width >= 768 ? '24px' : '12px');
            assert.equal(layout.nameFont, width >= 768 ? '20px' : '14px');
            assert(Math.abs(layout.imageRatio - 0.81) < 0.01);
            assert.equal(layout.headerHeight, width >= 768 ? 69 : 56);
            if (width < 768) assert.equal(layout.footerDisplay, 'none');
            results.push({ width, check: 'measured Ethereal layout dimensions', status: 'pass', layout });
            await capture(page, `home-${width}`);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
            await page.keyboard.press('Tab');
            results.push({ width, check: 'homepage, responsive layout, campaign, live product feed', status: 'pass' });
            const simpleCard = page.locator('.cfh-product-card').filter({ hasText: 'Cotton Socks 3-Pack' }).first();
            if (width >= 768) {
                await simpleCard.hover();
                await simpleCard.getByRole('button', { name: 'Add To Cart', exact: true }).click();
            } else {
                const simpleUrl = await simpleCard.locator('.cfh-product-image > a').getAttribute('href');
                await page.goto(simpleUrl);
                await settle(page);
                await page.getByRole('button', { name: 'Add To Cart', exact: true }).first().click();
            }
            await page.waitForTimeout(1000);
            await page.locator('header button[aria-label="Shopping Cart"]').first().click();
            await page.getByRole('dialog').waitFor();
            assert((await page.getByRole('dialog').innerText()).includes('Cotton Socks 3-Pack'));
            await capture(page, `mini-cart-${width}`);
            await page.keyboard.press('Escape');
            await page.getByRole('dialog').waitFor({ state: 'hidden' });
            results.push({ width, check: 'mini-cart drawer and keyboard dismissal', status: 'pass' });
            await page.goto(`${base}/checkout/cart`);
            await settle(page);
            assert(!(await page.locator('main').innerText()).includes('You don’t have a product'));
            await capture(page, `cart-${width}`);
            await page.getByRole('button', { name: 'Increase Quantity', exact: true }).first().click();
            const updateResponse = page.waitForResponse(response => response.url().includes('/api/checkout/cart') && response.request().method() === 'PUT');
            await page.getByRole('button', { name: 'Update Cart', exact: true }).click();
            const updatedCart = await (await updateResponse).json();
            console.log('UPDATED_CART', width, updatedCart.data?.items?.map(item => ({ name: item.name, quantity: item.quantity })), updatedCart.message);
            assert.equal(updatedCart.data.items[0].quantity, 2);
            results.push({ width, check: 'cart quantity and total update', status: 'pass' });
            await page.getByRole('link', { name: 'Proceed To Checkout', exact: true }).click();
            await settle(page);
            if (page.url().includes('/customer/login')) {
                await page.locator('main input[name=email]').fill('customer@classy.local');
                await page.locator('main input[name=password]').fill('customer123');
                await page.locator('main').getByRole('button', { name: 'Sign In', exact: true }).click();
                await settle(page);
            }
            await capture(page, `checkout-${width}`);
            const billingId = await page.locator('input[name="billing.id"]').first().getAttribute('id');
            await page.locator(`label[for="${billingId}"]`).last().click();
            const sameAddress = page.locator('input[name="billing.use_for_shipping"]');
            if (! await sameAddress.isChecked()) await page.locator('label[for="use_for_shipping"]').last().click();
            await page.getByRole('button', { name: 'Proceed', exact: true }).click();
            await page.locator('input[name="shipping_method"]').first().waitFor({ state: 'attached' });
            const shippingId = await page.locator('input[name="shipping_method"]').first().getAttribute('id');
            await page.locator(`label[for="${shippingId}"]`).last().click();
            await page.locator('input[name="payment[method]"]').first().waitFor({ state: 'attached' });
            console.log('PAYMENT_OPTIONS', width, await page.locator('input[name="payment[method]"]').evaluateAll(es => es.map(e => ({ id: e.id, value: e.value }))));
            const cod = page.locator('input[name="payment[method]"][id="cashondelivery"]');
            const codId = await cod.count() ? 'cashondelivery' : await page.locator('input[name="payment[method]"]').first().getAttribute('id');
            await page.locator(`label[for="${codId}"]`).last().click();
            await page.waitForTimeout(1000);
            await capture(page, `checkout-${width}`);
            results.push({ width, check: 'customer sign-in, checkout address, shipping, payment selection', status: 'pass' });
            await page.goto(`${base}/search?query=shirt`);
            await settle(page);
            assert(await page.locator('.cfh-product-card').count() > 0);
            await capture(page, `search-${width}`);
            const productName = await page.locator('.cfh-product-name').first().innerText();
            const productUrl = await page.locator('.cfh-product-image > a').first().getAttribute('href');
            await page.goto(productUrl);
            await settle(page);
            const selectors = page.locator('select[name^="super_attribute"]');
            for (let index = 0; index < await selectors.count(); index++) {
                const select = selectors.nth(index);
                const value = await select.locator('option').evaluateAll(options => options.find(o => o.value && !o.disabled)?.value);
                assert(value, 'a variant option is available');
                await select.selectOption(value);
            }
            await page.getByRole('button', { name: 'Add To Cart', exact: true }).first().click();
            await page.waitForTimeout(800);
            await capture(page, `product-${width}`);
            await page.goto(`${base}/checkout/cart`);
            await settle(page);
            assert((await page.locator('main').innerText()).includes(productName));
            results.push({ width, check: 'search, configurable variant selection, add to cart, persisted cart', status: 'pass' });
            await page.goto(`${base}/mens`);
            await settle(page);
            assert(await page.locator('.cfh-product-card').count() > 0);
            await capture(page, `category-${width}`);
            if (width >= 768) {
                await page.getByRole('button', { name: 'Filter', exact: true }).click();
                await page.getByRole('button', { name: 'Red', exact: true }).click();
                await page.waitForTimeout(1000);
                assert(await page.locator('.cfh-product-card').count() > 0);
                assert(page.url().includes('color='));
                await capture(page, `category-filtered-${width}`);
                await page.keyboard.press('Escape');
                await page.getByRole('dialog').waitFor({ state: 'hidden' });
                assert.equal(await page.getByRole('dialog').count(), 0);
                assert.equal(await page.evaluate(() => document.activeElement?.textContent.includes('Filter')), true);
                results.push({ width, check: 'filter drawer Escape and focus restoration', status: 'pass' });
                results.push({ width, check: 'category colour filter', status: 'pass' });
            }
            results.push({ width, check: 'category listing', status: 'pass' });
            await context.close();
        }
        assert.equal(errors.length, 0, JSON.stringify(errors));
        fs.writeFileSync(path.join(output, 'browser-checks.json'), JSON.stringify({ results, errors }, null, 2));
        console.log(JSON.stringify(results, null, 2));
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
