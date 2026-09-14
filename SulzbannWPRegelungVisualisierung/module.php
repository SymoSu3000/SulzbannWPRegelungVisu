<?php

declare(strict_types=1);

class SulzbannWPRegelungVisu extends IPSModule
{
    private const OZW_ROOT_ID = 28320;
    private const SOLCAST_PARENT_ID = 16397;

    private const WP_HEATING_ID = 44357;
    private const WP_COOLING_ID = 17689;
    private const OUTSIDE_TEMP_ID = 50237;
    private const WP_FLOW_TEMP_ID = 27923;
    private const WP_RETURN_TEMP_ID = 45419;
    private const WP_POWER_ID = 50450;
    private const WP_HEAT_OUTPUT_ID = 32403;
    private const WP_COP_ID = 37700;
    private const WP_MODULATION_ID = 20837;

    private const BUFFER_TOP_ID = 27553;
    private const BUFFER_MIDDLE_ID = 52270;
    private const BUFFER_BOTTOM_ID = 18594;
    private const DHW_TOP_ID = 39112;
    private const DHW_BOTTOM_ID = 40096;

    private const METEO_MAX_ID = 42622;
    private const METEO_MEAN_ID = 11157;
    private const METEO_MIN_ID = 28499;
    private const METEO_SOLAR_ID = 36980;

    private const ROOM_TEMP_IDS = [
        47619, 43486, 20056, 59980,
        52271, 51361, 43453, 16944, 26539, 12860,
        32416, 25829, 45373, 29653
    ];

    private const VALVE_STATE_IDS = [
        18344, 50892, 37309, 57721,
        52749, 46861, 49967, 15602, 27850, 10375,
        33924, 19131, 58629, 12822
    ];

    private const VALVE_DEMAND_IDS = [
        39391, 22281, 49588, 51260,
        26389, 43578, 36459, 26819, 39276, 16937,
        19263, 17140, 14321, 20806
    ];

    public function Create(): void
    {
        parent::Create();

        // Offiziell für HTML-SDK: 1. Type 2 wird nicht verwendet.
        $this->SetVisualizationType(1);

        // Bestehende Grossansicht bleibt erhalten.
        $this->RegisterVariableString(
            'WPRegelungGross',
            'WP Regelung Gross',
            [
                'PRESENTATION' => VARIABLE_PRESENTATION_WEB_CONTENT,
                'HTML_TYPE' => 0,
                'PADDING' => false
            ],
            20
        );
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetVisualizationType(1);

        foreach ($this->GetSourceVariableIDs() as $variableID) {
            if ($variableID > 0 && IPS_VariableExists($variableID)) {
                $this->RegisterMessage($variableID, VM_UPDATE);
            }
        }

        $this->UpdateGrossVisualization();
    }

    public function GetVisualizationTile(): string
    {
        $file = __DIR__ . '/module.html';

        if (!is_file($file)) {
            return '<div style="padding:60px 12px;color:var(--content-color)">module.html fehlt.</div>';
        }

        $html = file_get_contents($file);

        if ($html === false) {
            return '<div style="padding:60px 12px;color:var(--content-color)">module.html konnte nicht geladen werden.</div>';
        }

        $initialJson = json_encode(
            $this->BuildVisualizationData(),
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        );

        if ($initialJson === false) {
            $initialJson = '{}';
        }

        return str_replace(
            '__SBWRV_INITIAL_DATA__',
            $initialJson,
            $html
        );
    }

    public function MessageSink(
        $TimeStamp,
        $SenderID,
        $Message,
        $Data
    ): void {
        parent::MessageSink(
            $TimeStamp,
            $SenderID,
            $Message,
            $Data
        );

        if ($Message !== VM_UPDATE) {
            return;
        }

        $this->SendLiveValues();
        $this->UpdateGrossVisualization();
    }

