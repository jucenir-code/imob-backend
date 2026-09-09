<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Cadastro concluido</title>
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 24px;
                font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                background:
                    radial-gradient(circle at top, rgba(37, 99, 235, 0.12), transparent 30%),
                    linear-gradient(180deg, #eff6ff 0%, #f8fafc 35%);
                color: #0f172a;
            }

            .card {
                width: 100%;
                max-width: 520px;
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 24px;
                padding: 32px;
                box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
                text-align: center;
            }

            .badge {
                display: inline-flex;
                padding: 8px 12px;
                border-radius: 999px;
                background: #dcfce7;
                color: #166534;
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }

            h1 {
                margin: 18px 0 12px;
                font-size: 32px;
            }

            p {
                margin: 0;
                color: #64748b;
                line-height: 1.7;
                font-size: 16px;
            }

            strong {
                color: #0f172a;
            }
        </style>
    </head>
    <body>
        <section class="card">
            <span class="badge">Conta criada</span>
            <h1>Cadastro concluido</h1>
            <p>
                Sua conta foi criada com sucesso
                @if ($registeredEmail !== '')
                    para <strong>{{ $registeredEmail }}</strong>
                @endif
                e ja esta ativa.
            </p>
        <p style="margin-top:24px"><a href="{{ route('login') }}" style="color:#f0006e;font-weight:700">Entrar na versão web →</a></p>
        </section>
    </body>
</html>
