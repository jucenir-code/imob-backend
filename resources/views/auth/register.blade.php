<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Cadastro de Vendedor</title>
        <style>
            :root {
                color-scheme: light;
                --bg: #f8fafc;
                --panel: #ffffff;
                --text: #0f172a;
                --muted: #64748b;
                --border: #e2e8f0;
                --primary: #2563eb;
                --primary-dark: #1d4ed8;
                --danger-bg: #fef2f2;
                --danger-text: #b91c1c;
                --success-bg: #ecfdf5;
                --success-text: #166534;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                background:
                    radial-gradient(circle at top, rgba(37, 99, 235, 0.12), transparent 30%),
                    linear-gradient(180deg, #eff6ff 0%, var(--bg) 35%);
                color: var(--text);
            }

            .page {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 32px 16px;
            }

            .card {
                width: 100%;
                max-width: 560px;
                background: var(--panel);
                border: 1px solid rgba(226, 232, 240, 0.9);
                border-radius: 24px;
                padding: 32px;
                box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
            }

            .eyebrow {
                display: inline-flex;
                align-items: center;
                padding: 6px 12px;
                border-radius: 999px;
                background: #dbeafe;
                color: var(--primary-dark);
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            h1 {
                margin: 16px 0 10px;
                font-size: 32px;
                line-height: 1.1;
            }

            .subtitle {
                margin: 0 0 24px;
                color: var(--muted);
                font-size: 16px;
                line-height: 1.6;
            }

            .alert {
                border-radius: 16px;
                padding: 14px 16px;
                margin-bottom: 20px;
                font-size: 14px;
                line-height: 1.5;
            }

            .alert-danger {
                background: var(--danger-bg);
                color: var(--danger-text);
                border: 1px solid rgba(239, 68, 68, 0.16);
            }

            .alert-success {
                background: var(--success-bg);
                color: var(--success-text);
                border: 1px solid rgba(34, 197, 94, 0.16);
            }

            form {
                display: grid;
                gap: 18px;
            }

            .field {
                display: grid;
                gap: 8px;
            }

            label {
                font-size: 14px;
                font-weight: 600;
            }

            input {
                width: 100%;
                border: 1px solid var(--border);
                border-radius: 14px;
                padding: 14px 16px;
                font-size: 15px;
                color: var(--text);
                background: #fff;
                outline: none;
                transition: border-color 0.15s ease, box-shadow 0.15s ease;
            }

            input:focus {
                border-color: rgba(37, 99, 235, 0.7);
                box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
            }

            input[readonly] {
                background: #f8fafc;
                color: var(--muted);
            }

            .hint,
            .error {
                font-size: 13px;
                line-height: 1.5;
            }

            .hint {
                color: var(--muted);
            }

            .error {
                color: var(--danger-text);
            }

            .button {
                border: 0;
                border-radius: 16px;
                padding: 16px 18px;
                background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
                color: #fff;
                font-size: 16px;
                font-weight: 700;
            }

            .button:hover {
                filter: brightness(1.03);
            }

            .footer {
                margin-top: 20px;
                color: var(--muted);
                font-size: 13px;
                text-align: center;
            }

            @media (max-width: 640px) {
                .card {
                    padding: 24px;
                    border-radius: 20px;
                }

                h1 {
                    font-size: 28px;
                }
            }
        </style>
    </head>
    <body>
        @php
            $hasInvite = $inviteToken !== '' && $invite !== null;
            $lockedEmail = old('email', $prefilledEmail !== '' ? $prefilledEmail : ($invite?->email ?? ''));
        @endphp

        <main class="page">
            <section class="card">
                <span class="eyebrow">Convite Circles</span>
                <h1>Cadastro de vendedor</h1>
                <p class="subtitle">
                    Complete seus dados para ativar a conta enviada pela equipe da Circles Imobiliária.
                </p>

                @if (! $hasInvite)
                    <div class="alert alert-danger">
                        Este link de convite é inválido, já foi utilizado ou expirou.
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        Não foi possível concluir o cadastro. Revise os campos destacados.
                    </div>
                @endif

                <form method="POST" action="{{ route('register.store') }}">
                    @csrf

                    <input type="hidden" name="invite_token" value="{{ old('invite_token', $inviteToken) }}">

                    <div class="field">
                        <label for="name">Nome completo</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required>
                        @error('name')
                            <div class="error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="email">E-mail</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email', $lockedEmail) }}"
                            @if ($invite?->email) readonly @endif
                            required
                        >
                        @if ($invite?->email)
                            <div class="hint">Este convite foi emitido para este e-mail e não pode ser alterado.</div>
                        @endif
                        @error('email')
                            <div class="error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="phone_e164">Telefone</label>
                        <input
                            id="phone_e164"
                            name="phone_e164"
                            type="text"
                            value="{{ old('phone_e164') }}"
                            placeholder="+55 11 99999-9999"
                        >
                        @error('phone_e164')
                            <div class="error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="password">Senha</label>
                        <input id="password" name="password" type="password" required>
                        <div class="hint">Use no minimo 6 caracteres.</div>
                        @error('password')
                            <div class="error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="password_confirmation">Confirmar senha</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required>
                    </div>

                    @error('invite_token')
                        <div class="error">{{ $message }}</div>
                    @enderror

                    <button class="button" type="submit" @disabled(! $hasInvite)>Criar conta</button>
                </form>

                <p class="footer">Se este convite nao era para voce, ignore esta pagina.</p>
            </section>
        </main>
    </body>
</html>
