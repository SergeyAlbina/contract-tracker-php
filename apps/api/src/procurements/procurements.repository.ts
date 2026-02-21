import { Injectable } from '@nestjs/common';
import { Procurement, ProcurementStatus, LawType, Prisma } from '@prisma/client';
import { PrismaService } from '../prisma/prisma.service';

interface FindAllOptions {
  status?: ProcurementStatus;
  lawType?: LawType;
  search?: string;
  skip: number;
  take: number;
}

@Injectable()
export class ProcurementsRepository {
  constructor(private prisma: PrismaService) {}

  async findAll(opts: FindAllOptions): Promise<[Procurement[], number]> {
    const where: Prisma.ProcurementWhereInput = {
      ...(opts.status && { status: opts.status }),
      ...(opts.lawType && { lawType: opts.lawType }),
      ...(opts.search && {
        OR: [
          { number: { contains: opts.search } },
          { title: { contains: opts.search } },
        ],
      }),
    };
    const [data, total] = await this.prisma.$transaction([
      this.prisma.procurement.findMany({
        where,
        orderBy: { createdAt: 'desc' },
        skip: opts.skip,
        take: opts.take,
      }),
      this.prisma.procurement.count({ where }),
    ]);
    return [data, total];
  }

  findById(id: string): Promise<Procurement | null> {
    return this.prisma.procurement.findUnique({ where: { id } });
  }

  findByNumber(number: string): Promise<Procurement | null> {
    return this.prisma.procurement.findUnique({ where: { number } });
  }

  create(data: Prisma.ProcurementCreateInput): Promise<Procurement> {
    return this.prisma.procurement.create({ data });
  }

  update(id: string, data: Prisma.ProcurementUpdateInput): Promise<Procurement> {
    return this.prisma.procurement.update({ where: { id }, data });
  }
}
