import { Controller, Get, Post, Patch, Body, Param, Query, UseGuards } from '@nestjs/common';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';
import { ContractsService } from './contracts.service';
import { CreateContractDto } from './dto/create-contract.dto';
import { UpdateContractDto } from './dto/update-contract.dto';
import { ContractsQueryDto } from './dto/contracts-query.dto';
import { ContractResponseDto } from './dto/contract-response.dto';
import { PaginatedDto } from '../common/dto/paginated.dto';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { CurrentUser } from '../auth/decorators/current-user.decorator';
import { AuthUser } from '../auth/strategies/jwt-access.strategy';

@ApiTags('contracts')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('contracts')
export class ContractsController {
  constructor(private service: ContractsService) {}

  @Get()
  @ApiOperation({ summary: 'Список контрактов' })
  findAll(@Query() query: ContractsQueryDto): Promise<PaginatedDto<ContractResponseDto>> {
    return this.service.findAll(query);
  }

  @Get(':id')
  @ApiOperation({ summary: 'Контракт по ID (с остатком и флагами риска)' })
  findOne(@Param('id') id: string): Promise<ContractResponseDto> {
    return this.service.findById(id);
  }

  @Post()
  @ApiOperation({ summary: 'Создать контракт' })
  create(
    @Body() dto: CreateContractDto,
    @CurrentUser() user: AuthUser,
  ): Promise<ContractResponseDto> {
    return this.service.create(dto, user.id);
  }

  @Patch(':id')
  @ApiOperation({ summary: 'Обновить контракт' })
  update(@Param('id') id: string, @Body() dto: UpdateContractDto): Promise<ContractResponseDto> {
    return this.service.update(id, dto);
  }
}
