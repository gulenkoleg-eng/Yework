import { Body, Controller, Get, Post, Req, HttpCode, GoneException, ForbiddenException, Injectable, CanActivate, ExecutionContext, UseGuards } from '@nestjs/common';
import type { Request } from 'express';
import { RuntimeConfig } from '../config/runtime-config';
import { RateLimiter } from '../common/rate-limit';
import { safeEqual } from '../common/crypto';
import { InstallService } from './install.service';
import { dbSchema, runSchema, parse, PASSWORD_MIN } from './install.schemas';

const keyLimiter = new RateLimiter(10, 15 * 60_000);

/** Інсталятор доступний лише до встановлення і лише з правильним одноразовим ключем. */
@Injectable()
export class SetupKeyGuard implements CanActivate {
  constructor(private readonly rc: RuntimeConfig) {}
  canActivate(ctx: ExecutionContext): boolean {
    const req = ctx.switchToHttp().getRequest<Request>();
    if (this.rc.isInstalled()) throw new GoneException({ message: 'YEWORK уже встановлено. Інсталятор вимкнено.' });
    const expected = this.rc.setupKey();
    const given = String(req.get('x-setup-key') ?? '').trim().toUpperCase();
    if (!expected || !given || !safeEqual(given, expected)) {
      keyLimiter.hit('key:' + req.ip, 'Забагато невдалих спроб ключа. Зачекайте 15 хвилин.');
      throw new ForbiddenException({ message: 'Невірний ключ встановлення. Його видно в журналі сервера або у файлі config/setup-key.txt.' });
    }
    return true;
  }
}

@Controller('install')
export class InstallController {
  constructor(private readonly rc: RuntimeConfig, private readonly svc: InstallService) {}

  /** Публічний: чи потрібне встановлення. Нічого секретного не повертає. */
  @Get('status')
  status() { return { installed: this.rc.isInstalled(), passwordMin: PASSWORD_MIN }; }

  @Post('verify-key') @HttpCode(200) @UseGuards(SetupKeyGuard)
  verifyKey() { return { ok: true, requirements: this.svc.requirements() }; }

  @Post('check-db') @HttpCode(200) @UseGuards(SetupKeyGuard)
  checkDb(@Body() body: unknown) { return this.svc.checkDb(parse(dbSchema, body)); }

  @Post('run') @HttpCode(200) @UseGuards(SetupKeyGuard)
  run(@Body() body: unknown, @Req() req: Request) { return this.svc.run(parse(runSchema, body), req.ip); }
}
