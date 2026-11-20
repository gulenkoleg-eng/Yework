import { customType, timestamp, integer } from 'drizzle-orm/pg-core';
/** Логін без урахування регістру (розширення citext). */
export const citext = customType<{ data: string }>({ dataType: () => 'citext' });
export const ts = (name: string) => timestamp(name, { withTimezone: true, mode: 'date' });
/** Стандартні службові поля з архітектури (розділ 5). */
export const lifecycle = () => ({
  createdAt: ts('created_at').notNull().defaultNow(),
  updatedAt: ts('updated_at').notNull().defaultNow(),
  deletedAt: ts('deleted_at'),
  version: integer('version').notNull().default(1),
});
