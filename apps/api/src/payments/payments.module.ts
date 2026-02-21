import { Module } from '@nestjs/common';
import { PaymentsService } from './payments.service';
import { PaymentsController } from './payments.controller';
import { PaymentsRepository } from './payments.repository';
import { ContractsModule } from '../contracts/contracts.module';

@Module({
  imports: [ContractsModule],
  providers: [PaymentsService, PaymentsRepository],
  controllers: [PaymentsController],
})
export class PaymentsModule {}
