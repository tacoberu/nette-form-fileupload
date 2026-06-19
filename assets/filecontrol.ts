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


/**
 * AJAX upload for MultiFileControl.
 *
 * When data-chunk-size is set, large files are split into chunks so each POST stays
 * within PHP's upload_max_filesize. Chunks are assembled server-side. A progress bar
 * is shown during the upload. Small files are POSTed as a single request.
 *
 * @param container - div[data-taco-type="file multiple"][data-upload-url]
 */
function initMultiFileAjaxUpload(container: HTMLElement): void {
	const fileInput = container.querySelector<HTMLInputElement>('input[type="file"]');
	const preloadButton = container.querySelector<HTMLInputElement>('input[name$="[preload]"]');
	const uploadUrl = container.dataset.uploadUrl;
	const chunkSize = parseInt(container.dataset.chunkSize ?? '0', 10);

	if (!fileInput || !uploadUrl) {
		return;
	}

	if (preloadButton) {
		preloadButton.style.display = 'none';
	}

	fileInput.addEventListener('change', () => {
		const files = Array.from(fileInput.files ?? []);
		if (!files.length) {
			return;
		}

		(async () => {
			for (const file of files) {
				if (chunkSize > 0 && file.size > chunkSize) {
					await uploadFileInChunks(file, uploadUrl, container, chunkSize);
				}
				else {
					await uploadFileTo(file, uploadUrl, container);
				}
			}
			fileInput.value = '';
		})();
	});
}


/**
 * POST a single (small) file to the upload URL, then insert a new file row.
 */
async function uploadFileTo(file: File, url: string, container: HTMLElement): Promise<void> {
	const transactionInput = container.querySelector<HTMLInputElement>('input[name$="[transaction]"]');

	const data = new FormData();
	data.append('file', file);
	if (transactionInput?.value) {
		data.append('transaction', transactionInput.value);
	}

	try {
		const response = await fetch(url, { method: 'POST', body: data });
		if (!response.ok) {
			return;
		}
		const json = await response.json() as {
			file?: string;
			transaction?: number;
			name?: string;
			error?: string;
			preview?: string;
		};
		if (json.error) {
			appendErrorRow(container, json.error);
			return;
		}
		if (!json.file || json.transaction === undefined || !json.name) {
			return;
		}
		appendFileRow(container, json.file, json.name, json.transaction, json.preview ?? '');
	}
	catch {
		// network error — file will not appear in the list
	}
}


/**
 * Split a large file into chunks and POST them sequentially.
 * Shows a progress bar; each chunk response carries {progress: 0..1}.
 * The final chunk response carries the full file info.
 */
async function uploadFileInChunks(file: File, url: string, container: HTMLElement, chunkSize: number): Promise<void> {
	const transactionInput = container.querySelector<HTMLInputElement>('input[name$="[transaction]"]');
	const chunkId = generateId();
	const chunkTotal = Math.ceil(file.size / chunkSize);
	const progressRow = appendProgressRow(container, file.name);

	try {
		for (let i = 0; i < chunkTotal; i++) {
			const chunk = file.slice(i * chunkSize, (i + 1) * chunkSize);

			const data = new FormData();
			data.append('file', chunk, file.name);
			data.append('chunkIndex', String(i));
			data.append('chunkTotal', String(chunkTotal));
			data.append('chunkId', chunkId);
			data.append('chunkName', file.name);
			data.append('chunkType', file.type || 'application/octet-stream');
			if (transactionInput?.value) {
				data.append('transaction', transactionInput.value);
			}

			const response = await fetch(url, { method: 'POST', body: data });
			if (!response.ok) {
				progressRow.remove();
				appendErrorRow(container, 'Upload failed');
				return;
			}

			const json = await response.json() as {
				progress?: number;
				file?: string;
				transaction?: number;
				name?: string;
				error?: string;
				preview?: string;
			};

			if (json.error) {
				progressRow.remove();
				appendErrorRow(container, json.error);
				return;
			}

			const progressEl = progressRow.querySelector('progress');
			if (progressEl) {
				progressEl.value = (i + 1) / chunkTotal;
			}

			if (json.file && json.transaction !== undefined && json.name) {
				progressRow.remove();
				appendFileRow(container, json.file, json.name, json.transaction, json.preview ?? '');
				return;
			}
		}
	}
	catch {
		progressRow.remove();
		appendErrorRow(container, 'Network error during upload');
	}
}


/** Insert a progress bar row before the upload row; returns the row element. */
function appendProgressRow(container: HTMLElement, name: string): HTMLElement {
	const uploadRow = container.querySelector<HTMLElement>('.taco-filecontrol-upload');
	const row = document.createElement('div');
	row.className = 'taco-filecontrol-row taco-filecontrol-uploading';

	const label = document.createElement('span');
	label.textContent = name;

	const bar = document.createElement('progress');
	bar.value = 0;
	bar.max = 1;

	row.appendChild(label);
	row.appendChild(bar);
	container.insertBefore(row, uploadRow ?? null);
	return row;
}


