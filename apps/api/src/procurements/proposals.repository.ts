import { Injectable } from '@nestjs/common';
import { Proposal, ProposalStatus, Prisma } from '@prisma/client';
import { PrismaService } from '../prisma/prisma.service';

@Injectable()
export class ProposalsRepository {
  constructor(private prisma: PrismaService) {}

  findByProcurement(procurementId: string): Promise<Proposal[]> {
    return this.prisma.proposal.findMany({
      where: { procurementId },
      orderBy: { createdAt: 'desc' },
    });
  }

  findById(id: string): Promise<Proposal | null> {
    return this.prisma.proposal.findUnique({ where: { id } });
  }

  create(data: Prisma.ProposalCreateInput): Promise<Proposal> {
    return this.prisma.proposal.create({ data });
  }

  update(id: string, data: Prisma.ProposalUpdateInput): Promise<Proposal> {
    return this.prisma.proposal.update({ where: { id }, data });
  }

  countAccepted(procurementId: string): Promise<number> {
    return this.prisma.proposal.count({
      where: { procurementId, status: ProposalStatus.ACCEPTED },
    });
  }
}
