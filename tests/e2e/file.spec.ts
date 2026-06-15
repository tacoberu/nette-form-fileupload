import { test, expect } from '@playwright/test';
import * as path from 'path';

const CAT = path.resolve(__dirname, '../../examples/document_root/img/the-cat.jpeg');
const TINY = path.resolve(__dirname, 'fixtures/tiny.txt');
const JPG = path.resolve(__dirname, 'sample-data/cat.jpg');
const CSS = path.resolve(__dirname, 'sample-data/image-field.css');

const p2current = 'input[name="portrait2[current]"]';
const p2remove = 'input[name="portrait2[remove]"]';
const p5current = 'input[name="portrait5[current]"]';
const p5remove = 'input[name="portrait5[remove]"]';
const p6file = 'input[name="portrait6[new]"]';
const save = 'input[name="_submit"]';

test.describe('FileControl (/dashboard/file)', () => {

	test('page loads with the default portrait shown', async ({ page }) => {
		await page.goto('/dashboard/file');
		// Only portrait2 has a FileCurrent default; portrait5 default is intentionally absent.
		await expect(page.locator(p2current)).toHaveCount(1);
		await expect(page.locator(p5current)).toHaveCount(0);
	});


	test('the previewer renders an image, otherwise just the filename', async ({ page }) => {
		await page.goto('/dashboard/file');
		// Upload to portrait5 (GenericFilePreviewer + store from fileUploadFactory).
		// Upload CAT to portrait6 so client-side "required" passes but server-side MaxFileSize fails,
		// causing a server re-render that preserves portrait5's FileUploaded in its store.
		await page.setInputFiles('input[name="portrait5[new]"]', JPG);
		await page.setInputFiles(p6file, CAT);
		await page.click(save);
		// portrait5 now has FileUploaded -> GenericFilePreviewer renders a data:image thumbnail.
		await expect(page.locator(`.taco-filecontrol:has(${p5current}) img[src^="data:image"]`)).toHaveCount(1);
		// portrait2 has FileCurrent but no previewer -> filename only, no image.
		await expect(page.locator(`.taco-filecontrol:has(${p2current}) img[src^="data:image"]`)).toHaveCount(0);
	});


	test('remove (✕) drops the file and does NOT run onSuccess', async ({ page }) => {
		await page.goto('/dashboard/file');
		await expect(page.locator(p2current)).toHaveCount(1);

		await page.click(p2remove);

		await expect(page.locator(p2current)).toHaveCount(0);
		// onSuccess/onSubmit were suppressed -> no dump of submitted values.
		await expect(page.locator('.tracy-dump')).toHaveCount(0);
	});


	test('remove works on a previewed control too', async ({ page }) => {
		await page.goto('/dashboard/file');
		// Get portrait5 into FileUploaded state: upload + fail server-side on portrait6 MaxFileSize.
		await page.setInputFiles('input[name="portrait5[new]"]', JPG);
		await page.setInputFiles(p6file, CAT);
		await page.click(save);
		// portrait5 now shows FileUploaded with remove button.
		await expect(page.locator(p5current)).toHaveCount(1);
		await page.click(p5remove);
		await expect(page.locator(p5current)).toHaveCount(0);
	});


	test('a required file blocks submit', async ({ page }) => {
		await page.goto('/dashboard/file');
		// portrait6 is required and empty - netteForms shows the error in a <dialog>.
		await page.click(save);
		await expect(page.locator('dialog[open]')).toContainText('This field is required.');
		// No navigation -> nothing was submitted.
		await expect(page.locator('.tracy-dump')).toHaveCount(0);
	});


	test('a too big file blocks submit', async ({ page }) => {
		await page.goto('/dashboard/file');
		await page.setInputFiles(p6file, CAT); // > 255 B (MaxFileSize)
		await page.click(save);
		// MaxFileSize is validated server-side (custom validator) -> error text in page HTML, not dialog.
		await expect(page.locator('body')).toContainText('255');
		await expect(page.locator('.tracy-dump')).toHaveCount(0);
	});


	test('a conditionally required file blocks submit when its checkbox is on', async ({ page }) => {
		await page.goto('/dashboard/file');
		await page.setInputFiles(p6file, TINY); // satisfy the unconditional required
		await page.check('input[name="aux"]'); // makes portrait7 required
		await page.click(save);
		await expect(page.locator('dialog[open]')).toContainText('Vyžadován');
	});


	test('submitting valid data runs onSuccess', async ({ page }) => {
		await page.goto('/dashboard/file');
		await page.setInputFiles(p6file, TINY); // satisfies required + max size
		await page.click(save);
		// Navigation happened and onSuccess dumped the values.
		expect(await page.locator('.tracy-dump').count()).toBeGreaterThan(0);
	});


	test('portraits 1–5 with JPEG, portraits 6–7 with CSS, full submit succeeds', async ({ page }) => {
		await page.goto('/dashboard/file');

		// Portraits 1–5: JPEG image (no size restriction on these fields).
		// Portrait 2 starts with a FileCurrent default; uploading to [new] replaces it.
		await page.setInputFiles('input[name="portrait1[new]"]', JPG);
		await page.setInputFiles('input[name="portrait2[new]"]', JPG);
		await page.setInputFiles('input[name="portrait3[new]"]', JPG);
		await page.setInputFiles('input[name="portrait4[new]"]', JPG);
		await page.setInputFiles('input[name="portrait5[new]"]', JPG);

		// Portraits 6–7: small CSS file (33 B, fits MaxFileSize = 255 B on portrait6).
		await page.setInputFiles('input[name="portrait6[new]"]', CSS);
		await page.setInputFiles('input[name="portrait7[new]"]', CSS);

		await page.click(save);

		// onSuccess was called — Tracy dumps the submitted values.
		expect(await page.locator('.tracy-dump').count()).toBeGreaterThan(0);
	});

});
