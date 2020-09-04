<?php

function findBorder(
    Imagick $image,
    int $startX = 1,
    int $startY = 1,
    ?int $finishX = null,
    ?int $finishY = null,
    Closure $xCoordinateGenerator,
    Closure $yCoordinateGenerator,
    Closure $borderCondition,
    Closure $borderFinishCondition
): array {
    $width = $image->getImageWidth();
    $height = $image->getImageHeight();

    $borderPixel = null;
    $prevPixel = null;
    foreach ($xCoordinateGenerator($startX, $finishX ?? $width) as $x) {
        foreach ($yCoordinateGenerator($startY, $finishY ?? $height) as $y) {
            $pixel = $image->getImagePixelColor($x, $y);
            if ($prevPixel !== null) {
                $currentPixelColor = $pixel->getColor();
                $prevPixelColor = $prevPixel->getColor();
                if ($borderCondition($currentPixelColor, $prevPixelColor)) {
                    $borderPixel = $pixel;
                    $borderX = $x;
                    $borderY = $y;
                } elseif ($borderFinishCondition($currentPixelColor, $prevPixelColor) && $borderPixel !== null) {
                    return ['x' => $borderX ?? $x, 'y' => $borderY ?? $y];
                }
            }
            $prevPixel = $pixel;
        }
    }

    throw new \RuntimeException(sprintf('Border is not found in startX: %d, startY: %d, finishX: %d, finishY: %d'));
}

function getBitSize(
    Imagick $image,
    int $start,
    int $finish,
    Closure $imagePixel,
    Closure $coordinateGenerator
): int {
    $white = ['r' => 255, 'g' => 255, 'b' => 255, 'a' => 1];
    $black = ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 1];
    $prevPixel = null;
    $samePixelCount = 1;
    $bitSizes = [];
    foreach ($coordinateGenerator($start, $finish) as $point) {
        $pixel = $imagePixel($image, $point);
        if ($prevPixel !== null) {
            $currentPixelColor = $pixel->getColor();
            $prevPixelColor = $prevPixel->getColor();
            if ($prevPixelColor == $white || $prevPixelColor == $black) {
                if ($prevPixelColor == $currentPixelColor) {
                    $samePixelCount++;
                } else {
                    $bitSizes[] = $samePixelCount;
                    $samePixelCount = 1;
                }
            } else {
                throw new \RuntimeException('Unexpected pixel color:' . json_encode($prevPixelColor));
            }
        } else {
            $samePixelCount++;
        }
        $prevPixel = $pixel;
    }

    return min($bitSizes);
}

function read2DBarcode(
    Imagick $image,
    int $startX,
    int $startY,
    int $finishX,
    int $finishY,
    Closure $xCoordinateGenerator,
    Closure $yCoordinateGenerator
): array {
    $black = ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 1];
    $read2DBarcode = [];
    foreach ($yCoordinateGenerator($startY, $finishY) as $y) {
        $read2DBarcodeString = [];
        foreach ($xCoordinateGenerator($startX, $finishX) as $x) {
            $pixelColor = $image->getImagePixelColor($x, $y)->getColor();
            $read2DBarcodeString[] = $pixelColor == $black ? 1 : 0;
        }
        $read2DBarcode[] = $read2DBarcodeString;
    }

    return $read2DBarcode;
}

