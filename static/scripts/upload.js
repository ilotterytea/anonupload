function createUploadedFileItem(data) {
    const base = document.createElement("div");
    base.classList.add("box", "item", "column", "gap-4", "pad-4");

    const previewContainer = document.createElement("div");
    previewContainer.classList.add("column", "align-center", "justify-center", "grow");
    base.appendChild(previewContainer);

    const preview = document.createElement("img");
    preview.alt = "No thumbnail.";
    preview.loading = "lazy";
    previewContainer.appendChild(preview);

    const header = document.createElement("h2");
    base.appendChild(header);

    const description = document.createElement("div");
    base.appendChild(description);

    const buttons = document.createElement("div");
    buttons.classList.add("row", "gap-8");
    base.appendChild(buttons);

    if (data) {
        if (data.mime.startsWith("audio/")) {
            preview.src = "/static/img/icons/file_audio.png";
        } else if (data.mime.startsWith("text/")) {
            preview.src = "/static/img/icons/file_text.png";
        } else if (data.mime == "application/x-shockwave-flash") {
            preview.src = "/static/img/icons/file_flash.png";
        } else if (!data.mime.startsWith("image/") && !data.mime.startsWith("video/")) {
            preview.src = "/static/img/icons/file.png";
        } else {
            preview.src = `${thumbnailPathPrefix}/${data.id}.webp`;
        }

        header.textContent = `${data.id}.${data.extension}`;

        const mime = document.createElement("p");
        description.appendChild(mime);
        mime.textContent = data.mime;

        const size = document.createElement("p");
        description.appendChild(size);
        size.textContent = (data.size / 1024 / 1024).toFixed(2) + " MB";

        if (data.id && data.extension) {
            const url = `${window.location.href}${data.id}.${data.extension}`;
            const link = document.createElement("a");
            link.href = url;
            const btn = document.createElement("button");
            btn.textContent = "Open";
            link.appendChild(btn);
            buttons.appendChild(link);

            const copyBtn = document.createElement("button");
            copyBtn.addEventListener("click", () => {
                navigator.clipboard.writeText(url);
            });
            const copyImg = document.createElement("img");
            copyImg.src = "/static/img/icons/paste_plain.png";
            copyBtn.appendChild(copyImg);
            buttons.appendChild(copyBtn);
        }

        if (data.urls && data.urls.deletion_url) {
            const btn = document.createElement("button");
            btn.addEventListener("click", () => {
                deleteUploadedFile(data.urls.deletion_url, data.id);
                base.remove();
                if (getUploadedFiles().length == 0) {
                    document.querySelector('#uploaded-files').parentElement.style.display = 'none';
                }
            });

            const img = document.createElement("img");
            img.src = "/static/img/icons/cross.png";
            btn.appendChild(img);

            buttons.appendChild(btn);
        }
    }

    return {
        "base": base,
        "preview": preview,
        "header": header,
        "description": description,
        "buttons": buttons
    };
}

function getUploadedFiles() {
    const files = JSON.parse(localStorage.getItem("uploaded_files") ?? "[]");
    return files;
}

function saveUploadedFile(data) {
    const files = getUploadedFiles();
    files.unshift(data);
    localStorage.setItem("uploaded_files", JSON.stringify(files));
}

function displayUploadedFile(data) {
    const items = document.getElementById("uploaded-files");
    if (items) {
        items.prepend(createUploadedFileItem(data).base);
    }
}

function displayUploadedFiles() {
    const files = getUploadedFiles();
    for (const file of files) {
        displayUploadedFile(file);
    }
}

function deleteUploadedFile(url, id) {
    if (confirm("Do you want to delete file locally?")) {
        let files = getUploadedFiles();
        files = files.filter((x) => x.id !== id);
        localStorage.setItem("uploaded_files", JSON.stringify(files));
    }

    if (url && confirm(`Are you sure you want to delete file ID ${id} from the servers?`)) {
        fetch(url, {
            'headers': {
                'Accept': 'application/json'
            },
            'method': 'DELETE'
        }).then((r) => r.json())
            .then((json) => {
                if (json.status_code != 200) {
                    alert(`${json.message} (${json.status_code})`);
                }
            })
            .catch((err) => {
                alert('Failed to delete the file. Look into the console!');
                console.error(err);
            });
    }
}

function uploadData(data) {
    const status = document.createElement("p");

    const bar = document.createElement("progress");
    bar.max = 100;

    const item = createUploadedFileItem(null);
    item.description.appendChild(bar);
    item.description.appendChild(status);

    // setting item name
    if (data.get("file") != null) {
        let name = data.get("file").name;
        if (name.length > 10) {
            name = name.substring(0, 7) + '...';
        }
        item.header.textContent = name;
        item.header.style.fontStyle = "italic";
    }

    const xhr = new XMLHttpRequest();
    xhr.open('POST', '/upload.php');
    xhr.setRequestHeader("Accept", "application/json");

    xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            bar.value = percent;
            status.textContent = `Uploading file: ${percent}%`;
        } else {
            status.textContent = "Uploading...";
        }
    };

    xhr.onload = () => {
        const j = JSON.parse(xhr.responseText);

        if (xhr.status == 201) {
            status.textContent = "Uploaded!";
            bar.value = 100;

            const d = j.data;
            item.base.remove();
            saveUploadedFile(d);
            displayUploadedFile(d);
        } else {
            status.textContent = `Upload failed: ${j.message} (${xhr.status})`;
            item.buttons.remove();
        }
    };

    xhr.onerror = () => {
        status.textContent = "Upload error";
        item.buttons.remove();
    };

    xhr.send(data);

    const abortButton = document.createElement("button");
    abortButton.addEventListener("click", () => {
        xhr.abort();
        item.base.remove();
        alert("File upload has been aborted.");
    });
    item.buttons.appendChild(abortButton);
    const abortButtonImg = document.createElement("img");
    abortButtonImg.alt = "Abort";
    abortButtonImg.src = "/static/img/icons/cross.png";
    abortButton.appendChild(abortButtonImg);

    const items = document.getElementById("uploaded-files");
    if (items) {
        items.prepend(item.base);
    }
}

window.addEventListener("load", () => {
    const itemsElement = document.getElementById("uploaded-files");
    if (!itemsElement) return;

    const files = getUploadedFiles();
    files.forEach((x) => {
        const item = createUploadedFileItem(x);
        itemsElement.appendChild(item.base);
    });
});