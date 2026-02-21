import { ConflictException, Injectable, NotFoundException } from '@nestjs/common';
import { Procurement } from '@prisma/client';
import { LawType, ProcurementStatus } from '@ct/shared';
import { ProcurementsRepository } from './procurements.repository';
import { CreateProcurementDto } from './dto/create-procurement.dto';
import { UpdateProcurementDto } from './dto/update-procurement.dto';
import { ProcurementResponseDto } from './dto/procurement-response.dto';
import { ProcurementsQueryDto } from './dto/procurements-query.dto';
import { PaginatedDto } from '../common/dto/paginated.dto';

@Injectable()
export class ProcurementsService {
  constructor(private repo: ProcurementsRepository) {}

  async findAll(query: ProcurementsQueryDto): Promise<PaginatedDto<ProcurementResponseDto>> {
    const page = query.page ?? 1;
    const limit = query.limit ?? 20;
    const [data, total] = await this.repo.findAll({
      status: query.status as any,
      lawType: query.lawType as any,
      search: query.search,
      skip: (page - 1) * limit,
      take: limit,
    });
    return { data: data.map(this.toDto), total, page, limit };
  }

  async findById(id: string): Promise<ProcurementResponseDto> {
    const p = await this.repo.findById(id);
    if (!p) throw new NotFoundException(`Закупка ${id} не найдена`);
    return this.toDto(p);
  }

  async create(dto: CreateProcurementDto, ownerId: string): Promise<ProcurementResponseDto> {
    const existing = await this.repo.findByNumber(dto.number);
    if (existing) throw new ConflictException(`Номер ${dto.number} уже используется`);

    const p = await this.repo.create({
      number: dto.number,
      title: dto.title,
      lawType: dto.lawType as any,
      description: dto.description,
      plannedDate: dto.plannedDate ? new Date(dto.plannedDate) : undefined,
      owner: { connect: { id: ownerId } },
    });
    return this.toDto(p);
  }

  async update(id: string, dto: UpdateProcurementDto): Promise<ProcurementResponseDto> {
    await this.findById(id);
    const p = await this.repo.update(id, {
      title: dto.title,
      lawType: dto.lawType as any,
      status: dto.status as any,
      description: dto.description,
      plannedDate: dto.plannedDate ? new Date(dto.plannedDate) : undefined,
    });
    return this.toDto(p);
  }

  // ─── Mapper ───────────────────────────────────────────────────────────────

  private toDto(p: Procurement): ProcurementResponseDto {
    return {
      id: p.id,
      number: p.number,
      title: p.title,
      lawType: p.lawType as unknown as LawType,
      status: p.status as unknown as ProcurementStatus,
      description: p.description ?? undefined,
      plannedDate: p.plannedDate ?? undefined,
      ownerId: p.ownerId,
      createdAt: p.createdAt,
      updatedAt: p.updatedAt,
    };
  }
}