function find2DBarcodeAndRead(Imagick $image, int $startX, int $startY, int $finishX, int $finishY): array
{
    $generatorFromLeftToRight = function (int $step): Closure {
        return function ($left, $right) use($step): Iterator {
            for ($i = $left; $i <= $right; $i += $step) {
                yield $i;
            }
        };
    };

    $imagePixelByX = function (int $y): Closure {
        return function (Imagick $image, int $x) use($y) : ImagickPixel {
            return $image->getImagePixelColor($x, $y);
        };
    };

    $imagePixelByY = function (int $x): Closure {
        return function (Imagick $image, int $y) use($x) : ImagickPixel {
            return $image->getImagePixelColor($x, $y);
        };
    };

    $borderCoordinateTop = findBorder(
        $image,
        $startX,
        $startY,
        $finishX,
        $finishY,
        $generatorFromLeftToRight(1),
        $generatorFromLeftToRight(1),
        function ($currentColor, $prevColor) {
            $white = ['r' => 255, 'g' => 255, 'b' => 255, 'a' => 1];
            $black = ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 1];

            return $prevColor != $white
                && $prevColor != $black
                && ($currentColor == $white || $currentColor == $black);
        },
        function ($currentColor, $prevColor = null) {
            $black = ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 1];

            return $currentColor == $black;
        }
    );

    $borderCoordinateBottom = findBorder(
        $image,
        $borderCoordinateTop['x'],
        $borderCoordinateTop['y'],
        $borderCoordinateTop['x'],
        null,
        $generatorFromLeftToRight(1),
        $generatorFromLeftToRight(1),
        function ($currentColor, $prevColor) {
            $white = ['r' => 255, 'g' => 255, 'b' => 255, 'a' => 1];
            $black = ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 1];

            return ($prevColor == $white || $prevColor == $black)
                && ($currentColor == $white || $currentColor == $black);
        },
        function ($currentColor, $prevColor) {
            $white = ['r' => 255, 'g' => 255, 'b' => 255, 'a' => 1];
            $black = ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 1];

            return ($prevColor == $white || $prevColor == $black)
                && ($currentColor != $white || $currentColor != $black);
        }
    );

    $borderCoordinateTopRight = findBorder(
        $image,
        $borderCoordinateTop['x'],
        $borderCoordinateTop['y'],
        null,
        $borderCoordinateTop['y'],
        $generatorFromLeftToRight(1),
        $generatorFromLeftToRight(1),
        function ($currentColor, $prevColor) {
            $white = ['r' => 255, 'g' => 255, 'b' => 255, 'a' => 1];
            $black = ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 1];

            return ($prevColor == $white || $prevColor == $black)
                && ($currentColor == $white || $currentColor == $black);
        },
        function ($currentColor, $prevColor) {
            $white = ['r' => 255, 'g' => 255, 'b' => 255, 'a' => 1];
            $black = ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 1];

            return ($prevColor == $white || $prevColor == $black)
                && ($currentColor != $white || $currentColor != $black);
        }
    );

    $bitSizeByX = getBitSize(
        $image,
        $borderCoordinateTop['x'],
        $borderCoordinateTopRight['x'],
        $imagePixelByX($borderCoordinateTop['y']),
        $generatorFromLeftToRight(1)
    );

    $bitSizeByY = getBitSize(
        $image,
        $borderCoordinateTop['y'],
        $borderCoordinateBottom['y'],
        $imagePixelByY($borderCoordinateTop['x']),
        $generatorFromLeftToRight(1)
    );

    return read2DBarcode(
        $image,
        $borderCoordinateTop['x'],
        $borderCoordinateTop['y'],
        $borderCoordinateTopRight['x'],
        $borderCoordinateBottom['y'],
        $generatorFromLeftToRight($bitSizeByX),
        $generatorFromLeftToRight($bitSizeByY)
    );
}


try {
    $image = new Imagick('data:image/png;base64,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');
    $imageWidth = $image->getImageWidth();
    $imageHeight = $image->getImageHeight();

    $firstRead2DBarcode = find2DBarcodeAndRead($image, 1, 1, $imageWidth/2, $imageHeight);
    $secondRead2DBarcode = find2DBarcodeAndRead($image, $imageWidth/2, 1, $imageWidth, $imageHeight);

    $byte = '';
    foreach ($firstRead2DBarcode as $row => $firstRead2DBarcodeString) {
        foreach ($firstRead2DBarcodeString as $column => $firstBarcodeBit) {
            $secondBarcodeBit = $secondRead2DBarcode[$row][$column];
            $byte .= $firstBarcodeBit ^ $secondBarcodeBit;
            if (strlen($byte) === 8) {
                echo pack('H*', base_convert($byte, 2, 16));
                $byte = '';
            }
        }
    }

    echo PHP_EOL;
} catch (\Exception $e) {
    echo 'Error: '. $e->getMessage();
}