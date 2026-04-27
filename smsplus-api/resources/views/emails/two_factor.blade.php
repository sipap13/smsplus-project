<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votre code de connexion SMS+ VAS</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f3f5f7;
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
        }
        .email-wrapper {
            max-width: 480px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }
        .email-header {
            background: linear-gradient(135deg, #1f2f74 0%, #0b66c3 100%);
            padding: 28px 24px;
            text-align: center;
        }
        .email-header img {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            background: #fff;
            padding: 6px;
            object-fit: contain;
            margin-bottom: 10px;
        }
        .email-header h2 {
            margin: 0;
            color: #ffffff;
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .email-header p {
            margin: 6px 0 0;
            color: rgba(255,255,255,0.82);
            font-size: 0.82rem;
            font-weight: 500;
        }
        .email-body {
            padding: 32px 28px;
            color: #1f2937;
        }
        .email-body p {
            margin: 0 0 18px;
            font-size: 0.95rem;
            line-height: 1.6;
            color: #374151;
        }
        .code-label {
            text-align: center;
            font-size: 0.85rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 14px;
        }
        .code-box {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 24px;
        }
        .code-digit {
            width: 48px;
            height: 60px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            font-weight: 700;
            color: #1f2f74;
            letter-spacing: 0.05em;
        }
        .expiry-note {
            text-align: center;
            font-size: 0.85rem;
            color: #dc2626;
            font-weight: 600;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
        .warning-note {
            background: #f8fafc;
            border-left: 4px solid #0b66c3;
            padding: 14px 18px;
            border-radius: 0 10px 10px 0;
            font-size: 0.85rem;
            color: #475569;
            line-height: 1.5;
        }
        .email-footer {
            background: #f8fafc;
            padding: 20px 28px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        .email-footer p {
            margin: 0;
            font-size: 0.78rem;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <img src="{{ asset('tt-logo.png') }}" alt="Tunisie Telecom">
            <h2>SMS+ VAS</h2>
            <p>Tunisie Telecom — Direction Assurance &amp; Fraude</p>
        </div>
        <div class="email-body">
            <p>Bonjour,</p>
            <p>Vous avez demandé à vous connecter à la plateforme <strong>SMS+ VAS</strong>. Utilisez le code ci-dessous pour finaliser votre connexion.</p>

            <div class="code-label">Votre code de connexion</div>
            <div class="code-box">
                @foreach(str_split($code) as $digit)
                    <span class="code-digit">{{ $digit }}</span>
                @endforeach
            </div>

            <div class="expiry-note">
                ⏱ Ce code expire dans <strong>10 minutes</strong>.
            </div>

            <div class="warning-note">
                Si vous n'avez pas demandé cette connexion, veuillez ignorer cet email. Pour toute assistance, contactez l'équipe Direction Assurance &amp; Fraude.
            </div>
        </div>
        <div class="email-footer">
            <p>&copy; {{ date('Y') }} Tunisie Telecom — SMS+ VAS. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>

