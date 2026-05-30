# Plano de Implementação: Cálculo de Área Útil

**Data:** 27 de maio de 2026  
**Feature:** Cálculo automático de área útil descontando APP, declividade e áreas de preservação  
**Esforço estimado:** 3-4 semanas  
**Status:** Planejamento

---

## Sumário Executivo

Implementar cálculo automático de área útil a partir do polígono do terreno, descontando:
- Área com declividade > 30% (inutilizável para construção)
- Área de APP (Área de Preservação Permanente)
  - 50m de raio de cursos d'água
  - 15m de raio de nascentes
- Calcular % de aproveitamento (área útil / área total)

---

## 1. Contexto Atual

### 1.1 O que já existe

**Model `Terreno`:**
- `polygon_coords` (array de `{lat, lng}`) - já armazenado
- `area_calculada` (decimal) - já existe, mas é preenchida manualmente
- `static_map_url` - URL do mapa estático

**Serviços relacionados:**
- `KmzParserService` - extrai coordenadas de arquivos KMZ/KML
- `TerrenoService` - CRUD de terrenos
- Não há cálculo de área atual
- Não há integração com APIs de topografia/hidrografia

### 1.2 O que falta

- Cálculo automático de área a partir do polígono
- Integração com API de topografia (DEM) para declividade
- Integração com API de hidrografia para APP
- Lógica de interseção de polígonos (terreno - APP - declividade)
- Recálculo automático quando polígono muda

---

## 2. Arquitetura da Solução

### 2.1 Visão Geral

```
┌─────────────────┐
│  Terreno Model  │
│  (polygon_coords)│
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│  AreaCalculatorService                  │
│  - calculateTotalArea()                 │
│  - calculateSlopeArea()                 │
│  - calculateAppArea()                   │
│  - calculateUsableArea()                │
└────────┬────────────────────────────────┘
         │
         ├──────────────────┬──────────────────┐
         ▼                  ▼                  ▼
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│ PolygonArea  │  │ Topography   │  │ Hydrography  │
│ Calculator   │  │ Service      │  │ Service      │
│ (Shoelace)   │  │ (OpenTopo/   │  │ (ANA/Static) │
│              │  │  Google Elev)│  │              │
└──────────────┘  └──────────────┘  └──────────────┘
```

### 2.2 Fluxo de Processamento

```
1. Usuário cadastra/atualiza terreno com polygon_coords
   ↓
2. Observer detecta mudança em polygon_coords
   ↓
3. Dispara job assíncrono: CalculateUsableAreaJob
   ↓
4. Job executa:
   a. Calcula área total (Shoelace formula)
   b. Busca DEM da região (OpenTopography API)
   c. Calcula declividade ponto a ponto
   d. Identifica área com declividade > 30%
   e. Busca hidrografia da região (ANA/Static data)
   f. Calcula buffer de APP (50m rios, 15m nascentes)
   g. Intersecta polígonos (terreno - declividade - APP)
   h. Calcula área útil
   ↓
5. Atualiza campos no Terreno:
   - area_total
   - area_declividade
   - area_app
   - area_util
   - percentual_aproveitamento
   - area_calculada (alias para area_util)
   ↓
6. Notifica usuário (se demorou > 30s)
```

---

## 3. Dependências Externas

### 3.1 API de Topografia (Declividade) -->> FEITO 27/05/2026

**Opção 1: OpenTopography (Recomendado)**
- **Dataset:** SRTM GL1 (30m) ou COP30 (30m)
- **API:** REST, retorna GeoTIFF
- **Custo:** Gratuito (50 calls/dia para não-acadêmicos)
- **Limite:** 450.000 km² por request
- **Auth:** API key (registro gratuito)
- **Docs:** https://opentopography.org/developers

**Opção 2: Google Elevation API**
- **API:** REST, retorna JSON com elevações
- **Custo:** Pago (~$5 por 1000 requests)
- **Limite:** 512 coordenadas por request
- **Auth:** API key (Google Cloud)
- **Docs:** https://developers.google.com/maps/documentation/elevation

**Recomendação:** OpenTopography (gratuito, resolução adequada)

### 3.2 API de Hidrografia (APP) -->> FEITO 27/05/2026

**Opção 1: Dados estáticos (Recomendado para MVP)**
- Download de shapefiles da ANA (Agência Nacional de Águas)
- Converter para GeoJSON e armazenar no S3
- Filtrar por bounding box do terreno
- **Prós:** Sem dependência de API externa, sem custo
- **Contras:** Dados podem ficar desatualizados

