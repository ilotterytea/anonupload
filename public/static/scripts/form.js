document.onpaste = () => {
    var items = (event.clipboardData || event.originalEvent.clipboardData).items;

    for (index in items) {
        var item = items[index];
        if (item.kind === 'file') {
            file = item.getAsFile();
            showFile(file);
        }
    }
};

function showFile(file) {
    setFormDetailsVisiblity(file != null);
    
    if (file == null) {
        fileUploadWrapper.innerHTML = '<h1>Click, drop, or paste files here</h1>';
        
        if (fileURLWrapper) {
            fileURLWrapper.style.display = 'flex';
        }
    } else {
        fileUploadWrapper.innerHTML = `<h1>File: ${file.name}</h1>`;
        
        if (fileURLWrapper) {
            fileURLWrapper.style.display = 'none';
        }
    }
}