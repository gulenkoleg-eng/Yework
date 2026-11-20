import { Injectable, UnauthorizedException, ServiceUnavailableException } from '@nestjs/common';
import { and, eq, gt, isNull, sql } from 'drizzle-orm';
import { schema as s } from '@yework/db';
import { RuntimeConfig } from '../config/runtime-config';
import { verifyPassword, randomToken, sha256 } from '../common/crypto';
import { RateLimiter } from '../common/rate-limit';

export const SESSION_COOKIE = 'yw_session';
export const SESSION_TTL_MS = 12 * 60 * 60_000; // 12 годин для адмінів
const loginByIp = new RateLimiter(20, 15 * 60_000);
const loginByAccount = new RateLimiter(5, 15 * 60_000);
/** Хеш-заглушка, щоб час відповіді не видавав, чи існує логін. */
const DUMMY = '$argon2id$v=19$m=19456,t=2,p=1$c29tZXNhbHRzb21lc2FsdA$0n3d6pJ8Jx8V0bq8Xm0Qq4mJ4b1a1vYcJbGkqzv5e0Y';

@Injectable()
export class AuthService {
  constructor(private readonly rc: RuntimeConfig) {}
  private db() { if (!this.rc.isInstalled()) throw new ServiceUnavailableException({ message: 'Сайт ще не встановлено.' }); return this.rc.db(); }

  async login(login: string, password: string, ip?: string, ua?: string) {
    const db = this.db();
    loginByIp.hit('ip:' + ip, 'Забагато спроб входу з цієї адреси. Зачекайте 15 хвилин.');
    loginByAccount.hit('acc:' + login.toLowerCase(), 'Забагато спроб входу в цей акаунт. Зачекайте 15 хвилин.');
    const [u] = await db.select().from(s.users).where(and(sql`${s.users.login} = ${login}`, isNull(s.users.deletedAt)));
    const ok = await verifyPassword(u?.passwordHash ?? DUMMY, password);
    if (!u || !ok || u.status !== 'active') {
      await db.insert(s.auditLog).values({ actorId: u?.id ?? null, action: 'auth.login_failed', ip, data: { login } });
      throw new UnauthorizedException({ message: 'Невірний логін або пароль.' });
    }
    loginByAccount.reset('acc:' + login.toLowerCase());
    const token = randomToken();
    await db.insert(s.sessions).values({ userId: u.id, tokenHash: sha256(token), ip, userAgent: ua?.slice(0, 300), expiresAt: new Date(Date.now() + SESSION_TTL_MS) });
    await db.update(s.users).set({ lastLoginAt: new Date() }).where(eq(s.users.id, u.id));
    await db.insert(s.auditLog).values({ actorId: u.id, action: 'auth.login', ip });
    return { token };
  }

  async me(token: string | undefined) {
    if (!token) return null;
    const db = this.db();
    const rows = await db.select({ u: s.users, sid: s.sessions.id }).from(s.sessions)
      .innerJoin(s.users, eq(s.users.id, s.sessions.userId))
      .where(and(eq(s.sessions.tokenHash, sha256(token)), isNull(s.sessions.revokedAt), gt(s.sessions.expiresAt, new Date())));
    if (!rows.length) return null;
    const { u } = rows[0];
    const roles = await db.select({ slug: s.roles.slug, name: s.roles.name }).from(s.userRoles).innerJoin(s.roles, eq(s.roles.id, s.userRoles.roleId)).where(eq(s.userRoles.userId, u.id));
    const mfa = await db.select().from(s.mfaFactors).where(and(eq(s.mfaFactors.userId, u.id), sql`${s.mfaFactors.confirmedAt} is not null`));
    const site = await db.select().from(s.systemSettings).where(eq(s.systemSettings.key, 'site.name'));
    return { id: u.id, login: u.login, displayName: u.displayName, isOwner: u.isOwner, roles, mfaConfigured: mfa.length > 0, mfaRequired: true, siteName: site[0]?.value ?? 'YEWORK' };
  }

  async logout(token: string | undefined, ip?: string) {
    if (!token) return;
    const db = this.db();
    const [row] = await db.update(s.sessions).set({ revokedAt: new Date() }).where(eq(s.sessions.tokenHash, sha256(token))).returning();
    if (row) await db.insert(s.auditLog).values({ actorId: row.userId, action: 'auth.logout', ip });
  }
}