/** Compact unique ID for a chunked upload session. */
function generateId(): string {
	return Date.now().toString(36) + Math.random().toString(36).slice(2);
}


/**
 * Insert a pre-checked file row (checkbox + hidden current input + preview/label) before the
 * upload row. Also updates the [transaction] hidden input so the form knows which temp
 * directory to look in on submit.
 */
function appendFileRow(container: HTMLElement, serialized: string, name: string, transaction: number, preview: string = ''): void {
	const transactionInput = container.querySelector<HTMLInputElement>('input[name$="[transaction]"]');
	if (transactionInput) {
		transactionInput.value = String(transaction);
	}

	const uploadRow = container.querySelector<HTMLElement>('.taco-filecontrol-upload');
	if (!uploadRow) {
		return;
	}

	const controlName = transactionInput ? extractControlName(transactionInput.name) : '';
	if (!controlName) {
		return;
	}

	const row = document.createElement('div');
	row.className = 'taco-filecontrol-row';

	const label = document.createElement('label');
	const checkbox = document.createElement('input');
	checkbox.type = 'checkbox';
	checkbox.checked = true;
	checkbox.setAttribute('formnovalidate', '');
	checkbox.name = `${controlName}[use][]`;
	checkbox.value = serialized;
	label.appendChild(checkbox);
	row.appendChild(label);

	const currentInput = document.createElement('input');
	currentInput.readOnly = true;
	currentInput.style.display = 'none';
	currentInput.name = `${controlName}[current][]`;
	currentInput.value = serialized;
	row.appendChild(currentInput);

	if (preview) {
		const previewEl = document.createElement('span');
		previewEl.innerHTML = preview;
		row.appendChild(previewEl);
	}
	else {
		const labelInput = document.createElement('input');
		labelInput.readOnly = true;
		labelInput.value = name;
		row.appendChild(labelInput);
	}

	container.insertBefore(row, uploadRow);
}


/**
 * Insert an error row before the upload row.
 */
function appendErrorRow(container: HTMLElement, message: string): void {
	const uploadRow = container.querySelector<HTMLElement>('.taco-filecontrol-upload');
	const row = document.createElement('div');
	row.className = 'taco-filecontrol-row taco-filecontrol-error';
	row.textContent = message;
	container.insertBefore(row, uploadRow ?? null);
}


