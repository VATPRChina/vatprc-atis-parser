<?php

require_once __DIR__.'/../vendor/autoload.php';

use MetarDecoder\MetarDecoder;

$metar = 'ZGGG 040119Z 17004MPS 1200 R19R/P2000 R19L/P2000 R20R/P2000 '
    .'R20L/P2000 R21/0650D +TSRA FEW013 SCT020CB BKN030 27/27 Q1009 '
    .'BECMG AT0140 6000 -TSRA';

$decoded = (new MetarDecoder())->parse($metar);
$runways = $decoded->getRunwaysVisualRange();

if (!$decoded->isValid()) {
    throw new RuntimeException('METAR with five runway visual range groups was not valid');
}

if (count($runways) !== 5) {
    throw new RuntimeException('Expected all five runway visual range groups to be decoded');
}

if ($runways[4]->getRunway() !== '21'
    || $runways[4]->getVisualRange()->getValue() !== 650
    || $runways[4]->getPastTendency() !== 'D'
) {
    throw new RuntimeException('The fifth runway visual range group was decoded incorrectly');
}
