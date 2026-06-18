/**
 * Auto-preload for MultiFileControl.
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

/**
 * For FileControl (single file): when a new file is selected, hide the remove
 * button and the current-value input so they don't conflict with the new upload.
 *
 * @param container - div.taco-filecontrol-single
 */
function initFileHideOnNew(container: HTMLElement): void {
	const fileInput = container.querySelector<HTMLInputElement>('input[type="file"]');
	if (!fileInput) {
		return;
	}

	fileInput.addEventListener('change', () => {
		if (fileInput.files && fileInput.files.length > 0) {
			const remove = container.querySelector<HTMLElement>('input[name$="[remove]"]');
			const label = container.querySelector<HTMLElement>('input.taco-filecontrol-label');
			if (remove) remove.style.display = 'none';
			if (label) label.style.display = 'none';
		}
	});
}

/**
 * Inserts a clear (×) button right after the file input.
 * The button is hidden until the user selects a file; clicking it resets the input.
 *
 * @param fileInput - input[type="file"] element
 */
function initFileClearButton(fileInput: HTMLInputElement): void {
	const button = document.createElement('button');
	button.type = 'button';
	button.textContent = '✕';
	button.className = 'taco-filecontrol-remove';
	button.style.display = 'none';
	fileInput.after(button);

	fileInput.addEventListener('change', () => {
		button.style.display = fileInput.files && fileInput.files.length > 0 ? '' : 'none';
	});

	button.addEventListener('click', () => {
		fileInput.value = '';
		button.style.display = 'none';
		fileInput.dispatchEvent(new Event('change'));
	});
}

export { initMultiFileAutoPreload, initFileHideOnNew, initFileClearButton };
