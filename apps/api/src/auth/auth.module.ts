import { Module } from '@nestjs/common';
import { JwtModule } from '@nestjs/jwt';
import { PassportModule } from '@nestjs/passport';
import { AuthService } from './auth.service';
import { AuthController } from './auth.controller';
import { AuthRepository } from './auth.repository';
import { JwtAccessStrategy } from './strategies/jwt-access.strategy';

@Module({
  imports: [
    PassportModule,
    JwtModule.register({}), // секреты передаются per-call в AuthService
  ],
  providers: [AuthService, AuthRepository, JwtAccessStrategy],
  controllers: [AuthController],
  exports: [JwtModule, JwtAccessStrategy],
})
export class AuthModule {}
