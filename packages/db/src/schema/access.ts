import { pgSchema, uuid, text, boolean, primaryKey, uniqueIndex, index } from 'drizzle-orm/pg-core';
import { ts } from './types';
import { users } from './identity';

export const access = pgSchema('access');

export const permissions = access.table('permissions', {
  code: text('code').primaryKey(),
  module: text('module').notNull(),
  resource: text('resource').notNull(),
  action: text('action').notNull(),
  isCritical: boolean('is_critical').notNull().default(false),
  description: text('description').notNull(),
});

export const roles = access.table('roles', {
  id: uuid('id').primaryKey().defaultRandom(),
  slug: text('slug').notNull(),
  name: text('name').notNull(),
  description: text('description').notNull().default(''),
  isSystem: boolean('is_system').notNull().default(false),
  createdAt: ts('created_at').notNull().defaultNow(),
  deletedAt: ts('deleted_at'),
}, (t) => [uniqueIndex('roles_slug_uq').on(t.slug)]);

export const rolePermissions = access.table('role_permissions', {
  roleId: uuid('role_id').notNull().references(() => roles.id, { onDelete: 'cascade' }),
  permissionCode: text('permission_code').notNull().references(() => permissions.code, { onDelete: 'restrict' }),
}, (t) => [primaryKey({ columns: [t.roleId, t.permissionCode] })]);

export const userRoles = access.table('user_roles', {
  id: uuid('id').primaryKey().defaultRandom(),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'restrict' }),
  roleId: uuid('role_id').notNull().references(() => roles.id, { onDelete: 'restrict' }),
  grantedBy: uuid('granted_by').references(() => users.id),
  grantedAt: ts('granted_at').notNull().defaultNow(),
  expiresAt: ts('expires_at'),
}, (t) => [uniqueIndex('user_roles_uq').on(t.userId, t.roleId)]);

/** Немає рядків = роль діє глобально. Кілька рядків одного типу = АБО, різних типів = І. */
export const roleAssignmentScopes = access.table('role_assignment_scopes', {
  id: uuid('id').primaryKey().defaultRandom(),
  userRoleId: uuid('user_role_id').notNull().references(() => userRoles.id, { onDelete: 'cascade' }),
  scopeType: text('scope_type').notNull(), // region | category | organization | direction
  scopeId: text('scope_id').notNull(),
}, (t) => [index('ras_user_role_idx').on(t.userRoleId)]);

export const criticalGrants = access.table('critical_grants', {
  id: uuid('id').primaryKey().defaultRandom(),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'restrict' }),
  permissionCode: text('permission_code').notNull().references(() => permissions.code),
  grantedBy: uuid('granted_by').references(() => users.id),
  reason: text('reason').notNull(),
  grantedAt: ts('granted_at').notNull().defaultNow(),
  expiresAt: ts('expires_at'),
  revokedAt: ts('revoked_at'),
}, (t) => [index('critical_grants_user_idx').on(t.userId)]);