    public function RequestAction(
        $Ident,
        $Value
    ): void {
        switch ($Ident) {
            case 'Refresh':
                $this->SendLiveValues();
                $this->UpdateGrossVisualization();
                return;

            // Nur Legacy-/Cache-Fallback.
            case 'OpenSettings':
                $settingsObjectID = $this->FindSettingsObject();

                if (
                    $settingsObjectID > 0 &&
                    IPS_ObjectExists($settingsObjectID)
                ) {
                    $this->OpenObjectLegacy(
                        $settingsObjectID
                    );
                }

                return;

            // Nur Legacy-/Cache-Fallback.
            case 'OpenGross':
                $grossObjectID = $this->GetIDForIdent(
                    'WPRegelungGross'
                );

                if (
                    $grossObjectID > 0 &&
                    IPS_ObjectExists($grossObjectID)
                ) {
                    $this->OpenObjectLegacy(
                        $grossObjectID
                    );
                }

                return;

            default:
                throw new Exception(
                    'Invalid Ident: ' .
                    $Ident
                );
        }
    }

    private function OpenObjectLegacy(
        int $objectID
    ): void {
        if (
            $objectID <= 0 ||
            !IPS_ObjectExists($objectID)
        ) {
            return;
        }

        foreach (
            IPS_GetInstanceList()
            as $instanceID
        ) {
            $instance = IPS_GetInstance(
                $instanceID
            );

            $moduleName = (string) (
                $instance['ModuleInfo']['ModuleName']
                ??
                ''
            );

            $isTileVisualization =
                stripos(
                    $moduleName,
                    'Kachel Visualisierung'
                ) !== false
                ||
                stripos(
                    $moduleName,
                    'Tile Visualization'
                ) !== false;

            if (!$isTileVisualization) {
                continue;
            }

            try {
                VISU_OpenObject(
                    (int) $instanceID,
                    $objectID,
                    ''
                );

            } catch (Throwable $e) {
                $this->SendDebug(
                    'LegacyNavigation',
                    $e->getMessage(),
                    0
                );
            }
        }
    }

    private function SendLiveValues(): void
    {
        $json = json_encode(
            $this->BuildVisualizationData(),
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            return;
        }

        $this->UpdateVisualizationValue(
            $json
        );
    }

    private function UpdateGrossVisualization(): void
    {
        $grossID = $this->GetIDForIdent(
            'WPRegelungGross'
        );

        if (
            $grossID <= 0 ||
            !IPS_VariableExists($grossID)
        ) {
            return;
        }

        $html = $this->BuildGrossVisualization();

        if (
            GetValueString($grossID)
            !==
            $html
        ) {
            SetValueString(
                $grossID,
                $html
            );
        }
    }

