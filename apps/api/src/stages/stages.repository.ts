import { Injectable } from '@nestjs/common';
import { Stage, Prisma } from '../generated/prisma/client';
import { PrismaService } from '../prisma/prisma.service';

@Injectable()
export class StagesRepository {
  constructor(private prisma: PrismaService) {}

  findByContract(contractId: string): Promise<Stage[]> {
    return this.prisma.stage.findMany({
      where: { contractId },
      orderBy: { plannedStart: 'asc' },
    });
  }

  findById(id: string): Promise<Stage | null> {
    return this.prisma.stage.findUnique({ where: { id } });
  }

  create(data: Prisma.StageCreateInput): Promise<Stage> {
    return this.prisma.stage.create({ data });
  }

  update(id: string, data: Prisma.StageUpdateInput): Promise<Stage> {
    return this.prisma.stage.update({ where: { id }, data });
  }

  delete(id: string): Promise<Stage> {
    return this.prisma.stage.delete({ where: { id } });
  }
}
