import { pgSchema, uuid, text, integer, index, date, primaryKey } from 'drizzle-orm/pg-core';
import { ts } from './types';
import { identity, users } from './identity';

/** Одноразові коди підтвердження (реєстрація, відновлення доступу, підтвердження контакту). */
export const verificationCodes = identity.table('verification_codes', {
  id: uuid('id').primaryKey().defaultRandom(),
  purpose: text('purpose').notNull(), // register | recover | verify_contact
  channel: text('channel').notNull(), // sms | email
  /** HMAC контакту (номер або email) — сам контакт тут не зберігається. */
  targetHash: text('target_hash').notNull(),
  userId: uuid('user_id').references(() => users.id, { onDelete: 'cascade' }),
  /** HMAC коду: навіть з дампом бази код не відновити. */
  codeHash: text('code_hash').notNull(),
  attempts: integer('attempts').notNull().default(0),
  expiresAt: ts('expires_at').notNull(),
  consumedAt: ts('consumed_at'),
  ip: text('ip'),
  createdAt: ts('created_at').notNull().defaultNow(),
}, (t) => [index('vc_target_idx').on(t.targetHash, t.purpose)]);

export const notify = pgSchema('notify');

/** Черга вихідних повідомлень (SMS / email). Тексти зашифровані: вони можуть містити коди. */
export const outbox = notify.table('outbox', {
  id: uuid('id').primaryKey().defaultRandom(),
  channel: text('channel').notNull(), // sms | email
  driver: text('driver').notNull(),
  toEnc: text('to_enc').notNull(),
  subject: text('subject'),
  bodyEnc: text('body_enc').notNull(),
  template: text('template').notNull(),
  status: text('status').notNull().default('queued'), // queued | sent | failed
  attempts: integer('attempts').notNull().default(0),
  error: text('error'),
  createdAt: ts('created_at').notNull().defaultNow(),
  sentAt: ts('sent_at'),
}, (t) => [index('outbox_status_idx').on(t.status, t.createdAt)]);

export const stats = pgSchema('stats');

/** Унікальні відвідувачі: щоденний анонімний хеш (IP+UA+сіль дня), без персональних даних. */
export const dailyVisitors = stats.table('daily_visitors', {
  day: date('day').notNull(),
  visitorHash: text('visitor_hash').notNull(),
}, (t) => [primaryKey({ columns: [t.day, t.visitorHash] })]);
