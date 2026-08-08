<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SafeUploadExtension;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class SafeUploadExtensionTest extends TestCase
{
    public function test_resolves_client_extension_for_zip_based_formats(): void
    {
        $kmz = $this->zippedUpload('mapa.kmz', 'doc.kml', '<?xml version="1.0"?><kml/>');
        $this->assertSame('kmz', SafeUploadExtension::resolve($kmz, SafeUploadExtension::DOCUMENT_EXTENSIONS));

        $xlsx = $this->zippedUpload('planilha.xlsx', '[Content_Types].xml', '<Types/>');
        $this->assertSame('xlsx', SafeUploadExtension::resolve($xlsx, SafeUploadExtension::DOCUMENT_EXTENSIONS));
    }

    public function test_resolves_kml_even_when_content_is_detected_as_xml(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kml');
        self::assertIsString($path);
        file_put_contents($path, '<?xml version="1.0"?><kml xmlns="http://www.opengis.net/kml/2.2"></kml>');
        $file = new UploadedFile($path, 'area.kml', 'application/vnd.google-earth.kml+xml', null, true);

        $this->assertSame('kml', SafeUploadExtension::resolve($file, SafeUploadExtension::DOCUMENT_EXTENSIONS));
        $this->assertSame('xml', $file->guessExtension());
    }

    public function test_rejects_spoofed_extension_when_content_does_not_match(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bin');
        self::assertIsString($path);
        file_put_contents($path, random_bytes(64));
        $file = new UploadedFile($path, 'malware.pdf', 'application/pdf', null, true);

        $this->assertSame('bin', $file->guessExtension());
        $this->assertNull(SafeUploadExtension::resolve($file, SafeUploadExtension::DOCUMENT_EXTENSIONS));
    }

    public function test_accepts_pdf_content(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        self::assertIsString($path);
        file_put_contents($path, "%PDF-1.4\n%fake\n");
        $file = new UploadedFile($path, 'matricula.pdf', 'application/pdf', null, true);

        $this->assertSame('pdf', SafeUploadExtension::resolve($file, SafeUploadExtension::DOCUMENT_EXTENSIONS));
    }

    private function zippedUpload(string $clientName, string $entryName, string $entryContent): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        self::assertIsString($path);
        $zip = new ZipArchive;
        self::assertTrue($zip->open($path, ZipArchive::OVERWRITE));
        $zip->addFromString($entryName, $entryContent);
        $zip->close();

        return new UploadedFile($path, $clientName, 'application/zip', null, true);
    }
}
