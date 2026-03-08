function saveFavoriteFiles(files) {
    localStorage.setItem('favorite-files', JSON.stringify(files));
}

function getFavoriteFiles() {
    const files = JSON.parse(localStorage.getItem('favorite-files') ?? '[]');
    return files;
}

function addFavoriteFile(f) {
    if (isFavoriteFile(f)) {
        return;
    }

    const files = getFavoriteFiles();
    files.unshift(f);
    saveFavoriteFiles(files);
}

function removeFavoriteFile(f) {
    if (!isFavoriteFile(f)) {
        return;
    }

    let files = getFavoriteFiles();
    files = files.filter((x) => x.id != f.id);
    saveFavoriteFiles(files);
}

function isFavoriteFile(f) {
    const files = getFavoriteFiles();
    return files.find((x) => x.id == f.id) != undefined;
}

function createFavoriteButton(root, data) {
    const id = `${data.id}.${data.extension}`;

    const addIcon = '<img src="/static/img/icons/star-gray.png" alt="Favorite"/>';
    const delIcon = '<img src="/static/img/icons/star.png" alt="Unfavorite"/>';

    const btn = document.createElement('button');
    btn.classList.add("favorite-button");
    btn.setAttribute("file-id", id);

    btn.addEventListener('click', (e) => {
        if (isFavoriteFile(data)) {
            removeFavoriteFile(data);
        } else {
            addFavoriteFile(data);
        }

        const isf = isFavoriteFile(data);

        const btns = document.querySelectorAll(`button.favorite-button[file-id='${id}']`);
        btns.forEach((x) => {
            x.innerHTML = isf ? delIcon : addIcon;
            x.title = isf ? 'Unfavorite this file' : 'Favorite file';
        });
    });

    const isf = isFavoriteFile(data);

    btn.innerHTML = isf ? delIcon : addIcon;
    btn.title = isf ? 'Unfavorite this file' : 'Favorite file';

    root.appendChild(btn);
}

window.addEventListener('load', () => {
    const tabs = document.getElementById('file-tab-buttons');
    if (tabs != null) {
        const file = {
            id: document.getElementById('file-id').innerText,
            mime: document.getElementById('file-mime').innerText,
            extension: document.getElementById('file-extension').innerText,
            size: document.getElementById('file-size').innerText
        };

        createFavoriteButton(tabs, file);
    }

    const files = document.getElementById('favorite-files');
    if (files != null) {
        const data = getFavoriteFiles();
        if (data.length > 0) {
            files.parentElement.style.display = 'flex';
            files.innerHTML = '';
            enableTab('favorite-files');
        } else {
            disableTab('favorite-files');
        }
        data.forEach((x) => {
            const item = createUploadedFileItem(x);
            files.appendChild(item.base);
        });
    }
});