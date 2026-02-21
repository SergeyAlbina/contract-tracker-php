import { Controller, Get, Post, Patch, Body, Param, Query, UseGuards } from '@nestjs/common';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';
import { ProcurementsService } from './procurements.service';
import { CreateProcurementDto } from './dto/create-procurement.dto';
import { UpdateProcurementDto } from './dto/update-procurement.dto';
import { ProcurementsQueryDto } from './dto/procurements-query.dto';
import { ProcurementResponseDto } from './dto/procurement-response.dto';
import { PaginatedDto } from '../common/dto/paginated.dto';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { CurrentUser } from '../auth/decorators/current-user.decorator';
import { AuthUser } from '../auth/strategies/jwt-access.strategy';

@ApiTags('procurements')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('procurements')
export class ProcurementsController {
  constructor(private service: ProcurementsService) {}

  @Get()
  @ApiOperation({ summary: 'Список закупок' })
  findAll(@Query() query: ProcurementsQueryDto): Promise<PaginatedDto<ProcurementResponseDto>> {
    return this.service.findAll(query);
  }

  @Get(':id')
  @ApiOperation({ summary: 'Закупка по ID' })
  findOne(@Param('id') id: string): Promise<ProcurementResponseDto> {
    return this.service.findById(id);
  }

  @Post()
  @ApiOperation({ summary: 'Создать закупку' })
  create(
    @Body() dto: CreateProcurementDto,
    @CurrentUser() user: AuthUser,
  ): Promise<ProcurementResponseDto> {
    return this.service.create(dto, user.id);
  }

  @Patch(':id')
  @ApiOperation({ summary: 'Обновить закупку' })
  update(
    @Param('id') id: string,
    @Body() dto: UpdateProcurementDto,
  ): Promise<ProcurementResponseDto> {
    return this.service.update(id, dto);
  }
}
