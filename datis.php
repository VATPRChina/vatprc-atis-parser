<?php
require_once 'vendor/autoload.php';

use MetarDecoder\MetarDecoder;

require 'airports.php';

$rawMetar = $_GET['metar'];
// FIXME: Dirty fix to issue caused by `8000NW` in the METAR
// METAR ZMUB 100530Z VRB02MPS 8000NW BKN250 M07/M12 Q1005 NOSIG RMK QFE647.5 62 NW MO=
$rawMetar = preg_replace('/ (\d{4})[NWSE]+ /', ' $1 ', $rawMetar);

$decoder = new MetarDecoder();
$decoded = $decoder->parse($rawMetar);
$surfaceWindObj = $decoded->getSurfaceWind(); //SurfaceWind object
$visObj = $decoded->getVisibility(); //Visibility object
$rvr = $decoded->getRunwaysVisualRange(); //RunwayVisualRange array
$phenomenon = $decoded->getPresentWeather(); //WeatherPhenomenon array
$clouds = $decoded->getClouds(); //CloudLayer array
$windShearAlerts = $decoded->getWindshearRunways();
$type = $_GET['type'] ?? null;
$adelv = $_GET['adelv'] ?? null; // Value for QFE calculation, type aerodrome elevation in feet.

if ($decoded->isValid() == false) {
    exit('Invalid METAR.');
}

// Airport, date & time
print($decoded->getIcao() . ' ');
if ($type === 'D') {
    print('DEP ATIS ');
} elseif ($type === 'A') {
    print('ARR ATIS ');
} else {
    print('ATIS ');
}
print($_GET['info'] . ' '. substr($rawMetar, 7, 4) . 'Z ');

// Operational Runway
if ($type === 'D') {
    print ('DEP RWY ' . str_replace(',', ' AND ', $_GET['dep']));
} elseif ($type === 'A') {
    print ('EXP ' . $_GET['apptype'] . ' ARR RWY ' . str_replace(',', ' AND ', $_GET['arr']));
} else {
    print ('DEP RWY ' . str_replace(',', ' AND ', $_GET['dep']) . ' EXP ' . $_GET['apptype'] . ' ARR RWY ' . str_replace(',', ' AND ', $_GET['arr']));
}


// Wind Shear Alert
if ($decoded->getWindshearAllRunways()) {
    print(' CTN WS ALL RWYS ');
} else if ($windShearAlerts) {
    print(' CTN WS RWY ');
    foreach ($windShearAlerts as $index => $runway) {
        if ($index >= 1) {
            print('AND ');
        }
        print($runway);
    }
    print(' ');
}

//A-CDM
if ((time() - strtotime("2024-09-04 16:00:00Z") > 0) and in_array($decoded->getIcao(), ['ZBAA', 'ZSPD', 'ZGGG'])) {
    print(' A-CDM IN OPERATION ');
}

//NOTAM (reserve)

// Wind
print(' WIND ');

if ($surfaceWindObj->getMeanSpeed()->getValue() == 0) {
    print('CALM ');
} else {
    if ($surfaceWindObj->withVariableDirection() == true) {
        print('VRB DEG ');
    } else {
        if ($surfaceWindObj->getMeanDirection()->getValue() < 100) {
            print('0');
        }
        print($surfaceWindObj->getMeanDirection()->getValue() . ' DEG ');
    }
    
    $raw_sw = $surfaceWindObj->getMeanSpeed()->getValue();
    $int_sw = (int)$raw_sw;
    $str_sw = strval($int_sw);
    if ($int_sw < 10) {
        $out_sw = '0' . $str_sw ;
    } else {
        $out_sw = $str_sw ;
    }

    print($out_sw . ' MPS ');

}
If ($surfaceWindObj->getSpeedVariations() != null) {
    if ($surfaceWindObj->getSpeedVariations()->getValue() < 10) {
        print(' GUST 0' . $surfaceWindObj->getSpeedVariations()->getValue() . ' MPS ');
    } else {
            print(' GUST ' . $surfaceWindObj->getSpeedVariations()->getValue() . ' MPS ');
    }
}
if ($surfaceWindObj->getDirectionVariations() != null) {
    if ($surfaceWindObj->getDirectionVariations()[0]->getValue() < 100) {
        print('0');
    }
    print($surfaceWindObj->getDirectionVariations()[0]->getValue() . 'V');
    if ($surfaceWindObj->getDirectionVariations()[1]->getValue() < 100) {
        print('0');
    }
    print($surfaceWindObj->getDirectionVariations()[1]->getValue() . ' DEG ');
}

// RVR
if ($rvr != null) {
    foreach ($rvr as $runwayRvr) {
        print('RVR RWY ');
        print($runwayRvr->getRunway());
        if ($runwayRvr->getVisualRange() == null) {
            print(' BTW ');
            print($runwayRvr->getVisualRangeInterval()[0]->getValue());
            print(' M AND ');
            print($runwayRvr->getVisualRangeInterval()[1]->getValue());
            print(' M ');
        } else {
            print(' ');
            print($runwayRvr->getVisualRange()->getValue());
            print(' M');
        }
        switch ($runwayRvr->getPastTendency()) {
        case 'D':
                print(' DOWNWARD TNDCY ');
            break;
        case 'N':
                print(' NC ');
            break;
        case 'U':
                print(' UPWARD TNDCY ');
            break;
        }
    }
}

