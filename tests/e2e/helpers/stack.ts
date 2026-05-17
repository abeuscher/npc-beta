// Single source of truth for which Docker stack the e2e harness talks to.
//
// Defaults reproduce the original dev-stack behaviour exactly (default compose
// project, service `app`, Postgres on localhost:5432), so existing local usage
// is unaffected. The isolated e2e stack is opt-in purely via env:
//
//   E2E_COMPOSE_PROJECT=nonprofitcrm_e2e
//   E2E_COMPOSE_FILES=docker-compose.yml,docker-compose.e2e.yml
//   E2E_DB_PORT=5442
//   APP_URL=http://localhost:8090
//
// (APP_URL is already read directly by auth.ts / playwright.config.ts.)

const E2E_COMPOSE_PROJECT = process.env.E2E_COMPOSE_PROJECT ?? '';
const E2E_COMPOSE_FILES = process.env.E2E_COMPOSE_FILES ?? '';
const E2E_APP_SERVICE = process.env.E2E_APP_SERVICE ?? 'app';

export function composeExecArgs(rest: string[]): string[] {
    const args: string[] = ['compose'];
    if (E2E_COMPOSE_PROJECT) {
        args.push('-p', E2E_COMPOSE_PROJECT);
    }
    if (E2E_COMPOSE_FILES) {
        for (const f of E2E_COMPOSE_FILES.split(',').map((s) => s.trim()).filter(Boolean)) {
            args.push('-f', f);
        }
    }
    args.push('exec', '-T', E2E_APP_SERVICE, ...rest);
    return args;
}

export function pgConnectionConfig(): {
    host: string;
    port: number;
    database: string | undefined;
    user: string | undefined;
    password: string | undefined;
} {
    return {
        host: process.env.E2E_DB_HOST ?? 'localhost',
        port: Number(process.env.E2E_DB_PORT ?? 5432),
        database: process.env.DB_DATABASE,
        user: process.env.DB_USERNAME,
        password: process.env.DB_PASSWORD,
    };
}
