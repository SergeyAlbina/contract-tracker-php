import { BadRequestException, Injectable, NotFoundException } from '@nestjs/common';
import { Stage } from '@prisma/client';
import { StageStatus } from '@ct/shared';
import { StagesRepository } from './stages.repository';
import { ContractsRepository } from '../contracts/contracts.repository';
import { CreateStageDto } from './dto/create-stage.dto';
import { UpdateStageDto } from './dto/update-stage.dto';
import { StageResponseDto } from './dto/stage-response.dto';

@Injectable()
export class StagesService {
  constructor(
    private repo: StagesRepository,
    private contractsRepo: ContractsRepository,
  ) {}

  async findByContract(contractId: string): Promise<StageResponseDto[]> {
    await this.assertContractExists(contractId);
    const stages = await this.repo.findByContract(contractId);
    return stages.map(this.toDto);
  }

  async create(contractId: string, dto: CreateStageDto): Promise<StageResponseDto> {
    await this.assertContractExists(contractId);
    if (new Date(dto.plannedEnd) <= new Date(dto.plannedStart)) {
      throw new BadRequestException('plannedEnd должен быть позже plannedStart');
    }
    const stage = await this.repo.create({
      title: dto.title,
      plannedStart: new Date(dto.plannedStart),
      plannedEnd: new Date(dto.plannedEnd),
      amount: dto.amount,
      description: dto.description,
      contract: { connect: { id: contractId } },
    });
    return this.toDto(stage);
  }

  async update(contractId: string, id: string, dto: UpdateStageDto): Promise<StageResponseDto> {
    await this.assertStageInContract(id, contractId);

    // Автоматически выставляем COMPLETED при установке actualEnd
    let resolvedStatus = dto.status as any;
    if (dto.actualEnd && !dto.status) {
      resolvedStatus = 'COMPLETED';
    }

    const stage = await this.repo.update(id, {
      title: dto.title,
      status: resolvedStatus,
      plannedStart: dto.plannedStart ? new Date(dto.plannedStart) : undefined,
      plannedEnd: dto.plannedEnd ? new Date(dto.plannedEnd) : undefined,
      actualStart: dto.actualStart ? new Date(dto.actualStart) : undefined,
      actualEnd: dto.actualEnd ? new Date(dto.actualEnd) : undefined,
      amount: dto.amount,
      description: dto.description,
    });
    return this.toDto(stage);
  }

  async remove(contractId: string, id: string): Promise<void> {
    await this.assertStageInContract(id, contractId);
    await this.repo.delete(id);
  }

  // ─── Helpers ──────────────────────────────────────────────────────────────

  private async assertContractExists(contractId: string): Promise<void> {
    const c = await this.contractsRepo.findById(contractId);
    if (!c) throw new NotFoundException(`Контракт ${contractId} не найден`);
  }

  private async assertStageInContract(id: string, contractId: string): Promise<Stage> {
    const stage = await this.repo.findById(id);
    if (!stage || stage.contractId !== contractId) {
      throw new NotFoundException(`Этап ${id} не найден`);
    }
    return stage;
  }

  // ─── Mapper (OVERDUE вычисляется на лету, без мутации БД) ─────────────────

  private toDto(s: Stage): StageResponseDto {
    const effectiveStatus = this.resolveStatus(s);
    return {
      id: s.id,
      contractId: s.contractId,
      title: s.title,
      status: effectiveStatus,
      plannedStart: s.plannedStart,
      plannedEnd: s.plannedEnd,
      actualStart: s.actualStart ?? undefined,
      actualEnd: s.actualEnd ?? undefined,
      amount: s.amount?.toString(),
      description: s.description ?? undefined,
      createdAt: s.createdAt,
      updatedAt: s.updatedAt,
    };
  }

  private resolveStatus(s: Stage): StageStatus {
    if (s.status === 'COMPLETED' || s.status === 'OVERDUE') {
      return s.status as StageStatus;
    }
    if (s.plannedEnd < new Date() && !s.actualEnd) {
      return StageStatus.OVERDUE;
    }
    return s.status as StageStatus;
  }
}
