@extends('layouts.app')
@section('title', 'Waiting-room display')
@section('body-class', 'display-body')
@section('hide-nav', 'true')
@section('content')
<div class="display-screen" id="display-screen">
    <header class="display-header">
        <div class="display-brand"><span class="brand-mark">Q</span><span id="organization">Queue Management</span></div>
        <div class="display-controls">
            <button type="button" class="sound-button" id="enable-sound">Enable sound</button>
            <button type="button" class="sound-button" id="mute-sound" aria-pressed="false">Sound on</button>
            <button type="button" class="sound-button" id="test-sound">Test sound</button>
            <label class="volume-control">Volume <input id="volume" type="range" min="0" max="1" step="0.05" value="0.8"></label>
        </div>
    </header>
    <main class="display-main">
        <div class="queue-open" id="queue-open">
            <div class="display-title">NOW SERVING</div>
            <div class="display-call">
                <div class="display-ticket" id="current-ticket">—</div>
                <div class="display-proceed">PLEASE PROCEED TO</div>
                <div class="display-desk" id="current-desk">WAITING FOR THE NEXT CALL</div>
            </div>
            <div class="display-bottom">
                <section class="recent-panel"><h2>Recent calls</h2><ol id="recent-calls"><li class="empty-recent">No tickets have been called yet.</li></ol></section>
                <div class="waiting-panel"><span class="waiting-count" id="waiting-count">0</span><span>customers waiting</span></div>
            </div>
        </div>
        <div class="display-closed hidden" id="display-closed">
            <div class="closed-symbol">✓</div><div class="display-title">QUEUE SESSION ENDED</div>
            <p id="closed-message">Please wait for staff to start the next session.</p>
        </div>
    </main>
    <footer class="display-footer"><span id="session-label">Waiting for queue information…</span><span id="connection-status">Connecting…</span></footer>
</div>
@endsection
@push('scripts')
<script>
(() => {
    const stateUrl = @json(route('display.state'));
    const elements = {
        organization: document.getElementById('organization'), open: document.getElementById('queue-open'),
        closed: document.getElementById('display-closed'), ticket: document.getElementById('current-ticket'),
        desk: document.getElementById('current-desk'), recent: document.getElementById('recent-calls'),
        count: document.getElementById('waiting-count'), session: document.getElementById('session-label'),
        connection: document.getElementById('connection-status'), enable: document.getElementById('enable-sound'),
        mute: document.getElementById('mute-sound'), test: document.getElementById('test-sound'),
        volume: document.getElementById('volume')
    };
    const speechAvailable = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
    let enabled = sessionStorage.getItem('queue-sound-enabled') === 'true';
    let muted = sessionStorage.getItem('queue-sound-muted') === 'true';
    const savedCursor = sessionStorage.getItem('queue-announcement-cursor');
    let cursor = Number(savedCursor || 0);
    let firstLoad = savedCursor === null;
    let language = 'en-US';
    let preferredVoice = '';

    function updateSoundControls() {
        elements.enable.classList.toggle('hidden', enabled);
        elements.enable.disabled = !speechAvailable;
        if (!speechAvailable) elements.enable.textContent = 'Audio unavailable';
        elements.mute.textContent = muted ? 'Sound muted' : 'Sound on';
        elements.mute.setAttribute('aria-pressed', String(muted));
        elements.mute.disabled = !speechAvailable;
        elements.test.disabled = !speechAvailable;
    }

    function speak(text, language) {
        if (!speechAvailable || !enabled || muted) return;
        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = language || 'en-US';
        utterance.volume = Number(elements.volume.value);
        const voices = window.speechSynthesis.getVoices();
        utterance.voice = voices.find(voice => voice.name.toLowerCase() === preferredVoice.toLowerCase())
            || voices.find(voice => voice.lang.toLowerCase() === utterance.lang.toLowerCase())
            || null;
        window.speechSynthesis.speak(utterance);
    }

    function render(data) {
        elements.organization.textContent = data.organization || 'Queue Management';
        const mark = document.querySelector('.display-brand .brand-mark');
        if (data.logo_url) {
            const image = document.createElement('img');
            image.src = data.logo_url;
            image.alt = '';
            image.className = 'organization-logo-display';
            mark.replaceChildren(image);
        } else {
            mark.textContent = 'Q';
        }
        language = data.language || 'en-US';
        preferredVoice = data.voice || '';
        elements.session.textContent = data.session ? `Session: ${data.session}` : 'No active queue session';
        elements.count.textContent = data.waiting_count ?? 0;
        const ended = data.status !== 'open';
        elements.open.classList.toggle('hidden', ended);
        elements.closed.classList.toggle('hidden', !ended);
        if (ended) {
            elements.connection.textContent = data.status === 'ended' ? 'Session ended' : 'Queue closed';
            return;
        }
        elements.connection.textContent = 'Live updates active';
        if (data.current) {
            elements.ticket.textContent = data.current.ticket;
            elements.desk.textContent = data.current.desk;
        } else {
            elements.ticket.textContent = '—';
            elements.desk.textContent = 'WAITING FOR THE NEXT CALL';
        }
        elements.recent.replaceChildren();
        if (!data.recent.length) {
            const empty = document.createElement('li');
            empty.className = 'empty-recent';
            empty.textContent = 'No tickets have been called yet.';
            elements.recent.append(empty);
        } else {
            data.recent.forEach(call => {
                const item = document.createElement('li');
                const number = document.createElement('strong');
                number.textContent = call.ticket;
                const desk = document.createElement('span');
                desk.textContent = call.desk;
                item.append(number, desk);
                elements.recent.append(item);
            });
        }
    }

    async function poll() {
        try {
            const url = new URL(stateUrl, window.location.origin);
            if (cursor > 0) url.searchParams.set('cursor', String(cursor));
            const response = await fetch(url, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('Queue state request failed.');
            const data = await response.json();
            render(data);
            if (!firstLoad) {
                for (const call of data.announcements || []) {
                    if (call.id > cursor) {
                        cursor = call.id;
                        sessionStorage.setItem('queue-announcement-cursor', String(cursor));
                        speak(`Ticket ${[...call.ticket].join(' ')}, please proceed to ${call.desk}.`, data.language);
                    }
                }
            } else if (data.cursor > cursor) {
                cursor = data.cursor;
                sessionStorage.setItem('queue-announcement-cursor', String(cursor));
            }
            firstLoad = false;
        } catch {
            elements.connection.textContent = 'Reconnecting…';
        }
    }

    elements.enable.addEventListener('click', () => {
        enabled = true;
        sessionStorage.setItem('queue-sound-enabled', 'true');
        updateSoundControls();
        speak('Sound is enabled for queue announcements.', language);
    });
    elements.mute.addEventListener('click', () => {
        muted = !muted;
        sessionStorage.setItem('queue-sound-muted', String(muted));
        if (muted && speechAvailable) window.speechSynthesis.cancel();
        updateSoundControls();
    });
    elements.test.addEventListener('click', () => speak('Sound test. Ticket A zero zero one, please proceed to Desk One.', language));
    elements.volume.value = sessionStorage.getItem('queue-sound-volume') || '0.8';
    elements.volume.addEventListener('input', () => sessionStorage.setItem('queue-sound-volume', elements.volume.value));
    updateSoundControls();
    poll();
    window.setInterval(poll, 2500);
})();
</script>
@endpush
