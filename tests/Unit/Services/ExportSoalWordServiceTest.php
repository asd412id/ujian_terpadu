<?php

namespace Tests\Unit\Services;

use App\Models\NarasiSoal;
use App\Models\OpsiJawaban;
use App\Models\PasanganSoal;
use App\Models\Soal;
use App\Services\ExportSoalWordService;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use Tests\TestCase;

class ExportSoalWordServiceTest extends TestCase
{
    protected ExportSoalWordService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExportSoalWordService();
    }

    // ── Helper: extract all text from a PhpWord Section ──

    private function extractSectionText(Section $section): string
    {
        $lines = [];
        foreach ($section->getElements() as $element) {
            if ($element instanceof TextRun) {
                $text = '';
                foreach ($element->getElements() as $child) {
                    if ($child instanceof Text) {
                        $text .= $child->getText();
                    }
                }
                $lines[] = $text;
            } elseif ($element instanceof Text) {
                $lines[] = $element->getText();
            } elseif (method_exists($element, 'getText')) {
                $lines[] = $element->getText() ?? '';
            }
        }
        return implode("\n", array_filter($lines, fn ($l) => $l !== ''));
    }

    // ── Helper: make a Soal model with relations ──

    private function makeSoal(array $attrs = [], array $opsi = [], array $pasangan = []): Soal
    {
        $defaults = [
            'id' => fake()->uuid(),
            'tipe_soal' => 'pg',
            'pertanyaan' => 'Apa ibu kota Indonesia?',
            'tingkat_kesulitan' => 'sedang',
            'bobot' => 1.0,
            'narasi_id' => null,
            'urutan_dalam_narasi' => 0,
            'nomor_urut_import' => null,
            'gambar_soal' => null,
        ];

        $soal = new Soal(array_merge($defaults, $attrs));
        $soal->id = $attrs['id'] ?? $defaults['id'];

        // Set relation
        $opsiCollection = new Collection();
        foreach ($opsi as $i => $o) {
            $opsiModel = new OpsiJawaban(array_merge([
                'id' => fake()->uuid(),
                'soal_id' => $soal->id,
                'urutan' => $i,
            ], $o));
            $opsiModel->id = $o['id'] ?? fake()->uuid();
            $opsiCollection->push($opsiModel);
        }
        $soal->setRelation('opsiJawaban', $opsiCollection);

        $pasanganCollection = new Collection();
        foreach ($pasangan as $i => $p) {
            $pasanganModel = new PasanganSoal(array_merge([
                'id' => fake()->uuid(),
                'soal_id' => $soal->id,
                'urutan' => $i,
            ], $p));
            $pasanganModel->id = $p['id'] ?? fake()->uuid();
            $pasanganCollection->push($pasanganModel);
        }
        $soal->setRelation('pasangan', $pasanganCollection);

        // narasi defaults to null relation
        if (! isset($attrs['narasi_id'])) {
            $soal->setRelation('narasi', null);
        }

        return $soal;
    }

    private function makeNarasi(array $attrs = []): NarasiSoal
    {
        $defaults = [
            'id' => fake()->uuid(),
            'judul' => 'Narasi 1',
            'konten' => '<p>Bacalah teks berikut dengan seksama.</p>',
            'is_active' => true,
        ];

        $narasi = new NarasiSoal(array_merge($defaults, $attrs));
        $narasi->id = $attrs['id'] ?? $defaults['id'];

        return $narasi;
    }

    // ═══════════════════════════════════════════════════════════════════
    // buildExportSegments tests
    // ═══════════════════════════════════════════════════════════════════

    public function test_build_segments_empty_collection(): void
    {
        $segments = $this->service->buildExportSegments(new Collection());
        $this->assertEmpty($segments);
    }

    public function test_build_segments_standalone_soal_only(): void
    {
        $soal1 = $this->makeSoal(['nomor_urut_import' => 0]);
        $soal2 = $this->makeSoal(['nomor_urut_import' => 1]);

        $segments = $this->service->buildExportSegments(new Collection([$soal1, $soal2]));

        $this->assertCount(2, $segments);
        $this->assertEquals('soal', $segments[0]['type']);
        $this->assertEquals('soal', $segments[1]['type']);
    }

    public function test_build_segments_narasi_group(): void
    {
        $narasi = $this->makeNarasi(['id' => 'narasi-1']);

        $soal1 = $this->makeSoal([
            'narasi_id' => 'narasi-1',
            'urutan_dalam_narasi' => 1,
            'nomor_urut_import' => 0,
        ]);
        $soal1->setRelation('narasi', $narasi);

        $soal2 = $this->makeSoal([
            'narasi_id' => 'narasi-1',
            'urutan_dalam_narasi' => 2,
            'nomor_urut_import' => 1,
        ]);
        $soal2->setRelation('narasi', $narasi);

        $segments = $this->service->buildExportSegments(new Collection([$soal1, $soal2]));

        // Expected: narasi_start, soal, soal, narasi_end
        $this->assertCount(4, $segments);
        $this->assertEquals('narasi_start', $segments[0]['type']);
        $this->assertSame($narasi, $segments[0]['narasi']);
        $this->assertEquals('soal', $segments[1]['type']);
        $this->assertEquals('soal', $segments[2]['type']);
        $this->assertEquals('narasi_end', $segments[3]['type']);
    }

    public function test_build_segments_mixed_standalone_and_narasi(): void
    {
        $narasi = $this->makeNarasi(['id' => 'narasi-1']);

        $standalone = $this->makeSoal(['nomor_urut_import' => 0]);

        $narasiSoal1 = $this->makeSoal([
            'narasi_id' => 'narasi-1',
            'urutan_dalam_narasi' => 1,
            'nomor_urut_import' => 1,
        ]);
        $narasiSoal1->setRelation('narasi', $narasi);

        $narasiSoal2 = $this->makeSoal([
            'narasi_id' => 'narasi-1',
            'urutan_dalam_narasi' => 2,
            'nomor_urut_import' => 2,
        ]);
        $narasiSoal2->setRelation('narasi', $narasi);

        $standalone2 = $this->makeSoal(['nomor_urut_import' => 3]);

        $segments = $this->service->buildExportSegments(
            new Collection([$standalone, $narasiSoal1, $narasiSoal2, $standalone2])
        );

        // Expected: soal(standalone), narasi_start, soal, soal, narasi_end, soal(standalone2)
        $this->assertCount(6, $segments);
        $this->assertEquals('soal', $segments[0]['type']);
        $this->assertEquals('narasi_start', $segments[1]['type']);
        $this->assertEquals('soal', $segments[2]['type']);
        $this->assertEquals('soal', $segments[3]['type']);
        $this->assertEquals('narasi_end', $segments[4]['type']);
        $this->assertEquals('soal', $segments[5]['type']);
    }

    public function test_build_segments_multiple_narasi_groups(): void
    {
        $narasi1 = $this->makeNarasi(['id' => 'narasi-1']);
        $narasi2 = $this->makeNarasi(['id' => 'narasi-2']);

        $soal1 = $this->makeSoal([
            'narasi_id' => 'narasi-1',
            'urutan_dalam_narasi' => 1,
            'nomor_urut_import' => 0,
        ]);
        $soal1->setRelation('narasi', $narasi1);

        $soal2 = $this->makeSoal([
            'narasi_id' => 'narasi-2',
            'urutan_dalam_narasi' => 1,
            'nomor_urut_import' => 2,
        ]);
        $soal2->setRelation('narasi', $narasi2);

        $segments = $this->service->buildExportSegments(new Collection([$soal1, $soal2]));

        // narasi_start(1), soal, narasi_end, narasi_start(2), soal, narasi_end
        $this->assertCount(6, $segments);
        $this->assertEquals('narasi_start', $segments[0]['type']);
        $this->assertEquals('narasi_end', $segments[2]['type']);
        $this->assertEquals('narasi_start', $segments[3]['type']);
        $this->assertEquals('narasi_end', $segments[5]['type']);
    }

    public function test_build_segments_narasi_soal_sorted_by_urutan(): void
    {
        $narasi = $this->makeNarasi(['id' => 'narasi-1']);

        // Insert out of order
        $soal2 = $this->makeSoal([
            'id' => 'soal-2',
            'narasi_id' => 'narasi-1',
            'urutan_dalam_narasi' => 2,
            'nomor_urut_import' => 1,
        ]);
        $soal2->setRelation('narasi', $narasi);

        $soal1 = $this->makeSoal([
            'id' => 'soal-1',
            'narasi_id' => 'narasi-1',
            'urutan_dalam_narasi' => 1,
            'nomor_urut_import' => 0,
        ]);
        $soal1->setRelation('narasi', $narasi);

        // Collection ordered by nomor_urut_import
        $segments = $this->service->buildExportSegments(new Collection([$soal1, $soal2]));

        // Soal within narasi should be by urutan_dalam_narasi
        $this->assertEquals('soal', $segments[1]['type']);
        $this->assertEquals('soal-1', $segments[1]['soal']->id);
        $this->assertEquals('soal', $segments[2]['type']);
        $this->assertEquals('soal-2', $segments[2]['soal']->id);
    }

    // ═══════════════════════════════════════════════════════════════════
    // generate() tests — full Word document output
    // ═══════════════════════════════════════════════════════════════════

    public function test_generate_empty_collection(): void
    {
        $phpWord = $this->service->generate(new Collection());

        $sections = $phpWord->getSections();
        $this->assertCount(1, $sections);

        $text = $this->extractSectionText($sections[0]);
        $this->assertStringContainsString('Tidak ada soal', $text);
    }

    public function test_generate_pg_soal_format(): void
    {
        $soal = $this->makeSoal(
            ['tipe_soal' => 'pg', 'pertanyaan' => 'Apa ibu kota Indonesia?'],
            [
                ['label' => 'A', 'teks' => 'Bandung', 'is_benar' => false],
                ['label' => 'B', 'teks' => 'Jakarta', 'is_benar' => true],
                ['label' => 'C', 'teks' => 'Surabaya', 'is_benar' => false],
                ['label' => 'D', 'teks' => 'Medan', 'is_benar' => false],
            ]
        );

        $phpWord = $this->service->generate(new Collection([$soal]));
        $text = $this->extractSectionText($phpWord->getSections()[0]);

        $this->assertStringContainsString('1. Apa ibu kota Indonesia?', $text);
        $this->assertStringContainsString('a. Bandung', $text);
        $this->assertStringContainsString('b. Jakarta', $text);
        $this->assertStringContainsString('c. Surabaya', $text);
        $this->assertStringContainsString('d. Medan', $text);
        $this->assertStringContainsString('Jawaban: B', $text);
        // PG should NOT have type tag
        $this->assertStringNotContainsString('[PG]', $text);
    }

    public function test_generate_pg_kompleks_soal_format(): void
    {
        $soal = $this->makeSoal(
            ['tipe_soal' => 'pg_kompleks', 'pertanyaan' => 'Pilih yang benar:'],
            [
                ['label' => 'A', 'teks' => 'Opsi A', 'is_benar' => true],
                ['label' => 'B', 'teks' => 'Opsi B', 'is_benar' => false],
                ['label' => 'C', 'teks' => 'Opsi C', 'is_benar' => true],
                ['label' => 'D', 'teks' => 'Opsi D', 'is_benar' => false],
                ['label' => 'E', 'teks' => 'Opsi E', 'is_benar' => true],
            ]
        );

        $phpWord = $this->service->generate(new Collection([$soal]));
        $text = $this->extractSectionText($phpWord->getSections()[0]);

        $this->assertStringContainsString('1. [PG_KOMPLEKS]', $text);
        $this->assertStringContainsString('Jawaban: A, C, E', $text);
    }

    public function test_generate_benar_salah_format(): void
    {
        $soal = $this->makeSoal(
            ['tipe_soal' => 'benar_salah', 'pertanyaan' => 'Tentukan benar atau salah:'],
            [
                ['label' => '1', 'teks' => 'Jakarta ibu kota Indonesia', 'is_benar' => true],
                ['label' => '2', 'teks' => 'Indonesia di benua Eropa', 'is_benar' => false],
                ['label' => '3', 'teks' => 'Pancasila memiliki 5 sila', 'is_benar' => true],
            ]
        );

        $phpWord = $this->service->generate(new Collection([$soal]));
        $text = $this->extractSectionText($phpWord->getSections()[0]);

        $this->assertStringContainsString('1. [BENAR_SALAH]', $text);
        $this->assertStringContainsString('1) Jakarta ibu kota Indonesia (BENAR)', $text);
        $this->assertStringContainsString('2) Indonesia di benua Eropa (SALAH)', $text);
        $this->assertStringContainsString('3) Pancasila memiliki 5 sila (BENAR)', $text);
    }

    public function test_generate_menjodohkan_format(): void
    {
        $soal = $this->makeSoal(
            ['tipe_soal' => 'menjodohkan', 'pertanyaan' => 'Jodohkan berikut:'],
            [], // no opsi
            [ // pasangan
                ['kiri_teks' => 'Indonesia', 'kanan_teks' => 'Jakarta', 'kiri_gambar' => null, 'kanan_gambar' => null],
                ['kiri_teks' => 'Malaysia', 'kanan_teks' => 'Kuala Lumpur', 'kiri_gambar' => null, 'kanan_gambar' => null],
            ]
        );

        $phpWord = $this->service->generate(new Collection([$soal]));
        $text = $this->extractSectionText($phpWord->getSections()[0]);

        $this->assertStringContainsString('1. [MENJODOHKAN]', $text);
        $this->assertStringContainsString('Indonesia = Jakarta', $text);
        $this->assertStringContainsString('Malaysia = Kuala Lumpur', $text);
    }

    public function test_generate_isian_format(): void
    {
        $soal = $this->makeSoal(
            ['tipe_soal' => 'isian', 'pertanyaan' => 'Ibu kota Indonesia adalah ...'],
            [
                ['label' => 'KUNCI', 'teks' => 'Jakarta', 'is_benar' => true],
            ]
        );

        $phpWord = $this->service->generate(new Collection([$soal]));
        $text = $this->extractSectionText($phpWord->getSections()[0]);

        $this->assertStringContainsString('1. [ISIAN]', $text);
        $this->assertStringContainsString('Jawaban: Jakarta', $text);
    }

    public function test_generate_essay_format(): void
    {
        $soal = $this->makeSoal(
            ['tipe_soal' => 'essay', 'pertanyaan' => 'Jelaskan Pancasila.'],
            [
                ['label' => 'KUNCI', 'teks' => 'Pancasila adalah dasar negara...', 'is_benar' => true],
            ]
        );

        $phpWord = $this->service->generate(new Collection([$soal]));
        $text = $this->extractSectionText($phpWord->getSections()[0]);

        $this->assertStringContainsString('1. [ESSAY]', $text);
        $this->assertStringContainsString('Jawaban: Pancasila adalah dasar negara', $text);
    }

    public function test_generate_essay_without_kunci(): void
    {
        $soal = $this->makeSoal(
            ['tipe_soal' => 'essay', 'pertanyaan' => 'Jelaskan pendapatmu.'],
            [] // no opsi/kunci
        );

        $phpWord = $this->service->generate(new Collection([$soal]));
        $text = $this->extractSectionText($phpWord->getSections()[0]);

        $this->assertStringContainsString('1. [ESSAY]', $text);
        $this->assertStringContainsString('Jawaban:', $text);
    }

    // ═══════════════════════════════════════════════════════════════════
    // Meta tags tests
    // ═══════════════════════════════════════════════════════════════════

    public function test_generate_with_non_default_tingkat(): void
    {
        $soal = $this->makeSoal(
            ['tipe_soal' => 'pg', 'pertanyaan' => 'Soal sulit.', 'tingkat_kesulitan' => 'sulit'],
            [
                ['label' => 'A', 'teks' => 'Opsi A', 'is_benar' => true],
                ['label' => 'B', 'teks' => 'Opsi B', 'is_benar' => false],
            ]
        );

        $phpWord = $this->service->generate(new Collection([$soal]));
        $text = $this->extractSectionText($phpWord->getSections()[0]);

        $this->assertStringContainsString('[tingkat: sulit]', $text);
    }

    public function test_generate_with_default_tingkat_no_tag(): void
    {
        $soal = $this->makeSoal(
            ['tipe_soal' => 'pg', 'pertanyaan' => 'Soal biasa.', 'tingkat_kesulitan' => 'sedang'],
            [
                ['label' => 'A', 'teks' => 'Opsi A', 'is_benar' => true],
            ]
        );

        $phpWord = $this->service->generate(new Collection([$soal]));
        $text = $this->extractSectionText($phpWord->getSections()[0]);

        $this->assertStringNotContainsString('[tingkat:', $text);
    }

    public function test_generate_with_non_default_bobot(): void
    {
        $soal = $this->makeSoal(
            ['tipe_soal' => 'essay', 'pertanyaan' => 'Soal bobot 3.', 'bobot' => 3.0],
            []
        );

        $phpWord = $this->service->generate(new Collection([$soal]));
        $text = $this->extractSectionText($phpWord->getSections()[0]);

        $this->assertStringContainsString('[bobot: 3]', $text);
    }

    public function test_generate_with_default_bobot_no_tag(): void
    {
        $soal = $this->makeSoal(
            ['tipe_soal' => 'pg', 'pertanyaan' => 'Soal biasa.', 'bobot' => 1.0],
            [
                ['label' => 'A', 'teks' => 'Ya', 'is_benar' => true],
            ]
        );

        $phpWord = $this->service->generate(new Collection([$soal]));
        $text = $this->extractSectionText($phpWord->getSections()[0]);

        $this->assertStringNotContainsString('[bobot:', $text);
    }

    public function test_generate_with_both_meta_tags(): void
    {
        $soal = $this->makeSoal(
            ['tipe_soal' => 'pg_kompleks', 'pertanyaan' => 'Multi meta.', 'tingkat_kesulitan' => 'mudah', 'bobot' => 2.5],
            [
                ['label' => 'A', 'teks' => 'Opsi A', 'is_benar' => true],
            ]
        );

        $phpWord = $this->service->generate(new Collection([$soal]));
        $text = $this->extractSectionText($phpWord->getSections()[0]);

        $this->assertStringContainsString('[tingkat: mudah]', $text);
        $this->assertStringContainsString('[bobot: 2.5]', $text);
    }

    // ═══════════════════════════════════════════════════════════════════
    // Narasi in full generate tests
    // ═══════════════════════════════════════════════════════════════════

    public function test_generate_with_narasi_group(): void
    {
        $narasi = $this->makeNarasi([
            'id' => 'narasi-1',
            'konten' => '<p>Bacalah teks berikut.</p>',
        ]);

        $soal1 = $this->makeSoal(
            [
                'tipe_soal' => 'pg',
                'pertanyaan' => 'Pertanyaan narasi 1?',
                'narasi_id' => 'narasi-1',
                'urutan_dalam_narasi' => 1,
                'nomor_urut_import' => 0,
            ],
            [
                ['label' => 'A', 'teks' => 'Ya', 'is_benar' => true],
                ['label' => 'B', 'teks' => 'Tidak', 'is_benar' => false],
            ]
        );
        $soal1->setRelation('narasi', $narasi);

        $soal2 = $this->makeSoal(
            [
                'tipe_soal' => 'essay',
                'pertanyaan' => 'Pertanyaan narasi 2?',
                'narasi_id' => 'narasi-1',
                'urutan_dalam_narasi' => 2,
                'nomor_urut_import' => 1,
            ],
            []
        );
        $soal2->setRelation('narasi', $narasi);

        $phpWord = $this->service->generate(new Collection([$soal1, $soal2]));
        $text = $this->extractSectionText($phpWord->getSections()[0]);

        // Check narasi markers
        $this->assertStringContainsString('[NARASI]', $text);
        $this->assertStringContainsString('Bacalah teks berikut.', $text);
        $this->assertStringContainsString('[/NARASI]', $text);
        $this->assertStringContainsString('[/NARASI_SOAL]', $text);

        // Check soal within narasi
        $this->assertStringContainsString('1. Pertanyaan narasi 1?', $text);
        $this->assertStringContainsString('2. [ESSAY] Pertanyaan narasi 2?', $text);

        // Check ordering: [NARASI] before soal, [/NARASI_SOAL] after
        $narasiStartPos = strpos($text, '[NARASI]');
        $narasiEndPos = strpos($text, '[/NARASI]');
        $soal1Pos = strpos($text, '1. Pertanyaan narasi 1?');
        $narasiSoalEndPos = strpos($text, '[/NARASI_SOAL]');

        $this->assertLessThan($narasiEndPos, $narasiStartPos);
        $this->assertLessThan($soal1Pos, $narasiEndPos);
        $this->assertLessThan($narasiSoalEndPos, $soal1Pos);
    }

    public function test_generate_mixed_narasi_and_standalone(): void
    {
        $narasi = $this->makeNarasi(['id' => 'narasi-1', 'konten' => '<p>Teks narasi.</p>']);

        $standalone = $this->makeSoal(
            [
                'tipe_soal' => 'pg',
                'pertanyaan' => 'Soal standalone',
                'nomor_urut_import' => 0,
            ],
            [
                ['label' => 'A', 'teks' => 'Opsi', 'is_benar' => true],
            ]
        );

        $narasiSoal = $this->makeSoal(
            [
                'tipe_soal' => 'pg',
                'pertanyaan' => 'Soal narasi',
                'narasi_id' => 'narasi-1',
                'urutan_dalam_narasi' => 1,
                'nomor_urut_import' => 1,
            ],
            [
                ['label' => 'A', 'teks' => 'Opsi', 'is_benar' => true],
            ]
        );
        $narasiSoal->setRelation('narasi', $narasi);

        $phpWord = $this->service->generate(new Collection([$standalone, $narasiSoal]));
        $text = $this->extractSectionText($phpWord->getSections()[0]);

        // Standalone should be numbered 1, narasi soal should be numbered 2
        $this->assertStringContainsString('1. Soal standalone', $text);
        $this->assertStringContainsString('2. Soal narasi', $text);

        // [NARASI] should appear between standalone and narasi soal
        $standalonePos = strpos($text, '1. Soal standalone');
        $narasiPos = strpos($text, '[NARASI]');
        $this->assertLessThan($narasiPos, $standalonePos);
    }

    // ═══════════════════════════════════════════════════════════════════
    // Edge cases
    // ═══════════════════════════════════════════════════════════════════

    public function test_generate_html_pertanyaan_stripped_to_plain(): void
    {
        $soal = $this->makeSoal(
            ['tipe_soal' => 'essay', 'pertanyaan' => '<p>Apa itu <strong>Pancasila</strong>?</p>'],
            []
        );

        $phpWord = $this->service->generate(new Collection([$soal]));
        $text = $this->extractSectionText($phpWord->getSections()[0]);

        // Should contain plain text, no HTML tags
        $this->assertStringContainsString('Apa itu Pancasila?', $text);
        $this->assertStringNotContainsString('<strong>', $text);
        $this->assertStringNotContainsString('<p>', $text);
    }

    public function test_generate_multiple_soal_sequential_numbering(): void
    {
        $soalList = new Collection();
        for ($i = 0; $i < 5; $i++) {
            $soal = $this->makeSoal(
                ['tipe_soal' => 'essay', 'pertanyaan' => "Soal ke $i", 'nomor_urut_import' => $i],
                []
            );
            $soalList->push($soal);
        }

        $phpWord = $this->service->generate($soalList);
        $text = $this->extractSectionText($phpWord->getSections()[0]);

        for ($i = 0; $i < 5; $i++) {
            $nomor = $i + 1;
            $this->assertStringContainsString("{$nomor}. [ESSAY] Soal ke $i", $text);
        }
    }

    public function test_generate_soal_with_mixed_types_in_narasi(): void
    {
        $narasi = $this->makeNarasi(['id' => 'narasi-1', 'konten' => '<p>Teks</p>']);

        $pg = $this->makeSoal(
            [
                'tipe_soal' => 'pg',
                'pertanyaan' => 'PG dalam narasi?',
                'narasi_id' => 'narasi-1',
                'urutan_dalam_narasi' => 1,
                'nomor_urut_import' => 0,
            ],
            [
                ['label' => 'A', 'teks' => 'Ya', 'is_benar' => true],
                ['label' => 'B', 'teks' => 'Tidak', 'is_benar' => false],
            ]
        );
        $pg->setRelation('narasi', $narasi);

        $isian = $this->makeSoal(
            [
                'tipe_soal' => 'isian',
                'pertanyaan' => 'Isian dalam narasi?',
                'narasi_id' => 'narasi-1',
                'urutan_dalam_narasi' => 2,
                'nomor_urut_import' => 1,
            ],
            [
                ['label' => 'KUNCI', 'teks' => 'Jawaban isian', 'is_benar' => true],
            ]
        );
        $isian->setRelation('narasi', $narasi);

        $bs = $this->makeSoal(
            [
                'tipe_soal' => 'benar_salah',
                'pertanyaan' => 'BS dalam narasi?',
                'narasi_id' => 'narasi-1',
                'urutan_dalam_narasi' => 3,
                'nomor_urut_import' => 2,
            ],
            [
                ['label' => '1', 'teks' => 'Pernyataan satu', 'is_benar' => true],
                ['label' => '2', 'teks' => 'Pernyataan dua', 'is_benar' => false],
            ]
        );
        $bs->setRelation('narasi', $narasi);

        $phpWord = $this->service->generate(new Collection([$pg, $isian, $bs]));
        $text = $this->extractSectionText($phpWord->getSections()[0]);

        // All three types should be present within the same narasi group
        $this->assertStringContainsString('1. PG dalam narasi?', $text);
        $this->assertStringContainsString('2. [ISIAN] Isian dalam narasi?', $text);
        $this->assertStringContainsString('3. [BENAR_SALAH] BS dalam narasi?', $text);
    }

    public function test_extract_storage_paths_from_img_tags(): void
    {
        // Use reflection to test private method
        $method = new \ReflectionMethod(ExportSoalWordService::class, 'extractStoragePaths');
        $method->setAccessible(true);

        $html = '<p>Teks <img src="http://localhost/storage/soal/gambar/abc.png" alt="gambar"> lanjut</p>';
        $paths = $method->invoke($this->service, $html);

        $this->assertCount(1, $paths);
        $this->assertEquals('soal/gambar/abc.png', $paths[0]);
    }

    public function test_extract_storage_paths_ignores_external_images(): void
    {
        $method = new \ReflectionMethod(ExportSoalWordService::class, 'extractStoragePaths');
        $method->setAccessible(true);

        $html = '<img src="https://external.com/image.png">';
        $paths = $method->invoke($this->service, $html);

        $this->assertEmpty($paths);
    }

    public function test_extract_storage_paths_multiple_images(): void
    {
        $method = new \ReflectionMethod(ExportSoalWordService::class, 'extractStoragePaths');
        $method->setAccessible(true);

        $html = '<img src="/storage/soal/gambar/a.png"><img src="/storage/soal/gambar/b.jpg">';
        $paths = $method->invoke($this->service, $html);

        $this->assertCount(2, $paths);
        $this->assertEquals('soal/gambar/a.png', $paths[0]);
        $this->assertEquals('soal/gambar/b.jpg', $paths[1]);
    }

    public function test_build_meta_tags_empty_for_defaults(): void
    {
        $method = new \ReflectionMethod(ExportSoalWordService::class, 'buildMetaTags');
        $method->setAccessible(true);

        $soal = $this->makeSoal(['tingkat_kesulitan' => 'sedang', 'bobot' => 1.0]);
        $tags = $method->invoke($this->service, $soal);

        $this->assertEquals('', $tags);
    }

    public function test_build_meta_tags_both_non_default(): void
    {
        $method = new \ReflectionMethod(ExportSoalWordService::class, 'buildMetaTags');
        $method->setAccessible(true);

        $soal = $this->makeSoal(['tingkat_kesulitan' => 'sulit', 'bobot' => 2.0]);
        $tags = $method->invoke($this->service, $soal);

        $this->assertStringContainsString('[tingkat: sulit]', $tags);
        $this->assertStringContainsString('[bobot: 2]', $tags);
    }
}
