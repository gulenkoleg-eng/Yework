import { CanActivate, ExecutionContext, ForbiddenException, Injectable, SetMetadata, UnauthorizedException, createParamDecorator, ServiceUnavailableException } from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import type { Request } from 'express';
import { and, eq, gt, isNull, or, sql } from 'drizzle-orm';
import { schema as s } from '@yework/db';
import { PERMISSIONS } from '@yework/permissions';
import { RuntimeConfig } from '../config/runtime-config';
import { sha256 } from '../common/crypto';

export const SESSION_COOKIE = 'yw_session';
/** Персонал (будь-яка роль) — 12 годин, звичайні користувачі — 30 днів. */
export const STAFF_TTL_MS = 12 * 60 * 60_000;
export const USER_TTL_MS = 30 * 24 * 60 * 60_000;

export interface CurrentUser {
  id: string; login: string; displayName: string; isOwner: boolean; isStaff: boolean;
  sessionId: string; perms: Set<string>; roles: { slug: string; name: string }[];
}

/** Права користувача: ролі (без прострочених) + активні критичні права. Власник має все. */
@Injectable()
export class AccessService {
  constructor(private readonly rc: RuntimeConfig) {}
  db() { if (!this.rc.isInstalled()) throw new ServiceUnavailableException({ message: 'Сайт ще не встановлено.' }); return this.rc.db(); }

  async rolesOf(userId: string) {
    return this.db().select({ slug: s.roles.slug, name: s.roles.name, roleId: s.roles.id }).from(s.userRoles)
      .innerJoin(s.roles, eq(s.roles.id, s.userRoles.roleId))
      .where(and(eq(s.userRoles.userId, userId), isNull(s.roles.deletedAt), or(isNull(s.userRoles.expiresAt), gt(s.userRoles.expiresAt, new Date()))));
  }

  async permsOf(userId: string, isOwner: boolean): Promise<Set<string>> {
    if (isOwner) return new Set(PERMISSIONS.map((p) => p.code));
    const db = this.db();
    const roles = await this.rolesOf(userId);
    const out = new Set<string>();
    if (roles.length) {
      const rows = await db.select({ code: s.rolePermissions.permissionCode }).from(s.rolePermissions)
        .where(sql`${s.rolePermissions.roleId} in ${roles.map((r) => r.roleId)}`);
      rows.forEach((r) => out.add(r.code));
    }
    const crit = await db.select({ code: s.criticalGrants.permissionCode }).from(s.criticalGrants)
      .where(and(eq(s.criticalGrants.userId, userId), isNull(s.criticalGrants.revokedAt), or(isNull(s.criticalGrants.expiresAt), gt(s.criticalGrants.expiresAt, new Date()))));
    crit.forEach((r) => out.add(r.code));
    return out;
  }

  async resolve(token: string | undefined): Promise<CurrentUser | null> {
    if (!token || !this.rc.isInstalled()) return null;
    const db = this.db();
    const [row] = await db.select({ u: s.users, sid: s.sessions.id, seen: s.sessions.lastSeenAt }).from(s.sessions)
      .innerJoin(s.users, eq(s.users.id, s.sessions.userId))
      .where(and(eq(s.sessions.tokenHash, sha256(token)), isNull(s.sessions.revokedAt), gt(s.sessions.expiresAt, new Date()), isNull(s.users.deletedAt)));
    if (!row || row.u.status !== 'active') return null;
    if (Date.now() - row.seen.getTime() > 5 * 60_000) await db.update(s.sessions).set({ lastSeenAt: new Date() }).where(eq(s.sessions.id, row.sid));
    const roles = await this.rolesOf(row.u.id);
    const perms = await this.permsOf(row.u.id, row.u.isOwner);
    return {
      id: row.u.id, login: row.u.login, displayName: row.u.displayName, isOwner: row.u.isOwner,
      isStaff: row.u.isOwner || roles.length > 0, sessionId: row.sid, perms, roles: roles.map(({ slug, name }) => ({ slug, name })),
    };
  }
}

const PERMS_KEY = 'yework:perms';
/** Доступ лише з усіма переліченими правами. Без аргументів — будь-який користувач, що увійшов. */
export const Auth = (...perms: string[]) => SetMetadata(PERMS_KEY, perms);

@Injectable()
export class AuthGuard implements CanActivate {
  constructor(private readonly access: AccessService, private readonly reflector: Reflector) {}
  async canActivate(ctx: ExecutionContext) {
    const need = this.reflector.getAllAndOverride<string[] | undefined>(PERMS_KEY, [ctx.getHandler(), ctx.getClass()]);
    if (need === undefined) return true; // публічний маршрут
    const req = ctx.switchToHttp().getRequest<Request & { user?: CurrentUser }>();
    const user = await this.access.resolve(req.cookies?.[SESSION_COOKIE]);
    if (!user) throw new UnauthorizedException({ message: 'Потрібно увійти.' });
    const missing = need.filter((p) => !user.perms.has(p));
    if (missing.length) throw new ForbiddenException({ message: 'Недостатньо прав для цієї дії.', missing });
    req.user = user;
    return true;
  }
}

export const User = createParamDecorator((_: unknown, ctx: ExecutionContext) => ctx.switchToHttp().getRequest().user as CurrentUser);

/** Маскування контакту для показу: +380 67 *** ** 67, ol***@gmail.com */
export function maskContact(v: string | null): string | null {
  if (!v) return null;
  if (v.includes('@')) { const [a, d] = v.split('@'); return a.slice(0, 2) + '***@' + d; }
  return v.slice(0, 6) + ' ' + v.slice(6, 8) + ' *** ** ' + v.slice(-2);
}
