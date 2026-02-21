import { BadRequestException, Injectable, NotFoundException } from '@nestjs/common';
import { Proposal } from '@prisma/client';
import { ProposalStatus } from '@ct/shared';
import { ProposalsRepository } from './proposals.repository';
import { ProcurementsRepository } from './procurements.repository';
import { CreateProposalDto } from './dto/create-proposal.dto';
import { DecideProposalDto } from './dto/decide-proposal.dto';
import { ProposalResponseDto } from './dto/proposal-response.dto';

@Injectable()
export class ProposalsService {
  constructor(
    private repo: ProposalsRepository,
    private procRepo: ProcurementsRepository,
  ) {}

  async findByProcurement(procurementId: string): Promise<ProposalResponseDto[]> {
    await this.assertProcurementExists(procurementId);
    const proposals = await this.repo.findByProcurement(procurementId);
    return proposals.map(this.toDto);
  }

  async create(procurementId: string, dto: CreateProposalDto): Promise<ProposalResponseDto> {
    await this.assertProcurementExists(procurementId);
    const proposal = await this.repo.create({
      supplierName: dto.supplierName,
      offeredAmount: dto.offeredAmount,
      notes: dto.notes,
      submittedAt: dto.submittedAt ? new Date(dto.submittedAt) : undefined,
      procurement: { connect: { id: procurementId } },
    });
    return this.toDto(proposal);
  }

  async decide(id: string, dto: DecideProposalDto): Promise<ProposalResponseDto> {
    const proposal = await this.repo.findById(id);
    if (!proposal) throw new NotFoundException(`КП ${id} не найдено`);
    if (proposal.status !== ProposalStatus.PENDING) {
      throw new BadRequestException('Решение уже принято по этому КП');
    }
    if (dto.status === ProposalStatus.REJECTED && !dto.rejectionReason) {
      throw new BadRequestException('Причина отклонения обязательна');
    }

    const updated = await this.repo.update(id, {
      status: dto.status as any,
      rejectionReason: dto.rejectionReason,
      decidedAt: new Date(),
    });
    return this.toDto(updated);
  }

  // ─── Mapper ───────────────────────────────────────────────────────────────

  private async assertProcurementExists(id: string): Promise<void> {
    const p = await this.procRepo.findById(id);
    if (!p) throw new NotFoundException(`Закупка ${id} не найдена`);
  }

  private toDto(p: Proposal): ProposalResponseDto {
    return {
      id: p.id,
      procurementId: p.procurementId,
      supplierName: p.supplierName,
      offeredAmount: p.offeredAmount.toString(),
      status: p.status as unknown as ProposalStatus,
      notes: p.notes ?? undefined,
      rejectionReason: p.rejectionReason ?? undefined,
      submittedAt: p.submittedAt ?? undefined,
      decidedAt: p.decidedAt ?? undefined,
      createdAt: p.createdAt,
      updatedAt: p.updatedAt,
    };
  }
}
