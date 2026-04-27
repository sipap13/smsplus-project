# Configuration Email pour la 2FA — SMS+ VAS

## Statut actuel

Le driver `log` est activé par défaut. Les codes 2FA sont générés et **visibles dans les logs** du conteneur API (`smsplus-api/storage/logs/laravel.log`) mais **aucun email réel n'est envoyé**.

Pour envoyer des vrais emails sur Gmail, suivez les étapes ci-dessous.

---

## Pourquoi Gmail refuse le mot de passe normal

Google a désactivé l'authentification par "mot de passe de compte" pour les applications tierces. Il faut utiliser un **App Password** (mot de passe d'application).

---

## Étapes d'activation Gmail SMTP

### 1. Activer la 2FA sur votre compte Google

1. Connectez-vous à https://myaccount.google.com/
2. Allez dans **Sécurité** → **Vérification en deux étapes** → Activez-la
3. Suivez les instructions (téléphone, SMS, code)

### 2. Générer un App Password

1. Une fois la 2FA activée, retournez dans **Sécurité**
2. Cherchez **Mots de passe d'application** (ou https://myaccount.google.com/apppasswords)
3. Sélectionnez **Messagerie** → **Autre (nom personnalisé)**
4. Nommez-le : `SMS+ VAS 2FA`
5. Google génère un mot de passe de 16 caractères (ex: `abcd efgh ijkl mnop`) — **copiez-le immédiatement**

### 3. Configurer docker-compose.yml

Dans `docker-compose.yml`, section `api` → `environment`, remplacez :

```yaml
      # Option 1: LOG (développement)
      MAIL_MAILER: log
      # Option 2: SMTP Gmail — voir EMAIL_SETUP.md
```

Par :

```yaml
      # Option 2: SMTP Gmail — ACTIVÉ
      MAIL_MAILER: smtp
      MAIL_HOST: smtp.gmail.com
      MAIL_PORT: 587
      MAIL_USERNAME: bouaoun.melek@gmail.com
      MAIL_PASSWORD: VOTRE_APP_PASSWORD_GMAIL_SANS_ESPACES
      MAIL_ENCRYPTION: tls
```

⚠️ **Important** : `MAIL_PASSWORD` doit être l'**App Password** généré à l'étape 2, pas le mot de passe de votre compte Google.

### 4. Redémarrer l'API

```powershell
docker compose down api
docker compose up api -d
```

### 5. Vérifier

Faites un login et vérifiez votre boîte mail sur https://mail.google.com/

---

## Debug

Pour voir les emails en mode LOG (sans SMTP) :

```powershell
# Voir les derniers logs
docker exec smsplus-project-api-1 sh -c "tail -100 /app/storage/logs/laravel.log"

# Chercher un code spécifique
docker exec smsplus-project-api-1 sh -c "grep -E 'code-digit|two_fa_code' /app/storage/logs/laravel.log | tail -20"
```

---

## Option SMS (+216)

Le backend est prêt pour l'envoi SMS. Pour l'activer :

1. Mettez `two_fa_method = 'sms'` ou `'both'` dans `ra_t_users`
2. Assurez-vous que `numero_personnel` est renseigné (format +216...)
3. Implémentez un provider SMS (Twilio, SMSP, etc.) dans `AuthController::sendSms()`

---

## Sécurité

- **Ne jamais commiter** les credentials email dans Git
- Utilisez toujours des variables d'environnement ou un `.env` local
- Changez l'App Password Gmail régulièrement
- Le driver `log` est recommandé pour le développement local

