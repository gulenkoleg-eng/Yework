import { Body, Controller, Get, Post, Req, Res, HttpCode, UnauthorizedException } from '@nestjs/common';
import type { Request, Response } from 'express';
import { z } from 'zod';
import { AuthService, SESSION_COOKIE, SESSION_TTL_MS } from './auth.service';
import { RuntimeConfig } from '../config/runtime-config';
import { parse } from '../install/install.schemas';

const loginSchema = z.object({ login: z.string().trim().min(1, 'Вкажіть логін').max(254), password: z.string().min(1, 'Вкажіть пароль').max(200) });

@Controller('auth')
export class AuthController {
  constructor(private readonly auth: AuthService, private readonly rc: RuntimeConfig) {}

  @Post('login') @HttpCode(200)
  async login(@Body() body: unknown, @Req() req: Request, @Res({ passthrough: true }) res: Response) {
    const { login, password } = parse(loginSchema, body);
    const { token } = await this.auth.login(login, password, req.ip, req.get('user-agent'));
    const secure = this.rc.config().site.url.startsWith('https://');
    res.cookie(SESSION_COOKIE, token, { httpOnly: true, sameSite: 'lax', secure, maxAge: SESSION_TTL_MS, path: '/' });
    return { ok: true };
  }

  @Get('me')
  async me(@Req() req: Request) {
    const me = await this.auth.me(req.cookies?.[SESSION_COOKIE]);
    if (!me) throw new UnauthorizedException({ message: 'Потрібно увійти.' });
    return me;
  }

  @Post('logout') @HttpCode(200)
  async logout(@Req() req: Request, @Res({ passthrough: true }) res: Response) {
    await this.auth.logout(req.cookies?.[SESSION_COOKIE], req.ip);
    res.clearCookie(SESSION_COOKIE, { path: '/' });
    return { ok: true };
  }
}
