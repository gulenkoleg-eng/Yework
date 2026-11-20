import path from 'node:path';
import { Pool, type PoolConfig } from 'pg';
import { drizzle, type NodePgDatabase } from 'drizzle-orm/node-postgres';
import { migrate } from 'drizzle-orm/node-postgres/migrator';
import * as schema from './schema';

export * as schema from './schema';
export * from './seed';
export type YeworkDb = NodePgDatabase<typeof schema>;

export function createDb(config: PoolConfig): { pool: Pool; db: YeworkDb } {
  const pool = new Pool({ max: 10, ...config });
  return { pool, db: drizzle(pool, { schema }) };
}

/** Папка з SQL-міграціями (поруч із dist). */
export const MIGRATIONS_DIR = path.resolve(__dirname, '..', 'migrations');

/** Застосовує всі нові міграції. Кожна міграція виконується в транзакції. */
export async function runMigrations(db: YeworkDb) {
  await migrate(db, { migrationsFolder: MIGRATIONS_DIR, migrationsSchema: 'meta', migrationsTable: 'schema_migrations' });
}