**Opção 2: ANA Web Services**
- **API:** WMS/WFS (OGC standards)
- **Custo:** Gratuito
- **Limitação:** Não há API REST clara, requer parsing de XML
- **Docs:** https://www.ana.gov.br/servicos-e-produtos/servicos-web

**Opção 3: Overpass API (OpenStreetMap)**
- **API:** REST, retorna GeoJSON
- **Custo:** Gratuito
- **Limitação:** Dados incompletos em áreas rurais
- **Docs:** https://overpass-api.de/

**Recomendação:** Dados estáticos da ANA para MVP, migrar para API no futuro

### 3.3 Bibliotecas PHP Necessárias

```bash
composer require league/geotools:^1.0  # Cálculos geoespaciais
composer require geo-io/geometry:^1.0  # Manipulação de geometrias
```

**Alternativa:** Implementar cálculos manualmente (Shoelace formula, buffer, intersection)

---

## 4. Estrutura de Dados

### 4.1 Migração: Adicionar campos ao Terreno

```php
// database/migrations/tenant/2026_05_27_000001_add_area_util_fields_to_terrenos_table.php

Schema::table('terrenos', function (Blueprint $table) {
    $table->decimal('area_total', 12, 2)->nullable()->after('area_calculada');
    $table->decimal('area_declividade', 12, 2)->nullable()->after('area_total');
    $table->decimal('area_app', 12, 2)->nullable()->after('area_declividade');
    $table->decimal('area_util', 12, 2)->nullable()->after('area_app');
    $table->decimal('percentual_aproveitamento', 5, 2)->nullable()->after('area_util');
    $table->timestamp('area_calculada_em')->nullable()->after('percentual_aproveitamento');
    $table->string('area_calculo_status')->nullable()->after('area_calculada_em');
    // pending, calculating, success, failed
});
```

### 4.2 Model: Atualizar Terreno

```php
// app/Models/Tenant/Terreno.php

protected $fillable = [
    // ... existing fields
    'area_total',
    'area_declividade',
    'area_app',
    'area_util',
    'percentual_aproveitamento',
    'area_calculada_em',
    'area_calculo_status',
];

protected $casts = [
    // ... existing casts
    'area_total' => 'decimal:2',
    'area_declividade' => 'decimal:2',
    'area_app' => 'decimal:2',
    'area_util' => 'decimal:2',
    'percentual_aproveitamento' => 'decimal:2',
    'area_calculada_em' => 'datetime',
];

// Accessor para compatibilidade com código existente
public function getAreaCalculadaAttribute()
{
    return $this->area_util ?? $this->attributes['area_calculada'] ?? null;
}
```

---

## 5. Implementação Detalhada

### 5.1 Service: AreaCalculatorService

```php
// app/Services/Tenant/AreaCalculatorService.php

class AreaCalculatorService
{
    public function __construct(
        private TopographyService $topography,
        private HydrographyService $hydrography,
        private PolygonCalculator $polygonCalc,
    ) {}

    public function calculate(Terreno $terreno): array
    {
        $polygon = $terreno->polygon_coords;
        
        // 1. Área total
        $areaTotal = $this->polygonCalc->calculateArea($polygon);
        
        // 2. Área com declividade > 30%
        $areaDeclividade = $this->calculateSlopeArea($polygon);
        
        // 3. Área de APP
        $areaApp = $this->calculateAppArea($polygon);
        
        // 4. Área útil (total - declividade - APP + sobreposição)
        $areaUtil = $this->calculateUsableArea($polygon, $areaDeclividade, $areaApp);
        
        // 5. Percentual de aproveitamento
        $percentual = $areaTotal > 0 ? ($areaUtil / $areaTotal) * 100 : 0;
        
        return [
            'area_total' => $areaTotal,
            'area_declividade' => $areaDeclividade,
            'area_app' => $areaApp,
            'area_util' => $areaUtil,
            'percentual_aproveitamento' => $percentual,
        ];
    }

    private function calculateSlopeArea(array $polygon): float
    {
        // 1. Buscar DEM da região (OpenTopography)
        $dem = $this->topography->getDemForPolygon($polygon);
        
        // 2. Calcular declividade ponto a ponto
        $slopes = $this->topography->calculateSlopes($dem);
        
        // 3. Identificar pontos com declividade > 30%
        $steepPoints = array_filter($slopes, fn($s) => $s['slope'] > 30);
        
        // 4. Calcular área dos pontos íngremes
        return $this->polygonCalc->calculateAreaFromPoints($steepPoints);
    }

    private function calculateAppArea(array $polygon): float
    {
        // 1. Buscar hidrografia da região
        $waterBodies = $this->hydrography->getWaterBodiesForPolygon($polygon);
        
        // 2. Calcular buffer de APP (50m rios, 15m nascentes)
        $appPolygons = $this->hydrography->calculateAppBuffers($waterBodies);
        
        // 3. Intersectar com polígono do terreno
        $intersection = $this->polygonCalc->intersectPolygons($polygon, $appPolygons);
        
        // 4. Calcular área da interseção
        return $this->polygonCalc->calculateArea($intersection);
    }

    private function calculateUsableArea(array $polygon, float $slopeArea, float $appArea): float
    {
        // Simplificação: área útil = total - declividade - APP
        // (ignora sobreposição entre declividade e APP)
        $totalArea = $this->polygonCalc->calculateArea($polygon);
        return max(0, $totalArea - $slopeArea - $appArea);
    }
}
```

