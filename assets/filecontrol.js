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
export { initMultiFileAutoPreload };