    private function BuildGrossVisualization(): string
    {
        $d = $this->BuildVisualizationData();

        $wp = $d['wp'];
        $rooms = $d['rooms'];
        $fbh = $d['fbh'];
        $dhw = $d['dhw'];
        $buffer = $d['buffer'];
        $meteo = $d['meteo'];
        $solcast = $d['solcast'];

        $mode = $this->H(
            (string) $wp['mode']
        );

        $modeCode = $this->H(
            (string) $wp['modeCode']
        );

        $outside = $this->H(
            $this->FormatTemperature(
                $wp['outside']
            )
        );

        $flow = $this->H(
            $this->FormatTemperature(
                $wp['flow']
            )
        );

        $return = $this->H(
            $this->FormatTemperature(
                $wp['return']
            )
        );

        $modulation = $this->H(
            $this->FormatPercent(
                $wp['modulation']
            )
        );

        $wpPower = $this->H(
            $this->FormatPower(
                $wp['power']
            )
        );

        $heatOutput = $this->H(
            $this->FormatPower(
                $wp['heatOutput']
            )
        );

        $cop = $this->H(
            $wp['cop'] !== null
                ?
                number_format(
                    (float) $wp['cop'],
                    2,
                    '.',
                    ''
                )
                :
                '—'
        );

        $roomAverage = $this->H(
            $this->FormatTemperature(
                $rooms['average']
            )
        );

        $roomRange =
            (
                $rooms['minimum'] !== null &&
                $rooms['maximum'] !== null
            )
                ?
                number_format(
                    (float) $rooms['minimum'],
                    1,
                    '.',
                    ''
                )
                .
                ' / '
                .
                number_format(
                    (float) $rooms['maximum'],
                    1,
                    '.',
                    ''
                )
                .
                ' °C'
                :
                '—';

        $roomRange = $this->H(
            $roomRange
        );

        $valves = $this->H(
            (int) $fbh['open']
            .
            ' / '
            .
            (int) $fbh['total']
        );

        $demandAverage = $this->H(
            $this->FormatPercent(
                $fbh['demandAverage']
            )
        );

        $demandMaximum = $this->H(
            $this->FormatPercent(
                $fbh['demandMaximum']
            )
        );

        $dhwTop = $this->H(
            $this->FormatTemperature(
                $dhw['top']
            )
        );

        $dhwBottom = $this->H(
            $this->FormatTemperature(
                $dhw['bottom']
            )
        );

        $bufferTop = $this->H(
            $this->FormatTemperature(
                $buffer['top']
            )
        );

        $bufferMiddle = $this->H(
            $this->FormatTemperature(
                $buffer['middle']
            )
        );

        $bufferBottom = $this->H(
            $this->FormatTemperature(
                $buffer['bottom']
            )
        );

        $meteoMaximum = $this->H(
            $this->FormatTemperature(
                $meteo['maximum']
            )
        );

        $meteoMean = $this->H(
            $this->FormatTemperature(
                $meteo['mean']
            )
        );

        $meteoMinimum = $this->H(
            $this->FormatTemperature(
                $meteo['minimum']
            )
        );

        $meteoSolar = $this->H(
            $meteo['solar'] !== null
                ?
                number_format(
                    (float) $meteo['solar'],
                    2,
                    '.',
                    ''
                )
                .
                ' kWh/m²'
                :
                '—'
        );

        $solcastEnergy = $this->H(
            $solcast['energyP50'] !== null
                ?
                number_format(
                    (float) $solcast['energyP50'],
                    1,
                    '.',
                    ''
                )
                .
                ' kWh'
                :
                '—'
        );

        $solcastPeak = $this->H(
            $this->FormatPower(
                $solcast['peak']
            )
        );

        $solcastConfidence = $this->H(
            $this->FormatPercent(
                $solcast['confidence']
            )
        );

        $peakTime = $this->H(
            $this->FormatTimestamp(
                $solcast['peakTime']
            )
        );

        $peakWindow =
            (
                $solcast['peakWindowStart'] > 0 &&
                $solcast['peakWindowEnd'] > 0
            )
                ?
                $this->FormatTimestamp(
                    $solcast['peakWindowStart']
                )
                .
                ' – '
                .
                $this->FormatTimestamp(
                    $solcast['peakWindowEnd']
                )
                :
                '—';

        $peakWindow = $this->H(
            $peakWindow
        );

        $lastUpdate = date(
            'H:i',
            (int) $d['timestamp']
        );

        return <<<HTML
<!doctype html>
<html lang="de">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1, viewport-fit=cover"
>

<style>

:root {
    color-scheme: light dark;

    --text:#202124;
    --muted:#68747b;

    --card:#eef1f3;
    --card-strong:#e6ebee;

    --border:#c7d0d5;

    --heating:#d97f2b;
    --cooling:#2e8ec8;
    --standby:#78909c;
    --fault:#d84a4a;
}


@media (prefers-color-scheme:dark) {

    :root {
        --text:#f3f5f7;
        --muted:#b8c6cd;

        --card:#3d3f43;
        --card-strong:#44474b;

        --border:#63686d;

        --heating:#ff9c48;
        --cooling:#46a9e5;
        --standby:#91a0a8;
        --fault:#ef6262;
    }
}


* {
    box-sizing:border-box;
}


html,
body {
    margin:0;
    padding:0;

    width:100%;
    min-height:100%;

    background:transparent;

    color:var(--text);

    font-family:
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Arial,
        sans-serif;
}


.page {
    width:100%;
    padding:16px;
}


.hero {
    display:grid;

    grid-template-columns:
        minmax(240px,1.25fr)
        repeat(
            3,
            minmax(150px,.75fr)
        );

    gap:10px;

    margin-bottom:10px;
}


.hero-card,
.section {
    background:var(--card);

    border:
        1px
        solid
        var(--border);

    border-radius:12px;
}


.hero-main {
    padding:16px;
    background:var(--card-strong);
}


.hero-mini {
    min-height:88px;
    padding:13px;
}


.mode-line {
    display:flex;
    align-items:center;
    gap:10px;
}


.mode-dot {
    width:12px;
    height:12px;

    border-radius:50%;

    background:var(--standby);
}


.mode-dot.heating {
    background:var(--heating);
}


.mode-dot.cooling {
    background:var(--cooling);
}


.mode-dot.standby {
    background:var(--standby);
}


.mode-dot.fault {
    background:var(--fault);
}


.label {
    font-size:13px;
    line-height:1.25;

    color:var(--muted);
}


.hero-value {
    margin-top:4px;

    font-size:24px;
    line-height:1.15;

    font-weight:700;
}


.value {
    margin-top:4px;

    font-size:18px;
    line-height:1.2;

    font-weight:700;
}


.layout {
    display:grid;

    grid-template-columns:
        repeat(
            2,
            minmax(0,1fr)
        );

    gap:10px;
}


.section {
    padding:14px;
}


.section-title {
    margin-bottom:13px;

    font-size:16px;
    font-weight:700;
}


.data-grid {
    display:grid;

    grid-template-columns:
        repeat(
            2,
            minmax(0,1fr)
        );

    gap:
        14px
        22px;
}


.data-item {
    min-height:46px;
}


.section-wide {
    grid-column:
        1
        /
        -1;
}


.forecast-grid {
    display:grid;

    grid-template-columns:
        repeat(
            4,
            minmax(0,1fr)
        );

    gap:
        14px
        22px;
}


.peak-window {
    margin-top:14px;

    padding-top:12px;

    border-top:
        1px
        solid
        var(--border);
}


.footer {
    margin-top:10px;

    text-align:right;

    font-size:12px;

    color:var(--muted);
}


@media (max-width:1000px) {

    .hero {
        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );
    }

    .hero-main {
        grid-column:
            1
            /
            -1;
    }

    .forecast-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );
    }
}


