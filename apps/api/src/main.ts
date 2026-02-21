import { NestFactory } from '@nestjs/core';
import { ValidationPipe, VersioningType } from '@nestjs/common';
import { DocumentBuilder, SwaggerModule } from '@nestjs/swagger';
import { AppModule } from './app.module';

async function bootstrap() {
  const app = await NestFactory.create(AppModule);

  app.setGlobalPrefix('api');
  app.enableVersioning({ type: VersioningType.URI, defaultVersion: '1' });

  app.useGlobalPipes(
    new ValidationPipe({
      whitelist: true,
      forbidNonWhitelisted: true,
      transform: true,
    }),
  );

  app.enableCors({
    origin: process.env.CORS_ORIGINS?.split(',') ?? ['http://localhost:3000'],
    credentials: true,
  });

  const swaggerConfig = new DocumentBuilder()
    .setTitle('Contract Tracker API')
    .setDescription('API для системы контроля жизненного цикла контрактов (223-ФЗ / 44-ФЗ)')
    .setVersion('1.0')
    .addBearerAuth()
    .build();
  const doc = SwaggerModule.createDocument(app, swaggerConfig);
  SwaggerModule.setup('api/docs', app, doc);

  const port = Number(process.env.API_PORT) || 4000;
  await app.listen(port);
  console.log(`API запущен: http://localhost:${port}/api/v1`);
  console.log(`Swagger: http://localhost:${port}/api/docs`);
}

bootstrap();