### 5.2 Service: TopographyService

```php
// app/Services/Tenant/TopographyService.php

class TopographyService
{
    private const OPENTOPO_API = 'https://portal.opentopography.org/API/globaldem';
    private const DEM_DATASET = 'SRTMGL1'; // 30m resolution

    public function __construct(
        private HttpClient $http,
    ) {}

    public function getDemForPolygon(array $polygon): array
    {
        $bbox = $this->calculateBoundingBox($polygon);
        
        $response = $this->http->get(self::OPENTOPO_API, [
            'query' => [
                'demtype' => self::DEM_DATASET,
                'south' => $bbox['south'],
                'north' => $bbox['north'],
                'west' => $bbox['west'],
                'east' => $bbox['east'],
                'outputFormat' => 'GTiff',
                'API_Key' => config('services.opentopography.key'),
            ],
        ]);

        return $this->parseGeoTiff($response->body());
    }

    public function calculateSlopes(array $dem): array
    {
        $slopes = [];
        
        foreach ($dem['points'] as $i => $point) {
            if ($i === 0) continue;
            
            $prev = $dem['points'][$i - 1];
            $elevationDiff = $point['elevation'] - $prev['elevation'];
            $horizontalDist = $this->haversineDistance($prev, $point);
            
            $slope = $horizontalDist > 0 
                ? ($elevationDiff / $horizontalDist) * 100 
                : 0;
            
            $slopes[] = [
                'lat' => $point['lat'],
                'lng' => $point['lng'],
                'slope' => abs($slope),
            ];
        }
        
        return $slopes;
    }

    private function calculateBoundingBox(array $polygon): array
    {
        $lats = array_column($polygon, 'lat');
        $lngs = array_column($polygon, 'lng');
        
        return [
            'south' => min($lats),
            'north' => max($lats),
            'west' => min($lngs),
            'east' => max($lngs),
        ];
    }

    private function haversineDistance(array $p1, array $p2): float
    {
        $earthRadius = 6371000; // metros
        
        $lat1 = deg2rad($p1['lat']);
        $lat2 = deg2rad($p2['lat']);
        $dLat = deg2rad($p2['lat'] - $p1['lat']);
        $dLng = deg2rad($p2['lng'] - $p1['lng']);
        
        $a = sin($dLat / 2) ** 2 + 
             cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }

    private function parseGeoTiff(string $binary): array
    {
        // TODO: Implementar parsing de GeoTIFF
        // Opções:
        // 1. Usar extensão PHP GDAL (gdal/gdal)
        // 2. Converter para ASCII grid via CLI (gdal_translate)
        // 3. Usar biblioteca PHP (geospatial-php/geotiff)
        
        // Simplificação para MVP: usar Google Elevation API ao invés de GeoTIFF
        throw new \RuntimeException('GeoTIFF parsing não implementado');
    }
}
```

### 5.3 Service: HydrographyService

