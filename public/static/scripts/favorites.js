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
    files.push(f);
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

window.addEventListener('load', () => {
    const tabs = document.getElementById('file-tab-buttons');
    if (tabs != null) {
        const addIcon = document.createElement('img');
        addIcon.src = '/static/img/icons/star-gray.png';
        addIcon.alt = 'Favorite';

        const delIcon = document.createElement('img');
        delIcon.src = '/static/img/icons/star.png';
        delIcon.alt = 'Unfavorite';

        const btn = document.createElement('button');

        const file = {
            id: document.getElementById('file-id').innerText,
            mime: document.getElementById('file-mime').innerText,
            extension: document.getElementById('file-extension').innerText,
            size: document.getElementById('file-size').innerText
        };

        btn.addEventListener('click', (e) => {
            if (isFavoriteFile(file)) {
                removeFavoriteFile(file);
            } else {
                addFavoriteFile(file);
            }

            btn.innerHTML = '';

            const isf = isFavoriteFile(file);

            btn.appendChild(isf ? delIcon : addIcon);
            btn.title = isf ? 'Unfavorite this file' : 'Favorite file';
        });

        const isf = isFavoriteFile(file);

        btn.appendChild(isf ? delIcon : addIcon);
        btn.title = isf ? 'Unfavorite this file' : 'Favorite file';

        tabs.appendChild(btn);
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
            const html = addUploadedFile(x);
            files.innerHTML += html;
        });
    }
});