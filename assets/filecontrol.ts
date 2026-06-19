/**
 * Auto-preload for MultiFileControl (no-JS fallback path).
 *
 * Hides the preload button and clicks it automatically whenever files are
 * selected in the file input, so the user never has to press it manually.
 *
 * @param container - div[data-taco-type="file multiple"]
 */
function initMultiFileAutoPreload(container: HTMLElement): void {
	const fileInput = container.querySelector<HTMLInputElement>('input[type="file"]');
	const preloadButton = container.querySelector<HTMLInputElement>('input[name$="[preload]"]');

	if (!fileInput || !preloadButton) {
		return;
	}

	preloadButton.style.display = 'none';

	fileInput.addEventListener('change', () => {
		if (fileInput.files && fileInput.files.length > 0) {
			preloadButton.click();
		}
	});
}


export { initMultiFileAutoPreload };
