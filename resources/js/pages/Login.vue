<script setup>
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { http, unwrap, errorText, refreshCsrf } from "../http";
import { session } from "../session";
const router = useRouter();
const form = reactive({ email: "", password: "" });
const busy = ref(false);
const error = ref("");
async function submit() {
    busy.value = true;
    error.value = "";
    try {
        await refreshCsrf();
        session.user = unwrap(
            await http.post("/login", form, { baseURL: "/" }),
        );
        await refreshCsrf();
        await router.replace("/app/imoveis");
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <div class="login-page">
        <section class="login-story">
            <a class="brand" href="/"
                ><img src="/icons/icon-512.png" alt="CCI" /><span
                    >CCI<small>Central de Corretores de Imóveis</small></span
                ></a
            >
            <div>
                <span class="eyebrow"
                    >BOAS CONEXÕES. GRANDES OPORTUNIDADES.</span
                >
                <h1>
                    O próximo negócio<br />começa com uma<br /><em
                        >boa parceria.</em
                    >
                </h1>
                <p>
                    Seu portfólio, sua rede e suas negociações.<br />Tudo
                    conectado, onde você estiver.
                </p>
            </div>
            <span class="story-footer"
                >Uma rede feita por corretores, para corretores.</span
            >
        </section>
        <section class="login-form">
            <div class="login-card">
                <span class="eyebrow">BEM-VINDO À CCI</span>
                <h2>Vamos fazer negócios?</h2>
                <p class="muted">Entre com a mesma conta do aplicativo.</p>
                <form @submit.prevent="submit">
                    <p v-if="error" class="alert error" role="alert">
                        {{ error }}
                    </p>
                    <label
                        >E-mail<input
                            v-model="form.email"
                            type="email"
                            autocomplete="username"
                            placeholder="voce@exemplo.com"
                            required
                            maxlength="255" /></label
                    ><label
                        >Senha<input
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="Sua senha"
                            required /></label
                    ><button class="primary full" :disabled="busy">
                        {{ busy ? "Entrando…" : "Entrar na minha conta →" }}
                    </button>
                </form>
                <p class="invite-note">
                    Ainda não faz parte da rede? Peça um convite ao
                    administrador para criar sua conta.
                </p>
            </div>
            <small class="muted">CCI · Central de Corretores de Imóveis</small>
        </section>
    </div>
</template>
