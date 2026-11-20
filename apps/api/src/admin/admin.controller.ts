import { BadRequestException, Body, Controller, Delete, ForbiddenException, Get, HttpCode, NotFoundException, Param, ParseUUIDPipe, Post, Query, Req } from '@nestjs/common';
import type { Request } from 'express';
import { and, desc, eq, ilike, inArray, isNull, or, sql } from 'drizzle-orm';
import { z } from 'zod';
import { schema as s } from '@yework/db';
import { PERMISSIONS } from '@yework/permissions';
import { RuntimeConfig } from '../config/runtime-config';
import { AccessService, Auth, User, maskContact, type CurrentUser } from '../auth/session';
import { parseContact } from '../auth/auth.service';
import { decrypt, hmac } from '../common/crypto';
import { parse } from '../install/install.schemas';
import { MessagingService } from '../messaging/messaging.service';

const PAGE = 25;
const statusSchema = z.object({ status: z.enum(['active', 'blocked']), reason: z.string().trim().min(3, 'Вкажіть причину').max(300) });
const roleSchema = z.object({ slug: z.string().trim().min(2).max(64) });
const emailSchema = z.discriminatedUnion('driver', [
  z.object({ driver: z.literal('log') }),
  z.object({
    driver: z.literal('smtp'), host: z.string().trim().min(3).max(255), port: z.coerce.number().int().min(1).max(65535),
    secure: z.boolean(), user: z.string().trim().max(254), pass: z.string().max(500).optional(), from: z.string().trim().min(3).max(254),
  }),
]);

@Controller('admin')
export class AdminController {
  constructor(private readonly rc: RuntimeConfig, private readonly access: AccessService, private readonly msg: MessagingService) {}
  private db() { return this.access.db(); }

  @Get('dashboard') @Auth()
  async dashboard(@User() u: CurrentUser) {
    if (!u.isStaff) throw new ForbiddenException({ message: 'Лише для персоналу.' });
    const db = this.db();
    const one = async (q: any) => ((await db.execute(q)).rows[0] as any)?.n ?? 0;
    return {
      users: await one(sql`select count(*)::int n from identity.users where deleted_at is null`),
      usersToday: await one(sql`select count(*)::int n from identity.users where created_at >= date_trunc('day', now())`),
      vacanciesPublished: await one(sql`select count(*)::int n from jobs.vacancies where status='published' and deleted_at is null`),
      vacanciesPending: await one(sql`select count(*)::int n from jobs.vacancies where status='pending' and deleted_at is null`),
      employers: await one(sql`select count(*)::int n from jobs.employers where deleted_at is null`),
      applications: await one(sql`select count(*)::int n from jobs.applications`),
    };
  }

  /* ---------------- користувачі ---------------- */

  @Get('users') @Auth('identity.user:view')
  async users(@Query('q') q = '', @Query('page') page = '1', @Query('filter') filter = '') {
    const db = this.db();
    const p = Math.max(1, parseInt(page, 10) || 1);
    const conds = [isNull(s.users.deletedAt)];
    const term = q.trim().slice(0, 100);
    if (term) {
      const c = parseContact(term);
      if (c) conds.push(eq(c.kind === 'phone' ? s.users.phoneHash : s.users.emailHash, hmac(this.rc.config().appSecret, c.value)));
      else conds.push(or(ilike(s.users.displayName, `%${term}%`), ilike(sql`${s.users.login}::text`, `%${term}%`))!);
    }
    if (filter === 'blocked') conds.push(eq(s.users.status, 'blocked'));
    if (filter === 'staff') conds.push(or(eq(s.users.isOwner, true), sql`exists (select 1 from access.user_roles ur where ur.user_id = ${s.users.id})`)!);
    const where = and(...conds);
    const [{ n }] = await db.select({ n: sql<number>`count(*)::int` }).from(s.users).where(where);
    const rows = await db.select().from(s.users).where(where).orderBy(desc(s.users.createdAt)).limit(PAGE).offset((p - 1) * PAGE);
    const ids = rows.map((r) => r.id);
    const roles = ids.length ? await db.select({ userId: s.userRoles.userId, slug: s.roles.slug, name: s.roles.name }).from(s.userRoles).innerJoin(s.roles, eq(s.roles.id, s.userRoles.roleId)).where(inArray(s.userRoles.userId, ids)) : [];
    return {
      total: n, page: p, pageSize: PAGE,
      items: rows.map((r) => ({
        id: r.id, login: r.login, displayName: r.displayName, status: r.status, isOwner: r.isOwner,
        hasPhone: !!r.phoneHash, hasEmail: !!r.emailHash, createdAt: r.createdAt, lastLoginAt: r.lastLoginAt,
        roles: roles.filter((x) => x.userId === r.id).map(({ slug, name }) => ({ slug, name })),
      })),
    };
  }

