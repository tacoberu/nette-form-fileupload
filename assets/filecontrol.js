/**
 * Auto-preload for MultiFileControl.
 *
 * Hides the preload button and clicks it automatically whenever files are
 * selected in the file input, so the user never has to press it manually.
 *
 * @param container - div[data-taco-type="file multiple"]
 */
function initMultiFileAutoPreload(container) {
    const fileInput = container.querySelector('input[type="file"]');
    const preloadButton = container.querySelector('input[name$="[preload]"]');
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
function initFileHideOnNew(container) {
    const fileInput = container.querySelector('input[type="file"]');
    if (!fileInput) {
        return;
    }
    fileInput.addEventListener('change', () => {
        if (fileInput.files && fileInput.files.length > 0) {
            const remove = container.querySelector('input[name$="[remove]"]');
            const label = container.querySelector('input.taco-filecontrol-label');
            if (remove)
                remove.style.display = 'none';
            if (label)
                label.style.display = 'none';
        }
    });
}
export { initMultiFileAutoPreload, initFileHideOnNew };
