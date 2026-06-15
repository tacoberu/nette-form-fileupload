import { test, expect, Page } from '@playwright/test';
import * as path from 'path';

const CAT = path.resolve(__dirname, '../../examples/document_root/img/the-cat.jpeg');
const TINY = path.resolve(__dirname, 'fixtures/tiny.txt');

// attachments1 is intentionally absent in the presenter; all upload tests use attachments2.
// attachments2 starts with 1 FileCurrent default, so all counts are offset by +1.
const a2 = {
	current: 'input[name="attachments2[current][]"]',
	use: 'input[name="attachments2[use][]"]',
	file: 'input[name="attachments2[new][]"]',
	preload: 'input[name="attachments2[preload]"]',
	container: '.taco-filecontrol-multiple:has(input[name="attachments2[new][]"])',
};

const uncheck = (el: Element) => { (el as HTMLInputElement).checked = false; };

// JS hides the preload button; trigger it via DOM click() to bypass visibility.
const clickPreload = (page: Page) => Promise.all([
	page.waitForNavigation({ waitUntil: 'load' }),
	page.locator(a2.preload).evaluate(btn => (btn as HTMLInputElement).click()),
]);

// attachments2 always has a data-upload-url, so file selection triggers an AJAX
// upload (fetch) rather than a page navigation. The row is appended asynchronously;
// callers must assert with toHaveCount(), which retries until it lands.
const uploadFiles = (page: Page, files: string | string[]) => page.setInputFiles(a2.file, files);

test.describe('MultiFileControl (/dashboard/files)', () => {

	test('page loads with the default attachment shown', async ({ page }) => {
		await page.goto('/dashboard/files');
		// attachments2 has 1 FileCurrent default; no other controls start with files.
		await expect(page.locator(a2.current)).toHaveCount(1);
	});


	test('preload (↻) shows the uploaded file and does NOT run onSuccess', async ({ page }) => {
		await page.goto('/dashboard/files');
		// a2 starts with 1 default -> after preloading 1 more: 2 total.
		await uploadFiles(page, CAT);

		await expect(page.locator(a2.current)).toHaveCount(2);
		await expect(page.locator(a2.use)).toHaveCount(2);
		// The newly added file matches the uploaded filename.
		await expect(page.locator(a2.current).last()).toHaveValue(/the-cat\.jpeg/);
		// onSuccess/onSubmit were suppressed -> no dump of submitted values.
		await expect(page.locator('.tracy-dump')).toHaveCount(0);
	});


	test('without a previewer, only a text label is shown (no image thumbnail)', async ({ page }) => {
		await page.goto('/dashboard/files');
		await uploadFiles(page, CAT);
		await expect(page.locator(a2.current)).toHaveCount(2);

		// attachments2 has no GenericFilePreviewer -> no data:image thumbnail, just a readonly text input.
		await expect(page.locator(`${a2.container} img[src^="data:image"]`)).toHaveCount(0);
	});


	test('uploading multiple files at once shows them all', async ({ page }) => {
		await page.goto('/dashboard/files');
		// a2 starts with 1 default -> uploading 2 at once -> 3 total.
		await uploadFiles(page, [CAT, TINY]);

		await expect(page.locator(a2.current)).toHaveCount(3);
	});


	test('preloading again accumulates files', async ({ page }) => {
		await page.goto('/dashboard/files');
		// a2 starts with 1 default.
		await uploadFiles(page, CAT);
		await expect(page.locator(a2.current)).toHaveCount(2);

		await uploadFiles(page, TINY);
		await expect(page.locator(a2.current)).toHaveCount(3);
	});


	test('re-submitting (Save) keeps the preloaded files', async ({ page }) => {
		await page.goto('/dashboard/files');
		// a2 starts with 1 default -> preload 1 more -> 2 total.
		await uploadFiles(page, CAT);
		await expect(page.locator(a2.current)).toHaveCount(2);

		await page.fill('input[name="title"]', 'Hello');
		await page.fill('textarea[name="content"]', 'World');
		await page.click('input[name="_submit"]');

		// Regression: the "use" checkbox must carry name/value, otherwise the file is lost here.
		// loadHttpData() re-applies submitted values after setDefaults(), so both files remain.
		await expect(page.locator(a2.current)).toHaveCount(2);
		// Contrast with preload/remove: a real Save DOES run onSubmit (dumps the values).
		expect(await page.locator('.tracy-dump').count()).toBeGreaterThan(0);
	});


	test('unchecking a file removes it', async ({ page }) => {
		await page.goto('/dashboard/files');
		// a2 starts with 1 default -> preload 1 more -> 2 total.
		await uploadFiles(page, CAT);
		await expect(page.locator(a2.current)).toHaveCount(2);

		// The checkbox is visually hidden (custom styling), so toggle it directly.
		// Uncheck only the newly added file (last use checkbox).
		await page.locator(a2.use).last().evaluate(uncheck);
		await clickPreload(page);

		await expect(page.locator(a2.current)).toHaveCount(1);
	});


	test('the default attachment can be removed', async ({ page }) => {
		await page.goto('/dashboard/files');
		await expect(page.locator(a2.current)).toHaveCount(1);

		await page.locator(a2.use).evaluate(uncheck);
		await clickPreload(page);

		await expect(page.locator(a2.current)).toHaveCount(0);
	});

});