```php
// app/Services/Tenant/HydrographyService.php

class HydrographyService
{
    public function __construct(
        private Storage $storage,
    ) {}

    public function getWaterBodiesForPolygon(array $polygon): array
    {
        $bbox = $this->calculateBoundingBox($polygon);
        
        // Carregar dados estáticos da ANA (GeoJSON)
        $waterBodies = $this->loadStaticWaterBodies($bbox);
        
        // Filtrar apenas os que intersectam com o polígono
        return array_filter($waterBodies, function ($wb) use ($polygon) {
            return $this->intersects($wb['geometry'], $polygon);
        });
    }

    public function calculateAppBuffers(array $waterBodies): array
    {
        $appPolygons = [];
        
        foreach ($waterBodies as $wb) {
            $bufferDistance = match($wb['type']) {
                'rio' => 50, // metros
                'nascente' => 15,
                'lago' => 30,
                default => 30,
            };
            
            $appPolygons[] = $this->createBuffer($wb['geometry'], $bufferDistance);
        }
        
        return $appPolygons;
    }

    private function loadStaticWaterBodies(array $bbox): array
    {
        // Carregar GeoJSON do S3/storage
        $path = storage_path('app/hydrography/brasil_rivers.geojson');
        
        if (!file_exists($path)) {
            throw new \RuntimeException('Arquivo de hidrografia não encontrado');
        }
        
        $geojson = json_decode(file_get_contents($path), true);
        
        // Filtrar por bounding box
        return array_filter($geojson['features'], function ($feature) use ($bbox) {
            $coords = $feature['geometry']['coordinates'];
            return $this->isInBoundingBox($coords, $bbox);
        });
    }

    private function createBuffer(array $geometry, float $distance): array
    {
        // TODO: Implementar buffer de polígono
        // Opções:
        // 1. Usar PostGIS (ST_Buffer)
        // 2. Usar biblioteca PHP (geo-io/geometry)
        // 3. Implementar manualmente (complexo)
        
        throw new \RuntimeException('Buffer não implementado');
    }

    private function intersects(array $geom1, array $geom2): bool
    {
        // TODO: Implementar interseção de polígonos
        throw new \RuntimeException('Interseção não implementada');
    }

    private function calculateBoundingBox(array $polygon): array
    {
        $lats = array_column($polygon, 'lat');
        $lngs = array_column($polygon, 'lng');
        
        return [
            'south' => min($lats),
            'north' => max($lats),
            'west' => min($lngs),
            'east' => max($lngs),
        ];
    }

    private function isInBoundingBox(array $coords, array $bbox): bool
    {
        // Simplificação: verificar se algum ponto está dentro do bbox
        foreach ($coords as $coord) {
            if (is_array($coord[0])) {
                // MultiLineString ou Polygon
                foreach ($coord as $c) {
                    if ($this->isInBoundingBox([$c], $bbox)) {
                        return true;
                    }
                }
            } else {
                // Point [lng, lat]
                $lng = $coord[0];
                $lat = $coord[1];
                
                if ($lat >= $bbox['south'] && $lat <= $bbox['north'] &&
                    $lng >= $bbox['west'] && $lng <= $bbox['east']) {
                    return true;
                }
            }
        }
        
        return false;
    }
}
```

### 5.4 Service: PolygonCalculator

```php
// app/Services/Tenant/PolygonCalculator.php

class PolygonCalculator
{
    public function calculateArea(array $polygon): float
    {
        // Shoelace formula para calcular área de polígono
        // https://en.wikipedia.org/wiki/Shoelace_formula
        
        $n = count($polygon);
        if ($n < 3) {
            return 0;
        }
        
        $area = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            
            $lat1 = $polygon[$i]['lat'];
            $lng1 = $polygon[$i]['lng'];
            $lat2 = $polygon[$j]['lat'];
            $lng2 = $polygon[$j]['lng'];
            
            // Converter para metros (aproximação)
            $x1 = $lng1 * 111320 * cos(deg2rad($lat1));
            $y1 = $lat1 * 110540;
            $x2 = $lng2 * 111320 * cos(deg2rad($lat2));
            $y2 = $lat2 * 110540;
            
            $area += ($x1 * $y2) - ($x2 * $y1);
        }
        
        return abs($area) / 2;
    }

    public function calculateAreaFromPoints(array $points): float
    {
        // Criar convex hull dos pontos e calcular área
        // Simplificação: usar bounding box
        
        if (count($points) < 3) {
            return 0;
        }
        
        $lats = array_column($points, 'lat');
        $lngs = array_column($points, 'lng');
        
        $width = (max($lngs) - min($lngs)) * 111320 * cos(deg2rad(mean($lats)));
        $height = (max($lats) - min($lats)) * 110540;
        
        return $width * $height;
    }

    public function intersectPolygons(array $polygon1, array $polygon2): array
    {
        // TODO: Implementar interseção de polígonos
        // Opções:
        // 1. Usar PostGIS (ST_Intersection)
        // 2. Usar biblioteca PHP (geo-io/geometry)
        // 3. Implementar Sutherland-Hodgman algorithm
        
        throw new \RuntimeException('Interseção não implementada');
    }
}
```