@media (max-width:700px) {

    .page {
        padding:10px;
    }

    .hero,
    .layout {
        grid-template-columns:1fr;
    }

    .hero-main,
    .section-wide {
        grid-column:auto;
    }

    .forecast-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );
    }

    .label {
        font-size:12px;
    }

    .value {
        font-size:17px;
    }
}


@media (max-width:420px) {

    .data-grid,
    .forecast-grid {
        grid-template-columns:1fr;
    }
}

</style>

</head>


<body>

<div class="page">


    <div class="hero">


        <div class="hero-card hero-main">

            <div class="label">
                WP Master-Betriebsart
            </div>

            <div class="mode-line">

                <div
                    class="mode-dot {$modeCode}"
                ></div>

                <div class="hero-value">
                    {$mode}
                </div>

            </div>

        </div>


        <div class="hero-card hero-mini">

            <div class="label">
                Aussentemperatur
            </div>

            <div class="value">
                {$outside}
            </div>

        </div>


        <div class="hero-card hero-mini">

            <div class="label">
                Vorlauf / Rücklauf
            </div>

            <div class="value">
                {$flow} / {$return}
            </div>

        </div>


        <div class="hero-card hero-mini">

            <div class="label">
                Modulation / COP
            </div>

            <div class="value">
                {$modulation} / {$cop}
            </div>

        </div>


    </div>



    <div class="layout">


        <div class="section">

            <div class="section-title">
                Wärmepumpe
            </div>


            <div class="data-grid">

                <div class="data-item">
                    <div class="label">Aussentemperatur</div>
                    <div class="value">{$outside}</div>
                </div>

                <div class="data-item">
                    <div class="label">Vorlauf</div>
                    <div class="value">{$flow}</div>
                </div>

                <div class="data-item">
                    <div class="label">Rücklauf</div>
                    <div class="value">{$return}</div>
                </div>

                <div class="data-item">
                    <div class="label">Verdichtermodulation</div>
                    <div class="value">{$modulation}</div>
                </div>

                <div class="data-item">
                    <div class="label">Leistungsaufnahme</div>
                    <div class="value">{$wpPower}</div>
                </div>

                <div class="data-item">
                    <div class="label">Wärmeleistung</div>
                    <div class="value">{$heatOutput}</div>
                </div>

                <div class="data-item">
                    <div class="label">COP</div>
                    <div class="value">{$cop}</div>
                </div>

            </div>

        </div>



        <div class="section">

            <div class="section-title">
                Boiler / Puffer
            </div>


            <div class="data-grid">

                <div class="data-item">
                    <div class="label">Boiler oben</div>
                    <div class="value">{$dhwTop}</div>
                </div>

                <div class="data-item">
                    <div class="label">Boiler unten</div>
                    <div class="value">{$dhwBottom}</div>
                </div>

                <div class="data-item">
                    <div class="label">Puffer oben</div>
                    <div class="value">{$bufferTop}</div>
                </div>

                <div class="data-item">
                    <div class="label">Puffer Mitte</div>
                    <div class="value">{$bufferMiddle}</div>
                </div>

                <div class="data-item">
                    <div class="label">Puffer unten</div>
                    <div class="value">{$bufferBottom}</div>
                </div>

            </div>

        </div>



        <div class="section">

            <div class="section-title">
                Räume / Fussbodenheizung
            </div>


            <div class="data-grid">

                <div class="data-item">
                    <div class="label">Raumtemperatur Mittel</div>
                    <div class="value">{$roomAverage}</div>
                </div>

                <div class="data-item">
                    <div class="label">Raum Min. / Max.</div>
                    <div class="value">{$roomRange}</div>
                </div>

                <div class="data-item">
                    <div class="label">Ventile offen</div>
                    <div class="value">{$valves}</div>
                </div>

                <div class="data-item">
                    <div class="label">Stellwert Mittel</div>
                    <div class="value">{$demandAverage}</div>
                </div>

                <div class="data-item">
                    <div class="label">Stellwert Maximum</div>
                    <div class="value">{$demandMaximum}</div>
                </div>

            </div>

        </div>



        <div class="section section-wide">

            <div class="section-title">
                Wetter / PV Prognose
            </div>


            <div class="forecast-grid">

                <div class="data-item">
                    <div class="label">Morgen Maximum</div>
                    <div class="value">{$meteoMaximum}</div>
                </div>

                <div class="data-item">
                    <div class="label">Morgen Mittel</div>
                    <div class="value">{$meteoMean}</div>
                </div>

                <div class="data-item">
                    <div class="label">Morgen Minimum</div>
                    <div class="value">{$meteoMinimum}</div>
                </div>

                <div class="data-item">
                    <div class="label">Globalstrahlung</div>
                    <div class="value">{$meteoSolar}</div>
                </div>

                <div class="data-item">
                    <div class="label">Solcast P50 0–24 h</div>
                    <div class="value">{$solcastEnergy}</div>
                </div>

                <div class="data-item">
                    <div class="label">PV Peak</div>
                    <div class="value">{$solcastPeak}</div>
                </div>

                <div class="data-item">
                    <div class="label">Peak Zeitpunkt</div>
                    <div class="value">{$peakTime}</div>
                </div>

                <div class="data-item">
                    <div class="label">Vertrauen</div>
                    <div class="value">{$solcastConfidence}</div>
                </div>

            </div>


            <div class="peak-window">

                <div class="label">
                    Peakfenster
                </div>

                <div class="value">
                    {$peakWindow}
                </div>

            </div>

        </div>


    </div>


    <div class="footer">
        Aktualisiert {$lastUpdate}
    </div>