/** 'attachments[transaction]' → 'attachments' */
function extractControlName(inputName: string): string {
	return inputName.replace(/\[.*$/, '');
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
 * AJAX upload for FileControl (single file).
 *
 * Uploads the selected file immediately (chunked if large). Shows a progress bar.
 * On success injects current/label/remove elements; clears the file input.
 *
 * @param container - div.taco-filecontrol-single[data-upload-url]
 */
function initFileAjaxUpload(container: HTMLElement): void {
	const fileInput = container.querySelector<HTMLInputElement>('input[type="file"]');
	const uploadUrl = container.dataset.uploadUrl;
	const chunkSize = parseInt(container.dataset.chunkSize ?? '0', 10);

	if (!fileInput || !uploadUrl) {
		return;
	}

	fileInput.addEventListener('change', () => {
		const file = fileInput.files?.[0];
		if (!file) {
			return;
		}

		(async () => {
			clearSingleFileRow(container);
			if (chunkSize > 0 && file.size > chunkSize) {
				await uploadSingleFileInChunks(file, uploadUrl, container, chunkSize);
			}
			else {
				await uploadSingleFileTo(file, uploadUrl, container);
			}
			fileInput.value = '';
			fileInput.dispatchEvent(new Event('change'));
		})();
	});
}


async function uploadSingleFileTo(file: File, url: string, container: HTMLElement): Promise<void> {
	const transactionInput = container.querySelector<HTMLInputElement>('input[name$="[transaction]"]');

	const data = new FormData();
	data.append('file', file);
	if (transactionInput?.value) {
		data.append('transaction', transactionInput.value);
	}

	try {
		const response = await fetch(url, { method: 'POST', body: data });
		if (!response.ok) {
			return;
		}
		const json = await response.json() as {
			file?: string;
			transaction?: number;
			name?: string;
			error?: string;
			preview?: string;
		};
		if (json.error) {
			appendSingleErrorRow(container, json.error);
			return;
		}
		if (!json.file || json.transaction === undefined || !json.name) {
			return;
		}
		setSingleFileRow(container, json.file, json.name, json.transaction, json.preview ?? '');
	}
	catch {
		// network error — file will not appear
	}
}


async function uploadSingleFileInChunks(file: File, url: string, container: HTMLElement, chunkSize: number): Promise<void> {
	const transactionInput = container.querySelector<HTMLInputElement>('input[name$="[transaction]"]');
	const chunkId = generateId();
	const chunkTotal = Math.ceil(file.size / chunkSize);
	const progressRow = appendSingleProgressRow(container, file.name);

	try {
		for (let i = 0; i < chunkTotal; i++) {
			const chunk = file.slice(i * chunkSize, (i + 1) * chunkSize);

			const data = new FormData();
			data.append('file', chunk, file.name);
			data.append('chunkIndex', String(i));
			data.append('chunkTotal', String(chunkTotal));
			data.append('chunkId', chunkId);
			if (transactionInput?.value) {
				data.append('transaction', transactionInput.value);
			}

			const response = await fetch(url, { method: 'POST', body: data });
			if (!response.ok) {
				progressRow.remove();
				appendSingleErrorRow(container, 'Upload failed');
				return;
			}

			const json = await response.json() as {
				progress?: number;
				file?: string;
				transaction?: number;
				name?: string;
				error?: string;
				preview?: string;
			};

			if (json.error) {
				progressRow.remove();
				appendSingleErrorRow(container, json.error);
				return;
			}

			const progressEl = progressRow.querySelector('progress');
			if (progressEl) {
				progressEl.value = (i + 1) / chunkTotal;
			}

			if (json.file && json.transaction !== undefined && json.name) {
				progressRow.remove();
				setSingleFileRow(container, json.file, json.name, json.transaction, json.preview ?? '');
				return;
			}
		}
	}
	catch {
		progressRow.remove();
		appendSingleErrorRow(container, 'Network error during upload');
	}
}


/** Remove server-rendered or AJAX-injected file display elements. */
function clearSingleFileRow(container: HTMLElement): void {
	container.querySelectorAll('[data-ajax-injected]').forEach(el => el.remove());
	container.querySelector('input[name$="[current]"]')?.remove();
	container.querySelector('input.taco-filecontrol-label')?.remove();
	container.querySelector('img')?.remove();
	container.querySelector('input[type="submit"].taco-filecontrol-remove')?.remove();
	const fileInput = container.querySelector<HTMLInputElement>('input[type="file"]');
	if (fileInput) {
		fileInput.disabled = false;
	}
}


/** Inject current/preview/remove elements after a successful AJAX upload. */
function setSingleFileRow(container: HTMLElement, serialized: string, name: string, transaction: number, preview: string = ''): void {
	const transactionInput = container.querySelector<HTMLInputElement>('input[name$="[transaction]"]');
	if (transactionInput) {
		transactionInput.value = String(transaction);
	}

	const controlName = transactionInput ? extractControlName(transactionInput.name) : '';
	if (!controlName) {
		return;
	}

	const fileInput = container.querySelector<HTMLInputElement>('input[type="file"]');
	if (fileInput) {
		fileInput.disabled = true;
	}

	const currentInput = document.createElement('input');
	currentInput.type = 'hidden';
	currentInput.name = `${controlName}[current]`;
	currentInput.value = serialized;
	currentInput.dataset.ajaxInjected = '';

	const removeBtn = document.createElement('button');
	removeBtn.type = 'button';
	removeBtn.textContent = '✕';
	removeBtn.className = 'taco-filecontrol-remove';
	removeBtn.dataset.ajaxInjected = '';
	removeBtn.addEventListener('click', () => clearSingleFileRow(container));

	container.insertBefore(currentInput, fileInput ?? null);

	if (preview) {
		const previewEl = document.createElement('span');
		previewEl.innerHTML = preview;
		previewEl.dataset.ajaxInjected = '';
		container.insertBefore(previewEl, fileInput ?? null);
	}
	else {
		const label = document.createElement('input');
		label.readOnly = true;
		label.value = name;
		label.className = 'taco-filecontrol-label';
		label.dataset.ajaxInjected = '';
		container.insertBefore(label, fileInput ?? null);
	}

	container.insertBefore(removeBtn, fileInput ?? null);
}


function appendSingleProgressRow(container: HTMLElement, name: string): HTMLElement {
	const fileInput = container.querySelector<HTMLInputElement>('input[type="file"]');
	const row = document.createElement('div');
	row.className = 'taco-filecontrol-row taco-filecontrol-uploading';
	row.dataset.ajaxInjected = '';

	const label = document.createElement('span');
	label.textContent = name;

	const bar = document.createElement('progress');
	bar.value = 0;
	bar.max = 1;

	row.appendChild(label);
	row.appendChild(bar);
	container.insertBefore(row, fileInput ?? null);
	return row;
}


function appendSingleErrorRow(container: HTMLElement, message: string): void {
	const fileInput = container.querySelector<HTMLInputElement>('input[type="file"]');
	const row = document.createElement('div');
	row.className = 'taco-filecontrol-row taco-filecontrol-error';
	row.dataset.ajaxInjected = '';
	row.textContent = message;
	container.insertBefore(row, fileInput ?? null);
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


export { initMultiFileAutoPreload, initMultiFileAjaxUpload, initFileHideOnNew, initFileAjaxUpload, initFileClearButton };
