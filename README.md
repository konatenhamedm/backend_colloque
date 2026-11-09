# Backend — Plateforme d'inscription (Symfony 7.4 · PostgreSQL · Clean Architecture)

Structure par module (même logique qu'Educ360) : `src/<Module>/{Domain,Application,Infrastructure,UI}`
Modules : Shared (Fichier, ApiInterface), Security (JWT), Colloque, Inscription, Travaux, Attestation, Reporting.

## Démarrer
    createdb -h 127.0.0.1 -U postgres colloque   # PostgreSQL local, URL dans .env (DATABASE_URL)
    composer install
    php bin/console doctrine:migrations:migrate -n
    php bin/console doctrine:fixtures:load -n   # données de démo (dev uniquement)
    php -S 127.0.0.1:8011 -t public             # ou: symfony serve
Doc API : http://127.0.0.1:8011/api/doc — Comptes démo : admin@colloque.local / Admin#2027, comite@colloque.local / Comite#2027
Créer un admin : `php bin/console app:create-admin email mot_de_passe`

## Parcours
1. `POST /api/auth/inscription/demarrer` {email} → e-mail avec lien + jeton (1 h, usage unique)
2. `POST /api/auth/inscription` (multipart : token, password, infos, justificatif) → compte + inscription PENDING_PAYMENT
3. Admin : `/api/admin/paiements/en-attente`, `/valider` (→ reçu PDF joint à l'e-mail), `/rejeter`
4. Fin d'événement : `/api/admin/participations/valider`, `/api/admin/attestations/emettre` (PDF par e-mail)
5. Vérification publique (QR) : `GET /api/public/attestations/{code}`

## Fichiers
Justificatifs et PDF sont **privés** (`var/data`, jamais dans `public/`) ; servis par `GET /api/fichiers/{uuid}` après contrôle des droits.
En dev, le lien d'inscription est écrit dans `var/log/dev.log` (mailer null). Mettre `MAILER_DSN` pour l'envoi réel.

## Tester les e-mails en local (sans Docker)
    brew install mailpit && brew services start mailpit   # SMTP :1025, boîte de réception http://localhost:8025
`MAILER_DSN=smtp://127.0.0.1:1025` est déjà dans `.env` : tous les e-mails (lien d'inscription, reçu PDF, attestation) arrivent dans Mailpit, sans rien envoyer réellement.
Sans Mailpit, l'envoi échoue sans bloquer l'inscription, et le lien d'inscription reste lisible dans `var/log/dev.log`.

## CI / CD
- `.github/workflows/ci.yml` : push sur `main` / PR → Postgres 16 de test, `composer install`, lint (container, Twig, YAML), migrations, `schema:validate`, puis **test de bout en bout** (`tests/e2e/run.py`, ~50 vérifications).
- `.github/workflows/deploy.yml` : push sur la branche **`production`** → contrôle de la config de prod, **sauvegarde de la base**, `git reset` sur le serveur, `composer install --no-dev`, **migrations**, cache, vérification HTTP de https://backforum.instapoint.site.
- Secrets GitHub requis : `APP_HOST`, `APP_USER`, `APP_PASS`.

### Première mise en place du serveur (une seule fois)
1. Cloner le dépôt (branche `production`) dans `/var/www/backforum.instapoint.site/web` ; le vhost Apache pointe sur ce dossier (ou, mieux, sur son sous-dossier `public/`).
2. Créer la base PostgreSQL, puis `.env.local` à partir de `.env.prod.example` (secrets aléatoires : `openssl rand -hex 32`). Ce fichier n'est jamais versionné.
3. Créer le premier admin : `php bin/console app:create-admin email mot_de_passe`.
4. `.htaccess` (racine) verrouille `var/`, `src/`, `config/`, `.env*`… : les justificatifs et attestations (dans `var/data`) ne sont jamais servis directement.

### Tester en local avec `php -S`
Toujours lancer `php -d variables_order=EGPCS -S 127.0.0.1:8011 -t public` : sans cette option, PHP ignore les variables d'environnement (`DATABASE_URL`, `MAILER_DSN`) et Symfony utilise `.env` / `.env.local`.

### Configuration de production sans SSH : secret `ENV_LOCAL`
Dans GitHub (Settings → Secrets → Actions), créer le secret **`ENV_LOCAL`** avec le contenu complet de `.env.prod.example` renseigné. Le déploiement l'écrit dans `.env.local` sur le serveur (permissions 600) avant chaque déploiement, puis refuse de continuer si des secrets de développement sont détectés. Sans ce secret, le `.env.local` déjà présent sur le serveur est conservé.
