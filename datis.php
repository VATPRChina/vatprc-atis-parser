<?php
require_once 'vendor/autoload.php';

use MetarDecoder\MetarDecoder;

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
$zspdqnh = $decoder->parse(@file_get_contents('http://metar.vatprc.net/ZSPD') ?: file_get_contents('http://metar.vatsim.net/ZSPD'));
$type = $_GET['atistype'] ?? null;
$adelv = $_GET['adelv'] ?? null; // Value for QFE calculation, type aerodrome elevation in meters.
$notam = $_GET['NOTAM'] ?? null;
$acdm = $_GET['acdm'] ?? null;

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
    print('DEP RWY ' . str_replace(',', ' & ', $_GET['dep']));
} elseif ($type === 'A') {
    print('EXP ' . $_GET['apptype'] . ' APCH LDG RWY ' . str_replace(',', ' & ', $_GET['arr']));
} else {
    print('DEP RWY ' . str_replace(',', ' & ', $_GET['dep']) . ' EXP ' . $_GET['apptype'] . ' APCH LDG RWY ' . str_replace(',', ' & ', $_GET['arr']));
}

// Initialize ctnoutput
$ctnoutput = '';

// Runway Condition Code warning
$runwayConditionWarning = '';
if (isset($phenomenon[0]) && !empty($phenomenon[0])) {
    $types = $phenomenon[0]->getTypes();
    $isPrecipitation = in_array("DZ", $types) || in_array("TS", $types) || in_array("RA", $types) || in_array("SN", $types);
    
    if ($isPrecipitation) {
        $runwayConditionWarning .= 'RWY ';
        if ($type === 'D') {
            $runwayConditionWarning .= str_replace(',', ' & ', $_GET['dep']);
        } elseif ($type === 'A') {
            $runwayConditionWarning .= str_replace(',', ' & ', $_GET['arr']);
        } else {
            $runwayConditionWarning .= str_replace(',', ' & ', $_GET['arr']);
        }
        $runwayConditionWarning .= ' SURFACE CONDITION CODE 5, 5, 5, ISSUED AT ' . substr($rawMetar, 7, 4) . ' ALL PARTS WET, DEPTH NOT REPORTED, COVERAGE 100PCT';
    }
}

// Wind Shear Alert warning
$windShearWarning = '';
if ($decoded->getWindshearAllRunways()) {
    $windShearWarning .= 'WS ALL RWYS ';
} else if ($windShearAlerts) {
    $windShearWarning .= 'WS RWY ';
    foreach ($windShearAlerts as $index => $runway) {
        if ($index >= 1) {
            $windShearWarning .= '& ';
        }
        $windShearWarning .= $runway;
    }
}

// A-CDM message
if ($type !== 'A' && $acdm === '1') {
    $acdmMessage = ' A-CDM IN OPERATION';
} else {
    $acdmMessage = '';
}

// Visual Operation
if (isset($_GET['apptype']) && preg_match('/\b(VIS|VISUAL)\b/i', $_GET['apptype'])) {
    $visualMessage = ' VISUAL APPROACH IS IN PROGRESS RPT UNABLE';
} else {
    $visualMessage = '';
}

// NOTAM message
$notamMessage = '';
if ($notam !== null) {
    $notamMessage .= $notam;
}

// Check if any warning or message exists, add "CTN" if true
if (!empty($runwayConditionWarning) || !empty($windShearWarning) || !empty($acdmMessage) || !empty($visualMessage) || !empty($notamMessage)) {
    $ctnoutput .= ' CTN ';
}

// Append warnings and messages to ctnoutput
$ctnoutput .= $runwayConditionWarning;
$ctnoutput .= $windShearWarning;
$ctnoutput .= $acdmMessage;
$ctnoutput .= $visualMessage;
$ctnoutput .= $notamMessage;

// ctnoutput combined warnings and messages
print strtoupper($ctnoutput);

// Wind
print(' WIND ');

if ($surfaceWindObj->withVariableDirection() == true) {
    print('VRB DEG ');
} else {
    if ($surfaceWindObj->getMeanSpeed()->getValue() == 0) {
        print('000 DEG ');
    } elseif ($surfaceWindObj->getMeanDirection()->getValue() !== 0 && $surfaceWindObj->getMeanDirection()->getValue() < 100) {
        print('0' . $surfaceWindObj->getMeanDirection()->getValue() . ' DEG ');
    } else {
        print ($surfaceWindObj->getMeanDirection()->getValue() . ' DEG ');
    }
}

$raw_sw = $surfaceWindObj->getMeanSpeed()->getValue();
$int_sw = (int)$raw_sw;
$str_sw = strval($int_sw);
$out_sw = $str_sw ;

print($out_sw);

if ($surfaceWindObj->getSpeedVariations() != null) {
    print('G' . $surfaceWindObj->getSpeedVariations()->getValue());
}

