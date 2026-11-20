import { MiddlewareConsumer, Module, NestModule } from '@nestjs/common';
import { RuntimeConfig } from './config/runtime-config';
import { InstallController, SetupKeyGuard } from './install/install.controller';
import { InstallService } from './install/install.service';
import { AuthController } from './auth/auth.controller';
import { AuthService } from './auth/auth.service';
import { HealthController } from './health/health.controller';
import { CsrfMiddleware } from './common/csrf.middleware';

@Module({
  controllers: [InstallController, AuthController, HealthController],
  providers: [RuntimeConfig, InstallService, AuthService, SetupKeyGuard],
})
export class AppModule implements NestModule {
  configure(c: MiddlewareConsumer) { c.apply(CsrfMiddleware).forRoutes('*splat'); }
}