// Visibility
if (strpos($rawMetar, 'CAVOK') !== false) {    
    print(' CAVOK ');
} else {
    print(' VIS ' . $visObj->getVisibility()->getValue() . ' M ');
}

// Cloud & Weather Phenomenon
if (strpos($rawMetar, 'NSC') === true) {
    print(' NSC');
}
foreach ($phenomenon as $pwn) {
    if ((string)$pwn->getIntensityProximity() !== '') {
        print($pwn->getIntensityProximity());
    }
    if ($pwn->getCharacteristics() !== '') {
        print($pwn->getCharacteristics());
    }
    if (is_array($pwn->getTypes())) {
        foreach ($pwn->getTypes() as $pwntype) {
            print($pwntype);
            print(' ');
        }
    } else {
        print(' ');
    }
}
foreach ($clouds as $cloud) {
    print($cloud->getAmount());
    $baseHeight = $cloud->getBaseHeight();
    if ($baseHeight !== null) {
        print(' ' . $cloud->getBaseHeight()->getValue() * 0.3);
    } else {
        print(' 0');
    }
    print('M');
    print($cloud->getType());
    print(' ');
}

// Miscellaneous
$temp_data = $decoded->getAirTemperature()->getValue();
$int_temp_data = (int)$temp_data;
$str_temp_data = strval($int_temp_data);
if ($int_temp_data < 10 && $int_temp_data > 0) {
    $out_temp_data = '0' . $str_temp_data ;
} elseif ($int_temp_data < 0 && $int_temp_data > -10) {
    $out_temp_data = '-0' . $str_temp_data[1];
} elseif ($int_temp_data == 0) {
    $out_temp_data = '00';
} else {
    $out_temp_data = $str_temp_data ;
}

$dewpt_data = $decoded->getDewPointTemperature()->getValue();
$int_dewpt_data = (int)$dewpt_data;
$str_dewpt_data = strval($int_dewpt_data);
if ($int_dewpt_data < 10 && $int_dewpt_data > 0) {
        $out_dewpt_data = '0' . $str_dewpt_data ;
} elseif ($int_dewpt_data < 0 && $int_dewpt_data > -10) {
        $out_dewpt_data = '-0' . $str_dewpt_data[1];
} elseif ($int_dewpt_data == 0) {
        $out_dewpt_data = '00';
} else {
        $out_dewpt_data = $str_dewpt_data ;
}

print(' TEMP ' . $out_temp_data . ' DEW POINT ' . $out_dewpt_data . ' QNH ' . $decoded->getPressure()->getValue() . ' HPA ');

if ($adelv !== null) {
    print('QFE ' . floor($decoded->getPressure()->getValue() - $adelv / 27) . ' HPA ');
}

//Transition Level
print('TRANSITION LEVEL ');

$TLData = [
    'ZGGG' => ["pressure" => 980, "TL" => 3300],
    'ZGOW' => ["pressure" => 980, "TL" => 3300],
    'ZGSD' => ["pressure" => 980, "TL" => 3300],
    'ZGSZ' => ["pressure" => 980, "TL" => 3300],
    'ZHSN' => ["TL" => 5100],
    'ZHXY' => ["TL" => 2400],
    'ZLLL' => ["TL" => 4800],
    'ZLXN' => ["TL" => 5400],
    'ZMCK' => ["TL" => 4500],
    'ZMUB' => ["TL" => 4500],
    'ZPJH' => ["TL" => 4200],
    'ZPLJ' => ["TL" => 6600],
    'ZPMS' => ["TL" => 4800],
    'ZPPP' => ["TL" => 6000],
    'ZSCG' => ["TL" => 2400],
    'ZSWH' => ["TL" => 2100],
    'ZSWX' => ["TL" => 'BY ATC'],
    'ZSYN' => ["TL" => 1800],
    'ZULS' => ["TL" => 8100],
    'ZUXC' => ["TL" => 5400],
    'ZWSH' => ["TL" => 4800],
];

if (isset($TLData[$decoded->getIcao()]) && isset($TLData[$decoded->getIcao()]["pressure"])) {
    print ($decoded->getPressure()->getValue() >= $TLData[$decoded->getIcao()]["pressure"]) ? $TLData[$decoded->getIcao()]["TL"] : '3600';
} else {
    print $TLData[$decoded->getIcao()]["TL"] ?? '3600';
}

print(' M ');

//Runway Condition Code
if (isset($phenomenon[0]) && !empty($phenomenon[0])) {
    if ((in_array("DZ", $phenomenon[0]->getTypes()))
        || (in_array("RA", $phenomenon[0]->getTypes()))
        || (in_array("SN", $phenomenon[0]->getTypes()))
        || (in_array("SG", $phenomenon[0]->getTypes()))
    ) {
        print('ALL RWYS RWY CONDITION CODE 5 5 5 ISSUED AT ' . substr($rawMetar, 7, 4) . 'Z ALL PARTS WET DEPTH NOT REPORTED COVERAGE 100PCT ');
    }
}

// Closing Statement
print('REPORT RECEIPT OF ATIS ' . $_GET['info'] . ' ON ' . $decoded->getIcao());

?>
