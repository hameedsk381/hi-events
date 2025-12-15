---
description: How to deploy Hi.Events on Dokploy
---

1.  **Push your changes to Git**:
    Ensure all recent changes (Razorpay integration, `docker-compose.dokploy.yml`) are committed and pushed to your Git repository (GitHub/GitLab/etc.).

2.  **Access Dokploy**:
    Log in to your Dokploy dashboard.

3.  **Create a New Project/Service**:
    - Go to your project or create a new one.
    - Click **"Compose"** (Deploy via Docker Compose).

4.  **Configure Repository**:
    - Select your **Git Provider**.
    - Choose the **Repository** (`Hi.Events`).
    - Select the **Branch** (e.g., `main` or `master`).

5.  **Set Compose File Path**:
    - In the configuration settings, look for "Docker Compose Path" or similar.
    - Set it to `/docker-compose.dokploy.yml`.
    - If there is no option to specify the file path, you may need to rename `docker-compose.dokploy.yml` to `docker-compose.yml` in your repo, or copy its content into the Dokploy UI if it accepts raw compose config.

6.  **Configure Environment Variables**:
    You need to add the following environment variables in the Dokploy "Environment" tab. Make sure to generate secure keys where needed.

    ```env
    # App Settings
    APP_KEY=base64:YOUR_GENERATED_KEY_HERE (Run `php artisan key:generate --show` locally to get one)
    APP_URL=https://your-domain.com
    VITE_APP_NAME=Hi.Events
    
    # Razorpay Settings (Crucial for Payments)
    RAZORPAY_KEY_ID=your_razorpay_key_id
    RAZORPAY_KEY_SECRET=your_razorpay_key_secret
    RAZORPAY_WEBHOOK_SECRET=your_razorpay_webhook_secret

    # Database (These match the docker-compose defaults, change for production security)
    POSTGRES_DB=hi-events
    POSTGRES_USER=postgres
    POSTGRES_PASSWORD=secret
    
    # Mail Config (Required for signup/verification)
    MAIL_MAILER=smtp
    MAIL_HOST=smtp.mailgun.org
    MAIL_PORT=587
    MAIL_USERNAME=your_username
    MAIL_PASSWORD=your_password
    MAIL_ENCRYPTION=tls
    MAIL_FROM_ADDRESS=hello@your-domain.com
    MAIL_FROM_NAME="${VITE_APP_NAME}"
    
    # Other
    LOG_CHANNEL=stderr
    QUEUE_CONNECTION=redis
    FILESYSTEM_PUBLIC_DISK=local
    FILESYSTEM_PRIVATE_DISK=local
    ```

7.  **Deploy**:
    - Click **"Deploy"**.
    - Monitor the logs to ensure the build completes and services (App, Postgres, Redis) start successfully.

8.  **Post-Deployment Setup**:
    - Once running, access the app at your domain.
    - **Run Migrations**: You might need to shell into the container and run `php artisan migrate`.
      - In Dokploy Console/Shell for the app container: `php artisan migrate --force`
    - **Log In/Sign Up**: Create your admin account.
    - **Enable Payments**: Go to Event Dashboard -> Settings -> Payments -> Enable "Stripe" (This label is still used in the UI, but it now powers the Razorpay integration we built).