</div>

</body>

</html>
HTML;
    }

    private function BuildVisualizationData(): array
    {
        $heating = $this->ReadBool(
            self::WP_HEATING_ID
        );

        $cooling = $this->ReadBool(
            self::WP_COOLING_ID
        );

        if (
            $heating &&
            !$cooling
        ) {
            $wpMode = 'Heizen';
            $wpModeCode = 'heating';

        } elseif (
            $cooling &&
            !$heating
        ) {
            $wpMode = 'Kühlen';
            $wpModeCode = 'cooling';

        } elseif (
            !$heating &&
            !$cooling
        ) {
            $wpMode = 'Standby';
            $wpModeCode = 'standby';

        } else {
            $wpMode = 'Unplausibel';
            $wpModeCode = 'fault';
        }

        $roomTemperatures = [];

        foreach (
            self::ROOM_TEMP_IDS
            as $variableID
        ) {
            $value = $this->ReadFloatNullable(
                $variableID
            );

            if (
                $value !== null &&
                $value > -30.0 &&
                $value < 60.0
            ) {
                $roomTemperatures[] =
                    $value;
            }
        }

        $roomAverage = null;
        $roomMinimum = null;
        $roomMaximum = null;

        if (
            count(
                $roomTemperatures
            ) > 0
        ) {
            $roomAverage =
                array_sum(
                    $roomTemperatures
                )
                /
                count(
                    $roomTemperatures
                );

            $roomMinimum =
                min(
                    $roomTemperatures
                );

            $roomMaximum =
                max(
                    $roomTemperatures
                );
        }

        $openValves = 0;

        foreach (
            self::VALVE_STATE_IDS
            as $variableID
        ) {
            if (
                $this->ReadBool(
                    $variableID
                )
            ) {
                $openValves++;
            }
        }

        $demands = [];

        foreach (
            self::VALVE_DEMAND_IDS
            as $variableID
        ) {
            $value = $this->ReadFloatNullable(
                $variableID
            );

            if ($value !== null) {
                $demands[] =
                    $value;
            }
        }

        $demandAverage = null;
        $demandMaximum = null;

        if (
            count($demands)
            >
            0
        ) {
            $demandAverage =
                array_sum($demands)
                /
                count($demands);

            $demandMaximum =
                max($demands);
        }

        $settingsObjectID =
            $this->FindSettingsObject();

        $grossObjectID =
            $this->GetIDForIdent(
                'WPRegelungGross'
            );

        return [
            'timestamp' =>
                time(),

            'settingsObjectID' =>
                (
                    $settingsObjectID > 0 &&
                    IPS_ObjectExists(
                        $settingsObjectID
                    )
                )
                    ?
                    $settingsObjectID
                    :
                    0,

            'grossObjectID' =>
                (
                    $grossObjectID > 0 &&
                    IPS_ObjectExists(
                        $grossObjectID
                    )
                )
                    ?
                    $grossObjectID
                    :
                    0,

            'wp' => [
                'mode' =>
                    $wpMode,

                'modeCode' =>
                    $wpModeCode,

                'heating' =>
                    $heating,

                'cooling' =>
                    $cooling,

                'outside' =>
                    $this->ReadFloatNullable(
                        self::OUTSIDE_TEMP_ID
                    ),

                'flow' =>
                    $this->ReadFloatNullable(
                        self::WP_FLOW_TEMP_ID
                    ),

                'return' =>
                    $this->ReadFloatNullable(
                        self::WP_RETURN_TEMP_ID
                    ),

                'power' =>
                    $this->ReadFloatNullable(
                        self::WP_POWER_ID
                    ),

                'heatOutput' =>
                    $this->ReadFloatNullable(
                        self::WP_HEAT_OUTPUT_ID
                    ),

                'cop' =>
                    $this->ReadFloatNullable(
                        self::WP_COP_ID
                    ),

                'modulation' =>
                    $this->ReadFloatNullable(
                        self::WP_MODULATION_ID
                    )
            ],

            'rooms' => [
                'count' =>
                    count(
                        $roomTemperatures
                    ),

                'average' =>
                    $roomAverage,

                'minimum' =>
                    $roomMinimum,

                'maximum' =>
                    $roomMaximum
            ],

            'fbh' => [
                'open' =>
                    $openValves,

                'total' =>
                    count(
                        self::VALVE_STATE_IDS
                    ),

                'demandAverage' =>
                    $demandAverage,

                'demandMaximum' =>
                    $demandMaximum
            ],

            'buffer' => [
                'top' =>
                    $this->ReadFloatNullable(
                        self::BUFFER_TOP_ID
                    ),

                'middle' =>
                    $this->ReadFloatNullable(
                        self::BUFFER_MIDDLE_ID
                    ),

                'bottom' =>
                    $this->ReadFloatNullable(
                        self::BUFFER_BOTTOM_ID
                    )
            ],

            'dhw' => [
                'top' =>
                    $this->ReadFloatNullable(
                        self::DHW_TOP_ID
                    ),

                'bottom' =>
                    $this->ReadFloatNullable(
                        self::DHW_BOTTOM_ID
                    )
            ],

            'meteo' => [
                'maximum' =>
                    $this->ReadFloatNullable(
                        self::METEO_MAX_ID
                    ),

                'mean' =>
                    $this->ReadFloatNullable(
                        self::METEO_MEAN_ID
                    ),

                'minimum' =>
                    $this->ReadFloatNullable(
                        self::METEO_MIN_ID
                    ),

                'solar' =>
                    $this->ReadFloatNullable(
                        self::METEO_SOLAR_ID
                    )
            ],

            'solcast' => [
                'energyP50' =>
                    $this->ReadSolcastFloat(
                        'SolcastEnergy024P50'
                    ),

                'peak' =>
                    $this->ReadSolcastFloat(
                        'SolcastPeak024'
                    ),

                'confidence' =>
                    $this->ReadSolcastFloat(
                        'SolcastConfidence024'
                    ),

                'peakTime' =>
                    $this->ReadSolcastInteger(
                        'SolcastPeakTime024'
                    ),

                'peakWindowStart' =>
                    $this->ReadSolcastInteger(
                        'SolcastPeakWindowStart024'
                    ),

                'peakWindowEnd' =>
                    $this->ReadSolcastInteger(
                        'SolcastPeakWindowEnd024'
                    )
            ]
        ];
    }

    private function GetSourceVariableIDs(): array
    {
        $ids = [
            self::WP_HEATING_ID,
            self::WP_COOLING_ID,

            self::OUTSIDE_TEMP_ID,
            self::WP_FLOW_TEMP_ID,
            self::WP_RETURN_TEMP_ID,

            self::BUFFER_TOP_ID,
            self::BUFFER_MIDDLE_ID,
            self::BUFFER_BOTTOM_ID,

            self::DHW_TOP_ID,
            self::DHW_BOTTOM_ID,

            self::WP_POWER_ID,
            self::WP_HEAT_OUTPUT_ID,
            self::WP_COP_ID,
            self::WP_MODULATION_ID,

            self::METEO_MAX_ID,
            self::METEO_MEAN_ID,
            self::METEO_MIN_ID,
            self::METEO_SOLAR_ID
        ];

        $ids = array_merge(
            $ids,
            self::ROOM_TEMP_IDS,
            self::VALVE_STATE_IDS,
            self::VALVE_DEMAND_IDS
        );

        foreach (
            [
                'SolcastEnergy024P50',
                'SolcastPeak024',
                'SolcastConfidence024',
                'SolcastPeakTime024',
                'SolcastPeakWindowStart024',
                'SolcastPeakWindowEnd024'
            ]
            as $ident
        ) {
            $id = $this->GetSolcastVariableID(
                $ident
            );

            if ($id > 0) {
                $ids[] =
                    $id;
            }
        }

        return array_values(
            array_unique(
                $ids
            )
        );
    }

    private function GetSolcastVariableID(
        string $ident
    ): int {
        if (
            !IPS_ObjectExists(
                self::SOLCAST_PARENT_ID
            )
        ) {
            return 0;
        }

        $id = @IPS_GetObjectIDByIdent(
            $ident,
            self::SOLCAST_PARENT_ID
        );

        if (
            $id === false ||
            !IPS_VariableExists(
                (int) $id
            )
        ) {
            return 0;
        }

        return
            (int) $id;
    }

    private function ReadSolcastFloat(
        string $ident
    ): ?float {
        $id =
            $this->GetSolcastVariableID(
                $ident
            );

        return
            $id > 0
                ?
                $this->ReadFloatNullable(
                    $id
                )
                :
                null;
    }

    private function ReadSolcastInteger(
        string $ident
    ): int {
        $id =
            $this->GetSolcastVariableID(
                $ident
            );

        return
            $id > 0
                ?
                (int) GetValue($id)
                :
                0;
    }

    private function FindSettingsObject(): int
    {
        if (
            !IPS_ObjectExists(
                self::OZW_ROOT_ID
            )
        ) {
            return 0;
        }

        $wpRegulation =
            $this->FindRecursiveByName(
                self::OZW_ROOT_ID,
                'WP Regelung'
            );

        if ($wpRegulation <= 0) {
            return 0;
        }

        $parameters =
            $this->FindRecursiveByName(
                $wpRegulation,
                'Parameter'
            );

        return
            $parameters > 0
                ?
                $parameters
                :
                $wpRegulation;
    }

    private function FindRecursiveByName(
        int $parentID,
        string $name
    ): int {
        if (
            $parentID <= 0 ||
            !IPS_ObjectExists($parentID)
        ) {
            return 0;
        }

        $queue = [
            $parentID
        ];

        while (
            count($queue)
            >
            0
        ) {
            $current =
                array_shift(
                    $queue
                );

            foreach (
                IPS_GetChildrenIDs(
                    $current
                )
                as $childID
            ) {
                if (
                    IPS_GetName($childID)
                    ===
                    $name
                ) {
                    return
                        (int) $childID;
                }

                $queue[] =
                    (int) $childID;
            }
        }

        return 0;
    }

    private function FormatTemperature(
        ?float $value
    ): string {
        return
            $value === null
                ?
                '—'
                :
                number_format(
                    $value,
                    1,
                    '.',
                    ''
                )
                .
                ' °C';
    }

    private function FormatPower(
        ?float $value
    ): string {
        return
            $value === null
                ?
                '—'
                :
                number_format(
                    $value,
                    2,
                    '.',
                    ''
                )
                .
                ' kW';
    }

    private function FormatPercent(
        ?float $value
    ): string {
        return
            $value === null
                ?
                '—'
                :
                number_format(
                    $value,
                    0,
                    '.',
                    ''
                )
                .
                ' %';
    }

    private function FormatTimestamp(
        int $timestamp
    ): string {
        return
            $timestamp <= 0
                ?
                '—'
                :
                date(
                    'H:i',
                    $timestamp
                );
    }

    private function H(
        string $value
    ): string {
        return
            htmlspecialchars(
                $value,
                ENT_QUOTES |
                ENT_SUBSTITUTE,
                'UTF-8'
            );
    }

    private function ReadBool(
        int $variableID
    ): bool {
        if (
            $variableID <= 0 ||
            !IPS_VariableExists(
                $variableID
            )
        ) {
            return false;
        }

        return
            (bool) GetValue(
                $variableID
            );
    }

    private function ReadFloatNullable(
        int $variableID
    ): ?float {
        if (
            $variableID <= 0 ||
            !IPS_VariableExists(
                $variableID
            )
        ) {
            return null;
        }

        $value =
            GetValue(
                $variableID
            );

        return
            is_numeric($value)
                ?
                (float) $value
                :
                null;
    }
}