### 5.5 Job: CalculateUsableAreaJob

```php
// app/Jobs/CalculateUsableAreaJob.php

class CalculateUsableAreaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [30, 60, 120];

    public function __construct(
        private Terreno $terreno,
    ) {}

    public function handle(AreaCalculatorService $calculator): void
    {
        $this->terreno->update(['area_calculo_status' => 'calculating']);
        
        try {
            $result = $calculator->calculate($this->terreno);
            
            $this->terreno->update([
                'area_total' => $result['area_total'],
                'area_declividade' => $result['area_declividade'],
                'area_app' => $result['area_app'],
                'area_util' => $result['area_util'],
                'percentual_aproveitamento' => $result['percentual_aproveitamento'],
                'area_calculada_em' => now(),
                'area_calculo_status' => 'success',
            ]);
            
        } catch (\Throwable $e) {
            $this->terreno->update([
                'area_calculo_status' => 'failed',
            ]);
            
            Log::error('Falha ao calcular área útil', [
                'terreno_id' => $this->terreno->id,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->terreno->update([
            'area_calculo_status' => 'failed',
        ]);
    }
}
```

### 5.6 Observer: TerrenoObserver

```php
// app/Observers/Tenant/TerrenoObserver.php

class TerrenoObserver
{
    public function saved(Terreno $terreno): void
    {
        // Verificar se polygon_coords mudou
        if ($terreno->wasChanged('polygon_coords')) {
            CalculateUsableAreaJob::dispatch($terreno);
        }
    }
}
```

### 5.7 Controller: Atualizar TerrenoResource

```php
// app/Http/Resources/Tenant/TerrenoResource.php

public function toArray(Request $request): array
{
    return [
        // ... existing fields
        'area_total' => $this->area_total ? (float) $this->area_total : null,
        'area_declividade' => $this->area_declividade ? (float) $this->area_declividade : null,
        'area_app' => $this->area_app ? (float) $this->area_app : null,
        'area_util' => $this->area_util ? (float) $this->area_util : null,
        'percentual_aproveitamento' => $this->percentual_aproveitamento ? (float) $this->percentual_aproveitamento : null,
        'area_calculada_em' => $this->area_calculada_em?->toIso8601String(),
        'area_calculo_status' => $this->area_calculo_status,
    ];
}
```

---

## 6. Checklist de Implementação

### Semana 1: Estrutura e Cálculos Básicos

- [ ] Criar migração para adicionar campos de área útil
- [ ] Atualizar model `Terreno` com novos campos e casts
- [ ] Implementar `PolygonCalculator::calculateArea()` (Shoelace formula)
- [ ] Testar cálculo de área com polígonos conhecidos
- [ ] Criar `TerrenoObserver` para detectar mudanças em `polygon_coords`
- [ ] Criar `CalculateUsableAreaJob` (estrutura básica)

### Semana 2: Topografia (Declividade)

- [ ] Registrar API key do OpenTopography
- [ ] Implementar `TopographyService::getDemForPolygon()`
- [ ] Implementar parsing de GeoTIFF (ou usar Google Elevation API como fallback)
- [ ] Implementar `TopographyService::calculateSlopes()`
- [ ] Testar com terrenos reais (comparar com dados conhecidos)
- [ ] Integrar no `AreaCalculatorService`

### Semana 3: Hidrografia (APP)

- [ ] Download de shapefiles da ANA (rios, nascentes, lagos)
- [ ] Converter para GeoJSON e armazenar no S3
- [ ] Implementar `HydrographyService::getWaterBodiesForPolygon()`
- [ ] Implementar `HydrographyService::calculateAppBuffers()`
- [ ] Implementar interseção de polígonos (PostGIS ou biblioteca PHP)
- [ ] Testar com terrenos reais (comparar com APP conhecida)
- [ ] Integrar no `AreaCalculatorService`

### Semana 4: Integração e Testes

