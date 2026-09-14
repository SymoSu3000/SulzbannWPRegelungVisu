<?php

declare(strict_types=1);

class SulzbannWPRegelungVisu extends IPSModule
{
    /*
     * =========================================================================
     * SULZBANN WP REGELUNG VISU
     * =========================================================================
     *
     * IP-Symcon 9.0
     *
     * KLEIN:
     * - HTML-SDK
     * - VisualizationType 1
     * - module.html
     *
     * GROSS:
     * - eigenes WebContent-Unterobjekt
     * - VARIABLE_PRESENTATION_WEB_CONTENT
     * - wird aus der kleinen Kachel direkt geöffnet
     *
     * WP bleibt absoluter Master.
     * Keine Schreibbefehle an WP / OZW / KNX.
     *
     * =========================================================================
     */

    private const SETTINGS_OBJECT_ID = 26699;
    private const SOLCAST_PARENT_ID = 16397;

    /*
     * -------------------------------------------------------------------------
     * WP / OZW
     * -------------------------------------------------------------------------
     */

    private const WP_HEATING_ID = 44357;
    private const WP_COOLING_ID = 17689;

    private const OUTSIDE_TEMP_ID = 50237;
    private const WP_FLOW_TEMP_ID = 27923;
    private const WP_RETURN_TEMP_ID = 45419;

    private const WP_POWER_ID = 50450;
    private const WP_HEAT_OUTPUT_ID = 32403;
    private const WP_COP_ID = 37700;
    private const WP_MODULATION_ID = 20837;

    /*
     * -------------------------------------------------------------------------
     * SPEICHER
     * -------------------------------------------------------------------------
     */

    private const BUFFER_TOP_ID = 27553;
    private const BUFFER_MIDDLE_ID = 52270;
    private const BUFFER_BOTTOM_ID = 18594;

    private const DHW_TOP_ID = 39112;
    private const DHW_BOTTOM_ID = 40096;

    /*
     * -------------------------------------------------------------------------
     * METEO MORGEN
     * -------------------------------------------------------------------------
     */

    private const METEO_MAX_ID = 42622;
    private const METEO_MEAN_ID = 11157;
    private const METEO_MIN_ID = 28499;
    private const METEO_SOLAR_ID = 36980;

    /*
     * -------------------------------------------------------------------------
     * RÄUME
     * -------------------------------------------------------------------------
     */

    private const ROOM_TEMP_IDS = [
        // EG
        47619,
        43486,
        20056,
        59980,

        // OG
        52271,
        51361,
        43453,
        16944,
        26539,
        12860,

        // ELW
        32416,
        25829,
        45373,
        29653
    ];

    /*
     * -------------------------------------------------------------------------
     * FBH VENTILSTATUS
     * false = geschlossen
     * true  = offen
     * -------------------------------------------------------------------------
     */

    private const VALVE_STATE_IDS = [
        // EG
        18344,
        50892,
        37309,
        57721,

        // OG
        52749,
        46861,
        49967,
        15602,
        27850,
        10375,

        // ELW
        33924,
        19131,
        58629,
        12822
    ];

    /*
     * -------------------------------------------------------------------------
     * FBH STELLWERTE
     * -------------------------------------------------------------------------
     */

    private const VALVE_DEMAND_IDS = [
        // EG
        39391,
        22281,
        49588,
        51260,

        // OG
        26389,
        43578,
        36459,
        26819,
        39276,
        16937,

        // ELW
        19263,
        17140,
        14321,
        20806
    ];

    /*
     * =========================================================================
     * CREATE
     * =========================================================================
     */

    public function Create(): void
    {
        parent::Create();

        /*
         * Funktionierende kleine HTML-SDK-Kachel.
         */
        $this->SetVisualizationType(1);

        /*
         * Bewährter Grossansichtsweg aus dem früheren Heizungsmodul.
         */
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

    /*
     * =========================================================================
     * APPLY CHANGES
     * =========================================================================
     */

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->SetVisualizationType(1);

        foreach ($this->GetSourceVariableIDs() as $variableID) {
            if (
                $variableID > 0
                &&
                IPS_VariableExists($variableID)
            ) {
                $this->RegisterMessage(
                    $variableID,
                    VM_UPDATE
                );
            }
        }

        /*
         * Grossansicht initial erzeugen.
         */
        $this->UpdateGrossVisualization();
    }

