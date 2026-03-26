<?php

namespace Tests\Unit;

use App\Support\HtmlDisplay;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_html_display_renders_encoded_rich_text_fragments(): void
    {
        $html = HtmlDisplay::render('&lt;p&gt;Narasi &quot;penting&quot; untuk siswa.&lt;/p&gt;')->toHtml();

        $this->assertStringContainsString('Narasi "penting" untuk siswa.', $html);
        $this->assertStringNotContainsString('&lt;p&gt;', $html);
        $this->assertStringNotContainsString('&quot;', $html);
    }

    public function test_html_display_decodes_safe_entities_inside_existing_html_markup(): void
    {
        $html = HtmlDisplay::render('<p>Petani berkata &quot;aku ingin cepat kaya&quot;.</p>')->toHtml();

        $this->assertStringContainsString('Petani berkata "aku ingin cepat kaya".', $html);
        $this->assertStringNotContainsString('&quot;', $html);
    }

    public function test_html_display_preserves_literal_encoded_tags_and_media_as_text(): void
    {
        $tagHtml = HtmlDisplay::render('Apa fungsi tag &lt;option&gt;?')->toHtml();
        $mediaHtml = HtmlDisplay::render('Contoh &lt;img src="https://example.com/pixel.png"&gt;')->toHtml();

        $this->assertSame('Apa fungsi tag &lt;option&gt;?', $tagHtml);
        $this->assertSame('Contoh &lt;img src="https://example.com/pixel.png"&gt;', $mediaHtml);
    }

    public function test_html_display_preserves_line_breaks_for_plain_text(): void
    {
        $html = HtmlDisplay::render("Baris 1\nBaris 2")->toHtml();

        $this->assertSame("Baris 1<br />\nBaris 2", $html);
    }

    public function test_html_display_decodes_encoded_legacy_wrapper_tags_to_text(): void
    {
        $html = HtmlDisplay::render('&lt;div&gt;Contoh legacy&lt;/div&gt;')->toHtml();

        $this->assertSame('Contoh legacy', $html);
    }

    public function test_html_display_keeps_inline_tag_examples_literal(): void
    {
        $html = HtmlDisplay::render('&lt;span&gt;teks literal&lt;/span&gt;')->toHtml();

        $this->assertSame('&lt;span&gt;teks literal&lt;/span&gt;', $html);
    }
}