- [ ] Integrar todos os serviços no `AreaCalculatorService`
- [ ] Atualizar `TerrenoResource` para expor novos campos
- [ ] Criar endpoint para recalcular área manualmente (`POST /terrenos/{id}/recalcular-area`)
- [ ] Escrever testes unitários para cada serviço
- [ ] Escrever testes de integração (job completo)
- [ ] Testar com 10+ terrenos reais
- [ ] Documentar API no Swagger
- [ ] Deploy para staging
- [ ] Validar com usuários

---

## 7. Estimativa de Esforço Detalhada

| Tarefa | Esforço | Dependências |
|--------|---------|--------------|
| Migração + Model | 2h | Nenhuma |
| PolygonCalculator | 4h | Nenhuma |
| TerrenoObserver + Job | 2h | PolygonCalculator |
| TopographyService (OpenTopography) | 8h | API key |
| Parsing GeoTIFF | 6h | TopographyService |
| HydrographyService (dados estáticos) | 6h | Shapefiles ANA |
| Interseção de polígonos | 8h | PostGIS ou biblioteca |
| AreaCalculatorService (integração) | 4h | Todos acima |
| TerrenoResource + endpoint | 2h | AreaCalculatorService |
| Testes unitários | 8h | Todos acima |
| Testes de integração | 4h | Todos acima |
| Documentação | 2h | Todos acima |
| **TOTAL** | **56h (~7 dias)** | |

**Buffer (30%):** 17h (~2 dias)

**Total com buffer:** 73h (~9 dias = 2 semanas de trabalho focado)

**Estimativa realista:** 3-4 semanas (considerando outras tarefas em paralelo)

---

## 8. Riscos e Mitigações

### 8.1 Riscos Técnicos

| Risco | Probabilidade | Impacto | Mitigação |
|-------|---------------|---------|-----------|
| OpenTopography API instável | Média | Alto | Implementar fallback para Google Elevation API |
| GeoTIFF parsing complexo | Alta | Alto | Usar Google Elevation API (JSON) ao invés de GeoTIFF |
| Interseção de polígonos complexa | Alta | Alto | Usar PostGIS (ST_Intersection) ao invés de implementar manualmente |
| Dados da ANA desatualizados | Baixa | Médio | Documentar data dos dados, planejar atualização anual |
| Cálculo muito lento (> 2min) | Média | Médio | Otimizar com cache de DEM, processar em chunks |

### 8.2 Riscos de Negócio

| Risco | Probabilidade | Impacto | Mitigação |
|-------|---------------|---------|-----------|
| Cálculo impreciso | Média | Alto | Validar com topógrafos, documentar margem de erro |
| Usuários não confiam no cálculo | Média | Alto | Mostrar metodologia, permitir override manual |
| Custo de API alto | Baixa | Médio | Monitorar uso, implementar cache agressivo |

---

## 9. Alternativas Simplificadas (MVP)

Se a implementação completa for muito complexa, considerar:

### 9.1 MVP 1: Apenas Área Total

- Calcular apenas área total (Shoelace formula)
- Não calcular declividade nem APP
- Esforço: 1 dia

### 9.2 MVP 2: Área Total + Declividade (Google Elevation API)

- Calcular área total
- Usar Google Elevation API (JSON, não GeoTIFF)
- Não calcular APP
- Esforço: 1 semana

### 9.3 MVP 3: Área Total + Declividade + APP (simplificada)

- Calcular área total
- Usar Google Elevation API para declividade
- Usar Overpass API (OpenStreetMap) para hidrografia
- Esforço: 2 semanas

---

## 10. Próximos Passos

1. **Validar viabilidade técnica:**
   - Testar OpenTopography API com terreno real
   - Testar parsing de GeoTIFF (ou Google Elevation API)
   - Testar interseção de polígonos (PostGIS vs biblioteca PHP)

2. **Escolher abordagem:**
   - Implementação completa (3-4 semanas)
   - MVP 1, 2 ou 3 (1-2 semanas)

3. **Iniciar implementação:**
   - Criar branch `feature/area-util-calculo`
   - Implementar seguindo checklist
   - Code review após cada semana

4. **Validar com usuários:**
   - Selecionar 5-10 terrenos com área conhecida
   - Comparar cálculo automático vs manual
   - Ajustar algoritmos se necessário

---

**Documento criado em:** 27 de maio de 2026  
**Autor:** Análise técnica automatizada  
**Status:** Aguardando validação técnica e decisão de abordagem
