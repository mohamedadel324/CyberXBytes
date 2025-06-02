// Fix for FilePond initialization issues
document.addEventListener('DOMContentLoaded', function() {
    // Add the custom CSS file to the head
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '/css/fix-filepond.css';
    document.head.appendChild(link);

    // Re-initialize FilePond components if needed
    setTimeout(function() {
        const fileInputs = document.querySelectorAll('.fi-fo-file-upload input[type="file"]');
        if (fileInputs.length > 0) {
            console.log('Found file inputs:', fileInputs.length);
        }
    }, 1000);
}); 