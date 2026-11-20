import { Body, Controller, Get, Post, Req, Res, HttpCode, UnauthorizedException, BadRequestException } from '@nestjs/common';
import type { Request, Response } from 'express';
import { z } from 'zod';
import { AuthService, parseContact } from './auth.service';
import { Auth, User, SESSION_COOKIE, type CurrentUser } from './session';
import { RuntimeConfig } from '../config/runtime-config';
import { parse } from '../install/install.schemas';

/** Пароль користувача сайту: від 8 символів, літера й цифра. (Для персоналу — 12, див. інсталятор/адмінку.) */
export const userPassword = z.string().min(8, 'Пароль: щонайменше 8 символів').max(200)
  .refine((p) => /\p{L}/u.test(p) && /\d/.test(p), 'Пароль має містити літери та цифри');
const contact = z.string().trim().min(5, 'Вкажіть телефон або email').max(254);
const code = z.string().trim().regex(/^\d{6}$/, 'Код — 6 цифр');

const loginSchema = z.object({ login: z.string().trim().min(1, 'Вкажіть логін, телефон або email').max(254), password: z.string().min(1, 'Вкажіть пароль').max(200) });
const startSchema = z.object({ contact });
const registerSchema = z.object({ contact, code, displayName: z.string().trim().min(2, 'Вкажіть ім’я').max(80), password: userPassword, acceptTerms: z.literal(true, { errorMap: () => ({ message: 'Потрібна згода з правилами' }) }) });
const recoverSchema = z.object({ contact, code, password: userPassword });
const profileSchema = z.object({ displayName: z.string().trim().min(2, 'Вкажіть ім’я').max(80) });
const passwordSchema = z.object({ current: z.string().min(1).max(200), password: userPassword });

function contactOf(raw: string) {
  const c = parseContact(raw);
  if (!c) throw new BadRequestException({ message: 'Вкажіть український номер (067 123 45 67) або email.', fields: { contact: 'Некоректний телефон або email' } });
  return c;
}

@Controller('auth')
export class AuthController {
  constructor(private readonly auth: AuthService, private readonly rc: RuntimeConfig) {}

  private setCookie(res: Response, token: string, ttl: number) {
    const secure = this.rc.config().site.url.startsWith('https://');
    res.cookie(SESSION_COOKIE, token, { httpOnly: true, sameSite: 'lax', secure, maxAge: ttl, path: '/' });
  }

  @Post('login') @HttpCode(200)
  async login(@Body() body: unknown, @Req() req: Request, @Res({ passthrough: true }) res: Response) {
    const { login, password } = parse(loginSchema, body);
    const { token, ttl } = await this.auth.login(login, password, req.ip, req.get('user-agent'));
    this.setCookie(res, token, ttl);
    return { ok: true };
  }

  @Post('register/start') @HttpCode(200)
  registerStart(@Body() body: unknown, @Req() req: Request) {
    return this.auth.registerStart(contactOf(parse(startSchema, body).contact), req.ip);
  }

  @Post('register/finish') @HttpCode(200)
  async registerFinish(@Body() body: unknown, @Req() req: Request, @Res({ passthrough: true }) res: Response) {
    const b = parse(registerSchema, body);
    const { token, ttl } = await this.auth.registerFinish(contactOf(b.contact), b.code, b.displayName, b.password, req.ip, req.get('user-agent'));
    this.setCookie(res, token, ttl);
    return { ok: true };
  }

  @Post('recover/start') @HttpCode(200)
  recoverStart(@Body() body: unknown, @Req() req: Request) {
    return this.auth.recoverStart(contactOf(parse(startSchema, body).contact), req.ip);
  }

  @Post('recover/finish') @HttpCode(200)
  async recoverFinish(@Body() body: unknown, @Req() req: Request, @Res({ passthrough: true }) res: Response) {
    const b = parse(recoverSchema, body);
    const { token, ttl } = await this.auth.recoverFinish(contactOf(b.contact), b.code, b.password, req.ip, req.get('user-agent'));
    this.setCookie(res, token, ttl);
    return { ok: true };
  }

  @Get('me')
  async me(@Req() req: Request) {
    const me = await this.auth.me(req.cookies?.[SESSION_COOKIE]);
    if (!me) throw new UnauthorizedException({ message: 'Потрібно увійти.' });
    return me;
  }

  @Post('profile') @HttpCode(200) @Auth()
  async profile(@Body() body: unknown, @User() u: CurrentUser) {
    await this.auth.updateProfile(u.id, parse(profileSchema, body).displayName);
    return { ok: true };
  }

  @Post('password') @HttpCode(200) @Auth()
  async password(@Body() body: unknown, @User() u: CurrentUser, @Req() req: Request) {
    const b = parse(passwordSchema, body);
    if (u.isStaff && b.password.length < 12) throw new BadRequestException({ message: 'Для адміністраторів пароль — щонайменше 12 символів.', fields: { password: 'Щонайменше 12 символів' } });
    await this.auth.changePassword(u.id, b.current, b.password, u.sessionId, req.ip);
    return { ok: true };
  }

  @Post('logout') @HttpCode(200)
  async logout(@Req() req: Request, @Res({ passthrough: true }) res: Response) {
    await this.auth.logout(req.cookies?.[SESSION_COOKIE], req.ip);
    res.clearCookie(SESSION_COOKIE, { path: '/' });
    return { ok: true };
  }
}