    /*
     * =========================================================================
     * KLEINE HTML-SDK-KACHEL
     * =========================================================================
     */

    public function GetVisualizationTile(): string
    {
        $file = __DIR__ . '/module.html';

        if (!file_exists($file)) {
            return '
                <div style="
                    padding:20px;
                    color:var(--content-color);
                ">
                    module.html fehlt.
                </div>
            ';
        }

        $html = file_get_contents($file);

        if ($html === false) {
            return '
                <div style="
                    padding:20px;
                    color:var(--content-color);
                ">
                    module.html konnte nicht geladen werden.
                </div>
            ';
        }

        $json = json_encode(
            $this->BuildVisualizationData(),
            JSON_UNESCAPED_UNICODE
            |
            JSON_UNESCAPED_SLASHES
            |
            JSON_HEX_TAG
            |
            JSON_HEX_AMP
            |
            JSON_HEX_APOS
            |
            JSON_HEX_QUOT
        );

        if ($json === false) {
            $json = '{}';
        }

        return str_replace(
            '__SBWRV_INITIAL_DATA__',
            $json,
            $html
        );
    }

    /*
     * =========================================================================
     * MESSAGES / LIVE-UPDATES
     * =========================================================================
     */

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

        /*
         * Kleine HTML-SDK-Kachel:
         * nur JSON-Liveupdate.
         */
        $this->SendLiveValues();