  @Get('users/:id') @Auth('identity.user:view')
  async user(@Param('id', ParseUUIDPipe) id: string, @User() me: CurrentUser) {
    const db = this.db();
    const [u] = await db.select().from(s.users).where(eq(s.users.id, id));
    if (!u) throw new NotFoundException({ message: 'Користувача не знайдено.' });
    const key = this.rc.config().dataKey;
    const full = me.perms.has('identity.user:view_contacts');
    const phone = u.phoneEnc ? decrypt(key, u.phoneEnc) : null;
    const email = u.emailEnc ? decrypt(key, u.emailEnc) : null;
    const roles = await this.access.rolesOf(id);
    const audit = await db.select().from(s.auditLog).where(or(eq(s.auditLog.actorId, id), and(eq(s.auditLog.entityType, 'user'), eq(s.auditLog.entityId, id)))).orderBy(desc(s.auditLog.createdAt)).limit(15);
    const employers = await db.select({ id: s.employers.id, name: s.employers.name }).from(s.employers).where(eq(s.employers.ownerId, id));
    return {
      id: u.id, login: u.login, displayName: u.displayName, status: u.status, isOwner: u.isOwner, createdAt: u.createdAt, lastLoginAt: u.lastLoginAt,
      phone: full ? phone : maskContact(phone), email: full ? email : maskContact(email), contactsMasked: !full,
      phoneVerified: !!u.phoneVerifiedAt, emailVerified: !!u.emailVerifiedAt,
      roles: roles.map(({ slug, name }) => ({ slug, name })), employers,
      audit: audit.map((a) => ({ action: a.action, at: a.createdAt, ip: a.ip, byThisUser: a.actorId === id })),
    };
  }

  @Post('users/:id/status') @HttpCode(200) @Auth('identity.user:update')
  async setStatus(@Param('id', ParseUUIDPipe) id: string, @Body() body: unknown, @User() me: CurrentUser, @Req() req: Request) {
    const b = parse(statusSchema, body);
    if (id === me.id) throw new BadRequestException({ message: 'Не можна змінити статус власного акаунта.' });
    const db = this.db();
    const [u] = await db.select().from(s.users).where(eq(s.users.id, id));
    if (!u) throw new NotFoundException({ message: 'Користувача не знайдено.' });
    if (u.isOwner) throw new ForbiddenException({ message: 'Головного адміністратора заблокувати не можна.' });
    if (!me.isOwner && (await this.access.rolesOf(id)).length) throw new ForbiddenException({ message: 'Блокувати персонал може лише головний адміністратор.' });
    await db.update(s.users).set({ status: b.status, updatedAt: new Date() }).where(eq(s.users.id, id));
    if (b.status === 'blocked') await db.update(s.sessions).set({ revokedAt: new Date() }).where(and(eq(s.sessions.userId, id), isNull(s.sessions.revokedAt)));
    await db.insert(s.auditLog).values({ actorId: me.id, action: b.status === 'blocked' ? 'user.blocked' : 'user.unblocked', entityType: 'user', entityId: id, ip: req.ip, data: { reason: b.reason } });
    return { ok: true };
  }

  /* ---------------- ролі ---------------- */

  @Get('roles') @Auth('access.role:view')
  async roles() {
    const db = this.db();
    const roles = await db.select().from(s.roles).where(isNull(s.roles.deletedAt)).orderBy(s.roles.createdAt);
    const rp = await db.select().from(s.rolePermissions);
    const counts = await db.select({ roleId: s.userRoles.roleId, n: sql<number>`count(*)::int` }).from(s.userRoles).groupBy(s.userRoles.roleId);
    return {
      permissions: PERMISSIONS.map((p) => ({ code: p.code, module: p.module, description: p.description, critical: p.critical })),
      roles: roles.map((r) => ({
        slug: r.slug, name: r.name, description: r.description, isSystem: r.isSystem,
        permissions: r.slug === 'owner' ? ['*'] : rp.filter((x) => x.roleId === r.id).map((x) => x.permissionCode),
        members: counts.find((c) => c.roleId === r.id)?.n ?? 0,
      })),
    };
  }

