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

function setFormDetailsVisiblity(show) {
    formDetails.style.display = show ? 'flex' : 'none';
    formSubmitButton.style.display = show ? 'block' : 'none';
}

function showUploadType(type) {
    if (formTabs.hasAttribute('disabled')) {
        return;
    }

    document.getElementById('form-upload-wrapper').style.display = type == 'file' ? 'flex' : 'none';
    document.getElementById('form-text-upload').style.display = type == 'text' ? 'flex' : 'none';
    document.getElementById('form-record-upload').style.display = type === 'audio' ? 'flex' : 'none';

    const tabs = document.querySelectorAll('.form-upload-tab');

    for (const tab of tabs) {
        if (tab.getAttribute('id') == `form-tab-${type}`) {
            tab.classList.remove('disabled');
        } else {
            tab.classList.add('disabled');
        }
    }
}