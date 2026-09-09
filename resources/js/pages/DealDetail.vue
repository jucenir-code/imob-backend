<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, nextTick, watch } from "vue";
import { useRoute } from "vue-router";
import { http, unwrap, errorText, dateTime, dealStatuses } from "../http";
import { session, managesGroup } from "../session";
import Pagination from "../components/Pagination.vue";
import Icon from "../components/Icon.vue";
const route = useRoute();
const id = route.params.id;
const deal = ref(null);
const messages = ref([]);
const meta = ref(null);
const error = ref("");
const busy = ref(false);
const text = ref("");
const files = ref([]);
const fileInput = ref(null);
const messageList = ref(null);
const messageInput = ref(null);
const detailsDialog = ref(null);
const nearBottom = ref(true);
const online = ref(navigator.onLine);
const viewportStyle = ref({});
const otherPerson = computed(() =>
    deal.value?.seller_agent?.id === session.user?.id
        ? deal.value?.buyer_agent : deal.value?.seller_agent,
);
function scrollToBottom() {
    const list = messageList.value;
    if (list) list.scrollTop = list.scrollHeight;
    nearBottom.value = true;
}
function trackScroll() {
    const list = messageList.value;
    if (list) nearBottom.value = list.scrollHeight - list.scrollTop - list.clientHeight < 60;
}
function attachmentLoaded() {
    if (nearBottom.value) scrollToBottom();
}
function resizeInput() {
    const input = messageInput.value;
    if (!input) return;
    input.style.height = 'auto';
    input.style.height = `${Math.min(input.scrollHeight, 104)}px`;
}
watch(text, () => nextTick(resizeInput));
function updateViewport() {
    const viewport = window.visualViewport;
    // Let native pinch zoom work; compensate only for the keyboard/browser bars.
    if (viewport && viewport.scale !== 1) return;
    viewportStyle.value = {
        '--conversation-height': `${viewport?.height || window.innerHeight}px`,
        '--conversation-top': `${viewport?.offsetTop || 0}px`,
    };
    if (nearBottom.value) nextTick(scrollToBottom);
}
function updateConnection() { online.value = navigator.onLine; }
function clearFiles() {
    files.value = [];
    if (fileInput.value) fileInput.value.value = '';
}
const messageTime = (value) => new Date(value).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
const messageDay = (value) => new Date(value).toLocaleDateString('pt-BR');
const recording = ref(false);
const recordingSupported = Boolean(
    navigator.mediaDevices?.getUserMedia && window.MediaRecorder,
);
const form = reactive({
    status: "",
    buyer_name: "",
    buyer_contact: "",
    commission_percent: 6,
    commission_split: { seller_agent: 50, buyer_agent: 50 },
    notes: "",
});
let timer;
let recorder;
let stream;
let disposed = false;
let started;
let polling = false;
const canAccept = computed(
    () =>
        deal.value?.seller_agent?.id === session.user?.id ||
        managesGroup(deal.value?.property?.group?.id),
);
const canSend = computed(
    () =>
        deal.value &&
        !["initiated", "closed", "cancelled", "lost"].includes(
            deal.value.status,
        ),
);
function fillForm() {
    for (const key of Object.keys(form))
        if (deal.value[key] !== undefined)
            form[key] =
                key === "commission_split"
                    ? { ...deal.value[key] }
                    : deal.value[key];
}
async function loadMessages(page, position = "preserve") {
    const { data } = await http.get(`/deals/${id}/messages`, {
        params: { page: page || meta.value?.current_page || 1, per_page: 30 },
    });
    if (!disposed) {
        const list = messageList.value;
        const follow = !list || list.scrollHeight - list.scrollTop - list.clientHeight < 60;
        const previousTop = list?.scrollTop || 0;
        messages.value = data.data;
        meta.value = data.meta;
        await nextTick();
        if (position === "bottom" || (position === "preserve" && follow)) scrollToBottom();
        else if (messageList.value) messageList.value.scrollTop = position === "top" ? 0 : previousTop;
    }
}
async function messagesPage(page) {
    try {
        await loadMessages(page, "top");
    } catch (e) {
        error.value = errorText(e);
    }
}
async function latestMessages() {
    try {
        await loadMessages(meta.value?.last_page || 1, "bottom");
    } catch (e) {
        error.value = errorText(e);
    }
}
async function initialize() {
    try {
        deal.value = unwrap(await http.get(`/deals/${id}`));
        fillForm();
        await loadMessages(1);
        if (meta.value?.last_page > 1) await loadMessages(meta.value.last_page, "bottom");
    } catch (e) {
        error.value = errorText(e);
    }
}
async function refresh() {
    if (
        disposed ||
        document.hidden ||
        !navigator.onLine ||
        polling ||
        busy.value
    )
        return;
    polling = true;
    try {
        const latest = unwrap(await http.get(`/deals/${id}`));
        if (!disposed && deal.value) deal.value.status = latest.status;
        const wasLast = meta.value?.current_page === meta.value?.last_page;
        await loadMessages();
        if (wasLast && nearBottom.value && meta.value.current_page < meta.value.last_page)
            await loadMessages(meta.value.last_page, "bottom");
    } catch (e) {
        if (!disposed) error.value = errorText(e);
    } finally {
        polling = false;
    }
}
async function save(accept = false) {
    busy.value = true;
    error.value = "";
    try {
        deal.value = unwrap(
            await http.patch(
                `/deals/${id}`,
                accept ? { status: "proposal" } : form,
            ),
        );
        fillForm();
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
function selectFiles(event) {
    files.value = Array.from(event.target.files || []);
}
async function send() {
    if (!text.value.trim() && !files.value.length) return;
    const body = new FormData();
    body.append("message", text.value.trim());
    files.value.forEach((file, index) =>
        body.append(`attachments[${index}]`, file),
    );
    busy.value = true;
    error.value = "";
    try {
        await http.post(`/deals/${id}/messages`, body);
        text.value = "";
        files.value = [];
        if (fileInput.value) fileInput.value.value = "";
        await loadMessages(meta.value?.last_page || 1, "bottom");
        if (meta.value.current_page < meta.value.last_page)
            await loadMessages(meta.value.last_page, "bottom");
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
async function record() {
    if (recording.value) {
        recorder.stop();
        return;
    }
    try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        if (disposed) {
            stream.getTracks().forEach((track) => track.stop());
            return;
        }
        const mimeType = ["audio/webm", "audio/mp4", "audio/ogg"].find((type) =>
            MediaRecorder.isTypeSupported(type),
        );
        recorder = new MediaRecorder(
            stream,
            mimeType ? { mimeType } : undefined,
        );
        const chunks = [];
        started = Date.now();
        recorder.ondataavailable = (event) => {
            if (event.data.size) chunks.push(event.data);
        };
        recorder.onstop = () => {
            stream?.getTracks().forEach((track) => track.stop());
            recording.value = false;
            if (disposed) return;
            const type = recorder.mimeType.split(";")[0];
            const extension = type.includes("mp4")
                ? "m4a"
                : type.includes("ogg")
                  ? "ogg"
                  : "webm";
            files.value = [
                new File(chunks, `audio-${Date.now()}.${extension}`, { type }),
            ];
        };
        recorder.start();
        recording.value = true;
    } catch {
        stream?.getTracks().forEach((track) => track.stop());
        error.value =
            "Não foi possível acessar o microfone. Permita o acesso ou anexe um arquivo de áudio.";
    }
}
onMounted(async () => {
    updateViewport();
    window.visualViewport?.addEventListener('resize', updateViewport);
    window.visualViewport?.addEventListener('scroll', updateViewport);
    window.addEventListener('resize', updateViewport);
    window.addEventListener('online', updateConnection);
    window.addEventListener('offline', updateConnection);
    await initialize();
    if (!disposed) timer = setInterval(refresh, 15000);
});
onUnmounted(() => {
    disposed = true;
    window.visualViewport?.removeEventListener('resize', updateViewport);
    window.visualViewport?.removeEventListener('scroll', updateViewport);
    window.removeEventListener('resize', updateViewport);
    window.removeEventListener('online', updateConnection);
    window.removeEventListener('offline', updateConnection);
    clearInterval(timer);
    if (recorder?.state === "recording") recorder.stop();
    stream?.getTracks().forEach((track) => track.stop());
});
const attachments = (message) =>
    message.attachments?.length
        ? message.attachments
        : message.attachment_url
          ? [
                {
                    id: "legacy",
                    url: message.attachment_url,
                    type: message.attachment_type,
                    original_name: message.attachment_original_name,
                },
            ]
          : [];
</script>
<template>
    <section class="conversation" :style="viewportStyle" aria-label="Conversa da negociação">
        <header class="conversation-header">
            <RouterLink class="chat-icon" to="/app/negociacoes" aria-label="Voltar às negociações"><Icon name="arrow" /></RouterLink>
            <span class="conversation-avatar" aria-hidden="true">{{ otherPerson?.name?.slice(0, 1) || 'C' }}</span>
            <div class="conversation-heading">
                <h1>{{ otherPerson?.name || 'Conversa' }}</h1>
                <p>{{ deal?.property?.title || 'Carregando negociação…' }}</p>
            </div>
            <button v-if="deal" class="chat-icon" aria-label="Dados da negociação" @click="detailsDialog.showModal()"><Icon name="info" /></button>
        </header>
        <p v-if="!online" class="conversation-notice" role="status">Sem conexão. Suas mensagens serão enviadas quando você tentar novamente.</p>
        <p v-if="error" class="conversation-notice error" role="alert">{{ error }} <button class="text-button" @click="refresh">Tentar novamente</button></p>
        <div v-if="deal?.status === 'initiated'" class="conversation-notice">
            Aguardando aceite do responsável.
            <button v-if="canAccept" class="text-button" :disabled="busy" @click="save(true)">Aceitar negociação</button>
        </div>
        <div class="conversation-history">
            <div ref="messageList" class="messages" aria-live="polite" aria-label="Mensagens da negociação" @scroll="trackScroll">
                <Pagination :meta="meta" :busy="busy" @change="messagesPage" />
                <p v-if="!deal && !error" class="chat-system" role="status">Carregando conversa…</p>
                <p v-else-if="!messages.length" class="chat-system">A conversa começa aqui.</p>
                <template v-for="(message, index) in messages" :key="message.id">
                    <span v-if="index === 0 || messageDay(message.created_at) !== messageDay(messages[index - 1].created_at)" class="chat-date">{{ messageDay(message.created_at) }}</span>
                    <article class="message" :class="{ mine: message.author?.id === session.user?.id, 'chat-system': !message.author }">
                        <strong v-if="message.author && message.author.id !== session.user?.id">{{ message.author.name }}</strong>
                        <p v-if="message.message" class="preserve-lines">{{ message.message }}</p>
                        <template v-for="attachment in attachments(message)" :key="attachment.id">
                            <a v-if="attachment.type === 'image'" :href="attachment.url" target="_blank" rel="noopener">
                                <img :src="attachment.url" :alt="attachment.original_name || 'Imagem enviada'" loading="lazy" @load="attachmentLoaded" />
                            </a>
                            <audio v-else-if="attachment.type === 'audio'" :src="attachment.url" controls preload="none" :aria-label="attachment.original_name || 'Áudio enviado'"></audio>
                        </template>
                        <time :datetime="message.created_at" :title="dateTime(message.created_at)">{{ messageTime(message.created_at) }}</time>
                    </article>
                </template>
            </div>
            <button v-if="!nearBottom || meta?.current_page < meta?.last_page" class="chat-icon latest-messages" aria-label="Ir para últimas mensagens" @click="latestMessages"><Icon name="down" /></button>
        </div>
        <form v-if="canSend" class="composer" @submit.prevent="send">
            <div v-if="files.length" class="attachment-list">
                <span>{{ files.map(file => file.name).join(', ') }}</span>
                <button type="button" class="chat-icon" aria-label="Remover anexos" @click="clearFiles"><Icon name="close" /></button>
            </div>
            <p v-if="recording" class="recording-status" role="status"><span></span>Gravando áudio… Toque em parar para concluir.</p>
            <div class="composer-row">
                <label class="chat-icon file-button" title="Anexar imagem ou áudio">
                    <Icon name="clip" /><span class="sr-only">Anexar imagem ou áudio</span>
                    <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,audio/*" multiple @change="selectFiles" :disabled="busy || recording" />
                </label>
                <label class="sr-only" for="message">Mensagem</label>
                <textarea id="message" ref="messageInput" v-model="text" rows="1" placeholder="Mensagem" @keydown.enter="($event.ctrlKey || $event.metaKey) && !$event.isComposing && !busy && !recording && send()"></textarea>
                <button v-if="recordingSupported" type="button" class="chat-icon" :class="{ 'is-recording': recording }" :aria-label="recording ? 'Parar gravação' : 'Gravar áudio'" :disabled="busy" @click="record"><Icon :name="recording ? 'stop' : 'mic'" /></button>
                <button class="chat-icon send-message" :aria-label="busy ? 'Enviando…' : 'Enviar →'" :disabled="busy || recording || (!text.trim() && !files.length)"><Icon name="send" /></button>
            </div>
        </form>
        <p v-else-if="deal" class="conversation-notice">O envio de mensagens está indisponível nesta etapa.</p>
        <dialog ref="detailsDialog" class="conversation-details" aria-labelledby="conversation-details-title">
            <header><h2 id="conversation-details-title">Dados da negociação</h2><button type="button" class="chat-icon" aria-label="Fechar detalhes" @click="detailsDialog.close()"><Icon name="close" /></button></header>
            <template v-if="deal">
                <p class="badge">{{ dealStatuses[deal.status] }}</p>
                <p class="small muted">Use o chat interno. Não compartilhe telefone, e-mail ou links de contato. Anexe até 6 imagens ou 1 áudio por mensagem; até 20 MB por arquivo.</p>
                <p v-if="error" class="alert error" role="alert">{{ error }}</p>
                    <form @submit.prevent="save(false)">
                        <label
                            >Situação<select v-model="form.status">
                                <option
                                    v-for="(label, key) in dealStatuses"
                                    :key="key"
                                    :value="key"
                                    :disabled="
                                        deal.status === 'initiated' &&
                                        key === 'proposal' &&
                                        !canAccept
                                    "
                                >
                                    {{ label }}
                                </option>
                            </select></label
                        ><label
                            >Nome do cliente<input
                                v-model="form.buyer_name"
                                maxlength="120" /></label
                        ><label
                            >Contato do cliente<input
                                v-model="form.buyer_contact"
                                maxlength="50" /></label
                        ><label
                            >Comissão (%)<input
                                v-model.number="form.commission_percent"
                                type="number"
                                required
                                min="0"
                                max="20"
                                step="0.01" /></label
                        ><label
                            >Participação do vendedor (%)<input
                                v-model.number="
                                    form.commission_split.seller_agent
                                "
                                type="number"
                                required
                                min="0"
                                max="100"
                                step="0.01" /></label
                        ><label
                            >Participação do comprador (%)<input
                                v-model.number="
                                    form.commission_split.buyer_agent
                                "
                                type="number"
                                required
                                min="0"
                                max="100"
                                step="0.01" /></label
                        ><label
                            >Observações<textarea
                                v-model="form.notes"
                                rows="3"
                            ></textarea></label
                        ><button class="primary" :disabled="busy">
                            Salvar alterações
                        </button>
                    </form>
                <RouterLink class="secondary full" :to="`/app/imoveis/${deal.property_id}`">Ver imóvel ↗</RouterLink>
            </template>
        </dialog>
    </section>
</template>