        /*
         * Grossansicht:
         * WebContent aktualisieren.
         */
        $this->UpdateGrossVisualization();
    }

    /*
     * =========================================================================
     * ACTIONS AUS module.html
     * =========================================================================
     */

    public function RequestAction(
        $Ident,
        $Value
    ): void {
        switch ($Ident) {

            case 'Refresh':

                $this->SendLiveValues();
                $this->UpdateGrossVisualization();

                return;


            case 'OpenSettings':

                $this->OpenObjectInTileVisualization(
                    self::SETTINGS_OBJECT_ID
                );

                return;


            case 'OpenGross':

                $grossID =
                    $this->GetIDForIdent(
                        'WPRegelungGross'
                    );

                if (
                    $grossID > 0
                    &&
                    IPS_ObjectExists($grossID)
                ) {
                    $this->OpenObjectInTileVisualization(
                        $grossID
                    );
                }

                return;


            default:

                throw new Exception(
                    'Ungültige Aktion: '
                    .
                    $Ident
                );
        }
    }

    /*
     * =========================================================================
     * OBJEKT DIREKT IN KACHELVISUALISIERUNG ÖFFNEN
     * =========================================================================
     */

    private function OpenObjectInTileVisualization(
        int $objectID
    ): void {
        if (
            $objectID <= 0
            ||
            !IPS_ObjectExists($objectID)
        ) {
            return;
        }

        foreach (
            IPS_GetInstanceList()
            as $instanceID
        ) {
            $instance =
                IPS_GetInstance(
                    $instanceID
                );

            $moduleName =
                (string) (
                    $instance['ModuleInfo']['ModuleName']
                    ??
                    ''
                );

            $isTileVisualization =
                stripos(
                    $moduleName,
                    'Kachel Visualisierung'
                )
                !== false
                ||
                stripos(
                    $moduleName,
                    'Tile Visualization'
                )
                !== false;

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
                    'OpenObject',
                    'VISU #'
                    .
                    $instanceID
                    .
                    ': '
                    .
                    $e->getMessage(),
                    0
                );
            }
        }
    }

    /*
     * =========================================================================
     * KLEINE KACHEL - LIVE JSON
     * =========================================================================
     */

    private function SendLiveValues(): void
    {
        $json =
            json_encode(
                $this->BuildVisualizationData(),
                JSON_UNESCAPED_UNICODE
                |
                JSON_UNESCAPED_SLASHES
            );

        if ($json === false) {
            return;
        }

        $this->UpdateVisualizationValue(
            $json
        );
    }

    /*
     * =========================================================================
     * GROSSANSICHT
     * =========================================================================
     */

    private function UpdateGrossVisualization(): void
    {
        $grossID =
            $this->GetIDForIdent(
                'WPRegelungGross'
            );

        if (
            $grossID <= 0
            ||
            !IPS_VariableExists($grossID)
        ) {
            return;
        }

        $html =
            $this->BuildGrossVisualization();

        /*
         * Nur schreiben, wenn sich der Inhalt wirklich geändert hat.
         */
        $current =
            GetValueString(
                $grossID
            );

        if ($current !== $html) {
            SetValueString(
                $grossID,
                $html
            );
        }
    }

    private function BuildGrossVisualization(): string
    {
        $d =
            $this->BuildVisualizationData();

        $wp =
            $d['wp'];

        $rooms =
            $d['rooms'];

        $fbh =
            $d['fbh'];

        $dhw =
            $d['dhw'];

        $buffer =
            $d['buffer'];

        $meteo =
            $d['meteo'];

        $solcast =
            $d['solcast'];

        /*
         * ---------------------------------------------------------------------
         * FORMATIERUNG
         * ---------------------------------------------------------------------
         */

        $mode =
            htmlspecialchars(
                (string) $wp['mode'],
                ENT_QUOTES
            );

        $modeCode =
            htmlspecialchars(
                (string) $wp['modeCode'],
                ENT_QUOTES
            );

        $roomAverage =
            $this->FormatTemperature(
                $rooms['average']
            );

        $roomRange =
            (
                $rooms['minimum'] !== null
                &&
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

        $valves =
            (int) $fbh['open']
            .
            ' / '
            .
            (int) $fbh['total'];

        $demandAverage =
            $this->FormatPercent(
                $fbh['demandAverage']
            );

        $demandMaximum =
            $this->FormatPercent(
                $fbh['demandMaximum']
            );

        $outside =
            $this->FormatTemperature(
                $wp['outside']
            );

        $flow =
            $this->FormatTemperature(
                $wp['flow']
            );

        $return =
            $this->FormatTemperature(
                $wp['return']
            );

        $modulation =
            $this->FormatPercent(
                $wp['modulation']
            );

        $wpPower =
            $this->FormatKW(
                $wp['power']
            );

        $heatOutput =
            $this->FormatKW(
                $wp['heatOutput']
            );

        $cop =
            $wp['cop'] !== null
                ?
                number_format(
                    (float) $wp['cop'],
                    2,
                    '.',
                    ''
                )
                :
                '—';

        $dhwTop =
            $this->FormatTemperature(
                $dhw['top']
            );

        $dhwBottom =
            $this->FormatTemperature(
                $dhw['bottom']
            );

        $bufferTop =
            $this->FormatTemperature(
                $buffer['top']
            );

        $bufferMiddle =
            $this->FormatTemperature(
                $buffer['middle']
            );

        $bufferBottom =
            $this->FormatTemperature(
                $buffer['bottom']
            );

        $meteoMaximum =
            $this->FormatTemperature(
                $meteo['maximum']
            );

        $meteoMean =
            $this->FormatTemperature(
                $meteo['mean']
            );

        $meteoMinimum =
            $this->FormatTemperature(
                $meteo['minimum']
            );

        $meteoSolar =
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
                '—';

        $solcastEnergy =
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
                '—';

        $solcastPeak =
            $this->FormatKW(
                $solcast['peak']
            );

        $solcastConfidence =
            $this->FormatPercent(
                $solcast['confidence']
            );

        $peakTime =
            $this->FormatTimestamp(
                $solcast['peakTime']
            );

        $peakWindow =
            (
                $solcast['peakWindowStart'] > 0
                &&
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

        $lastUpdate =
            date(
                'H:i',
                (int) $d['timestamp']
            );

        /*
         * ---------------------------------------------------------------------
         * HTML
         * ---------------------------------------------------------------------
         */

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

    --foreground:
        var(
            --content-color,
            #202124
        );

    --muted:
        color-mix(
            in srgb,
            var(--foreground) 62%,
            transparent
        );

    --card:
        color-mix(
            in srgb,
            var(--foreground) 7%,
            transparent
        );

    --border:
        color-mix(
            in srgb,
            var(--foreground) 16%,
            transparent
        );

    --heating:#d97f2b;
    --cooling:#2e8ec8;
    --standby:#7e8b91;
    --fault:#d84a4a;
}

@media (prefers-color-scheme: dark) {
    :root {
        --foreground:
            var(
                --content-color,
                #f3f5f7
            );
    }
}

@media (prefers-color-scheme: light) {
    :root {
        --foreground:
            var(
                --content-color,
                #202124
            );
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
    color:var(--foreground);
    font-family:
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Arial,
        sans-serif;
}

.wp-gross {
    width:100%;
    min-height:100%;
    padding:18px;
    color:var(--foreground);
}

.head {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    margin-bottom:14px;
}

.mode-wrap {
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

.mode {
    margin-top:3px;
    font-size:22px;
    line-height:1.2;
    font-weight:700;
}

.summary-grid {
    display:grid;
    grid-template-columns:
        repeat(
            4,
            minmax(0,1fr)
        );
    gap:10px;
    margin-bottom:10px;
}

.sections {
    display:grid;
    grid-template-columns:
        repeat(
            2,
            minmax(0,1fr)
        );
    gap:10px;
}

.card {
    background:var(--card);
    border:1px solid var(--border);
    border-radius:11px;
}

.summary-card {
    min-height:78px;
    padding:12px;
}

.section {
    padding:14px;
}

.section-title {
    margin-bottom:13px;
    font-size:15px;
    font-weight:700;
}

.data-grid {
    display:grid;
    grid-template-columns:
        repeat(
            2,
            minmax(0,1fr)
        );
    gap:15px 24px;
}

.data-item {
    min-height:46px;
}

.value {
    margin-top:4px;
    font-size:18px;
    line-height:1.2;
    font-weight:700;
}

.peak-window {
    margin-top:14px;
    padding-top:13px;
    border-top:1px solid var(--border);
}

.footer {
    margin-top:11px;
    text-align:right;
    font-size:12px;
    color:var(--muted);
}

@media (max-width:800px) {

    .wp-gross {
        padding:12px;
    }

    .summary-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );
    }

    .sections {
        grid-template-columns:1fr;
    }

    .label {
        font-size:12px;
    }

    .value {
        font-size:18px;
    }
}

@media (max-width:420px) {

    .wp-gross {
        padding:9px;
    }

    .summary-grid {
        gap:8px;
    }

    .data-grid {
        gap:13px 16px;
    }
}

</style>
</head>

<body>

<div class="wp-gross">

    <div class="head">

        <div class="mode-wrap">

            <div class="mode-dot {$modeCode}"></div>

            <div>

                <div class="label">
                    WP Master-Betriebsart
                </div>

                <div class="mode">
                    {$mode}
                </div>

            </div>

        </div>

    </div>


    <div class="summary-grid">

        <div class="card summary-card">
            <div class="label">
                Raumtemperatur Mittel
            </div>
            <div class="value">
                {$roomAverage}
            </div>
        </div>

        <div class="card summary-card">
            <div class="label">
                Raum Min. / Max.
            </div>
            <div class="value">
                {$roomRange}
            </div>
        </div>

        <div class="card summary-card">
            <div class="label">
                FBH Ventile offen
            </div>
            <div class="value">
                {$valves}
            </div>
        </div>

        <div class="card summary-card">
            <div class="label">
                FBH Stellwert max.
            </div>
            <div class="value">
                {$demandMaximum}
            </div>
        </div>

    </div>


    <div class="sections">

        <div class="card section">

            <div class="section-title">
                Wärmepumpe
            </div>

            <div class="data-grid">

                <div class="data-item">
                    <div class="label">
                        Aussentemperatur
                    </div>
                    <div class="value">
                        {$outside}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        Vorlauf
                    </div>
                    <div class="value">
                        {$flow}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        Rücklauf
                    </div>
                    <div class="value">
                        {$return}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        Verdichtermodulation
                    </div>
                    <div class="value">
                        {$modulation}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        Leistungsaufnahme
                    </div>
                    <div class="value">
                        {$wpPower}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        Wärmeleistung
                    </div>
                    <div class="value">
                        {$heatOutput}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        COP
                    </div>
                    <div class="value">
                        {$cop}
                    </div>
                </div>

            </div>

        </div>


        <div class="card section">

            <div class="section-title">
                Speicher
            </div>

            <div class="data-grid">

                <div class="data-item">
                    <div class="label">
                        Boiler oben
                    </div>
                    <div class="value">
                        {$dhwTop}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        Boiler unten
                    </div>
                    <div class="value">
                        {$dhwBottom}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        Puffer oben
                    </div>
                    <div class="value">
                        {$bufferTop}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        Puffer Mitte
                    </div>
                    <div class="value">
                        {$bufferMiddle}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        Puffer unten
                    </div>
                    <div class="value">
                        {$bufferBottom}
                    </div>
                </div>

            </div>

        </div>


        <div class="card section">

            <div class="section-title">
                Fussbodenheizung
            </div>

            <div class="data-grid">

                <div class="data-item">
                    <div class="label">
                        Ventile offen
                    </div>
                    <div class="value">
                        {$valves}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        Stellwert Mittel
                    </div>
                    <div class="value">
                        {$demandAverage}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        Stellwert Maximum
                    </div>
                    <div class="value">
                        {$demandMaximum}
                    </div>
                </div>

            </div>

        </div>


        <div class="card section">

            <div class="section-title">
                Prognose
            </div>

            <div class="data-grid">

                <div class="data-item">
                    <div class="label">
                        Morgen Maximum
                    </div>
                    <div class="value">
                        {$meteoMaximum}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        Morgen Mittel
                    </div>
                    <div class="value">
                        {$meteoMean}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        Morgen Minimum
                    </div>
                    <div class="value">
                        {$meteoMinimum}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        Globalstrahlung
                    </div>
                    <div class="value">
                        {$meteoSolar}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        Solcast P50 0–24 h
                    </div>
                    <div class="value">
                        {$solcastEnergy}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        PV Peak
                    </div>
                    <div class="value">
                        {$solcastPeak}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        Peak Zeitpunkt
                    </div>
                    <div class="value">
                        {$peakTime}
                    </div>
                </div>

                <div class="data-item">
                    <div class="label">
                        Vertrauen
                    </div>
                    <div class="value">
                        {$solcastConfidence}
                    </div>
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

    /*
     * =========================================================================
     * DATEN
     * =========================================================================
     */

    private function BuildVisualizationData(): array
    {
        /*
         * ---------------------------------------------------------------------
         * WP IST MASTER
         * ---------------------------------------------------------------------
         */

        $heating =
            $this->ReadBool(
                self::WP_HEATING_ID
            );

        $cooling =
            $this->ReadBool(
                self::WP_COOLING_ID
            );

        if (
            $heating
            &&
            !$cooling
        ) {
            $wpMode =
                'Heizen';

            $wpModeCode =
                'heating';

        } elseif (
            $cooling
            &&
            !$heating
        ) {
            $wpMode =
                'Kühlen';

            $wpModeCode =
                'cooling';

        } elseif (
            !$heating
            &&
            !$cooling
        ) {
            $wpMode =
                'Standby';

            $wpModeCode =
                'standby';

        } else {
            $wpMode =
                'Unplausibel';

            $wpModeCode =
                'fault';
        }

        /*
         * ---------------------------------------------------------------------
         * RÄUME
         * ---------------------------------------------------------------------
         */

        $roomTemperatures =
            [];

        foreach (
            self::ROOM_TEMP_IDS
            as $variableID
        ) {
            $value =
                $this->ReadFloatNullable(
                    $variableID
                );

            if (
                $value !== null
                &&
                $value > -30.0
                &&
                $value < 60.0
            ) {
                $roomTemperatures[] =
                    $value;
            }
        }

        $roomAverage =
            null;

        $roomMinimum =
            null;

        $roomMaximum =
            null;

        if (
            count(
                $roomTemperatures
            )
            >
            0
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

        /*
         * ---------------------------------------------------------------------
         * FBH
         * ---------------------------------------------------------------------
         */

        $openValves =
            0;

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

        $demands =
            [];

        foreach (
            self::VALVE_DEMAND_IDS
            as $variableID
        ) {
            $value =
                $this->ReadFloatNullable(
                    $variableID
                );

            if ($value !== null) {
                $demands[] =
                    $value;
            }
        }

        $demandAverage =
            null;

        $demandMaximum =
            null;

        if (
            count(
                $demands
            )
            >
            0
        ) {
            $demandAverage =
                array_sum(
                    $demands
                )
                /
                count(
                    $demands
                );

            $demandMaximum =
                max(
                    $demands
                );
        }

        return [
            'timestamp' =>
                time(),

            'settingsObjectID' =>
                self::SETTINGS_OBJECT_ID,

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

    /*
     * =========================================================================
     * QUELLVARIABLEN
     * =========================================================================
     */

    private function GetSourceVariableIDs(): array
    {
        $ids = [
            self::WP_HEATING_ID,
            self::WP_COOLING_ID,

            self::OUTSIDE_TEMP_ID,
            self::WP_FLOW_TEMP_ID,
            self::WP_RETURN_TEMP_ID,

            self::WP_POWER_ID,
            self::WP_HEAT_OUTPUT_ID,
            self::WP_COP_ID,
            self::WP_MODULATION_ID,

            self::BUFFER_TOP_ID,
            self::BUFFER_MIDDLE_ID,
            self::BUFFER_BOTTOM_ID,

            self::DHW_TOP_ID,
            self::DHW_BOTTOM_ID,

            self::METEO_MAX_ID,
            self::METEO_MEAN_ID,
            self::METEO_MIN_ID,
            self::METEO_SOLAR_ID
        ];

        foreach (
            self::ROOM_TEMP_IDS
            as $id
        ) {
            $ids[] =
                $id;
        }

        foreach (
            self::VALVE_STATE_IDS
            as $id
        ) {
            $ids[] =
                $id;
        }

        foreach (
            self::VALVE_DEMAND_IDS
            as $id
        ) {
            $ids[] =
                $id;
        }

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
            $id =
                $this->GetSolcastVariableID(
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

    /*
     * =========================================================================
     * SOLCAST
     * =========================================================================
     */

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

        $id =
            @IPS_GetObjectIDByIdent(
                $ident,
                self::SOLCAST_PARENT_ID
            );

        if (
            $id === false
            ||
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

        if ($id <= 0) {
            return null;
        }

        return
            $this->ReadFloatNullable(
                $id
            );
    }

    private function ReadSolcastInteger(
        string $ident
    ): int {
        $id =
            $this->GetSolcastVariableID(
                $ident
            );

        if ($id <= 0) {
            return 0;
        }

        return
            (int) GetValue(
                $id
            );
    }

    /*
     * =========================================================================
     * FORMATIERUNG
     * =========================================================================
     */

    private function FormatTemperature(
        ?float $value
    ): string {
        if ($value === null) {
            return '—';
        }

        return
            number_format(
                $value,
                1,
                '.',
                ''
            )
            .
            ' °C';
    }

    private function FormatKW(
        ?float $value
    ): string {
        if ($value === null) {
            return '—';
        }

        return
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
        if ($value === null) {
            return '—';
        }

        return
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
        if ($timestamp <= 0) {
            return '—';
        }

        return
            date(
                'H:i',
                $timestamp
            );
    }

    /*
     * =========================================================================
     * SICHERE LESER
     * =========================================================================
     */

    private function ReadBool(
        int $variableID
    ): bool {
        if (
            $variableID <= 0
            ||
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
            $variableID <= 0
            ||
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

        if (!is_numeric($value)) {
            return null;
        }

        return
            (float) $value;
    }
}
