import { pgSchema, bigserial, uuid, text, jsonb, integer, boolean, uniqueIndex, index } from 'drizzle-orm/pg-core';
import { sql } from 'drizzle-orm';
import { ts } from './types';

/* ---------- журнал дій (append-only, тригер у міграції 0002) ---------- */
export const audit = pgSchema('audit');
export const auditLog = audit.table('audit_log', {
  id: bigserial('id', { mode: 'number' }).primaryKey(),
  at: ts('at').notNull().defaultNow(),
  actorId: uuid('actor_id'),
  action: text('action').notNull(),
  entityType: text('entity_type'),
  entityId: text('entity_id'),
  ip: text('ip'),
  data: jsonb('data').notNull().default({}),
}, (t) => [index('audit_at_idx').on(t.at), index('audit_actor_idx').on(t.actorId)]);

/* ---------- системні налаштування ---------- */
export const settings = pgSchema('settings');
export const systemSettings = settings.table('system_settings', {
  key: text('key').primaryKey(),
  value: jsonb('value').notNull(),
  updatedBy: uuid('updated_by'),
  updatedAt: ts('updated_at').notNull().defaultNow(),
});

/* ---------- теми оформлення ---------- */
export const theming = pgSchema('theming');
export const themes = theming.table('themes', {
  id: uuid('id').primaryKey().defaultRandom(),
  slug: text('slug').notNull(),
  name: text('name').notNull(),
  description: text('description').notNull().default(''),
  isSystem: boolean('is_system').notNull().default(false),
  basedOnId: uuid('based_on_id'),
  defaultMode: text('default_mode').notNull().default('light'),
  draft: jsonb('draft'),
  createdBy: uuid('created_by'),
  createdAt: ts('created_at').notNull().defaultNow(),
  deletedAt: ts('deleted_at'),
}, (t) => [uniqueIndex('themes_slug_uq').on(t.slug)]);

export const themeVersions = theming.table('theme_versions', {
  id: uuid('id').primaryKey().defaultRandom(),
  themeId: uuid('theme_id').notNull().references(() => themes.id, { onDelete: 'restrict' }),
  version: integer('version').notNull(),
  schemaVersion: integer('schema_version').notNull().default(1),
  tokens: jsonb('tokens').notNull(),
  layouts: jsonb('layouts').notNull().default({}),
  checks: jsonb('checks').notNull().default({}),
  comment: text('comment'),
  createdBy: uuid('created_by'),
  createdAt: ts('created_at').notNull().defaultNow(),
}, (t) => [uniqueIndex('theme_versions_uq').on(t.themeId, t.version)]);

export const themePublications = theming.table('theme_publications', {
  id: uuid('id').primaryKey().defaultRandom(),
  themeVersionId: uuid('theme_version_id').notNull().references(() => themeVersions.id, { onDelete: 'restrict' }),
  publishedBy: uuid('published_by'),
  publishedAt: ts('published_at').notNull().defaultNow(),
  isActive: boolean('is_active').notNull(),
}, (t) => [uniqueIndex('one_active_theme').on(t.isActive).where(sql`${t.isActive}`)]);

/* ---------- довідники географії ---------- */
export const reference = pgSchema('reference');
export const regions = reference.table('regions', {
  id: uuid('id').primaryKey().defaultRandom(),
  parentId: uuid('parent_id'),
  kind: text('kind').notNull(), // oblast | raion
  name: text('name').notNull(),
  katottg: text('katottg'),
});
export const communities = reference.table('communities', {
  id: uuid('id').primaryKey().defaultRandom(),
  regionId: uuid('region_id').notNull().references(() => regions.id),
  name: text('name').notNull(),
  katottg: text('katottg'),
  isLaunch: boolean('is_launch').notNull().default(false),
});
export const settlements = reference.table('settlements', {
  id: uuid('id').primaryKey().defaultRandom(),
  communityId: uuid('community_id').notNull().references(() => communities.id),
  name: text('name').notNull(),
  kind: text('kind').notNull().default('city'),
  lat: text('lat'),
  lon: text('lon'),
});
