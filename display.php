<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
$djVoiceDefault = getSetting('dj_voice', 'openai');
$platform = PLATFORM;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Now Playing - LiveDJ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            background: #0d0d0d;
            color: #fff;
            font-family: 'Inter', -apple-system, sans-serif;
        }
        .layout {
            display: flex;
            height: 100vh;
            width: 100%;
        }

        /* Left Panel */
        .left-panel {
            position: relative;
            width: 55%;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: flex-start;
            padding: 60px;
            padding-bottom: 260px;
            overflow: hidden;
        }
        .left-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center top;
            z-index: 0;
        }
        .left-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                180deg,
                rgba(13,13,13,0.6) 0%,
                rgba(13,13,13,0.4) 30%,
                rgba(13,13,13,0.4) 70%,
                rgba(13,13,13,0.8) 100%
            );
            z-index: 1;
        }
        .left-content {
            position: relative;
            z-index: 2;
            text-align: left;
            padding-left: 20px;
        }
        .artist-name {
            font-family: 'Inter', sans-serif;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.45);
            margin-bottom: 16px;
            margin-left: 4px;
        }
        .track-name {
            font-family: 'EB Garamond', serif;
            font-size: clamp(64px, 11vw, 130px);
            font-weight: 400;
            font-style: normal;
            line-height: 0.9;
            text-shadow: 0 4px 30px rgba(0,0,0,0.5);
            margin-left: -4px;
        }

        /* Album + Vinyl */
        .album-vinyl-wrap {
            position: fixed;
            bottom: 40px;
            left: 55%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            z-index: 50;
        }
        .album-cover {
            width: 180px;
            height: 180px;
            object-fit: cover;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            z-index: 2;
            position: relative;
        }
        .vinyl {
            width: 170px;
            height: 170px;
            margin-left: -60px;
            background:
                repeating-radial-gradient(circle at 50% 50%,
                    #111 0px, #111 1px,
                    #1a1a1a 1px, #1a1a1a 2px,
                    #222 2px, #222 3px,
                    #1a1a1a 3px, #1a1a1a 4px
                ),
                radial-gradient(circle at 50% 50%,
                    transparent 0%, transparent 28%,
                    #151515 28%, #1a1a1a 100%);
            border-radius: 50%;
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
            z-index: 1;
            position: relative;
            animation: spin 8s linear infinite;
        }
        .vinyl-label {
            position: absolute;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            animation: spin-label 8s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes spin-label {
            from { transform: translate(-50%, -50%) rotate(0deg); }
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }
        .vinyl-hole {
            position: absolute;
            width: 8px;
            height: 8px;
            background: #0d0d0d;
            border-radius: 50%;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            border: 1px solid #333;
        }

        /* Right Panel */
        .right-panel {
            position: relative;
            width: 45%;
            height: 100%;
            background: #f9f8f6;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px;
            padding-bottom: 160px;
            cursor: grab;
            user-select: none;
            touch-action: pan-y pinch-zoom;
        }
        .right-panel:active { cursor: grabbing; }

        /* Slider */
        .slider-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
        }
        .slides {
            position: relative;
            min-height: 220px;
        }
        .slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.4s ease;
            pointer-events: none;
        }
        .slide.active {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        .slide-label {
            font-family: 'Inter', sans-serif;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #aaa;
            margin-bottom: 16px;
        }
        .slide-text {
            font-family: 'EB Garamond', serif;
            font-size: 22px;
            line-height: 1.75;
            color: #222;
        }

        /* Controls */
        .slide-controls {
            position: absolute;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .slide-dots {
            display: flex;
            gap: 8px;
        }
        .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #ddd;
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .dot:hover { background: #bbb; }
        .dot.active {
            background: #222;
            width: 20px;
            border-radius: 3px;
        }
        .play-pause {
            width: 28px;
            height: 28px;
            border: none;
            background: none;
            cursor: pointer;
            opacity: 0.4;
            transition: opacity 0.2s;
            padding: 0;
        }
        .play-pause:hover { opacity: 0.7; }
        .play-pause svg { width: 100%; height: 100%; }

        /* Waiting */
        .waiting {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            width: 100%;
            font-family: 'EB Garamond', serif;
            font-size: 28px;
            color: rgba(255,255,255,0.25);
        }

        /* TTS Mute Button */
        .tts-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100;
            width: 44px;
            height: 44px;
            border: none;
            background: rgba(0,0,0,0.3);
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            backdrop-filter: blur(10px);
        }
        .tts-toggle:hover {
            background: rgba(0,0,0,0.5);
            transform: scale(1.05);
        }
        .tts-toggle svg {
            width: 22px;
            height: 22px;
            fill: rgba(255,255,255,0.7);
        }
        .tts-toggle.muted svg {
            fill: rgba(255,255,255,0.3);
        }

        /* Progress bar */
        .progress-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: rgba(0,0,0,0.2);
            z-index: 200;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #1DB954, #1ed760);
            width: 0%;
            transition: width 1s linear;
        }

        /* Source badge */
        .source-badge {
            position: fixed;
            bottom: 16px;
            right: 20px;
            z-index: 100;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
            background: rgba(0,0,0,0.2);
            padding: 6px 12px;
            border-radius: 4px;
        }

        /* Tablet / Tesla */
        @media (max-width: 1400px) {
            .track-name {
                font-size: clamp(40px, 7vw, 80px);
            }
            .left-panel {
                padding: 40px;
                padding-bottom: 260px;
            }
            .right-panel {
                padding: 40px;
                padding-bottom: 160px;
            }
        }

        /* Stack layout */
        @media (max-width: 900px) {
            .layout {
                flex-direction: column;
                height: auto;
            }
            .left-panel {
                width: 100%;
                min-height: 50vh;
                height: auto;
                padding: 40px 30px;
                padding-bottom: 40px;
                justify-content: center;
                align-items: center;
            }
            .left-content {
                padding-left: 0;
                text-align: center;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            .artist-name { margin-left: 0; font-size: 13px; }
            .track-name {
                font-size: clamp(24px, 5vw, 36px);
                margin-left: 0;
                margin-bottom: 20px;
                text-align: center;
            }
            .album-vinyl-wrap {
                position: static;
                transform: none;
                margin: 20px 0 0;
                justify-content: center;
            }
            .album-cover { width: 120px; height: 120px; }
            .vinyl { width: 110px; height: 110px; margin-left: -45px; }
            .vinyl-label { width: 40px; height: 40px; }
            .right-panel {
                width: 100%;
                min-height: 45vh;
                height: auto;
                padding: 40px 30px;
            }
            .slide-text { font-size: 19px; }
        }

        /* Mobile */
        @media (max-width: 700px) {
            html, body {
                height: auto;
                overflow-y: auto;
            }
            .layout {
                flex-direction: column;
                height: auto;
                min-height: 50vh;
            }
            .left-panel {
                width: 100%;
                min-height: 50vh;
                height: auto;
                padding: 60px 30px;
                justify-content: center;
                align-items: center;
                text-align: center;
            }
            .left-bg {
                background-position: center center;
            }
            .left-content {
                padding-left: 0;
                text-align: center;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            .artist-name { margin-left: 0; font-size: 12px; }
            .track-name {
                font-size: clamp(24px, 7vw, 40px);
                margin-left: 0;
                margin-bottom: 16px;
            }
            .album-vinyl-wrap {
                position: static;
                transform: none;
                margin: 20px 0 0;
                justify-content: center;
            }
            .album-cover { width: 100px; height: 100px; }
            .vinyl { width: 90px; height: 90px; margin-left: -35px; }
            .vinyl-label { width: 32px; height: 32px; }
            .right-panel {
                width: 100%;
                min-height: 50vh;
                height: auto;
                padding: 50px 30px;
                flex-direction: column;
                align-items: center;
            }
            .slider-container {
                max-width: 100%;
                text-align: left;
                display: flex;
                flex-direction: column;
            }
            .slides {
                position: relative;
                min-height: auto;
            }
            .slide {
                position: relative;
                opacity: 1;
                transform: none;
                display: none;
            }
            .slide.active { display: block; }
            .slide-text { font-size: 20px; line-height: 1.7; }
            .slide-controls {
                position: relative;
                bottom: auto;
                left: auto;
                transform: none;
                margin-top: 30px;
                justify-content: center;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- TTS Toggle Button -->
    <button class="tts-toggle" id="ttsToggle" onclick="toggleTTS()" title="Toggle DJ voice">
        <svg viewBox="0 0 24 24" id="ttsIcon">
            <path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h4c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z"/><circle cx="12" cy="22" r="2"/><path d="M12 20v-3"/>
        </svg>
    </button>

    <div id="content">
        <div class="waiting">Waiting for playback...</div>
    </div>

    <!-- Progress Bar -->
    <div class="progress-bar">
        <div class="progress-fill" id="progressFill"></div>
    </div>

    <script>
        const platform = '<?php echo $platform; ?>';
        let currentSlide = 0;
        let slideCount = 0;
        let autoSlideTimer = null;
        let lastTrack = '';
        let isPlaying = true;

        // Progress bar
        let progressMs = 0;
        let durationMs = 0;
        let lastProgressUpdate = Date.now();
        let progressInterval = null;

        function updateProgressBar() {
            if (durationMs <= 0) return;
            const fill = document.getElementById('progressFill');
            if (!fill) return;

            // Calculate current progress with interpolation
            const elapsed = Date.now() - lastProgressUpdate;
            const currentProgress = Math.min(progressMs + elapsed, durationMs);
            const percent = (currentProgress / durationMs) * 100;
            fill.style.width = percent + '%';
        }

        function startProgressAnimation() {
            if (progressInterval) clearInterval(progressInterval);
            progressInterval = setInterval(updateProgressBar, 100);
        }

        // TTS (Text-to-Speech) DJ Voice
        // Modes: 'off', 'free', 'openai'
        const adminDefault = '<?php echo $djVoiceDefault; ?>';
        let ttsMode = localStorage.getItem('ttsMode') || adminDefault;
        let ttsVoice = null;
        let ttsReady = false;

        // Initialize TTS
        function initTTS() {
            if ('speechSynthesis' in window) {
                const loadVoices = () => {
                    const voices = speechSynthesis.getVoices();
                    console.log('Available voices:', voices.map(v => v.name).join(', '));

                    // Prefer soft, warm voices
                    ttsVoice = voices.find(v => v.name === 'Karen') ||
                               voices.find(v => v.name === 'Moira') ||
                               voices.find(v => v.name === 'Tessa') ||
                               voices.find(v => v.name.includes('Google UK English Female')) ||
                               voices.find(v => v.name === 'Samantha') ||
                               voices.find(v => v.name === 'Daniel') ||
                               voices.find(v => v.lang.startsWith('en')) ||
                               voices[0];

                    if (ttsVoice) console.log('Selected voice:', ttsVoice.name);
                    ttsReady = voices.length > 0;
                };
                loadVoices();
                speechSynthesis.onvoiceschanged = loadVoices;
            }
            updateTTSButton();
        }

        function toggleTTS() {
            // Unlock speech synthesis on iOS
            if ('speechSynthesis' in window) {
                const unlock = new SpeechSynthesisUtterance('');
                speechSynthesis.speak(unlock);
            }

            // Simple on/off toggle - dashboard controls which provider
            if (ttsMode === 'off') {
                ttsMode = adminDefault; // Use dashboard setting
                setTimeout(() => { if (ttsReady) speak("DJ mode on"); }, 100);
            } else {
                ttsMode = 'off';
                setTimeout(() => { if (ttsReady) speak("DJ mode off"); }, 100);
            }
            localStorage.setItem('ttsMode', ttsMode);
            updateTTSButton();
            console.log('TTS mode:', ttsMode);
        }

        function updateTTSButton() {
            const btn = document.getElementById('ttsToggle');
            const icon = document.getElementById('ttsIcon');
            if (!btn || !icon) return;

            btn.classList.remove('muted');

            if (ttsMode === 'off') {
                btn.classList.add('muted');
                btn.title = 'DJ voice OFF - click to enable';
                // Headset with X
                icon.innerHTML = '<path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h3c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z"/><path d="M14.59 14.59L12 17.17l-2.59-2.58L8 16l2.59 2.59L8 21.17 9.41 22.59 12 20l2.59 2.59L16 21.17l-2.59-2.58L16 16z" opacity="0.5"/>';
            } else {
                btn.title = 'DJ voice ON - click to mute';
                // Headset with mic
                icon.innerHTML = '<path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h4c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z"/><circle cx="12" cy="22" r="2"/><path d="M12 20v-3"/>';
            }
        }

        function speak(text) {
            if (!ttsReady || !('speechSynthesis' in window)) return;

            speechSynthesis.cancel();

            const utterance = new SpeechSynthesisUtterance(text);
            if (ttsVoice) utterance.voice = ttsVoice;
            utterance.rate = 0.85;
            utterance.pitch = 0.9;
            utterance.volume = 0.9;

            speechSynthesis.speak(utterance);
        }

        // Audio element for generated TTS (OpenAI or Groq)
        let djAudio = null;

        function announceSong(data) {
            if (ttsMode === 'off') return;

            console.log('announceSong called, mode:', ttsMode);

            // Both 'openai' and 'groq' use generated audio files
            if ((ttsMode === 'openai' || ttsMode === 'groq') && data.tts_audio_url) {
                if (djAudio) {
                    djAudio.pause();
                    djAudio = null;
                }
                console.log('Playing generated audio:', data.tts_audio_url);
                djAudio = new Audio(data.tts_audio_url);
                djAudio.volume = 1.0;
                djAudio.play().then(() => {
                    console.log('Audio playing!');
                }).catch(e => {
                    console.log('Audio blocked:', e.message);
                });
            } else if (ttsMode === 'free' || ((ttsMode === 'openai' || ttsMode === 'groq') && !data.tts_audio_url)) {
                useWebSpeech(data);
            }
        }

        function useWebSpeech(data) {
            if (!ttsReady) return;
            let announcement = '';

            if (data.artist_note) {
                announcement = `You're listening to ${data.artist}. ${data.artist_note} `;
                announcement += `This is ${data.track}`;
                if (data.album) announcement += ` from ${data.album}`;
            } else if (data.album_note) {
                announcement = `Now playing ${data.track} by ${data.artist}. ${data.album_note}`;
            } else if (data.track_note) {
                announcement = `This is ${data.track} by ${data.artist}. ${data.track_note}`;
            } else {
                announcement = `Now playing ${data.track} by ${data.artist}`;
                if (data.album) announcement += ` from the album ${data.album}`;
            }

            speak(announcement);
        }

        // Initialize TTS on load
        initTTS();

        async function tick() {
            try {
                const r = await fetch("api/state.php?ts=" + Date.now());
                const j = await r.json();
                const el = document.getElementById("content");

                if (!j.track || !j.playing) {
                    el.innerHTML = '<div class="waiting">Waiting for playback...</div>';
                    lastTrack = '';
                    progressMs = 0;
                    durationMs = 0;
                    updateProgressBar();
                    return;
                }

                // Update progress bar
                progressMs = j.progress_ms || 0;
                durationMs = j.duration_ms || 0;
                lastProgressUpdate = Date.now();
                if (!progressInterval) startProgressAnimation();

                const trackKey = j.artist + '|||' + j.track;
                const trackChanged = trackKey !== lastTrack;
                if (!trackChanged) return;

                currentSlide = 0;
                lastTrack = trackKey;

                // Announce the song with DJ voice
                announceSong(j);

                // Thumb URLs - Spotify sends direct URLs, Plex needs proxy
                const thumbUrl = j.thumb || '';
                const artistThumbUrl = j.artist_thumb || '';
                const bgUrl = artistThumbUrl || thumbUrl;

                let html = '<div class="layout">';

                // Left Panel
                html += '<div class="left-panel">';
                if (bgUrl) {
                    html += '<div class="left-bg" style="background-image: url(\'' + bgUrl + '\')"></div>';
                }
                html += '<div class="left-overlay"></div>';
                html += '<div class="left-content">';
                html += '<div class="artist-name">' + escapeHtml(j.artist) + '</div>';
                html += '<div class="track-name">' + escapeHtml(j.track) + '</div>';

                if (thumbUrl) {
                    html += '<div class="album-vinyl-wrap">';
                    html += '<img class="album-cover" src="' + thumbUrl + '" alt="Album" onerror="this.parentElement.style.display=\'none\'">';
                    html += '<div class="vinyl">';
                    html += '<img class="vinyl-label" src="' + thumbUrl + '" alt="" onerror="this.style.display=\'none\'">';
                    html += '<div class="vinyl-hole"></div>';
                    html += '</div>';
                    html += '</div>';
                }
                html += '</div>';
                html += '</div>';

                // Right Panel
                html += '<div class="right-panel" id="sliderCard">';
                html += '<div class="slider-container">';
                html += '<div class="slides">';

                let slides = [];
                if (j.track_note) slides.push({ label: 'This Song', text: j.track_note });
                if (j.album_note) slides.push({ label: 'The Album', text: j.album_note });
                if (j.artist_note) slides.push({ label: 'The Artist', text: j.artist_note });

                if (slides.length === 0) {
                    slides.push({ label: 'Now Playing', text: j.album ? j.album + (j.year ? ' (' + j.year + ')' : '') : 'Enjoy the music' });
                }

                slideCount = slides.length;

                slides.forEach((s, i) => {
                    html += '<div class="slide' + (i === currentSlide ? ' active' : '') + '" data-index="' + i + '">';
                    html += '<div class="slide-label">' + s.label + '</div>';
                    html += '<div class="slide-text">' + escapeHtml(s.text) + '</div>';
                    html += '</div>';
                });

                html += '</div></div>';

                if (slides.length > 1) {
                    html += '<div class="slide-controls">';
                    html += '<div class="slide-dots">';
                    slides.forEach((_, i) => {
                        html += '<div class="dot' + (i === currentSlide ? ' active' : '') + '" data-index="' + i + '" onclick="goToSlide(' + i + ')"></div>';
                    });
                    html += '</div>';
                    html += '<button class="play-pause" onclick="toggleAutoSlide()" id="playPauseBtn">';
                    html += '<svg viewBox="0 0 24 24" fill="#222"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>';
                    html += '</button>';
                    html += '</div>';
                }

                html += '</div></div>';

                // Source badge
                if (j.source) {
                    html += '<div class="source-badge">' + escapeHtml(j.source) + '</div>';
                }

                el.innerHTML = html;
                startAutoSlide();
                initSwipe();
            } catch (e) { console.error(e); }
        }

        function goToSlide(index) {
            currentSlide = index;
            document.querySelectorAll('.slide').forEach((s, i) => s.classList.toggle('active', i === index));
            document.querySelectorAll('.dot').forEach((d, i) => d.classList.toggle('active', i === index));
            resetAutoSlide();
        }

        function nextSlide() {
            if (slideCount > 1) goToSlide((currentSlide + 1) % slideCount);
        }

        function prevSlide() {
            if (slideCount > 1) goToSlide((currentSlide - 1 + slideCount) % slideCount);
        }

        function startAutoSlide() {
            if (autoSlideTimer) clearInterval(autoSlideTimer);
            if (isPlaying) autoSlideTimer = setInterval(nextSlide, 8000);
            updatePlayPauseBtn();
        }

        function resetAutoSlide() {
            if (isPlaying) startAutoSlide();
        }

        function toggleAutoSlide() {
            isPlaying = !isPlaying;
            if (isPlaying) {
                startAutoSlide();
            } else {
                if (autoSlideTimer) clearInterval(autoSlideTimer);
                autoSlideTimer = null;
            }
            updatePlayPauseBtn();
        }

        function updatePlayPauseBtn() {
            const btn = document.getElementById('playPauseBtn');
            if (!btn) return;
            if (isPlaying) {
                btn.innerHTML = '<svg viewBox="0 0 24 24" fill="#222"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>';
            } else {
                btn.innerHTML = '<svg viewBox="0 0 24 24" fill="#222"><polygon points="6,4 20,12 6,20"/></svg>';
            }
        }

        let startX = 0;
        let isDragging = false;

        function initSwipe() {
            const card = document.getElementById('sliderCard');
            if (!card) return;

            card.addEventListener('touchstart', handleStart, { passive: true });
            card.addEventListener('touchmove', handleMove, { passive: true });
            card.addEventListener('touchend', handleEnd);
            card.addEventListener('mousedown', handleStart);
            card.addEventListener('mousemove', handleMove);
            card.addEventListener('mouseup', handleEnd);
            card.addEventListener('mouseleave', handleEnd);
        }

        function handleStart(e) {
            isDragging = true;
            startX = e.type.includes('mouse') ? e.clientX : e.touches[0].clientX;
        }

        function handleMove(e) {}

        function handleEnd(e) {
            if (!isDragging) return;
            isDragging = false;

            const endX = e.type.includes('mouse') ? e.clientX : (e.changedTouches ? e.changedTouches[0].clientX : startX);
            const diff = startX - endX;
            const threshold = 50;

            if (Math.abs(diff) > threshold) {
                if (diff > 0) nextSlide();
                else prevSlide();
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        tick();
        setInterval(tick, 4000);
    </script>
</body>
</html>
