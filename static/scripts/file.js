// -- uploaded files
function getUploadedFiles() {
    const files = JSON.parse(localStorage.getItem("uploaded_files") ?? "[]");
    return files;
}

function saveUploadedFile(data) {
    const files = getUploadedFiles();
    files.unshift(data);
    localStorage.setItem("uploaded_files", JSON.stringify(files));
}

function deleteUploadedFile(id) {
    let files = getUploadedFiles();
    files = files.filter((x) => x.id !== id);
    localStorage.setItem("uploaded_files", JSON.stringify(files));
}

// -- dom functions
function createFile(file) {
    const fileName = `${file.id}.${file.extension}`;

    const root = document.createElement("div");
    root.classList.add("file", "item");

    const base = document.createElement("div");
    base.classList.add("base");
    root.append(base);

    if (file.urls && file.urls.thumbnail_url) {
        root.style.backgroundImage = `url('${file.urls.thumbnail_url}')`;
    }

    // -- creating name
    const name = document.createElement("p");
    name.textContent = fileName;
    name.classList.add("name");
    base.append(name);

    // -- details
    const details = document.createElement("ul");
    details.classList.add("details");
    base.append(details);

    // filesize
    const fileSize = document.createElement("li");
    fileSize.textContent = (file.size / 1024 / 1024).toFixed(2) + " MB";
    details.append(fileSize);

    // -- buttons
    const buttons = document.createElement("div");
    buttons.classList.add("buttons");
    base.append(buttons);

    // open button
    const openButton = document.createElement("button");
    openButton.textContent = 'open';
    openButton.addEventListener("click", () => window.open(file.urls.download_url, '_blank'));
    buttons.append(openButton);

    // copy button
    if (navigator.clipboard) {
        const copyButton = document.createElement("button");
        copyButton.innerHTML = '<img src="/static/img/icons/paste_plain.png" alt="copy" title="copy" />';
        copyButton.addEventListener("click", () => navigator.clipboard.writeText(file.urls.download_url));
        buttons.append(copyButton);
    }

    // delete button
    if (file.urls.deletion_url) {
        const deletionButton = document.createElement("button");
        deletionButton.innerHTML = '<img src="/static/img/icons/cross.png" alt="delete" title="delete this file" />';
        deletionButton.addEventListener("click", () => {
            fetch(file.urls.deletion_url, {
                method: "DELETE",
                headers: {
                    "Accept": "application/json"
                }
            })
                .then((r) => r.json())
                .then((j) => {
                    if (j.status_code === 200 || j.status_code == 404) {
                        base.remove();
                        deleteUploadedFile(file.id);
                    } else {
                        alert(`${j.message} (${j.status_code})`);
                    }
                })
                .catch((err) => {
                    alert("Failed to delete this file. Check the console.");
                    console.error(err);
                });
        });
        buttons.append(deletionButton);
    }

    // favorite button
    if (file.is_favorite) {
        const favoriteButton = document.createElement("button");
        favoriteButton.innerHTML = '<img src="/static/img/icons/star.png" alt="unfavorite" title="unfavorite this file" />';
        favoriteButton.addEventListener("click", () => {
            removeFavoriteFile(file);
            base.remove();
        });
        buttons.append(favoriteButton);
    }

    return root;
}