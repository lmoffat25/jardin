<?php

namespace Src\Jardin\Identification;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use LogicException;

/**
 * Le QR d'une adresse, en SVG à poser tel quel dans la page (étiquettes, B10).
 * Seuls les modules foncés sont tracés, sans prologue XML ; la taille se règle
 * en CSS. Correction d'erreur M : une étiquette au jardin se salit (D-108).
 */
final class EncodeurQR
{
    public function svg(string $url): string
    {
        $svg = (new QRCode(new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
            'eccLevel' => EccLevel::M,
        ])))->render($url);

        // `render()` est typée `mixed` : la sortie SVG est une chaîne.
        return is_string($svg) ? $svg : throw new LogicException('Le QR n\'a pas rendu de SVG.');
    }
}
