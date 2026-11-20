import { pgSchema, uuid, text, boolean, uniqueIndex, index } from 'drizzle-orm/pg-core';
import { sql } from 'drizzle-orm';
import { citext, ts, lifecycle } from './types';

export const identity = pgSchema('identity');

export const users = identity.table('users', {
  id: uuid('id').primaryKey().defaultRandom(),
  login: citext('login').notNull(),
  displayName: text('display_name').notNull(),
  passwordHash: text('password_hash').notNull(),
  /** HMAC номера для перевірки унікальності; сам номер — лише зашифрований. */
  phoneHash: text('phone_hash'),
  phoneEnc: text('phone_enc'),
  phoneVerifiedAt: ts('phone_verified_at'),
  emailHash: text('email_hash'),
  emailEnc: text('email_enc'),
  emailVerifiedAt: ts('email_verified_at'),
  status: text('status').notNull().default('active'),
  isOwner: boolean('is_owner').notNull().default(false),
  lastLoginAt: ts('last_login_at'),
  ...lifecycle(),
}, (t) => [
  uniqueIndex('users_login_uq').on(t.login),
  uniqueIndex('users_phone_hash_uq').on(t.phoneHash),
  uniqueIndex('users_email_hash_uq').on(t.emailHash),
  /** Власник платформи може бути лише один. */
  uniqueIndex('users_single_owner_uq').on(t.isOwner).where(sql`${t.isOwner}`),
]);

export const sessions = identity.table('sessions', {
  id: uuid('id').primaryKey().defaultRandom(),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  tokenHash: text('token_hash').notNull(),
  ip: text('ip'),
  userAgent: text('user_agent'),
  createdAt: ts('created_at').notNull().defaultNow(),
  lastSeenAt: ts('last_seen_at').notNull().defaultNow(),
  expiresAt: ts('expires_at').notNull(),
  revokedAt: ts('revoked_at'),
}, (t) => [uniqueIndex('sessions_token_uq').on(t.tokenHash), index('sessions_user_idx').on(t.userId)]);

export const mfaFactors = identity.table('mfa_factors', {
  id: uuid('id').primaryKey().defaultRandom(),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  type: text('type').notNull().default('totp'),
  secretEnc: text('secret_enc').notNull(),
  confirmedAt: ts('confirmed_at'),
  createdAt: ts('created_at').notNull().defaultNow(),
});
