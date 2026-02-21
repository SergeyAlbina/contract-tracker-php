import { Module } from '@nestjs/common';
import { ProcurementsService } from './procurements.service';
import { ProcurementsController } from './procurements.controller';
import { ProcurementsRepository } from './procurements.repository';
import { ProposalsService } from './proposals.service';
import { ProposalsController } from './proposals.controller';
import { ProposalsRepository } from './proposals.repository';

@Module({
  providers: [
    ProcurementsService,
    ProcurementsRepository,
    ProposalsService,
    ProposalsRepository,
  ],
  controllers: [ProcurementsController, ProposalsController],
  exports: [ProcurementsRepository],
})
export class ProcurementsModule {}
