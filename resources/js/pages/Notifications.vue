<script setup>
import { ref, onMounted } from 'vue';
import { session } from '../session';
import { http, errorText } from '../http';
import { pushSupported, needsHomeScreen, isStandalone, pushEnabled, enablePush, disablePush } from '../push';
const loading = ref(true);
const busy = ref(false);
const enabled = ref(false);
const publicKey = ref(null);
const serverEnabled = ref(false);
const error = ref('');
const success = ref('');
const installFirst = needsHomeScreen() && !isStandalone();
const supported = pushSupported();
onMounted(async () => {
    try {
        const { data } = await http.get('/push/config');
        publicKey.value = data.public_key;
        serverEnabled.value = data.enabled;
        enabled.value = await pushEnabled(session.user.id);
    } catch (e) { error.value = errorText(e); }
    finally { loading.value = false; }
});
async function toggle() {
    busy.value = true;
    error.value = '';
    success.value = '';
    try {
        if (enabled.value) await disablePush();
        else await enablePush(publicKey.value, session.user.id);
        enabled.value = !enabled.value;
        success.value = enabled.value ? 'Notificações ativadas neste dispositivo.' : 'Notificações desativadas neste dispositivo.';
    } catch (e) { error.value = e.response ? errorText(e) : e.message; }
    finally { busy.value = false; }
}
</script>
<template>
    <RouterLink class="back-link" to="/app/imoveis">← Voltar aos imóveis</RouterLink>
    <div class="page-heading"><div><span class="eyebrow">FIQUE POR DENTRO</span><h1>Notificações</h1><p class="muted">Receba os avisos da CCI neste celular ou computador.</p></div></div>
    <section class="panel notification-settings">
        <h2>Conversas e novos imóveis</h2>
        <p>Saiba quando receber uma mensagem em uma negociação ou quando um novo imóvel for cadastrado na rede.</p>
        <p class="muted">Toque no aviso para abrir a conversa ou o imóvel. Você pode desativar as notificações aqui a qualquer momento.</p>
        <p v-if="installFirst" class="alert" role="status">No iPhone ou iPad, use Compartilhar → Adicionar à Tela de Início. Abra a CCI pelo ícone criado e volte aqui para ativar os avisos. Requer iOS 16.4 ou superior.</p>
        <p v-else-if="!supported" class="alert">Este navegador não oferece notificações. Abra a CCI em um navegador compatível, usando HTTPS.</p>
        <p v-else-if="!loading && !serverEnabled" class="alert">As notificações estão temporariamente indisponíveis.</p>
        <p v-if="error" class="alert error" role="alert">{{ error }}</p>
        <p v-if="success" class="alert" role="status">{{ success }}</p>
        <button v-if="supported && !installFirst" class="primary" :disabled="loading || busy || (!serverEnabled && !enabled)" @click="toggle">{{ loading ? 'Carregando…' : busy ? 'Aguarde…' : enabled ? 'Desativar notificações' : 'Ativar notificações' }}</button>
    </section>
</template>