  /** Призначення ролі. Критичне право; власну роль змінити не можна; роль «owner» не видається. */
  @Post('users/:id/roles') @HttpCode(200) @Auth('access.role:manage_settings')
  async grantRole(@Param('id', ParseUUIDPipe) id: string, @Body() body: unknown, @User() me: CurrentUser, @Req() req: Request) {
    const { slug } = parse(roleSchema, body);
    if (id === me.id) throw new ForbiddenException({ message: 'Не можна змінювати ролі самому собі.' });
    if (slug === 'owner') throw new ForbiddenException({ message: 'Роль головного адміністратора не передається.' });
    const db = this.db();
    const [role] = await db.select().from(s.roles).where(and(eq(s.roles.slug, slug), isNull(s.roles.deletedAt)));
    if (!role) throw new NotFoundException({ message: 'Роль не знайдено.' });
    const [u] = await db.select().from(s.users).where(eq(s.users.id, id));
    if (!u || u.deletedAt) throw new NotFoundException({ message: 'Користувача не знайдено.' });
    if (u.status !== 'active') throw new BadRequestException({ message: 'Спочатку розблокуйте користувача.' });
    await db.insert(s.userRoles).values({ userId: id, roleId: role.id, grantedBy: me.id }).onConflictDoNothing();
    // Персонал отримує короткі сесії: просимо увійти заново.
    await db.update(s.sessions).set({ revokedAt: new Date() }).where(and(eq(s.sessions.userId, id), isNull(s.sessions.revokedAt)));
    await db.insert(s.auditLog).values({ actorId: me.id, action: 'access.role_granted', entityType: 'user', entityId: id, ip: req.ip, data: { role: slug } });
    return { ok: true };
  }

  @Delete('users/:id/roles/:slug') @Auth('access.role:manage_settings')
  async revokeRole(@Param('id', ParseUUIDPipe) id: string, @Param('slug') slug: string, @User() me: CurrentUser, @Req() req: Request) {
    if (id === me.id) throw new ForbiddenException({ message: 'Не можна змінювати ролі самому собі.' });
    if (slug === 'owner') throw new ForbiddenException({ message: 'Роль головного адміністратора не знімається.' });
    const db = this.db();
    const [role] = await db.select().from(s.roles).where(eq(s.roles.slug, slug));
    if (!role) throw new NotFoundException({ message: 'Роль не знайдено.' });
    await db.delete(s.userRoles).where(and(eq(s.userRoles.userId, id), eq(s.userRoles.roleId, role.id)));
    await db.update(s.sessions).set({ revokedAt: new Date() }).where(and(eq(s.sessions.userId, id), isNull(s.sessions.revokedAt)));
    await db.insert(s.auditLog).values({ actorId: me.id, action: 'access.role_revoked', entityType: 'user', entityId: id, ip: req.ip, data: { role: slug } });
    return { ok: true };
  }

  /* ---------------- журнал дій ---------------- */

  @Get('audit') @Auth('audit.log:view')
  async audit(@Query('page') page = '1', @Query('action') action = '') {
    const db = this.db();
    const p = Math.max(1, parseInt(page, 10) || 1);
    const where = action ? ilike(s.auditLog.action, `${action.replace(/[%_]/g, '')}%`) : undefined;
    const rows = await db.select({ a: s.auditLog, login: s.users.login, name: s.users.displayName }).from(s.auditLog)
      .leftJoin(s.users, eq(s.users.id, s.auditLog.actorId)).where(where).orderBy(desc(s.auditLog.createdAt)).limit(50).offset((p - 1) * 50);
    return { page: p, items: rows.map(({ a, login, name }) => ({ id: a.id, at: a.createdAt, action: a.action, actor: login ? { login, name } : null, entityType: a.entityType, entityId: a.entityId, ip: a.ip, data: a.data })) };
  }

  /* ---------------- налаштування повідомлень ---------------- */

  @Get('settings/messaging') @Auth('settings.system:manage_settings')
  async messaging() {
    const [r] = await this.db().select().from(s.systemSettings).where(eq(s.systemSettings.key, 'messaging.email'));
    const v = (r?.value as any) ?? { driver: 'log' };
    return { sms: { driver: 'log' }, email: { ...v, passEnc: undefined, hasPassword: !!v.passEnc } };
  }

  @Post('settings/messaging') @HttpCode(200) @Auth('settings.system:manage_settings')
  async saveMessaging(@Body() body: unknown, @User() me: CurrentUser, @Req() req: Request) {
    const v = parse(emailSchema, (body as any)?.email);
    await this.msg.saveEmailSettings(v as any, me.id);
    await this.db().insert(s.auditLog).values({ actorId: me.id, action: 'settings.messaging_updated', ip: req.ip, data: { driver: v.driver } });
    return { ok: true };
  }

  @Post('settings/messaging/test') @HttpCode(200) @Auth('settings.system:manage_settings')
  async testMessaging(@Body() body: unknown) {
    const { to } = parse(z.object({ to: z.string().trim().email('Вкажіть email') }), body);
    const r = await this.msg.send({ channel: 'email', to, subject: 'YEWORK: тестовий лист', text: 'Якщо ви бачите цей лист, пошта налаштована правильно.', template: 'test' });
    if (!r.ok) throw new BadRequestException({ message: 'Лист не надіслано. Перевірте налаштування SMTP (деталі в журналі сервера).' });
    return { ok: true, driver: r.driver };
  }
}
