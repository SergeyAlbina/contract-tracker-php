import { Injectable } from '@nestjs/common';
import { Contract, ContractStatus, LawType, Prisma } from '../generated/prisma/client';
import { PrismaService } from '../prisma/prisma.service';

export type ContractWithAggregates = Contract & {
  stages: { status: string }[];
  payments: { amount: any; status: string }[];
  documents: { id: string }[];
};

interface FindAllOptions {
  status?: ContractStatus;
  lawType?: LawType;
  search?: string;
  ownerId?: string;
  skip: number;
  take: number;
}

const RISK_INCLUDE = {
  stages: { select: { status: true } },
  payments: { select: { amount: true, status: true } },
  documents: { select: { id: true } },
} satisfies Prisma.ContractInclude;

@Injectable()
export class ContractsRepository {
  constructor(private prisma: PrismaService) {}

  async findAll(opts: FindAllOptions): Promise<[ContractWithAggregates[], number]> {
    const where: Prisma.ContractWhereInput = {
      ...(opts.status && { status: opts.status }),
      ...(opts.lawType && { lawType: opts.lawType }),
      ...(opts.ownerId && { ownerId: opts.ownerId }),
      ...(opts.search && {
        OR: [
          { number: { contains: opts.search } },
          { title: { contains: opts.search } },
          { supplierName: { contains: opts.search } },
        ],
      }),
    };
    const [data, total] = await this.prisma.$transaction([
      this.prisma.contract.findMany({
        where,
        include: RISK_INCLUDE,
        orderBy: { createdAt: 'desc' },
        skip: opts.skip,
        take: opts.take,
      }),
      this.prisma.contract.count({ where }),
    ]);
    return [data as ContractWithAggregates[], total];
  }

  findById(id: string): Promise<ContractWithAggregates | null> {
    return this.prisma.contract.findUnique({
      where: { id },
      include: RISK_INCLUDE,
    }) as Promise<ContractWithAggregates | null>;
  }

  findByNumber(number: string): Promise<Contract | null> {
    return this.prisma.contract.findUnique({ where: { number } });
  }

  create(data: Prisma.ContractCreateInput): Promise<Contract> {
    return this.prisma.contract.create({ data });
  }

  update(id: string, data: Prisma.ContractUpdateInput): Promise<Contract> {
    return this.prisma.contract.update({ where: { id }, data });
  }
}
