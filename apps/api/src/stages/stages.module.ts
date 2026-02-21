import { Module } from '@nestjs/common';
import { StagesService } from './stages.service';
import { StagesController } from './stages.controller';
import { StagesRepository } from './stages.repository';
import { ContractsModule } from '../contracts/contracts.module';

@Module({
  imports: [ContractsModule],
  providers: [StagesService, StagesRepository],
  controllers: [StagesController],
})
export class StagesModule {}
