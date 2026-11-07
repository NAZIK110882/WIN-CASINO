# Free Render deployment

This repository includes a Render Blueprint at `render.yaml`. It creates one free Docker web service that serves both the PHP API and the frontend, plus a free Render PostgreSQL database. Keeping the UI and API on one service means the browser uses the same-origin `/api` URL and does not need a separate CORS setup.

## Deploy

1. Push this branch to a GitHub repository that Render can read. For a repository you do not own, Render can connect it as a public Git repository; that does not enable automatic deploys.
2. In Render, choose **New → Blueprint**, then connect the repository and select the branch containing `render.yaml`.
3. Review the resources. Both the web service and database are set to the Free plan.
4. When prompted for `SEED_PLAYER_PASSWORD` and `SEED_ADMIN_PASSWORD`, enter two passwords and save them somewhere safe. The player account is for the demo; the admin account is for the admin endpoints.
5. Apply the Blueprint and wait for the web service health check to pass. Open the `win-casino` service URL.
6. Log in as `player1` with `SEED_PLAYER_PASSWORD` to demonstrate games. The startup script runs migrations and seeds the accounts.

If Render does not prompt for the two `sync: false` values during Blueprint creation, open the web service's **Environment** page, add both variables, and redeploy.

## Free-plan limitation

Render Free Postgres databases expire 30 days after creation. They are suitable for a course demo, but this deployment is not permanent; download or recreate data before expiration. Free web services can spin down while idle, so the first page load after inactivity may take a while. See [Render's free instance limits](https://render.com/docs/free).

## Database support

Local Docker Compose continues to use MySQL. When `DATABASE_URL` is set, the backend connects to Render PostgreSQL instead. `scripts/migrate.php` creates the PostgreSQL schema, and the application uses PostgreSQL-compatible seed, history, and identity retrieval queries. The Docker image includes both PDO drivers.
