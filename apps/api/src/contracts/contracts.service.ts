import { BadRequestException, ConflictException, Injectable, NotFoundException } from '@nestjs/common';
import { Contract } from '@prisma/client';
import { ContractStatus, LawType, RiskFlag } from '@ct/shared';
import { ContractsRepository, ContractWithAggregates } from './contracts.repository';
import { CreateContractDto } from './dto/create-contract.dto';
import { UpdateContractDto } from './dto/update-contract.dto';
import { ContractResponseDto } from './dto/contract-response.dto';
import { ContractsQueryDto } from './dto/contracts-query.dto';
import { PaginatedDto } from '../common/dto/paginated.dto';

@Injectable()
export class ContractsService {
  constructor(private repo: ContractsRepository) {}

  async findAll(query: ContractsQueryDto): Promise<PaginatedDto<ContractResponseDto>> {
    const page = query.page ?? 1;
    const limit = query.limit ?? 20;
    const [data, total] = await this.repo.findAll({
      status: query.status as any,
      lawType: query.lawType as any,
      search: query.search,
      ownerId: query.ownerId,
      skip: (page - 1) * limit,
      take: limit,
    });
    return { data: data.map((c) => this.toDto(c)), total, page, limit };
  }

  async findById(id: string): Promise<ContractResponseDto> {
    const c = await this.repo.findById(id);
    if (!c) throw new NotFoundException(`Контракт ${id} не найден`);
    return this.toDto(c);
  }

  async create(dto: CreateContractDto, ownerId: string): Promise<ContractResponseDto> {
    if (dto.lawType === LawType.LAW_44 && !dto.nmckAmount) {
      throw new BadRequestException('nmckAmount обязателен для 44-ФЗ');
    }
    const existing = await this.repo.findByNumber(dto.number);
    if (existing) throw new ConflictException(`Номер ${dto.number} уже используется`);

    const c = await this.repo.create({
      number: dto.number,
      title: dto.title,
      lawType: dto.lawType as any,
      supplierName: dto.supplierName,
      supplierInn: dto.supplierInn,
      totalAmount: dto.totalAmount,
      nmckAmount: dto.nmckAmount,
      signedAt: dto.signedAt ? new Date(dto.signedAt) : undefined,
      startDate: dto.startDate ? new Date(dto.startDate) : undefined,
      endDate: dto.endDate ? new Date(dto.endDate) : undefined,
      description: dto.description,
      owner: { connect: { id: ownerId } },
      ...(dto.procurementId && { procurement: { connect: { id: dto.procurementId } } }),
    });

    return this.toDto(c as ContractWithAggregates);
  }

  async update(id: string, dto: UpdateContractDto): Promise<ContractResponseDto> {
    await this.findById(id);
    const c = await this.repo.update(id, {
      title: dto.title,
      status: dto.status as any,
      lawType: dto.lawType as any,
      supplierName: dto.supplierName,
      supplierInn: dto.supplierInn,
      totalAmount: dto.totalAmount,
      nmckAmount: dto.nmckAmount,
      signedAt: dto.signedAt ? new Date(dto.signedAt) : undefined,
      startDate: dto.startDate ? new Date(dto.startDate) : undefined,
      endDate: dto.endDate ? new Date(dto.endDate) : undefined,
      description: dto.description,
    });
    return this.toDto(c as ContractWithAggregates);
  }

  // ─── Risk flags ───────────────────────────────────────────────────────────

  private calcRiskFlags(c: ContractWithAggregates): RiskFlag[] {
    const flags: RiskFlag[] = [];
    const now = new Date();

    if (c.endDate) {
      const days = Math.ceil((c.endDate.getTime() - now.getTime()) / 86_400_000);
      if (days >= 0 && days <= 10) flags.push(RiskFlag.EXPIRING_10);
      else if (days <= 30) flags.push(RiskFlag.EXPIRING_30);
      else if (days <= 90) flags.push(RiskFlag.EXPIRING_90);
    }

    const paid = c.payments
      .filter((p) => p.status === 'PAID')
      .reduce((sum, p) => sum + Number(p.amount), 0);
    if (paid > Number(c.totalAmount)) flags.push(RiskFlag.OVERSPEND);

    if (c.stages.some((s) => s.status === 'OVERDUE')) flags.push(RiskFlag.OVERDUE_STAGE);
    if (c.documents.length === 0) flags.push(RiskFlag.MISSING_DOCS);

    return flags;
  }

  private calcBalance(c: ContractWithAggregates): string {
    const paid = c.payments
      .filter((p) => p.status === 'PAID')
      .reduce((sum, p) => sum + Number(p.amount), 0);
    return (Number(c.totalAmount) - paid).toFixed(2);
  }

  // ─── Mapper ───────────────────────────────────────────────────────────────

  private toDto(c: ContractWithAggregates | Contract): ContractResponseDto {
    const full = c as ContractWithAggregates;
    const hasRelations = Array.isArray(full.payments);
    return {
      id: c.id,
      number: c.number,
      title: c.title,
      lawType: c.lawType as unknown as LawType,
      status: c.status as unknown as ContractStatus,
      supplierName: c.supplierName,
      supplierInn: c.supplierInn ?? undefined,
      totalAmount: c.totalAmount.toString(),
      nmckAmount: c.nmckAmount?.toString(),
      signedAt: c.signedAt ?? undefined,
      startDate: c.startDate ?? undefined,
      endDate: c.endDate ?? undefined,
      description: c.description ?? undefined,
      ownerId: c.ownerId,
      procurementId: c.procurementId ?? undefined,
      balance: hasRelations ? this.calcBalance(full) : c.totalAmount.toString(),
      riskFlags: hasRelations ? this.calcRiskFlags(full) : [],
      createdAt: c.createdAt,
      updatedAt: c.updatedAt,
    };
  }
}
