window.addEventListener("load", () => {
    const playback = document.getElementById("video-playback");
    if (playback === null) {
        return;
    }

    const volume = Number(localStorage.getItem("player-volume") || "0.45");
    playback.volume = volume;
    console.log(volume);

    playback.addEventListener("volumechange", () => {
        localStorage.setItem("player-volume", playback.volume);
    });
});