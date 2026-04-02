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
