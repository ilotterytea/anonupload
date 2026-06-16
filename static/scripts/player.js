window.addEventListener("load", () => {
    const volume = Number(localStorage.getItem("player-volume") || "0.45");

    const playbacks = document.querySelectorAll("video, audio");
    playbacks.forEach(playback => {
        playback.volume = volume;
        playback.addEventListener("volumechange", () => {
            localStorage.setItem("player-volume", playback.volume);
        });

        const source = playback.querySelector("source");
        if (source && !playback.canPlayType(source.type)) {
            const root = document.createElement("div");
            root.classList.add("unsupported-playback", "box");

            root.innerHTML = `
            <p>This file uses the ${source.type} format which your browser cannot play.</p>
            <p>
                You can download the file and watch it in a media player or
                <b>use a different browser</b> that supports this codec.
            `;

            playback.parentElement.append(root);
            playback.style.display = 'none';
        }
    });
});