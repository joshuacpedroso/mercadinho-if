let soundEnabled = true;
let audioContext = null;

const soundToggle = document.querySelector('#sound-toggle');
const soundToggleBottom = document.querySelector('#sound-toggle-bottom');
const codeInputs = document.querySelectorAll('input[name="code"], input[name="recipient_code"]');

function getAudioContext() {
    if (!soundEnabled) return null;
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return null;
    try {
        audioContext = audioContext || new AudioContext();
        return audioContext;
    } catch (e) {
        return null;
    }
}

// Sintetizador básico, sem MP3 pesado.
function playTone(freq, type, duration, vol) {
    const ctx = getAudioContext();
    if (!ctx) return;
    try {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = type;
        osc.frequency.value = freq;
        gain.gain.setValueAtTime(vol, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + duration);
    } catch (e) {}
}

function sfx(type) {
    if (type === 'tap') playTone(400, 'sine', 0.05, 0.02);
    if (type === 'submit') playTone(600, 'square', 0.1, 0.02);
    if (type === 'error') {
        playTone(200, 'sawtooth', 0.15, 0.03);
        setTimeout(() => playTone(150, 'sawtooth', 0.2, 0.03), 100);
    }
    if (type === 'success' || type === 'cash') {
        playTone(800, 'square', 0.1, 0.03);
        setTimeout(() => playTone(1200, 'square', 0.2, 0.03), 100);
    }
    if (type === 'donate') {
        playTone(500, 'square', 0.1, 0.03);
        setTimeout(() => playTone(750, 'square', 0.1, 0.03), 100);
        setTimeout(() => playTone(1000, 'square', 0.2, 0.03), 200);
    }
}

function sparkle(x, y) {
    for (let i = 0; i < 9; i += 1) {
        const dot = document.createElement('span');
        const angle = (Math.PI * 2 * i) / 9;
        const distance = 30 + Math.random() * 28;
        dot.className = 'sparkle';
        dot.style.left = `${x}px`;
        dot.style.top = `${y}px`;
        dot.style.setProperty('--x', `${Math.cos(angle) * distance}px`);
        dot.style.setProperty('--y', `${Math.sin(angle) * distance}px`);
        document.body.appendChild(dot);
        setTimeout(() => dot.remove(), 800);
    }
}

// Ligar/desligar som.
function setSoundButton(btn) {
    if (!btn) return;
    btn.setAttribute('aria-pressed', soundEnabled ? 'true' : 'false');
    btn.classList.toggle('sound-off', !soundEnabled);
    btn.classList.toggle('sound-on', soundEnabled);

    let icon = btn.querySelector('.icon');
    const label = btn.querySelector('span:not(.icon)');
    if (!icon) {
        icon = document.createElement('span');
        icon.setAttribute('aria-hidden', 'true');
        btn.insertBefore(icon, btn.firstChild);
    }
    icon.className = soundEnabled ? 'icon icon-sound-on' : 'icon icon-sound-off';
    if (!label && btn.id === 'sound-toggle') {
        btn.setAttribute('aria-label', soundEnabled ? 'Som ligado' : 'Som desligado');
    }
}
function toggleSound() {
    soundEnabled = !soundEnabled;
    setSoundButton(soundToggle);
    setSoundButton(soundToggleBottom);
    if (soundEnabled) sfx('success');
}
setSoundButton(soundToggle);
setSoundButton(soundToggleBottom);
if (soundToggle) soundToggle.addEventListener('click', toggleSound);
if (soundToggleBottom) soundToggleBottom.addEventListener('click', toggleSound);

// Formatador de código: maiúsculo, máximo 5, alfanumérico.
codeInputs.forEach(input => {
    input.addEventListener('input', () => {
        input.value = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 5);
        if (input.value.length === 5) sfx('success');
    });
});

// Efeitos de clique genéricos.
document.querySelectorAll('button, a, .product-card').forEach(el => {
    el.addEventListener('pointerdown', event => {
        if (!el.disabled) {
            sfx('tap');
            if (event.clientX && event.clientY) sparkle(event.clientX, event.clientY);
        }
    });
});

// Confirmações.
document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', e => {
        const msg = form.getAttribute('data-confirm') || 'Confirmar ação?';
        if (!window.confirm(msg)) {
            e.preventDefault();
            sfx('error');
            return;
        }
        sfx('submit');
    });
});

document.querySelectorAll('form:not([data-confirm])').forEach(form => {
    form.addEventListener('submit', () => sfx('submit'));
});

// Ações disparadas pelo backend via data-attributes.
window.addEventListener('DOMContentLoaded', () => {
    if (document.body.dataset.picked === '1') {
        document.body.classList.add('picked');
        setTimeout(() => {
            sfx('cash');
            const card = document.querySelector('.balance-card');
            if (card) {
                const rect = card.getBoundingClientRect();
                sparkle(rect.left + rect.width / 2, rect.top + rect.height / 2);
            }
        }, 300);
    }
    if (document.body.dataset.donated === '1') {
        setTimeout(() => {
            sfx('donate');
            const panel = document.querySelector('.donate-panel');
            if (panel) {
                const rect = panel.getBoundingClientRect();
                sparkle(rect.left + rect.width / 2, rect.top + 80);
            }
        }, 300);
    }
    if (document.body.dataset.rewarded === '1') {
        setTimeout(() => {
            sfx('cash');
            const panel = document.querySelector('.daily-goal-panel') || document.querySelector('.panel');
            if (panel) {
                const rect = panel.getBoundingClientRect();
                sparkle(rect.left + rect.width / 2, rect.top + 80);
            }
        }, 300);
    }
    if (document.body.dataset.flash === 'error') setTimeout(() => sfx('error'), 300);
});

// Padroniza imagens dos produtos para todos os cards ficarem do mesmo tamanho
(function normalizeProductImages() {
    const style = document.createElement('style');

    style.textContent = `
        .item-card {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .item-img,
        .product-image {
            width: 100%;
            height: 180px;
            min-height: 180px;
            max-height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 14px 14px 0 0;
        }

        .item-img img,
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            display: block;
            padding: 12px;
        }

        .item-body,
        .product-body {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .item-body form,
        .product-body form {
            margin-top: auto;
        }

        @media (max-width: 768px) {
            .item-img,
            .product-image {
                height: 130px;
                min-height: 130px;
                max-height: 130px;
            }

            .item-img img,
            .product-image img {
                padding: 8px;
            }
        }
    `;

    document.head.appendChild(style);
})();

// Mostra o nome da foto escolhida no cadastro de produto
document.querySelectorAll('.file-field-custom input[type="file"]').forEach((input) => {
    input.addEventListener('change', () => {
        const wrapper = input.closest('.file-field-custom');
        const preview = wrapper ? wrapper.querySelector('.file-name-preview') : null;

        if (!preview) return;

        preview.textContent = input.files && input.files.length
            ? input.files[0].name
            : 'Nenhuma foto selecionada';
    });
});
