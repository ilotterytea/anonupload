function createFile(file) {
    const fileName = `${file.id}.${file.extension}`;

    const base = document.createElement("div");
    base.classList.add("item");

    // -- creating thumbnail
    const thumbnail = document.createElement("img");
    thumbnail.alt = `[thumbnail of ${fileName}]`;
    thumbnail.title = thumbnail.alt;

    {
        const wrapper = document.createElement("div");
        wrapper.classList.add("thumbnail");
        wrapper.append(thumbnail);
        base.append(wrapper);
    }

    if (file.urls && file.urls.thumbnail_url) {
        thumbnail.src = file.urls.thumbnail_url;
    } else {
        thumbnail.src = `/-/${file.id}.webp`;
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
    openButton.addEventListener("click", () => window.location.href = file.urls.download_url);
    buttons.append(openButton);

    // copy button
    if (navigator.clipboard) {
        const copyButton = document.createElement("button");
        copyButton.innerHTML = '<img src="/static/img/icons/paste_plain.png" alt="copy" title="copy" />';
        copyButton.addEventListener("click", () => navigator.clipboard.writeText(file.urls.download_url));
        buttons.append(copyButton);
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

    return base;
}