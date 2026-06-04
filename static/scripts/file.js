// -- uploaded files
function getUploadedFiles() {
    return JSON.parse(localStorage.getItem("uploaded_files") ?? "[]");
}

function saveUploadedFiles(list) {
    localStorage.setItem("uploaded_files", JSON.stringify(list));
}

function saveUploadedFile(data) {
    const files = getUploadedFiles();
    files.unshift(data);
    saveUploadedFiles(files);
}

function deleteUploadedFile(id, element = null) {
    let files = getUploadedFiles();

    const file = files.find((x) => x.id === id);
    if (!file || !file.urls || !file.urls.deletion_url) {
        console.error(`Failed to delete file ID ${id}: file or deletion URL not found`);
        return;
    }

    const alertStatus = (text, attr) => {
        if (element !== null && typeof element.setStatus === 'function') {
            element.setStatus(text, attr);
        } else {
            alert(text);
        }
    };

    fetch(file.urls.deletion_url, {
        method: "DELETE",
        headers: {
            "Accept": "application/json"
        }
    })
        .then((r) => {
            if (r.status !== 200 && r.status !== 404 && r.headers.get("Content-Type") !== "application/json") {
                return Promise.reject(`Failed to remove file ID ${id}: ${r.status} ${r.statusText}`);
            }
            return r.json();
        })
        .then((j) => {
            if (j.status_code === 200 || j.status_code == 404) {
                alertStatus('deleted', 'deleted');
                saveUploadedFiles(files.filter((x) => x.id !== id));
            } else {
                alertStatus(j.message, 'error');
            }
        })
        .catch((err) => {
            alertStatus('Failed to delete this file. Check the console.', 'error');
            console.error(err);
        });
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
        const img = new Image();

        img.addEventListener("load", () => root.style.backgroundImage = `url('${file.urls.thumbnail_url}')`);
        img.addEventListener("error", () => root.style.backgroundImage = "url('/static/img/default-thumbnail.webp')");

        img.src = file.urls.thumbnail_url;
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
        copyButton.innerHTML = '<img src="/static/img/icons/copy.png" alt="copy" title="copy" />';
        copyButton.addEventListener("click", () => navigator.clipboard.writeText(file.urls.download_url));
        buttons.append(copyButton);
    }

    // delete button
    if (file.urls.deletion_url) {
        console.log(file);
        const deletionButton = document.createElement("button");
        deletionButton.innerHTML = '<img src="/static/img/icons/cross.png" alt="delete" title="delete this file" />';
        deletionButton.addEventListener("click", () => {
            deleteUploadedFile(file.id, root);
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

    root.setStatus = (text, attr) => {
        if (attr === 'deleted') {
            root.remove();
        } else {
            alert(text);
        }
    };

    return root;
}

function createFileUploadProgress(file) {
    const fileElement = document.createElement("tr");
    fileElement.setAttribute("status", "progress");

    const fileIconColumn = document.createElement("td");
    fileElement.append(fileIconColumn);

    const fileIconElement = document.createElement("img");
    fileIconColumn.append(fileIconElement);

    // setting icon according to file type (not thumbnail)
    if (file.type.startsWith("image/")) {
        fileIconElement.src = '/static/img/icons/file_image.png';
        fileIconElement.alt = '[I]';
        fileIconElement.title = 'image file';
    } else if (file.type.startsWith("video/")) {
        fileIconElement.src = '/static/img/icons/file_video.png';
        fileIconElement.alt = '[V]';
        fileIconElement.title = 'video file';
    } else if (file.type.startsWith("audio/")) {
        fileIconElement.src = '/static/img/icons/file_audio.png';
        fileIconElement.alt = '[A]';
        fileIconElement.title = 'audio file';
    } else if (file.type.startsWith("text/")) {
        fileIconElement.src = '/static/img/icons/file_text.png';
        fileIconElement.alt = '[T]';
        fileIconElement.title = 'text file';
    } else if (file.type === "application/x-shockwave-flash") {
        fileIconElement.src = '/static/img/icons/file_flash.png';
        fileIconElement.alt = '[S]';
        fileIconElement.title = 'flash file';
    } else {
        fileIconElement.src = '/static/img/icons/file.png';
        fileIconElement.alt = '[F]';
        fileIconElement.title = 'file';
    }

    const fileInfo = document.createElement("td");
    fileElement.append(fileInfo);

    const fileNameElement = document.createElement("p");
    fileNameElement.classList.add("file-name");
    if (file.name.length > 16) {
        fileNameElement.textContent = file.name.substring(0, 13) + '...';
    } else {
        fileNameElement.textContent = file.name;
    }
    fileInfo.append(fileNameElement);

    // -- file actions
    const fileActions = document.createElement("td");
    fileElement.append(fileActions);

    const fileStatusElement = document.createElement("p");
    fileActions.append(fileStatusElement);

    const fileProgressElement = document.createElement("progress");
    fileProgressElement.max = 100;
    fileActions.append(fileProgressElement);

    // file buttons
    const fileButtons = document.createElement("div");
    fileButtons.classList.add("details");
    fileActions.append(fileButtons);

    const fileOpenButton = document.createElement("button");
    fileOpenButton.textContent = 'open';
    fileOpenButton.style.display = 'none';

    const fileCopyButton = document.createElement("button");
    fileCopyButton.innerHTML = '<img src="/static/img/icons/copy.png" alt="[C]" title="copy URL" />';
    fileCopyButton.style.display = 'none';

    const fileDeleteButton = document.createElement("button");
    fileDeleteButton.innerHTML = '<img src="/static/img/icons/cross.png" alt="[X]" title="delete this file" />';
    fileDeleteButton.style.display = 'none';

    fileButtons.append(fileOpenButton, fileCopyButton, fileDeleteButton);

    let f = {
        root: fileElement,
        thumbnail: fileIconElement,
        name: fileNameElement,
        status: fileStatusElement,
        progress: fileProgressElement,
        buttons: {
            root: fileButtons,
            open: fileOpenButton,
            copy: fileCopyButton,
            delete: fileDeleteButton
        }
    };

    f.setStatus = (text, attr = null) => {
        fileStatusElement.style.display = text === null ? 'none' : 'flex';
        fileStatusElement.textContent = text;
        if (attr !== null) {
            fileElement.setAttribute("status", attr);

            if (attr === "deleted" || attr === "abort" || attr === "error") {
                f.buttons.root.style.display = 'none';
            }
        }
    };

    f.setProgress = (v) => {
        console.log(v);
        fileProgressElement.style.display = v < 100 ? 'flex' : 'none';
        fileProgressElement.value = v;
    };

    return f;
}

function uploadData(data, form = null) {
    const file = createFileUploadProgress(data);
    file.setStatus('waiting...', 'queue');
    file.setProgress(100);

    const abortButton = document.createElement("button");
    file.buttons.root.append(abortButton);

    // file upload
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '/upload');
    xhr.setRequestHeader("Accept", "application/json");

    xhr.upload.addEventListener("progress", (e) => {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            console.log(percent);
            file.setProgress(percent);
            file.setStatus(percent < 100 ? `uploading... (${percent}%)` : 'finishing...', 'in_progress');
        } else {
            file.setStatus('uploading...', 'in_progress');
        }
    });

    xhr.addEventListener("load", () => {
        abortButton.remove();

        file.setProgress(100);
        if (xhr.status !== 201 && xhr.getResponseHeader("Content-Type") !== 'application/json') {
            file.setStatus(`${xhr.status} ${xhr.statusText}`, 'error');
            return;
        }

        const j = JSON.parse(xhr.responseText);
        const d = j.data;
        if (j.status_code !== 201) {
            file.setStatus(j.message ?? d.error ?? `received ${j.status_code} code`, 'error');
            return;
        }

        file.name.textContent = `${d.id}.${d.extension}`;
        file.setStatus(null, 'success');

        if (d.urls) {
            if (d.urls.download_url) {
                file.buttons.open.style.display = 'inline';
                file.buttons.open.addEventListener("click", () => window.open(d.urls.download_url, '_blank'));
                if (navigator.clipboard) {
                    file.buttons.copy.style.display = 'inline';
                    file.buttons.copy.addEventListener("click", () => navigator.clipboard.writeText(d.urls.download_url));
                }
            }

            if (d.urls.deletion_url) {
                file.buttons.delete.style.display = 'inline';
                file.buttons.delete.addEventListener('click', () => {
                    file.buttons.delete.style.display = 'none';
                    file.setStatus('deleting...', 'delete_wait');
                    deleteUploadedFile(d.id, file);
                });
            }
        }

        saveUploadedFile(d);
    });

    xhr.addEventListener("error", () => {
        file.status.setStatus('upload error', 'error');
    });

    xhr.send(form ?? data);

    // abort button
    abortButton.addEventListener("click", () => {
        xhr.abort();
        file.setStatus('aborted', 'abort');
    });

    const abortButtonImg = document.createElement('img');
    abortButtonImg.alt = 'abort';
    abortButtonImg.title = 'abort file';
    abortButtonImg.src = '/static/img/icons/cross.png';
    abortButton.appendChild(abortButtonImg);

    return file;
}