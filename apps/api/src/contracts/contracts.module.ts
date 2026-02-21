import { Module } from '@nestjs/common';
import { ContractsService } from './contracts.service';
import { ContractsController } from './contracts.controller';
import { ContractsRepository } from './contracts.repository';

@Module({
  providers: [ContractsService, ContractsRepository],
  controllers: [ContractsController],
  exports: [ContractsRepository],
})
export class ContractsModule {}
