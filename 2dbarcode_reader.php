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

    return [];
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

$generatorFromLeftToRight = function (int $step): Closure {
    return function ($left, $right) use($step): Iterator {
        for ($i = $left; $i <= $right; $i += $step) {
            yield $i;
        }
    };
};

$generatorFromRighgtToLeft = function ($left, $right): Iterator {
    for ($i = $right; $i <= $left; $i--) {
        yield $i;
    }
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

$image = new Imagick('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAZAAAADXAgMAAABJ++8/AAAACVBMVEX////pDlsAAADsJtXVAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAE2klEQVR4nO2cwY7jIAyGyUhceiYv0acgh7lTqXmJPkUfJceWp1wDNhiSdmZ3tCtLaw4pE8AfgV+xTaQxRosWLf9dmfF38iaw29fyE8bupdhWLeMCVuEyDpmutb8bIK7+OFdZq71WiIU2uL1eBshuXtZ2EF5csQU/c4VMy6cdIXZneQcxNthpWWB+67JOwQYzrdbO1zW49JSztwkyLyv0DQiZ/QqdQoXAuDkEaL1af4GeyUq/XM7ZMtRNiw+wMvkvGO7KUyaj12QiPRu0ATlcoAuMI0geB4tm4aHWS7HSP0qBrAixuCRh8s6uuC6uQGA7oC0tf+oyX3vImnYEIfN1D4HtNAgxZUnSeJduWu8qJD3l5dMhpE62QdK2hvwk48YXSJovPmjakzTFslwZ4kKA1Uhtn2lh3y1XuMCeOGc5BPYq4B7bBVYtba6fVtj4y7pkeedHmP2cl6tMZ9x4GJfmAcZg9y7eQtvcK3VXOiW7d41vhttv9frj8sPhWrRo+Wtl/QdFIRIh/vQw8Q4XY04xmvNmWA0aYox381EuKJZ2L/eLsdYe1FqM5tpTITIhcD/GLVlILakGpdYSs4CHfslgtspayzBTLmRvUohIyC1yYRWZmCy2Tk6diHKXTk7ZdC9AujwVIhPS5FS10gsm20IzJ94PlTS24izLayVGhciE+KwB0/uOXKs+prYyYbV7zSBXJkoM7E0KEQm5kUyYPykyIZ3hPWMOJQYX9DtcdpGik/OmEJkQz4TAhYVayS7ihL4DJbYdSLEFlNRKHuipEJGQ2yCOLigs7xG8h8Ejb/1ObVKISIhvYR8LAB9mlxUOOSNdWufWyjPPEkgoRBzkVoPHVJrH6MJIssUySuYxTDfWmK5fzRkVIg3C3QYPHl8eC1XpZNPVn1CSOIjtqRDBkBfSiV/lkd2R0p1a21hKHRQiDuJJMF3Yx/PD43PhU2RiG7NMnFEeoRChELPPGTGgbLIzPHtkx0KG93sReCpEJuRWevcnuubgWGjvY7hnOT1eeRaFyIUUq1995vldv3NXiGSI7+TUBYqDF0F1jX7HVC/SUs0y38FpKUQWJA09k0yYps4kJ/zSt+9HKuz7nR7srJg5LYUIhGCgiLX2Tbf5iUMB7vLDGPcCHA+fFSID0p8Lt0OefUBZfcd2dJBE7qX3StVpKUQapOaMZjwWqn5iDBSHMDLyILMIMG7NylMhMiHpz0EmKLHckQeURTrocvBY6IXEmgAnhciFjCIy7eCnl84gwIOAEmONPmdUiDxInzOSx+BZIR0QHXiRrV12I6ifQmRCPL3/B5lQrv9BYuuzx3MdUeNGJjtu76kQkZBbdQKH0ok7sR16kTvJswmVfJFCZEK8OfrMU3NGs88Ud8dHzeVEfi6MQlWITMiNvMiPzoCZZ2EeKOMUIhPiT91h0DnykJEHik1O5oMCxTGgJLHl7AKtKEQqBHM8ev+3/PAgZGytzIv0Y0li5HcmhciEVEm88QnxhZyq32n5Zg0tSZQKkQlpOWPJFeiCvU09DIpki3uRGjI2sXU1MyamCpEC8UwI3VfbIqxWS/3wFVJH9BJrXol9DtoUIhSSpbPVDz4b6Yznkf0RUOStBVxDRro35owKEQfJ4mhB4SAOElYLKFFE5rtlUohIyD/5fysKkQb5BUOfnwuI33HlAAAAAElFTkSuQmCC');

$borderCoordinateTop = findBorder(
    $image,
    1,
    1,
    null,
    null,
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

if ($borderCoordinateTop == []) {
    echo 'Border is not found' . PHP_EOL;
} else {
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

    if ($borderCoordinateBottom != []) {
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

        if ($borderCoordinateTopRight != []) {
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

            $read2DBarcode = read2DBarcode(
                $image,
                $borderCoordinateTop['x'],
                $borderCoordinateTop['y'],
                $borderCoordinateTopRight['x'],
                $borderCoordinateBottom['y'],
                $generatorFromLeftToRight($bitSizeByX),
                $generatorFromLeftToRight($bitSizeByY)
            );
        }
    }

    $byte = '';
    foreach ($read2DBarcode as $read2DBarcodeString) {
        foreach ($read2DBarcodeString as $bit) {
            $byte .= $bit;
            if (strlen($byte) === 8) {
                echo pack('H*', base_convert($byte, 2, 16));
                $byte = '';
            }
        }
    }

    echo PHP_EOL;
}