print(' MPS ');


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
} elseif ($visObj !== NULL) {
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

$isFirst = true;

foreach ($clouds as $cloud) {
    if (!$isFirst) {
        print(' /');
    } else {
        print('CLOUD ');
        $isFirst = false;
    }
    $amount = $cloud->getAmount();
    $baseHeight = $cloud->getBaseHeight();
    $height = ($baseHeight !== null) ? intval($baseHeight->getValue() * 0.3) : 0;
    print(sprintf('%s %dM', $amount, $height));
}

// Miscellaneous
$temp_data = $decoded->getAirTemperature()->getValue();
$int_temp_data = (int)$temp_data;
$str_temp_data = strval($int_temp_data);
if ($int_temp_data < 10 && $int_temp_data > 0) {
    $out_temp_data = '0' . $str_temp_data ;
} elseif ($int_temp_data < 0 && $int_temp_data > -10) {
    $out_temp_data = 'M0' . $str_temp_data[1];
} elseif ($int_temp_data == 0) {
    $out_temp_data = '00';
} else {
    $out_temp_data = str_replace('-', 'M', $str_temp_data);
}

$dewpt_data = $decoded->getDewPointTemperature()->getValue();
$int_dewpt_data = (int)$dewpt_data;
$str_dewpt_data = strval($int_dewpt_data);
if ($int_dewpt_data < 10 && $int_dewpt_data > 0) {
        $out_dewpt_data = '0' . $str_dewpt_data ;
} elseif ($int_dewpt_data < 0 && $int_dewpt_data > -10) {
        $out_dewpt_data = 'M0' . $str_dewpt_data[1];
} elseif ($int_dewpt_data == 0) {
        $out_dewpt_data = '00';
} else {
        $out_dewpt_data = str_replace('-', 'M', $str_dewpt_data);
}

print(' T ' . $out_temp_data . ' /DP ' . $out_dewpt_data . ' QNH ' . $decoded->getPressure()->getValue() . ' HPA ');

if (is_numeric($adelv)) {
    /**
     * Calculates QFE based on QNH and altitude.
     *
     * @param float $qfe   The initial estimate of QFE.
     * @param float $qnh   The QNH value.
     * @param float $adelv The altitude above sea level.
     *
     * @return float The calculated QFE.
     */
    function qfeFunction($qfe, $qnh, $adelv)
    {
        return $qnh - 1013.25 * pow((1 - 0.0065 * ((44330.77 - 11880.32 * pow($qfe, 0.190263) - $adelv) / 288.15)), 5.25588);
    }
    
    /**
     * Iteratively calculates QFE based on QNH and altitude.
     *
     * @param float $qnh   The QNH value.
     * @param float $adelv The altitude above sea level.
     *
     * @return float The calculated QFE.
     */
    function calculateQFE($qnh, $adelv)
    {
        $tolerance = 0.01;
        $maxIterations = 100;
        $qfe = $qnh - 100;
        $iteration = 0;
    
        while ($iteration < $maxIterations) {
            $f = qfeFunction($qfe, $qnh, $adelv);
            $f_prime = (qfeFunction($qfe + $tolerance, $qnh, $adelv) - $f) / $tolerance;
    
            if (abs($f_prime) < $tolerance) {
                break;
            }
    
            $qfe = $qfe - $f / $f_prime;
            $iteration++;
    
            if (abs($f) < $tolerance) {
                break;
            }
        }
    
        return round($qfe);
    }
    
    $qnh = $decoded->getPressure()->getValue();
    
    $qfe = calculateQFE($qnh, $adelv);
    print('QFE ' . $qfe . ' HPA ');
}

if ($decoded->getIcao() == 'ZSSS') {
    print('QNH OF SHANGHAI TERMINAL CONTROL AREA ' . $zspdqnh->getPressure()->getValue() . ' ');
}

//Pre-set information
if (in_array($decoded->getIcao(), ['ZBAA', 'ZBAD', 'ZBTJ', 'ZBYN', 'ZGGG', 'ZGHA', 'ZGNN', 'ZGSZ', 'ZHCC', 'ZHEC', 'ZHHH', 'ZJHK', 'ZLLL', 'ZLXY', 'ZPPP', 'ZSHC', 'ZSNJ', 'ZSPD', 'ZSQD', 'ZSSS', 'ZUCK', 'ZUGY', 'ZUTF', 'ZUUU', 'ZWWW'])) {
    if (
        (!str_contains($_GET['dep'] ?? '', ',') && !str_contains($_GET['arr'] ?? '', ',')) &&
        ($_GET['dep'] == $_GET['arr'])
    ) {
        print 'SINGLE RUNWAY OPERATION ';
    }
}

//Transition Altitude

$TAData = [
    'ZGGG' => ["TA" => 2700],
    'ZGOW' => ["TA" => 2700],
    'ZGSD' => ["TA" => 2700],
    'ZGSZ' => ["TA" => 2700],
    'ZHSN' => ["TAA" => 4800, "TAB" => 4200, "TA" => 4500],
    'ZHXY' => ["TH" => 1800],
    'ZLLL' => ["TAA" => 5100, "TAB" => 4500, "TA" => 4800],
    'ZLXN' => ["TAA" => 5100, "TAB" => 4500, "TA" => 4800],
    'ZPDL' => ["TAA" => 4500, "TAB" => 3900, "TA" => 4200],
    'ZPJH' => ["TAA" => 4500, "TAB" => 3900, "TA" => 4200],
    'ZPLJ' => ["TAA" => 6300, "TAB" => 5700, "TA" => 6000],
    'ZPMS' => ["TAA" => 4500, "TAB" => 3900, "TA" => 4200],
    'ZPPP' => ["TAA" => 5700, "TAB" => 5100, "TA" => 5400],
    'ZSCG' => ["TA" => 2100],
    'ZSWH' => ["TA" => 1500],
    'ZSWX' => ["TA" => 1800],
    'ZSYN' => ["TA" => 'BY ATC'],
    'ZSYT' => ["TAA" => 3000, "TAB" => 2400, "TA" => 2700],
    'ZULS' => ["TAA" => 7800, "TAB" => 7200, "TA" => 7500],
    'ZUXC' => ["TAA" => 5100, "TAB" => 4500, "TA" => 4800],
    'ZWSH' => ["TH" => 3000],
    'ZMUB' => ["TAA" => 4200, "TAB" => 3900, "TA" => 3900],
    'ZMCK' => ["TAA" => 4200, "TAB" => 3900, "TA" => 3900],
];

print isset($TAData[$decoded->getIcao()]['TA']) ? 'TRANSITION ALTITUDE ' : (isset($TAData[$decoded->getIcao()]['TH']) ? 'TRANSITION HEIGHT ' : 'TRANSITION ALTITUDE ');

if (isset($TAData[$decoded->getIcao()]['TAA']) && isset($TAData[$decoded->getIcao()]['TAB']) && isset($TAData[$decoded->getIcao()]['TA'])) {
    $displayTime = ($decoded->getPressure()->getValue() >= 1031) ? $TAData[$decoded->getIcao()]['TAA'] : (($decoded->getPressure()->getValue() <= 979) ? $TAData[$decoded->getIcao()]['TAB'] : $TAData[$decoded->getIcao()]['TA']);
} else {
    $displayTime = $TAData[$decoded->getIcao()]['TA'] ?? $TAData[$decoded->getIcao()]['TH'] ?? null;
}

print $displayTime ?? (($decoded->getPressure()->getValue() >= 1031) ? 3300 : (($decoded->getPressure()->getValue() <= 979) ? 2700 : 3000));

print(isset($TAData[$decoded->getIcao()]["TA"]) && $TAData[$decoded->getIcao()]["TA"] === 'BY ATC') || (isset($TAData[$decoded->getIcao()]["TH"]) && $TAData[$decoded->getIcao()]["TH"] === 'BY ATC') ? ' ' : ' ';

//Transition Level
print('/LEVEL ');

$TLData = [
    'ZGGG' => ["pressure" => 980, "TL" => 3300],
    'ZGOW' => ["pressure" => 980, "TL" => 3300],
    'ZGSD' => ["pressure" => 980, "TL" => 3300],
    'ZGSZ' => ["pressure" => 980, "TL" => 3300],
    'ZHSN' => ["TL" => 5100],
    'ZHXY' => ["TL" => 2400],
    'ZLLL' => ["TL" => 5400],
    'ZLXN' => ["TL" => 5400],
    'ZMCK' => ["TL" => 4500],
    'ZMUB' => ["TL" => 4500],
    'ZPDL' => ["TL" => 4800],
    'ZPJH' => ["TL" => 4800],
    'ZPLJ' => ["TL" => 6600],
    'ZPMS' => ["TL" => 4800],
    'ZPPP' => ["TL" => 6000],
    'ZSCG' => ["TL" => 2400],
    'ZSWH' => ["TL" => 2100],
    'ZSWX' => ["TL" => 'BY ATC'],
    'ZSYT' => ["TL" => 3300],
    'ZSYN' => ["TL" => 1800],
    'ZULS' => ["TL" => 8100],
    'ZUXC' => ["TL" => 5400],
    'ZWSH' => ["TL" => 4800],
];

if (isset($TLData[$decoded->getIcao()]) && isset($TLData[$decoded->getIcao()]["pressure"])) {
    print($decoded->getPressure()->getValue() >= $TLData[$decoded->getIcao()]["pressure"]) ? $TLData[$decoded->getIcao()]["TL"] : '3600';
} else {
    print $TLData[$decoded->getIcao()]["TL"] ?? '3600';
}

print(isset($TLData[$decoded->getIcao()]["TL"]) && $TLData[$decoded->getIcao()]["TL"] === 'BY ATC') ? ' ' : ' M ';

// Closing Statement
print('ADZ YOU HAVE INFO ' . $_GET['info']);

?>
