import { test, expect } from '@playwright/test';
import * as path from 'path';

const CAT = path.resolve(__dirname, '../../examples/document_root/img/the-cat.jpeg');
const TINY = path.resolve(__dirname, 'fixtures/tiny.txt');

// Same selectors as multifile.spec.ts — attachments2 starts with 1 FileCurrent default.
const a2 = {
	current: 'input[name="attachments2[current][]"]',
	use: 'input[name="attachments2[use][]"]',
	file: 'input[name="attachments2[new][]"]',
	preload: 'input[name="attachments2[preload]"]',
};

test.describe('MultiFileControl auto-preload (JS)', () => {

	test('preload button is hidden', async ({ page }) => {
		await page.goto('/dashboard/files');
		await expect(page.locator(a2.preload)).toBeHidden();
	});


	test('selecting files triggers preload automatically', async ({ page }) => {
		await page.goto('/dashboard/files');
		// a2 starts with 1 default; after auto-preload of 1 more file: 2 total.
		// No manual page.click(preload) — the JS fires it on change.
		await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.setInputFiles(a2.file, CAT)]);
		await expect(page.locator(a2.current)).toHaveCount(2);
	});


	test('selecting multiple files at once auto-preloads all of them', async ({ page }) => {
		await page.goto('/dashboard/files');
		await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.setInputFiles(a2.file, [CAT, TINY])]);
		await expect(page.locator(a2.current)).toHaveCount(3);
	});


	test('repeated selections accumulate', async ({ page }) => {
		await page.goto('/dashboard/files');

		// setInputFiles does not await navigation caused by the auto-preload click.
		// page.waitForNavigation() must start before setInputFiles so it doesn't miss the event.
		await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.setInputFiles(a2.file, CAT)]);
		await expect(page.locator(a2.current)).toHaveCount(2);

		await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.setInputFiles(a2.file, TINY)]);
		await expect(page.locator(a2.current)).toHaveCount(3);
	});

});
