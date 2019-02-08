<?php

function longUnsignedIntSum(string $leftInt, string $rightInt): string
{
    $baseExponent = strlen($leftInt) > strlen($rightInt) ? findBaseExponent($leftInt) : findBaseExponent($rightInt);

    $leftIntAsIntList = convertIntAsStringToIntList($leftInt, $baseExponent);
    $rightIntAsIntList = convertIntAsStringToIntList($rightInt, $baseExponent);

    $sumStepNumber = max(count($leftIntAsIntList), count($rightIntAsIntList));
    $listSumOfIntAsString = [];
    for($i = 0; $i < $sumStepNumber; $i++) {
        $nextRegisterValue = $listSumOfIntAsString[$i] ?? 0;
        $leftIntAsIntItem = $leftIntAsIntList[$i] ?? 0;
        $rightIntAsIntItem = $rightIntAsIntList[$i] ?? 0;
        $listSumOfIntCurrentStep = $nextRegisterValue + $leftIntAsIntItem + $rightIntAsIntItem;

        $calculationBaseExponent = strlen(max($leftIntAsIntItem, $rightIntAsIntItem));
        $base = 10 ** $calculationBaseExponent;

        $listSumOfIntAsString[$i + 1] = (string) intdiv($listSumOfIntCurrentStep, $base);
        $listSumOfIntAsString[$i] = str_pad($listSumOfIntCurrentStep % $base, $calculationBaseExponent, '0', STR_PAD_LEFT);
    }

    if ($listSumOfIntAsString[$sumStepNumber] === '0') {
        unset($listSumOfIntAsString[$sumStepNumber]);
    }

    if ($base === 1 && $listSumOfIntAsString[0] === 0) {
        unset($listSumOfIntAsString[0]);
    }

    krsort($listSumOfIntAsString);

    return implode('', $listSumOfIntAsString);
}

function convertIntAsStringToIntList(string $stringInt, int $baseExponent): array
{
    $stringIntLength = strlen($stringInt);
    $stringIntAsIntList = [];
    $baseLength = $baseExponent === 0 || $baseExponent === 1 ? 1 : $baseExponent;
    $nextStart = $baseLength;

    do {
        $remainLength = $stringIntLength - $nextStart + $baseLength;
        $readLength = $baseLength > $remainLength ? $remainLength : $baseLength;

        $subStringInt = substr($stringInt, -$nextStart, $readLength);
        if ($subStringInt === false) {
            throw new \RuntimeException(
                sprintf(
                    'Unexpected result to get substring from string: %s, with start: %d and length: %d',
                    $stringInt,
                    -$nextStart,
                    $readLength
                )
            );
        }
        $stringIntAsIntList[] = (int) $subStringInt;

        $nextStart += $baseLength;
    } while ($remainLength > $baseLength);

    return $stringIntAsIntList;
}

function findBaseExponent(string $stringInt): int
{
    $isFoundBaseExponent = false;
    $baseExponent = 0;

    while (!$isFoundBaseExponent) {
        $int = (int) $stringInt;

        $isFoundBaseExponent = (string) $int === $stringInt;

        $baseExponent = strlen($stringInt) - 1;

        $stringInt = substr($stringInt, 0, $baseExponent);
        if ($stringInt === false) {
            throw new \RuntimeException(
                sprintf(
                    'Unexpected result to get substring from string: %s, with start: %d and length: %d',
                    $stringInt,
                    0,
                    $baseExponent
                )
            );
        }
    }

    return $baseExponent;
}