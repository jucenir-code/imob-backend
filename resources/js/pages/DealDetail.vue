<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watchPostEffect } from "vue";
import { useRoute } from "vue-router";
import { http, unwrap, errorText, dateTime, dealStatuses } from "../http";
import { session, managesGroup } from "../session";
import Pagination from "../components/Pagination.vue";
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
const composer = ref(null);
const composerHeight = ref(0);
watchPostEffect((onCleanup) => {
    if (!composer.value) {
        composerHeight.value = 0;
        return;
    }
    const element = composer.value;
    const observer = new ResizeObserver(() => {
        composerHeight.value = element.getBoundingClientRect().height;
    });
    observer.observe(element);
    onCleanup(() => observer.disconnect());
});
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
async function loadMessages(page) {
    const { data } = await http.get(`/deals/${id}/messages`, {
        params: { page: page || meta.value?.current_page || 1, per_page: 30 },
    });
    if (!disposed) {
        messages.value = data.data;
        meta.value = data.meta;
    }
}
async function messagesPage(page) {
    try {
        await loadMessages(page);
    } catch (e) {
        error.value = errorText(e);
    }
}
async function initialize() {
    try {
        deal.value = unwrap(await http.get(`/deals/${id}`));
        fillForm();
        await loadMessages(1);
        if (meta.value?.last_page > 1) await loadMessages(meta.value.last_page);
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
        if (wasLast && meta.value.current_page < meta.value.last_page)
            await loadMessages(meta.value.last_page);
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
        await loadMessages(meta.value?.last_page || 1);
        if (meta.value.current_page < meta.value.last_page)
            await loadMessages(meta.value.last_page);
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
    await initialize();
    if (!disposed) timer = setInterval(refresh, 15000);
});
onUnmounted(() => {
    disposed = true;
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
    <RouterLink class="back-link" to="/app/negociacoes"
        >← Voltar às negociações</RouterLink
    >
    <p v-if="error" class="alert error" role="alert">{{ error }}</p>
    <template v-if="deal"
        ><div class="page-heading">
            <div>
                <span class="eyebrow">NEGOCIAÇÃO #{{ deal.id }}</span>
                <h1>{{ deal.property?.title }}</h1>
                <p class="muted">
                    {{ deal.seller_agent?.name }} e {{ deal.buyer_agent?.name }}
                </p>
            </div>
            <span class="badge">{{ dealStatuses[deal.status] }}</span>
        </div>
        <div v-if="deal.status === 'initiated'" class="alert">
            <span>A conversa aguarda o aceite do responsável pelo imóvel.</span
            ><button
                v-if="canAccept"
                class="primary"
                :disabled="busy"
                @click="save(true)"
            >
                Aceitar negociação
            </button>
        </div>
        <div class="chat-layout" :style="{ '--composer-height': `${composerHeight}px` }">
            <section class="panel chat">
                <div class="section-line">
                    <h2>Conversa</h2>
                    <button class="text-button" @click="refresh">
                        Atualizar
                    </button>
                </div>
                <p class="small muted">
                    Use o chat interno. Não compartilhe telefone, e-mail ou
                    links de contato.
                </p>
                <div
                    class="messages"
                    aria-live="polite"
                    aria-label="Mensagens da negociação"
                >
                    <p v-if="!messages.length" class="empty">
                        A conversa começa aqui.
                    </p>
                    <article
                        v-for="message in messages"
                        :key="message.id"
                        class="message"
                        :class="{
                            mine: message.author?.id === session.user?.id,
                        }"
                    >
                        <strong>{{ message.author?.name }}</strong>
                        <p v-if="message.message" class="preserve-lines">
                            {{ message.message }}
                        </p>
                        <template
                            v-for="attachment in attachments(message)"
                            :key="attachment.id"
                            ><a
                                v-if="attachment.type === 'image'"
                                :href="attachment.url"
                                target="_blank"
                                rel="noopener"
                                ><img
                                    :src="attachment.url"
                                    :alt="
                                        attachment.original_name ||
                                        'Imagem enviada'
                                    "
                                    loading="lazy" /></a
                            ><audio
                                v-else-if="attachment.type === 'audio'"
                                :src="attachment.url"
                                controls
                                preload="none"
                                :aria-label="
                                    attachment.original_name || 'Áudio enviado'
                                "
                            ></audio></template
                        ><time>{{ dateTime(message.created_at) }}</time>
                    </article>
                </div>
                <Pagination :meta="meta" :busy="busy" @change="messagesPage" />
                <form v-if="canSend" ref="composer" class="composer" @submit.prevent="send">
                    <label class="sr-only" for="message">Mensagem</label
                    ><textarea
                        id="message"
                        v-model="text"
                        rows="2"
                        placeholder="Escreva sua mensagem…"
                    ></textarea>
                    <div v-if="files.length" class="attachment-list">
                        <span v-for="file in files" :key="file.name">{{
                            file.name
                        }}</span
                        ><button
                            type="button"
                            class="text-button"
                            @click="
                                files = [];
                                fileInput.value = '';
                            "
                        >
                            Remover anexos
                        </button>
                    </div>
                    <div class="composer-actions">
                        <label class="secondary file-button"
                            >Anexar<input
                                ref="fileInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,image/heic,image/heif,audio/*"
                                multiple
                                @change="selectFiles"
                                :disabled="busy || recording" /></label
                        ><button
                            v-if="recordingSupported"
                            type="button"
                            class="secondary"
                            :disabled="busy"
                            @click="record"
                        >
                            {{
                                recording ? "■ Parar gravação" : "Gravar áudio"
                            }}</button
                        ><button
                            class="primary"
                            :disabled="
                                busy ||
                                recording ||
                                (!text.trim() && !files.length)
                            "
                        >
                            {{ busy ? "Enviando…" : "Enviar →" }}
                        </button>
                    </div>
                    <small class="muted"
                        >Até 6 imagens ou 1 áudio por mensagem; 20 MB por
                        arquivo.</small
                    >
                </form>
                <p v-else class="alert">
                    O envio de mensagens está indisponível nesta etapa.
                </p>
            </section>
            <aside>
                <details class="panel deal-settings">
                    <summary>Dados da negociação</summary>
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
                </details>
                <RouterLink
                    class="secondary full"
                    :to="`/app/imoveis/${deal.property_id}`"
                    >Ver imóvel ↗</RouterLink
                >
            </aside>
        </div></template
    >
    <p v-else-if="!error" role="status">Carregando negociação…</p>
</template>
