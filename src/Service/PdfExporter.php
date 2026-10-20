<?php
namespace App\Service;

use App\Entity\ContentPost;
use Dompdf\{Dompdf, Options};

/** Private, local PDF rendering. User content is always literal text, never HTML. */
final class PdfExporter
{
    public function __construct(private string $projectDir) {}

    public function export(ContentPost $post): string
    {
        $temporary = $this->projectDir.'/var/pdf';
        if (!is_dir($temporary) && !mkdir($temporary, 0700, true) && !is_dir($temporary)) {
            throw new \RuntimeException('Das lokale PDF-Verzeichnis konnte nicht erstellt werden.');
        }
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setAllowedProtocols([]);
        $options->setChroot([$this->projectDir.'/vendor/dompdf/dompdf/lib/fonts']);
        $options->setFontDir($this->projectDir.'/vendor/dompdf/dompdf/lib/fonts');
        $options->setFontCache($temporary);
        $options->setTempDir($temporary);
        $options->setDefaultFont('DejaVu Sans');
        $escape = static fn(string $value): string => htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = $escape($post->title);
        $text = nl2br($escape(mb_substr((string)($post->data['content'] ?? $post->data['caption'] ?? ''), 0, 100000)));
        $status = $escape((string)($post->data['status'] ?? 'Entwurf'));
        $version = (int)($post->data['version'] ?? 1);
        $html = '<!doctype html><html lang="de"><head><meta charset="UTF-8"><style>
@page { margin: 78px 44px 65px; }
body { font-family: "DejaVu Sans",sans-serif; font-size: 10px; color: #151923; line-height: 1.65; overflow-wrap: break-word; }
.header { position: fixed; top: -52px; left: 0; right: 0; border-bottom: 3px solid #FF6A00; padding-bottom: 9px; }
.brand { font-weight: bold; font-size: 18px; } .notice { float: right; color: #b04600; font-weight: bold; font-size: 13px; }
.footer { position: fixed; bottom: -43px; left: 0; right: 0; font-size: 8px; color: #555; border-top: 1px solid #ddd; padding-top: 8px; }
.label { color: #b04600; font-weight: bold; font-size: 13px; background: #fff1e7; padding: 10px; }
h1 { font-size: 21px; line-height: 1.4; } .meta { color: #666; font-size: 9px; margin-bottom: 22px; } .content { word-wrap: break-word; }
.disclaimer { margin-top: 28px; border-top: 1px solid #ddd; padding-top: 12px; font-size: 9px; }
</style></head><body>
<div class="header"><span class="brand">CHASE Immobilien</span><span class="notice">DEMO / MUSTER</span></div>
<div class="footer">DEMO / MUSTER · Unverbindlicher lokaler Entwurf · Vor jeder externen Verwendung fachlich prüfen.</div>
<div class="label">DEMO / MUSTER – KEIN PRODUKTIVES EXPOSÉ</div>
<h1>'.$title.'</h1><p class="meta">Version '.$version.' · Status: '.$status.' · Lokaler PDF-Export, keine externe Übertragung.</p>
<div class="content">'.$text.'</div>
<p class="disclaimer"><strong>Unverbindliches Demo-Muster.</strong> Dieses Dokument ist kein Rechtsgutachten, keine Verkehrswertermittlung und kein Nachweis für rechtliche Vollständigkeit. Energie-, Preis-, Flächen- und Ausstattungsangaben sowie Bildrechte und Freigaben vor Verwendung unabhängig prüfen. Die Kennzeichnung als Demo bleibt Bestandteil dieses Exports.</p>
</body></html>';
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();
        return $pdf->output();
    }
}
