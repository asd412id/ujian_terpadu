<?php

namespace App\Services;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class SoalTemplateService
{
    /**
     * Download a Word template for soal import.
     */
    public function templateWord(): StreamedResponse
    {
        $phpWord = new PhpWord();

        $titleStyle = ['bold' => true, 'size' => 14];
        $headingStyle = ['bold' => true, 'size' => 11, 'color' => '1a56db'];
        $normalStyle = ['size' => 11];
        $boldStyle = ['bold' => true, 'size' => 11];
        $italicStyle = ['italic' => true, 'size' => 10, 'color' => '6b7280'];
        $noteStyle = ['italic' => true, 'size' => 10, 'color' => 'dc2626'];

        $section = $phpWord->addSection();

        $section->addText('Template Import Soal', $titleStyle);
        $section->addText('Gunakan format berikut untuk mengimport soal. Setiap soal diawali nomor urut.', $italicStyle);
        $section->addTextBreak(1);

        // -- PG --
        $section->addText('PILIHAN GANDA', $headingStyle);
        $section->addTextBreak(0);
        $section->addText('1. Apa ibu kota Indonesia?', $boldStyle);
        $section->addText('    Gambar: peta_indonesia.png', $noteStyle);
        $section->addText('    a. Bandung', $normalStyle);
        $section->addText('    b. Surabaya', $normalStyle);
        $section->addText('    c. Jakarta', $normalStyle);
        $section->addText('    d. Yogyakarta', $normalStyle);
        $section->addText('    Jawaban: C', $normalStyle);
        $section->addTextBreak(1);

        // -- PG dengan gambar opsi --
        $section->addText('PILIHAN GANDA DENGAN GAMBAR OPSI', $headingStyle);
        $section->addTextBreak(0);
        $section->addText('2. Manakah gambar bendera Indonesia?', $boldStyle);
        $section->addText('    a. Bendera Merah Putih | gambar: bendera_id.png', $normalStyle);
        $section->addText('    b. Bendera Jepang | gambar: bendera_jp.png', $normalStyle);
        $section->addText('    c. Bendera Thailand | gambar: bendera_th.png', $normalStyle);
        $section->addText('    d. Bendera Malaysia | gambar: bendera_my.png', $normalStyle);
        $section->addText('    Jawaban: A', $normalStyle);
        $section->addTextBreak(1);

        // -- PG Kompleks --
        $section->addText('PILIHAN GANDA KOMPLEKS', $headingStyle);
        $section->addTextBreak(0);
        $section->addText('3. [PG_KOMPLEKS] Manakah yang merupakan bilangan prima?', $boldStyle);
        $section->addText('    a. 2', $normalStyle);
        $section->addText('    b. 4', $normalStyle);
        $section->addText('    c. 7', $normalStyle);
        $section->addText('    d. 9', $normalStyle);
        $section->addText('    e. 11', $normalStyle);
        $section->addText('    Jawaban: A, C, E', $normalStyle);
        $section->addTextBreak(1);

        // -- Menjodohkan --
        $section->addText('MENJODOHKAN', $headingStyle);
        $section->addTextBreak(0);
        $section->addText('4. [MENJODOHKAN] Jodohkan negara dengan ibu kotanya:', $boldStyle);
        $section->addText('    Indonesia | gambar: indonesia.png = Jakarta | gambar: jakarta.png', $normalStyle);
        $section->addText('    Jepang = Tokyo', $normalStyle);
        $section->addText('    Thailand = Bangkok', $normalStyle);
        $section->addText('    Malaysia = Kuala Lumpur', $normalStyle);
        $section->addText('    (Format gambar opsional: kiri | gambar: file.png = kanan | gambar: file.png)', $italicStyle);
        $section->addTextBreak(1);

        // -- Isian --
        $section->addText('ISIAN SINGKAT', $headingStyle);
        $section->addTextBreak(0);
        $section->addText('5. [ISIAN] Ibu kota Jepang adalah ___', $boldStyle);
        $section->addText('    Jawaban: Tokyo', $normalStyle);
        $section->addTextBreak(1);

        // -- Essay --
        $section->addText('ESSAY', $headingStyle);
        $section->addTextBreak(0);
        $section->addText('6. [ESSAY] Jelaskan proses terjadinya hujan!', $boldStyle);
        $section->addText('    Gambar: siklus_air.png', $noteStyle);
        $section->addText('    Jawaban: (tulis jawaban contoh atau kosongkan)', $normalStyle);
        $section->addTextBreak(1);

        // -- Benar/Salah --
        $section->addText('BENAR / SALAH', $headingStyle);
        $section->addTextBreak(0);
        $section->addText('7. [BENAR_SALAH] Tentukan benar atau salah pernyataan berikut tentang air:', $boldStyle);
        $section->addText('    1) Air mendidih pada suhu 100°C di tekanan standar (BENAR)', $normalStyle);
        $section->addText('    2) Es memiliki massa jenis lebih besar dari air (SALAH)', $normalStyle);
        $section->addText('    3) H2O adalah rumus kimia garam dapur (SALAH)', $normalStyle);
        $section->addText('    4) Air merupakan pelarut universal (BENAR)', $normalStyle);
        $section->addTextBreak(2);

        // -- Narasi / Teks Bacaan --
        $narasiHeading = ['bold' => true, 'size' => 12, 'color' => '7c3aed'];
        $section->addText('SOAL BERNARASI (TEKS BACAAN)', $narasiHeading);
        $section->addText('Gunakan tag [NARASI] ... [/NARASI] untuk menandai teks bacaan yang dipakai bersama oleh beberapa soal.', $italicStyle);
        $section->addTextBreak(0);
        $section->addText('[NARASI]', $boldStyle);
        $section->addText('Bacalah teks berikut!', $normalStyle);
        $section->addTextBreak(0);
        $section->addText('Indonesia adalah negara kepulauan terbesar di dunia yang terletak di Asia Tenggara. Indonesia memiliki lebih dari 17.000 pulau dengan keberagaman budaya, bahasa, dan adat istiadat yang sangat kaya. Semboyan negara Indonesia adalah "Bhinneka Tunggal Ika" yang berarti berbeda-beda tetapi tetap satu.', $normalStyle);
        $section->addText('[/NARASI]', $boldStyle);
        $section->addTextBreak(0);
        $section->addText('8. Berapa jumlah pulau di Indonesia menurut teks di atas?', $boldStyle);
        $section->addText('    a. 13.000', $normalStyle);
        $section->addText('    b. 15.000', $normalStyle);
        $section->addText('    c. 17.000', $normalStyle);
        $section->addText('    d. 19.000', $normalStyle);
        $section->addText('    Jawaban: C', $normalStyle);
        $section->addTextBreak(0);
        $section->addText('[/NARASI_SOAL]', $boldStyle);
        $section->addText('(Soal setelah tag ini tidak lagi terkait dengan narasi di atas)', $italicStyle);
        $section->addTextBreak(1);
        $section->addText('9. Apa arti semboyan "Bhinneka Tunggal Ika"?', $boldStyle);
        $section->addText('    a. Satu untuk semua', $normalStyle);
        $section->addText('    b. Berbeda-beda tetapi tetap satu', $normalStyle);
        $section->addText('    c. Bersatu kita teguh', $normalStyle);
        $section->addText('    d. Merdeka atau mati', $normalStyle);
        $section->addText('    Jawaban: B', $normalStyle);
        $section->addTextBreak(1);
        $section->addText('10. [ESSAY] Jelaskan mengapa keberagaman budaya di Indonesia sangat kaya!', $boldStyle);
        $section->addText('    Jawaban:', $normalStyle);
        $section->addTextBreak(2);

        // -- Notes --
        $section->addText('CATATAN PENTING:', $boldStyle);
        $section->addListItem('Tandai jenis soal dengan tag [PG_KOMPLEKS], [MENJODOHKAN], [ISIAN], [ESSAY], atau [BENAR_SALAH] setelah nomor soal.', 0, $normalStyle);
        $section->addListItem('Soal tanpa tag yang memiliki opsi a/b/c/d dianggap Pilihan Ganda biasa.', 0, $normalStyle);
        $section->addListItem('Soal tanpa tag dan tanpa opsi dianggap Essay.', 0, $normalStyle);
        $section->addListItem('Untuk PG Kompleks, pisahkan jawaban benar dengan koma: Jawaban: A, C, E', 0, $normalStyle);
        $section->addListItem('Untuk Menjodohkan, gunakan tanda = untuk memisahkan pasangan kiri dan kanan. Gambar opsional: kiri | gambar: file.png = kanan | gambar: file.png', 0, $normalStyle);
        $section->addListItem('Untuk Benar/Salah, gunakan format: 1) Pernyataan (BENAR) atau 1) Pernyataan (SALAH)', 0, $normalStyle);
        $section->addTextBreak(1);
        $section->addText('TAG OPSIONAL:', $boldStyle);
        $section->addListItem('Tingkat kesulitan: [tingkat: mudah], [tingkat: sedang], atau [tingkat: sulit] — default: sedang', 0, $normalStyle);
        $section->addListItem('Bobot nilai: [bobot: 2] — default: 1. Bisa ditaruh di baris soal atau baris terpisah.', 0, $normalStyle);
        $section->addListItem('Contoh: 1. [PG_KOMPLEKS] [tingkat: sulit] [bobot: 3] Manakah bilangan prima?', 0, $italicStyle);
        $section->addTextBreak(1);
        $section->addText('GAMBAR:', $boldStyle);
        $section->addListItem('Untuk soal bergambar, sisipkan gambar langsung di dokumen Word ATAU gunakan format teks: [gambar: namafile.png]', 0, $normalStyle);
        $section->addListItem('Untuk opsi bergambar, tambahkan setelah teks opsi: a. Teks opsi | gambar: namafile.png', 0, $normalStyle);
        $section->addListItem('Jika menggunakan referensi nama file, masukkan file gambar ke folder "gambar/" lalu ZIP bersama file .docx ini.', 0, $normalStyle);
        $section->addListItem('Upload file .docx langsung (tanpa gambar) atau .zip (dengan gambar).', 0, $normalStyle);
        $section->addTextBreak(1);
        $section->addText('NARASI / TEKS BACAAN:', $boldStyle);
        $section->addListItem('Gunakan tag [NARASI] dan [/NARASI] untuk menandai awal dan akhir teks bacaan.', 0, $normalStyle);
        $section->addListItem('Semua soal setelah tag [/NARASI] otomatis dikaitkan dengan narasi tersebut.', 0, $normalStyle);
        $section->addListItem('Gunakan tag [/NARASI_SOAL] untuk mengakhiri hubungan narasi — soal setelahnya menjadi soal biasa.', 0, $normalStyle);
        $section->addListItem('Jika ada [NARASI] baru, narasi sebelumnya otomatis terputus.', 0, $normalStyle);
        $section->addListItem('Soal bernarasi akan ditampilkan bersama teks bacaannya saat ujian berlangsung.', 0, $normalStyle);
        $section->addListItem('Saat pengacakan soal, soal dalam satu narasi tetap berurutan (hanya posisi grup yang diacak).', 0, $normalStyle);

        $fileName = 'template_import_soal.docx';

        return response()->streamDownload(function () use ($phpWord) {
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /**
     * Download a ZIP template containing a Word template + empty gambar/ folder.
     */
    public function templateZip(): StreamedResponse
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();

        $titleStyle = ['bold' => true, 'size' => 14, 'color' => '1a56db'];
        $boldStyle  = ['bold' => true, 'size' => 11];
        $normalStyle = ['size' => 11];
        $grayStyle   = ['size' => 10, 'color' => '6B7280', 'italic' => true];

        // Instructions
        $section->addText('TEMPLATE IMPORT SOAL (ZIP + GAMBAR)', $titleStyle);
        $section->addText('Format ini mendukung soal dengan gambar. Masukkan gambar ke folder gambar/.', $grayStyle);
        $section->addTextBreak(1);

        $section->addText('PANDUAN FORMAT:', $boldStyle);
        $section->addListItem('Setiap soal diawali nomor: 1. Pertanyaan', 0, $normalStyle);
        $section->addListItem('Untuk gambar soal, gunakan: [gambar: namafile.png] di baris pertanyaan', 0, $normalStyle);
        $section->addListItem('Opsi PG: a. teks opsi', 0, $normalStyle);
        $section->addListItem('Opsi dengan gambar: a. teks opsi | gambar: namafile.png', 0, $normalStyle);
        $section->addListItem('Jawaban: huruf opsi (A) atau beberapa dipisah koma (A,C)', 0, $normalStyle);
        $section->addListItem('Tag jenis: [PG_KOMPLEKS], [MENJODOHKAN], [ISIAN], [ESSAY], [BENAR_SALAH]', 0, $normalStyle);
        $section->addListItem('Tag tingkat: [tingkat: mudah], [tingkat: sedang], [tingkat: sulit] — default: sedang', 0, $normalStyle);
        $section->addListItem('Tag bobot: [bobot: 2] — default: 1. Bisa di baris soal atau baris terpisah.', 0, $normalStyle);
        $section->addListItem('Narasi: [NARASI]...[/NARASI] untuk teks bacaan. Soal setelahnya otomatis terkait.', 0, $normalStyle);
        $section->addTextBreak(1);

        // PG with image options
        $section->addText('CONTOH PILIHAN GANDA DENGAN GAMBAR OPSI', $titleStyle, ['alignment' => Jc::LEFT]);
        $section->addTextBreak(1);

        $section->addText('1. Manakah gambar bendera Indonesia?', $boldStyle);
        $section->addText('[gambar: soal_bendera.png]', $grayStyle);
        $section->addText('a. Bendera Jepang | gambar: bendera_jp.png', $normalStyle);
        $section->addText('b. Bendera Indonesia | gambar: bendera_id.png', $normalStyle);
        $section->addText('c. Bendera Thailand | gambar: bendera_th.png', $normalStyle);
        $section->addText('d. Bendera Malaysia | gambar: bendera_my.png', $normalStyle);
        $section->addText('Jawaban: B', $normalStyle);
        $section->addTextBreak(1);

        // PG without images
        $section->addText('CONTOH PILIHAN GANDA TANPA GAMBAR', $titleStyle, ['alignment' => Jc::LEFT]);
        $section->addTextBreak(1);

        $section->addText('2. Apa ibu kota Indonesia?', $boldStyle);
        $section->addText('a. Bandung', $normalStyle);
        $section->addText('b. Surabaya', $normalStyle);
        $section->addText('c. Jakarta', $normalStyle);
        $section->addText('d. Yogyakarta', $normalStyle);
        $section->addText('Jawaban: C', $normalStyle);
        $section->addTextBreak(1);

        // Menjodohkan
        $section->addText('CONTOH MENJODOHKAN', $titleStyle, ['alignment' => Jc::LEFT]);
        $section->addTextBreak(1);

        $section->addText('3. [MENJODOHKAN] Jodohkan negara dengan ibu kotanya:', $boldStyle);
        $section->addText('Indonesia | gambar: indonesia.png = Jakarta | gambar: jakarta.png', $normalStyle);
        $section->addText('Jepang = Tokyo', $normalStyle);
        $section->addText('Thailand = Bangkok', $normalStyle);
        $section->addTextBreak(1);

        // Isian
        $section->addText('CONTOH ISIAN SINGKAT', $titleStyle, ['alignment' => Jc::LEFT]);
        $section->addTextBreak(1);

        $section->addText('4. Ibu kota Jepang adalah ___', $boldStyle);
        $section->addText('Jawaban: Tokyo', $normalStyle);
        $section->addTextBreak(1);

        // Essay
        $section->addText('CONTOH ESSAY', $titleStyle, ['alignment' => Jc::LEFT]);
        $section->addTextBreak(1);

        $section->addText('5. Jelaskan proses terjadinya hujan!', $boldStyle);
        $section->addText('Jawaban: Proses terjadinya hujan meliputi evaporasi, kondensasi, dan presipitasi.', $normalStyle);
        $section->addTextBreak(1);

        // Benar/Salah
        $section->addText('CONTOH BENAR / SALAH', $titleStyle, ['alignment' => Jc::LEFT]);
        $section->addTextBreak(1);

        $section->addText('6. [BENAR_SALAH] Tentukan benar atau salah pernyataan berikut tentang air:', $boldStyle);
        $section->addText('1) Air mendidih pada suhu 100°C di tekanan standar (BENAR)', $normalStyle);
        $section->addText('2) Es memiliki massa jenis lebih besar dari air (SALAH)', $normalStyle);
        $section->addText('3) H2O adalah rumus kimia garam dapur (SALAH)', $normalStyle);
        $section->addText('4) Air merupakan pelarut universal (BENAR)', $normalStyle);

        $section->addTextBreak(2);

        // Narasi
        $narasiColor = ['bold' => true, 'size' => 14, 'color' => '7c3aed'];
        $section->addText('CONTOH SOAL BERNARASI (TEKS BACAAN)', $narasiColor, ['alignment' => Jc::LEFT]);
        $section->addTextBreak(0);
        $section->addText('Gunakan [NARASI]...[/NARASI] untuk teks bacaan bersama.', $grayStyle);
        $section->addTextBreak(1);
        $section->addText('[NARASI]', $boldStyle);
        $section->addText('Indonesia adalah negara kepulauan terbesar di dunia. Indonesia memiliki lebih dari 17.000 pulau dengan keberagaman budaya yang sangat kaya.', $normalStyle);
        $section->addText('[/NARASI]', $boldStyle);
        $section->addTextBreak(0);
        $section->addText('7. Berapa jumlah pulau di Indonesia menurut teks di atas?', $boldStyle);
        $section->addText('a. 13.000', $normalStyle);
        $section->addText('b. 17.000', $normalStyle);
        $section->addText('c. 19.000', $normalStyle);
        $section->addText('d. 21.000', $normalStyle);
        $section->addText('Jawaban: B', $normalStyle);
        $section->addTextBreak(1);
        $section->addText('8. [ESSAY] Jelaskan mengapa Indonesia disebut negara kepulauan terbesar!', $boldStyle);
        $section->addText('Jawaban:', $normalStyle);

        $section->addTextBreak(2);
        $section->addText('STRUKTUR ZIP:', $boldStyle);
        $section->addListItem('soal_import.zip', 0, $normalStyle);
        $section->addListItem('    template_soal.docx  (file ini)', 0, $normalStyle);
        $section->addListItem('    gambar/', 0, $normalStyle);
        $section->addListItem('        soal_bendera.png', 0, $normalStyle);
        $section->addListItem('        bendera_jp.png', 0, $normalStyle);
        $section->addListItem('        bendera_id.png', 0, $normalStyle);
        $section->addListItem('        ...', 0, $normalStyle);

        // Create ZIP with docx + empty gambar folder
        $tmpDocx = tempnam(sys_get_temp_dir(), 'soal_tpl_') . '.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tmpDocx);

        $fileName = 'template_import_soal_zip.zip';

        return response()->streamDownload(function () use ($tmpDocx) {
            $zip = new ZipArchive();
            $tmpZip = tempnam(sys_get_temp_dir(), 'soal_zip_') . '.zip';

            $zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFile($tmpDocx, 'template_soal.docx');
            $zip->addEmptyDir('gambar');
            $readme = "PANDUAN IMPORT SOAL DENGAN GAMBAR\n";
            $readme .= "================================\n\n";
            $readme .= "1. Edit file template_soal.docx sesuai format yang sudah disediakan.\n";
            $readme .= "2. Masukkan semua file gambar ke folder gambar/\n";
            $readme .= "3. Referensikan gambar di Word dengan format:\n";
            $readme .= "   - Gambar soal: [gambar: namafile.png]\n";
            $readme .= "   - Gambar opsi: a. Teks opsi | gambar: namafile.png\n";
            $readme .= "4. ZIP seluruh isi folder ini (template_soal.docx + gambar/)\n";
            $readme .= "5. Upload file .zip melalui halaman Import Soal\n";
            $zip->addFromString('README.txt', $readme);
            $zip->close();

            readfile($tmpZip);

            @unlink($tmpDocx);
            @unlink($tmpZip);
        }, $fileName, [
            'Content-Type' => 'application/zip',
        ]);
    }
}
