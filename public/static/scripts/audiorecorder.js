// creating an audio button
formTabs.innerHTML += `
<div class="form-upload-tab tab disabled" id="form-tab-audio">
    <button onclick="showUploadType('audio')" class="transparent">
        <p>Record</p>
    </button>
</div>
`;

function generateAudioRecorderHTML() {
    return `
    <div class="column gap-8">
        <button type="button" class="big-upload-button" onclick="startRecording()" id="record-start-btn">
            <h1>Click here to start audio recording</h1>
        </button>
        <button type="button" class="big-upload-button" onclick="stopRecording()" id="record-stop-btn" style="display: none">
            <h1>Recording...</h1>
            <p>Click here to stop audio recording</p>
        </button>
        <div class="row align-center justify-center">
            <div class="box row align-center gap-8 pad-8" id="record-player" style="display:none">
                <button type="button" onclick="removeRecording()">
                    <img src="/static/img/icons/cross.png" alt="Delete">
                </button>
                <button type="button" onclick="startRecording()">
                    <img src="/static/img/icons/repeat.png" alt="Retry">
                </button>
                <button type="button" onclick="playRecord()" id="record-play-btn">
                    <img src="/static/img/icons/play.png" alt="Play">
                </button>
                <button type="button" onclick="pauseRecord()" id="record-pause-btn" style="display: none;">
                    <img src="/static/img/icons/pause.png" alt="Pause">
                </button>
                <div class="column gap-8">
                    <input type="range" min="0" max="100" value="0" step="0.01" class="audio-slider" id="record-slider" oninput="rewindRecord()">
                    <div class="row justify-between">
                        <p id="record-currentsecond"></p>
                        <p id="record-duration"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
`;
}

const form = document.getElementById('form-record-upload');
form.innerHTML = generateAudioRecorderHTML();

const startBtn = document.getElementById('record-start-btn');
const stopBtn = document.getElementById('record-stop-btn');
const playBtn = document.getElementById('record-play-btn');
const pauseBtn = document.getElementById('record-pause-btn');

const player = document.getElementById('record-player');
const slider = document.getElementById('record-slider');
const currentSecond = document.getElementById('record-currentsecond');
const duration = document.getElementById('record-duration');

let playback = null;

// record functions
let mediaRecorder;
let stream;
let audioChunks = [];
let audioLength = 0;

async function startRecording() {
    // TODO: very poor sound quality
    stream = await navigator.mediaDevices.getUserMedia({ audio: { sampleRate: 44100, channelCount: 1 }});
    const options = { mimeType: 'audio/ogg; codecs=opus', audioBitsPerSecond: 128000 };
    if (!MediaRecorder.isTypeSupported(options.mimeType)) {
        alert("Your browser doesn't support audio/ogg recording.");
        return;
    }
    
    audioLength = 0;
      
    mediaRecorder = new MediaRecorder(stream, options);
    audioChunks = [];

    mediaRecorder.ondataavailable = event => {
        if (event.data.size > 0) {
            audioChunks.push(event.data);
            audioLength++;
        }
    };

    mediaRecorder.start();
    startBtn.style.display = 'none';
    stopBtn.style.display = 'block';
    player.style.display = 'none';
    
    setFormDetailsVisiblity(false);
}

function stopRecording() {
    mediaRecorder.stop();
    startBtn.style.display = 'block';
    stopBtn.style.display = 'none';
    
    if (playback) {
        form.removeChild(playback);
        playback = null;
    }
    
    mediaRecorder.onstop = () => {
        const blob = new Blob(audioChunks, { type: 'audio/ogg; codecs=opus' });
        const file = new File([blob], 'recording.ogg', { type: 'audio/ogg; codecs=opus' });
        
        const url = URL.createObjectURL(file);
        
        playback = document.createElement('audio');
        playback.src = url;
        
        playback.addEventListener('loadedmetadata', () => {
           const d = playback.duration;
           slider.max = d;
           slider.value = 0;
           currentSecond.textContent = formatTimestamp(slider.getAttribute('value'));
           duration.textContent = formatTimestamp(d);
        });
        
        playback.addEventListener('timeupdate', () => {
            currentSecond.textContent = formatTimestamp(playback.currentTime);
            slider.value = playback.currentTime;
        });
        
        playback.addEventListener('ended', () => {
            playBtn.style.display = 'flex';
            pauseBtn.style.display = 'none';
            currentSecond.textContent = formatTimestamp(0);
            slider.value = 0;
        });
        
        form.appendChild(playback);
        
        playBtn.style.display = 'flex';
        pauseBtn.style.display = 'none';
        
        startBtn.style.display = 'none';
        stopBtn.style.display = 'none';
        player.style.display = 'flex';
        
        setFormDetailsVisiblity(true);
        
        stream.getAudioTracks().forEach(track => {
            track.stop();
        });
        
        stream = null;
        
        // attaching the file
        if (formFile) {
            const dt = new DataTransfer();
            dt.items.add(file);
            formFile.files = dt.files;
        }
        
        formTabs.setAttribute('disabled', 'true');
    };
}

function removeRecording() {
    startBtn.style.display = 'block';
    player.style.display = 'none';
    form.removeChild(playback);
    formFile.value = '';
    setFormDetailsVisiblity(false);
    
    formTabs.removeAttribute('disabled');
}

function playRecord() {
    if (playback) playback.play();
    
    playBtn.style.display = 'none';
    pauseBtn.style.display = 'flex';
}

function pauseRecord() {
    if (playback) playback.pause();
    
    playBtn.style.display = 'flex';
    pauseBtn.style.display = 'none';
}

function rewindRecord() {
    currentSecond.textContent = formatTimestamp(slider.value);
    if (playback) {
        playback.currentTime = slider.value;
        playRecord();
    }
}

function formatTimestamp(timestamp) {
    const m = Math.floor(timestamp / 60);
    const s = Math.ceil(timestamp % 60);
    
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s
}

// form
document.getElementById('form-upload').addEventListener('submit', () => {
    player.style.display = 'none';
    startBtn.style.display = 'block';
});