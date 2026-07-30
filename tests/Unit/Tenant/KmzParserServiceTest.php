<?php

namespace Tests\Unit\Tenant;

use App\Services\Tenant\KmzParserService;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class KmzParserServiceTest extends TestCase
{
    private KmzParserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(KmzParserService::class);
    }

    // -------------------------------------------------------------------------
    // KML válido
    // -------------------------------------------------------------------------

    public function test_parse_kml_com_polygon_retorna_coordenadas_corretas(): void
    {
        $kml = $this->kmlComPolygon();
        $file = $this->makeKmlFile($kml);

        $coords = $this->service->parse($file);

        $this->assertCount(3, $coords); // 4 pontos - 1 fechamento duplicado
        $this->assertArrayHasKey('lat', $coords[0]);
        $this->assertArrayHasKey('lng', $coords[0]);
        $this->assertEqualsWithDelta(-23.5505, $coords[0]['lat'], 0.00001);
        $this->assertEqualsWithDelta(-46.6333, $coords[0]['lng'], 0.00001);
    }

    public function test_parse_kml_sem_fechamento_duplicado_mantem_todos_os_pontos(): void
    {
        $kml = <<<'KML'
        <?xml version="1.0" encoding="UTF-8"?>
        <kml xmlns="http://www.opengis.net/kml/2.2">
          <Placemark>
            <Polygon>
              <outerBoundaryIs>
                <LinearRing>
                  <coordinates>
                    -46.6333,-23.5505,0
                    -46.6340,-23.5510,0
                    -46.6320,-23.5515,0
                  </coordinates>
                </LinearRing>
              </outerBoundaryIs>
            </Polygon>
          </Placemark>
        </kml>
        KML;

        $coords = $this->service->parse($this->makeKmlFile($kml));

        $this->assertCount(3, $coords);
    }

    public function test_parse_kml_sem_namespace_funciona(): void
    {
        $kml = <<<'KML'
        <?xml version="1.0" encoding="UTF-8"?>
        <kml>
          <Placemark>
            <Polygon>
              <outerBoundaryIs>
                <LinearRing>
                  <coordinates>-46.6333,-23.5505,0 -46.6340,-23.5510,0 -46.6320,-23.5515,0</coordinates>
                </LinearRing>
              </outerBoundaryIs>
            </Polygon>
          </Placemark>
        </kml>
        KML;

        $coords = $this->service->parse($this->makeKmlFile($kml));

        $this->assertCount(3, $coords);
        $this->assertEqualsWithDelta(-23.5505, $coords[0]['lat'], 0.00001);
    }

    public function test_parse_kml_com_linestring_como_fallback(): void
    {
        $kml = <<<'KML'
        <?xml version="1.0" encoding="UTF-8"?>
        <kml xmlns="http://www.opengis.net/kml/2.2">
          <Placemark>
            <LineString>
              <coordinates>
                -46.6333,-23.5505,0
                -46.6340,-23.5510,0
                -46.6320,-23.5515,0
              </coordinates>
            </LineString>
          </Placemark>
        </kml>
        KML;

        $coords = $this->service->parse($this->makeKmlFile($kml));

        $this->assertCount(3, $coords);
    }

    public function test_parse_kml_usa_primeiro_polygon_quando_ha_multiplos(): void
    {
        $kml = <<<'KML'
        <?xml version="1.0" encoding="UTF-8"?>
        <kml xmlns="http://www.opengis.net/kml/2.2">
          <Placemark>
            <Polygon>
              <outerBoundaryIs>
                <LinearRing>
                  <coordinates>-46.6333,-23.5505,0 -46.6340,-23.5510,0 -46.6320,-23.5515,0</coordinates>
                </LinearRing>
              </outerBoundaryIs>
            </Polygon>
          </Placemark>
          <Placemark>
            <Polygon>
              <outerBoundaryIs>
                <LinearRing>
                  <coordinates>-40.0000,-10.0000,0 -40.0010,-10.0010,0 -40.0020,-10.0020,0</coordinates>
                </LinearRing>
              </outerBoundaryIs>
            </Polygon>
          </Placemark>
        </kml>
        KML;

        $coords = $this->service->parse($this->makeKmlFile($kml));

        // Deve usar o primeiro polígono
        $this->assertEqualsWithDelta(-23.5505, $coords[0]['lat'], 0.00001);
    }

    public function test_parse_many_retorna_todos_os_poligonos_com_metadados_e_limites(): void
    {
        $kml = <<<'KML'
        <?xml version="1.0" encoding="UTF-8"?>
        <kml xmlns="http://www.opengis.net/kml/2.2">
          <Document>
            <Placemark>
              <name>Área Norte</name>
              <MultiGeometry>
                <Polygon><outerBoundaryIs><LinearRing><coordinates>
                  -46.63,-23.55,0 -46.64,-23.56,0 -46.62,-23.57,0 -46.63,-23.55,0
                </coordinates></LinearRing></outerBoundaryIs></Polygon>
                <Polygon><outerBoundaryIs><LinearRing><coordinates>
                  -40.00,-10.00,0 -40.01,-10.01,0 -39.99,-10.02,0 -40.00,-10.00,0
                </coordinates></LinearRing></outerBoundaryIs></Polygon>
              </MultiGeometry>
            </Placemark>
          </Document>
        </kml>
        KML;

        $geometries = $this->service->parseMany($this->makeKmlFile($kml));

        $this->assertCount(2, $geometries);
        $this->assertSame('Área Norte', $geometries[0]['placemark_name']);
        $this->assertSame(0, $geometries[0]['geometry_index']);
        $this->assertSame(1, $geometries[1]['geometry_index']);
        $this->assertSame(-46.64, $geometries[0]['bounds']['min_lng']);
        $this->assertSame(-23.55, $geometries[0]['bounds']['max_lat']);
        $this->assertSame(64, strlen($geometries[0]['geometry_hash']));
    }

    // -------------------------------------------------------------------------
    // KMZ válido
    // -------------------------------------------------------------------------

    public function test_parse_kmz_extrai_kml_e_retorna_coordenadas(): void
    {
        $file = $this->makeKmzFile($this->kmlComPolygon());

        $coords = $this->service->parse($file);

        $this->assertCount(3, $coords);
        $this->assertEqualsWithDelta(-23.5505, $coords[0]['lat'], 0.00001);
        $this->assertEqualsWithDelta(-46.6333, $coords[0]['lng'], 0.00001);
    }

    public function test_parse_many_kmz_processa_todas_as_entradas_kml(): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'kmz_many_').'.kmz';
        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('primeiro.kml', $this->kmlComPolygon());
        $zip->addFromString('pasta/segundo.kml', str_replace('-46.6333', '-40.0000', $this->kmlComPolygon()));
        $zip->close();

        $file = new UploadedFile($tmpPath, 'multiplos.kmz', 'application/zip', null, true);
        $geometries = $this->service->parseMany($file);

        $this->assertCount(2, $geometries);
        $this->assertSame(['primeiro.kml', 'pasta/segundo.kml'], array_column($geometries, 'source_entry'));
    }

    public function test_parse_many_rejeita_poligono_com_furo(): void
    {
        $kml = <<<'KML'
        <kml xmlns="http://www.opengis.net/kml/2.2"><Placemark><Polygon>
          <outerBoundaryIs><LinearRing><coordinates>
            -46,-23,0 -47,-24,0 -45,-24,0 -46,-23,0
          </coordinates></LinearRing></outerBoundaryIs>
          <innerBoundaryIs><LinearRing><coordinates>
            -46,-23.2,0 -46.1,-23.3,0 -45.9,-23.3,0 -46,-23.2,0
          </coordinates></LinearRing></innerBoundaryIs>
        </Polygon></Placemark></kml>
        KML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/áreas internas/i');

        $this->service->parseMany($this->makeKmlFile($kml));
    }

    // -------------------------------------------------------------------------
    // Erros esperados
    // -------------------------------------------------------------------------

    public function test_extensao_invalida_lanca_exception(): void
    {
        $file = UploadedFile::fake()->create('mapa.geojson', 1, 'application/json');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/extensão de arquivo não suportada/i');

        $this->service->parse($file);
    }

    public function test_xml_malformado_lanca_exception(): void
    {
        $file = $this->makeKmlFile('<kml><unclosed>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/xml inválido/i');

        $this->service->parse($file);
    }

    public function test_kml_sem_polygon_nem_linestring_lanca_exception(): void
    {
        $kml = <<<'KML'
        <?xml version="1.0" encoding="UTF-8"?>
        <kml xmlns="http://www.opengis.net/kml/2.2">
          <Placemark><name>Sem geometria</name></Placemark>
        </kml>
        KML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/nenhum polígono/i');

        $this->service->parse($this->makeKmlFile($kml));
    }

    public function test_kmz_corrompido_lanca_exception(): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'kmz_bad_').'.kmz';
        file_put_contents($tmpPath, 'isto nao e um zip');
        $file = new UploadedFile($tmpPath, 'corrompido.kmz', 'application/zip', null, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/não foi possível abrir/i');

        $this->service->parse($file);

        @unlink($tmpPath);
    }

    public function test_kmz_sem_kml_interno_lanca_exception(): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'kmz_nokml_').'.kmz';
        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('readme.txt', 'sem kml aqui');
        $zip->close();

        $file = new UploadedFile($tmpPath, 'semkml.kmz', 'application/zip', null, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/nenhum arquivo .kml encontrado/i');

        $this->service->parse($file);

        @unlink($tmpPath);
    }

    public function test_kmz_com_itens_demais_lanca_exception(): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'kmz_entries_').'.kmz';
        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach (range(1, 101) as $index) {
            $zip->addFromString("item-{$index}.txt", 'conteudo');
        }

        $zip->addFromString('doc.kml', $this->kmlComPolygon());
        $zip->close();

        $file = new UploadedFile($tmpPath, 'muitos-itens.kmz', 'application/zip', null, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/itens demais/i');

        $this->service->parse($file);
    }

    public function test_kmz_com_kml_descompactado_acima_do_limite_lanca_exception(): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'kmz_large_').'.kmz';
        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('doc.kml', '<kml>'.str_repeat(' ', (20 * 1024 * 1024) + 1).'</kml>');
        $zip->close();

        $file = new UploadedFile($tmpPath, 'grande.kmz', 'application/zip', null, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/excede o limite de 20 MB/i');

        $this->service->parse($file);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function kmlComPolygon(): string
    {
        return <<<'KML'
        <?xml version="1.0" encoding="UTF-8"?>
        <kml xmlns="http://www.opengis.net/kml/2.2">
          <Placemark>
            <Polygon>
              <outerBoundaryIs>
                <LinearRing>
                  <coordinates>
                    -46.6333,-23.5505,0
                    -46.6340,-23.5510,0
                    -46.6320,-23.5515,0
                    -46.6333,-23.5505,0
                  </coordinates>
                </LinearRing>
              </outerBoundaryIs>
            </Polygon>
          </Placemark>
        </kml>
        KML;
    }

    private function makeKmlFile(string $content): UploadedFile
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'kml_test_');
        file_put_contents($tmpPath, $content);

        return new UploadedFile($tmpPath, 'test.kml', 'application/vnd.google-earth.kml+xml', null, true);
    }

    private function makeKmzFile(string $kmlContent): UploadedFile
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'kmz_test_').'.kmz';
        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('doc.kml', $kmlContent);
        $zip->close();

        return new UploadedFile($tmpPath, 'test.kmz', 'application/zip', null, true);
    }
}
