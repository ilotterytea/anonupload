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