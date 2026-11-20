import { defineConfig } from 'drizzle-kit';
export default defineConfig({
  dialect: 'postgresql',
  schema: './src/schema/index.ts',
  out: './migrations',
  schemaFilter: ['identity', 'access', 'audit', 'settings', 'theming', 'reference', 'notify', 'stats', 'jobs'],
});
